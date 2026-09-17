<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$studentStmt = db()->prepare(
    'SELECT s.id, s.enrollment_no, b.name AS branch_name, sem.name AS semester_name
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

$feeStmt = db()->prepare(
    'SELECT sf.id, sf.category, sf.amount, sf.discount, sf.paid_amount, sf.due_date,
            sf.status, fs.name AS fee_structure
     FROM student_fees sf
     LEFT JOIN fee_structures fs ON fs.id = sf.fee_structure_id
     WHERE sf.student_id = ?
     ORDER BY CASE WHEN sf.due_date IS NULL THEN 1 ELSE 0 END, sf.due_date ASC, sf.id DESC'
);
$feeStmt->execute([(int) $student['id']]);
$fees = $feeStmt->fetchAll();

$totalAmount = 0.0;
$totalDiscount = 0.0;
$totalPaid = 0.0;
$totalBalance = 0.0;
$overdue = 0;
$pending = 0;
$paid = 0;
$today = new DateTimeImmutable('today');

foreach ($fees as &$fee) {
    $fee['amount'] = (float) $fee['amount'];
    $fee['discount'] = (float) $fee['discount'];
    $fee['paid_amount'] = (float) $fee['paid_amount'];
    $fee['balance'] = max(0.0, $fee['amount'] - $fee['discount'] - $fee['paid_amount']);
    $status = strtolower((string) $fee['status']);

    if ($fee['balance'] <= 0) {
        $paid++;
    } else {
        $pending++;
        if (!empty($fee['due_date']) && new DateTimeImmutable((string) $fee['due_date']) < $today) {
            $overdue++;
        }
    }

    $totalAmount += $fee['amount'];
    $totalDiscount += $fee['discount'];
    $totalPaid += $fee['paid_amount'];
    $totalBalance += $fee['balance'];
}
unset($fee);

page_top('Digital Fees');
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-muted fw-semibold">Student finance</div>
            <h1 class="h3 mb-1">Digital Fees</h1>
            <p class="text-muted mb-0">
                <?= e($student['enrollment_no'] ?: 'Student') ?> · <?= e($student['branch_name'] ?: 'Branch') ?> · <?= e($student['semester_name'] ?: 'Semester') ?>
            </p>
        </div>
        <a href="payments.php" class="btn btn-primary">Payments &amp; Receipts</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm"><div class="card-body">
                <div class="small text-muted">Total fees</div>
                <div class="h3 fw-bold mb-0">₹<?= e(number_format($totalAmount, 2)) ?></div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm"><div class="card-body">
                <div class="small text-muted">Total paid</div>
                <div class="h3 fw-bold mb-0">₹<?= e(number_format($totalPaid, 2)) ?></div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm"><div class="card-body">
                <div class="small text-muted">Outstanding balance</div>
                <div class="h3 fw-bold mb-0">₹<?= e(number_format($totalBalance, 2)) ?></div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm"><div class="card-body">
                <div class="small text-muted">Overdue items</div>
                <div class="h3 fw-bold mb-0"><?= $overdue ?></div>
            </div></div>
        </div>
    </div>

    <?php if (!$fees): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="display-6 mb-3">💳</div>
                <h2 class="h5">No fee records found</h2>
                <p class="text-muted mb-0">Your fee records will appear here when they are added by the college.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="h5 mb-1">Fee Ledger</h2>
                        <div class="small text-muted"><?= count($fees) ?> fee record<?= count($fees) === 1 ? '' : 's' ?></div>
                    </div>
                    <div class="small text-muted">
                        Paid: <?= $paid ?> · Pending: <?= $pending ?>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-3">Fee</th>
                            <th>Structure</th>
                            <th>Amount</th>
                            <th>Discount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Due date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($fees as $fee):
                        $isOverdue = $fee['balance'] > 0 && !empty($fee['due_date']) && new DateTimeImmutable((string) $fee['due_date']) < $today;
                        $isPaid = $fee['balance'] <= 0;
                        $statusClass = $isPaid ? 'text-bg-success' : ($isOverdue ? 'text-bg-danger' : 'text-bg-warning');
                        $statusLabel = $isPaid ? 'Paid' : ($isOverdue ? 'Overdue' : 'Pending');
                    ?>
                        <tr>
                            <td class="px-3">
                                <div class="fw-semibold"><?= e($fee['category']) ?></div>
                                <?php if (!empty($fee['status'])): ?>
                                    <div class="small text-muted">Record: <?= e($fee['status']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($fee['fee_structure'] ?: '—') ?></td>
                            <td>₹<?= e(number_format($fee['amount'], 2)) ?></td>
                            <td>₹<?= e(number_format($fee['discount'], 2)) ?></td>
                            <td>₹<?= e(number_format($fee['paid_amount'], 2)) ?></td>
                            <td class="fw-semibold">₹<?= e(number_format($fee['balance'], 2)) ?></td>
                            <td><?= $fee['due_date'] ? e(date('d M Y', strtotime((string) $fee['due_date']))) : '—' ?></td>
                            <td><span class="badge <?= $statusClass ?>"><?= e($statusLabel) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2" class="px-3">Totals</th>
                            <th>₹<?= e(number_format($totalAmount, 2)) ?></th>
                            <th>₹<?= e(number_format($totalDiscount, 2)) ?></th>
                            <th>₹<?= e(number_format($totalPaid, 2)) ?></th>
                            <th>₹<?= e(number_format($totalBalance, 2)) ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php page_bottom(); ?>
