<?php
include __DIR__ . '/const.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/assets.php';

session_start();

function restore_remember_login(PDO $pdo): void
{
    if (isset($_SESSION['user']['id'])) {
        return;
    }

    if (empty($_COOKIE['divine_remember'])) {
        return;
    }

    $parts = explode(':', (string) $_COOKIE['divine_remember']);

    if (count($parts) !== 2) {
        setcookie('divine_remember', '', time() - 3600, '/');
        return;
    }

    [$selector, $token] = $parts;

    $stmt = $pdo->prepare("
        SELECT 
            rt.id AS token_id,
            rt.user_id,
            rt.token_hash,
            rt.expires_at,
            u.id,
            u.username,
            u.display_name,
            u.role
        FROM user_remember_tokens rt
        INNER JOIN users u ON u.id = rt.user_id
        WHERE rt.selector = :selector
          AND rt.expires_at > NOW()
        LIMIT 1
    ");

    $stmt->execute([
        ':selector' => $selector,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($token, $row['token_hash'])) {
        $stmtDelete = $pdo->prepare("
            DELETE FROM user_remember_tokens
            WHERE selector = :selector
        ");
        $stmtDelete->execute([':selector' => $selector]);

        setcookie('divine_remember', '', time() - 3600, '/');
        return;
    }

    $_SESSION['user'] = [
        'id' => (int) $row['id'],
        'username' => $row['username'],
        'display_name' => $row['display_name'],
        'role' => $row['role'],
    ];
}

function create_remember_token(PDO $pdo, array $user): void
{
    $selector = bin2hex(random_bytes(16));
    $token = bin2hex(random_bytes(32));
    $tokenHash = password_hash($token, PASSWORD_DEFAULT);

    $days = 30;
    $expiresAt = date('Y-m-d H:i:s', time() + (60 * 60 * 24 * $days));

    $stmt = $pdo->prepare("
        INSERT INTO user_remember_tokens 
            (user_id, selector, token_hash, expires_at)
        VALUES 
            (:user_id, :selector, :token_hash, :expires_at)
    ");

    $stmt->execute([
        ':user_id' => (int) $user['id'],
        ':selector' => $selector,
        ':token_hash' => $tokenHash,
        ':expires_at' => $expiresAt,
    ]);

    setcookie('divine_remember', $selector . ':' . $token, [
        'expires' => time() + (60 * 60 * 24 * $days),
        'path' => '/',
        'secure' => false, // En Railway/HTTPS ponelo en true
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

restore_remember_login($pdo);

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/app_logs.php';

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
        ];

        if (!empty($_POST['remember_me'])) {
            create_remember_token($pdo, $_SESSION['user']);
        }

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
                'remember_me' => !empty($_POST['remember_me']),
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
    <title>Login <?= APP_NAME ?> App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/login.css">
    <link rel="stylesheet" href="styles.css">

<link rel="stylesheet" href="styles/theme.css?v=<?= asset_version('styles/theme.css') ?>">
<script src="js/theme.js?v=<?= asset_version('js/theme.js') ?>" defer></script>
</head>
<body class="login-page">
<form class="login-box" method="POST">
    <h1><?= APP_NAME ?> APP</h1>
    <div class="sub">Iniciar sesión</div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <label>Usuario</label>
    <input type="text" name="username" required autocomplete="username" autofocus>

    <label>Contraseña</label>
    <input type="password" name="password" required autocomplete="current-password">

    <label class="remember-row">
        <input type="checkbox" name="remember_me" value="1">
        Mantener sesión iniciada
    </label>

    <button type="submit">Entrar</button>
</form>

  <footer class="theme-footer" aria-label="Preferencias visuales">
  <button type="button" class="theme-toggle" id="themeToggle" data-theme-toggle aria-label="Cambiar tema">
    <span class="theme-toggle__icon" aria-hidden="true">◐</span>
    <span class="theme-toggle__copy">
      <span class="theme-toggle__eyebrow">Tema visual</span>
      <span class="theme-toggle__label" data-theme-label>Cambiar tema</span>
    </span>
    <span class="theme-toggle__track" aria-hidden="true">
      <span class="theme-toggle__thumb"></span>
    </span>
  </button>
</footer>

</body>
</html>
