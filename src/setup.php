<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/conexion.php';

try {
    /*
    =========================
    1) EJECUTAR init.sql
    =========================
    */

    $initPath = __DIR__ . '/../db/init.sql';

   if (!file_exists($initPath)) {
    throw new RuntimeException('No se encontró init.sql en: ' . $initPath);
    }

    $sql = file_get_contents($initPath);

    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('init.sql está vacío o no se pudo leer.');
    }

    $pdo->exec($sql);

    /*
    =========================
    2) CREAR USUARIOS
    =========================
    */

    $users = [
        [
            'username' => 'camila',
            'display_name' => 'Camila',
            'password' => 'camila123',
            'role' => 'admin'
        ],
        [
            'username' => 'nicolas',
            'display_name' => 'Nicolas',
            'password' => 'nicolas123',
            'role' => 'admin'
        ],
        [
            'username' => 'lopez',
            'display_name' => 'Lopez',
            'password' => 'lopez123',
            'role' => 'admin'
        ],
        [
            'username' => 'publica',
            'display_name' => 'Publica',
            'password' => 'publica123',
            'role' => 'usuario'
        ],
    ];

    $check = $pdo->prepare("
        SELECT id 
        FROM users 
        WHERE username = :username 
        LIMIT 1
    ");

    $insert = $pdo->prepare("
        INSERT INTO users 
        (username, display_name, password_hash, role)
        VALUES 
        (:username, :display_name, :password_hash, :role)
    ");

    $update = $pdo->prepare("
        UPDATE users
        SET display_name = :display_name,
            password_hash = :password_hash,
            role = :role
        WHERE username = :username
    ");

    foreach ($users as $user) {
        $passwordHash = password_hash($user['password'], PASSWORD_DEFAULT);

        $check->execute([
            ':username' => $user['username']
        ]);

        $exists = $check->fetchColumn();

        if ($exists) {
            $update->execute([
                ':username' => $user['username'],
                ':display_name' => $user['display_name'],
                ':password_hash' => $passwordHash,
                ':role' => $user['role'],
            ]);
        } else {
            $insert->execute([
                ':username' => $user['username'],
                ':display_name' => $user['display_name'],
                ':password_hash' => $passwordHash,
                ':role' => $user['role'],
            ]);
        }
    }

    echo "<h1>Base y usuarios creados correctamente</h1>";

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