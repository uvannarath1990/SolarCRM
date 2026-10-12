<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
    <title>Login — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/v2.css') ?>">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">

<div class="card shadow-sm" style="width:100%;max-width:400px">
    <div class="card-body p-4">
        <?php $isDoc = ($mode ?? 'employee') === 'doc'; ?>

        <div class="text-center mb-3">
            <i class="bi bi-<?= $isDoc ? 'folder2-open' : 'sun-fill' ?> fs-1"
               style="color:<?= $isDoc ? 'var(--info)' : 'var(--brand)' ?>"></i>
            <h4 class="mt-2 mb-0 fw-bold"><?= htmlspecialchars(getSetting('company_name', APP_NAME)) ?></h4>
            <p class="text-muted small mb-0" id="loginSubtitle">
                <?= $isDoc ? 'Documentation Portal — Sign In' : 'Solar CRM — Sign In' ?>
            </p>
        </div>

        <?php // Two tabs, one form, one POST — the lockout counter must stay on a
              // single code path, so this only switches a hidden field. ?>
        <div class="login-tabs mb-3">
            <button type="button" class="login-tab <?= $isDoc ? '' : 'on' ?>" data-mode="employee">
                Employee
            </button>
            <button type="button" class="login-tab <?= $isDoc ? 'on' : '' ?>" data-mode="doc">
                Documentation
            </button>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/login" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="mode" id="loginMode" value="<?= $isDoc ? 'doc' : 'employee' ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold" for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= htmlspecialchars(post('email')) ?>"
                       autocomplete="username" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold" for="password">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password"
                           autocomplete="current-password" required>
                    <button type="button" class="btn btn-outline-secondary" id="togglePw" tabindex="-1">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100" id="loginSubmit">
                <?= $isDoc ? 'Sign in to Documentation' : 'Sign In' ?>
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var tabs     = document.querySelectorAll('.login-tab');
    var modeInp  = document.getElementById('loginMode');
    var subtitle = document.getElementById('loginSubtitle');
    var submit   = document.getElementById('loginSubmit');
    var icon     = document.getElementById('brandIcon');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var doc = tab.dataset.mode === 'doc';
            tabs.forEach(function (t) { t.classList.toggle('on', t === tab); });
            modeInp.value = doc ? 'doc' : 'employee';
            subtitle.textContent = doc ? 'Documentation Portal — Sign In' : 'Solar CRM — Sign In';
            submit.textContent   = doc ? 'Sign in to Documentation' : 'Sign In';
        });
    });
})();

document.getElementById('togglePw').addEventListener('click', function () {
    const pw   = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pw.type === 'password') {
        pw.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        pw.type = 'password';
        icon.className = 'bi bi-eye';
    }
});
</script>
</body>
</html>
