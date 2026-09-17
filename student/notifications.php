<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$userId = actor_id();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();

        $notificationId = safe_int($_POST['id'] ?? 0);
        if ($notificationId <= 0) {
            throw new InvalidArgumentException('Invalid notification.');
        }

        $stmt = db()->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$notificationId, $userId]);

        $_SESSION['flash'] = ['success', 'Notification marked as read.'];
        redirect('notifications.php');
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        $error = 'Unable to update the notification. Please try again.';
    }
}

$stmt = db()->prepare(
    'SELECT id, title, message, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY is_read ASC, created_at DESC, id DESC
     LIMIT 100'
);
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$total = count($notifications);
$unread = 0;
foreach ($notifications as $notification) {
    if (!(int)$notification['is_read']) {
        $unread++;
    }
}
$read = $total - $unread;

page_top('Notifications');
flash();
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <span class="badge text-bg-primary mb-2">Student Updates</span>
            <h2 class="mb-1">Notifications</h2>
            <p class="text-secondary mb-0">Stay up to date with your college notifications and important messages.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-secondary small">Total Notifications</div>
                    <div class="fs-3 fw-bold mt-1"><?= $total ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-secondary small">Unread</div>
                    <div class="fs-3 fw-bold mt-1"><?= $unread ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-secondary small">Read</div>
                    <div class="fs-3 fw-bold mt-1"><?= $read ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="card-header bg-transparent py-3">
            <div class="d-flex justify-content-between align-items-center gap-3">
                <h5 class="mb-0">Recent Notifications</h5>
                <span class="badge text-bg-secondary">Last 100</span>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (!$notifications): ?>
                <div class="text-center text-secondary py-5 px-3">
                    <h6>No notifications</h6>
                    <p class="mb-0">New college updates will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <?php $isUnread = !(int)$notification['is_read']; ?>
                    <div class="p-4 border-bottom <?= $isUnread ? 'bg-body-tertiary' : '' ?>">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
                            <div class="d-flex gap-3">
                                <div class="rounded-circle <?= $isUnread ? 'bg-primary text-white' : 'bg-secondary-subtle text-secondary' ?> d-flex align-items-center justify-content-center flex-shrink-0" style="width:42px;height:42px;">
                                    <?= $isUnread ? '!' : '✓' ?>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <h6 class="mb-0"><?= e($notification['title']) ?></h6>
                                        <?php if ($isUnread): ?>
                                            <span class="badge text-bg-primary">Unread</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-light border text-secondary">Read</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-secondary mb-2 mt-2" style="white-space:pre-line;"><?= e($notification['message']) ?></p>
                                    <small class="text-secondary"><?= e($notification['created_at']) ?></small>
                                </div>
                            </div>

                            <?php if ($isUnread): ?>
                                <form method="post" class="flex-shrink-0">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= e((string)$notification['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Mark as read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php page_bottom(); ?>
