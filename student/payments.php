<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$studentStmt = db()->prepare(
    'SELECT id, enrollment_no FROM students WHERE user_id = ? AND status = "active" LIMIT 1'
);
$studentStmt->execute([actor_id()]);
$student = $studentStmt->fetch();

if (!$student) {
    http_response_code(403);
    exit('Active student profile not found.');
}

$studentId = (int) $student['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $feeId = safe_int($_POST['fee_id'] ?? 0);

    if ($feeId <= 0) {
        $_SESSION['flash'] = ['danger', 'Invalid fee record.'];
        redirect('payments.php');
    }

    $feeStmt = db()->prepare(
        'SELECT id, category, amount, discount, paid_amount, due_date
         FROM student_fees WHERE id = ? AND student_id = ? LIMIT 1'
    );
    $feeStmt->execute([$feeId, $studentId]);
    $fee = $feeStmt->fetch();

    if (!$fee) {
        $_SESSION['flash'] = ['danger', 'Fee record not found.'];
        redirect('payments.php');
    }

    $pending = max(0.0, (float) $fee['amount'] - (float) $fee['discount'] - (float) $fee['paid_amount']);
    if ($pending <= 0) {
        $_SESSION['flash'] = ['info', 'This fee has already been paid.'];
        redirect('payments.php');
    }

    try {
        $result = PaymentService::createDemoPayment($studentId, $feeId, $pending);
        $_SESSION['flash'] = ['success', 'Payment recorded successfully. Receipt ' . $result['receipt_no'] . '.'];
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['danger', 'Payment could not be completed. Please try again.'];
    }

    redirect('payments.php');
}

$feeStmt = db()->prepare(
    'SELECT sf.id, sf.category, sf.amount, sf.discount, sf.paid_amount, sf.due_date,
            GREATEST(sf.amount - sf.discount - sf.paid_amount, 0) AS pending
     FROM student_fees sf
     WHERE sf.student_id = ? AND sf.paid_amount < sf.amount - sf.discount
     ORDER BY CASE WHEN sf.due_date IS NULL THEN 1 ELSE 0 END, sf.due_date ASC, sf.id DESC'
);
$feeStmt->execute([$studentId]);
$pendingFees = $feeStmt->fetchAll();

$receiptStmt = db()->prepare(
    'SELECT p.id, p.reference, p.amount, p.provider, p.status, p.paid_at,
            sf.category, rc.receipt_no, rc.issued_at
     FROM payments p
     LEFT JOIN receipts rc ON rc.payment_id = p.id
     LEFT JOIN student_fees sf ON sf.id = p.student_fee_id
     WHERE p.student_id = ?
     ORDER BY p.paid_at DESC, p.id DESC'
);
$receiptStmt->execute([$studentId]);
$payments = $receiptStmt->fetchAll();

$totalPending = 0.0;
foreach ($pendingFees as $fee) {
    $totalPending += (float) $fee['pending'];
}

$totalPaid = 0.0;
foreach ($payments as $payment) {
    if (strtolower((string) $payment['status']) === 'success') {
        $totalPaid += (float) $payment['amount'];
    }
}

page_top('Payments & Receipts');
flash();
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-muted fw-semibold">Student finance</div>
            <h1 class="h3 mb-1">Payments &amp; Receipts</h1>
            <p class="text-muted mb-0">Securely view your payment history and college receipts.</p>
        </div>
        <a href="fees.php" class="btn btn-outline-primary">Back to Fee Center</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Outstanding fees</div><div class="h3 fw-bold mb-0">₹<?= e(number_format($totalPending, 2)) ?></div>
        </div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Successful payments</div><div class="h3 fw-bold mb-0">₹<?= e(number_format($totalPaid, 2)) ?></div>
        </div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Payment count</div><div class="h3 fw-bold mb-0"><?= count($payments) ?></div>
        </div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Student ID</div><div class="h5 fw-bold mb-0"><?= e($student['enrollment_no'] ?: '—') ?></div>
        </div></div></div>
    </div>

    <?php if ($pendingFees): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-1">Pending Fees</h2>
                <div class="small text-muted">DemoGateway mode records the payment locally and generates a receipt.</div>
            </div>
            <div class="card-body"><div class="row g-3">
                <?php foreach ($pendingFees as $fee): ?>
                    <div class="col-12 col-lg-6"><div class="border rounded-3 p-3 h-100">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-semibold"><?= e($fee['category']) ?></div>
                                <div class="small text-muted mt-1">
                                    Pending ₹<?= e(number_format((float) $fee['pending'], 2)) ?>
                                    <?php if ($fee['due_date']): ?> · Due <?= e(date('d M Y', strtotime((string) $fee['due_date']))) ?><?php endif; ?>
                                </div>
                            </div>
                            <span class="badge text-bg-warning">Pending</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="fw-bold">₹<?= e(number_format((float) $fee['pending'], 2)) ?></span>
                            <form method="post" class="m-0" onsubmit="return confirm('Record this demo payment?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="fee_id" value="<?= e($fee['id']) ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Pay Now</button>
                            </form>
                        </div>
                    </div></div>
                <?php endforeach; ?>
            </div></div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-1">Payment History &amp; Receipts</h2>
            <div class="small text-muted">Every successful payment is linked to its receipt number.</div>
        </div>
        <?php if (!$payments): ?>
            <div class="card-body text-center py-5">
                <div class="display-6 mb-3">🧾</div>
                <h3 class="h5">No payments yet</h3>
                <p class="text-muted mb-0">Completed payments and receipt numbers will appear here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="px-3">Receipt</th><th>Fee</th><th>Amount</th><th>Reference</th><th>Provider</th><th>Status</th><th>Paid on</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $payment): $success = strtolower((string) $payment['status']) === 'success'; ?>
                    <tr>
                        <td class="px-3"><span class="fw-semibold"><?= e($payment['receipt_no'] ?: '—') ?></span></td>
                        <td><?= e($payment['category'] ?: 'Fee payment') ?></td>
                        <td>₹<?= e(number_format((float) $payment['amount'], 2)) ?></td>
                        <td><code><?= e($payment['reference']) ?></code></td>
                        <td><?= e($payment['provider'] ?: '—') ?></td>
                        <td><span class="badge <?= $success ? 'text-bg-success' : 'text-bg-danger' ?>"><?= e($payment['status']) ?></span></td>
                        <td><?= $payment['paid_at'] ? e(date('d M Y, h:i A', strtotime((string) $payment['paid_at']))) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
</div>

<?php page_bottom(); ?>
