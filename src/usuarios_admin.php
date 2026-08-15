<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/assets.php';

if (file_exists(__DIR__ . '/const.php')) {
  require_once __DIR__ . '/const.php';
}

$appName = defined('APP_NAME') ? APP_NAME : 'Divine App';

if (!isset($_SESSION['user'])) {
  header('Location: login.php');
  exit;
}

$currentUser = $_SESSION['user'];

if (($currentUser['role'] ?? '') !== 'admin') {
  die('Acceso denegado');
}

$currentUserId = (int)($currentUser['id'] ?? 0);

function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage(string $type, string $message): void
{
  $_SESSION['flash_users'] = [
    'type' => $type,
    'message' => $message,
  ];

  header('Location: usuarios_admin.php');
  exit;
}

function normalizeUsername(string $username): string
{
  $username = trim($username);
  $username = preg_replace('/\s+/', '', $username) ?? '';
  $username = mb_strtolower($username, 'UTF-8');

  // Permite usuarios normales y correos electrónicos completos.
  if (str_contains($username, '@')) {
    return $username;
  }

  return preg_replace('/[^a-z0-9_.-]/i', '', $username) ?? '';
}

function isEmailUsername(string $username): bool
{
  return filter_var($username, FILTER_VALIDATE_EMAIL) !== false;
}

function usernameLabel(string $username): string
{
  return isEmailUsername($username) ? $username : '@' . $username;
}

