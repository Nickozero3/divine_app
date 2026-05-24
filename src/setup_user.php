<?php
require_once __DIR__ . '/config/conexion.php';

$users = [
    ['username' => 'Camila',  'display_name' => 'Camila',  'password' => 'camila123',  'role' => 'admin'],
    ['username' => 'Nicolas', 'display_name' => 'Nicolas', 'password' => 'nicolas123', 'role' => 'admin'],
    ['username' => 'Lopez',   'display_name' => 'Lopez',   'password' => 'lopez123',   'role' => 'admin'],
    ['username' => 'Publica', 'display_name' => 'Publica', 'password' => 'publica123', 'role' => 'usuario'],
];

$sql = "
    INSERT INTO users (username, display_name, password_hash, role)
    VALUES (:username, :display_name, :password_hash, :role)
    ON DUPLICATE KEY UPDATE
        display_name = VALUES(display_name),
        password_hash = VALUES(password_hash),
        role = VALUES(role)
";

$stmt = $pdo->prepare($sql);

foreach ($users as $user) {
    $stmt->execute([
        ':username' => $user['username'],
        ':display_name' => $user['display_name'],
        ':password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
        ':role' => $user['role'],
    ]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios creados</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f8f4ea;margin:0;min-height:100vh;display:grid;place-items:center;color:#111}
        .box{background:#fff;border-radius:20px;padding:26px;box-shadow:0 18px 50px rgba(0,0,0,.12);max-width:520px;width:92vw}
        code{background:#f1f1f1;border-radius:8px;padding:2px 6px}
        li{margin:8px 0}
        a{color:#111;font-weight:bold}
    </style>
</head>
<body>
<div class="box">
    <h1>Usuarios creados correctamente</h1>
    <ul>
        <li>Camila / <code>camila123</code> — admin</li>
        <li>Nicolas / <code>nicolas123</code> — admin</li>
        <li>Lopez / <code>lopez123</code> — admin</li>
        <li>Publica / <code>publica123</code> — usuario</li>
    </ul>
    <p>Después de confirmar que funciona, borrá <code>setup_user.php</code> o no lo subas público.</p>
    <p><a href="login.php">Ir al login</a></p>
</div>
</body>
</html>
