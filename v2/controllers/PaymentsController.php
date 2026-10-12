<?php
declare(strict_types=1);

class PaymentsController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    public function index(): void
    {
        requireRole('admin', 'operation_manager', 'accounts');

        $q      = trim(get('q'));
        $status = get('status', 'all'); // all | completed | pending

        $where  = ["l.`stage` = 'Won'", "l.`deleted_at` IS NULL"];
        $params = [];

        if ($q !== '') {
            $where[]  = '(l.`name` LIKE ? OR l.`phone` LIKE ?)';
            $like     = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if ($status === 'completed') {
            $where[] = "lf.`payment_status` = 'Payment Completed'";
        } elseif ($status === 'pending') {
            $where[] = "(lf.`payment_status` IS NULL OR lf.`payment_status` != 'Payment Completed')";
        }

        $whereStr = implode(' AND ', $where);

        $stmt = $this->pdo->prepare(
            "SELECT l.`id`, l.`name`, l.`phone`, l.`location`, l.`potential_kw`,
                    lf.`total_amount`, lf.`discount`, lf.`payment_status`, lf.`equipment_brand`,
                    COALESCE(pi.`total_paid`, 0) AS total_paid
             FROM `leads` l
             LEFT JOIN `lead_financials` lf ON lf.`lead_id` = l.`id`
             LEFT JOIN (
                 SELECT `lead_id`, SUM(`amount`) AS `total_paid`
                 FROM `lead_payment_installments`
                 GROUP BY `lead_id`
             ) pi ON pi.`lead_id` = l.`id`
             WHERE {$whereStr}
             ORDER BY l.`name` ASC"
        );
        $stmt->execute($params);
        $payments = $stmt->fetchAll();

        $pageTitle = 'Payments';
        require __DIR__ . '/../views/payments/list.php';
    }

    public function saveFinancials(): void
    {
        requireRole('admin', 'operation_manager', 'accounts');
        csrfVerify();

        $leadId           = postInt('lead_id');
        $totalAmount      = post('total_amount') !== '' ? (float) post('total_amount') : 0;
        $discount         = post('discount') !== '' ? (float) post('discount') : 0;
        $paymentStatus    = trim(post('payment_status')) ?: null;
        $feasibilityStatus = trim(post('feasibility_status')) ?: null;
        $structureType    = trim(post('structure_type')) ?: null;
        $equipmentBrand   = trim(post('equipment_brand')) ?: null;
        $notes            = trim(post('notes')) ?: null;

        if ($leadId <= 0) {
            jsonError('Invalid lead.', 400);
        }

        // Verify lead is Won
        $check = $this->pdo->prepare("SELECT `id` FROM `leads` WHERE `id`=? AND `stage`='Won' LIMIT 1");
        $check->execute([$leadId]);
        if (!$check->fetch()) {
            jsonError('Financials can only be added to Won leads.', 400);
        }

        $this->pdo->prepare(
            'INSERT INTO `lead_financials`
                (`lead_id`,`total_amount`,`discount`,`payment_status`,`feasibility_status`,
                 `structure_type`,`equipment_brand`,`notes`)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                `total_amount`=VALUES(`total_amount`),
                `discount`=VALUES(`discount`),
                `payment_status`=VALUES(`payment_status`),
                `feasibility_status`=VALUES(`feasibility_status`),
                `structure_type`=VALUES(`structure_type`),
                `equipment_brand`=VALUES(`equipment_brand`),
                `notes`=VALUES(`notes`)'
        )->execute([
            $leadId, $totalAmount, $discount, $paymentStatus, $feasibilityStatus,
            $structureType, $equipmentBrand, $notes,
        ]);

        // Return updated totals
        $pi = $this->pdo->prepare(
            'SELECT COALESCE(SUM(`amount`),0) FROM `lead_payment_installments` WHERE `lead_id`=?'
        );
        $pi->execute([$leadId]);
        $totalPaid = (float) $pi->fetchColumn();
        $balance   = $totalAmount - $discount - $totalPaid;

        jsonResponse([
            'ok'            => true,
            'total_amount'  => $totalAmount,
            'discount'      => $discount,
            'total_paid'    => $totalPaid,
            'balance'       => $balance,
        ]);
    }

    public function saveInstallment(): void
    {
        requireRole('admin', 'operation_manager', 'accounts');
        csrfVerify();

        $leadId   = postInt('lead_id');
        $type     = post('installment_type', 'advance');
        $amount   = post('amount') !== '' ? (float) post('amount') : 0;
        $date     = post('received_date');
        $chequeStatus = post('cheque_status', 'none');
        $chequeAmount = post('cheque_amount') !== '' ? (float) post('cheque_amount') : null;
        $notes    = trim(post('notes')) ?: null;

        $allowedTypes   = ['advance', 'installation', 'commissioning', 'other'];
        $allowedCheque  = ['none', 'received', 'cleared', 'bounced'];

        if ($leadId <= 0 || $date === '' || $amount <= 0) {
            jsonError('Lead, date and amount are required.');
        }
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'advance';
        }
        if (!in_array($chequeStatus, $allowedCheque, true)) {
            $chequeStatus = 'none';
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `lead_payment_installments`
                (`lead_id`,`installment_type`,`amount`,`received_date`,
                 `cheque_status`,`cheque_amount`,`notes`,`created_by`)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $leadId, $type, $amount, $date,
            $chequeStatus, $chequeAmount, $notes, (int) $_SESSION['user_id'],
        ]);
        $newId = (int) $this->pdo->lastInsertId();

        // Recalculate totals
        $fin = $this->pdo->prepare('SELECT * FROM `lead_financials` WHERE `lead_id`=? LIMIT 1');
        $fin->execute([$leadId]);
        $financials = $fin->fetch();
        $totalAmount = $financials ? (float) $financials['total_amount'] : 0;
        $discount    = $financials ? (float) $financials['discount'] : 0;

        $pi = $this->pdo->prepare(
            'SELECT COALESCE(SUM(`amount`),0) FROM `lead_payment_installments` WHERE `lead_id`=?'
        );
        $pi->execute([$leadId]);
        $totalPaid = (float) $pi->fetchColumn();
        $balance   = $totalAmount - $discount - $totalPaid;

        jsonResponse([
            'ok'              => true,
            'id'              => $newId,
            'installment_type' => $type,
            'amount'          => $amount,
            'received_date'   => $date,
            'cheque_status'   => $chequeStatus,
            'cheque_amount'   => $chequeAmount,
            'notes'           => $notes,
            'total_paid'      => $totalPaid,
            'balance'         => $balance,
        ]);
    }

    public function deleteInstallment(): void
    {
        requireRole('admin');
        csrfVerify();

        $id = postInt('id');
        if ($id <= 0) {
            jsonError('Invalid installment.', 400);
        }
        $this->pdo->prepare('DELETE FROM `lead_payment_installments` WHERE `id`=?')->execute([$id]);
        jsonResponse(['ok' => true]);
    }
}
