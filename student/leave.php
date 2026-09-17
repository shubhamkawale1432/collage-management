<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$userId = actor_id();
$allowedTypes = ['Medical', 'Personal', 'Academic', 'Emergency'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();

        $leaveType = trim((string)($_POST['leave_type'] ?? ''));
        $fromDate = trim((string)($_POST['from_date'] ?? ''));
        $toDate = trim((string)($_POST['to_date'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if (!in_array($leaveType, $allowedTypes, true)) {
            throw new InvalidArgumentException('Please select a valid leave type.');
        }

        $from = DateTime::createFromFormat('Y-m-d', $fromDate);
        $to = DateTime::createFromFormat('Y-m-d', $toDate);

        if (!$from || $from->format('Y-m-d') !== $fromDate || !$to || $to->format('Y-m-d') !== $toDate) {
            throw new InvalidArgumentException('Please enter valid leave dates.');
        }

        if ($to < $from) {
            throw new InvalidArgumentException('The end date cannot be before the start date.');
        }

        if ($reason === '') {
            throw new InvalidArgumentException('Please provide a reason for the leave request.');
        }

        if (mb_strlen($reason) > 1000) {
            throw new InvalidArgumentException('The reason must be 1000 characters or less.');
        }

        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO leave_requests
                (applicant_user_id, leave_type, from_date, to_date, reason, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $leaveType, $fromDate, $toDate, $reason, 'Pending']);

        $leaveId = (int)$pdo->lastInsertId();
        $pdo->commit();

        AuditService::log('LEAVE_REQUEST_CREATE', 'leave_requests', $leaveId, 'Student submitted leave request');
        $_SESSION['flash'] = ['success', 'Leave request submitted successfully.'];
        redirect('leave.php');
    } catch (InvalidArgumentException $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Unable to submit the leave request right now. Please try again.';
    }
}

$stmt = db()->prepare(
    'SELECT id, leave_type, from_date, to_date, reason, status, created_at
     FROM leave_requests
     WHERE applicant_user_id = ?
     ORDER BY created_at DESC, id DESC'
);
$stmt->execute([$userId]);
$requests = $stmt->fetchAll();

$total = count($requests);
$pending = 0;
$approved = 0;
$rejected = 0;
foreach ($requests as $request) {
    $status = strtolower((string)$request['status']);
    if ($status === 'pending') {
        $pending++;
    } elseif ($status === 'approved') {
        $approved++;
    } elseif ($status === 'rejected') {
        $rejected++;
    }
}

page_top('Digital Leave Request');
flash();
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <span class="badge text-bg-primary mb-2">Student Services</span>
            <h2 class="mb-1">Digital Leave Request</h2>
            <p class="text-secondary mb-0">Submit a leave application and track its approval status.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Total Requests</div><div class="fs-3 fw-bold mt-1"><?= $total ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Pending</div><div class="fs-3 fw-bold mt-1"><?= $pending ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Approved</div><div class="fs-3 fw-bold mt-1"><?= $approved ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Rejected</div><div class="fs-3 fw-bold mt-1"><?= $rejected ?></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0">New Leave Application</h5>
        </div>
        <div class="card-body p-4">
            <form method="post" class="row g-3" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="col-md-6 col-xl-3">
                    <label for="leave_type" class="form-label fw-semibold">Leave Type</label>
                    <select id="leave_type" name="leave_type" class="form-select" required>
                        <?php foreach ($allowedTypes as $type): ?>
                            <option value="<?= e($type) ?>" <?= (($_POST['leave_type'] ?? '') === $type) ? 'selected' : '' ?>><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 col-xl-3">
                    <label for="from_date" class="form-label fw-semibold">From Date</label>
                    <input id="from_date" type="date" name="from_date" class="form-control" value="<?= e((string)($_POST['from_date'] ?? '')) ?>" required>
                </div>

                <div class="col-md-6 col-xl-3">
                    <label for="to_date" class="form-label fw-semibold">To Date</label>
                    <input id="to_date" type="date" name="to_date" class="form-control" value="<?= e((string)($_POST['to_date'] ?? '')) ?>" required>
                </div>

                <div class="col-12">
                    <label for="reason" class="form-label fw-semibold">Reason</label>
                    <textarea id="reason" name="reason" class="form-control" rows="4" maxlength="1000" required placeholder="Explain the reason for your leave..."><?= e((string)($_POST['reason'] ?? '')) ?></textarea>
                    <div class="form-text">Maximum 1000 characters.</div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary px-4">Submit Leave Request</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0">My Leave Requests</h5>
        </div>
        <div class="card-body p-0">
            <?php if (!$requests): ?>
                <div class="text-center text-secondary py-5 px-3">
                    <h6>No leave requests yet</h6>
                    <p class="mb-0">Your submitted applications will appear here.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="px-3">Type</th>
                                <th>Period</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <?php
                                $status = (string)$request['status'];
                                $statusClass = match (strtolower($status)) {
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    default => 'warning',
                                };
                                ?>
                                <tr>
                                    <td class="px-3 fw-semibold"><?= e($request['leave_type']) ?></td>
                                    <td><?= e($request['from_date']) ?> → <?= e($request['to_date']) ?></td>
                                    <td style="min-width:240px;max-width:420px;"><?= e($request['reason']) ?></td>
                                    <td><span class="badge text-bg-<?= $statusClass ?>"><?= e($status) ?></span></td>
                                    <td class="text-nowrap"><?= e($request['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php page_bottom(); ?>
