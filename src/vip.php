<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/assets.php';

if (!$canSeeVip) {
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
  <meta name="theme-color" content="#0c0a12">
  <title><?= e(APP_NAME) ?> · Kioskito VIP</title>
  <link rel="stylesheet" href="styles.css?v=<?= asset_version('styles.css') ?>">
  <link rel="icon" type="image/x-icon" href="./favicon.ico">

<link rel="stylesheet" href="styles/theme.css?v=<?= asset_version('styles/theme.css') ?>">
<link rel="stylesheet" href="styles/kioskito.css?v=<?= asset_version('styles/kioskito.css') ?>">
<script src="js/theme.js?v=<?= asset_version('js/theme.js') ?>" defer></script>
  <script src="pwa.js" defer></script>
</head>
<body data-page="kioskito-vip" data-zona="vip">
  <div class="stars"></div>

  <main id="page-kioskito" class="page active" data-zona="vip">
    <div class="topbar">
      <div class="topbar-title" onclick="location.href='index.php'">
        <span class="divine-kiosk-brand"><?= e(APP_NAME) ?></span>
        <span class="divine-kiosk-name">Kioskito VIP</span>
        <span class="live-dot">● LIVE</span>
      </div>
      <div class="kiosk-top-actions">
        <span class="kiosk-role-badge"><?= $isAdmin ? '🛡️ Admin' : ($isKioskito ? '🛒 Kioskito' : '🧾 Cajera') ?></span>
        <span class="kiosk-cash-badge">● Caja VIP</span>
        <?php if ($isAdmin): ?>
          <button class="topbar-back kiosk-admin-action" type="button" onclick="openAddProduct()">＋ Producto</button>
          <button class="topbar-back kiosk-admin-action" type="button" onclick="toggleEditProducts()">✎ Editar</button>
        <?php endif; ?>
        <button class="topbar-back" type="button" onclick="location.href='index.php'">← Menú</button>
      </div>
    </div>

    <div class="page-kioskito-wrap">
      <section class="kiosk-catalog-shell" aria-label="Catálogo Kioskito VIP">
        <div class="kiosk-catalog-head">
          <div>
            <div class="kiosk-eyebrow">PUNTO DE VENTA · VIP</div>
            <h1>Kioskito VIP</h1>
            <p class="kiosk-catalog-subtitle">Venta rápida y táctil, exclusiva de VIP.</p>
          </div>
          <div class="kiosk-quick-info">⚡ Tocá un producto para agregarlo</div>
        </div>

        <div class="kiosk-tools">
          <div id="kiosk-product-search-wrap" class="kiosk-search-wrap">
            <span class="kiosk-search-icon" aria-hidden="true">⌕</span>
            <input id="kiosk-product-search" class="kiosk-product-search" type="search" placeholder="Buscar producto..." autocomplete="off" oninput="filterKioskProducts(this.value)">
          </div>
        </div>

        <div id="k-categories"></div>
      </section>

      <aside id="kioskito-side-panel" class="kioskito-side-panel" aria-label="Venta actual y caja VIP">
        <div class="kiosk-side-tabs" role="tablist" aria-label="Panel de caja VIP">
          <button id="k-side-tab-sale" class="kiosk-side-tab active" type="button" role="tab" aria-selected="true" onclick="switchKioskSideTab('sale')">🛒 Venta actual</button>
          <button id="k-side-tab-history" class="kiosk-side-tab" type="button" role="tab" aria-selected="false" onclick="switchKioskSideTab('history')">▤ Historial y caja</button>
        </div>

        <div id="k-side-pane-sale" class="kiosk-side-pane">
        <div class="kioskito-sale-card">
          <div class="kioskito-sale-header">
            <div>
              <div class="kiosk-sale-label">VENTA ACTUAL</div>
              <div class="kiosk-sale-context">Caja VIP · abierta</div>
            </div>
            <span id="k-total">$0</span>
          </div>

          <div id="sale-detail" class="kioskito-sale-detail">
            <div class="kioskito-empty-cart">
              <div class="empty-cart-icon">🛒</div>
              <strong>Tu venta está vacía</strong>
              <span>Elegí un producto del catálogo.</span>
            </div>
          </div>

          <div class="kioskito-sale-sticky">
            <div class="payment-heading">Método de pago</div>
            <div class="payment-methods">
              <button class="payment-btn active" type="button" data-payment="efectivo" onclick="selectPaymentMethod('efectivo')">💵 <span>Efectivo</span></button>
              <button class="payment-btn" type="button" data-payment="transferencia" onclick="selectPaymentMethod('transferencia')">📲 <span>Transferencia</span></button>
              <button class="payment-btn" type="button" data-payment="tarjeta" onclick="selectPaymentMethod('tarjeta')">💳 <span>Tarjeta</span></button>
              <button class="payment-btn" type="button" data-payment="regalo" onclick="selectPaymentMethod('regalo')">🎁 <span>Regalo</span></button>
            </div>
            <button id="confirm-sale-btn" type="button" onclick="confirmCurrentSale()" class="btn-action btn-add">✓ Confirmar venta</button>
          </div>
        </div>
        </div>

        <div id="k-side-pane-history" class="kiosk-side-pane" hidden>
          <div id="sales-history"></div>
          <div class="kioskito-bottom">
            <div id="kiosko-summary"></div>
            <button class="btn-action btn-close-cash" type="button" onclick="closeKioskoCash()">🧾 Cerrar Caja VIP</button>
          </div>
        </div>
      </aside>
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

      <div class="modal-field">
        <label>Dónde se vende</label>
        <select id="ap-zona">
          <option value="vip">Solo VIP</option>
          <option value="ambos">Kiosko y VIP</option>
          <option value="kiosko">Solo Kiosko</option>
        </select>
      </div>

      <div id="ap-qty-info" class="ap-qty-info" style="display:none;"></div>

      <div class="modal-btns">
        <button class="btn-modal btn-cancel" onclick="closeModal('modal-add-product')">Cancelar</button>
        <button class="btn-modal btn-confirm" id="ap-submit-btn" onclick="saveProduct()">Agregar</button>
      </div>
    </div>
  </div>

<script>
    window.DIVINE_USER = <?= divineUserPayload($currentUser, $currentRole, $isAdmin, $isPuerta, $canManageDoor) ?>;
  </script>
  <script src="script.js?v=<?= asset_version('script.js') ?>"></script>

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
