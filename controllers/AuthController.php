<?php
declare(strict_types=1);

class AuthController
{
    public function loginPage(): void
    {
        if (isLoggedIn()) {
            redirect(hasRole('partner') ? '/partner/dashboard' : '/today');
        }

        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrfVerify();

            $email    = trim(post('email'));
            $password = post('password');

            if ($email === '' || $password === '') {
                $error = 'Please enter your email and password.';
            } else {
                $pdo  = getPdo();
                $stmt = $pdo->prepare(
                    'SELECT `id`,`name`,`email`,`role`,`password_hash`,`is_active`,
                            `failed_attempts`,`locked_until`
                     FROM `users` WHERE `email` = ? LIMIT 1'
                );
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user) {
                    $error = 'Invalid email or password.';
                } elseif (!$user['is_active']) {
                    $error = 'Your account is deactivated. Please contact the administrator.';
                } elseif ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $mins  = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
                    $error = "Account temporarily locked. Try again in {$mins} minute(s).";
                } elseif (!password_verify($password, $user['password_hash'])) {
                    // Increment failed attempts
                    $attempts = (int) $user['failed_attempts'] + 1;
                    if ($attempts >= 5) {
                        $pdo->prepare(
                            'UPDATE `users` SET `failed_attempts`=?, `locked_until`=DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE `id`=?'
                        )->execute([$attempts, $user['id']]);
                        $error = 'Too many failed attempts. Account locked for 30 minutes.';
                    } else {
                        $pdo->prepare(
                            'UPDATE `users` SET `failed_attempts`=? WHERE `id`=?'
                        )->execute([$attempts, $user['id']]);
                        $remaining = 5 - $attempts;
                        $error     = "Invalid email or password. {$remaining} attempt(s) remaining.";
                    }
                } else {
                    // Successful login
                    $pdo->prepare(
                        'UPDATE `users` SET `failed_attempts`=0, `locked_until`=NULL, `last_login`=NOW() WHERE `id`=?'
                    )->execute([$user['id']]);
                    loginUser($user);
                    auditLogin($user['email']);
                    redirect($user['role'] === 'partner' ? '/partner/dashboard' : '/today');
                }
            }
        }

        $pageTitle = 'Login';
        require __DIR__ . '/../views/auth/login.php';
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/dashboard');
        }
        csrfVerify();
        logoutUser();
        redirect('/login');
    }
}
