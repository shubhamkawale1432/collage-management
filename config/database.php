<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('CMS_DB_HOST') ?: '127.0.0.1';
    $name = getenv('CMS_DB_NAME') ?: 'college_management';
    $user = getenv('CMS_DB_USER') ?: 'root';
    $pass = getenv('CMS_DB_PASS') ?: '';

    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $host)) {
        throw new RuntimeException('Invalid database host configuration.');
    }
    if (!preg_match('/^[A-Za-z0-9_$-]+$/', $name)) {
        throw new RuntimeException('Invalid database name configuration.');
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
