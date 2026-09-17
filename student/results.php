<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$studentStmt = db()->prepare(
    'SELECT s.id, s.enrollment_no, s.branch_id, s.semester_id,
            b.name AS branch_name, sem.name AS semester_name
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

$resultStmt = db()->prepare(
    'SELECT m.id, m.exam_id, m.subject_id, m.internal_marks, m.practical_marks,
            m.external_marks, m.total_marks, m.grade, m.grade_point, m.result,
            e.name AS exam_name, e.type AS exam_type, e.exam_date,
            sem.number AS semester_number, sem.name AS semester_name,
            sub.code AS subject_code, sub.name AS subject_name,
            sub.max_marks
     FROM marks m
     INNER JOIN subjects sub ON sub.id = m.subject_id
     INNER JOIN exams e ON e.id = m.exam_id
     LEFT JOIN semesters sem ON sem.id = e.semester_id
     WHERE m.student_id = ?
     ORDER BY e.exam_date DESC, e.id DESC, sub.code ASC'
);
$resultStmt->execute([(int) $student['id']]);
$results = $resultStmt->fetchAll();

$examGroups = [];
$totalMarks = 0.0;
$totalMaxMarks = 0.0;
$passed = 0;
$failed = 0;
$gradePoints = [];

foreach ($results as $row) {
    $examKey = (string) $row['exam_id'];
    if (!isset($examGroups[$examKey])) {
        $examGroups[$examKey] = [
            'name' => $row['exam_name'],
            'type' => $row['exam_type'],
            'date' => $row['exam_date'],
            'semester' => $row['semester_name'] ?: ('Semester ' . (string) $row['semester_number']),
            'rows' => [],
        ];
    }
    $examGroups[$examKey]['rows'][] = $row;

    $totalMarks += (float) $row['total_marks'];
    $totalMaxMarks += (float) ($row['max_marks'] ?: 100);
    if (strtoupper((string) $row['result']) === 'PASS') {
        $passed++;
    } else {
        $failed++;
    }
    if ($row['grade_point'] !== null && $row['grade_point'] !== '') {
        $gradePoints[] = (float) $row['grade_point'];
    }
}

$overallPercentage = $totalMaxMarks > 0 ? ($totalMarks / $totalMaxMarks) * 100 : 0;
avgGradePoint = count($gradePoints) > 0 ? array_sum($gradePoints) / count($gradePoints) : 0;

page_top('Result Center');
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-muted fw-semibold">Academic performance</div>
            <h1 class="h3 mb-1">Result Center</h1>
            <p class="text-muted mb-0">
                <?= e($student['enrollment_no'] ?: 'Student') ?> · <?= e($student['branch_name'] ?: 'Branch') ?> · <?= e($student['semester_name'] ?: 'Semester') ?>
            </p>
        </div>
        <a href="index.php" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm"><div class="card-body">
                <div class="small text-muted">Subjects evaluated</div>
                <div class="display-6 fw-bold"><?= count($results) ?></div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm"><div class="card-body">
                <div class="small text-muted">Overall percentage</div>
                <div class="display-6 fw-bold"><?= number_format($overallPercentage, 2) ?>%</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm"><div class="card-body">
                <div class="small text-muted">Passed</div>
                <div class="display-6 fw-bold"><?= $passed ?></div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm"><div class="card-body">
                <div class="small text-muted">Average grade point</div>
                <div class="display-6 fw-bold"><?= number_format($avgGradePoint, 2) ?></div>
            </div></div>
        </div>
    </div>

    <?php if (!$results): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="display-6 mb-3">📊</div>
                <h2 class="h5">No results published yet</h2>
                <p class="text-muted mb-0">Your examination results will appear here when marks are published.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($examGroups as $exam): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h2 class="h5 mb-1"><?= e($exam['name']) ?></h2>
                            <div class="small text-muted">
                                <?= e($exam['type'] ?: 'Examination') ?> · <?= e($exam['semester']) ?> · <?= e(date('d M Y', strtotime((string) $exam['date']))) ?>
                            </div>
                        </div>
                        <span class="badge text-bg-primary"><?= count($exam['rows']) ?> subjects</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-3">Subject</th>
                                <th>Internal</th>
                                <th>Practical</th>
                                <th>External</th>
                                <th>Total</th>
                                <th>Grade</th>
                                <th>Point</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($exam['rows'] as $row):
                            $isPass = strtoupper((string) $row['result']) === 'PASS';
                        ?>
                            <tr>
                                <td class="px-3">
                                    <div class="fw-semibold"><?= e($row['subject_code']) ?></div>
                                    <div class="small text-muted"><?= e($row['subject_name']) ?></div>
                                </td>
                                <td><?= e((string) $row['internal_marks']) ?></td>
                                <td><?= e((string) $row['practical_marks']) ?></td>
                                <td><?= e((string) $row['external_marks']) ?></td>
                                <td class="fw-semibold"><?= e((string) $row['total_marks']) ?> / <?= e((string) ($row['max_marks'] ?: 100)) ?></td>
                                <td><?= e((string) ($row['grade'] ?: '—')) ?></td>
                                <td><?= e((string) ($row['grade_point'] ?? '—')) ?></td>
                                <td>
                                    <span class="badge <?= $isPass ? 'text-bg-success' : 'text-bg-danger' ?>">
                                        <?= e($row['result'] ?: '—') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php page_bottom(); ?>
