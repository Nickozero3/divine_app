<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

?>
<!DOCTYPE html>
<html lang="es">

<head>

  <meta charset="UTF-8">

  <meta
    name="viewport"
    content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">

  <meta
    name="theme-color"
    content="#f8f4ea">

  <title><?= e(APP_NAME) ?> · Puerta</title>

  <link rel="stylesheet" href="styles.css?v=1">
  <link
    rel="stylesheet"
    href="styles/theme.css?v=<?= time() ?>">

  <link
    rel="icon"
    href="favicon.ico">

  <script
    src="js/theme.js?v=<?= time() ?>"
    defer></script>

</head>

<body data-page="listas">

  <div class="stars"></div>

  <main
    id="page-puerta"
    class="page active">

    <div class="topbar">

      <div
        class="topbar-title"
        onclick="location.href='index.php'">

        <?= e(APP_NAME) ?>

        Puerta 🚪

        <span class="live-dot">

          ● live

        </span>

      </div>

      <button
        class="topbar-back"
        onclick="location.href='index.php'">

        ← Menú

      </button>

    </div>

    <div class="lists-wrap">

      <?php if ($canManageDoor): ?>

        <div class="door-view-selector">

          <button
            id="door-view-mine"
            class="door-view-btn active"
            onclick="setDoorView('mine')">

            👤 Mi Lista

          </button>

          <button
            id="door-view-all"
            class="door-view-btn"
            onclick="setDoorView('all')">

            📋 Todas

          </button>

        </div>

      <?php endif; ?>

      <div class="door-toolbar">

        <div class="door-searches">

          <?php if ($canManageDoor): ?>

            <input

              id="list-search"

              type="text"

              placeholder="🔎 Buscar lista o usuario..."

              oninput="drawPuerta()">

          <?php endif; ?>

          <input

            id="person-search"

            type="text"

            placeholder="<?= $canManageDoor
                            ? '🔎 Buscar persona...'
                            : '🔎 Buscar nombre...' ?>"

            oninput="drawPuerta()">

        </div>

        <button

          class="door-scanner-btn"

          onclick="openScanner()">

          <div class="scanner-icon">

            📷

          </div>

          <div class="scanner-text">

            Scanner

          </div>

        </button>

      </div>

      <!-- ACA EMPIEZAN LAS LISTAS -->

      <div id="p-lists"></div>
    </div>

    </div>

    <button class="fab" onclick="openAddList()" title="Nueva lista">
      ＋
    </button>

  </main>

  <!-- ===========================
     MODAL NUEVA LISTA
=========================== -->

  <div class="modal-overlay" id="modal-add-list">

    <div class="modal-box">

      <div class="modal-title">

        Nueva lista

      </div>

      <div class="modal-field">

        <label>

          Nombre automático

        </label>

        <div
          id="auto-list-info"
          style="
                    padding:14px;
                    border-radius:14px;
                    background:var(--bg3);
                    border:1px solid var(--border);
                    color:var(--text2);
                    line-height:1.5;
                ">

          La lista principal será:

          <br><br>

          <b style="color:var(--gold-2);">

            <?= e((string)$currentUser['display_name']) ?>

          </b>

          <br><br>

          Si es cumpleaños:

          <br>

          <b style="color:var(--gold-2);">

            <?= e((string)$currentUser['display_name']) ?>

            Cumpleaños 1

          </b>

        </div>

      </div>

      <div class="modal-field">

        <label
          style="
                    display:flex;
                    align-items:center;
                    gap:12px;
                    font-size:15px;
                    text-transform:none;
                ">

          <input
            id="al-birthday"
            type="checkbox">

          Crear como cumpleaños

        </label>

      </div>

      <div class="modal-btns">

        <button

          class="btn-modal btn-cancel"

          onclick="closeModal('modal-add-list')">

          Cancelar

        </button>

        <button

          class="btn-modal btn-confirm"

          onclick="addList(this)">

          Crear

        </button>

      </div>

    </div>

  </div>

  <!-- ===========================
     MODAL SCANNER
=========================== -->

  <div
    id="scanner-modal"
    class="modal-overlay"
    style="display:none;">

    <div class="modal-box scanner-modal-box">

      <div class="modal-title">

        📷 Escanear QR

      </div>

      <div class="reader-frame">

        <div id="reader"></div>

        <div class="scan-corners"></div>

      </div>

      <section
        id="qr-result"
        class="result-card">

        <div class="status-pill status-wait">

          ● Esperando QR

        </div>

        <div class="qr-detail">

          Apuntá la cámara al código QR del invitado.

        </div>

      </section>

      <div class="modal-btns">

        <button

          class="btn-modal btn-cancel"

          onclick="closeScanner()">

          Cerrar

        </button>

      </div>

    </div>

  </div>
  <script>
    window.DIVINE_USER =
      <?= divineUserPayload(
        $currentUser,
        $currentRole,
        $isAdmin,
        $isPuerta,
        $canManageDoor
      ) ?>;
  </script>

  <!-- Librería QR -->
  <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>

  <!-- Lector QR -->
  <script src="https://unpkg.com/html5-qrcode" defer></script>

  <!-- Script principal -->
  <script src="script.js?v=1" defer></script>


  <footer class="theme-footer" aria-label="Preferencias visuales">

    <button
      type="button"
      class="theme-toggle"
      id="themeToggle"
      data-theme-toggle>

      <span
        class="theme-toggle__icon">

        ◐

      </span>

      <span class="theme-toggle__copy">

        <span class="theme-toggle__eyebrow">

          Tema visual

        </span>

        <span
          class="theme-toggle__label"
          data-theme-label>

          Cambiar tema

        </span>

      </span>

      <span
        class="theme-toggle__track">

        <span
          class="theme-toggle__thumb">

        </span>

      </span>

    </button>

  </footer>

</body>

</html>