<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$month = trim((string) ($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

$eventsStmt = db()->prepare(
    "SELECT id, title, event_type, start_at, end_at
     FROM calendar_events
     WHERE start_at < DATE_ADD(?, INTERVAL 1 DAY)
       AND (end_at IS NULL OR end_at >= ?)
     ORDER BY start_at ASC
     LIMIT 200"
);
$eventsStmt->execute([$endDate, $startDate]);
$events = $eventsStmt->fetchAll();

$today = date('Y-m-d');
$upcomingCount = 0;
$todayCount = 0;
$pastCount = 0;

foreach ($events as $event) {
    $eventDate = substr((string) $event['start_at'], 0, 10);
    if ($eventDate === $today) {
        $todayCount++;
    } elseif ($eventDate > $today) {
        $upcomingCount++;
    } else {
        $pastCount++;
    }
}

$previousMonth = date('Y-m', strtotime($startDate . ' -1 month'));
$nextMonth = date('Y-m', strtotime($startDate . ' +1 month'));

$eventsByDate = [];
foreach ($events as $event) {
    $dateKey = substr((string) $event['start_at'], 0, 10);
    $eventsByDate[$dateKey][] = $event;
}

page_top('Campus Calendar');
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-muted fw-semibold">Campus events</div>
            <h1 class="h3 mb-1">Campus Calendar</h1>
            <p class="text-muted mb-0">View college events, academic activities and important dates.</p>
        </div>
        <a href="calendar.php?month=<?= e(date('Y-m')) ?>" class="btn btn-outline-primary">Current Month</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Events this month</div>
            <div class="h3 fw-bold mb-0"><?= count($events) ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Today</div>
            <div class="h3 fw-bold mb-0"><?= $todayCount ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Upcoming</div>
            <div class="h3 fw-bold mb-0"><?= $upcomingCount ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Past</div>
            <div class="h3 fw-bold mb-0"><?= $pastCount ?></div>
        </div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <a href="calendar.php?month=<?= e($previousMonth) ?>" class="btn btn-outline-secondary">← Previous</a>
            <h2 class="h4 mb-0"><?= e(date('F Y', strtotime($startDate))) ?></h2>
            <a href="calendar.php?month=<?= e($nextMonth) ?>" class="btn btn-outline-secondary">Next →</a>
        </div>
    </div>

    <?php if (!$events): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="display-6 mb-3">📅</div>
                <h2 class="h5">No events scheduled</h2>
                <p class="text-muted mb-0">There are no calendar events for this month.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-1">Event Schedule</h2>
                <div class="small text-muted">Showing events from <?= e(date('d M Y', strtotime($startDate))) ?> to <?= e(date('d M Y', strtotime($endDate))) ?></div>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($events as $event):
                    $eventDate = substr((string) $event['start_at'], 0, 10);
                    $isToday = $eventDate === $today;
                    $badgeClass = $isToday ? 'text-bg-primary' : 'text-bg-light';
                ?>
                    <div class="list-group-item p-4">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-2">
                                <div class="small text-muted">Date</div>
                                <div class="fw-semibold"><?= e(date('D, d M Y', strtotime($eventDate))) ?></div>
                                <?php if ($isToday): ?><span class="badge text-bg-primary mt-1">Today</span><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h3 class="h6 mb-1"><?= e($event['title']) ?></h3>
                                <span class="badge <?= $badgeClass ?>"><?= e($event['event_type'] ?: 'Event') ?></span>
                            </div>
                            <div class="col-md-4 text-md-end small text-muted">
                                <div><strong>Start:</strong> <?= e((string) $event['start_at']) ?></div>
                                <?php if (!empty($event['end_at'])): ?>
                                    <div><strong>End:</strong> <?= e((string) $event['end_at']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php page_bottom(); ?>
