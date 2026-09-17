<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Faculty');

$facultyQuery = db()->prepare('SELECT id FROM faculty WHERE user_id = ? AND status = "active" LIMIT 1');
$facultyQuery->execute([actor_id()]);
$facultyId = (int) $facultyQuery->fetchColumn();
if ($facultyId < 1) { http_response_code(403); exit('Faculty profile is not active.'); }

$subjectQuery = db()->prepare('SELECT s.id, s.code, s.name FROM subjects s INNER JOIN faculty_subjects fs ON fs.subject_id = s.id WHERE fs.faculty_id = ? AND s.status = 1 ORDER BY s.code');
$subjectQuery->execute([$facultyId]);
$subjects = $subjectQuery->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $subjectId = safe_int($_POST['subject_id'] ?? null);
    $title = trim((string) ($_POST['title'] ?? ''));
    $errors = [];
    if (!$subjectId) $errors[] = 'Select a valid subject.';
    if ($title === '' || mb_strlen($title) > 200) $errors[] = 'Material title is required and must be at most 200 characters.';

    if (!$errors) {
        $check = db()->prepare('SELECT 1 FROM faculty_subjects WHERE faculty_id = ? AND subject_id = ? LIMIT 1');
        $check->execute([$facultyId, $subjectId]);
        if (!$check->fetchColumn()) $errors[] = 'You can only publish material for subjects assigned to you.';
    }

    if (!$errors) {
        try {
            $allowed = [
                'pdf' => ['application/pdf'],
                'doc' => ['application/msword'],
                'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                'ppt' => ['application/vnd.ms-powerpoint'],
                'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
                'zip' => ['application/zip'],
                'jpg' => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
                'png' => ['image/png'],
            ];
            $file = FileService::save($_FILES['file'] ?? null, 'materials', $allowed);
            $query = db()->prepare('INSERT INTO study_materials(subject_id, faculty_id, title, file_path, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)');
            $query->execute([$subjectId, $facultyId, $title, $file['path'], $file['mime'], $file['size']]);
            AuditService::log('UPLOAD', 'materials', (int) db()->lastInsertId(), 'Study material uploaded');
            $_SESSION['flash'] = ['success', 'Study material uploaded successfully.'];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['danger', 'The material could not be uploaded. Please check the file and try again.'];
        }
    } else {
        $_SESSION['flash'] = ['danger', implode(' ', $errors)];
    }
    redirect('materials.php');
}

$listQuery = db()->prepare('SELECT sm.id, sm.title, sm.file_path, sm.mime_type, sm.file_size, sm.created_at, s.code AS subject_code FROM study_materials sm INNER JOIN subjects s ON s.id = sm.subject_id WHERE sm.faculty_id = ? AND sm.deleted_at IS NULL ORDER BY sm.id DESC');
$listQuery->execute([$facultyId]);
$materials = $listQuery->fetchAll();

page_top('Study Material Vault'); flash();
?>
<div class="hero"><div><span class="pill">FACULTY · RESOURCES</span><h2>Study Material Vault</h2><p class="mb-0">Publish learning resources to subjects assigned to you.</p></div></div>
<div class="card p-4 mb-4">
<h5 class="mb-3">Upload material</h5>
<?php if (!$subjects): ?><div class="alert alert-warning mb-0">No subjects are currently assigned to your faculty profile.</div>
<?php else: ?><form method="post" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div class="col-md-6"><label class="form-label">Subject</label><select name="subject_id" class="form-select" required><option value="">Select subject</option><?php foreach ($subjects as $subject): ?><option value="<?= e($subject['id']) ?>"><?= e($subject['code'].' · '.$subject['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label">Title</label><input name="title" maxlength="200" class="form-control" required></div>
<div class="col-md-8"><label class="form-label">File</label><input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.jpg,.jpeg,.png" required></div>
<div class="col-12"><button class="btn btn-primary">Upload material</button></div></form><?php endif; ?>
</div>
<div class="card p-4"><h5 class="mb-3">Published materials</h5>
<?php if (!$materials): ?><div class="alert alert-light border mb-0">No materials found.</div>
<?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Subject</th><th>Title</th><th>Type</th><th>Size</th><th>Uploaded</th></tr></thead><tbody><?php foreach ($materials as $material): ?><tr><td><?= e((string) $material['subject_code']) ?></td><td><?= e((string) $material['title']) ?></td><td><?= e((string) $material['mime_type']) ?></td><td><?= e(number_format(((int) $material['file_size']) / 1048576, 2)) ?> MB</td><td><?= e((string) $material['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<?php page_bottom();
