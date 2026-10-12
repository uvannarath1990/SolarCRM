<?php
declare(strict_types=1);

class LeadsImportController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    // ── Show import form ──────────────────────────────────────────────────────
    public function index(): void
    {
        requireRole('admin', 'manager');

        $reps = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='sales_rep' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();

        $teleCallers = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='tele_caller' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();

        $sources = $this->pdo->query(
            "SELECT `id`,`name` FROM `lead_sources` WHERE `is_active`=1 ORDER BY `sort_order`,`name`"
        )->fetchAll();

        $pageTitle = 'Import Leads';
        require __DIR__ . '/../views/leads/import.php';
    }

    // ── Download CSV template ─────────────────────────────────────────────────
    public function template(): void
    {
        requireRole('admin', 'manager');

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="leads_import_template.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'name', 'phone', 'email', 'location',
            'lead_source', 'lead_source_sub', 'bill_amount', 'potential_kw',
            'is_new_construction', 'visit_scheduled_date',
            'lead_stage', 'quote_amount',
        ]);
        // Example rows. lead_stage must be one of the stage names exactly as shown
        // in the app (case doesn't matter); Quoted needs a quote_amount.
        fputcsv($out, ['John Smith',  '9876543210', 'john@example.com', 'Kochi',   'Referral',     '', 5000, '',  'no',  '',           'Contacted', '']);
        fputcsv($out, ['Priya Nair',  '9123456780', '',                 'Calicut', 'Social Media', '', 8000, 6.5, 'yes', '2026-10-01', 'Quoted',    '250000']);
        fputcsv($out, ['Raju Thomas', '9000012345', '',                 '',        '',             '', '',   '',  '',    '',           '',          '']);
        fclose($out);
        exit;
    }

    // ── Process upload ────────────────────────────────────────────────────────
    public function upload(): void
    {
        requireRole('admin', 'manager');
        csrfVerify();

        // Global assignment options
        $repId       = postInt('assigned_rep_id') ?: null;
        $tcId        = postInt('tele_caller_id') ?: null;
        $visitDate   = post('visit_scheduled_date') ?: null;
        if ($visitDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate)) $visitDate = null;

        // Validate file
        if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            flash('danger', 'Please select a CSV file.');
            redirect('/leads/import');
        }

        $origName = $_FILES['csv_file']['name'] ?? '';
        if (strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'csv') {
            flash('danger', 'Only .csv files are allowed.');
            redirect('/leads/import');
        }

        $file  = $_FILES['csv_file']['tmp_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file);
        $allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($mime, $allowedMimes, true)) {
            flash('danger', 'Uploaded file is not a valid CSV.');
            redirect('/leads/import');
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            flash('danger', 'Could not read the uploaded file.');
            redirect('/leads/import');
        }

        // Build source lookup map (name → id, case-insensitive)
        $sourceMap = [];
        $srcRows = $this->pdo->query("SELECT `id`,`name` FROM `lead_sources`")->fetchAll();
        foreach ($srcRows as $s) {
            $sourceMap[strtolower(trim($s['name']))] = (int) $s['id'];
        }

        // Build subsource lookup map (source_id + sub_name → sub_id, case-insensitive)
        $subMap = [];
        $subRows = $this->pdo->query("SELECT `id`,`source_id`,`name` FROM `lead_source_subs` WHERE `is_active`=1")->fetchAll();
        foreach ($subRows as $sub) {
            $subMap[(int)$sub['source_id']][strtolower(trim($sub['name']))] = (int) $sub['id'];
        }

        // Read & validate header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            flash('danger', 'The CSV file is empty.');
            redirect('/leads/import');
        }
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        $required = ['name', 'phone'];
        foreach ($required as $col) {
            if (!in_array($col, $headers, true)) {
                flash('danger', "CSV must have a '{$col}' column.");
                redirect('/leads/import');
            }
        }

        $col = array_flip($headers); // column name → index

        require_once __DIR__ . '/../includes/workflow.php';

        // Opt-in: rows whose phone already exists update that lead instead of being skipped
        $updateExisting = (bool) post('update_existing');

        // Stage names are matched case-insensitively onto the canonical spelling
        $stageMap = [];
        foreach (allStages() as $st) $stageMap[strtolower($st)] = $st;

        $results   = [];
        $imported  = 0;
        $updated   = 0;
        $skipped   = 0;
        $rowNum    = 1;

        $insertStmt = $this->pdo->prepare(
            'INSERT INTO `leads`
                (`name`,`phone`,`email`,`location`,`source_id`,`source_sub_id`,`bill_amount`,`potential_kw`,
                 `quote_amount`,`is_new_construction`,`assigned_rep_id`,`tele_caller_id`,
                 `visit_scheduled_date`,`stage`,`created_by`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $findByPhone = $this->pdo->prepare('SELECT * FROM `leads` WHERE `phone`=? AND `deleted_at` IS NULL LIMIT 1');
        $dupAny      = $this->pdo->prepare('SELECT `id` FROM `leads` WHERE `phone`=? LIMIT 1');

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count(array_filter($row)) === 0) continue; // skip blank rows

            $get = fn(string $k) => isset($col[$k]) && isset($row[$col[$k]])
                ? trim($row[$col[$k]]) : '';

            $name  = $get('name');
            $phone = preg_replace('/\D/', '', $get('phone'));
            if (strlen($phone) === 12 && str_starts_with($phone, '91')) $phone = substr($phone, 2);

            $fail = function (string $status, string $reason) use (&$results, &$skipped, $rowNum, $name, $phone) {
                $results[] = ['row' => $rowNum, 'status' => $status, 'name' => $name ?: '—',
                              'phone' => $phone ?: '—', 'reason' => $reason];
                $skipped++;
            };

            if ($phone === '')          { $fail('error', 'Phone is required'); continue; }
            if (strlen($phone) !== 10)  { $fail('error', 'Phone must be 10 digits'); continue; }

            // ── Parse the optional columns once; blanks mean "not provided" ──
            $warnings = [];

            $stageRaw = $get('lead_stage');
            $stage    = null;
            if ($stageRaw !== '') {
                $stage = $stageMap[strtolower($stageRaw)] ?? null;
                if ($stage === null) {
                    $fail('error', "Unknown stage '{$stageRaw}' — use one of: " . implode(', ', allStages()));
                    continue;
                }
            }

            $srcRaw   = $get('lead_source');
            $sourceId = null;
            if ($srcRaw !== '') {
                $sourceId = $sourceMap[strtolower($srcRaw)] ?? null;
                if ($sourceId === null) $warnings[] = "source '{$srcRaw}' not found — left blank";
            }
            $subRaw = $get('lead_source_sub');

            $bill  = $get('bill_amount')  !== '' ? (float) $get('bill_amount')  : null;
            $kw    = $get('potential_kw') !== '' ? (float) $get('potential_kw') : null;
            $quote = $get('quote_amount') !== '' ? (float) preg_replace('/[^0-9.]/', '', $get('quote_amount')) : null;

            $newConstRaw = strtolower($get('is_new_construction'));
            $isNewConst  = $newConstRaw === '' ? null
                         : (in_array($newConstRaw, ['yes','1','true','y'], true) ? 1 : 0);

            $rowVisitDate = $get('visit_scheduled_date') ?: null;
            if ($rowVisitDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rowVisitDate)) {
                $warnings[]   = "visit date '{$rowVisitDate}' not in YYYY-MM-DD — ignored";
                $rowVisitDate = null;
            }

            $findByPhone->execute([$phone]);
            $existing = $findByPhone->fetch();

            // A deleted lead still holds its phone number, so it can't be re-created
            if (!$existing) {
                $dupAny->execute([$phone]);
                if ($dupAny->fetch()) { $fail('skip', 'Phone belongs to a deleted lead'); continue; }
            }

            // ══ Existing lead ═══════════════════════════════════════════════════
            if ($existing) {
                if (!$updateExisting) { $fail('skip', 'Phone already exists'); continue; }

                $leadId  = (int) $existing['id'];
                $changes = [];   // column => new value; only non-blank CSV cells

                if ($name !== '')         $changes['name']     = $name;
                if ($get('email') !== '') $changes['email']    = $get('email');
                if ($get('location') !== '') $changes['location'] = $get('location');
                if ($sourceId)            { $changes['source_id'] = $sourceId; $changes['source_sub_id'] = null; }
                $subSource = $sourceId ?? (int) $existing['source_id'];
                if ($subRaw !== '' && $subSource) {
                    $subId = $subMap[$subSource][strtolower($subRaw)] ?? null;
                    if ($subId) $changes['source_sub_id'] = $subId;
                    else        $warnings[] = "sub-source '{$subRaw}' not found — left unchanged";
                }
                if ($bill  !== null)      $changes['bill_amount']  = $bill;
                if ($kw    !== null)      $changes['potential_kw'] = $kw;
                if ($quote !== null)      $changes['quote_amount'] = $quote;
                if ($isNewConst !== null) $changes['is_new_construction'] = $isNewConst;
                if ($rowVisitDate)        $changes['visit_scheduled_date'] = $rowVisitDate;

                // Drop anything that wouldn't actually change
                foreach ($changes as $c => $v) {
                    if ((string) ($existing[$c] ?? '') === (string) ($v ?? '')) unset($changes[$c]);
                }

                try {
                    if ($changes) {
                        $set = implode(', ', array_map(fn($c) => "`{$c}`=?", array_keys($changes)));
                        $this->pdo->prepare("UPDATE `leads` SET {$set}, `updated_at`=NOW() WHERE `id`=?")
                                  ->execute([...array_values($changes), $leadId]);
                        foreach ($changes as $c => $v) auditUpdate($leadId, $c, $existing[$c] ?? null, $v);
                    }

                    // Stage last, so a quote_amount in the same row already counts
                    $stageNote = '';
                    if ($stage !== null && $stage !== $existing['stage']) {
                        $fresh = $this->pdo->prepare('SELECT * FROM `leads` WHERE `id`=?');
                        $fresh->execute([$leadId]);
                        $why = applyLeadStage($this->pdo, $fresh->fetch(), $stage);
                        if ($why) $warnings[] = "stage not changed: {$why}";
                        else      $stageNote = "stage → {$stage}";
                    }

                    if (!$changes && $stageNote === '') {
                        $results[] = ['row' => $rowNum, 'status' => 'skip', 'name' => $existing['name'], 'phone' => $phone,
                                      'lead_id' => $leadId, 'reason' => 'No changes' . ($warnings ? ' — ' . implode('; ', $warnings) : '')];
                        $skipped++;
                        continue;
                    }

                    $detail = array_filter([
                        $changes ? 'updated: ' . implode(', ', array_keys($changes)) : '',
                        $stageNote,
                    ]);
                    $results[] = ['row' => $rowNum, 'status' => 'updated', 'name' => $existing['name'], 'phone' => $phone,
                                  'lead_id' => $leadId, 'reason' => implode(' · ', $detail),
                                  'warnings' => $warnings];
                    $updated++;
                } catch (\PDOException $e) {
                    $fail('error', 'DB error: ' . $e->getMessage());
                }
                continue;
            }

            // ══ New lead ════════════════════════════════════════════════════════
            if ($name === '') { $fail('error', 'Name is required for a new lead'); continue; }

            $sourceSubId = ($sourceId && $subRaw !== '') ? ($subMap[$sourceId][strtolower($subRaw)] ?? null) : null;
            if ($sourceId && $subRaw !== '' && !$sourceSubId) $warnings[] = "sub-source '{$subRaw}' not found — left blank";
            if ($kw === null) $kw = kwFromBill($bill);

            $effectiveVisitDate = $rowVisitDate ?? $visitDate;

            // With an explicit stage, the lead starts at New and is then moved there
            // through applyLeadStage(), so it gets that stage's tasks and side-effects.
            // Without one, the existing rep + date → Visit Scheduled rule applies.
            $initialStage = $stage !== null ? 'New'
                          : (($repId && $effectiveVisitDate) ? 'Visit Scheduled' : 'New');

            try {
                $insertStmt->execute([
                    $name, $phone, $get('email') ?: null, $get('location') ?: null,
                    $sourceId, $sourceSubId, $bill, $kw, $quote, $isNewConst ?? 0,
                    $repId, $tcId, $effectiveVisitDate, $initialStage, $_SESSION['user_id'],
                ]);
                $leadId = (int) $this->pdo->lastInsertId();
                auditCreate($leadId, "Lead imported: {$name}");

                if ($stage !== null && $stage !== 'New') {
                    $fresh = $this->pdo->prepare('SELECT * FROM `leads` WHERE `id`=?');
                    $fresh->execute([$leadId]);
                    $why = applyLeadStage($this->pdo, $fresh->fetch(), $stage);
                    if ($why) $warnings[] = "created at New — {$why}";
                } elseif ($stage === null && $repId && $effectiveVisitDate) {
                    $this->pdo->prepare(
                        "INSERT INTO `workflow_tasks`
                            (`lead_id`,`stage`,`title`,`assigned_to`,`assigned_role`,`due_date`)
                         VALUES (?,?,?,?,?,?)"
                    )->execute([
                        $leadId, 'Visit Scheduled',
                        'Conduct site visit and follow up with customer',
                        $repId, 'sales_rep', $effectiveVisitDate,
                    ]);
                    auditTaskCreate((int) $this->pdo->lastInsertId(), $leadId,
                                    'Conduct site visit and follow up with customer', 'lead import');
                }

                $results[] = ['row' => $rowNum, 'status' => 'ok', 'name' => $name, 'phone' => $phone,
                              'lead_id' => $leadId, 'warnings' => $warnings];
                $imported++;

            } catch (\PDOException $e) {
                $fail('error', 'DB error: ' . $e->getMessage());
            }
        }

        fclose($handle);

        $pageTitle   = 'Import Results';
        $reps        = $this->pdo->query("SELECT `id`,`name` FROM `users` WHERE `role`='sales_rep' AND `is_active`=1 ORDER BY `name`")->fetchAll();
        $teleCallers = $this->pdo->query("SELECT `id`,`name` FROM `users` WHERE `role`='tele_caller' AND `is_active`=1 ORDER BY `name`")->fetchAll();
        $sources     = [];
        require __DIR__ . '/../views/leads/import.php';
    }
}
