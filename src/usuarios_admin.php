<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/conexion.php';

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
    $username = mb_strtolower($username, 'UTF-8');

    return preg_replace('/[^a-z0-9_.-]/i', '', $username) ?? '';
}

$roles = [
    'admin' => [
        'label' => 'Rol: Admin',
        'desc' => 'Acceso completo al sistema.',
    ],
    'puerta' => [
        'label' => 'Rol: Puerta',
        'desc' => 'Acceso a puerta y scanner.',
    ],
    'usuario' => [
        'label' => 'Rol: Usuario',
        'desc' => 'Acceso limitado a sus listas.',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    try {
        /*
        |--------------------------------------------------------------------------
        | CREAR USUARIO
        |--------------------------------------------------------------------------
        */

        if ($action === 'add_user') {
            $username = normalizeUsername((string)($_POST['username'] ?? ''));
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? 'usuario');

            if ($username === '' || mb_strlen($username) < 3) {
                redirectWithMessage('error', 'El usuario debe tener al menos 3 caracteres.');
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

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users 
                (username, display_name, password_hash, role)
                VALUES 
                (:username, :display_name, :password_hash, :role)
            ");

            $stmt->execute([
                ':username' => $username,
                ':display_name' => $displayName,
                ':password_hash' => $passwordHash,
                ':role' => $role,
            ]);

            $newUserId = (int)$pdo->lastInsertId();

            if ($role === 'usuario' && isset($_POST['create_list'])) {
                $listName = $displayName;

                $stmtList = $pdo->prepare("
                    INSERT INTO door_lists 
                    (user_id, name, is_birthday, price_per_person)
                    VALUES 
                    (:user_id, :name, 0, 500)
                ");

                $stmtList->execute([
                    ':user_id' => $newUserId,
                    ':name' => $listName,
                ]);
            }

            redirectWithMessage('success', 'Usuario creado correctamente.');
        }

        /*
        |--------------------------------------------------------------------------
        | ACTUALIZAR USUARIO
        |--------------------------------------------------------------------------
        | Ahora también permite cambiar el username, no solo el nombre visible.
        |--------------------------------------------------------------------------
        */

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

            /*
            |--------------------------------------------------------------------------
            | SI EL ADMIN EDITA SU PROPIO USUARIO, ACTUALIZAR LA SESIÓN
            |--------------------------------------------------------------------------
            */

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
    SELECT id, username, display_name, role, created_at
    FROM users
    ORDER BY
      CASE role
        WHEN 'admin' THEN 1
        WHEN 'puerta' THEN 2
        WHEN 'usuario' THEN 3
        ELSE 9
      END,
      display_name ASC
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash_users'] ?? null;
unset($_SESSION['flash_users']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Usuarios y roles · <?= APP_NAME ?></title>
<link rel="stylesheet" href="styles.css">

<style>
:root {
  --bg: #09060f;
  --bg2: #130b1c;
  --card: #1a1025;
  --gold: #f0d48d;
  --purple: #8f4cff;
  --text: #fff8ea;
  --text2: #c9bdd7;
  --border: rgba(240, 212, 141, .18);
  --green: #4ade80;
  --red: #fb7185;
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  min-height: 100vh;
  background:
    radial-gradient(circle at top, rgba(143, 76, 255, .18), transparent 34%),
    linear-gradient(180deg, #120a1a 0%, #07040b 100%);
  color: var(--text);
  font-family: Arial, sans-serif;
}

.topbar {
  position: sticky;
  top: 0;
  z-index: 20;
  background: rgba(12, 7, 18, .94);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
  padding: 12px 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.topbar-title {
  color: var(--gold);
  font-weight: 900;
  font-size: 18px;
  cursor: pointer;
}

.topbar-back {
  border: 1px solid var(--border);
  background: rgba(255,255,255,.06);
  color: var(--text);
  border-radius: 12px;
  padding: 10px 12px;
  font-weight: 800;
  cursor: pointer;
}

.wrap {
  width: min(1050px, 100%);
  margin: 0 auto;
  padding: 16px;
}

.page-title {
  color: var(--gold);
  font-size: 28px;
  font-weight: 900;
  margin: 0 0 12px;
}

.card {
  background: rgba(19, 11, 28, .94);
  border: 1px solid var(--border);
  border-radius: 22px;
  padding: 14px;
  margin-bottom: 14px;
  box-shadow: 0 14px 34px rgba(0,0,0,.24);
}

.card-title {
  color: var(--gold);
  font-size: 20px;
  font-weight: 900;
  margin-bottom: 12px;
}

.flash {
  border-radius: 14px;
  padding: 12px 14px;
  font-weight: 800;
  margin-bottom: 14px;
}

.flash.success {
  background: rgba(74, 222, 128, .14);
  border: 1px solid rgba(74, 222, 128, .35);
  color: var(--green);
}

.flash.error {
  background: rgba(251, 113, 133, .14);
  border: 1px solid rgba(251, 113, 133, .35);
  color: var(--red);
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1.2fr 1fr 1fr auto;
  gap: 10px;
  align-items: end;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field label {
  color: var(--text2);
  font-size: 12px;
  font-weight: 800;
}

.field input,
.field select {
  width: 100%;
  border: 1px solid var(--border);
  background: #120a1a;
  color: var(--text);
  border-radius: 13px;
  padding: 11px;
  outline: none;
  font-weight: 700;
}

.field input:focus,
.field select:focus {
  border-color: var(--gold);
}

.check-row {
  margin-top: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text2);
  font-size: 13px;
}

.check-row input {
  width: 18px;
  height: 18px;
}

.btn {
  border: 0;
  border-radius: 13px;
  padding: 12px 14px;
  font-weight: 900;
  cursor: pointer;
  background: linear-gradient(135deg, var(--gold), var(--purple));
  color: white;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
}

.roles-info {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 9px;
  margin-bottom: 14px;
}

.role-card {
  background: rgba(255,255,255,.04);
  border: 1px solid var(--border);
  border-radius: 15px;
  padding: 12px;
}

.role-name {
  color: var(--gold);
  font-weight: 900;
}

.role-desc {
  color: var(--text2);
  font-size: 12px;
  line-height: 1.35;
  margin-top: 5px;
}

.user-row {
  background: #120a1a;
  border: 1px solid rgba(240, 212, 141, .13);
  border-radius: 18px;
  padding: 12px;
  margin-bottom: 10px;
}

.user-head {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}

.user-name {
  font-weight: 900;
  color: var(--text);
}

.user-sub {
  font-size: 12px;
  color: var(--text2);
  margin-top: 3px;
}

.badge {
  border-radius: 999px;
  padding: 6px 10px;
  background: rgba(240, 212, 141, .12);
  border: 1px solid rgba(240, 212, 141, .22);
  color: var(--gold);
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.user-edit-grid {
  display: grid;
  grid-template-columns: 1fr 1.2fr 1fr 1fr auto;
  gap: 9px;
  align-items: end;
}

.empty {
  color: var(--text2);
  border: 1px dashed var(--border);
  border-radius: 16px;
  padding: 14px;
  text-align: center;
}

@media(max-width:950px) {
  .roles-info {
    grid-template-columns: 1fr;
  }

  .form-grid,
  .user-edit-grid {
    grid-template-columns: 1fr 1fr;
  }

  .btn {
    width: 100%;
  }
}

@media(max-width:520px) {
  .wrap {
    padding: 12px;
  }

  .page-title {
    font-size: 24px;
  }

  .form-grid,
  .user-edit-grid {
    grid-template-columns: 1fr;
  }

  .user-head {
    flex-direction: column;
    align-items: flex-start;
  }

  .topbar-title {
    font-size: 16px;
  }
}
</style>
</head>

<body>

<div class="topbar">
  <div class="topbar-title" onclick="location.href='admin.php'">Usuarios y roles</div>
  <button class="topbar-back" onclick="location.href='admin.php'">← Volver</button>
</div>

<div class="wrap">

  <h1 class="page-title">Gestión de usuarios</h1>

  <?php if ($flash): ?>
    <div class="flash <?= e((string)$flash['type']) ?>">
      <?= e((string)$flash['message']) ?>
    </div>
  <?php endif; ?>

  <div class="roles-info">
    <?php foreach ($roles as $roleKey => $roleData): ?>
      <div class="role-card">
        <div class="role-name"><?= e($roleData['label']) ?></div>
        <div class="role-desc"><?= e($roleData['desc']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-title">Crear usuario</div>

    <form method="POST">
      <input type="hidden" name="action" value="add_user">

      <div class="form-grid">
        <div class="field">
          <label>Usuario</label>
          <input type="text" name="username" placeholder="ej: darwin" required>
        </div>

        <div class="field">
          <label>Nombre visible</label>
          <input type="text" name="display_name" placeholder="Ej: Darwin" required>
        </div>

        <div class="field">
          <label>Contraseña</label>
          <input type="text" name="password" placeholder="Ej: darwin123" required>
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

      <label class="check-row">
        <input type="checkbox" name="create_list" value="1" checked>
        Crear lista automática si el rol es Usuario
      </label>
    </form>
  </div>

  <div class="card">
    <div class="card-title">Usuarios existentes</div>

    <?php if (empty($users)): ?>
      <div class="empty">No hay usuarios cargados.</div>
    <?php endif; ?>

    <?php foreach ($users as $user): ?>
      <?php
        $roleKey = (string)$user['role'];
        $roleLabel = $roles[$roleKey]['label'] ?? $roleKey;
      ?>

      <div class="user-row">
        <div class="user-head">
          <div>
            <div class="user-name"><?= e((string)$user['display_name']) ?></div>
            <div class="user-sub">
              Usuario: <?= e((string)$user['username']) ?> · Creado: <?= e((string)$user['created_at']) ?>
            </div>
          </div>

          <div class="badge"><?= e($roleLabel) ?></div>
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
            >
          </div>

          <div class="field">
            <label>Nombre visible</label>
            <input
              type="text"
              name="display_name"
              value="<?= e((string)$user['display_name']) ?>"
              required
            >
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
              type="text"
              name="new_password"
              placeholder="Dejar vacío para no cambiar"
            >
          </div>

          <button class="btn" type="submit">Guardar</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

</div>

</body>
</html>