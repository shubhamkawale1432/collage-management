<?php
declare(strict_types=1);

class AuditService
{
    public static function log(string $action, string $module, ?int $recordId = null, string $description = ''): void
    {
        try {
            $user = current_user();
            $statement = db()->prepare(
                'INSERT INTO activity_logs
                 (user_id, role_name, action, module, record_id, description, ip_address, user_agent)
                 VALUES (:user_id, :role_name, :action, :module, :record_id, :description, :ip, :user_agent)'
            );
            $statement->execute([
                'user_id' => $user['id'] ?? null,
                'role_name' => $user['role_name'] ?? null,
                'action' => mb_substr($action, 0, 80),
                'module' => mb_substr($module, 0, 80),
                'record_id' => $recordId,
                'description' => mb_substr($description, 0, 1000),
                'ip' => client_ip(),
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ]);
        } catch (Throwable $exception) {
            error_log('Audit logging failed: ' . $exception->getMessage());
        }
    }
}
