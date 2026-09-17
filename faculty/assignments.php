<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Faculty');

$facultyStmt = db()->prepare('SELECT id FROM faculty WHERE user_id = ? AND status = ? LIMIT 1');
$facultyStmt->execute([actor_id(), 'Active']);
$facultyId = safe_int($facultyStmt->fetchColumn());

if ($facultyId === null) {
    http_response_code(403);
    exit('Faculty profile is not active.');
}

$subjectStmt = db()->prepare(
    'SELECT s.id, s.code, s.name
     FROM subjects s
     INNER JOIN faculty_subjects fs ON fs.subject_id = s.id
     WHERE fs.faculty_id = ?
     ORDER BY s.name'
);
$subjectStmt->execute([$facultyId]);
$subjects = $subjectStmt->fetchAll();

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();

    $subjectId = safe_int($_POST['subject_id'] ?? null);
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $deadline = trim((string) ($_POST['deadline'] ?? ''));
    $maxMarksRaw = trim((string) ($_POST['max_marks'] ?? ''));

    if ($subjectId === null || $subjectId < 1) {
        $errors[] = 'Please select a valid subject.';
    }
    if ($title === '' || mb_strlen($title) > 200) {
        $errors[] = 'Title is required and must be 200 characters or fewer.';
    }
    if (mb_strlen($description) > 2000) {
        $errors[] = 'Description must be 2000 characters or fewer.';
    }
    if ($deadline === '') {
        $errors[] = 'Deadline is required.';
    }
    if (!is_numeric($maxMarksRaw) || (float) $maxMarksRaw <= 0 || (float) $maxMarksRaw > 1000) {
        $errors[] = 'Maximum marks must be between 0 and 1000.';
    }

    if (!$errors) {
        $ownershipStmt = db()->prepare(
            'SELECT 1 FROM faculty_subjects WHERE faculty_id = ? AND subject_id = ? LIMIT 1'
        );
        $ownershipStmt->execute([$facultyId, $subjectId]);
        if (!$ownershipStmt->fetchColumn()) {
            $errors[] = 'You can only create assignments for subjects assigned to you.';
        }
    }

    $deadlineValue = null;
    if (!$errors) {
        $deadlineObject = DateTime::createFromFormat('Y-m-d\\TH:i', $deadline);
        if (!$deadlineObject || $deadlineObject->format('Y-m-d\\TH:i') !== $deadline) {
            $errors[] = 'Please enter a valid deadline.';
        } elseif ($deadlineObject <= new DateTime()) {
            $errors[] = 'Deadline must be in the future.';
        } else {
            $deadlineValue = $deadlineObject->format('Y-m-d H:i:s');
        }
    }

    if (!$errors) {
        $insert = db()->prepare(
            'INSERT INTO assignments
                (subject_id, faculty_id, title, description, deadline, max_marks, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $subjectId,
            $facultyId,
            $title,
            $description !== '' ? $description : null,
            $deadlineValue,
            (float) $maxMarksRaw,
            'published',
        ]);

        $assignmentId = (int) db()->lastInsertId();
        AuditService::log('CREATE', 'assignments', $assignmentId, 'Assignment published by faculty');
        $_SESSION['flash'] = ['success', 'Assignment published successfully.'];
        redirect('assignments.php');
    }
}

$listStmt = db()->prepare(
    'SELECT a.id, a.title, a.description, a.deadline, a.max_marks, a.status,
            s.code AS subject_code, s.name AS subject_name
     FROM assignments a
     INNER JOIN subjects s ON s.id = a.subject_id
     WHERE a.faculty_id = ? AND a.deleted_at IS NULL
     ORDER BY a.deadline ASC, a.id DESC'
);
$listStmt->execute([$facultyId]);
$assignments = $listStmt->fetchAll();

page_top('Assignment Studio');
flash();
?>

<div class="row g-4">
    <div class="col-xl-5">
        <div class="card p-4 h-100">
            <div class="mb-4">
                <span class="badge text-bg-primary mb-2">Faculty Workspace</span>
                <h2 class="h4 mb-1">Create assignment</h2>
                <p class="text-muted mb-0">Publish a clear task for a subject assigned to you.</p>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert">
                    <strong>Please fix the following:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="mb-3">
                    <label for="subject_id" class="form-label">Subject</label>
                    <select id="subject_id" name="subject_id" class="form-select" required>
                        <option value="">Select subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= e($subject['id']) ?>" <?= ((string) ($_POST['subject_id'] ?? '') === (string) $subject['id']) ? 'selected' : '' ?>>
                                <?= e($subject['code'] . ' · ' . $subject['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="title" class="form-label">Assignment title</label>
                    <input id="title" name="title" maxlength="200" class="form-control" value="<?= e($_POST['title'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" maxlength="2000" rows="5" class="form-control" placeholder="Explain the task, expected output and instructions."><?= e($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="row g-3">
                    <div class="col-md-7">
                        <label for="deadline" class="form-label">Deadline</label>
                        <input id="deadline" name="deadline" type="datetime-local" class="form-control" value="<?= e($_POST['deadline'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-5">
                        <label for="max_marks" class="form-label">Max marks</label>
                        <input id="max_marks" name="max_marks" type="number" min="0.01" max="1000" step="0.01" class="form-control" value="<?= e($_POST['max_marks'] ?? '10') ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-4">Publish Assignment</button>
            </form>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h4 mb-1">Published assignments</h2>
                    <p class="text-muted mb-0">Assignments created by your faculty account.</p>
                </div>
                <span class="badge text-bg-light"><?= e(count($assignments)) ?> total</span>
            </div>

            <?php if (!$assignments): ?>
                <div class="text-center py-5 text-muted">
                    <h3 class="h6">No assignments yet</h3>
                    <p class="mb-0">Create your first assignment using the form.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Assignment</th>
                                <th>Subject</th>
                                <th>Deadline</th>
                                <th>Marks</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($assignment['title']) ?></div>
                                        <?php if (!empty($assignment['description'])): ?>
                                            <div class="small text-muted text-truncate" style="max-width: 260px;"><?= e($assignment['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($assignment['subject_code'] . ' · ' . $assignment['subject_name']) ?></td>
                                    <td><?= e(date('d M Y, h:i A', strtotime((string) $assignment['deadline']))) ?></td>
                                    <td><?= e($assignment['max_marks']) ?></td>
                                    <td><span class="badge text-bg-primary"><?= e(ucfirst((string) $assignment['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php page_bottom();
