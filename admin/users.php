<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Super Admin', 'College Admin');

$roles = db()->query('SELECT id, name FROM roles ORDER BY name')->fetchAll();
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        check_csrf();

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $roleId = safe_int($_POST['role_id'] ?? null);

        if ($name === '' || mb_strlen($name) > 150) {
            throw new RuntimeException('Enter a valid full name.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }
        if ($roleId === null || $roleId < 1) {
            throw new RuntimeException('Select a valid role.');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters.');
        }

        $roleQuery = db()->prepare('SELECT id, name FROM roles WHERE id = ? LIMIT 1');
        $roleQuery->execute([$roleId]);
        $role = $roleQuery->fetch();
        if (!$role) {
            throw new RuntimeException('Selected role does not exist.');
        }

        // College Admin must not be able to create another Super Admin account.
        if (has_role('College Admin') && $role['name'] === 'Super Admin') {
            throw new RuntimeException('Only a Super Admin can create a Super Admin account.');
        }

        $exists = db()->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) {
            throw new RuntimeException('An account with this email already exists.');
        }

        $insert = db()->prepare(
            'INSERT INTO users (role_id, name, email, password_hash, status)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $roleId,
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            'active',
        ]);

        $newId = (int) db()->lastInsertId();
        AuditService::log('CREATE', 'users', $newId, 'User account created');
        $_SESSION['flash'] = ['success', 'User account created successfully.'];
        redirect('users.php');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$rows = db()->query(
    'SELECT u.id, u.name, u.email, r.name AS role_name, u.status, u.created_at
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     ORDER BY u.id DESC'
)->fetchAll();

page_top('Identity & Role Management');
flash();
?>
<div class="card p-4 mb-3">
    <h2 class="h5 mb-3">Create User Account</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="col-md-4">
            <label class="form-label" for="name">Full name</label>
            <input id="name" name="name" class="form-control" maxlength="150" required>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="email">Email</label>
            <input id="email" name="email" type="email" class="form-control" maxlength="190" required>
        </div>

        <div class="col-md-2">
            <label class="form-label" for="role_id">Role</label>
            <select id="role_id" name="role_id" class="form-select" required>
                <option value="">Select role</option>
                <?php foreach ($roles as $role): ?>
                    <?php if (has_role('College Admin') && $role['name'] === 'Super Admin') continue; ?>
                    <option value="<?= e($role['id']) ?>"><?= e($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label" for="password">Password</label>
            <input id="password" name="password" type="password" class="form-control" minlength="8" autocomplete="new-password" required>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" type="submit">Create Account</button>
        </div>
    </form>
</div>

<div class="card p-3 table-responsive">
    <h2 class="h5 mb-3">User Accounts</h2>
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e($row['name']) ?></td>
                <td><?= e($row['email']) ?></td>
                <td><?= e($row['role_name']) ?></td>
                <td><?= e($row['status']) ?></td>
                <td><?= e($row['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php page_bottom(); ?>