$roles = [

  'admin' => [
    'label' => 'Administrador',
    'desc' => 'Acceso completo'
  ],

  'puerta' => [
    'label' => 'Puerta',
    'desc' => 'Ingreso y control de listas'
  ],

  'usuario' => [
    'label' => 'RRPP',
    'desc' => 'Gestiona únicamente sus listas'
  ],

  'kiosko' => [
    'label' => 'Kioskito',
    'desc' => 'Caja, ventas y guardarropas'
  ]

];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  try {
    if ($action === 'add_user') {
      $username = normalizeUsername((string)($_POST['username'] ?? ''));
      $displayName = trim((string)($_POST['display_name'] ?? ''));
      $password = (string)($_POST['password'] ?? '');
      $role = (string)($_POST['role'] ?? 'usuario');

      if ($username === '' || mb_strlen($username) < 3) {
        redirectWithMessage('error', 'El usuario debe tener al menos 3 caracteres.');
      }

      if (str_contains($username, '@') && !isEmailUsername($username)) {
        redirectWithMessage('error', 'Si usás un email como usuario, ingresalo completo. Ejemplo: nicolasochoa@gmail.com');
      }

      if ($displayName === '') {
        redirectWithMessage('error', 'El nombre visible es obligatorio.');
      }

      if (mb_strlen($password) < 4) {
        redirectWithMessage('error', 'La contraseña debe tener al menos 4 caracteres.');
      }

      if (!array_key_exists($role, $roles)) {
        $role = 'usuario';
      }

      $stmt = $pdo->prepare("
                INSERT INTO users 
                (username, display_name, password_hash, role)
                VALUES 
                (:username, :display_name, :password_hash, :role)
            ");

      $stmt->execute([
        ':username' => $username,
        ':display_name' => $displayName,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':role' => $role,
      ]);

      $newUserId = (int)$pdo->lastInsertId();

      // Toda persona nueva tiene su lista propia creada de forma
      // obligatoria, para que su link de registro público funcione
      // desde el primer momento (sin depender de un checkbox).
      $stmtList = $pdo->prepare("
                INSERT INTO door_lists 
                (user_id, name, is_birthday, price_per_person)
                VALUES 
                (:user_id, :name, 0, 500)
            ");

      $stmtList->execute([
        ':user_id' => $newUserId,
        ':name' => $displayName,
      ]);

      redirectWithMessage('success', 'Usuario creado correctamente.');
    }

    if ($action === 'delete_user') {
      $id = (int)($_POST['id'] ?? 0);

      if ($id <= 0) {
        redirectWithMessage('error', 'Usuario inválido.');
      }

      if ($id === $currentUserId) {
        redirectWithMessage('error', 'No podés eliminar tu propio usuario mientras estás conectado.');
      }

      $stmtTarget = $pdo->prepare("SELECT id, username, display_name, role FROM users WHERE id = :id LIMIT 1");
      $stmtTarget->execute([':id' => $id]);
      $targetUser = $stmtTarget->fetch(PDO::FETCH_ASSOC);

      if (!$targetUser) {
        redirectWithMessage('error', 'El usuario ya no existe.');
      }

      if (($targetUser['role'] ?? '') === 'admin') {
        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount <= 1) {
          redirectWithMessage('error', 'No podés eliminar al último administrador.');
        }
      }

      $pdo->beginTransaction();
      try {
        $stmtTokens = $pdo->prepare("DELETE FROM user_remember_tokens WHERE user_id = :id");
        $stmtTokens->execute([':id' => $id]);

        // Conservamos los registros de auditoría, pero sin apuntar al usuario eliminado.
        $stmtLogs = $pdo->prepare("UPDATE app_logs SET user_id = NULL WHERE user_id = :id");
        $stmtLogs->execute([':id' => $id]);

        // door_lists y kiosko_sales usan ON DELETE CASCADE en el esquema actual.
        $stmtDelete = $pdo->prepare("DELETE FROM users WHERE id = :id LIMIT 1");
        $stmtDelete->execute([':id' => $id]);

        if ($stmtDelete->rowCount() !== 1) {
          throw new RuntimeException('No se pudo eliminar el usuario.');
        }
        $pdo->commit();
      } catch (Throwable $deleteError) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        throw $deleteError;
      }

      redirectWithMessage('success', 'Se eliminó "' . (string)$targetUser['display_name'] . '" correctamente.');
    }

    if ($action === 'update_user') {
      $id = (int)($_POST['id'] ?? 0);
      $username = normalizeUsername((string)($_POST['username'] ?? ''));
      $displayName = trim((string)($_POST['display_name'] ?? ''));
      $role = (string)($_POST['role'] ?? 'usuario');
      $newPassword = (string)($_POST['new_password'] ?? '');

      if ($id <= 0) {
        redirectWithMessage('error', 'Usuario inválido.');
      }

      if ($username === '' || mb_strlen($username) < 3) {
        redirectWithMessage('error', 'El usuario debe tener al menos 3 caracteres.');
      }

      if (str_contains($username, '@') && !isEmailUsername($username)) {
        redirectWithMessage('error', 'Si usás un email como usuario, ingresalo completo. Ejemplo: nicolasochoa@gmail.com');
      }

      if ($displayName === '') {
        redirectWithMessage('error', 'El nombre visible no puede estar vacío.');
      }

      if (!array_key_exists($role, $roles)) {
        $role = 'usuario';
      }

      if ($id === $currentUserId && $role !== 'admin') {
        redirectWithMessage('error', 'No podés quitarte el rol admin a vos mismo.');
      }

      $sets = [
        'username = :username',
        'display_name = :display_name',
        'role = :role',
      ];

      $params = [
        ':id' => $id,
        ':username' => $username,
        ':display_name' => $displayName,
        ':role' => $role,
      ];

      if ($newPassword !== '') {
        if (mb_strlen($newPassword) < 4) {
          redirectWithMessage('error', 'La nueva contraseña debe tener al menos 4 caracteres.');
        }

        $sets[] = 'password_hash = :password_hash';
        $params[':password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
      }

      $stmt = $pdo->prepare("
                UPDATE users
                SET " . implode(', ', $sets) . "
                WHERE id = :id
                LIMIT 1
            ");

      $stmt->execute($params);

      if ($id === $currentUserId) {
        $_SESSION['user']['username'] = $username;
        $_SESSION['user']['display_name'] = $displayName;
        $_SESSION['user']['role'] = $role;
      }

      redirectWithMessage('success', 'Usuario actualizado correctamente.');
    }

    redirectWithMessage('error', 'Acción no válida.');
  } catch (PDOException $e) {
    if (($e->errorInfo[1] ?? null) === 1062) {
      redirectWithMessage('error', 'Ese nombre de usuario ya existe.');
    }

    redirectWithMessage('error', 'Error de base de datos: ' . $e->getMessage());
  } catch (Throwable $e) {
    redirectWithMessage('error', 'Error: ' . $e->getMessage());
  }
}

$stmt = $pdo->query("
    SELECT
      u.id, u.username, u.display_name, u.role, u.created_at,
      (
        SELECT dl.id
        FROM door_lists dl
        WHERE dl.user_id = u.id AND dl.is_birthday = 0
        ORDER BY dl.id ASC
        LIMIT 1
      ) AS list_id
    FROM users u
    ORDER BY
      CASE u.role
        WHEN 'admin' THEN 1
        WHEN 'puerta' THEN 2
        WHEN 'usuario' THEN 3
        ELSE 9
      END,
      u.display_name ASC
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Toda persona tiene que tener su lista propia creada de forma
 * obligatoria (para que el link de registro público siempre
 * funcione). Si algún usuario todavía no tiene su lista normal
 * (is_birthday = 0), se la crea acá mismo, una sola vez, con el
 * mismo INSERT que ya usa el alta de usuarios.
 */
$stmtCreateList = $pdo->prepare("
    INSERT INTO door_lists 
    (user_id, name, is_birthday, price_per_person)
    VALUES 
    (:user_id, :name, 0, 500)
");

foreach ($users as &$userRow) {
  if (empty($userRow['list_id'])) {
    $stmtCreateList->execute([
      ':user_id' => (int) $userRow['id'],
      ':name' => (string) $userRow['display_name'],
    ]);

    $userRow['list_id'] = (int) $pdo->lastInsertId();
  }
}
unset($userRow);

// URL base para el link de registro público (registro_publico.php ?lista=ID).
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
$registroBaseUrl = $scheme . '://' . $host . $scriptDir . '/registro_publico.php';

$flash = $_SESSION['flash_users'] ?? null;
unset($_SESSION['flash_users']);

$totalUsers = count($users);
$roleCounts = [
  'all' => $totalUsers,
  'admin' => 0,
  'puerta' => 0,
  'usuario' => 0,
  'kiosko' => 0,
];

foreach ($users as $user) {
  $r = (string)($user['role'] ?? '');
  if (isset($roleCounts[$r])) {
    $roleCounts[$r]++;
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Usuarios y roles · <?= e($appName) ?></title>
  <link rel="stylesheet" href="styles.css">

  <style>
    :root {
      --bg: #07040b;
      --panel: rgba(19, 11, 28, .92);
      --panel-strong: rgba(28, 17, 41, .96);
      --input: rgba(7, 4, 11, .82);

      --gold: #f4d98e;
      --gold-2: #ffe7a8;
      --purple: #8f4cff;

      --text: #fff8ea;
      --muted: #c9bdd7;
      --muted-2: #9286a2;

      --border: rgba(244, 217, 142, .18);
      --border-strong: rgba(244, 217, 142, .34);

      --success: #4ade80;
      --danger: #fb7185;

      --radius-lg: 24px;
      --radius-md: 16px;
      --shadow: 0 18px 45px rgba(0, 0, 0, .35);
    }

    * {
      box-sizing: border-box;
      -webkit-tap-highlight-color: transparent;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      margin: 0;
      min-height: 100dvh;
      overflow-x: hidden;
      color: var(--text);
      font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
      background:
        radial-gradient(circle at 12% 0%, rgba(143, 76, 255, .26), transparent 30%),
        radial-gradient(circle at 95% 15%, rgba(244, 217, 142, .12), transparent 28%),
        linear-gradient(180deg, #120a1a 0%, #07040b 100%);
    }

    button,
    input,
    select {
      font: inherit;
    }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 50;
      min-height: 64px;
      padding: 12px clamp(12px, 3vw, 22px);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      background: color-mix(in srgb, var(--bg) 78%, transparent);
      backdrop-filter: blur(18px) saturate(135%);
      -webkit-backdrop-filter: blur(18px) saturate(135%);
      border-bottom: 1px solid rgba(255, 255, 255, .07);
    }

    .topbar-title {
      font-family: "Cinzel", serif;
      font-size: 18px;
      font-weight: 700;
      letter-spacing: .04em;
      cursor: pointer;
      background: linear-gradient(110deg, var(--gold-2), var(--purple-2));
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .topbar-back {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 40px;
      border: 1px solid rgba(255, 255, 255, .08);
      background: rgba(255, 255, 255, .035);
      color: var(--text2);
      border-radius: 999px;
      padding: 0 16px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      transition: transform 160ms ease, border-color 160ms ease, background-color 160ms ease;
    }

    .topbar-back:active {
      transform: scale(.96);
    }

    .wrap {
      width: min(1160px, 100%);
      margin: 0 auto;
      padding: clamp(14px, 3vw, 26px);
    }

    .page-title {
      margin: 4px 0 6px;
      color: var(--gold);
      font-size: clamp(26px, 4vw, 38px);
      font-weight: 950;
      letter-spacing: -.8px;
    }

    .page-subtitle {
      margin: 0 0 18px;
      color: var(--muted);
      font-weight: 750;
      line-height: 1.45;
    }

    .card {
      background: linear-gradient(180deg, var(--panel-strong), var(--panel));
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: clamp(14px, 2.6vw, 22px);
      margin-bottom: 16px;
      box-shadow: var(--shadow);
    }

    .card-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 14px;
      margin-bottom: 16px;
    }

    .card-title {
      color: var(--gold);
      font-size: 21px;
      font-weight: 950;
    }

    .card-note {
      color: var(--muted-2);
      font-size: 13px;
      font-weight: 800;
      line-height: 1.35;
      margin-top: 4px;
    }

    .flash {
      border-radius: 18px;
      padding: 13px 15px;
      font-weight: 900;
      margin-bottom: 16px;
    }

    .flash.success {
      background: rgba(74, 222, 128, .13);
      border: 1px solid rgba(74, 222, 128, .38);
      color: var(--success);
    }

    .flash.error {
      background: rgba(251, 113, 133, .13);
      border: 1px solid rgba(251, 113, 133, .38);
      color: var(--danger);
    }

    .stats {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 10px;
      margin-bottom: 16px;
    }

    .stat {
      background: rgba(255, 255, 255, .045);
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 13px;
    }

    .stat-number {
      color: var(--gold);
      font-size: 24px;
      font-weight: 950;
    }

    .stat-label {
      color: var(--muted);
      font-size: 12px;
      font-weight: 850;
      margin-top: 2px;
    }

    .form-grid,
    .user-edit-grid {
      display: grid;
      grid-template-columns: 1fr 1.25fr 1fr 1fr auto;
      gap: 11px;
      align-items: end;
    }

    .field {
      display: flex;
      flex-direction: column;
      gap: 7px;
    }

    .field label {
      color: var(--muted);
      font-size: 12px;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: .4px;
    }

    .field input,
    .field select {
      width: 100%;
      min-height: 46px;
      border: 1px solid var(--border);
      background: var(--input);
      color: var(--text);
      border-radius: 15px;
      padding: 12px 13px;
      outline: none;
      font-size: 15px;
      font-weight: 800;
    }

    .field input::placeholder {
      color: rgba(201, 189, 215, .48);
    }

    .field input:focus,
    .field select:focus {
      border-color: var(--gold);
      background: rgba(12, 7, 18, .96);
      box-shadow: 0 0 0 4px rgba(244, 217, 142, .1);
    }

    .check-row {
      width: fit-content;
      margin-top: 14px;
      display: flex;
      align-items: center;
      gap: 9px;
      color: var(--muted);
      font-size: 14px;
      font-weight: 800;
      cursor: pointer;
    }

    .check-row input {
      width: 20px;
      height: 20px;
      accent-color: var(--gold);
    }

    .btn {
      min-height: 46px;
      border: 0;
      border-radius: 15px;
      padding: 12px 18px;
      font-weight: 950;
      cursor: pointer;
      color: #150c1f;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, var(--gold-2), var(--gold));
      box-shadow: 0 10px 24px rgba(244, 217, 142, .18);
    }

    .btn.secondary {
      color: var(--text);
      border: 1px solid var(--border);
      background: rgba(255, 255, 255, .06);
      box-shadow: none;
    }

    .directory-tools {
      display: grid;
      grid-template-columns: minmax(240px, 1fr) auto;
      gap: 12px;
      align-items: end;
      margin-bottom: 14px;
    }

    .search-box {
      position: relative;
    }

    .search-box input {
      padding-left: 42px;
    }

    .search-icon {
      position: absolute;
      left: 14px;
      bottom: 12px;
      color: var(--gold);
      font-weight: 950;
      pointer-events: none;
    }

    .role-filters {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .filter-chip {
      border: 1px solid var(--border);
      background: rgba(255, 255, 255, .055);
      color: var(--muted);
      border-radius: 999px;
      min-height: 43px;
      padding: 10px 13px;
      font-size: 13px;
      font-weight: 950;
      cursor: pointer;
    }

    .filter-chip.is-active {
      color: #150c1f;
      background: linear-gradient(135deg, var(--gold-2), var(--gold));
      border-color: transparent;
    }

    .directory-meta {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      color: var(--muted-2);
      font-size: 13px;
      font-weight: 850;
      margin-bottom: 10px;
    }

    .user-table {
      border: 1px solid var(--border);
      border-radius: 20px;
      overflow: hidden;
      background: rgba(7, 4, 11, .52);
    }

    .user-table-head,
    .user-row {
      display: grid;
      grid-template-columns: minmax(190px, 1.25fr) minmax(130px, .9fr) 110px minmax(135px, .8fr) 120px;
      gap: 10px;
      align-items: center;
    }

    .user-table-head {
      padding: 12px 14px;
      color: var(--muted);
      background: rgba(255, 255, 255, .045);
      border-bottom: 1px solid var(--border);
      font-size: 12px;
      font-weight: 950;
      text-transform: uppercase;
      letter-spacing: .45px;
    }

    .user-row {
      padding: 13px 14px;
      border-bottom: 1px solid rgba(244, 217, 142, .1);
    }

    .user-row:last-child {
      border-bottom: 0;
    }

    .user-row.is-hidden {
      display: none;
    }

    .user-main {
      min-width: 0;
    }

    .user-name {
      font-size: 16px;
      font-weight: 950;
      color: var(--text);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .user-sub {
      font-size: 12px;
      color: var(--muted-2);
      margin-top: 4px;
      line-height: 1.35;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .user-username,
    .user-date {
      color: var(--muted);
      font-size: 13px;
      font-weight: 850;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .badge {
      width: fit-content;
      border-radius: 999px;
      padding: 7px 11px;
      background: rgba(244, 217, 142, .12);
      border: 1px solid rgba(244, 217, 142, .24);
      color: var(--gold);
      font-size: 12px;
      font-weight: 950;
      white-space: nowrap;
    }

    .action-cell {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .edit-btn {
      min-height: 39px;
      border: 1px solid var(--border-strong);
      border-radius: 13px;
      background: rgba(255, 255, 255, .06);
      color: var(--text);
      font-size: 13px;
      font-weight: 950;
      cursor: pointer;
    }

    .edit-btn:hover {
      border-color: var(--gold);
    }

    .link-btn {
      min-height: 33px;
      border: 1px solid var(--border);
      border-radius: 13px;
      background: rgba(143, 76, 255, .12);
      color: var(--gold-2);
      font-size: 11.5px;
      font-weight: 850;
      cursor: pointer;
      white-space: nowrap;
      padding: 4px 8px;
    }

    .link-btn:hover {
      border-color: var(--gold);
    }

    .link-btn.copied {
      background: rgba(74, 222, 128, .14);
      border-color: var(--success);
      color: var(--success);
    }

    .no-list-tag {
      font-size: 11px;
      color: var(--muted-2);
      text-align: center;
    }

    .delete-user-btn {
      min-height: 33px;
      border: 1px solid rgba(251, 113, 133, .24);
      border-radius: 13px;
      background: rgba(251, 113, 133, .08);
      color: #ff9aac;
      font-size: 11.5px;
      font-weight: 900;
      cursor: pointer;
      padding: 5px 8px;
      transition: transform .14s ease, background .14s ease, border-color .14s ease;
    }
    .delete-user-btn:hover { background: rgba(251, 113, 133, .15); border-color: rgba(251, 113, 133, .48); }
    .delete-user-btn:active { transform: scale(.97); }
    .delete-user-btn--large { min-height: 42px; padding: 9px 13px; font-size: 12px; }
    .danger-zone { margin-top: 14px; padding: 13px; display:flex; align-items:center; justify-content:space-between; gap:12px; border:1px solid rgba(251,113,133,.18); border-radius:16px; background:linear-gradient(135deg,rgba(251,113,133,.07),rgba(255,255,255,.02)); }
    .danger-zone strong { display:block; color:#ff9aac; font-size:12px; text-transform:uppercase; letter-spacing:.08em; }
    .danger-zone span { display:block; margin-top:4px; color:var(--muted-2); font-size:11px; line-height:1.35; }
    body.modal-open { overflow:hidden; }
    .delete-modal { position:fixed; inset:0; z-index:9999; display:none; place-items:center; padding:18px; }
    .delete-modal.is-open { display:grid; }
    .delete-modal__backdrop { position:absolute; inset:0; background:rgba(0,0,0,.72); backdrop-filter:blur(8px); }
    .delete-modal__card { position:relative; width:min(460px,100%); padding:22px; border:1px solid rgba(251,113,133,.22); border-radius:24px; background:radial-gradient(circle at 100% 0%,rgba(251,113,133,.12),transparent 42%),linear-gradient(160deg,rgba(30,16,38,.98),rgba(10,6,14,.99)); box-shadow:0 30px 90px rgba(0,0,0,.58); animation:deleteModalIn .16s ease-out; }
    .delete-modal__icon { width:48px; height:48px; display:grid; place-items:center; border-radius:15px; background:rgba(251,113,133,.10); border:1px solid rgba(251,113,133,.18); font-size:22px; margin-bottom:15px; }
    .delete-modal__eyebrow { color:#ff9aac; font-size:9px; font-weight:950; letter-spacing:.13em; }
    .delete-modal__copy h2 { margin:5px 0 8px; font-size:22px; color:var(--text); }
    .delete-modal__copy p { margin:0; color:var(--muted); font-size:13px; line-height:1.5; }
    .delete-modal__copy strong { color:var(--text); }
    .delete-modal__copy span { color:var(--gold-2); }
    .delete-modal__actions { display:grid; grid-template-columns:1fr 1fr; gap:9px; margin-top:20px; }
    .delete-confirm-btn { min-height:46px; border:0; border-radius:14px; background:linear-gradient(135deg,#b91c3b,#ef476f); color:#fff; font-weight:950; cursor:pointer; box-shadow:0 10px 24px rgba(239,71,111,.18); }
    @keyframes deleteModalIn { from { opacity:0; transform:translateY(7px) scale(.985); } to { opacity:1; transform:translateY(0) scale(1); } }

    .empty {
      color: var(--muted);
      border: 1px dashed var(--border);
      border-radius: 18px;
      padding: 18px;
      text-align: center;
      font-weight: 800;
    }

    .user-editor {
      display: none;
      margin-top: 16px;
      background: rgba(7, 4, 11, .72);
      border: 1px solid rgba(244, 217, 142, .18);
      border-radius: 20px;
      padding: 15px;
    }

    .user-editor.is-open {
      display: block;
    }

    .editor-head {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 14px;
    }

    .editor-actions {
      display: flex;
      gap: 8px;
      align-items: flex-start;
    }

    .selected-user-empty {
      color: var(--muted);
      border: 1px dashed var(--border);
      border-radius: 18px;
      padding: 18px;
      text-align: center;
      font-weight: 800;
    }

    @media (max-width: 980px) {
      .stats {
        grid-template-columns: repeat(3, 1fr);
      }

      .form-grid,
      .user-edit-grid {
        grid-template-columns: 1fr 1fr;
      }

      .btn {
        width: 100%;
      }

      .directory-tools {
        grid-template-columns: 1fr;
      }

      .user-table-head {
        display: none;
      }

      .user-table {
        border: 0;
        background: transparent;
        display: grid;
        gap: 10px;
      }

      .user-row {
        grid-template-columns: 1fr auto;
        gap: 10px;
        border: 1px solid rgba(244, 217, 142, .13);
        border-radius: 18px;
        background: rgba(7, 4, 11, .72);
        padding: 13px;
      }

      .user-username,
      .user-date {
        grid-column: 1 / -1;
      }

      .user-row .badge {
        grid-column: 1 / 2;
      }

      .edit-btn {
        grid-row: 1 / span 2;
        grid-column: 2 / 3;
        align-self: center;
        min-width: 78px;
      }
    }

    @media (max-width: 560px) {
      .topbar {
        min-height: 58px;
      }

      .topbar-title {
        font-size: 16px;
      }

      .topbar-back {
        padding: 9px 12px;
        font-size: 13px;
      }

      .wrap {
        padding: 12px;
      }

      .card {
        border-radius: 20px;
      }

      .card-head,
      .editor-head {
        flex-direction: column;
      }

      .stats,
      .form-grid,
      .user-edit-grid {
        grid-template-columns: 1fr;
      }

      .check-row {
        width: 100%;
        align-items: flex-start;
      }

      .directory-meta {
        flex-direction: column;
      }

      .role-filters {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
      }

      .filter-chip {
        width: 100%;
      }

      .user-row {
        grid-template-columns: 1fr;
      }

      .edit-btn {
        grid-row: auto;
        grid-column: auto;
        width: 100%;
      }

      .editor-actions {
        width: 100%;
      }

      .editor-actions .btn {
        width: 100%;
      }

      .danger-zone { align-items: stretch; flex-direction: column; }
      .delete-user-btn--large { width: 100%; }
      .delete-modal__actions { grid-template-columns: 1fr; }
    }
  </style>

  <link rel="stylesheet" href="styles/theme.css?v=<?= asset_version('styles/theme.css') ?>">
  <script src="js/theme.js?v=<?= asset_version('js/theme.js') ?>" defer></script>
</head>

<body>

  <div class="topbar">
    <div class="topbar-title" onclick="location.href='admin.php'">
      Usuarios y roles
    </div>

    <button class="topbar-back" onclick="location.href='admin.php'">
      ← Volver
    </button>
  </div>

  <div class="wrap">

    <h1 class="page-title">Gestión de usuarios</h1>
    <p class="page-subtitle">Administrá accesos, roles y cuentas desde un solo lugar.</p>

    <?php if ($flash): ?>
      <div class="flash <?= e((string)$flash['type']) ?>">
        <?= e((string)$flash['message']) ?>
      </div>
    <?php endif; ?>

    <div class="stats">
      <div class="stat">
        <div class="stat-number"><?= (int)$roleCounts['all'] ?></div>
        <div class="stat-label">Total usuarios</div>
      </div>

      <div class="stat">
        <div class="stat-number"><?= (int)$roleCounts['admin'] ?></div>
        <div class="stat-label">Admins</div>
      </div>

      <div class="stat">
        <div class="stat-number"><?= (int)$roleCounts['puerta'] ?></div>
        <div class="stat-label">Puerta</div>
      </div>

      <div class="stat">
        <div class="stat-number"><?= (int)$roleCounts['usuario'] ?></div>
        <div class="stat-label">Usuarios</div>
      </div>

      <div class="stat">
        <div class="stat-number"><?= (int)$roleCounts['kiosko'] ?></div>
        <div class="stat-label">Kioskito</div>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div>
          <div class="card-title">Crear usuario</div>
          <div class="card-note">Ideal para cargar públicas, puerta o nuevos admins.</div>
        </div>
      </div>

      <form method="POST">
        <input type="hidden" name="action" value="add_user">

        <div class="form-grid">
          <div class="field">
            <label>Usuario</label>
            <input type="text" name="username" placeholder="ej: darwin o nicolasochoa@gmail.com" required autocomplete="off">
          </div>

          <div class="field">
            <label>Nombre visible</label>
            <input type="text" name="display_name" placeholder="Ej: Darwin" required>
          </div>

          <div class="field">
            <label>Contraseña</label>
            <input type="password" name="password" placeholder="Mínimo 4 caracteres" required autocomplete="new-password">
          </div>

          <div class="field">
            <label>Rol</label>
            <select name="role" required>
              <?php foreach ($roles as $roleKey => $roleData): ?>
                <option value="<?= e($roleKey) ?>"><?= e($roleData['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <button class="btn" type="submit">Crear</button>
        </div>

        <p class="check-row" style="opacity:.75;">
          ℹ️ Se crea automáticamente su lista propia (obligatorio, para que su link de registro funcione).
        </p>
      </form>
    </div>

    <div class="card">
      <div class="card-head">
        <div>
          <div class="card-title">Directorio de usuarios</div>
          <div class="card-note">Buscá, filtrá por rol y editá sin recorrer una lista gigante.</div>
        </div>
      </div>

      <?php if (empty($users)): ?>
        <div class="empty">No hay usuarios cargados.</div>
      <?php else: ?>

        <div class="directory-tools">
          <div class="field search-box">
            <label>Buscar usuario</label>
            <span class="search-icon">⌕</span>
            <input type="search" id="userSearch" placeholder="Nombre, usuario o rol..." autocomplete="off">
          </div>

          <div class="role-filters" aria-label="Filtrar por rol">
            <button type="button" class="filter-chip is-active" data-role="all">Todos</button>
            <button type="button" class="filter-chip" data-role="admin">Admin</button>
            <button type="button" class="filter-chip" data-role="puerta">Puerta</button>
            <button type="button" class="filter-chip" data-role="usuario">Usuario</button>
            <button type="button" class="filter-chip" data-role="kiosko">Kioskito</button>
          </div>
        </div>

        <div class="directory-meta">
          <span id="resultCount"><?= (int)$totalUsers ?> usuarios encontrados</span>
          <span>Tocá “Editar” para abrir el formulario.</span>
        </div>

        <div class="user-table" id="userTable">
          <div class="user-table-head">
            <div>Nombre</div>
            <div>Usuario</div>
            <div>Rol</div>
            <div>Creado</div>
            <div>Acción</div>
          </div>

          <?php foreach ($users as $user): ?>
            <?php
            $roleKey = (string)$user['role'];
            $roleLabel = $roles[$roleKey]['label'] ?? $roleKey;

            $searchText = mb_strtolower(
              (string)$user['display_name'] . ' ' .
                (string)$user['username'] . ' ' .
                $roleLabel,
              'UTF-8'
            );
            ?>

            <div
              class="user-row"
              data-user-row
              data-role="<?= e($roleKey) ?>"
              data-search="<?= e($searchText) ?>">
              <div class="user-main">
                <div class="user-name"><?= e((string)$user['display_name']) ?></div>
                <div class="user-sub">ID #<?= (int)$user['id'] ?></div>
              </div>

              <div class="user-username"><?= e(usernameLabel((string)$user['username'])) ?></div>

              <div>
                <span class="badge"><?= e($roleLabel) ?></span>
              </div>

              <div class="user-date"><?= e((string)$user['created_at']) ?></div>

              <div class="action-cell">
                <button
                  type="button"
                  class="edit-btn"
                  data-edit-user="user-editor-<?= (int)$user['id'] ?>">
                  Editar
                </button>

                <?php if (!empty($user['list_id'])): ?>
                  <button
                    type="button"
                    class="link-btn"
                    data-copy-link="<?= e($registroBaseUrl . '?lista=' . (int)$user['list_id']) ?>"
                    title="Copiar link de registro público de <?= e((string)$user['display_name']) ?>">
                    🔗 Copiar link
                  </button>
                <?php else: ?>
                  <div class="no-list-tag">Sin lista propia</div>
                <?php endif; ?>

                <?php if ((int)$user['id'] !== $currentUserId): ?>
                  <button
                    type="button"
                    class="delete-user-btn"
                    data-delete-user="<?= (int)$user['id'] ?>"
                    data-delete-name="<?= e((string)$user['display_name']) ?>"
                    data-delete-username="<?= e((string)$user['username']) ?>">
                    🗑 Eliminar
                  </button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="empty" id="noResults" style="display:none;">
          No se encontraron usuarios con esa búsqueda.
        </div>

        <div class="selected-user-empty" id="selectedUserEmpty">
          Seleccioná un usuario para editar sus datos.
        </div>

        <?php foreach ($users as $user): ?>
          <?php
          $roleKey = (string)$user['role'];
          $roleLabel = $roles[$roleKey]['label'] ?? $roleKey;
          ?>

          <div class="user-editor" id="user-editor-<?= (int)$user['id'] ?>">
            <div class="editor-head">
              <div>
                <div class="user-name"><?= e((string)$user['display_name']) ?></div>
                <div class="user-sub">
                  Usuario: <?= e(usernameLabel((string)$user['username'])) ?> · Creado: <?= e((string)$user['created_at']) ?>
                </div>
              </div>

              <div class="editor-actions">
                <span class="badge"><?= e($roleLabel) ?></span>
                <button type="button" class="btn secondary" data-close-editor>Cerrar</button>
              </div>
            </div>

            <form method="POST" class="user-edit-grid">
              <input type="hidden" name="action" value="update_user">
              <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

              <div class="field">
                <label>Usuario</label>
                <input
                  type="text"
                  name="username"
                  value="<?= e((string)$user['username']) ?>"
                  required
                  autocomplete="off">
              </div>

              <div class="field">
                <label>Nombre visible</label>
                <input
                  type="text"
                  name="display_name"
                  value="<?= e((string)$user['display_name']) ?>"
                  required>
              </div>

              <div class="field">
                <label>Rol</label>
                <select name="role" required>
                  <?php foreach ($roles as $optionKey => $optionData): ?>
                    <option value="<?= e($optionKey) ?>" <?= $roleKey === $optionKey ? 'selected' : '' ?>>
                      <?= e($optionData['label']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field">
                <label>Nueva contraseña</label>
                <input
                  type="password"
                  name="new_password"
                  placeholder="Dejar vacío para no cambiar"
                  autocomplete="new-password">
              </div>

              <button class="btn" type="submit">Guardar cambios</button>
            </form>

            <?php if ((int)$user['id'] !== $currentUserId): ?>
              <div class="danger-zone">
                <div>
                  <strong>Zona peligrosa</strong>
                  <span>Eliminar esta cuenta también puede eliminar sus listas y ventas asociadas.</span>
                </div>
                <button
                  type="button"
                  class="delete-user-btn delete-user-btn--large"
                  data-delete-user="<?= (int)$user['id'] ?>"
                  data-delete-name="<?= e((string)$user['display_name']) ?>"
                  data-delete-username="<?= e((string)$user['username']) ?>">
                  🗑 Eliminar usuario
                </button>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

      <?php endif; ?>
    </div>

  </div>

  <div class="delete-modal" id="deleteUserModal" aria-hidden="true">
    <div class="delete-modal__backdrop" data-close-delete-user></div>
    <section class="delete-modal__card" role="dialog" aria-modal="true" aria-labelledby="deleteUserTitle">
      <div class="delete-modal__icon">🗑️</div>
      <div class="delete-modal__copy">
        <span class="delete-modal__eyebrow">ELIMINAR USUARIO</span>
        <h2 id="deleteUserTitle">¿Eliminar esta cuenta?</h2>
        <p id="deleteUserDescription"></p>
      </div>
      <form method="POST" class="delete-modal__actions">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="id" id="deleteUserId" value="">
        <button type="button" class="btn secondary" data-close-delete-user>Cancelar</button>
        <button type="submit" class="delete-confirm-btn">Sí, eliminar</button>
      </form>
    </section>
  </div>

  <script>
    const userSearch = document.getElementById('userSearch');
    const filterChips = document.querySelectorAll('.filter-chip');
    const userRows = document.querySelectorAll('[data-user-row]');
    const resultCount = document.getElementById('resultCount');
    const noResults = document.getElementById('noResults');
    const selectedUserEmpty = document.getElementById('selectedUserEmpty');
    const editButtons = document.querySelectorAll('[data-edit-user]');
    const closeButtons = document.querySelectorAll('[data-close-editor]');
    const userEditors = document.querySelectorAll('.user-editor');

    let activeRole = 'all';

    function normalizeText(value) {
      return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
    }

    function closeEditors() {
      userEditors.forEach(editor => editor.classList.remove('is-open'));

      if (selectedUserEmpty) {
        selectedUserEmpty.style.display = 'block';
      }
    }

    function applyFilters() {
      const query = normalizeText(userSearch ? userSearch.value.trim() : '');
      let visible = 0;

      userRows.forEach(row => {
        const rowRole = row.dataset.role || '';
        const rowSearch = normalizeText(row.dataset.search || '');

        const matchesRole = activeRole === 'all' || rowRole === activeRole;
        const matchesSearch = query === '' || rowSearch.includes(query);

        const shouldShow = matchesRole && matchesSearch;

        row.classList.toggle('is-hidden', !shouldShow);

        if (shouldShow) {
          visible++;
        }
      });

      if (resultCount) {
        resultCount.textContent = visible === 1 ?
          '1 usuario encontrado' :
          visible + ' usuarios encontrados';
      }

      if (noResults) {
        noResults.style.display = visible === 0 ? 'block' : 'none';
      }

      closeEditors();
    }

    filterChips.forEach(chip => {
      chip.addEventListener('click', () => {
        filterChips.forEach(item => item.classList.remove('is-active'));
        chip.classList.add('is-active');

        activeRole = chip.dataset.role || 'all';
        applyFilters();
      });
    });

    if (userSearch) {
      userSearch.addEventListener('input', applyFilters);
    }

    editButtons.forEach(button => {
      button.addEventListener('click', () => {
        closeEditors();

        const editorId = button.dataset.editUser;
        const editor = document.getElementById(editorId);

        if (!editor) return;

        editor.classList.add('is-open');

        if (selectedUserEmpty) {
          selectedUserEmpty.style.display = 'none';
        }

        editor.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      });
    });

    closeButtons.forEach(button => {
      button.addEventListener('click', closeEditors);
    });

    applyFilters();

    /* =========================
       ELIMINAR USUARIO
    ========================= */
    const deleteUserModal = document.getElementById('deleteUserModal');
    const deleteUserId = document.getElementById('deleteUserId');
    const deleteUserDescription = document.getElementById('deleteUserDescription');
    const deleteUserButtons = document.querySelectorAll('[data-delete-user]');
    const closeDeleteUserButtons = document.querySelectorAll('[data-close-delete-user]');

    function escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function closeDeleteUserModal() {
      if (!deleteUserModal) return;
      deleteUserModal.classList.remove('is-open');
      deleteUserModal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('modal-open');
    }

    function openDeleteUserModal(button) {
      if (!deleteUserModal || !deleteUserId || !deleteUserDescription) return;

      const id = button.dataset.deleteUser || '';
      const name = button.dataset.deleteName || 'este usuario';
      const username = button.dataset.deleteUsername || '';

      deleteUserId.value = id;
      deleteUserDescription.innerHTML =
        `Vas a eliminar <strong>${escapeHtml(name)}</strong>` +
        (username ? ` (<span>${escapeHtml(username)}</span>)` : '') +
        `. Esta acción es permanente y puede eliminar sus listas y ventas asociadas.`;

      deleteUserModal.classList.add('is-open');
      deleteUserModal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('modal-open');
    }

    deleteUserButtons.forEach(button => {
      button.addEventListener('click', () => openDeleteUserModal(button));
    });

    closeDeleteUserButtons.forEach(button => {
      button.addEventListener('click', closeDeleteUserModal);
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeDeleteUserModal();
    });

    /* =========================
       COPIAR LINK DE REGISTRO
    ========================= */
    const copyLinkButtons = document.querySelectorAll('[data-copy-link]');

    async function copyLinkToClipboard(url, btn) {
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(url);
        } else {
          const textarea = document.createElement('textarea');
          textarea.value = url;
          textarea.style.position = 'fixed';
          textarea.style.opacity = '0';
          document.body.appendChild(textarea);
          textarea.select();
          document.execCommand('copy');
          document.body.removeChild(textarea);
        }
        return true;
      } catch (err) {
        return false;
      }
    }

    copyLinkButtons.forEach(button => {
      button.addEventListener('click', async () => {
        const url = button.dataset.copyLink;
        if (!url) return;

        const original = button.textContent;
        const ok = await copyLinkToClipboard(url, button);

        button.textContent = ok ? '✔ Copiado' : 'No se pudo copiar';
        button.classList.toggle('copied', ok);

        setTimeout(() => {
          button.textContent = original;
          button.classList.remove('copied');
        }, 1600);
      });
    });
  </script>


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
