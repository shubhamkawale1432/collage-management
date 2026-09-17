<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$code = trim((string)($_GET['code'] ?? ''));
$cert = null;

if ($code !== '' && mb_strlen($code) <= 100) {
    $query = db()->prepare(
        'SELECT c.type, c.certificate_no, c.issue_date, c.status, u.name, v.id AS verification_id
         FROM certificates c
         INNER JOIN students s ON s.id = c.student_id
         INNER JOIN users u ON u.id = s.user_id
         INNER JOIN certificate_verifications v ON v.certificate_id = c.id
         WHERE v.verification_code = ?
         LIMIT 1'
    );
    $query->execute([$code]);
    $cert = $query->fetch();

    if ($cert) {
        db()->prepare(
            'UPDATE certificate_verifications
             SET verified_count = verified_count + 1, last_verified_at = NOW()
             WHERE id = ?'
        )->execute([$cert['verification_id']]);
    }
}

$isValid = is_array($cert) && (string)$cert['status'] === 'Issued';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#4f46e5">
    <title>Certificate Verification · DCOS 2077</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="login-bg">
    <main class="login-card" aria-labelledby="verification-title">
        <div class="brand-wrap mb-4">
            <div class="brand-mark"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
            <div>
                <div class="brand big">DCOS <span>2077</span></div>
                <div class="brand-mini text-muted">DIGITAL COLLEGE OPERATING SYSTEM</div>
            </div>
        </div>

        <span class="pill text-dark border">Public Verification</span>
        <h1 id="verification-title" class="mt-3 mb-2">Verify a certificate</h1>
        <p class="text-muted mb-4">Enter the official verification code printed on the certificate.</p>

        <form method="get" autocomplete="off">
            <label class="form-label" for="code">Verification code</label>
            <div class="input-group">
                <input id="code" class="form-control" name="code" maxlength="100" value="<?= e($code) ?>" placeholder="Enter verification code" required autofocus>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span class="visually-hidden">Verify</span></button>
            </div>
        </form>

        <?php if ($code !== ''): ?>
            <?php if ($cert && $isValid): ?>
                <section class="alert alert-success mt-4" aria-live="polite">
                    <h2 class="h5 mb-3"><i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>Certificate verified</h2>
                    <div class="small">
                        <div><strong>Type:</strong> <?= e((string)$cert['type']) ?></div>
                        <div><strong>Issued to:</strong> <?= e((string)$cert['name']) ?></div>
                        <div><strong>Certificate No:</strong> <?= e((string)$cert['certificate_no']) ?></div>
                        <div><strong>Issue date:</strong> <?= e((string)$cert['issue_date']) ?></div>
                        <div><strong>Status:</strong> <?= e((string)$cert['status']) ?></div>
                    </div>
                </section>
            <?php elseif ($cert): ?>
                <section class="alert alert-warning mt-4" aria-live="polite">
                    <h2 class="h5 mb-1">Certificate record found</h2>
                    <p class="mb-0">This certificate is not currently marked as issued. Status: <?= e((string)$cert['status']) ?>.</p>
                </section>
            <?php else: ?>
                <section class="alert alert-danger mt-4" aria-live="polite">
                    <h2 class="h5 mb-1">No matching certificate</h2>
                    <p class="mb-0">Check the verification code and try again.</p>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <div class="mt-4 pt-3 border-top text-center">
            <a href="admission.php" class="small">Online admission</a>
            <span class="text-muted mx-2">·</span>
            <a href="../auth/login.php" class="small">Campus login</a>
        </div>
    </main>
</body>
</html>
