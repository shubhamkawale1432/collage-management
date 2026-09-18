<?php
declare(strict_types=1);

$env = static function (string $key, mixed $default = null): mixed {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
};

return [
    'name' => (string) $env('CMS_APP_NAME', 'Digital College Operating System'),
    'base_url' => rtrim((string) $env('CMS_BASE_URL', '/college_management'), '/'),
    'timezone' => (string) $env('CMS_TIMEZONE', 'Asia/Kolkata'),
    'session_timeout' => max(300, (int) $env('CMS_SESSION_TIMEOUT', 3600)),
    'upload_max_bytes' => max(1024 * 1024, (int) $env('CMS_UPLOAD_MAX_BYTES', 20 * 1024 * 1024)),
    'upload_dir' => (string) $env('CMS_UPLOAD_DIR', dirname(__DIR__) . '/uploads/private'),
    'ai_provider' => (string) $env('CMS_AI_PROVIDER', 'local'),
    'ai_api_key' => (string) $env('CMS_AI_API_KEY', ''),
    'mail_from' => (string) $env('CMS_MAIL_FROM', 'no-reply@college.local'),
    'demo_mode' => filter_var($env('CMS_DEMO_MODE', 'false'), FILTER_VALIDATE_BOOLEAN),
    'debug' => filter_var($env('CMS_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
];
