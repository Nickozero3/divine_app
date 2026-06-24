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
  <title><?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
  <link rel="icon" type="image/x-icon" href="./favicon.ico">

<link rel="stylesheet" href="styles/theme.css?v=<?= time() ?>">
<script src="js/theme.js?v=<?= time() ?>" defer></script>
</head>
<body data-page="menu">
  <div class="stars"></div>

  <main id="page-menu" class="page active">
    <div class="menu-wrap">
      <div>
        <div class="menu-logo"><?= e(APP_NAME) ?><br>APP</div>
        <div class="menu-sub">Panel de control Divino</div>

        <div style="text-align:center;margin-top:12px;color:var(--text2);font-size:13px;">
          <?= e((string) ($currentUser['display_name'] ?? 'Usuario')) ?>
          · <?= e(ucfirst($currentRole)) ?>
          · <a href="logout.php" style="color:var(--gold-2);text-decoration:none;">Salir</a>
        </div>
      </div>

      <div class="menu-cards">
        <?php if ($canSeeAdmin): ?>
          <div class="menu-card" onclick="location.href='admin.php'">
            <div class="menu-icon">📊</div>
            <div class="menu-info">
              <div class="menu-name">ADMIN</div>
              <div class="menu-desc">Dashboard · QR · Estadísticas</div>
            </div>
            <div class="menu-arr">›</div>
          </div>
        <?php endif; ?>

        <?php if ($canSeeScanner): ?>
          <div class="menu-card" onclick="location.href='scanner.php'">
            <div class="menu-icon">📷</div>
            <div class="menu-info">
              <div class="menu-name">SCANNER</div>
              <div class="menu-desc">Escanear QR · Confirmar entrada</div>
            </div>
            <div class="menu-arr">›</div>
          </div>
        <?php endif; ?>

        <?php if ($canSeeKioskito): ?>
          <div class="menu-card" onclick="location.href='kioskito.php'">
            <div class="menu-icon">🛒</div>
            <div class="menu-info">
              <div class="menu-name">KIOSKITO</div>
              <div class="menu-desc">Ventas · Productos · Caja</div>
            </div>
            <div class="menu-arr">›</div>
          </div>
        <?php endif; ?>

        <div class="menu-card" onclick="location.href='listas.php'">
          <div class="menu-icon">🚪</div>
          <div class="menu-info">
            <div class="menu-name">PUERTA</div>
            <div class="menu-desc">Listas · Entradas · Control</div>
          </div>
          <div class="menu-arr">›</div>
        </div>

        <div class="menu-card" onclick="location.href='menu.php'">
          <div class="menu-icon">📋</div>
          <div class="menu-info">
            <div class="menu-name">Carta</div>
            <div class="menu-desc">Consulta rápida de precios</div>
          </div>
          <div class="menu-arr">›</div>
        </div>
      </div>
    </div>
  </main>
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
