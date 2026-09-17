<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$email = strtolower(trim((string) ($_GET['email'] ?? $_POST['email'] ?? '')));
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $token = trim((string) ($_POST['token'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $token === '') {
        $error = 'Invalid or expired reset link.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'Passwords do not match.';
    } elseif (!rate_limit_key('reset|' . hash('sha256', $token) . '|' . client_ip(), 5, 300)) {
        $error = 'Too many attempts. Please request a new reset link.';
    } else {
        $query = db()->prepare(
            'SELECT pr.*, u.email
             FROM password_resets pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE u.email = ?
               AND pr.used_at IS NULL
               AND pr.expires_at > NOW()
             ORDER BY pr.id DESC
             LIMIT 1'
        );
        $query->execute([$email]);
        $reset = $query->fetch();

        if (is_array($reset) && password_verify($token, (string) $reset['token_hash'])) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);

            db()->beginTransaction();
            try {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([$newHash, $reset['user_id']]);
                db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
                    ->execute([$reset['id']]);
                // Revoke any other active reset tokens for the same account.
                db()->prepare(
                    'UPDATE password_resets SET used_at = NOW()
                     WHERE user_id = ? AND used_at IS NULL'
                )->execute([$reset['user_id']]);
                db()->commit();

                redirect('login.php?reset=1');
            } catch (Throwable $exception) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                $error = 'Unable to reset the password right now. Please try again.';
            }
        } else {
            $error = 'Invalid or expired reset link.';
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
    <title>Set New Password</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="login-bg">
    <main class="login-card" aria-labelledby="reset-title">
        <div class="brand big">DCOS <span>2077</span></div>
        <h1 id="reset-title">Set New Password</h1>
        <p>Create a new password for your account.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <label for="email">Email</label>
            <input
                id="email"
                class="form-control mb-3"
                name="email"
                type="email"
                autocomplete="email"
                value="<?= e($email) ?>"
                maxlength="190"
                required
            >

            <label for="password">New password</label>
            <input
                id="password"
                class="form-control mb-3"
                name="password"
                type="password"
                autocomplete="new-password"
                minlength="8"
                required
            >

            <label for="password_confirmation">Confirm password</label>
            <input
                id="password_confirmation"
                class="form-control mb-3"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                minlength="8"
                required
            >

            <button class="btn btn-primary w-100" type="submit">Update Password</button>
        </form>

        <a href="login.php" class="d-block mt-3">Back to login</a>
    </main>
</body>
</html>
