<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Super Admin', 'College Admin');

$fields = [
    'college_name' => ['label' => 'College name', 'type' => 'text', 'maxlength' => 150],
    'college_address' => ['label' => 'College address', 'type' => 'text', 'maxlength' => 255],
    'college_phone' => ['label' => 'College phone', 'type' => 'text', 'maxlength' => 30],
    'college_email' => ['label' => 'College email', 'type' => 'email', 'maxlength' => 190],
    'attendance_threshold' => ['label' => 'Attendance threshold (%)', 'type' => 'number', 'min' => 0, 'max' => 100],
    'passing_percentage' => ['label' => 'Passing percentage (%)', 'type' => 'number', 'min' => 0, 'max' => 100],
    'currency' => ['label' => 'Currency', 'type' => 'text', 'maxlength' => 10],
    'timezone' => ['label' => 'Timezone', 'type' => 'text', 'maxlength' => 80],
];

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        check_csrf();

        $values = [];
        foreach ($fields as $key => $meta) {
            $value = trim((string) ($_POST[$key] ?? ''));
            if (isset($meta['maxlength']) && mb_strlen($value) > $meta['maxlength']) {
                throw new RuntimeException($meta['label'] . ' is too long.');
            }
            $values[$key] = $value;
        }

        if ($values['college_email'] !== '' && !filter_var($values['college_email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid college email address.');
        }

        foreach (['attendance_threshold', 'passing_percentage'] as $key) {
            if ($values[$key] === '') {
                continue;
            }
            if (!is_numeric($values[$key]) || (float) $values[$key] < 0 || (float) $values[$key] > 100) {
                throw new RuntimeException($fields[$key]['label'] . ' must be between 0 and 100.');
            }
        }

        if ($values['timezone'] !== '' && !in_array($values['timezone'], timezone_identifiers_list(), true)) {
            throw new RuntimeException('Enter a valid PHP timezone identifier.');
        }

        $save = db()->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );

        db()->beginTransaction();
        foreach ($values as $key => $value) {
            $save->execute([$key, $value]);
        }
        db()->commit();

        AuditService::log('UPDATE', 'settings', null, 'System settings updated');
        $_SESSION['flash'] = ['success', 'System settings saved successfully.'];
        redirect('settings.php');
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        $error = $exception->getMessage();
    }
}

$values = [];
foreach (db()->query('SELECT `key`, `value` FROM settings') as $row) {
    $values[(string) $row['key']] = (string) $row['value'];
}

page_top('System Settings');
flash();
?>
<div class="card p-4">
    <div class="mb-4">
        <h2 class="h5 mb-1">System Settings</h2>
        <p class="text-muted mb-0">Configure core college information and academic defaults.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <?php foreach ($fields as $key => $meta): ?>
            <div class="col-md-6">
                <label class="form-label" for="<?= e($key) ?>"><?= e($meta['label']) ?></label>
                <input
                    id="<?= e($key) ?>"
                    name="<?= e($key) ?>"
                    type="<?= e($meta['type']) ?>"
                    class="form-control"
                    value="<?= e($values[$key] ?? '') ?>"
                    <?php if (isset($meta['maxlength'])): ?>maxlength="<?= e($meta['maxlength']) ?>"<?php endif; ?>
                    <?php if (isset($meta['min'])): ?>min="<?= e($meta['min']) ?>"<?php endif; ?>
                    <?php if (isset($meta['max'])): ?>max="<?= e($meta['max']) ?>"<?php endif; ?>
                >
            </div>
        <?php endforeach; ?>

        <div class="col-12 mt-3">
            <button class="btn btn-primary" type="submit">Save Settings</button>
        </div>
    </form>
</div>
<?php page_bottom(); ?>
