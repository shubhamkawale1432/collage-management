<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$map = require __DIR__ . '/entity_map.php';
$entity = (string) ($_GET['e'] ?? $_POST['e'] ?? '');

if (!isset($map[$entity])) {
    http_response_code(404);
    exit('Invalid entity');
}

$meta = $map[$entity];
require_perm((string) $meta['perm']);

$id = safe_int($_GET['id'] ?? $_POST['id'] ?? 0) ?? 0;
$id = max(0, $id);

$defs = [
    'users' => [['name', 'text'], ['email', 'email'], ['role_id', 'number'], ['password', 'password'], ['status', 'select:active|inactive|suspended']],
    'departments' => [['name', 'text'], ['code', 'text'], ['description', 'textarea'], ['status', 'select:1|0']],
    'courses' => [['name', 'text'], ['code', 'text'], ['duration_years', 'number'], ['status', 'select:1|0']],
    'branches' => [['department_id', 'number'], ['name', 'text'], ['code', 'text'], ['intake', 'number'], ['status', 'select:1|0']],
    'academic_years' => [['label', 'text'], ['is_current', 'select:1|0']],
    'semesters' => [['number', 'number'], ['name', 'text']],
    'divisions' => [['name', 'text']],
    'subjects' => [['code', 'text'], ['name', 'text'], ['branch_id', 'number'], ['semester_id', 'number'], ['credits', 'number'], ['subject_type', 'text'], ['max_marks', 'number'], ['passing_marks', 'number'], ['status', 'select:1|0']],
    'applications' => [['applicant_user_id', 'number'], ['application_type', 'text'], ['title', 'text'], ['description', 'textarea'], ['status', 'select:Draft|Submitted|Under Verification|Correction Required|Approved|Rejected|Waitlisted|Admitted'], ['owner_role', 'text'], ['current_stage', 'text']],
    'exams' => [['name', 'text'], ['type', 'text'], ['academic_year_id', 'number'], ['semester_id', 'number'], ['branch_id', 'number'], ['exam_date', 'date'], ['start_time', 'time'], ['end_time', 'time'], ['room', 'text'], ['max_marks', 'number'], ['status', 'select:scheduled|cancelled|completed']],
    'certificates' => [['student_id', 'number'], ['type', 'text'], ['certificate_no', 'text'], ['issue_date', 'date'], ['status', 'select:Issued|Revoked|Draft']],
    'books' => [['isbn', 'text'], ['title', 'text'], ['author', 'text'], ['publisher', 'text'], ['category', 'text'], ['year', 'number'], ['status', 'select:Active|Inactive']],
    'admissions' => [['application_no', 'text'], ['full_name', 'text'], ['email', 'email'], ['program', 'text'], ['branch', 'text'], ['status', 'select:Draft|Submitted|Under Verification|Correction Required|Verified|Approved|Rejected|Waitlisted|Admitted']],
    'assignment_list' => [['subject_id', 'number'], ['faculty_id', 'number'], ['title', 'text'], ['description', 'textarea'], ['deadline', 'datetime-local'], ['max_marks', 'number'], ['status', 'select:published|draft|closed']],
    'materials' => [['subject_id', 'number'], ['faculty_id', 'number'], ['title', 'text'], ['file_path', 'text'], ['mime_type', 'text'], ['file_size', 'number']],
    'timetables' => [['day_name', 'text'], ['start_time', 'time'], ['end_time', 'time'], ['subject_id', 'number'], ['faculty_id', 'number'], ['branch_id', 'number'], ['semester_id', 'number'], ['division_id', 'number'], ['room', 'text'], ['academic_year_id', 'number']],
    'alumni' => [['student_id', 'number'], ['graduation_year', 'number'], ['status', 'text'], ['current_company', 'text'], ['designation', 'text'], ['contact_email', 'email'], ['contact_mobile', 'text'], ['profile_url', 'url'], ['notes', 'textarea']],
];

$fields = $defs[$entity] ?? [];
if (!$fields) {
    foreach ($meta['columns'] as $column) {
        if ($column !== 'id') {
            $fields[] = [$column, 'text'];
        }
    }
}

