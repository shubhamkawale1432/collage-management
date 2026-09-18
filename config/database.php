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
    $port = (int) (getenv('CMS_DB_PORT') ?: 3306);

    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $host)) {
        throw new RuntimeException('Invalid database host configuration.');
    }
    if (!preg_match('/^[A-Za-z0-9_$-]+$/', $name)) {
        throw new RuntimeException('Invalid database name configuration.');
    }
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('Invalid database port configuration.');
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);

    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $pdo;
}
