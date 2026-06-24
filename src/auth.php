<?php
declare(strict_types=1);

require_once __DIR__ . '/const.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$currentRole = strtolower(trim((string) ($currentUser['role'] ?? '')));

$isAdmin  = $currentRole === 'admin';
$isPuerta = $currentRole === 'puerta';

$canSeeAdmin    = $isAdmin;
$canSeeScanner  = $isAdmin || $isPuerta;
$canSeeKioskito = $isAdmin;
$canManageDoor  = $isAdmin || $isPuerta;

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function divineUserPayload(array $currentUser, string $currentRole, bool $isAdmin, bool $isPuerta, bool $canManageDoor): string
{
    return (string) json_encode([
        'id' => (int) ($currentUser['id'] ?? 0),
        'username' => (string) ($currentUser['username'] ?? ''),
        'display_name' => (string) ($currentUser['display_name'] ?? ''),
        'role' => $currentRole,
        'is_admin' => $isAdmin,
        'is_puerta' => $isPuerta,
        'can_manage_door' => $canManageDoor,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
