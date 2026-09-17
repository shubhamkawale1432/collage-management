<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$studentQuery = db()->prepare('SELECT id FROM students WHERE user_id = ? AND status = "active" LIMIT 1');
$studentQuery->execute([actor_id()]);
$studentId = (int) $studentQuery->fetchColumn();

if ($studentId <= 0) {
    http_response_code(403);
    exit('Active student profile not found.');
}

$query = db()->prepare(
    'SELECT s.id, s.code, s.name,
            COUNT(a.id) AS total_classes,
            SUM(a.status = "Present") AS present_count,
            SUM(a.status = "Absent") AS absent_count,
            SUM(a.status = "Late") AS late_count,
            SUM(a.status = "Leave") AS leave_count,
            ROUND(100 * SUM(a.status = "Present") / NULLIF(COUNT(a.id), 0), 2) AS attendance_pct
     FROM attendance a
     INNER JOIN attendance_sessions ats ON ats.id = a.session_id
     INNER JOIN subjects s ON s.id = ats.subject_id
     WHERE a.student_id = ?
     GROUP BY s.id, s.code, s.name
     ORDER BY s.code'
);
$query->execute([$studentId]);
$records = $query->fetchAll();

$threshold = (float) ($GLOBALS['app']['attendance_threshold'] ?? 75);
$totalClasses = 0;
$totalPresent = 0;
foreach ($records as $record) {
    $totalClasses += (int) $record['total_classes'];
    $totalPresent += (int) $record['present_count'];
}
$overall = $totalClasses > 0 ? round(($totalPresent / $totalClasses) * 100, 2) : 0.0;

page_top('Attendance Intelligence');
?>
<div class="hero">
    <div>
        <span class="pill">STUDENT · ATTENDANCE</span>
        <h2>Attendance Intelligence</h2>
        <p class="mb-0">Track subject-wise attendance and identify areas that need attention.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php kpi('Overall attendance', number_format($overall, 2) . '%', '◉'); ?>
    <?php kpi('Classes recorded', $totalClasses, '▣'); ?>
    <?php kpi('Present', $totalPresent, '✓'); ?>
    <?php kpi('Required threshold', number_format($threshold, 2) . '%', '⚑'); ?>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h5 class="mb-1">Subject-wise attendance</h5><p class="text-muted mb-0">Attendance is calculated from recorded attendance sessions.</p></div>
    </div>

    <?php if (!$records): ?>
        <div class="alert alert-light border mb-0">No attendance records are available yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Subject</th><th>Attendance</th><th>Present</th><th>Absent</th><th>Late</th><th>Leave</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($records as $record):
                    $percentage = (float) ($record['attendance_pct'] ?? 0);
                    $safePercentage = max(0, min(100, $percentage));
                    $isBelow = $percentage < $threshold;
                ?>
                    <tr>
                        <td><div class="fw-semibold"><?= e((string) $record['name']) ?></div><small class="text-muted"><?= e((string) $record['code']) ?></small></td>
                        <td style="min-width:220px">
                            <div class="d-flex justify-content-between small mb-1"><span><?= number_format($percentage, 2) ?>%</span><span><?= (int) $record['total_classes'] ?> classes</span></div>
                            <div class="progress" role="progressbar" aria-valuenow="<?= e((string) $safePercentage) ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width: <?= e((string) $safePercentage) ?>%"></div>
                            </div>
                        </td>
                        <td><?= (int) $record['present_count'] ?></td>
                        <td><?= (int) $record['absent_count'] ?></td>
                        <td><?= (int) $record['late_count'] ?></td>
                        <td><?= (int) $record['leave_count'] ?></td>
                        <td><span class="badge <?= $isBelow ? 'text-bg-warning' : 'text-bg-success' ?>"><?= $isBelow ? 'Below threshold' : 'On track' ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="alert <?= $overall < $threshold ? 'alert-warning' : 'alert-light' ?> border mt-4 mb-0">
            Attendance below <?= number_format($threshold, 2) ?>% is highlighted for attention.
        </div>
    <?php endif; ?>
</div>
<?php page_bottom();