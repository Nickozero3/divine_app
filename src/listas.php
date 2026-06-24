<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#f8f4ea">
  <title><?= e(APP_NAME) ?> · Listas</title>
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
  <link rel="icon" type="image/x-icon" href="./favicon.ico">

<link rel="stylesheet" href="styles/theme.css?v=<?= time() ?>">
<script src="js/theme.js?v=<?= time() ?>" defer></script>
</head>
<style> body {min-height: 100vh;}</style>
<body data-page="listas">
  <div class="stars"></div>

  <main id="page-puerta" class="page active">
    <div class="topbar">
      <div class="topbar-title" onclick="location.href='index.php'">
        <?= e(APP_NAME) ?> Puerta 🚪
        <span class="live-dot">● live</span>
      </div>
      <button class="topbar-back" onclick="location.href='index.php'">← Menú</button>
    </div>

    <div class="lists-wrap">
      <div style="padding:0 0 12px;display:flex;flex-direction:column;gap:10px;">
        <?php if ($canManageDoor): ?>
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
          placeholder="<?= $canManageDoor ? 'Buscar por nombre en todas las listas...' : 'Buscar nombre dentro de tu lista...' ?>"
          oninput="drawPuerta()"
          style="width:100%;padding:12px 14px;border-radius:14px;border:1px solid var(--border);background:var(--bg3);color:var(--text);outline:none;font-size:14px;"
        >
      </div>

      <div id="p-lists"></div>
    </div>

    <button class="fab" onclick="openAddList()" title="Nueva lista">＋</button>
  </main>

  <div class="modal-overlay" id="modal-add-list">
    <div class="modal-box">
      <div class="modal-title">Nueva lista</div>

      <div class="modal-field" id="auto-list-info">
        <label>Nombre automático</label>
        <div style="padding:12px 14px;border-radius:14px;border:1px solid var(--border);background:var(--bg3);color:var(--text2);font-size:14px;line-height:1.35;">
          La lista normal se crea como <b style="color:var(--gold-2);"><?= e((string) ($currentUser['display_name'] ?? 'Usuario')) ?></b>.<br>
          Si marcás cumpleaños, se crea como <b style="color:var(--gold-2);"><?= e((string) ($currentUser['display_name'] ?? 'Usuario')) ?> Cumpleaños 1</b>, <b style="color:var(--gold-2);"><?= e((string) ($currentUser['display_name'] ?? 'Usuario')) ?> Cumpleaños 2</b>, etc.
        </div>
      </div>

      <div class="modal-field">
        <label style="display:flex;align-items:center;gap:10px;text-transform:none;letter-spacing:0;font-size:14px;color:var(--text);">
          <input type="checkbox" id="al-birthday" style="width:18px;height:18px;accent-color:#b07cff;">
          Crear como cumpleaños
        </label>
      </div>

      <div class="modal-btns">
        <button type="button" class="btn-modal btn-cancel" onclick="closeModal('modal-add-list')">Cancelar</button>
        <button type="button" class="btn-modal btn-confirm" onclick="addList(this)">Crear</button>
      </div>
    </div>
  </div>
<script>
    window.DIVINE_USER = <?= divineUserPayload($currentUser, $currentRole, $isAdmin, $isPuerta, $canManageDoor) ?>;
  </script>
  <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
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
