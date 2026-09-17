<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$allowedMimeTypes = [
    'pdf' => ['application/pdf'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'doc' => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
];

$categories = ['Identity', 'Academic', 'Scholarship', 'Certificate', 'Application', 'Other'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $title = trim((string) ($_POST['title'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));

    if ($title === '' || mb_strlen($title) > 200) {
        $errors[] = 'Document title is required and must be 200 characters or fewer.';
    }
    if (!in_array($category, $categories, true)) {
        $errors[] = 'Please select a valid document category.';
    }
    if (empty($_FILES['file']) || (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Please select a valid document to upload.';
    }

    if (!$errors) {
        try {
            $upload = FileService::save($_FILES['file'], 'documents', $allowedMimeTypes);

            $insert = db()->prepare(
                'INSERT INTO documents
                    (owner_user_id, title, category, file_path, mime_type, file_size)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                actor_id(),
                $title,
                $category,
                $upload['path'],
                $upload['mime'],
                $upload['size'],
            ]);

            $documentId = (int) db()->lastInsertId();
            AuditService::log('CREATE', 'documents', $documentId, 'Student uploaded document');
            $_SESSION['flash'] = ['success', 'Document uploaded successfully and is awaiting verification.'];
            redirect('documents.php');
        } catch (Throwable $e) {
            $errors[] = 'The document could not be uploaded. Please check the file and try again.';
        }
    }
}

$list = db()->prepare(
    'SELECT id, title, category, mime_type, file_size, verification_status, remarks, created_at
     FROM documents
     WHERE owner_user_id = ?
     ORDER BY id DESC'
);
$list->execute([actor_id()]);
$documents = $list->fetchAll();

$total = count($documents);
$verified = 0;
$pending = 0;
$rejected = 0;

foreach ($documents as $document) {
    $status = strtolower((string) $document['verification_status']);
    if (in_array($status, ['verified', 'approved'], true)) {
        $verified++;
    } elseif (in_array($status, ['rejected', 'declined'], true)) {
        $rejected++;
    } else {
        $pending++;
    }
}

function document_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return number_format($bytes / 1048576, 1) . ' MB';
}

page_top('My Secure Documents');
flash();
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-muted fw-semibold">Student services</div>
            <h1 class="h3 mb-1">My Secure Documents</h1>
            <p class="text-muted mb-0">Upload and track documents submitted for college verification.</p>
        </div>
        <a href="certificates.php" class="btn btn-outline-primary">Digital Certificates</a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">Upload could not be completed:</div>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Total documents</div>
            <div class="h3 fw-bold mb-0"><?= $total ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Verified</div>
            <div class="h3 fw-bold mb-0"><?= $verified ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Pending review</div>
            <div class="h3 fw-bold mb-0"><?= $pending ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Rejected</div>
            <div class="h3 fw-bold mb-0"><?= $rejected ?></div>
        </div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-1">Upload Document</h2>
            <div class="small text-muted">Supported: PDF, JPG, JPEG, PNG, DOC and DOCX. File validation is handled server-side.</div>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="col-md-5">
                    <label for="title" class="form-label">Document title</label>
                    <input id="title" name="title" class="form-control" maxlength="200" required
                           value="<?= e((string) ($_POST['title'] ?? '')) ?>"
                           placeholder="e.g. 10th Marksheet">
                </div>

                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select id="category" name="category" class="form-select" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category) ?>" <?= (($_POST['category'] ?? '') === $category) ? 'selected' : '' ?>><?= e($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="file" class="form-label">File</label>
                    <input id="file" type="file" name="file" class="form-control" required
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4">Upload Document</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-1">Document History</h2>
            <div class="small text-muted">Only documents owned by your account are listed.</div>
        </div>

        <?php if (!$documents): ?>
            <div class="card-body text-center py-5">
                <div class="display-6 mb-3">📁</div>
                <h3 class="h5">No documents uploaded</h3>
                <p class="text-muted mb-0">Upload your first document using the form above.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-3">Document</th>
                            <th>Category</th>
                            <th>File</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th>Uploaded</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $document):
                        $status = strtolower((string) $document['verification_status']);
                        $badge = match ($status) {
                            'verified', 'approved' => 'text-bg-success',
                            'rejected', 'declined' => 'text-bg-danger',
                            default => 'text-bg-warning',
                        };
                    ?>
                        <tr>
                            <td class="px-3 fw-semibold"><?= e($document['title']) ?></td>
                            <td><?= e($document['category']) ?></td>
                            <td>
                                <div><?= e(strtoupper(pathinfo((string) $document['mime_type'], PATHINFO_EXTENSION) ?: 'FILE')) ?></div>
                                <div class="small text-muted"><?= e(document_size((int) $document['file_size'])) ?></div>
                            </td>
                            <td><span class="badge <?= $badge ?>"><?= e($document['verification_status']) ?></span></td>
                            <td><?= e($document['remarks'] ?: '—') ?></td>
                            <td><?= $document['created_at'] ? e(date('d M Y, h:i A', strtotime((string) $document['created_at']))) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php page_bottom(); ?>
