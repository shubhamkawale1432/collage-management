<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$userId = actor_id();
$studentQuery = db()->prepare(
    'SELECT st.*, b.name AS branch_name, se.name AS semester_name, dv.name AS division_name
     FROM students st
     LEFT JOIN branches b ON b.id = st.branch_id
     LEFT JOIN semesters se ON se.id = st.semester_id
     LEFT JOIN divisions dv ON dv.id = st.division_id
     WHERE st.user_id = ? AND st.status = "active" LIMIT 1'
);
$studentQuery->execute([$userId]);
$student = $studentQuery->fetch();

if (!$student) {
    http_response_code(403);
    exit('Active student profile not found.');
}

$attendanceQuery = db()->prepare(
    'SELECT ROUND(100 * SUM(a.status = "Present") / NULLIF(COUNT(*), 0), 2)
     FROM attendance a WHERE a.student_id = ?'
);
$attendanceQuery->execute([(int) $student['id']]);
$attendance = $attendanceQuery->fetchColumn();

$feeQuery = db()->prepare(
    'SELECT COALESCE(SUM(GREATEST(amount - paid_amount - discount, 0)), 0)
     FROM student_fees WHERE student_id = ?'
);
$feeQuery->execute([(int) $student['id']]);
$feeBalance = (float) $feeQuery->fetchColumn();

$assignmentQuery = db()->prepare(
    'SELECT COUNT(*) FROM assignments a
     INNER JOIN student_subjects ss ON ss.subject_id = a.subject_id
     WHERE ss.student_id = ? AND a.status = "published" AND a.deleted_at IS NULL AND a.deadline >= NOW()'
);
$assignmentQuery->execute([(int) $student['id']]);
$assignmentCount = (int) $assignmentQuery->fetchColumn();

$examQuery = db()->prepare(
    'SELECT COUNT(*) FROM exams e
     WHERE e.exam_date >= CURDATE() AND e.status = "scheduled"
       AND (e.branch_id IS NULL OR e.branch_id = ?)'
);
$examQuery->execute([(int) $student['branch_id']]);
$examCount = (int) $examQuery->fetchColumn();

$noticeQuery = db()->prepare(
    'SELECT id, title, description, publish_date
     FROM notices
     WHERE status = "Published"
       AND (expiry_date IS NULL OR expiry_date >= CURDATE())
       AND (target_audience IS NULL OR target_audience IN ("All", "Student"))
       AND (branch_id IS NULL OR branch_id = ?)
       AND (semester_id IS NULL OR semester_id = ?)
     ORDER BY publish_date DESC, id DESC LIMIT 5'
);
$noticeQuery->execute([(int) $student['branch_id'], (int) $student['semester_id']]);
$notices = $noticeQuery->fetchAll();

page_top('My Digital Campus');
?>
<div class="hero">
    <div>
        <span class="pill">STUDENT · DIGITAL CAMPUS</span>
        <h2>Good day, <?= e((string) current_user()['name']) ?>.</h2>
        <p class="mb-0"><?= e((string) $student['enrollment_no']) ?> · <?= e((string) $student['branch_name']) ?> · <?= e((string) $student['semester_name']) ?> · Division <?= e((string) $student['division_name']) ?></p>
    </div>
    <a class="btn btn-light" href="profile.php">My profile</a>
</div>

<div class="row g-3 mt-1">
    <?php kpi('Attendance', number_format((float) ($attendance ?? 0), 2) . '%', '◉'); ?>
    <?php kpi('Fee balance', '₹' . number_format($feeBalance, 2), '₹'); ?>
    <?php kpi('Open assignments', $assignmentCount, '✓'); ?>
    <?php kpi('Upcoming exams', $examCount, '▣'); ?>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-7">
        <div class="card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div><h5 class="mb-1">Academic Command Center</h5><p class="text-muted mb-0">Quick access to your student services.</p></div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary" href="academics.php">Academics</a>
                <a class="btn btn-outline-primary" href="attendance.php">Attendance</a>
                <a class="btn btn-outline-primary" href="assignments.php">Assignments</a>
                <a class="btn btn-outline-primary" href="quiz.php">Quizzes</a>
                <a class="btn btn-outline-primary" href="results.php">Results</a>
                <a class="btn btn-outline-primary" href="fees.php">Fees</a>
                <a class="btn btn-outline-primary" href="exams.php">Exams</a>
                <a class="btn btn-outline-primary" href="library.php">Library</a>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card p-4 h-100">
            <span class="badge text-bg-primary align-self-start mb-2">Student Assistant</span>
            <h5>AI College Assistant</h5>
            <p class="text-muted">Get help with authorized campus information and student services.</p>
            <a class="btn btn-primary" href="ai.php">Open AI Assistant</a>
        </div>
    </div>
</div>

<div class="card p-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h5 class="mb-1">Latest notices</h5><p class="text-muted mb-0">Updates relevant to your student profile.</p></div>
        <a class="btn btn-sm btn-outline-primary" href="notifications.php">Notifications</a>
    </div>
    <?php if (!$notices): ?>
        <div class="text-muted py-3">No current notices.</div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($notices as $notice): ?>
                <div class="list-group-item px-0">
                    <div class="fw-semibold"><?= e((string) $notice['title']) ?></div>
                    <div class="small text-muted"><?= e((string) ($notice['publish_date'] ?? '')) ?></div>
                    <?php if (!empty($notice['description'])): ?><div class="mt-1"><?= e(mb_substr((string) $notice['description'], 0, 180)) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php page_bottom();