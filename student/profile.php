<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$user = current_user();

$profileStmt = db()->prepare(
    "SELECT u.id, u.name, u.email, u.profile_photo,
            s.id AS student_id, s.enrollment_no, s.mobile, s.dob, s.gender,
            s.address, s.parent_name, s.parent_mobile, s.admission_no,
            s.branch, s.semester, s.division, s.status
     FROM users u
     JOIN students s ON s.user_id = u.id
     WHERE u.id = ?
     LIMIT 1"
);
$profileStmt->execute([(int) $user['id']]);
$student = $profileStmt->fetch();

if (!$student) {
    $_SESSION['flash'] = ['danger', 'Student profile not found.'];
    redirect(BASE_URL . '/student/index.php');
}

$initials = '';
$nameParts = preg_split('/\s+/', trim((string) $student['name'])) ?: [];
foreach (array_slice($nameParts, 0, 2) as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = $initials ?: 'S';

$fields = [
    'email' => 'Email',
    'mobile' => 'Mobile',
    'dob' => 'Date of Birth',
    'gender' => 'Gender',
    'address' => 'Address',
    'parent_name' => 'Parent / Guardian',
    'parent_mobile' => 'Parent Mobile',
    'admission_no' => 'Admission No.',
];

page_top('My Digital Identity');
?>

<div class="container-fluid py-3">
    <div class="mb-4">
        <div class="text-uppercase small text-muted fw-semibold">Student account</div>
        <h1 class="h3 mb-1">My Digital Identity</h1>
        <p class="text-muted mb-0">View your registered personal and academic identity information.</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <?php if (!empty($student['profile_photo'])): ?>
                        <img src="<?= e((string) $student['profile_photo']) ?>"
                             alt="Profile photo"
                             class="rounded-circle mb-3"
                             style="width:120px;height:120px;object-fit:cover">
                    <?php else: ?>
                        <div class="avatar-xl mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle">
                            <?= e($initials) ?>
                        </div>
                    <?php endif; ?>

                    <h2 class="h4 mb-1"><?= e($student['name']) ?></h2>
                    <div class="text-muted mb-3"><?= e($student['enrollment_no']) ?></div>

                    <span class="badge <?= $student['status'] === 'Active' ? 'text-bg-success' : 'text-bg-secondary' ?> px-3 py-2">
                        <?= e($student['status'] ?: 'Unknown') ?> Student
                    </span>

                    <hr class="my-4">

                    <div class="text-start small">
                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span class="text-muted">Branch</span>
                            <strong><?= e($student['branch'] ?: '—') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span class="text-muted">Semester</span>
                            <strong><?= e($student['semester'] ?: '—') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between gap-3">
                            <span class="text-muted">Division</span>
                            <strong><?= e($student['division'] ?: '—') ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-xl-9">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-1">Personal Information</h2>
                    <div class="small text-muted">Information registered with your student account.</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($fields as $field => $label):
                            $value = trim((string) ($student[$field] ?? ''));
                        ?>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold"><?= e($label) ?></label>
                                <div class="form-control bg-light" style="min-height:42px">
                                    <?= e($value !== '' ? $value : 'Not provided') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-1">Academic Identity</h2>
                    <div class="small text-muted">These fields are maintained by authorized college staff.</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded">
                                <div class="small text-muted">Enrollment No.</div>
                                <div class="fw-semibold mt-1"><?= e($student['enrollment_no'] ?: '—') ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded">
                                <div class="small text-muted">Admission No.</div>
                                <div class="fw-semibold mt-1"><?= e($student['admission_no'] ?: '—') ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded">
                                <div class="small text-muted">Status</div>
                                <div class="fw-semibold mt-1"><?= e($student['status'] ?: '—') ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4 mb-0 small">
                        Need to correct your academic or personal details? Contact the authorized college administration instead of editing institutional records directly.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php page_bottom(); ?>
