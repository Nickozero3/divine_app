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
<link rel="stylesheet" href="styles.css">
<script src="https://unpkg.com/html5-qrcode"></script>

<style>
.scanner-wrap {
  padding: 16px;
  max-width: 520px;
  margin: auto;
}

.scanner-box {
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 14px;
  margin-bottom: 14px;
  box-shadow: var(--shadow);
}

#reader {
  width: 100%;
  overflow: hidden;
  border-radius: 16px;
}

.result-ok {
  color: var(--green);
  font-weight: 800;
}

.result-error {
  color: var(--red);
  font-weight: 800;
}

.qr-person {
  font-size: 24px;
  font-weight: 800;
  color: var(--gold-2);
  margin: 8px 0;
}

.qr-detail {
  color: var(--text2);
  font-size: 14px;
  line-height: 1.5;
  margin-bottom: 12px;
}
</style>
</head>

<body>

<div class="stars"></div>

<div class="topbar">
  <div class="topbar-title">📷 Escanear QR</div>
  <button class="topbar-back" onclick="location.href='index.php'">← App</button>
</div>

<div class="scanner-wrap">

  <div class="scanner-box">
    <div id="reader"></div>
  </div>

  <div class="scanner-box" id="qr-result">
    <div style="color:var(--text2);">Esperando QR...</div>
  </div>

</div>

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

async function onScanSuccess(decodedText) {
  if (isChecking) return;

  const token = extractToken(decodedText);

  if (!token || token === lastToken) return;

  lastToken = token;
  isChecking = true;

  const result = document.getElementById('qr-result');

  result.innerHTML = `
    <div style="color:var(--text2);">Verificando QR...</div>
  `;

  try {
    const data = await api('qr_check', { token });
    const p = data.person;

    result.innerHTML = `
      <div class="result-ok">QR válido</div>

      <div class="qr-person">
        ${esc(p.name)}
      </div>

      <div class="qr-detail">
        Lista: ${esc(p.list_name)}<br>
        Estado: ${esc(p.status)}<br>
        Dato: ${esc(p.note || '')}
      </div>

      <button class="btn-action btn-add" style="width:100%;" onclick="confirmQR('${esc(token)}')">
        Confirmar entrada
      </button>

      <button class="btn-action" style="width:100%;margin-top:8px;" onclick="resetScanner()">
        Escanear otro
      </button>
    `;
  } catch (error) {
    result.innerHTML = `
      <div class="result-error">QR inválido</div>

      <div class="qr-detail" style="margin-top:8px;">
        ${esc(error.message)}
      </div>

      <button class="btn-action" style="width:100%;margin-top:8px;" onclick="resetScanner()">
        Escanear otro
      </button>
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
      <div class="result-ok" style="font-size:28px;">
        ✓ Entrada confirmada
      </div>

      <div class="qr-person">
        ${esc(p.name)}
      </div>

      <div class="qr-detail">
        Lista: ${esc(p.list_name)}<br>
        Marcado como ENTRO
      </div>

      <button class="btn-action btn-add" style="width:100%;margin-top:8px;" onclick="resetScanner()">
        Escanear otro
      </button>
    `;
  } catch (error) {
    alert(error.message);
  }
}

function resetScanner() {
  lastToken = null;
  document.getElementById('qr-result').innerHTML = `
    <div style="color:var(--text2);">Esperando QR...</div>
  `;
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