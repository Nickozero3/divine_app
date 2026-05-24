<?php

declare(strict_types=1);

function env_value(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

$appEnv = (string) env_value('APP_ENV', 'production');
$dbHost = (string) env_value('DB_HOST', 'localhost');
$dbPort = (string) env_value('DB_PORT', '3306');
$dbName = (string) env_value('DB_NAME', 'divine_db');
$dbUser = (string) env_value('DB_USER', 'root');
$dbPass = (string) env_value('DB_PASSWORD', env_value('DB_PASS', ''));

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    if ($appEnv === 'development') {
        die('Error de conexión: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
    die('Error de conexión con la base de datos.');
}
