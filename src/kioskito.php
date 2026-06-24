<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

if (!$canSeeKioskito) {
    http_response_code(403);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#f8f4ea">
  <title><?= e(APP_NAME) ?> · Kioskito</title>
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
  <link rel="icon" type="image/x-icon" href="./favicon.ico">

<link rel="stylesheet" href="styles/theme.css?v=<?= time() ?>">
<script src="js/theme.js?v=<?= time() ?>" defer></script>
</head>
<body data-page="kioskito">
  <div class="stars"></div>

  <main id="page-kioskito" class="page active">
    <div class="topbar">
      <div class="topbar-title" onclick="location.href='index.php'">
        <?= e(APP_NAME) ?> Kioskito 🛒
        <span class="live-dot">● live</span>
      </div>
      <button class="topbar-back" onclick="location.href='index.php'">← Menú</button>
    </div>

    <div class="page-kioskito-wrap" style="padding-top:12px">
      <div id="kioskito-side-panel" class="kioskito-side-panel">
        <div class="kioskito-sale-card">
          <div class="kioskito-sale-header">
            <span>🛒 Venta actual</span>
            <span id="k-total">$0</span>
          </div>

          <div id="sale-detail" class="kioskito-sale-detail">
            <div class="kioskito-empty-cart">Sin productos agregados.</div>
          </div>

          <div class="kioskito-sale-sticky">
            <div class="payment-methods">
              <button class="payment-btn active" data-payment="efectivo" onclick="selectPaymentMethod('efectivo')">💵 Efectivo</button>
              <button class="payment-btn" data-payment="transferencia" onclick="selectPaymentMethod('transferencia')">📲 Transferencia</button>
              <button class="payment-btn" data-payment="tarjeta" onclick="selectPaymentMethod('tarjeta')">💳 Tarjeta</button>
              <button class="payment-btn" data-payment="regalo" onclick="selectPaymentMethod('regalo')">🎁 Regalo</button>
            </div>

            <button id="confirm-sale-btn" onclick="confirmCurrentSale()" class="btn-action btn-add">✓ Confirmar venta</button>
          </div>
        </div>

        <div id="sales-history"></div>
      </div>

      <div id="k-categories"></div>

      <div class="kioskito-bottom">
        <div id="kiosko-summary"></div>
        <button class="btn-action btn-close-cash" onclick="closeKioskoCash()">🧾 Cerrar caja</button>
      </div>
    </div>
  </main>

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
          <option value="Combos">Combos</option>
          <option value="Botellas">Botellas</option>
          <option value="Bebidas">Bebidas</option>
          <option value="Kiosko">Kiosko</option>
          <option value="Snacks">Snacks</option>
          <option value="Extras">Extras</option>
          <option value="Otros">Otros</option>
        </select>
      </div>

      <div class="modal-btns">
        <button class="btn-modal btn-cancel" onclick="closeModal('modal-add-product')">Cancelar</button>
        <button class="btn-modal btn-confirm" id="ap-submit-btn" onclick="saveProduct()">Agregar</button>
      </div>
    </div>
  </div>

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

  <div class="modal-overlay" id="modal-guardarropas">
    <div class="modal-box">
      <div class="modal-title">🧥 Nuevo guardarropas</div>

      <div class="modal-field">
        <label>Nombre</label>
        <input type="text" id="gr-name" placeholder="Ej: Nicko" onkeydown="if(event.key==='Enter'){document.getElementById('gr-dni').focus();}">
      </div>

      <div class="modal-field">
        <label>DNI (opcional)</label>
        <input type="text" id="gr-dni" placeholder="Ej: 40111222" onkeydown="if(event.key==='Enter'){document.getElementById('gr-phone').focus();}">
      </div>

      <div class="modal-field">
        <label>Teléfono (opcional)</label>
        <input type="text" id="gr-phone" placeholder="Ej: 3548..." onkeydown="if(event.key==='Enter'){crearGuardarropas();}">
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
<script>
    window.DIVINE_USER = <?= divineUserPayload($currentUser, $currentRole, $isAdmin, $isPuerta, $canManageDoor) ?>;
  </script>
  <script src="script.js?v=<?= time() ?>"></script>

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
