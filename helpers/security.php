<?php
declare(strict_types=1);

/** Escape untrusted data before rendering it into HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirect and stop execution. */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Return the current CSRF token, creating one when necessary. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

/** Rotate the CSRF token after a sensitive authentication event. */
function rotate_csrf_token(): string
{
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

/** Validate CSRF for state-changing requests. */
function check_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $submitted = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf'] ?? '';

    if (!is_string($submitted) || !is_string($expected) || $submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(419);
        exit('CSRF validation failed');
    }
}

/** Send a JSON response and stop execution. */
function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

/** Get the best available client IP for application-level rate limiting. */
function client_ip(): string
{
    return filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';
}

/**
 * Session-based rate limiter.
 * Returns false when the limit has been reached during the rolling window.
 */
function rate_limit_key(string $key, int $maxAttempts = 60, int $windowSeconds = 60): bool
{
    $maxAttempts = max(1, $maxAttempts);
    $windowSeconds = max(1, $windowSeconds);
    $sessionKey = 'rl_' . hash('sha256', $key);
    $now = time();

    $attempts = $_SESSION[$sessionKey] ?? [];
    if (!is_array($attempts)) {
        $attempts = [];
    }

    $attempts = array_values(array_filter(
        $attempts,
        static fn ($timestamp): bool => is_int($timestamp) && $timestamp > ($now - $windowSeconds)
    ));

    if (count($attempts) >= $maxAttempts) {
        $_SESSION[$sessionKey] = $attempts;
        return false;
    }

    $attempts[] = $now;
    $_SESSION[$sessionKey] = $attempts;
    return true;
}

/** Generate cryptographically secure random hexadecimal data. */
function secure_random(int $bytes = 32): string
{
    if ($bytes < 1) {
        throw new InvalidArgumentException('Random byte length must be greater than zero.');
    }

    return bin2hex(random_bytes($bytes));
}

/** Return a safe integer from user input, or null when invalid. */
function safe_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }

    if (!is_string($value) || !preg_match('/^-?\d+$/', $value)) {
        return null;
    }

    return filter_var($value, FILTER_VALIDATE_INT) === false ? null : (int) $value;
}
