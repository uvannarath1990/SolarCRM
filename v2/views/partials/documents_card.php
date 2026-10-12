<?php
/**
 * Uploaded documents for a lead — drop zone, staging list and the file list.
 *
 * Shared by the documentation portal and the CRM lead page, because the people
 * who hold the documents are not only the documentation team: a sales rep
 * standing with the customer is usually the one who can photograph the Aadhar
 * card. One implementation, so the two can never drift apart.
 *
 * Expects: $lead (row), $documents (array), $docSize (callable) and the
 * doc-upload.js asset loaded by the page.
 */
if (!function_exists('docSize')) {
    function docSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024) . ' KB';
        return $bytes . ' B';
    }
}
?>
<!-- ── Documents ────────────────────────────────────────────────────────── -->
<div class="v2-card" id="docUploader" data-lead-id="<?= (int) $lead['id'] ?>"
     style="margin-top:12px;padding:16px">
    <div class="v2-section" style="margin-top:0">
        <h2>Documents</h2>
        <span class="rule"></span>
        <span class="pill pill-muted" id="docCount"><?= count($documents) ?></span>
    </div>

    <?php // Drop zone doubles as a file picker and, on a phone, a camera button.
          // Documentation staff photograph papers far more often than they browse
          // a filesystem, so both are offered side by side. ?>
    <div id="dropZone" class="drop-zone" tabindex="0" role="button"
         aria-label="Add documents">
        <i class="bi bi-cloud-arrow-up"></i>
        <div class="dz-main">Drop files here or tap to choose</div>
        <div class="dz-sub">PDF, photos or Office files · up to 25 MB each</div>
    </div>

    <div class="dz-actions">
        <button type="button" class="v2-btn v2-btn-sm" id="pickBtn">
            <i class="bi bi-folder2-open"></i>Choose files
        </button>
        <button type="button" class="v2-btn v2-btn-sm" id="cameraBtn">
            <i class="bi bi-camera-fill"></i>Take photo
        </button>
    </div>

    <input type="file" id="fileInput" multiple hidden
           accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.doc,.docx,.xls,.xlsx,.zip">
    <input type="file" id="cameraInput" accept="image/*" capture="environment" multiple hidden>

    <?php // Staging list: every file gets its own name before anything is sent. ?>
    <div id="queue" class="doc-queue" hidden></div>

    <div id="queueBar" hidden style="display:flex;gap:8px;margin-top:10px">
        <button type="button" class="v2-btn v2-btn-primary v2-btn-sm" id="uploadBtn">
            <i class="bi bi-upload"></i>Upload <span id="queueCount"></span>
        </button>
        <button type="button" class="v2-btn v2-btn-ghost v2-btn-sm" id="clearBtn">Clear</button>
    </div>

    <div id="uploadError" class="v2-flash v2-flash-danger" style="margin-top:10px;display:none"></div>

    <div id="docList" style="margin-top:14px">
    <?php foreach ($documents as $d):
        $isImg = str_starts_with($d['mime_type'], 'image/');
        $saved = $d['original_size_bytes'] && $d['original_size_bytes'] > $d['size_bytes']
               ? (int) round(100 - ($d['size_bytes'] / $d['original_size_bytes'] * 100)) : 0;
    ?>
        <div class="doc-item" data-id="<?= (int) $d['id'] ?>">
            <?php if ($isImg): ?>
            <img class="doc-thumb" loading="lazy"
                 src="<?= BASE_URL ?>/doc-portal/file?id=<?= (int) $d['id'] ?>&mode=view"
                 alt="<?= htmlspecialchars($d['label']) ?>"
                 data-full="<?= BASE_URL ?>/doc-portal/file?id=<?= (int) $d['id'] ?>&mode=view">
            <?php else: ?>
            <div class="doc-thumb doc-thumb-file">
                <i class="bi bi-<?= str_contains($d['mime_type'], 'pdf') ? 'file-earmark-pdf' : 'file-earmark' ?>"></i>
                <span><?= htmlspecialchars(strtoupper($d['extension'])) ?></span>
            </div>
            <?php endif; ?>

            <div class="doc-meta">
                <div class="doc-label" data-id="<?= (int) $d['id'] ?>" title="Tap to rename">
                    <?= htmlspecialchars($d['label']) ?>
                </div>
                <div class="doc-sub">
                    <?= docSize((int) $d['size_bytes']) ?>
                    <?php if ($saved >= 5): ?><span style="color:var(--ok)">· −<?= $saved ?>%</span><?php endif; ?>
                    · <?= htmlspecialchars($d['uploader_name'] ?? 'Unknown') ?>
                    · <?= date('d M', strtotime($d['created_at'])) ?>
                </div>
            </div>

            <div class="doc-acts">
                <?php if ($isImg): ?>
                <button type="button" class="doc-act doc-preview"
                        data-src="<?= BASE_URL ?>/doc-portal/file?id=<?= (int) $d['id'] ?>&mode=view"
                        data-title="<?= htmlspecialchars($d['label']) ?>" title="Preview">
                    <i class="bi bi-eye"></i>
                </button>
                <?php else: ?>
                <a class="doc-act" target="_blank" rel="noopener" title="Open"
                   href="<?= BASE_URL ?>/doc-portal/file?id=<?= (int) $d['id'] ?>&mode=view">
                    <i class="bi bi-eye"></i>
                </a>
                <?php endif; ?>
                <a class="doc-act" title="Download"
                   href="<?= BASE_URL ?>/doc-portal/file?id=<?= (int) $d['id'] ?>&mode=download">
                    <i class="bi bi-download"></i>
                </a>
                <button type="button" class="doc-act doc-del" data-id="<?= (int) $d['id'] ?>" title="Delete">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <div id="docEmpty" class="text-muted-2" style="font-size:13.5px;padding:8px 0"
         <?= $documents ? 'hidden' : '' ?>>
        Nothing uploaded yet.
    </div>
</div>

<?php // Full-screen preview, so looking at a scan does not mean losing the page. ?>
<div id="lightbox" class="doc-lightbox" hidden>
    <div class="lb-bar">
        <span id="lbTitle"></span>
        <button type="button" id="lbClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>
    <img id="lbImg" alt="">
</div>
