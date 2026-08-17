<?php

declare(strict_types=1);

require_once __DIR__ . '/const.php';
require_once __DIR__ . '/roles.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$currentRole = strtolower(trim((string) ($currentUser['role'] ?? '')));

$isAdmin = $currentRole === ROLE_ADMIN;
$isPuerta = $currentRole === ROLE_PUERTA;
$isKiosko = $currentRole === ROLE_KIOSKO;

$canSeeAdmin = canAccess($currentRole, 'admin');

$canSeeKioskito = canAccess($currentRole, 'kiosko');

$canManageDoor = canAccess($currentRole, 'door');

// Scanner QR: exclusivo de administrador y rol puerta.
$canUseScanner = $isAdmin || $isPuerta;

$canSeeStock = canAccess($currentRole, 'stock');

$canSeeGuardarropas = canAccess($currentRole, 'guardarropas');



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
        'can_use_scanner' => $canUseScanner,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
