<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$query = db()->prepare(
    'SELECT s.code, s.name, s.credits, s.subject_type, u.name AS faculty_name
     FROM student_subjects ss
     INNER JOIN subjects s ON s.id = ss.subject_id
     LEFT JOIN faculty_subjects fs ON fs.subject_id = s.id
     LEFT JOIN faculty f ON f.id = fs.faculty_id
     LEFT JOIN users u ON u.id = f.user_id
     INNER JOIN students st ON st.id = ss.student_id
     WHERE st.user_id = ? AND st.status = "active"
     ORDER BY s.code'
);
$query->execute([actor_id()]);
$subjects = $query->fetchAll();

$totalCredits = 0;
foreach ($subjects as $subject) {
    $totalCredits += (float) ($subject['credits'] ?? 0);
}

page_top('Academic Profile');
?>
<div class="hero">
    <div>
        <span class="pill">STUDENT · ACADEMICS</span>
        <h2>Academic Profile</h2>
        <p class="mb-0">Your currently assigned subjects and faculty details.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php kpi('Subjects', count($subjects), '▤'); ?>
    <?php kpi('Total credits', number_format($totalCredits, 1), '◈'); ?>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h5 class="mb-1">My subjects</h5><p class="text-muted mb-0">Subjects linked to your active student profile.</p></div>
    </div>
    <?php if (!$subjects): ?>
        <div class="alert alert-light border mb-0">No subjects are currently assigned to your profile.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Code</th><th>Subject</th><th>Credits</th><th>Type</th><th>Faculty</th></tr></thead>
                <tbody>
                <?php foreach ($subjects as $subject): ?>
                    <tr>
                        <td><span class="badge text-bg-light"><?= e((string) $subject['code']) ?></span></td>
                        <td class="fw-semibold"><?= e((string) $subject['name']) ?></td>
                        <td><?= e((string) $subject['credits']) ?></td>
                        <td><?= e((string) ($subject['subject_type'] ?? '—')) ?></td>
                        <td><?= e((string) ($subject['faculty_name'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php page_bottom();