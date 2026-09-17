<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect((defined('BASE_URL') ? BASE_URL : '') . '/dashboard.php');
}

$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

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
            && password_verify($password, (string) ($user['password_hash'] ?? ''));

        if ($valid) {
            login_user($user);

            // Upgrade hashes created with an older/default cost when the user logs in.
            if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([$newHash, $user['id']]);
            }

            db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
                ->execute([$user['id']]);

            AuditService::log('LOGIN', 'auth', (int) $user['id'], 'Successful login');
            redirect((defined('BASE_URL') ? BASE_URL : '') . '/dashboard.php');
        }

        // Keep the message generic so account existence/status is not disclosed.
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
    <title>DCOS 2077 — Login</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="login-bg">
    <main class="login-card" aria-labelledby="login-title">
        <div class="brand big">DCOS <span>2077</span></div>
        <h1 id="login-title">Digital College Operating System</h1>
        <p>Secure, connected, role-based campus access.</p>

        <?php if ($err !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= e($err) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="on" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <label for="email">Email</label>
            <input
                id="email"
                class="form-control mb-3"
                name="email"
                type="email"
                inputmode="email"
                autocomplete="username"
                maxlength="190"
                required
                autofocus
            >

            <label for="password">Password</label>
            <input
                id="password"
                class="form-control mb-3"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >

            <button class="btn btn-primary w-100" type="submit">Enter Digital Campus</button>
        </form>

        <a href="forgot.php" class="d-block mt-3">Forgot password?</a>
    </main>
</body>
</html>
