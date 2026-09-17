<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Super Admin', 'IT/System Administrator');

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        check_csrf();

        if (($_POST['action'] ?? '') !== 'database') {
            throw new RuntimeException('Invalid backup action.');
        }

        $dir = dirname(__DIR__) . '/storage/backups';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create the backup directory.');
        }

        $timestamp = date('Ymd_His');
        $file = $dir . '/college_management_' . $timestamp . '.sql';
        $binary = getenv('CMS_MYSQLDUMP_BIN') ?: (
            strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && is_file('C:/xampp/mysql/bin/mysqldump.exe')
                ? 'C:/xampp/mysql/bin/mysqldump.exe'
                : 'mysqldump'
        );

        $dbHost = (string) ($GLOBALS['app']['db_host'] ?? getenv('CMS_DB_HOST') ?: '127.0.0.1');
        $dbName = (string) ($GLOBALS['app']['db_name'] ?? getenv('CMS_DB_NAME') ?: 'college_management');
        $dbUser = (string) ($GLOBALS['app']['db_user'] ?? getenv('CMS_DB_USER') ?: 'root');
        $dbPass = (string) ($GLOBALS['app']['db_pass'] ?? getenv('CMS_DB_PASS') ?: '');

        $command = escapeshellarg($binary)
            . ' --host=' . escapeshellarg($dbHost)
            . ' --user=' . escapeshellarg($dbUser)
            . ($dbPass !== '' ? ' --password=' . escapeshellarg($dbPass) : '')
            . ' --single-transaction --routines --triggers '
            . escapeshellarg($dbName)
            . ' > ' . escapeshellarg($file)
            . ' 2>&1';

        exec($command, $output, $exitCode);
        $success = $exitCode === 0 && is_file($file) && filesize($file) > 0;
        $status = $success ? 'Success' : 'Failed';

        if (!$success && is_file($file)) {
            @unlink($file);
        }

        db()->prepare(
            'INSERT INTO backup_logs (created_by, backup_type, file_name, status)
             VALUES (?, ?, ?, ?)'
        )->execute([actor_id(), 'database', basename($file), $status]);

        AuditService::log('BACKUP', 'system', null, $status . ' database backup');
        $_SESSION['flash'] = [
            $success ? 'success' : 'danger',
            $success
                ? 'Database backup created: ' . basename($file)
                : 'Backup failed. Check the mysqldump configuration and database credentials.',
        ];
        redirect('backup.php');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$logs = db()->query(
    'SELECT id, created_at, backup_type, file_name, status
     FROM backup_logs
     ORDER BY id DESC
     LIMIT 20'
)->fetchAll();

page_top('Backup & Recovery Center');
flash();
?>
<div class="card p-4 mb-4">
    <h2 class="h5 mb-1">Database Backup</h2>
    <p class="text-muted">Creates a SQL dump in the protected <code>storage/backups</code> directory.</p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="database">
        <button class="btn btn-primary" type="submit">Create Database Backup Now</button>
    </form>
</div>

<div class="card p-3 table-responsive">
    <h2 class="h5 mb-3">Recent Backup Activity</h2>
    <table class="table align-middle">
        <thead>
            <tr><th>Time</th><th>Type</th><th>File</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php if (!$logs): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No backup activity recorded.</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= e($log['created_at']) ?></td>
                <td><?= e($log['backup_type']) ?></td>
                <td><?= e($log['file_name']) ?></td>
                <td><?= e($log['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php page_bottom(); ?>
