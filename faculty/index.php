<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Faculty');

$user = current_user();
$facultyQuery = db()->prepare(
    'SELECT f.id, f.employee_id, f.designation, f.qualification, d.name AS department_name
     FROM faculty f
     INNER JOIN departments d ON d.id = f.department_id
     WHERE f.user_id = ? AND f.status = "active"
     LIMIT 1'
);
$facultyQuery->execute([actor_id()]);
$faculty = $facultyQuery->fetch();

if (!$faculty) {
    http_response_code(403);
    exit('Faculty profile is not active.');
}

$facultyId = (int) $faculty['id'];
$stats = [];

$query = db()->prepare('SELECT COUNT(*) FROM faculty_subjects WHERE faculty_id = ?');
$query->execute([$facultyId]);
$stats['subjects'] = (int) $query->fetchColumn();

$query = db()->prepare('SELECT COUNT(*) FROM assignments WHERE faculty_id = ? AND deleted_at IS NULL');
$query->execute([$facultyId]);
$stats['assignments'] = (int) $query->fetchColumn();

$query = db()->prepare('SELECT COUNT(*) FROM quizzes WHERE faculty_id = ?');
$query->execute([$facultyId]);
$stats['quizzes'] = (int) $query->fetchColumn();

$query = db()->prepare('SELECT COUNT(*) FROM attendance_sessions WHERE faculty_id = ?');
$query->execute([$facultyId]);
$stats['sessions'] = (int) $query->fetchColumn();

$query = db()->prepare(
    'SELECT COUNT(DISTINCT ss.student_id)
     FROM student_subjects ss
     INNER JOIN faculty_subjects fs ON fs.subject_id = ss.subject_id
     WHERE fs.faculty_id = ?'
);
$query->execute([$facultyId]);
$stats['students'] = (int) $query->fetchColumn();

$query = db()->prepare(
    'SELECT a.id, a.title, a.deadline, a.max_marks, s.code AS subject_code
     FROM assignments a
     INNER JOIN subjects s ON s.id = a.subject_id
     WHERE a.faculty_id = ? AND a.deleted_at IS NULL
     ORDER BY a.deadline ASC, a.id DESC
     LIMIT 5'
);
$query->execute([$facultyId]);
$upcomingAssignments = $query->fetchAll();

page_top('Faculty Academic Command Center');
flash();
?>
<div class="hero">
    <div>
        <span class="pill">FACULTY WORKSPACE</span>
        <h2 class="mb-2">Good day, <?= e((string) ($user['name'] ?? 'Faculty')) ?>.</h2>
        <p class="mb-0">Manage teaching, attendance, assessments and learning resources from one secure workspace.</p>
    </div>
</div>

<div class="row g-3 mt-1">
    <?php kpi('Students', $stats['students'], '◉'); ?>
    <?php kpi('Subjects', $stats['subjects'], '▦'); ?>
    <?php kpi('Assignments', $stats['assignments'], '✓'); ?>
    <?php kpi('Attendance Sessions', $stats['sessions'], '◷'); ?>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-8">
        <div class="card p-4 h-100">
            <h5 class="mb-1">Quick actions</h5>
            <p class="text-muted">Start the most common academic tasks.</p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="attendance.php">Mark attendance</a>
                <a class="btn btn-outline-primary" href="assignments.php">Create assignment</a>
                <a class="btn btn-outline-primary" href="materials.php">Publish material</a>
                <a class="btn btn-outline-primary" href="quiz.php">Build quiz</a>
                <a class="btn btn-outline-secondary" href="exams.php">Examination workspace</a>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <h5>Faculty profile</h5>
            <dl class="row small mb-0 mt-3">
                <dt class="col-5">Employee ID</dt><dd class="col-7"><?= e((string) ($faculty['employee_id'] ?? '—')) ?></dd>
                <dt class="col-5">Department</dt><dd class="col-7"><?= e((string) ($faculty['department_name'] ?? '—')) ?></dd>
                <dt class="col-5">Designation</dt><dd class="col-7"><?= e((string) ($faculty['designation'] ?? '—')) ?></dd>
                <dt class="col-5">Qualification</dt><dd class="col-7"><?= e((string) ($faculty['qualification'] ?? '—')) ?></dd>
            </dl>
        </div>
    </div>
</div>

<div class="card p-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h5 class="mb-1">Upcoming assignments</h5><p class="text-muted mb-0">Your five most recent deadlines.</p></div>
        <a href="assignments.php" class="btn btn-sm btn-outline-primary">View all</a>
    </div>
    <?php if (!$upcomingAssignments): ?>
        <div class="alert alert-light border mb-0">No assignments have been published yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Subject</th><th>Assignment</th><th>Deadline</th><th>Marks</th></tr></thead>
                <tbody>
                <?php foreach ($upcomingAssignments as $assignment): ?>
                    <tr>
                        <td><?= e((string) $assignment['subject_code']) ?></td>
                        <td><?= e((string) $assignment['title']) ?></td>
                        <td><?= e((string) $assignment['deadline']) ?></td>
                        <td><?= e((string) $assignment['max_marks']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php page_bottom();
