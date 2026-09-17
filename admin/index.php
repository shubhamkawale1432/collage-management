<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_perm('reports.view');

$queries = [
    'Students' => 'SELECT COUNT(*) FROM students',
    'Faculty' => 'SELECT COUNT(*) FROM faculty',
    'Applications' => 'SELECT COUNT(*) FROM applications',
    'Open Tickets' => "SELECT COUNT(*) FROM complaints WHERE status NOT IN ('Closed','Resolved')",
    'Fee Balance' => 'SELECT COALESCE(SUM(amount-paid_amount),0) FROM student_fees',
    'Upcoming Exams' => "SELECT COUNT(*) FROM exams WHERE exam_date >= CURDATE() AND status = 'scheduled'",
];

$stats = [];
foreach ($queries as $label => $sql) {
    $stats[$label] = db()->query($sql)->fetchColumn();
}

page_top('Command Center');
?>
<div class="hero mb-4">
    <div>
        <span class="pill">DIGITAL CAMPUS OS</span>
        <h2>College Command Center</h2>
        <p>Live operational overview for academics, people, finance, services and examinations.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-light" href="../reports/print.php?view=executive">Print Executive Brief</a>
        <a class="btn btn-outline-light" href="modules.php">All Modules</a>
    </div>
</div>

<div class="row g-3 mb-4">
<?php foreach ($stats as $label => $value): ?>
    <div class="col-sm-6 col-xl-4">
        <?php
        $display = $label === 'Fee Balance'
            ? '₹' . number_format((float) $value, 2)
            : number_format((int) $value);
        kpi($label, $display, '◈');
        ?>
    </div>
<?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1">Operational Pulse</h5>
                    <small class="text-muted">Current system volume by major area</small>
                </div>
            </div>
            <canvas id="pulseChart" height="120" aria-label="Operational statistics chart"></canvas>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card p-4 h-100">
            <h5>Attention Queue</h5>
            <p class="text-muted small">Areas administrators should review regularly.</p>
            <div class="list-group list-group-flush">
                <div class="list-group-item px-0">Pending admissions</div>
                <div class="list-group-item px-0">Low attendance</div>
                <div class="list-group-item px-0">Fee reminders</div>
                <div class="list-group-item px-0">Certificate requests</div>
            </div>
        </div>
    </div>
</div>

<script>
window.chartData = <?= json_encode([
    'labels' => ['Students', 'Faculty', 'Applications', 'Complaints', 'Exams'],
    'data' => [
        (int) $stats['Students'],
        (int) $stats['Faculty'],
        (int) $stats['Applications'],
        (int) $stats['Open Tickets'],
        (int) $stats['Upcoming Exams'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
</script>
<?php page_bottom(); ?>
