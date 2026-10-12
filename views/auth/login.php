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
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">

<div class="card shadow-sm" style="width:100%;max-width:400px">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <i class="bi bi-sun-fill text-warning fs-1"></i>
            <h4 class="mt-2 mb-0 fw-bold"><?= htmlspecialchars(getSetting('company_name', APP_NAME)) ?></h4>
            <p class="text-muted small">Solar CRM — Sign In</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/login" novalidate>
            <?= csrfField() ?>
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
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