$data = [];
if ($id > 0) {
    $query = db()->prepare("SELECT * FROM `{$meta['table']}` WHERE id = ? LIMIT 1");
    $query->execute([$id]);
    $data = $query->fetch() ?: [];

    if (!$data) {
        http_response_code(404);
        exit('Record not found');
    }
}

$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();

    try {
        $names = [];
        $values = [];

        foreach ($fields as [$field, $type]) {
            // Passwords are never loaded from the database and never written as plaintext.
            if ($entity === 'users' && $field === 'password') {
                $password = (string) ($_POST['password'] ?? '');
                if ($id > 0 && $password === '') {
                    continue;
                }
                if (strlen($password) < 8) {
                    throw new RuntimeException('Password must be at least 8 characters.');
                }
                $names[] = 'password_hash';
                $values[] = password_hash($password, PASSWORD_DEFAULT);
                continue;
            }

            $names[] = $field;
            $value = $_POST[$field] ?? null;
            $values[] = is_string($value) ? trim($value) : $value;
        }

        if ($entity === 'users') {
            $emailIndex = array_search('email', $names, true);
            if ($emailIndex !== false) {
                $values[$emailIndex] = strtolower(trim((string) $values[$emailIndex]));
                if (!filter_var($values[$emailIndex], FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid email address.');
                }
            }
        }

        if ($id > 0) {
            if (!$names) {
                throw new RuntimeException('No changes were supplied.');
            }

            $sets = array_map(static fn (string $name): string => "`{$name}` = ?", $names);
            $query = db()->prepare(
                "UPDATE `{$meta['table']}` SET " . implode(', ', $sets) . ' WHERE id = ?'
            );
            $query->execute([...$values, $id]);
            AuditService::log('UPDATE', $entity, $id, 'Entity update');
        } else {
            if (!$names) {
                throw new RuntimeException('No fields supplied.');
            }

            $columns = '`' . implode('`,`', $names) . '`';
            $placeholders = implode(',', array_fill(0, count($names), '?'));
            $query = db()->prepare(
                "INSERT INTO `{$meta['table']}` ({$columns}) VALUES ({$placeholders})"
            );
            $query->execute($values);
            $id = (int) db()->lastInsertId();
            AuditService::log('CREATE', $entity, $id, 'Entity create');
        }

        $_SESSION['flash'] = ['success', 'Record saved successfully.'];
        redirect('entity.php?e=' . urlencode($entity));
    } catch (Throwable $exception) {
        $err = $exception->getMessage();
    }
}

page_top(($id > 0 ? 'Edit ' : 'Create ') . $meta['label']);
flash();
?>
<div class="card p-4">
    <?php if ($err !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= e($err) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="e" value="<?= e($entity) ?>">
        <input type="hidden" name="id" value="<?= e($id) ?>">

        <?php foreach ($fields as [$name, $type]): ?>
            <div class="col-md-6">
                <label class="form-label" for="field_<?= e($name) ?>">
                    <?= e(ucwords(str_replace('_', ' ', $name))) ?>
                </label>

                <?php if (str_starts_with($type, 'select:')): ?>
                    <?php $options = explode('|', substr($type, 7)); ?>
                    <select id="field_<?= e($name) ?>" name="<?= e($name) ?>" class="form-select">
                        <?php foreach ($options as $option): ?>
                            <option value="<?= e($option) ?>" <?= ((string) ($data[$name] ?? '') === (string) $option) ? 'selected' : '' ?>>
                                <?= e($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($type === 'textarea'): ?>
                    <textarea id="field_<?= e($name) ?>" name="<?= e($name) ?>" rows="4" class="form-control"><?= e($data[$name] ?? '') ?></textarea>
                <?php else: ?>
                    <input
                        id="field_<?= e($name) ?>"
                        class="form-control"
                        type="<?= e($type) ?>"
                        name="<?= e($name) ?>"
                        value="<?= $name === 'password' ? '' : e($data[$name] ?? '') ?>"
                        <?= $name === 'password' && $id > 0 ? '' : 'required' ?>
                    >
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="col-12">
            <button class="btn btn-primary" type="submit">Save</button>
            <a class="btn btn-outline-secondary" href="entity.php?e=<?= urlencode($entity) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php page_bottom(); ?>
