<?php
include_once __DIR__ . '/const.php'; 
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$isAdmin = ($currentUser['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#f8f4ea">
<title><?= APP_NAME ?></title>
<link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="stars"></div>

<!-- MENU -->
<!-- MENU -->
<div id="page-menu" class="page active">
  <div class="menu-wrap">
    <div>
      <div class="menu-logo"><?= APP_NAME ?><br>APP</div>
      <div class="menu-sub">Panel de control Divino</div>

      <div style="text-align:center;margin-top:12px;color:var(--text2);font-size:13px;">
        <?= htmlspecialchars($currentUser['display_name'], ENT_QUOTES, 'UTF-8') ?>
        ·
        <?= htmlspecialchars($currentUser['role'], ENT_QUOTES, 'UTF-8') ?>
        ·
        <a href="logout.php" style="color:var(--gold-2);text-decoration:none;">Salir</a>
      </div>
    </div>

    <div class="menu-cards">

      <?php if ($isAdmin): ?>
      <div class="menu-card" onclick="location.href='admin.php'">
        <div class="menu-icon">📊</div>
        <div class="menu-info">
          <div class="menu-name">ADMIN</div>
          <div class="menu-desc">Dashboard · QR · Estadísticas</div>
        </div>
        <div class="menu-arr">›</div>
      </div>

      <div class="menu-card" onclick="location.href='scanner.php'">
        <div class="menu-icon">📷</div>
        <div class="menu-info">
          <div class="menu-name">SCANNER</div>
          <div class="menu-desc">Escanear QR · Confirmar entrada</div>
        </div>
        <div class="menu-arr">›</div>
      </div>

      <div class="menu-card" onclick="goTo('kioskito')">
        <div class="menu-icon">🛒</div>
        <div class="menu-info">
          <div class="menu-name">KIOSKITO</div>
          <div class="menu-desc">Ventas · Productos · Caja</div>
        </div>
        <div class="menu-arr">›</div>
      </div>
      <?php endif; ?>

      <div class="menu-card" onclick="goTo('puerta')">
        <div class="menu-icon">🚪</div>
        <div class="menu-info">
          <div class="menu-name">PUERTA</div>
          <div class="menu-desc">Listas · Entradas · Control</div>
        </div>
        <div class="menu-arr">›</div>
      </div>

    </div>
  </div>
</div>
<!-- KIOSKITO -->
<!-- KIOSKITO -->
<?php if ($isAdmin): ?>
<div id="page-kioskito" class="page">

  <div class="topbar">
    <div class="topbar-title" onclick="goTo('menu')">
      🛒 <?= APP_NAME ?> Kioskito
    </div>
    <button class="topbar-back" onclick="goTo('menu')">← Menú</button>
  </div>

  <div class="page-kioskito-wrap" style="padding-top:12px">

    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:12px;">

      <div style="padding:14px 16px 10px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <span style="font-size:13px;color:var(--text2);font-weight:500;">🛒 Venta actual</span>
        <span style="font-size:20px;font-weight:500;color:var(--text);" id="k-total">$0</span>
      </div>

      <div id="sale-detail" style="min-height:48px;padding:6px 0;">
        <div style="padding:14px 16px;font-size:14px;color:var(--text2);">Sin productos agregados.</div>
      </div>

      <div style="padding:10px 12px;border-top:1px solid var(--border);display:flex;gap:8px;">
        <button onclick="confirmCurrentSale()" class="btn-action btn-add" style="flex:1;">
          ✓ Confirmar venta
        </button>
      </div>

    </div>

    <div id="sales-history"></div>

    <div id="k-categories"></div>

    <div class="kioskito-bottom">
      <div class="action-row">
        <button class="btn-action btn-add" onclick="openAddProduct()">
          ＋ Añadir
        </button>

        <button class="btn-action" onclick="toggleEditProducts()">
          ✎ Modificar
        </button>

        <button class="btn-action btn-reset" onclick="openPin()">
          🔒 Reiniciar
        </button>
      </div>
    </div>

  </div>
</div>
<?php endif; ?>
<!-- PUERTA -->
<div id="page-puerta" class="page">
  <div class="topbar">
    <div class="topbar-title" onclick="goTo('menu')"><?= APP_NAME ?> Puerta 🚪</div>
    <button class="topbar-back" onclick="goTo('menu')">← Menú</button>
  </div>

  <div class="lists-wrap">
    <div style="padding:0 0 12px;display:flex;flex-direction:column;gap:10px;">
      <?php if ($isAdmin): ?>
      <input
        id="list-search"
        type="text"
        placeholder="Buscar por lista o usuario..."
        oninput="drawPuerta()"
        style="width:100%;padding:12px 14px;border-radius:14px;border:1px solid var(--border);background:var(--bg3);color:var(--text);outline:none;font-size:14px;"
      >
      <?php endif; ?>

      <input
        id="person-search"
        type="text"
        placeholder="<?= $isAdmin ? 'Buscar por nombre en todas las listas...' : 'Buscar nombre dentro de tu lista...' ?>"
        oninput="drawPuerta()"
        style="width:100%;padding:12px 14px;border-radius:14px;border:1px solid var(--border);background:var(--bg3);color:var(--text);outline:none;font-size:14px;"
      >
    </div>

    <div id="p-lists"></div>
  </div>

  <button class="fab" onclick="openAddList()" title="Nueva lista">＋</button>
</div>

<!-- MODAL ADD PRODUCT -->
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="modal-add-product">
  <div class="modal-box">
    <div class="modal-title">Nuevo producto</div>

    <div class="modal-field">
      <label>Nombre</label>
      <input type="text" id="ap-name" placeholder="Ej: Fernet" maxlength="40">
    </div>

    <div class="modal-field">
      <label>Precio</label>
      <input type="number" id="ap-price" placeholder="0" min="0">
    </div>

    <div class="modal-field">
      <label>Categoría</label>
      <select id="ap-cat">
        <option value="Vasos">Vasos</option>
        <option value="Snacks">Snacks</option>
        <option value="Combos">Combos</option>
        <option value="Extras">Extras</option>
        <option value="Bebidas">Bebidas</option>
        <option value="Otros">Otros</option>
        <option value="Prendas">Botellas</option>
      </select>
    </div>

    <div class="modal-btns">
      <button class="btn-modal btn-cancel" onclick="closeModal('modal-add-product')">Cancelar</button>
      <button class="btn-modal btn-confirm" id="ap-submit-btn" onclick="saveProduct()">Agregar</button>
    </div>
  </div>
</div>

<!-- MODAL PIN -->
<div class="modal-overlay" id="modal-pin">
  <div class="modal-box">
    <div class="modal-title">🔒 Confirmar reinicio</div>
    <div class="pin-display" id="pin-display">·  ·  ·  ·</div>
    <div class="pin-grid" id="pin-grid"></div>
    <div class="pin-err" id="pin-err"></div>

    <div class="modal-btns" style="margin-top:14px">
      <button class="btn-modal btn-cancel" onclick="closeModal('modal-pin'); pinClear();">Cancelar</button>
    </div>
  </div>
</div>

<!-- MODAL GUARDARROPAS -->
<div class="modal-overlay" id="modal-guardarropas">
  <div class="modal-box">
    <div class="modal-title">🧥 Nuevo guardarropas</div>

    <div class="modal-field">
      <label>Nombre</label>
      <input type="text" id="gr-name" placeholder="Ej: Nicko"
        onkeydown="if(event.key==='Enter'){document.getElementById('gr-dni').focus();}">
    </div>

    <div class="modal-field">
      <label>DNI (opcional)</label>
      <input type="text" id="gr-dni" placeholder="Ej: 40111222"
        onkeydown="if(event.key==='Enter'){document.getElementById('gr-phone').focus();}">
    </div>

    <div class="modal-field">
      <label>Teléfono (opcional)</label>
      <input type="text" id="gr-phone" placeholder="Ej: 3548..."
        onkeydown="if(event.key==='Enter'){crearGuardarropas();}">
    </div>

    <div style="padding:12px 14px;border-radius:14px;border:1px solid var(--border);background:var(--bg3);color:var(--gold-2);font-weight:700;margin-bottom:12px;">
      1 número = 1 prenda = $2.000
    </div>

    <div class="modal-btns">
      <button class="btn-modal btn-cancel" onclick="closeModal('modal-guardarropas')">Cancelar</button>
      <button class="btn-modal btn-confirm" onclick="crearGuardarropas()">Crear número</button>
    </div>
  </div>
</div>

<?php endif; ?>

<!-- MODAL ADD LIST -->
<div class="modal-overlay" id="modal-add-list">
  <div class="modal-box">
    <div class="modal-title">Nueva lista</div>

    <div class="modal-field" id="auto-list-info">
      <label>Nombre automático</label>
      <div style="padding:12px 14px;border-radius:14px;border:1px solid var(--border);background:var(--bg3);color:var(--text2);font-size:14px;line-height:1.35;">
        La lista normal se crea como <b style="color:var(--gold-2);"><?= htmlspecialchars($currentUser['display_name'], ENT_QUOTES, 'UTF-8') ?></b>.<br>
        Si marcás cumpleaños, se crea como <b style="color:var(--gold-2);"><?= htmlspecialchars($currentUser['display_name'], ENT_QUOTES, 'UTF-8') ?> Cumpleaños 1</b>, <b style="color:var(--gold-2);"><?= htmlspecialchars($currentUser['display_name'], ENT_QUOTES, 'UTF-8') ?> Cumpleaños 2</b>, etc.
      </div>
    </div>

    <div class="modal-field">
      <label style="display:flex;align-items:center;gap:10px;text-transform:none;letter-spacing:0;font-size:14px;color:var(--text);">
        <input type="checkbox" id="al-birthday" style="width:18px;height:18px;accent-color:#b07cff;">
        Crear como cumpleaños
      </label>
    </div>

    <div class="modal-btns">
      <button class="btn-modal btn-cancel" onclick="closeModal('modal-add-list')">Cancelar</button>
      <button class="btn-modal btn-confirm" onclick="addList()">Crear</button>
    </div>
  </div>
</div>

<script>
window.DIVINE_USER = <?= json_encode([
    'id' => (int) $currentUser['id'],
    'username' => $currentUser['username'],
    'display_name' => $currentUser['display_name'],
    'role' => $currentUser['role'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
<script src="script.js"></script>
</body>
</html>