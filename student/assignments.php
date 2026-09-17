<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$studentStmt = db()->prepare(
    'SELECT id, branch, semester, division_id
     FROM students
     WHERE user_id = ? AND status = ?
     LIMIT 1'
);
$studentStmt->execute([actor_id(), 'Active']);
$student = $studentStmt->fetch();

if (!$student) {
    http_response_code(403);
    exit('Student profile is not active.');
}

$studentId = (int) $student['id'];

$assignmentStmt = db()->prepare(
    'SELECT a.id, a.title, a.description, a.deadline, a.max_marks,
            s.code AS subject_code, s.name AS subject_name,
            sub.id AS submission_id, sub.status AS submission_status,
            sub.submitted_at, sub.marks
     FROM assignments a
     INNER JOIN subjects s ON s.id = a.subject_id
     INNER JOIN student_subjects ss ON ss.subject_id = a.subject_id
     LEFT JOIN assignment_submissions sub
            ON sub.assignment_id = a.id AND sub.student_id = ?
     WHERE ss.student_id = ?
       AND a.status = ?
       AND a.deleted_at IS NULL
     ORDER BY
       CASE WHEN a.deadline < NOW() THEN 1 ELSE 0 END,
       a.deadline ASC,
       a.id DESC'
);
$assignmentStmt->execute([$studentId, $studentId, 'published']);
$assignments = $assignmentStmt->fetchAll();

$now = time();
$openCount = 0;
$submittedCount = 0;
$overdueCount = 0;

foreach ($assignments as $assignment) {
    $deadline = strtotime((string) $assignment['deadline']);
    $submitted = !empty($assignment['submission_id']);

    if ($submitted) {
        $submittedCount++;
    } elseif ($deadline !== false && $deadline < $now) {
        $overdueCount++;
    } else {
        $openCount++;
    }
}

page_top('Assignment Hub');
flash();
?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card p-3 h-100">
            <div class="small text-muted">Total assignments</div>
            <div class="display-6 fw-semibold"><?= e(count($assignments)) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 h-100">
            <div class="small text-muted">Open</div>
            <div class="display-6 fw-semibold text-primary"><?= e($openCount) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 h-100">
            <div class="small text-muted">Submitted</div>
            <div class="display-6 fw-semibold text-success"><?= e($submittedCount) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 h-100">
            <div class="small text-muted">Overdue</div>
            <div class="display-6 fw-semibold text-danger"><?= e($overdueCount) ?></div>
        </div>
    </div>
</div>

<div class="card p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <span class="badge text-bg-primary mb-2">Academic Work</span>
            <h1 class="h4 mb-1">My assignments</h1>
            <p class="text-muted mb-0">View tasks for your enrolled subjects and track submissions.</p>
        </div>
        <span class="badge text-bg-light"><?= e(count($assignments)) ?> assignments</span>
    </div>

    <?php if (!$assignments): ?>
        <div class="text-center py-5 text-muted">
            <h2 class="h6">No assignments available</h2>
            <p class="mb-0">Published assignments for your subjects will appear here.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Assignment</th>
                        <th>Subject</th>
                        <th>Deadline</th>
                        <th>Marks</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                        <?php
                        $deadlineTs = strtotime((string) $assignment['deadline']);
                        $isSubmitted = !empty($assignment['submission_id']);
                        $isOverdue = !$isSubmitted && $deadlineTs !== false && $deadlineTs < $now;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($assignment['title']) ?></div>
                                <?php if (!empty($assignment['description'])): ?>
                                    <div class="small text-muted text-truncate" style="max-width: 280px;">
                                        <?= e($assignment['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($assignment['subject_code'] . ' · ' . $assignment['subject_name']) ?></td>
                            <td>
                                <div><?= e(date('d M Y', $deadlineTs ?: time())) ?></div>
                                <div class="small text-muted"><?= e(date('h:i A', $deadlineTs ?: time())) ?></div>
                            </td>
                            <td><?= e($assignment['max_marks']) ?></td>
                            <td>
                                <?php if ($isSubmitted): ?>
                                    <span class="badge text-bg-success">Submitted</span>
                                    <?php if (!empty($assignment['marks'])): ?>
                                        <div class="small text-muted mt-1">Marks: <?= e($assignment['marks']) ?></div>
                                    <?php endif; ?>
                                <?php elseif ($isOverdue): ?>
                                    <span class="badge text-bg-danger">Overdue</span>
                                <?php else: ?>
                                    <span class="badge text-bg-primary">Open</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm <?= $isOverdue ? 'btn-outline-secondary' : 'btn-primary' ?>"
                                   href="assignment_submit.php?id=<?= e($assignment['id']) ?>">
                                    <?= $isSubmitted ? 'View / Resubmit' : ($isOverdue ? 'View' : 'Open') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php page_bottom();
