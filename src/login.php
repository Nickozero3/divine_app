<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/conexion.php';
    require_once __DIR__ . '/config/app_logs.php';

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
        ];

        app_log(
            $pdo,
            (int) $user['id'],
            (string) $user['username'],
            'login',
            'auth',
            (int) $user['id'],
            'Inicio de sesión correcto',
            [
                'display_name' => $user['display_name'],
                'role' => $user['role'],
            ]
        );

        header('Location: index.php');
        exit;
    }

    $error = 'Usuario o contraseña incorrectos';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login Divine App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="login.css">
</head>
<body>
<form class="login-box" method="POST">
    <h1>DIVINE APP</h1>
    <div class="sub">Iniciar sesión</div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <label>Usuario</label>
    <input type="text" name="username" required autocomplete="username" autofocus>

    <label>Contraseña</label>
    <input type="password" name="password" required autocomplete="current-password">

    <button type="submit">Entrar</button>
</form>
</body>
</html>
