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
<title><?= APP_NAME ?> Admin</title>
<link rel="stylesheet" href="styles.css">

<style>
.admin-wrap{padding:16px;max-width:1000px;margin:auto}
.admin-title{font-family:'Cinzel',serif;font-size:28px;color:var(--gold-2);margin-bottom:12px}
.admin-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:16px}
.admin-card{background:var(--bg2);border:1px solid var(--border);border-radius:18px;padding:14px;margin-bottom:12px}
.admin-num{font-size:26px;font-weight:800;color:var(--gold-2)}
.admin-label{font-size:12px;color:var(--text2);margin-top:4px}
.admin-actions{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.admin-btn{border:0;border-radius:12px;padding:11px 13px;font-weight:700;cursor:pointer;background:linear-gradient(135deg,var(--gold),var(--purple));color:white;text-decoration:none}
.admin-row{display:flex;justify-content:space-between;align-items:center;gap:10px}
.admin-name{font-weight:800;color:var(--text)}
.admin-sub{font-size:12px;color:var(--text2);margin-top:4px}
.qr-img{width:120px;height:120px;background:white;border-radius:10px;padding:6px;margin-top:10px}
.live-dot{font-size:12px;color:var(--green);margin-left:8px}
@media(max-width:650px){.admin-grid{grid-template-columns:1fr}}

.qr-person-card{display:flex;justify-content:space-between;align-items:center;gap:14px;}

.qr-left{display:flex;align-items:center;gap:14px;min-width:0;}

.qr-img{width:120px;height:120px;background:white;border-radius:12px;padding:6px;flex-shrink:0;}

.qr-actions-right{display:flex;flex-direction:column;gap:8px;min-width:140px;}

.qr-actions-right .admin-btn{width:100%;text-align:center;}

@media(max-width:650px){
.qr-person-card{align-items:flex-start;}
.qr-left{flex-direction:column;align-items:flex-start;}

  .qr-actions-right{min-width:120px;}}
</style>
</head>

<body>

<div class="topbar">
  <div class="topbar-title" onclick="location.href='index.php'">
    Panel Admin <span class="live-dot" id="live-status">● live</span>
  </div>
  <button class="topbar-back" onclick="location.href='index.php'">← Menu </button>
</div>

<div class="admin-wrap">

  <div class="admin-title">Administración</div>

  <div class="admin-actions">
    <a class="admin-btn" href="scanner.php">Escanear QR</a>
    <button class="admin-btn" onclick="manualRefreshAdmin()">Actualizar</button>
  </div>

  <div class="admin-grid">
    <div class="admin-card">
      <div class="admin-num" id="total-general">$0</div>
      <div class="admin-label">Total general</div>
    </div>

    <div class="admin-card">
      <div class="admin-num" id="total-kioskito">$0</div>
      <div class="admin-label">Kioskito</div>
    </div>

    <div class="admin-card">
      <div class="admin-num" id="total-puerta">$0</div>
      <div class="admin-label">Puerta</div>
    </div>

    <div class="admin-card">
      <div class="admin-num" id="total-guardarropas">$0</div>
      <div class="admin-label">Guardarropas</div>
    </div>
  </div>

  <div class="admin-card">
    <div class="section-title">Resumen de puerta</div>
    <div id="admin-door"></div>
  </div>

  <div class="admin-card">
    <div class="section-title">Productos más vendidos</div>
    <div id="admin-products"></div>
  </div>

  <div class="admin-card">
    <div class="section-title">QR de personas</div>
    <div id="admin-qr"></div>
  </div>

</div>

<script>
let adminLiveTimer = null;
let adminIsLoading = false;
let adminLastInteraction = 0;

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

function fmt(n) {
  return '$' + Number(n || 0).toLocaleString('es-AR');
}

function markAdminInteraction() {
  adminLastInteraction = Date.now();
}

function qrUrl(token) {
  const link = `${location.origin}/qr.php?token=${encodeURIComponent(token)}`;
  return `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(link)}`;
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function setHTML(id, value) {
  const el = document.getElementById(id);
  if (el) el.innerHTML = value;
}

async function renderAdmin(silent = false) {
  if (adminIsLoading) return;

  adminIsLoading = true;

  const liveStatus = document.getElementById('live-status');

  if (liveStatus && !silent) {
    liveStatus.textContent = '● actualizando';
    liveStatus.style.color = 'var(--gold-2)';
  }

  try {
    const [salesData, doorData, guardarData, qrData] = await Promise.all([
      api('sales_history'),
      api('door_lists'),
      api('guardarropas_list'),
      api('admin_qr_people')
    ]);

    const sales = Array.isArray(salesData.sales) ? salesData.sales : [];
    const lists = Array.isArray(doorData.lists) ? doorData.lists : [];
    const guardarropas = Array.isArray(guardarData.items) ? guardarData.items : [];
    const peopleQR = Array.isArray(qrData.people) ? qrData.people : [];

    const totalKioskito = sales.reduce((acc, sale) => {
      return acc + Number(sale.total || 0);
    }, 0);

    let totalPuerta = 0;
    let totalPersonas = 0;
    let totalEntraron = 0;

    lists.forEach(list => {
      const people = Array.isArray(list.people) ? list.people : [];
      const price = Number(list.pricePerPerson || 500);
      const entered = people.filter(p => p.status === 'entro').length;

      totalPersonas += people.length;
      totalEntraron += entered;
      totalPuerta += entered * price;
    });

    const totalGuardarropas = guardarropas.reduce((acc, item) => {
      return acc + Number(item.precio || 0);
    }, 0);

    const totalGeneral = totalKioskito + totalPuerta + totalGuardarropas;

    setText('total-general', fmt(totalGeneral));
    setText('total-kioskito', fmt(totalKioskito));
    setText('total-puerta', fmt(totalPuerta));
    setText('total-guardarropas', fmt(totalGuardarropas));

    renderDoorSummary(lists, totalPersonas, totalEntraron);
    renderTopProducts(sales);
    renderQRPeople(peopleQR);

    if (liveStatus) {
      liveStatus.textContent = '● live';
      liveStatus.style.color = 'var(--green)';
    }

  } catch (error) {
    if (!silent) alert(error.message);

    if (liveStatus) {
      liveStatus.textContent = '● error';
      liveStatus.style.color = 'var(--red)';
    }
  } finally {
    adminIsLoading = false;
  }
}

function renderDoorSummary(lists, totalPersonas, totalEntraron) {
  const wrap = document.getElementById('admin-door');
  if (!wrap) return;

  wrap.innerHTML = `
    <div class="admin-grid">
      <div>
        <div class="admin-num">${totalPersonas}</div>
        <div class="admin-label">Personas anotadas</div>
      </div>
      <div>
        <div class="admin-num">${totalEntraron}</div>
        <div class="admin-label">Entraron</div>
      </div>
    </div>

    ${lists.map(list => {
      const people = Array.isArray(list.people) ? list.people : [];
      const price = Number(list.pricePerPerson || 500);
      const entered = people.filter(p => p.status === 'entro').length;
      const collected = entered * price;

      return `
        <div class="admin-card">
          <div class="admin-row">
            <div>
              <div class="admin-name">${esc(list.name)}</div>
              <div class="admin-sub">
                ${entered}/${people.length} entraron · ${fmt(price)} c/u
              </div>
            </div>
            <div class="list-badge badge-green">${fmt(collected)}</div>
          </div>
        </div>
      `;
    }).join('')}
  `;
}

function renderTopProducts(sales) {
  const wrap = document.getElementById('admin-products');
  if (!wrap) return;

  const map = {};

  sales.forEach(sale => {
    const items = Array.isArray(sale.items) ? sale.items : [];

    items.forEach(item => {
      const name = item.name || 'Producto';

      if (!map[name]) {
        map[name] = { name, qty: 0, total: 0 };
      }

      map[name].qty += Number(item.qty || 0);
      map[name].total += Number(item.subtotal || 0);
    });
  });

  const products = Object.values(map)
    .sort((a, b) => b.total - a.total)
    .slice(0, 10);

  if (!products.length) {
    wrap.innerHTML = `<div style="color:var(--text2);">Sin ventas registradas.</div>`;
    return;
  }

  wrap.innerHTML = products.map(p => `
    <div class="admin-card">
      <div class="admin-row">
        <div>
          <div class="admin-name">${esc(p.name)}</div>
          <div class="admin-sub">Cantidad vendida: ${p.qty}</div>
        </div>
        <div class="list-badge badge-green">${fmt(p.total)}</div>
      </div>
    </div>
  `).join('');
}

function renderQRPeople(people) {
  const wrap = document.getElementById('admin-qr');
  if (!wrap) return;

  if (!people.length) {
    wrap.innerHTML = `<div style="color:var(--text2);">No hay personas cargadas.</div>`;
    return;
  }

  wrap.innerHTML = people.map(p => `
    <div class="admin-card">
      <div class="qr-person-card">

        <div class="qr-left">
          ${p.qr_token ? `
            <img class="qr-img" src="${qrUrl(p.qr_token)}" alt="QR de ${esc(p.name)}">
          ` : `
            <div class="qr-img" style="display:grid;place-items:center;color:#333;font-weight:800;">
              Sin QR
            </div>
          `}

          <div>
            <div class="admin-name">${esc(p.name)}</div>
            <div class="admin-sub">
              Lista: ${esc(p.list_name)} · ${esc(p.owner_name || '')}<br>
              Estado: ${esc(p.status)}<br>
              QR: ${
                p.qr_used_at
                  ? 'Usado'
                  : Number(p.qr_enabled)
                    ? 'Activo'
                    : 'Sin QR'
              }
            </div>
          </div>
        </div>

        <div class="qr-actions-right">
          <button class="admin-btn" onclick="markAdminInteraction(); generateQR(${Number(p.id)})">
            ${p.qr_token ? 'Regenerar QR' : 'Generar QR'}
          </button>

          ${p.qr_token ? `
            <button class="admin-btn" onclick="markAdminInteraction(); copyQR('${esc(p.qr_token)}')">
              Copiar link
            </button>
          ` : ''}
        </div>

      </div>
    </div>
  `).join('');
}

async function generateQR(personId) {
  try {
    await api('qr_generate', { personId });
    await renderAdmin();
  } catch (error) {
    alert(error.message);
  }
}

async function copyQR(token) {
  const link = `${location.origin}/qr.php?token=${token}`;

  try {
    await navigator.clipboard.writeText(link);
    alert('Link copiado');
  } catch (error) {
    prompt('Copiá este link:', link);
  }
}

async function manualRefreshAdmin() {
  markAdminInteraction();
  await renderAdmin();
}

function startLiveAdmin() {
  if (adminLiveTimer) {
    clearInterval(adminLiveTimer);
  }

  adminLiveTimer = setInterval(async () => {
    const recentlyInteracted = Date.now() - adminLastInteraction < 2500;
    const active = document.activeElement;
    const typing = active && active.matches && active.matches('input, textarea, select');

    if (recentlyInteracted || typing || adminIsLoading) return;

    await renderAdmin(true);
  }, 3000);
}

window.addEventListener('load', async () => {
  await renderAdmin();
  startLiveAdmin();
});
</script>

</body>
</html>