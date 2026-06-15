<?php
session_start();

require_once __DIR__ . '/config/conexion.php';

if (!empty($_COOKIE['divine_remember'])) {
    $parts = explode(':', (string) $_COOKIE['divine_remember']);

    if (count($parts) === 2) {
        $selector = $parts[0];

        $stmt = $pdo->prepare("
            DELETE FROM user_remember_tokens
            WHERE selector = :selector
        ");

        $stmt->execute([
            ':selector' => $selector,
        ]);
    }

    setcookie('divine_remember', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true, // En Railway/HTTPS ponelo en true
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => 'Lax',
    ]);
}

session_destroy();

header('Location: login.php');
exit;