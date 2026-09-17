<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_perm('documents.manage');

$allowedStatuses = ['Approved', 'Rejected', 'Correction Required'];
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        check_csrf();

        $id = safe_int($_POST['id'] ?? null);
        $status = trim((string) ($_POST['status'] ?? ''));
        $remarks = trim((string) ($_POST['remarks'] ?? ''));

        if ($id === null || $id < 1) {
            throw new RuntimeException('Invalid document ID.');
        }
        if (!in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException('Invalid verification status.');
        }
        if (mb_strlen($remarks) > 1000) {
            throw new RuntimeException('Remarks cannot exceed 1000 characters.');
        }

        $document = db()->prepare('SELECT id FROM documents WHERE id = ? LIMIT 1');
        $document->execute([$id]);
        if (!$document->fetchColumn()) {
            throw new RuntimeException('Document not found.');
        }

        $update = db()->prepare(
            'UPDATE documents
             SET verification_status = ?, verified_by = ?, remarks = ?
             WHERE id = ?'
        );
        $update->execute([$status, actor_id(), $remarks, $id]);

        AuditService::log('DOCUMENT_VERIFY', 'documents', $id, $status);
        $_SESSION['flash'] = ['success', 'Document verification status updated.'];
        redirect('documents.php');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$rows = db()->query(
    'SELECT d.id, d.title, d.category, d.verification_status, d.remarks,
            d.created_at, u.name AS owner_name
     FROM documents d
     INNER JOIN users u ON u.id = d.owner_user_id
     ORDER BY d.id DESC'
)->fetchAll();

page_top('Digital Document Verification');
flash();
?>
<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Document Verification</h2>
            <p class="text-muted mb-0">Review submitted documents and record verification decisions.</p>
        </div>
        <span class="badge text-bg-secondary"><?= number_format(count($rows)) ?> documents</span>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Owner</th>
                    <th>Document</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th style="min-width:420px">Verification</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No documents found.</td></tr>
            <?php endif; ?>

            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['owner_name']) ?></td>
                    <td>
                        <strong><?= e($row['title']) ?></strong>
                        <div class="small text-muted"><?= e($row['created_at']) ?></div>
                    </td>
                    <td><?= e($row['category']) ?></td>
                    <td><?= e($row['verification_status']) ?></td>
                    <td>
                        <form method="post" class="d-flex flex-wrap gap-2">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                            <select name="status" class="form-select form-select-sm" style="max-width:190px" required>
                                <?php foreach ($allowedStatuses as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $row['verification_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input name="remarks" class="form-control form-control-sm" maxlength="1000" value="<?= e($row['remarks'] ?? '') ?>" placeholder="Remarks">
                            <button class="btn btn-sm btn-primary" type="submit">Save</button>
                            <a class="btn btn-sm btn-outline-secondary" href="../files/download.php?id=<?= e($row['id']) ?>">Download</a>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php page_bottom(); ?>
