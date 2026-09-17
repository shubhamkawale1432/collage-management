<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Faculty');

$facultyStmt = db()->prepare('SELECT id FROM faculty WHERE user_id = ? AND status = ? LIMIT 1');
$facultyStmt->execute([actor_id(), 'active']);
$facultyId = safe_int($facultyStmt->fetchColumn());

if ($facultyId === null) {
    http_response_code(403);
    exit('Faculty profile is not active.');
}

$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $technology = trim((string) ($_POST['technology'] ?? ''));
    $startDate = trim((string) ($_POST['start_date'] ?? ''));
    $endDate = trim((string) ($_POST['end_date'] ?? ''));
    $repositoryUrl = trim((string) ($_POST['repository_url'] ?? ''));

    if ($title === '' || mb_strlen($title) > 200) $errors[] = 'Project title is required and must be 200 characters or fewer.';
    if (mb_strlen($description) > 5000) $errors[] = 'Description must be 5000 characters or fewer.';
    if ($technology === '' || mb_strlen($technology) > 200) $errors[] = 'Technology stack is required and must be 200 characters or fewer.';
    if ($startDate !== '' && !DateTime::createFromFormat('Y-m-d', $startDate)) $errors[] = 'Please enter a valid start date.';
    if ($endDate !== '' && !DateTime::createFromFormat('Y-m-d', $endDate)) $errors[] = 'Please enter a valid end date.';
    if ($startDate !== '' && $endDate !== '' && $endDate < $startDate) $errors[] = 'End date cannot be before the start date.';
    if ($repositoryUrl !== '' && (mb_strlen($repositoryUrl) > 255 || !filter_var($repositoryUrl, FILTER_VALIDATE_URL))) $errors[] = 'Repository URL must be a valid URL of 255 characters or fewer.';

    if (!$errors) {
        $insert = db()->prepare(
            'INSERT INTO projects (title, description, technology, guide_faculty_id, start_date, end_date, repository_url, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $title,
            $description !== '' ? $description : null,
            $technology,
            $facultyId,
            $startDate !== '' ? $startDate : null,
            $endDate !== '' ? $endDate : null,
            $repositoryUrl !== '' ? $repositoryUrl : null,
            'Active',
        ]);
        $projectId = (int) db()->lastInsertId();
        AuditService::log('CREATE', 'projects', $projectId, 'Project created by faculty');
        $_SESSION['flash'] = ['success', 'Project created successfully.'];
        redirect('projects.php');
    }
}

$listStmt = db()->prepare(
    'SELECT id, title, description, technology, start_date, end_date, repository_url, status
     FROM projects
     WHERE guide_faculty_id = ?
     ORDER BY id DESC'
);
$listStmt->execute([$facultyId]);
$projects = $listStmt->fetchAll();

page_top('Project Studio');
flash();
?>

<div class="row g-4">
    <div class="col-xl-5">
        <div class="card p-4">
            <span class="badge text-bg-primary mb-2">Faculty Workspace</span>
            <h2 class="h4 mb-1">Create project</h2>
            <p class="text-muted">Track projects where you are the faculty guide.</p>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" novalidate>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="mb-3"><label for="title" class="form-label">Project title</label><input id="title" name="title" maxlength="200" class="form-control" value="<?= e($_POST['title'] ?? '') ?>" required></div>
                <div class="mb-3"><label for="technology" class="form-label">Technology stack</label><input id="technology" name="technology" maxlength="200" class="form-control" value="<?= e($_POST['technology'] ?? '') ?>" placeholder="PHP, MySQL, JavaScript" required></div>
                <div class="mb-3"><label for="description" class="form-label">Description</label><textarea id="description" name="description" maxlength="5000" rows="4" class="form-control"><?= e($_POST['description'] ?? '') ?></textarea></div>
                <div class="row g-3">
                    <div class="col-6"><label for="start_date" class="form-label">Start date</label><input id="start_date" name="start_date" type="date" class="form-control" value="<?= e($_POST['start_date'] ?? '') ?>"></div>
                    <div class="col-6"><label for="end_date" class="form-label">End date</label><input id="end_date" name="end_date" type="date" class="form-control" value="<?= e($_POST['end_date'] ?? '') ?>"></div>
                </div>
                <div class="mt-3"><label for="repository_url" class="form-label">Repository URL <span class="text-muted">(optional)</span></label><input id="repository_url" name="repository_url" type="url" maxlength="255" class="form-control" value="<?= e($_POST['repository_url'] ?? '') ?>" placeholder="https://github.com/..." ></div>
                <button type="submit" class="btn btn-primary w-100 mt-4">Create Project</button>
            </form>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4 mb-1">Guided projects</h2><p class="text-muted mb-0">Projects assigned to your faculty guide account.</p></div><span class="badge text-bg-light"><?= e(count($projects)) ?> total</span></div>
            <?php if (!$projects): ?>
                <div class="text-center py-5 text-muted"><h3 class="h6">No projects yet</h3><p class="mb-0">Create your first guided project.</p></div>
            <?php else: ?>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Project</th><th>Technology</th><th>Timeline</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($projects as $project): ?>
                    <tr><td><div class="fw-semibold"><?= e($project['title']) ?></div><?php if ($project['repository_url']): ?><a class="small" href="<?= e($project['repository_url']) ?>" target="_blank" rel="noopener noreferrer">Repository</a><?php endif; ?></td><td><?= e($project['technology']) ?></td><td><?= e($project['start_date'] ?: '—') ?> → <?= e($project['end_date'] ?: '—') ?></td><td><span class="badge text-bg-primary"><?= e($project['status']) ?></span></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php page_bottom();
