<?php
declare(strict_types=1);

/** Return the authenticated user stored in the current session. */
function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;
    return is_array($user) ? $user : null;
}

/** Create an authenticated session and prevent session fixation. */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    $_SESSION['last_activity'] = time();
    rotate_csrf_token();
}

/** Destroy the authenticated session and its cookie. */
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

/** Require an authenticated user and enforce inactivity timeout. */
function require_login(): void
{
    if (!current_user()) {
        redirect((defined('BASE_URL') ? BASE_URL : '') . '/auth/login.php');
    }

    $timeout = (int) ($GLOBALS['app']['session_timeout'] ?? 3600);
    $timeout = max(300, $timeout);
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);

    if ($lastActivity <= 0 || (time() - $lastActivity) > $timeout) {
        logout_user();
        redirect((defined('BASE_URL') ? BASE_URL : '') . '/auth/login.php?expired=1');
    }

    $_SESSION['last_activity'] = time();
}

/** Check whether the current user has one of the supplied roles. */
function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user !== null
        && isset($user['role_name'])
        && in_array((string) $user['role_name'], $roles, true);
}

/** Require one of the supplied roles. */
function require_role(string ...$roles): void
{
    require_login();

    if (!has_role(...$roles)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

/** Check a named permission against the role-permission table. */
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
        'SELECT 1
         FROM role_permissions rp
         INNER JOIN permissions p ON p.id = rp.permission_id
         INNER JOIN roles r ON r.id = rp.role_id
         WHERE r.name = ? AND p.name = ?
         LIMIT 1'
    );
    $query->execute([(string) $user['role_name'], $permission]);

    return (bool) $query->fetchColumn();
}

/** Require a named permission. */
function require_perm(string $permission): void
{
    require_login();

    if (!can($permission)) {
        http_response_code(403);
        exit('Permission denied');
    }
}

/** Return the authenticated user's database ID, when available. */
function actor_id(): ?int
{
    $id = current_user()['id'] ?? null;
    return safe_int($id);
}
