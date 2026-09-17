<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Faculty');

$facultyStmt = db()->prepare('SELECT id, employee_id, designation FROM faculty WHERE user_id = ? AND status = ? LIMIT 1');
$facultyStmt->execute([actor_id(), 'active']);
$faculty = $facultyStmt->fetch();

if (!$faculty) {
    http_response_code(403);
    exit('Faculty profile is not active.');
}

page_top('Faculty Examination Workspace');
?>

<div class="card p-4 mb-4">
    <span class="badge text-bg-primary mb-2">Examination</span>
    <h1 class="h3 mb-2">Faculty examination workspace</h1>
    <p class="text-muted mb-0">Use the examination module to review scheduled exams and enter marks for students.</p>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card p-4 h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle bg-primary-subtle p-3"><span class="fw-bold">01</span></div>
                <div><h2 class="h5 mb-1">View examinations</h2><p class="text-muted mb-0">Review exam schedules and subjects.</p></div>
            </div>
            <a class="btn btn-outline-primary" href="../examination/exams.php">Open exam schedule</a>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-4 h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle bg-primary-subtle p-3"><span class="fw-bold">02</span></div>
                <div><h2 class="h5 mb-1">Enter marks</h2><p class="text-muted mb-0">Record internal, practical and external marks.</p></div>
            </div>
            <a class="btn btn-primary" href="../examination/marks.php">Open marks entry</a>
        </div>
    </div>
</div>

<div class="alert alert-light border mt-4 mb-0">
    <strong>Faculty account:</strong> <?= e((string) ($faculty['employee_id'] ?: 'Faculty')) ?>
    <?php if (!empty($faculty['designation'])): ?> · <?= e($faculty['designation']) ?><?php endif; ?>
</div>

<?php page_bottom();
