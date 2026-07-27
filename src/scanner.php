<?php
session_start();
include_once __DIR__ . '/const.php';

if (!isset($_SESSION['user'])) {
  header('Location: login.php');
  exit;
}

$currentUser = $_SESSION['user'];

if (($currentUser['role'] ?? '') !== 'admin') {
  die('Acceso denegado');
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= APP_NAME ?> Scanner QR</title>

  <link rel="stylesheet" href="styles/scanner.css">
  <link rel="stylesheet" href="styles.css">
  <script src="https://unpkg.com/html5-qrcode"></script>


  <link rel="stylesheet" href="styles/theme.css?v=<?= time() ?>">
  <script src="js/theme.js?v=<?= time() ?>" defer></script>
</head>

<body>

  <div class="stars"></div>

  <div class="topbar">
    <div class="topbar-title" onclick="location.href='index.php'">Escanear QR 📷</div>
    <button class="topbar-back" onclick="location.href='index.php'">← Menu</button>
  </div>

  <main class="scanner-page">
    <div class="scanner-wrap">

      <div class="scanner-header">
        <div class="scanner-icon">▣</div>
        <div class="scanner-title">Scanner de entrada</div>
        <div class="scanner-subtitle">Apuntá la cámara al QR del invitado</div>
      </div>

      <section class="scanner-card">
        <div class="reader-frame">
          <div id="reader"></div>
          <div class="scan-corners"></div>
        </div>
      </section>

      <section class="result-card" id="qr-result">
        <div class="status-pill status-wait">● Esperando QR</div>
        <div class="qr-detail">
          Cuando detecte un código, se verificará automáticamente.
        </div>
      </section>

    </div>
  </main>
  <script>
    let scanner = null;
    let lastToken = null;
    let isChecking = false;

    let countdownTimer = null;
    let countdownSeconds = 10;
    let currentToken = null;
    let currentPerson = null;
    let alreadyFinished = false;

    async function api(action, data = null) {
      const options = {
        credentials: 'same-origin'
      };

      if (data !== null) {
        options.method = 'POST';
        options.headers = {
          'Content-Type': 'application/json'
        };
        options.body = JSON.stringify(data);
      }

      const res = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);

      let json;
      try {
        json = await res.json();
      } catch {
        throw new Error('Respuesta inválida del servidor.');
      }

      if (!res.ok || !json.ok) {
        throw new Error(json.error || 'Error del servidor');
      }

      return json;
    }

    function esc(str) {
      return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function extractToken(text) {
      const value = String(text || '').trim();

      try {
        const url = new URL(value);
        return url.searchParams.get('token') || value;
      } catch {
        return value;
      }
    }

    function renderWaiting() {
      document.getElementById('qr-result').innerHTML = `
    <div class="status-pill status-wait">● Esperando QR</div>
    <div class="qr-detail">
      Cuando detecte un código, se mostrarán los datos y correrá el tiempo de confirmación.
    </div>
  `;
    }

    function stopCountdown() {
      if (countdownTimer) {
        clearInterval(countdownTimer);
        countdownTimer = null;
      }
    }

    function renderPersonCard(person, token) {
      const nombre = person.name || person.nombre || 'Sin nombre';
      const apellido = person.last_name || person.apellido || '';
      const codigo = person.document_number || person.dni || person.code || person.numero || '';
      const lista = person.list_name || person.lista || 'Sin lista';

      document.getElementById('qr-result').innerHTML = `
    <div class="status-pill status-ok">✓ QR válido</div>

    <div class="qr-person">
      ${esc(nombre)} ${esc(apellido)}
    </div>

    <div class="qr-detail">
      ${codigo ? `<b>Código:</b> ${esc(codigo)}<br>` : ''}
      <b>Lista:</b> ${esc(lista)}<br>
      <b>Estado:</b> En espera de confirmación
    </div>

    <div class="status-pill status-checking" style="margin-top:12px;">
      ⏳ Se confirmará automáticamente en <span id="countdownValue">10</span> segundos
    </div>

    <div class="scanner-actions">
      <button class="scan-btn scan-btn-primary" onclick='confirmQR(${JSON.stringify(token)}, false)'>
        Confirmar entrada
      </button>

      <button class="scan-btn scan-btn-secondary" onclick="resetScanner()">
        Escanear otro
      </button>
    </div>
  `;
    }

    function startCountdown(token) {
      stopCountdown();

      currentToken = token;
      countdownSeconds = 10;
      alreadyFinished = false;

      const tick = () => {
        const el = document.getElementById('countdownValue');
        if (el) el.textContent = String(countdownSeconds);

        if (countdownSeconds <= 0) {
          stopCountdown();
          confirmQR(token, true);
          return;
        }

        countdownSeconds -= 1;
      };

      tick();
      countdownTimer = setInterval(tick, 1000);
    }

    async function onScanSuccess(decodedText) {
      if (isChecking) return;

      const token = extractToken(decodedText);
      if (!token || token === lastToken) return;

      lastToken = token;
      isChecking = true;
      stopCountdown();

      const result = document.getElementById('qr-result');
      result.innerHTML = `
    <div class="status-pill status-checking">⟳ Verificando QR</div>
    <div class="qr-detail">Consultando datos del invitado...</div>
  `;

      try {
        const data = await api('qr_check', {
          token
        });
        currentPerson = data.person;

        renderPersonCard(currentPerson, token);
        startCountdown(token);
      } catch (error) {
        result.innerHTML = `
      <div class="status-pill status-error">✕ QR inválido</div>
      <div class="qr-detail">${esc(error.message)}</div>
      <div class="scanner-actions">
        <button class="scan-btn scan-btn-secondary" onclick="resetScanner()">
          Escanear otro
        </button>
      </div>
    `;
      } finally {
        isChecking = false;
      }
    }

    async function confirmQR(token, auto = false) {
      if (alreadyFinished) return;
      alreadyFinished = true;

      stopCountdown();

      try {
        const data = await api('qr_confirm', {
          token
        });
        const p = data.person || currentPerson || {};
        const nombre = p.name || p.nombre || 'Sin nombre';
        const apellido = p.last_name || p.apellido || '';
        const codigo = p.document_number || p.dni || p.code || p.numero || '';
        const lista = p.list_name || p.lista || 'Sin lista';

        document.getElementById('qr-result').innerHTML = `
      <div class="confirmed-box">
        <div class="confirmed-icon">✓</div>
        <div class="status-pill status-ok">
          ${auto ? 'Entrada confirmada automáticamente' : 'Entrada confirmada'}
        </div>

        <div class="qr-person">
          ${esc(nombre)} ${esc(apellido)}
        </div>

        <div class="qr-detail">
          ${codigo ? `<b>Código:</b> ${esc(codigo)}<br>` : ''}
          <b>Lista:</b> ${esc(lista)}<br>
          <b>Estado:</b> Marcado como ENTRO
        </div>

        <div class="scanner-actions">
          <button class="scan-btn scan-btn-primary" onclick="resetScanner()">
            Escanear otro
          </button>
        </div>
      </div>
    `;
      } catch (error) {
        alreadyFinished = false;
        alert(error.message);
      }
    }

    function resetScanner() {
      lastToken = null;
      currentToken = null;
      currentPerson = null;
      alreadyFinished = false;
      stopCountdown();
      renderWaiting();
    }

    function startScanner() {
      scanner = new Html5QrcodeScanner(
        "reader", {
          fps: 10,
          qrbox: {
            width: 250,
            height: 250
          },
          rememberLastUsedCamera: true
        },
        false
      );

      scanner.render(onScanSuccess);
    }

    startScanner();
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