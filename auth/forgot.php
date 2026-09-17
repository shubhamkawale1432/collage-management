<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$message = 'If that email is registered, a password-reset link has been initiated.';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'If that email is registered, a password-reset link has been initiated.';
    } elseif (!rate_limit_key('forgot|' . $email . '|' . client_ip(), 3, 300)) {
        $message = 'If that email is registered, a password-reset link has been initiated.';
    } else {
        $query = db()->prepare('SELECT id, email, name FROM users WHERE email = ? LIMIT 1');
        $query->execute([$email]);
        $user = $query->fetch();

        if (is_array($user)) {
            // Invalidate older reset tokens for this account before issuing a new one.
            db()->prepare('DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()')
                ->execute([$user['id']]);

            $token = secure_random(32);
            $hash = password_hash($token, PASSWORD_DEFAULT);

            db()->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
            )->execute([$user['id'], $hash]);

            $resetUrl = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/')
                . '/auth/reset.php?token=' . rawurlencode($token);

            // EmailService handles the configured delivery mechanism.
            EmailService::send(
                (string) $user['email'],
                'Password Reset',
                '<p>Hello ' . e($user['name'] ?? 'User') . ',</p>'
                . '<p>A password reset was requested for your account.</p>'
                . '<p><a href="' . e($resetUrl) . '">Reset your password</a></p>'
                . '<p>This link expires in 30 minutes. If you did not request this, you can ignore this email.</p>'
            );
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="login-bg">
    <main class="login-card" aria-labelledby="reset-title">
        <div class="brand big">DCOS <span>2077</span></div>
        <h1 id="reset-title">Reset Password</h1>
        <p><?= e($message) ?></p>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label for="email">Account email</label>
            <input
                id="email"
                class="form-control mb-3"
                name="email"
                type="email"
                inputmode="email"
                autocomplete="email"
                maxlength="190"
                required
                autofocus
            >
            <button class="btn btn-primary w-100" type="submit">Send Reset Link</button>
        </form>

        <a href="login.php" class="d-block mt-3">Back to login</a>
    </main>
</body>
</html>
