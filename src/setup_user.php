<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/conexion.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            display_name VARCHAR(120) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(30) NOT NULL DEFAULT 'usuario',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $users = [
        ['username' => 'camila',  'display_name' => 'Camila',  'password' => 'camila123',  'role' => 'admin'],
        ['username' => 'nicolas', 'display_name' => 'Nicolas', 'password' => 'nicolas123', 'role' => 'admin'],
        ['username' => 'lopez',   'display_name' => 'Lopez',   'password' => 'lopez123',   'role' => 'admin'],
        ['username' => 'publica', 'display_name' => 'Publica', 'password' => 'publica123', 'role' => 'usuario'],
    ];

    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");

    $stmtInsert = $pdo->prepare("
        INSERT INTO users (username, display_name, password_hash, role)
        VALUES (:username, :display_name, :password_hash, :role)
    ");

    $stmtUpdate = $pdo->prepare("
        UPDATE users
        SET display_name = :display_name,
            password_hash = :password_hash,
            role = :role
        WHERE username = :username
    ");

    foreach ($users as $user) {
        $hash = password_hash($user['password'], PASSWORD_DEFAULT);

        $stmtCheck->execute([
            ':username' => $user['username']
        ]);

        $exists = $stmtCheck->fetchColumn();

        if ($exists) {
            $stmtUpdate->execute([
                ':username' => $user['username'],
                ':display_name' => $user['display_name'],
                ':password_hash' => $hash,
                ':role' => $user['role'],
            ]);
        } else {
            $stmtInsert->execute([
                ':username' => $user['username'],
                ':display_name' => $user['display_name'],
                ':password_hash' => $hash,
                ':role' => $user['role'],
            ]);
        }
    }

    echo "<h1>Usuarios creados correctamente</h1>";
    echo "<ul>";
    echo "<li>camila / camila123</li>";
    echo "<li>nicolas / nicolas123</li>";
    echo "<li>lopez / lopez123</li>";
    echo "<li>publica / publica123</li>";
    echo "</ul>";
    echo "<p><a href='login.php'>Ir al login</a></p>";

} catch (Throwable $e) {
    http_response_code(500);

    echo "<h1>Error en setup_user.php</h1>";
    echo "<pre>";
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo "</pre>";
}