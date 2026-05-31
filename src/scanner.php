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

<link rel="stylesheet" href="./scanner.css">
<link rel="stylesheet" href="styles.css">
<script src="https://unpkg.com/html5-qrcode"></script>

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

async function api(action, data = null) {
  const options = { credentials: 'same-origin' };

  if (data !== null) {
    options.method = 'POST';
    options.headers = { 'Content-Type': 'application/json' };
    options.body = JSON.stringify(data);
  }

  const res = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);
  let json;

  try {
    json = await res.json();
  } catch (e) {
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
      Cuando detecte un código, se verificará automáticamente.
    </div>
  `;
}

async function onScanSuccess(decodedText) {
  if (isChecking) return;

  const token = extractToken(decodedText);

  if (!token || token === lastToken) return;

  lastToken = token;
  isChecking = true;

  const result = document.getElementById('qr-result');

  result.innerHTML = `
    <div class="status-pill status-checking">⟳ Verificando QR</div>
    <div class="qr-detail">
      Consultando datos del invitado...
    </div>
  `;

  try {
    const data = await api('qr_check', { token });
    const p = data.person;

    result.innerHTML = `
      <div class="status-pill status-ok">✓ QR válido</div>

      <div class="qr-person">${esc(p.name)}</div>

      <div class="qr-detail">
        <b>Lista:</b> ${esc(p.list_name)}<br>
        <b>Estado:</b> ${esc(p.status)}<br>
        <b>Dato:</b> ${esc(p.note || 'Sin dato')}
      </div>

      <div class="scanner-actions">
        <button class="scan-btn scan-btn-primary" onclick="confirmQR('${esc(token)}')">
          Confirmar entrada
        </button>

        <button class="scan-btn scan-btn-secondary" onclick="resetScanner()">
          Escanear otro
        </button>
      </div>
    `;
  } catch (error) {
    result.innerHTML = `
      <div class="status-pill status-error">✕ QR inválido</div>

      <div class="qr-detail">
        ${esc(error.message)}
      </div>

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

async function confirmQR(token) {
  const ok = confirm('¿Confirmar entrada?');
  if (!ok) return;

  try {
    const data = await api('qr_confirm', { token });
    const p = data.person;

    document.getElementById('qr-result').innerHTML = `
      <div class="confirmed-box">
        <div class="confirmed-icon">✓</div>

        <div class="status-pill status-ok">Entrada confirmada</div>

        <div class="qr-person">${esc(p.name)}</div>

        <div class="qr-detail">
          <b>Lista:</b> ${esc(p.list_name)}<br>
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
    alert(error.message);
  }
}

function resetScanner() {
  lastToken = null;
  renderWaiting();
}

function startScanner() {
  scanner = new Html5QrcodeScanner(
    "reader",
    {
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

</body>
</html>