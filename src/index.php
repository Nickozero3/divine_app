<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/roles.php';

/**
 * Devuelve una versión estable del recurso basada en su última modificación.
 * Evita usar time(), que anulaba la caché del navegador en cada visita.
 */
function homeAssetVersion(string $relativePath): string
{
  $absolutePath = __DIR__ . '/' . ltrim($relativePath, '/');

  return is_file($absolutePath)
    ? (string) filemtime($absolutePath)
    : (defined('APP_VERSION') ? (string) APP_VERSION : '1');
}

/**
 * Íconos SVG livianos. No dependen de una librería externa.
 */
function homeModuleIcon(string $icon): string
{
  return match ($icon) {
    'admin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9m5 10V5m6 14v-7m5 7V3"/><path d="M2 21h20"/></svg>',
    'kioskito' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="17" cy="20" r="1"/></svg>',
    'door' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 21h14M7 21V4.5A1.5 1.5 0 0 1 8.5 3H17v18"/><path d="M11 12h.01"/></svg>',
    'menu' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/></svg>',
    'stock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 7 9-4 9 4-9 4-9-4Z"/><path d="m3 7 9 4 9-4M3 7v10l9 4 9-4V7M12 11v10"/></svg>',
    default => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
  };
}

/*
 * Roles de la aplicación:
 * - admin: acceso total.
 * - puerta: listas en puerta y carta.
 * - usuario: RRPP/Pública; sus listas y carta.
 * - kiosko: Kioskito, Guardarropas y carta.
 */
$currentRole = strtolower(trim((string) (
  $currentRole
  ?? $currentUser['role']
  ?? ''
)));

$isAdmin = $currentRole === 'admin';
$isPuerta = $currentRole === 'puerta';
$isRrpp = $currentRole === 'usuario';
$isKiosko = $currentRole === 'kiosko';

$canSeeAdmin = canAccess($currentRole, 'admin');

$canSeeKioskito = canAccess($currentRole, 'kiosko');

$canSeeDoor = canAccess($currentRole, 'door');

$canSeeStock = canAccess($currentRole, 'stock');

$canSeeGuardarropas = canAccess($currentRole, 'guardarropas');


$roleLabels = [
  'admin' => 'Administrador',
  'puerta' => 'Guardia / Puerta',
  'usuario' => 'RRPP / Pública',
  'kiosko' => 'Kioskito / Guardarropas',
];

$displayName = trim((string) ($currentUser['display_name'] ?? 'Usuario')) ?: 'Usuario';
$roleLabel = $roleLabels[$currentRole] ?? 'Usuario';

$modules = array_values(array_filter([
  [
    'visible' => $canSeeAdmin,
    'href' => 'admin.php',
    'icon' => 'admin',
    'accent' => 'violet',
    'eyebrow' => 'Gestión',
    'title' => 'Administración',
    'description' => 'Dashboard, códigos QR, usuarios y estadísticas generales.',
  ],
  [
    'visible' => $canSeeKioskito,
    'href' => 'kioskito.php',
    'icon' => 'kioskito',
    'accent' => 'amber',
    'eyebrow' => 'Ventas y prendas',
    'title' => 'Kioskito',
    'description' => 'Registrá ventas, controlá la caja y gestioná el guardarropas desde el mismo módulo.',
  ],
  [
    'visible' => $canSeeDoor,
    'href' => 'listas.php',
    'icon' => 'door',
    'accent' => 'green',
    'eyebrow' => 'Ingreso',
    'title' => 'Listas en puerta',
    'description' => $isRrpp
      ? 'Gestioná tus listas, agregá invitados y compartí sus accesos.'
      : 'Consultá listas, estados de ingreso y control de asistentes.',
  ],
  [
    'visible' => $canSeeStock,
    'href' => 'stock_contenedor.php',
    'icon' => 'stock',
    'accent' => 'rose',
    'eyebrow' => 'Inventario',
    'title' => 'Stock',
    'description' => 'Controlá stock interno, externo y faltantes del contenedor.',
  ],
  [
    'visible' => true,
    'href' => 'menu.php',
    'icon' => 'menu',
    'accent' => 'blue',
    'eyebrow' => 'Consulta',
    'title' => 'Carta',
    'description' => 'Consultá precios y productos disponibles en segundos.',
  ],
], static fn(array $module): bool => $module['visible']));

$appVersion = defined('APP_VERSION') ? (string) APP_VERSION : '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0c0a12">
  <meta name="color-scheme" content="dark">
  <meta name="description" content="Panel de control de <?= e(APP_NAME) ?>">

  <title>Panel · <?= e(APP_NAME) ?></title>

  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="stylesheet" href="styles.css?v=<?= e(homeAssetVersion('styles.css')) ?>">
  <link rel="stylesheet" href="styles/theme.css?v=<?= e(homeAssetVersion('styles/theme.css')) ?>">
  <link rel="stylesheet" href="styles/index.css?v=<?= e(homeAssetVersion('styles/index.css')) ?>">

  <script src="js/theme.js?v=<?= e(homeAssetVersion('js/theme.js')) ?>" defer></script>
