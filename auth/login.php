<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect((defined('BASE_URL') ? BASE_URL : '') . '/dashboard.php');
}

$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $err = 'Please enter a valid email and password.';
    } elseif (!rate_limit_key('login|' . $email . '|' . client_ip(), 8, 60)) {
        $err = 'Too many login attempts. Please wait a minute and try again.';
    } else {
        $query = db()->prepare(
            'SELECT u.*, r.name AS role_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.email = ?
             LIMIT 1'
        );
        $query->execute([$email]);
        $user = $query->fetch();

        $valid = is_array($user)
            && ($user['status'] ?? '') === 'active'
            && password_verify($password, (string)($user['password_hash'] ?? ''));

        if ($valid) {
            login_user($user);

            if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $user['id']]);
            }

            db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
            AuditService::log('LOGIN', 'auth', (int)$user['id'], 'Successful login');
            redirect((defined('BASE_URL') ? BASE_URL : '') . '/dashboard.php');
        }

        $err = 'Invalid email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#4f46e5">
    <title>Sign in · DCOS 2077</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="login-bg">
    <main class="login-card" aria-labelledby="login-title">
        <div class="brand-wrap mb-4">
            <div class="brand-mark"><i class="fa-solid fa-atom" aria-hidden="true"></i></div>
            <div>
                <div class="brand big">DCOS <span>2077</span></div>
                <div class="brand-mini text-muted">DIGITAL COLLEGE OPERATING SYSTEM</div>
            </div>
        </div>

        <div class="mb-4">
            <span class="pill text-dark border">Secure Campus Access</span>
            <h1 id="login-title" class="mt-3 mb-2">Welcome back</h1>
            <p class="text-muted mb-0">Sign in to continue to your role-based campus portal.</p>
        </div>

        <?php if ($err !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fa-solid fa-circle-exclamation me-1" aria-hidden="true"></i>
                <?= e($err) ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="mb-3">
                <label class="form-label" for="email">Email address</label>
                <input id="email" class="form-control" name="email" type="email" inputmode="email" autocomplete="username" maxlength="190" required autofocus placeholder="you@example.com">
            </div>

            <div class="mb-2">
                <label class="form-label" for="password">Password</label>
                <input id="password" class="form-control" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password">
            </div>

            <div class="d-flex justify-content-end mb-4">
                <a href="forgot.php" class="small">Forgot password?</a>
            </div>

            <button class="btn btn-primary w-100 py-2" type="submit">
                <i class="fa-solid fa-arrow-right-to-bracket me-2" aria-hidden="true"></i>Sign in securely
            </button>
        </form>

        <div class="mt-4 pt-3 border-top small text-muted text-center">
            Access is protected with secure authentication and role-based permissions.
        </div>
    </main>
</body>
</html>
