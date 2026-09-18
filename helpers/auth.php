<?php
declare(strict_types=1);

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;
    return is_array($user) ? $user : null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    $_SESSION['authenticated_at'] = time();
    $_SESSION['last_activity'] = time();
    rotate_csrf_token();
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function require_login(): void
{
    if (!current_user()) {
        redirect((defined('BASE_URL') ? BASE_URL : '') . '/auth/login.php');
    }

    $timeout = max(300, (int) ($GLOBALS['app']['session_timeout'] ?? 3600));
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    if ($lastActivity <= 0 || time() - $lastActivity > $timeout) {
        logout_user();
        redirect((defined('BASE_URL') ? BASE_URL : '') . '/auth/login.php?expired=1');
    }

    $_SESSION['last_activity'] = time();
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user !== null && in_array((string) ($user['role_name'] ?? ''), $roles, true);
}

function require_role(string ...$roles): void
{
    require_login();
    if (!has_role(...$roles)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function can(string $permission): bool
{
    $user = current_user();
    if ($user === null || empty($user['role_name'])) {
        return false;
    }
    if ((string) $user['role_name'] === 'Super Admin') {
        return true;
    }

    $query = db()->prepare(
        'SELECT 1 FROM role_permissions rp
         INNER JOIN permissions p ON p.id = rp.permission_id
         INNER JOIN roles r ON r.id = rp.role_id
         WHERE r.name = :role AND p.name = :permission LIMIT 1'
    );
    $query->execute([
        'role' => (string) $user['role_name'],
        'permission' => $permission,
    ]);
    return (bool) $query->fetchColumn();
}

function require_perm(string $permission): void
{
    require_login();
    if (!can($permission)) {
        http_response_code(403);
        exit('Permission denied');
    }
}

function actor_id(): ?int
{
    return safe_int(current_user()['id'] ?? null);
}
