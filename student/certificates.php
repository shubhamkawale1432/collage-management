<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$studentStmt = db()->prepare(
    'SELECT id, enrollment_no
     FROM students
     WHERE user_id = ? AND status = "active"
     LIMIT 1'
);
$studentStmt->execute([actor_id()]);
$student = $studentStmt->fetch();

if (!$student) {
    http_response_code(403);
    exit('Active student profile not found.');
}

$certificateStmt = db()->prepare(
    'SELECT c.id, c.type, c.certificate_no, c.issue_date, c.status,
            v.verification_code
     FROM certificates c
     LEFT JOIN certificate_verifications v ON v.certificate_id = c.id
     WHERE c.student_id = ?
     ORDER BY c.issue_date DESC, c.id DESC'
);
$certificateStmt->execute([(int) $student['id']]);
$certificates = $certificateStmt->fetchAll();

$total = count($certificates);
$active = 0;
$revoked = 0;
$pending = 0;

foreach ($certificates as $certificate) {
    $status = strtolower((string) $certificate['status']);
    if (in_array($status, ['active', 'issued', 'valid'], true)) {
        $active++;
    } elseif (in_array($status, ['revoked', 'cancelled'], true)) {
        $revoked++;
    } else {
        $pending++;
    }
}

page_top('Digital Certificates');
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-muted fw-semibold">Student services</div>
            <h1 class="h3 mb-1">Digital Certificates</h1>
            <p class="text-muted mb-0">View issued certificates and verify their authenticity.</p>
        </div>
        <div class="small text-muted">Enrollment: <?= e($student['enrollment_no'] ?: '—') ?></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Total certificates</div>
            <div class="h3 fw-bold mb-0"><?= $total ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Valid / issued</div>
            <div class="h3 fw-bold mb-0"><?= $active ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Pending</div>
            <div class="h3 fw-bold mb-0"><?= $pending ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Revoked / cancelled</div>
            <div class="h3 fw-bold mb-0"><?= $revoked ?></div>
        </div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-1">Certificate Records</h2>
            <div class="small text-muted">Only certificates belonging to your active student profile are displayed.</div>
        </div>

        <?php if (!$certificates): ?>
            <div class="card-body text-center py-5">
                <div class="display-6 mb-3">🎓</div>
                <h3 class="h5">No certificates available</h3>
                <p class="text-muted mb-0">Certificates issued by the college will appear here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-3">Certificate</th>
                            <th>Certificate No.</th>
                            <th>Issue Date</th>
                            <th>Status</th>
                            <th>Verification</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($certificates as $certificate):
                        $status = strtolower((string) $certificate['status']);
                        $badge = match ($status) {
                            'active', 'issued', 'valid' => 'text-bg-success',
                            'revoked', 'cancelled' => 'text-bg-danger',
                            default => 'text-bg-warning',
                        };
                    ?>
                        <tr>
                            <td class="px-3">
                                <div class="fw-semibold"><?= e($certificate['type']) ?></div>
                            </td>
                            <td><code><?= e($certificate['certificate_no']) ?></code></td>
                            <td>
                                <?= $certificate['issue_date']
                                    ? e(date('d M Y', strtotime((string) $certificate['issue_date'])))
                                    : '—' ?>
                            </td>
                            <td><span class="badge <?= $badge ?>"><?= e($certificate['status']) ?></span></td>
                            <td>
                                <?php if (!empty($certificate['verification_code'])): ?>
                                    <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer"
                                       href="../public/verification.php?code=<?= urlencode((string) $certificate['verification_code']) ?>">
                                        Verify Certificate
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">Not available</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php page_bottom(); ?>
