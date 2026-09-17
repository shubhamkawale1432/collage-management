<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$studentStmt = db()->prepare(
    'SELECT s.id, s.branch_id, s.semester_id, b.name AS branch_name, sem.name AS semester_name
     FROM students s
     LEFT JOIN branches b ON b.id = s.branch_id
     LEFT JOIN semesters sem ON sem.id = s.semester_id
     WHERE s.user_id = ? AND s.status = "active"
     LIMIT 1'
);
$studentStmt->execute([actor_id()]);
$student = $studentStmt->fetch();

if (!$student) {
    http_response_code(403);
    exit('Active student profile not found.');
}

$examStmt = db()->prepare(
    'SELECT e.id, e.name, e.type, e.exam_date, e.start_time, e.end_time, e.room,
            e.max_marks, e.status,
            COALESCE(er.status, "not_registered") AS registration_status,
            (SELECT COUNT(*) FROM exam_subjects es WHERE es.exam_id = e.id) AS subject_count,
            (SELECT GROUP_CONCAT(su.code ORDER BY su.code SEPARATOR ", ")
             FROM exam_subjects es
             INNER JOIN subjects su ON su.id = es.subject_id
             WHERE es.exam_id = e.id) AS subject_codes
     FROM exams e
     LEFT JOIN exam_registrations er
       ON er.exam_id = e.id AND er.student_id = ?
     WHERE e.branch_id = ?
       AND e.semester_id = ?
       AND e.exam_date >= CURDATE()
     ORDER BY e.exam_date ASC, e.start_time ASC'
);
$examStmt->execute([(int) $student['id'], (int) $student['branch_id'], (int) $student['semester_id']]);
$exams = $examStmt->fetchAll();

$scheduled = 0;
$cancelled = 0;
$registered = 0;
foreach ($exams as $exam) {
    if ($exam['status'] === 'scheduled') {
        $scheduled++;
    }
    if ($exam['status'] === 'cancelled') {
        $cancelled++;
    }
    if (in_array($exam['registration_status'], ['registered', 'confirmed'], true)) {
        $registered++;
    }
}

page_top('Exam Center');
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-muted fw-semibold">Academic schedule</div>
            <h1 class="h3 mb-1">Exam Center</h1>
            <p class="text-muted mb-0">
                Upcoming examinations for <?= e($student['branch_name'] ?? 'your branch') ?> · <?= e($student['semester_name'] ?? 'your semester') ?>
            </p>
        </div>
        <a href="index.php" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted">Upcoming exams</div>
                    <div class="display-6 fw-bold"><?= count($exams) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted">Scheduled</div>
                    <div class="display-6 fw-bold"><?= $scheduled ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted">Registered</div>
                    <div class="display-6 fw-bold"><?= $registered ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted">Cancelled</div>
                    <div class="display-6 fw-bold"><?= $cancelled ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$exams): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="display-6 mb-3">📅</div>
                <h2 class="h5">No upcoming exams</h2>
                <p class="text-muted mb-0">No examination schedule is currently available for your branch and semester.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-3">Exam</th>
                                <th>Subjects</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Room</th>
                                <th>Marks</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($exams as $exam):
                            $examStatus = strtolower((string) $exam['status']);
                            $registrationStatus = strtolower((string) $exam['registration_status']);
                            $statusClass = match ($examStatus) {
                                'cancelled' => 'text-bg-danger',
                                'completed' => 'text-bg-secondary',
                                default => 'text-bg-success',
                            };
                            $registrationLabel = match ($registrationStatus) {
                                'registered', 'confirmed' => 'Registered',
                                'cancelled' => 'Registration cancelled',
                                default => 'Not registered',
                            };
                        ?>
                            <tr>
                                <td class="px-3">
                                    <div class="fw-semibold"><?= e($exam['name']) ?></div>
                                    <div class="small text-muted"><?= e($exam['type'] ?: 'Examination') ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($exam['subject_codes'])): ?>
                                        <span class="small"><?= e($exam['subject_codes']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">No subjects listed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(date('d M Y', strtotime((string) $exam['exam_date']))) ?></td>
                                <td><?= e(substr((string) $exam['start_time'], 0, 5)) ?>–<?= e(substr((string) $exam['end_time'], 0, 5)) ?></td>
                                <td><?= e($exam['room'] ?: 'TBA') ?></td>
                                <td><?= e((string) $exam['max_marks']) ?></td>
                                <td>
                                    <span class="badge <?= $statusClass ?> mb-1"><?= e(ucfirst($examStatus)) ?></span>
                                    <div class="small text-muted"><?= e($registrationLabel) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php page_bottom(); ?>