</head>

<body class="home-page" data-page="menu">
  <a class="home-skip-link" href="#main-content">Saltar al contenido</a>

  <div class="home-backdrop" aria-hidden="true">
    <span class="home-orb home-orb--one"></span>
    <span class="home-orb home-orb--two"></span>
    <span class="home-grid"></span>
  </div>

  <header class="home-header">
    <div class="home-header__inner">
      <a class="home-brand" href="index.php" aria-label="Ir al inicio de <?= e(APP_NAME) ?>">
        <span class="home-brand__mark" aria-hidden="true">D</span>
        <span class="home-brand__copy">
          <strong><?= e(APP_NAME) ?></strong>
          <small>Panel de control</small>
        </span>
      </a>

      <div class="home-header__actions">
        <div class="home-user" title="Sesión actual">
          <span class="home-user__avatar" aria-hidden="true">
            <?= e(mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8')) ?>
          </span>
          <span class="home-user__copy">
            <strong><?= e($displayName) ?></strong>
            <small><?= e($roleLabel) ?></small>
          </span>
        </div>

        <a class="home-logout" href="logout.php" aria-label="Cerrar sesión">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M10 17l5-5-5-5M15 12H3" />
            <path d="M14 3h4a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-4" />
          </svg>
          <span>Salir</span>
        </a>
      </div>
    </div>
  </header>

  <main id="main-content" class="home-main">
    <section class="home-hero" aria-labelledby="home-title">
      <div class="home-hero__content">
        <span class="home-kicker">
          <span class="home-kicker__dot" aria-hidden="true"></span>
          Sistema operativo del evento
        </span>

        <h1 id="home-title">
          Todo el control de la noche,
          <span>en un solo lugar.</span>
        </h1>

        <p>
          Accedé a las herramientas habilitadas para tu cuenta y gestioná cada área con menos pasos.
        </p>
      </div>

      <div class="home-summary" aria-label="Resumen de la sesión">
        <div class="home-summary__item">
          <span>Módulos</span>
          <strong><?= count($modules) ?></strong>
        </div>
        <div class="home-summary__divider" aria-hidden="true"></div>
        <div class="home-summary__item">
          <span>Perfil</span>
          <strong><?= e($roleLabel) ?></strong>
        </div>
      </div>
    </section>

    <section class="home-modules" aria-labelledby="modules-title">
      <div class="home-section-heading">
        <div>
          <span class="home-section-heading__eyebrow">Accesos rápidos</span>
          <h2 id="modules-title">¿Qué querés ver?</h2>
        </div>
        <p>Elegí un módulo para continuar.</p>
      </div>

      <nav class="home-module-grid" aria-label="Módulos disponibles">
        <?php foreach ($modules as $module): ?>
          <a
            class="home-module-card home-module-card--<?= e($module['accent']) ?>"
            href="<?= e($module['href']) ?>"
            aria-label="Abrir <?= e($module['title']) ?>">
            <span class="home-module-card__glow" aria-hidden="true"></span>

            <span class="home-module-card__top">
              <span class="home-module-card__icon">
                <?= homeModuleIcon($module['icon']) ?>
              </span>
              <span class="home-module-card__arrow" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                  <path d="M5 12h14M13 6l6 6-6 6" />
                </svg>
              </span>
            </span>

            <span class="home-module-card__body">
              <small><?= e($module['eyebrow']) ?></small>
              <strong><?= e($module['title']) ?></strong>
              <span><?= e($module['description']) ?></span>
            </span>

            <span class="home-module-card__footer">
              <span class="home-module-card__status">
                <span aria-hidden="true"></span>
                Disponible
              </span>
              <span>Abrir módulo</span>
            </span>
          </a>
        <?php endforeach; ?>
      </nav>
    </section>
  </main>

  <footer class="home-footer">
    <div class="home-footer__inner">
      <p>
        <?= e(APP_NAME) ?>
        <?php if ($appVersion !== ''): ?>
          <span aria-hidden="true">·</span> v<?= e($appVersion) ?>
        <?php endif; ?>
      </p>

      <button
        type="button"
        class="theme-toggle home-theme-toggle"
        id="themeToggle"
        data-theme-toggle
        aria-label="Cambiar tema visual">
        <span class="theme-toggle__icon" aria-hidden="true">◐</span>
        <span class="theme-toggle__copy">
          <span class="theme-toggle__eyebrow">Tema visual</span>
          <span class="theme-toggle__label" data-theme-label>Cambiar tema</span>
        </span>
        <span class="theme-toggle__track" aria-hidden="true">
          <span class="theme-toggle__thumb"></span>
        </span>
      </button>
    </div>
  </footer>
</body>

</html>
