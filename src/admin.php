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
.admin-wrap {
  padding: 16px;
  max-width: 1000px;
  margin: auto;
}

.admin-title {
  font-family: 'Cinzel', serif;
  font-size: 28px;
  color: var(--gold-2);
  margin-bottom: 12px;
}

.admin-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.admin-btn {
  border: 0;
  border-radius: 12px;
  padding: 11px 13px;
  font-weight: 700;
  cursor: pointer;
  background: linear-gradient(135deg, var(--gold), var(--purple));
  color: white;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.admin-btn.secondary {
  background: rgba(255,255,255,.08);
  border: 1px solid var(--border);
  color: var(--text);
}

.admin-night-counters {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  margin-bottom: 16px;
}

.admin-counter-card {
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.admin-counter-info {
  min-width: 0;
}

.admin-num {
  font-size: 26px;
  font-weight: 800;
  color: var(--gold-2);
  white-space: nowrap;
}

.admin-label {
  font-size: 12px;
  color: var(--text2);
  margin-top: 4px;
}

.admin-card {
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 14px;
  margin-bottom: 12px;
}

.admin-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}

.admin-name {
  font-weight: 800;
  color: var(--text);
}

.admin-sub {
  font-size: 12px;
  color: var(--text2);
  margin-top: 4px;
}

.live-dot {
  font-size: 12px;
  color: var(--green);
  margin-left: 8px;
}

.admin-links-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.admin-link-card {
  background: rgba(255,255,255,.04);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 13px;
  text-decoration: none;
  color: var(--text);
  display: block;
}

.admin-link-title {
  color: var(--gold-2);
  font-weight: 900;
  font-size: 16px;
}

.admin-link-sub {
  margin-top: 5px;
  color: var(--text2);
  font-size: 12px;
  line-height: 1.35;
}

.door-counter-stack {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  margin-bottom: 12px;
}

.door-counter-item {
  background: rgba(255,255,255,.04);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 12px;
}

.qr-person-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
}

.qr-left {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}

.qr-img {
  width: 120px;
  height: 120px;
  background: white;
  border-radius: 12px;
  padding: 6px;
  flex-shrink: 0;
}

.qr-actions-right {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 140px;
}

.qr-actions-right .admin-btn {
  width: 100%;
  text-align: center;
}

@media(max-width:650px) {
  .admin-links-grid {
    grid-template-columns: 1fr;
  }

  .admin-counter-card {
    align-items: flex-start;
    flex-direction: column;
  }

  .qr-person-card {
    align-items: flex-start;
    flex-direction: column;
  }

  .qr-left {
    flex-direction: column;
    align-items: flex-start;
  }

  .qr-actions-right {
    min-width: 100%;
    width: 100%;
  }
}
</style>
</head>

<body>

<div class="topbar">
  <div class="topbar-title" onclick="location.href='index.php'">
    Panel Admin <span class="live-dot" id="live-status">● live</span>
  </div>
  <button class="topbar-back" onclick="location.href='index.php'">← Menu</button>
</div>

<div class="admin-wrap">

  <div class="admin-title">Administración</div>

  <div class="admin-card">
    <div class="section-title">Gestión del comercio</div>

    <div class="admin-links-grid">
      <a class="admin-link-card" href="usuarios_admin.php">
        <div class="admin-link-title">Usuarios y roles</div>
        <div class="admin-link-sub">Crear usuarios, cambiar roles y actualizar contraseñas.</div>
      </a>

      <a class="admin-link-card" href="productos_admin.php">
        <div class="admin-link-title">Productos de la Base de datos</div>
        <div class="admin-link-sub">Añadir productos, modificar precios, categorías y descripciones.</div>
      </a>

      <a class="admin-link-card" href="menu.php" target="_blank">
        <div class="admin-link-title">Menú público</div>
        <div class="admin-link-sub">Vista que ven los clientes al escanear el QR.</div>
      </a>

      <a class="admin-link-card" href="menu_qr.php" target="_blank">
        <div class="admin-link-title">QR fijo del menú</div>
        <div class="admin-link-sub">QR para imprimir o dejar fijo en barra/mesa.</div>
      </a>
    </div>
  </div>

  <div class="admin-card">
    <div class="section-title">Contadores de la noche</div>

    <div class="admin-night-counters">
      <div class="admin-counter-card">
        <div class="admin-counter-info">
          <div class="admin-name">Total general</div>
          <div class="admin-label">Kioskito + puerta + guardarropas</div>
        </div>
        <div class="admin-num" id="total-general">$0</div>
      </div>

      <div class="admin-counter-card">
        <div class="admin-counter-info">
          <div class="admin-name">Kioskito</div>
          <div class="admin-label">Ventas registradas</div>
        </div>
        <div class="admin-num" id="total-kioskito">$0</div>
      </div>

      <div class="admin-counter-card">
        <div class="admin-counter-info">
          <div class="admin-name">Puerta</div>
          <div class="admin-label">Presencias cobradas</div>
        </div>
        <div class="admin-num" id="total-puerta">$0</div>
      </div>

      <div class="admin-counter-card">
        <div class="admin-counter-info">
          <div class="admin-name">Guardarropas</div>
          <div class="admin-label">Prendas cargadas</div>
        </div>
        <div class="admin-num" id="total-guardarropas">$0</div>
      </div>
    </div>
  </div>

  <div class="admin-card">
    <div class="section-title">Resumen de puerta a pagar (publicas) </div>
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
    <div class="door-counter-stack">
      <div class="door-counter-item">
        <div class="admin-num">${totalPersonas}</div>
        <div class="admin-label">Personas anotadas</div>
      </div>

      <div class="door-counter-item">
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