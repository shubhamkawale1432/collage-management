<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$allowedTypes = [
    'Bonafide', 'Certificate', 'Leave', 'Scholarship', 'Internship',
    'Exam Request', 'Academic Correction', 'Other',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $type = trim((string) ($_POST['application_type'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if (!in_array($type, $allowedTypes, true)) {
        $errors[] = 'Please select a valid application type.';
    }
    if ($title === '' || mb_strlen($title) > 200) {
        $errors[] = 'Title is required and must be 200 characters or fewer.';
    }
    if ($description === '' || mb_strlen($description) > 5000) {
        $errors[] = 'Description is required and must be 5000 characters or fewer.';
    }

    if (!$errors) {
        try {
            db()->beginTransaction();

            $insert = db()->prepare(
                'INSERT INTO applications
                    (applicant_user_id, application_type, title, description, status, owner_role, current_stage)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                actor_id(),
                $type,
                $title,
                $description,
                'Submitted',
                'Student',
                'Submitted',
            ]);

            $applicationId = (int) db()->lastInsertId();

            $history = db()->prepare(
                'INSERT INTO application_history
                    (application_id, status, stage, remarks, changed_by)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $history->execute([
                $applicationId,
                'Submitted',
                'Submitted',
                'Student submitted application',
                actor_id(),
            ]);

            db()->commit();
            AuditService::log('CREATE', 'applications', $applicationId, 'Student submitted application');
            $_SESSION['flash'] = ['success', 'Application submitted successfully. Reference #' . $applicationId . '.'];
            redirect('applications.php');
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errors[] = 'The application could not be submitted. Please try again.';
        }
    }
}

$list = db()->prepare(
    'SELECT id, application_type, title, description, status, current_stage, created_at, updated_at
     FROM applications
     WHERE applicant_user_id = ?
     ORDER BY id DESC'
);
$list->execute([actor_id()]);
$applications = $list->fetchAll();

$total = count($applications);
$submitted = 0;
$approved = 0;
$rejected = 0;
$inProgress = 0;

foreach ($applications as $application) {
    $status = strtolower((string) $application['status']);
    if ($status === 'submitted') {
        $submitted++;
    } elseif ($status === 'approved' || $status === 'completed') {
        $approved++;
    } elseif ($status === 'rejected') {
        $rejected++;
    } else {
        $inProgress++;
    }
}

page_top('Online Application Center');
flash();
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-muted fw-semibold">Student services</div>
            <h1 class="h3 mb-1">Online Application Center</h1>
            <p class="text-muted mb-0">Submit college requests and track their progress from one place.</p>
        </div>
        <a href="index.php" class="btn btn-outline-primary">Student Dashboard</a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">Please fix the following:</div>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Total applications</div>
            <div class="h3 fw-bold mb-0"><?= $total ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Submitted</div>
            <div class="h3 fw-bold mb-0"><?= $submitted ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Approved / completed</div>
            <div class="h3 fw-bold mb-0"><?= $approved ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">In progress</div>
            <div class="h3 fw-bold mb-0"><?= $inProgress ?></div>
        </div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-1">New Application</h2>
            <div class="small text-muted">Choose a service and provide clear details for the college office.</div>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="col-md-4">
                    <label for="application_type" class="form-label">Application type</label>
                    <select id="application_type" name="application_type" class="form-select" required>
                        <option value="">Select service</option>
                        <?php foreach ($allowedTypes as $type): ?>
                            <option value="<?= e($type) ?>" <?= (($_POST['application_type'] ?? '') === $type) ? 'selected' : '' ?>><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-8">
                    <label for="title" class="form-label">Request title</label>
                    <input id="title" name="title" class="form-control" maxlength="200" required
                           value="<?= e((string) ($_POST['title'] ?? '')) ?>"
                           placeholder="e.g. Bonafide certificate for scholarship">
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="5" maxlength="5000" required
                              placeholder="Explain what you need and include any important details."><?= e((string) ($_POST['description'] ?? '')) ?></textarea>
                    <div class="form-text">Maximum 5000 characters.</div>
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4">Submit Application</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-1">My Applications</h2>
            <div class="small text-muted">Only applications submitted from your student account are shown.</div>
        </div>

        <?php if (!$applications): ?>
            <div class="card-body text-center py-5">
                <div class="display-6 mb-3">📄</div>
                <h3 class="h5">No applications yet</h3>
                <p class="text-muted mb-0">Your submitted requests will appear here with their current status.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-3">Reference</th>
                            <th>Type</th>
                            <th>Request</th>
                            <th>Status</th>
                            <th>Stage</th>
                            <th>Submitted</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($applications as $application):
                        $status = strtolower((string) $application['status']);
                        $badge = match ($status) {
                            'approved', 'completed' => 'text-bg-success',
                            'rejected', 'cancelled' => 'text-bg-danger',
                            'submitted', 'pending' => 'text-bg-warning',
                            default => 'text-bg-secondary',
                        };
                    ?>
                        <tr>
                            <td class="px-3 fw-semibold">#<?= e($application['id']) ?></td>
                            <td><?= e($application['application_type']) ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($application['title']) ?></div>
                                <div class="small text-muted text-truncate" style="max-width: 360px;">
                                    <?= e($application['description']) ?>
                                </div>
                            </td>
                            <td><span class="badge <?= $badge ?>"><?= e($application['status']) ?></span></td>
                            <td><?= e($application['current_stage'] ?: '—') ?></td>
                            <td><?= $application['created_at'] ? e(date('d M Y', strtotime((string) $application['created_at']))) : '—' ?></td>
                            <td><?= $application['updated_at'] ? e(date('d M Y, h:i A', strtotime((string) $application['updated_at']))) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php page_bottom(); ?>
