<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$msg = '';
$type = 'info';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();

    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $program = trim((string)($_POST['program'] ?? ''));
    $branch = trim((string)($_POST['branch'] ?? ''));

    if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || $program === '' || mb_strlen($program) > 120 || $branch === '' || mb_strlen($branch) > 120) {
        $msg = 'Please enter valid details in all required fields.';
        $type = 'danger';
    } else {
        try {
            $no = 'ADM-' . date('Y') . '-' . strtoupper(substr(hash('sha256', microtime(true) . secure_random(8)), 0, 10));
            $query = db()->prepare(
                'INSERT INTO admissions(application_no, full_name, email, program, branch, status, submitted_at)
                 VALUES (?, ?, ?, ?, ?, "Submitted", NOW())'
            );
            $query->execute([$no, $name, $email, $program, $branch]);
            $msg = 'Application submitted successfully. Your application number is ' . $no . '.';
            $type = 'success';
        } catch (Throwable $e) {
            error_log('Admission submission failed: ' . $e->getMessage());
            $msg = 'Unable to submit the application right now. Please try again later.';
            $type = 'danger';
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
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#4f46e5">
    <title>Online Admission · DCOS 2077</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="login-bg">
    <main class="login-card" aria-labelledby="admission-title">
        <div class="brand-wrap mb-4">
            <div class="brand-mark"><i class="fa-solid fa-atom" aria-hidden="true"></i></div>
            <div>
                <div class="brand big">DCOS <span>2077</span></div>
                <div class="brand-mini text-muted">DIGITAL COLLEGE OPERATING SYSTEM</div>
            </div>
        </div>

        <span class="pill text-dark border">Admissions Portal</span>
        <h1 id="admission-title" class="mt-3 mb-2">Start your application</h1>
        <p class="text-muted mb-4">Submit your basic details to begin the digital admission process.</p>

        <?php if ($msg !== ''): ?>
            <div class="alert alert-<?= e($type) ?>" role="status">
                <i class="fa-solid <?= $type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> me-1" aria-hidden="true"></i>
                <?= e($msg) ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="mb-3">
                <label class="form-label" for="full_name">Full name</label>
                <input id="full_name" class="form-control" name="full_name" maxlength="120" required value="<?= e((string)($_POST['full_name'] ?? '')) ?>" placeholder="Enter your full name">
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email address</label>
                <input id="email" class="form-control" name="email" type="email" maxlength="190" autocomplete="email" required value="<?= e((string)($_POST['email'] ?? '')) ?>" placeholder="you@example.com">
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="program">Program</label>
                    <input id="program" class="form-control" name="program" maxlength="120" required value="<?= e((string)($_POST['program'] ?? '')) ?>" placeholder="e.g. Diploma">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="branch">Branch</label>
                    <input id="branch" class="form-control" name="branch" maxlength="120" required value="<?= e((string)($_POST['branch'] ?? '')) ?>" placeholder="e.g. Information Technology">
                </div>
            </div>

            <button class="btn btn-primary w-100 py-2 mt-4" type="submit">
                <i class="fa-solid fa-paper-plane me-2" aria-hidden="true"></i>Submit application
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center">
            <a href="../auth/login.php" class="small">Already have campus access? Sign in</a>
        </div>
    </main>
</body>
</html>
