/* =========================
   UTILS
========================= */
function esc(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// Read environment-like variables exposed to the frontend.
// It supports window.__ENV__ or window.ENV as injection points.
function getenv(key, defaultValue = '') {
  try {
    const env = window.__ENV__ || window.ENV || {};
    const val = env && Object.prototype.hasOwnProperty.call(env, key) ? env[key] : undefined;
    return val != null ? String(val) : defaultValue;
  } catch {
    return defaultValue;
  }
}

function fmt(n) {
  if (!n && n !== 0) return '$0';
  return '$' + Number(n).toLocaleString('es-AR');
}

function normalizeText(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

/* =========================
   SISTEMA DE RESPALDO / ERRORES
========================= */
/* =========================
   RESPALDO DE ERRORES
========================= */
function appErrorBox(title, error = null, targetId = null) {
  const message = error?.message || String(error || 'Error desconocido');

  console.error('[DIVINE APP ERROR]', title, error);

  const html = `
    <div style="
      margin:14px;
      padding:16px;
      border-radius:16px;
      border:1px solid rgba(255,80,80,.4);
      background:rgba(255,80,80,.10);
      color:var(--text);
      font-family:Arial,sans-serif;
    ">
      <div style="font-size:16px;font-weight:800;color:#ff7070;margin-bottom:8px;">
        ⚠ Se rompió esta sección
      </div>

      <div style="font-size:14px;margin-bottom:8px;">
        ${esc(title)}
      </div>

      <pre style="
        white-space:pre-wrap;
        word-break:break-word;
        background:rgba(0,0,0,.25);
        padding:10px;
        border-radius:12px;
        color:#ffd1d1;
        font-size:12px;
        max-height:180px;
        overflow:auto;
      ">${esc(message)}</pre>

      <button onclick="location.reload()" style="
        width:100%;
        margin-top:10px;
        padding:12px;
        border:0;
        border-radius:12px;
        background:#ff7070;
        color:white;
        font-weight:800;
      ">
        Recargar
      </button>
    </div>
  `;

  if (targetId) {
    const target = document.getElementById(targetId);
    if (target) {
      target.innerHTML = html;
      return;
    }
  }

  const activePage = document.querySelector('.page.active');
  if (activePage) {
    activePage.insertAdjacentHTML('afterbegin', html);
  } else {
    document.body.insertAdjacentHTML('afterbegin', html);
  }
}


let APP_BROKEN = false;

function getErrorMessage(error) {
  if (!error) return 'Error desconocido';
  if (typeof error === 'string') return error;
  if (error.message) return error.message;
  try {
    return JSON.stringify(error);
  } catch {
    return 'Error desconocido';
  }
}

function showAppBroken(title, error = null, targetId = null) {
  APP_BROKEN = true;

  const message = getErrorMessage(error);

  console.error('[DIVINE APP ERROR]', title, error);

  const html = `
    <div style="
      margin:14px;
      padding:16px;
      border-radius:16px;
      border:1px solid rgba(255,80,80,.35);
      background:rgba(255,80,80,.08);
      color:var(--text);
      font-family:Arial,sans-serif;
    ">
      <div style="font-weight:800;color:#ff7070;font-size:16px;margin-bottom:6px;">
        ⚠ Se rompió esta sección
      </div>

      <div style="font-size:14px;color:var(--text);margin-bottom:8px;">
        ${esc(title)}
      </div>

      <pre style="
        white-space:pre-wrap;
        word-break:break-word;
        margin:0;
        padding:10px;
        border-radius:12px;
        background:rgba(0,0,0,.25);
        color:#ffd0d0;
        font-size:12px;
        max-height:180px;
        overflow:auto;
      ">${esc(message)}</pre>

      <button
        onclick="location.reload()"
        style="
          margin-top:12px;
          width:100%;
          padding:12px;
          border:0;
          border-radius:12px;
          background:#ff7070;
          color:#fff;
          font-weight:800;
        "
      >
        Recargar app
      </button>
    </div>
  `;

  if (targetId) {
    const target = document.getElementById(targetId);
    if (target) {
      target.innerHTML = html;
      return;
    }
  }

  const activePage = document.querySelector('.page.active');
  if (activePage) {
    activePage.insertAdjacentHTML('afterbegin', html);
    return;
  }

  document.body.insertAdjacentHTML('afterbegin', html);
}

function safeRun(label, fn, targetId = null) {
  try {
    return fn();
  } catch (error) {
    showAppBroken(label, error, targetId);
    return null;
  }
}

async function safeRunAsync(label, fn, targetId = null) {
  try {
    return await fn();
  } catch (error) {
    showAppBroken(label, error, targetId);
    return null;
  }
}

window.addEventListener('error', (event) => {
  showAppBroken('Error general de JavaScript', event.error || event.message);
});

window.addEventListener('unhandledrejection', (event) => {
  showAppBroken('Error async no controlado', event.reason || 'Promesa rechazada');
});

async function api(action, data = null) {
  const options = { credentials: 'same-origin' };

  if (data !== null) {
    options.method = 'POST';
    options.headers = { 'Content-Type': 'application/json' };
    options.body = JSON.stringify(data);
  }

  let res;
  let text;

  try {
    res = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);
    text = await res.text();
  } catch (error) {
    throw new Error(`No se pudo conectar con api.php en la acción "${action}". ${getErrorMessage(error)}`);
  }

  let json;

  try {
    json = JSON.parse(text);
  } catch {
    throw new Error(
      `api.php no devolvió JSON válido en la acción "${action}".\n\n` +
      `HTTP: ${res.status}\n\n` +
      `Respuesta recibida:\n${text.slice(0, 800)}`
    );
  }

  if (!res.ok || !json.ok) {
    throw new Error(
      `Error en api.php?action=${action}\n\n` +
      `${json.error || 'Error del servidor'}\n\n` +
      `HTTP: ${res.status}`
    );
  }

  return json;
}

function showError(error, targetId = null) {
  showAppBroken('Error de la app', error, targetId);
}

/* =========================
   ANTI DOBLE ENVÍO
========================= */

const ACTION_LOCKS = new Map();

function makeActionKey(action, payload = {}) {
  try {
    return `${action}:${JSON.stringify(payload)}`;
  } catch {
    return `${action}:${Date.now()}`;
  }
}

function isActionLocked(key) {
  return ACTION_LOCKS.has(key);
}

function lockAction(key, ms = 1200) {
  ACTION_LOCKS.set(key, true);

  setTimeout(() => {
    ACTION_LOCKS.delete(key);
  }, ms);
}

async function apiLocked(action, payload = {}, customKey = '') {
  const key = customKey || makeActionKey(action, payload);

  if (isActionLocked(key)) {
    console.warn('[DIVINE APP] Doble envío bloqueado:', key);
    return null;
  }

  lockAction(key);

  return await api(action, payload);
}

function setButtonLoading(btn, loading, textLoading = 'Procesando...', textNormal = null) {
  if (!btn) return;

  if (loading) {
    btn.dataset.originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = textLoading;
    return;
  }

  btn.disabled = false;
  btn.textContent = textNormal || btn.dataset.originalText || btn.textContent;
}

/* =========================
   NAVIGATION
========================= */
function goTo(page) {
  safeRun(`No se pudo abrir la página "${page}"`, () => {
    document.querySelectorAll(".page").forEach(p => p.classList.remove("active"));

    const target = document.getElementById("page-" + page);

    if (!target) {
      throw new Error(`No existe el contenedor: page-${page}`);
    }

    target.classList.add("active");

    if (page === "puerta") {
      safeRunAsync('No se pudo cargar Puerta', () => renderPuerta(true), 'p-lists');
    }

    if (page === "kioskito") {
      safeRunAsync('No se pudo cargar Kioskito', renderKioskito, 'k-categories');
      safeRunAsync('No se pudo cargar historial de ventas', renderSalesHistory, 'sales-history');
      safeRunAsync('No se pudo cargar resumen de caja', renderKioskoSummary, 'kiosko-summary');


      setTimeout(() => {
        safeRun('No se pudo instalar Guardarropas', instalarGuardarropas);
        safeRunAsync('No se pudo cargar Guardarropas', renderGuardarropas, 'gr-list');
      }, 100);
    }

    startLiveApp();
    window.scrollTo(0, 0);
  });
}/* ============================================================
   🛒 KIOSKITO — PRODUCTOS, CARRITO, PAGOS Y VENTAS
   ============================================================ */

/*
  Reglas importantes:
  - El precio original del producto NO se modifica en la base de datos.
  - Si el método de pago es "tarjeta", se aplica 10% de recargo.
  - El recargo se aplica solo a la venta actual.
  - El total mostrado, confirmado, impreso y enviado a la API usa el precio final.
*/

const CARD_SURCHARGE_PERCENT = 10;

let products = [];
let cart = {};
let editingProductId = null;
let editProductsMode = false;
let collapsedProductCats = {};

let selectedPaymentMethod = 'efectivo';
let saleIsProcessing = false;

/* ------------------------------------------------------------
   💰 Helpers de pago y precios
   ------------------------------------------------------------ */

function paymentLabel(method) {
  if (method === 'transferencia') return 'Transferencia';
  if (method === 'tarjeta') return 'Tarjeta';
  if (method === 'regalo') return 'Regalo';
  return 'Efectivo';
}

function hasCardSurcharge() {
  return selectedPaymentMethod === 'tarjeta';
}

function getFinalUnitPrice(basePrice) {
  const price = Number(basePrice || 0);

  if (hasCardSurcharge()) {
    return Math.round(price * (1 + CARD_SURCHARGE_PERCENT / 100));
  }

  return price;
}

function getLinePrices(basePrice, qty) {
  const quantity = Number(qty || 0);
  const baseUnit = Number(basePrice || 0);
  const finalUnit = getFinalUnitPrice(baseUnit);
  const surchargeUnit = finalUnit - baseUnit;

  return {
    quantity,
    baseUnit,
    finalUnit,
    surchargeUnit,
    baseSubtotal: baseUnit * quantity,
    surchargeSubtotal: surchargeUnit * quantity,
    subtotal: finalUnit * quantity
  };
}

function selectPaymentMethod(method) {
  selectedPaymentMethod = method || 'efectivo';

  document.querySelectorAll('.payment-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.payment === selectedPaymentMethod);
  });

  renderCart();
}

/* ------------------------------------------------------------
   🧾 Estado visual del botón Confirmar venta
   ------------------------------------------------------------ */

function makeClientSaleId() {
  const random = Math.random().toString(16).slice(2);
  return `sale_${Date.now()}_${random}`;
}

function setSaleProcessing(isProcessing) {
  saleIsProcessing = isProcessing;

  const btn = document.getElementById('confirm-sale-btn');
  if (!btn) return;

  btn.disabled = isProcessing;
  btn.textContent = isProcessing ? 'Confirmando...' : '✓ Confirmar venta';
}

/* ------------------------------------------------------------
   ✏️ Modo edición de productos
   ------------------------------------------------------------ */

function toggleEditProducts() {
  editProductsMode = !editProductsMode;
  renderKioskito();
}

function toggleProductCat(cat) {
  collapsedProductCats[cat] = !collapsedProductCats[cat];
  renderKioskito();
}

/* ------------------------------------------------------------
   📦 Render de productos
   ------------------------------------------------------------ */

async function renderKioskito() {
  const wrap = document.getElementById('k-categories');
  if (!wrap) return;

  try {
    const data = await api('products_list');
    products = data.products || [];

    const grouped = {};

    products.forEach(product => {
      const cat = product.cat || 'Otros';

      if (!grouped[cat]) {
        grouped[cat] = [];
      }

      grouped[cat].push(product);
    });

    if (!Object.keys(grouped).length) {
      wrap.innerHTML = `
        <div class="list-card">
          <div style="padding:18px;color:var(--text2);">
            No hay productos cargados.
          </div>
        </div>
      `;

      renderCart();
      return;
    }

    wrap.innerHTML = Object.keys(grouped).map(cat => {
      const collapsed = !!collapsedProductCats[cat];

      return `
        <div class="section ${collapsed ? 'collapsed-products' : ''}">

          <div
            class="section-title"
            onclick="toggleProductCat('${esc(cat)}')"
          >
            <span>${esc(cat)}</span>
            <span class="section-toggle">${collapsed ? '▸' : '▾'}</span>
          </div>

          <div class="product-grid">

            ${grouped[cat].map(product => `
              <div
                class="pos-product-card"
                onclick="addToCart(${Number(product.id)}, event)"
              >

                ${editProductsMode ? `
                  <button
                    class="btn-edit-product"
                    onclick="event.stopPropagation(); openEditProduct(${Number(product.id)})"
                  >
                    ✎
                  </button>
                ` : ''}

                <div class="pos-product-name">
                  ${esc(product.name)}
                </div>

                <div class="pos-product-price">
                  ${fmt(product.price)}
                </div>

                ${product.sub
                  ? `<div class="product-sub">${esc(product.sub)}</div>`
                  : ''
                }

              </div>
            `).join('')}

          </div>

        </div>
      `;
    }).join('');

    renderCart();

  } catch (error) {
    showError(error);
  }
}

/* ------------------------------------------------------------
   🛒 Carrito
   ------------------------------------------------------------ */

function addToCart(id, ev = null) {
  cart[id] = (cart[id] || 0) + 1;

  const card = ev?.currentTarget || window.event?.currentTarget || null;

  if (card) {
    card.classList.remove('wave-active');
    void card.offsetWidth;
    card.classList.add('wave-active');
  }

  renderCart();
}

function removeFromCart(id) {
  if (!cart[id]) return;

  cart[id]--;

  if (cart[id] <= 0) {
    delete cart[id];
  }

  renderCart();
}

function renderCart() {
  const saleDetail = document.getElementById('sale-detail');
  const totalEl = document.getElementById('k-total');
  const ids = Object.keys(cart);

  if (!ids.length) {
    if (saleDetail) {
      saleDetail.innerHTML = `
        <div style="padding:14px 16px;font-size:14px;color:var(--text2);">
          Sin productos agregados.
        </div>
      `;
    }

    if (totalEl) {
      totalEl.textContent = '$0';
    }

    return;
  }

  let total = 0;

  const rows = ids.map(id => {
    const product = products.find(p => Number(p.id) === Number(id));
    if (!product) return '';

    const qty = Number(cart[id] || 0);
    const prices = getLinePrices(product.price, qty);

    total += prices.subtotal;

    const cardSurchargeText = hasCardSurcharge()
      ? `
        <div class="cart-item-detail" style="color:var(--gold-2);">
          Tarjeta +${CARD_SURCHARGE_PERCENT}%:
          ${fmt(prices.baseUnit)} → ${fmt(prices.finalUnit)} c/u
        </div>
      `
      : '';

    return `
      <div class="cart-item">
        <div class="cart-item-info">

          <div class="cart-item-name">
            ${esc(product.name)} - ${fmt(prices.finalUnit)} x${qty}
          </div>

          ${cardSurchargeText}

          <div class="cart-item-detail">
            Subtotal: ${fmt(prices.subtotal)}
          </div>

        </div>

        <button
          class="cart-minus"
          onclick="removeFromCart(${Number(product.id)})"
        >
          −
        </button>
      </div>
    `;
  }).join('');

  if (saleDetail) {
    saleDetail.innerHTML = rows;
  }

  if (totalEl) {
    totalEl.textContent = fmt(total);
  }
}

/* ------------------------------------------------------------
   ✅ Confirmar venta
   ------------------------------------------------------------ */

async function confirmCurrentSale() {
  if (saleIsProcessing) {
    return;
  }

  const ids = Object.keys(cart);

  if (!ids.length) {
    alert('No hay productos en el carrito');
    return;
  }

  let total = 0;
  const lines = [];

  ids.forEach(id => {
    const product = products.find(p => Number(p.id) === Number(id));
    if (!product) return;

    const qty = Number(cart[id] || 0);
    const prices = getLinePrices(product.price, qty);

    total += prices.subtotal;

    lines.push({
      id: Number(product.id),
      name: product.name,
      qty,

      // Precio original del producto.
      base_price: prices.baseUnit,

      // Precio final usado en esta venta.
      price: prices.finalUnit,

      // Datos del recargo.
      surcharge_percent: hasCardSurcharge() ? CARD_SURCHARGE_PERCENT : 0,
      surcharge_unit: prices.surchargeUnit,
      surcharge_total: prices.surchargeSubtotal,

      subtotal: prices.subtotal
    });
  });

  if (!lines.length || total <= 0) {
    alert('No se pudo calcular la venta. Revisá los productos cargados.');
    return;
  }

  const paymentText = paymentLabel(selectedPaymentMethod);

  const surchargeText = hasCardSurcharge()
    ? `\nRecargo tarjeta: +${CARD_SURCHARGE_PERCENT}%`
    : '';

  const detailText = lines.map(line => {
    const recargo = line.surcharge_percent
      ? ` (${fmt(line.base_price)} + ${CARD_SURCHARGE_PERCENT}%)`
      : '';

    return `• ${line.name} x${line.qty} = ${fmt(line.subtotal)}${recargo}`;
  }).join('\n');

  const ok = confirm(
    `Confirmar venta por ${fmt(total)}?\n` +
    `${surchargeText}\n\n` +
    `Productos:\n${detailText}\n\n` +
    `Método de pago: ${paymentText}`
  );

  if (!ok) {
    return;
  }

  const clientSaleId = makeClientSaleId();

  setSaleProcessing(true);

  try {
    const data = await api('sale_register', {
      items: lines,
      total,

      // Mando ambos nombres para compatibilidad con tu backend.
      paymentMethod: selectedPaymentMethod,
      payment_method: selectedPaymentMethod,

      clientSaleId,
      client_sale_id: clientSaleId
    });

    if (data.duplicate) {
      alert('Esta venta ya había sido registrada. No se duplicó.');
    }

    if (confirm('¿Imprimir ticket?')) {
      printTicket(lines, total, paymentText);
    }

    cart = {};
    renderCart();

    await renderSalesHistory();
    await renderKioskoSummary();

  } catch (error) {
    showError(error);
  } finally {
    setSaleProcessing(false);
  }
}

/* ------------------------------------------------------------
   🖨️ Ticket
   ------------------------------------------------------------ */

function printTicket(lines, total, paymentMethod = '') {
  const now = new Date();

  const fecha = now.toLocaleDateString('es-AR', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit'
  });

  const hora = now.toLocaleTimeString('es-AR', {
    hour: '2-digit',
    minute: '2-digit'
  });

  const ticketNumber = Math.floor(Date.now() / 1000).toString().slice(-6);

  const shortLine = '--------------------------------';

  const itemsHtml = lines.map(item => {
    const name = String(item.name || 'Producto');
    const qty = Number(item.qty || 0);
    const unitPrice = Number(item.price || 0);
    const subtotal = Number(item.subtotal || 0);

    const surchargeText = Number(item.surcharge_percent || 0) > 0
      ? `<div class="muted">Tarjeta +${Number(item.surcharge_percent)}%</div>`
      : '';

    return `
      <div class="item">
        <div class="item-name">${esc(name)}</div>

        <div class="row">
          <span>${qty} x ${fmt(unitPrice)}</span>
          <strong>${fmt(subtotal)}</strong>
        </div>

        ${surchargeText}
      </div>
    `;
  }).join('');

  const html = `
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">

  <title>Ticket Del Kiosko</title>

  <style>
    @page {
      size: 80mm auto;
      margin: 0;
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      margin: 0;
      padding: 0;
      background: #fff;
      color: #000;
      font-family: Arial, "Courier New", monospace;
      font-size: 12px;
      line-height: 1.25;
    }

    body {
      width: 80mm;
    }

    .ticket {
      width: 72mm;
      max-width: 72mm;
      margin: 0 auto;
      padding: 4mm 3mm 3mm;
    }

    .center {
      text-align: center;
    }

    .title {
      font-size: 17px;
      font-weight: 900;
      letter-spacing: 0.5px;
      margin-bottom: 2px;
      text-transform: uppercase;
    }

    .sub {
      font-size: 11px;
      margin-bottom: 2px;
    }

    .line {
      font-family: "Courier New", monospace;
      font-size: 11px;
      white-space: pre;
      margin: 5px 0;
    }

    .row {
      display: flex;
      justify-content: space-between;
      gap: 6px;
      align-items: flex-start;
    }

    .item {
      margin: 5px 0 6px;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .item-name {
      font-size: 12px;
      font-weight: 800;
      margin-bottom: 2px;
      word-break: break-word;
    }

    .muted {
      font-size: 10px;
      opacity: 0.85;
      margin-top: 1px;
    }

    .total {
      font-size: 17px;
      font-weight: 900;
      margin-top: 4px;
    }

    .thanks {
      margin-top: 8px;
      font-size: 11px;
      text-align: center;
    }

    @media print {
      html,
      body {
        width: 80mm;
      }

      .ticket {
        width: 72mm;
        max-width: 72mm;
      }
    }
  </style>
</head>

<body>
  <div class="ticket">

    <div class="center">
      <div class="title">KIOSCO</div>
      <div class="sub">${fecha} ${hora}</div>
      <div class="sub">Ticket #${ticketNumber}</div>
    </div>

    <div class="line">${shortLine}</div>

    ${itemsHtml}

    <div class="line">${shortLine}</div>

    <div class="row">
      <span>Método</span>
      <strong>${esc(paymentMethod)}</strong>
    </div>

    <div class="row total">
      <span>Total</span>
      <strong>${fmt(total)}</strong>
    </div>

    <div class="line">${shortLine}</div>

    <div class="thanks">
      Gracias por tu compra
    </div>

  </div>

  <script>
    window.onload = function () {
      setTimeout(function () {
        window.print();
        setTimeout(function () {
          window.close();
        }, 500);
      }, 250);
    };
  </script>
</body>
</html>
  `;

  const win = window.open('', 'ticket_print', 'width=360,height=700');

  if (!win) {
    alert('El navegador bloqueó la ventana de impresión.');
    return;
  }

  win.document.open();
  win.document.write(html);
  win.document.close();
}


/* ------------------------------------------------------------
   📜 Historial de ventas
   ------------------------------------------------------------ */

async function renderSalesHistory() {
  const wrap = document.getElementById('sales-history');
  if (!wrap) return;

  try {
    const data = await api('sales_history');
    const sales = data.sales || [];

    if (!sales.length) {
      wrap.innerHTML = `
        <div class="section">
          <div class="section-title">Historial de ventas</div>
          <div style="padding:14px 16px;color:var(--text2);font-size:14px;">
            Sin ventas registradas.
          </div>
        </div>
      `;
      return;
    }

    const totalGlobal = sales.reduce((acc, sale) => acc + Number(sale.total || 0), 0);

    wrap.innerHTML = `
      <div class="section">
        <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;">
          <span>Historial de ventas</span>
          <span style="font-size:13px;color:var(--text2);">
            Total: ${fmt(totalGlobal)}
          </span>
        </div>

        ${sales.map(sale => {
          const items = Array.isArray(sale.items) ? sale.items : [];

          const hora = new Date(sale.created_at).toLocaleTimeString('es-AR', {
            hour: '2-digit',
            minute: '2-digit'
          });

          return `
            <div class="list-card" style="margin:0 0 8px;">
              <div class="list-header">

                <div class="list-name-txt">
                  <span style="font-size:13px;font-weight:500;">
                    ${hora}
                  </span>

                  <span style="font-size:12px;color:var(--text2);display:block;margin-top:2px;">
                    ${items.map(item => `${esc(item.name)} ×${Number(item.qty)}`).join(' · ')}
                  </span>

                  <span style="font-size:11px;color:var(--gold-2);display:block;margin-top:4px;">
                    ${esc(sale.payment_label || paymentLabel(sale.payment_method))}
                  </span>
                </div>

                <div class="list-badge badge-green">
                  ${fmt(sale.total)}
                </div>

              </div>
            </div>
          `;
        }).join('')}
      </div>
    `;
  } catch (error) {
    console.error('Error cargando historial:', error);
  }
}

/* ------------------------------------------------------------
   📊 Resumen / cierre de caja
   ------------------------------------------------------------ */

async function renderKioskoSummary() {
  const wrap = document.getElementById('kiosko-summary');
  if (!wrap) return;

  try {
    const data = await api('kiosko_summary');
    const summary = data.summary || {};

    const byPayment = summary.by_payment || {};
    const productsSummary = Array.isArray(summary.products) ? summary.products : [];

    wrap.innerHTML = `
      <div class="section">
        <div class="section-title">Cierre de caja actual</div>

        <div class="list-card">
          <div style="padding:14px 16px;">

            <div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:10px;">
              <span style="color:var(--text2);">Ventas realizadas</span>
              <strong>${Number(summary.sales_count || 0)}</strong>
            </div>

            <div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:10px;">
              <span style="color:var(--text2);">Total vendido</span>
              <strong style="color:var(--gold-2);">
                ${fmt(summary.total_amount || 0)}
              </strong>
            </div>

            <div style="border-top:1px solid rgba(240,212,141,.08);padding-top:10px;margin-top:10px;">

              <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span>💵 Efectivo</span>
                <strong>${fmt(byPayment.efectivo || 0)}</strong>
              </div>

              <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span>📲 Transferencia</span>
                <strong>${fmt(byPayment.transferencia || 0)}</strong>
              </div>

              <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span>💳 Tarjeta</span>
                <strong>${fmt(byPayment.tarjeta || 0)}</strong>
              </div>

              <div style="display:flex;justify-content:space-between;">
                <span>🎁 Regalo</span>
                <strong>${fmt(byPayment.regalo || 0)}</strong>
              </div>

            </div>

            ${productsSummary.length ? `
              <div style="border-top:1px solid rgba(240,212,141,.08);padding-top:10px;margin-top:10px;">
                <div style="color:var(--text2);font-size:12px;margin-bottom:8px;">
                  Productos más vendidos
                </div>

                ${productsSummary.slice(0, 5).map(item => `
                  <div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:5px;font-size:13px;">
                    <span>${esc(item.name)} ×${Number(item.qty)}</span>
                    <strong>${fmt(item.subtotal)}</strong>
                  </div>
                `).join('')}
              </div>
            ` : ''}

            <button
              class="btn-action btn-reset"
              style="width:100%;margin-top:14px;"
              onclick="closeKioskoCash()"
            >
              🔒 Cerrar caja
            </button>

          </div>
        </div>
      </div>
    `;
  } catch (error) {
    console.error('Error cargando resumen de caja:', error);
  }
}

async function closeKioskoCash() {
  const ok = confirm('¿Cerrar caja del Kioskito? Se guardará el resumen de ventas actuales.');
  if (!ok) return;

  try {
    const data = await api('kiosko_close');

    alert(data.message || 'Caja cerrada correctamente.');

    await renderKioskoSummary();
    await renderSalesHistory();

  } catch (error) {
    showError(error);
  }
}

/* ------------------------------------------------------------
   ➕ Agregar / editar / eliminar productos
   ------------------------------------------------------------ */

function openAddProduct() {
  editingProductId = null;

  const name = document.getElementById('ap-name');
  const price = document.getElementById('ap-price');
  const cat = document.getElementById('ap-cat');
  const btn = document.getElementById('ap-submit-btn');

  if (name) name.value = '';
  if (price) price.value = '';
  if (cat) cat.value = 'Vasos';
  if (btn) btn.textContent = 'Agregar';

  openModal('modal-add-product');
}

function openEditProduct(id) {
  const product = products.find(p => Number(p.id) === Number(id));
  if (!product) return;

  editingProductId = Number(id);

  const name = document.getElementById('ap-name');
  const price = document.getElementById('ap-price');
  const cat = document.getElementById('ap-cat');
  const btn = document.getElementById('ap-submit-btn');

  if (name) name.value = product.name;
  if (price) price.value = product.price;
  if (cat) cat.value = product.cat || 'Otros';
  if (btn) btn.textContent = 'Guardar';

  openModal('modal-add-product');
}

async function saveProduct() {
  const name = document.getElementById('ap-name')?.value.trim() || '';
  const price = Number(document.getElementById('ap-price')?.value || 0);
  const cat = document.getElementById('ap-cat')?.value || 'Otros';

  if (!name) {
    alert('Ingresá el nombre del producto');
    return;
  }

  try {
    if (editingProductId) {
      await api('product_edit', {
        id: editingProductId,
        name,
        price,
        cat
      });
    } else {
      await api('product_add', {
        name,
        price,
        cat
      });
    }

    closeModal('modal-add-product');
    await renderKioskito();

  } catch (error) {
    showError(error);
  }
}

async function deleteProduct(id) {
  if (!confirm('¿Eliminar este producto?')) return;

  try {
    await api('product_delete', { id });
    await renderKioskito();
  } catch (error) {
    showError(error);
  }
}

/* =========================
   PIN RESET
========================= */
const RESET_PIN = '1234';
let pinValue = '';

function buildPinPad() {
  const grid = document.getElementById('pin-grid');
  if (!grid) return;

  const buttons = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '←', '0', 'OK'];
  grid.innerHTML = buttons.map(b => `
    <button class="btn-pin" onclick="pinPress('${b}')">${b}</button>
  `).join('');
}

function pinRender() {
  const display = document.getElementById('pin-display');
  const err = document.getElementById('pin-err');
  if (!display || !err) return;

  const dots = Array.from({ length: 4 }, (_, i) => i < pinValue.length ? '•' : '·').join('  ');
  display.textContent = dots;
  if (pinValue.length < 4) err.textContent = '';
}

function pinClear() {
  pinValue = '';
  pinRender();
  const err = document.getElementById('pin-err');
  if (err) err.textContent = '';
}

function openPin() {
  pinClear();
  openModal('modal-pin');
}

async function pinPress(val) {
  const err = document.getElementById('pin-err');
  const box = document.querySelector('#modal-pin .modal-box');

  if (val === '←') {
    pinValue = pinValue.slice(0, -1);
    pinRender();
    return;
  }

  if (val === 'OK') {
    if (pinValue !== RESET_PIN) {
      if (err) err.textContent = 'PIN incorrecto';

      if (box) {
        box.classList.remove('shake');
        void box.offsetWidth;
        box.classList.add('shake');
      }

      pinClear();
      return;
    }

    try {
      await api('products_reset', { pin: pinValue });

      closeModal('modal-pin');
      pinClear();

      cart = {};
      renderCart();

      await renderKioskito();
      await renderSalesHistory();
    } catch (error) {
      showError(error);
      pinClear();
    }

    return;
  }

  if (pinValue.length >= 4) return;

  pinValue += val;
  pinRender();
}

document.addEventListener('keydown', (e) => {
  const modal = document.getElementById('modal-pin');

  // Solo funciona si el modal está abierto
  if (!modal || !modal.classList.contains('open')) {
    return;
  }

  if (/^[0-9]$/.test(e.key)) {
    pinPress(e.key);
    return;
  }

  if (e.key === 'Backspace') {
    pinPress('←');
    return;
  }

  if (e.key === 'Enter') {
    pinPress('OK');
  }
});

/* =========================
   PUERTA STATE FROM MYSQL
========================= */
const PERSON_PRICE = 500;
const BIRTHDAY_PERSON_PRICE = 1000;
let doorLists = [];
let openQuickAddListId = null;
let collapsedDoorLists = {};
let lastChangedPersonId = null;
let statusAnimationTimer = null;
let statusAnimationUntil = 0;

let lastDoorSnapshot = '';
let puertaYaRenderizada = false;


function makeDoorSnapshot(lists) {
  return JSON.stringify((lists || []).map(list => ({
    id: Number(list.id),
    userId: Number(list.userId),
    ownerName: list.ownerName || '',
    name: list.name || '',
    isBirthday: !!list.isBirthday,
    pricePerPerson: Number(list.pricePerPerson || 0),
    people: (list.people || []).map(person => ({
      id: Number(person.id),
      listId: Number(person.listId),
      name: person.name || '',
      note: person.note || '',
      status: person.status || '',
      qr_token: person.qr_token || '',
      qr_enabled: Number(person.qr_enabled || 0),
      qr_used_at: person.qr_used_at || ''
    }))
  })));
}

function getListPrice(list) {
  if (!list) return PERSON_PRICE;
  return Number(list.pricePerPerson || (list.isBirthday ? BIRTHDAY_PERSON_PRICE : PERSON_PRICE));
}

function getListStats(list) {
  const people = Array.isArray(list.people) ? list.people : [];
  const entered = people.filter(person => person.status === 'entro').length;
  const left = people.filter(person => person.status === 'se_fue').length;
  const pending = people.filter(person => person.status === 'no_vino').length;
  const collected = entered * getListPrice(list);

  return { entered, left, pending, collected, total: people.length };
}

function listMatchesSearch(list, term) {
  if (!term) return true;
  return normalizeText(list.name).includes(term) || normalizeText(list.ownerName).includes(term);
}

function personMatchesSearch(list, term) {
  if (!term) return true;
  const people = Array.isArray(list.people) ? list.people : [];
  return people.some(person => normalizeText(person.name).includes(term) || normalizeText(person.note).includes(term));
}

function statusLabel(status) {
  if (status === 'entro') return 'Entró';
  if (status === 'se_fue') return 'Se fue';
  return 'No vino';
}

async function renderPuerta(forceRender = false) {
  const wrap = document.getElementById('p-lists');
  if (!wrap) return;

  try {
    if (!puertaYaRenderizada && !doorLists.length) {
      wrap.innerHTML = `
        <div class="list-card">
          <div style="padding:18px 16px;color:var(--text2);">
            Cargando listas...
          </div>
        </div>
      `;
    }

    const data = await api('door_lists');
    const newLists = Array.isArray(data.lists) ? data.lists : [];
    const newSnapshot = makeDoorSnapshot(newLists);

    if (!forceRender && puertaYaRenderizada && newSnapshot === lastDoorSnapshot) {
      return;
    }

    doorLists = newLists;
    lastDoorSnapshot = newSnapshot;
    puertaYaRenderizada = true;

    drawPuerta();

  } catch (error) {
    showError(error, 'p-lists');
  }
}

function drawPuerta() {
  const wrap = document.getElementById('p-lists');
  const searchInput = document.getElementById('list-search');
  const personSearchInput = document.getElementById('person-search');

  if (!wrap) return;

  const user = window.DIVINE_USER || {};
  const role = normalizeText(user.role || '');

  const isAdmin = role === 'admin';
  const isPuerta = role === 'puerta';

  // Admin y puerta pueden ver/controlar todas las listas.
  const canManageDoor = isAdmin || isPuerta;

  // Usuario común solo busca dentro de sus listas.
  const listTerm = canManageDoor ? normalizeText(searchInput ? searchInput.value : '') : '';
  const personTerm = normalizeText(personSearchInput ? personSearchInput.value : '');

  const visibleLists = doorLists.filter(list =>
    listMatchesSearch(list, listTerm) &&
    personMatchesSearch(list, personTerm)
  );

  if (!visibleLists.length) {
    wrap.innerHTML = `
      <div class="list-card">
        <div style="padding:18px 16px;color:var(--text2);">
          ${(listTerm || personTerm) ? 'No se encontraron resultados con esa búsqueda.' : 'Todavía no hay listas creadas.'}
        </div>
      </div>
    `;
    return;
  }

  wrap.innerHTML = visibleLists.map(list => {
    const stats = getListStats(list);
    const pricePerPerson = getListPrice(list);
    const collapsed = personTerm ? false : !!collapsedDoorLists[list.id];

    const owner = canManageDoor && list.ownerName
      ? `<span style="font-size:11px;color:var(--text2);display:block;margin-top:3px;">Creada por: ${esc(list.ownerName)}</span>`
      : '';

    // Admin puede editar todas.
    // Usuario común solo su lista.
    // Puerta NO agrega ni borra personas/listas, solo cambia estados.
    const canEditThisList = isAdmin || Number(list.userId) === Number(user.id);

    const statusControl = person => {
      const text = `${statusLabel(person.status)}${person.status === 'entro' ? ` · $${pricePerPerson.toLocaleString('es-AR')}` : ''}`;

      return canManageDoor
        ? `
          <button
            class="btn-status ${esc(person.status)}"
            onclick="event.stopPropagation(); togglePersonStatus(${Number(list.id)}, ${Number(person.id)})">
            ${text}
          </button>
        `
        : `
          <button
            class="btn-status ${esc(person.status)} btn-status-readonly"
            disabled>
            ${text}
          </button>
        `;
    };

    const filteredPeople = (list.people || []).filter(person => {
      if (!personTerm) return true;

      return normalizeText(person.name).includes(personTerm) ||
             normalizeText(person.note || '').includes(personTerm);
    });

    return `
      <div class="list-card ${collapsed ? 'collapsed' : ''}">

        <div class="list-header">
          <div class="list-name-txt" onclick="toggleCollapseList(${Number(list.id)})">
            <span class="list-collapse-icon">${collapsed ? '▸' : '▾'}</span>
            ${esc(list.name)}
            ${list.isBirthday ? `<span class="list-badge badge-orange" style="margin-left:8px;">Cumpleaños</span>` : ''}
            ${owner}

            <span style="font-size:12px;color:var(--text2);display:block;margin-top:4px;">
              ${stats.entered} (${stats.collected.toLocaleString('es-AR')} ARS) · $${pricePerPerson.toLocaleString('es-AR')} c/u
            </span>
          </div>

          ${canEditThisList ? `
            <button
              class="btn-add-person btn-quick-add"
              onclick="event.stopPropagation(); toggleQuickAdd(${Number(list.id)});">
              ＋
            </button>
          ` : ''}

          <div class="list-badge badge-green">
            $${stats.collected.toLocaleString('es-AR')}
          </div>

          ${canEditThisList ? `
            <button
              class="list-delete"
              onclick="event.stopPropagation(); deleteList(${Number(list.id)}, this)">
              ✕
            </button>
          ` : ''}
        </div>

        <div class="list-stats">
          <div class="stat-item">
            <div class="stat-num">${stats.total}</div>
            <div class="stat-lbl">Total</div>
          </div>

          <div class="stat-item">
            <div class="stat-num">${stats.entered}</div>
            <div class="stat-lbl">Entraron</div>
          </div>

          <div class="stat-item">
            <div class="stat-num">${stats.left}</div>
            <div class="stat-lbl">Se fueron</div>
          </div>

          <div class="stat-item">
            <div class="stat-num">${stats.pending}</div>
            <div class="stat-lbl">No vinieron</div>
          </div>
        </div>

        <div class="person-list">
          ${filteredPeople.length
            ? filteredPeople.map(person => `
              <div
                class="person-row ${esc(person.status)} ${canManageDoor ? 'person-row-clickable' : ''} ${lastChangedPersonId === Number(person.id) ? `status-${esc(person.status)}` : ''}"
                ${canManageDoor ? `onclick="togglePersonStatus(${Number(list.id)}, ${Number(person.id)})"` : ''}
              >

                <div class="person-info">
                  <div class="person-name">${esc(person.name)}</div>
                  <div class="person-note">${esc(person.note || '')}</div>
                </div>

                <div class="person-actions">
                  ${statusControl(person)}

                  ${canEditThisList ? `
                    <button
                      class="qr-send-btn"
                      onclick='event.stopPropagation(); enviarQRPersona(
                        ${Number(person.id)},
                        ${JSON.stringify(person.name)},
                        ${JSON.stringify(person.note || '')},
                        ${JSON.stringify(list.name)},
                        ${JSON.stringify(person.qr_token || '')}
                      )'>
                      📤 Enviar QR
                    </button>
                  ` : ''}

                  ${canEditThisList ? `
                    <button
                      class="btn-del-person"
                      onclick="event.stopPropagation(); deletePerson(${Number(list.id)}, ${Number(person.id)}, this)">
                      ✕
                    </button>
                  ` : ''}
                </div>

              </div>
            `).join('')
            : `<div style="padding:8px 4px;color:var(--text2);">Sin personas en esta lista.</div>`
          }
        </div>

        ${canEditThisList ? `
          <div class="quick-add-panel ${openQuickAddListId === Number(list.id) ? '' : 'hidden'}" data-list-id="${Number(list.id)}">

            <div style="padding:12px 14px;border-top:1px solid rgba(240,212,141,.08);display:flex;gap:8px;">
              <button
                class="btn-action quick-tab active"
                data-mode="manual"
                onclick="setQuickAddMode(${Number(list.id)}, 'manual')">
                Manual
              </button>

              <button
                class="btn-action quick-tab"
                data-mode="bulk"
                onclick="setQuickAddMode(${Number(list.id)}, 'bulk')">
                Pegar lista
              </button>
            </div>

            <div class="quick-manual" data-list-id="${Number(list.id)}" style="padding:0 14px 14px;display:flex;gap:8px;">
              <input
                id="person-name-${Number(list.id)}"
                placeholder="Nombre"
                style="flex:1;min-width:0;background:var(--bg3);border:1px solid var(--border);color:var(--text);border-radius:10px;padding:10px;"
              >

              <input
                id="person-note-${Number(list.id)}"
                placeholder="Dato"
                style="width:90px;background:var(--bg3);border:1px solid var(--border);color:var(--text);border-radius:10px;padding:10px;"
              >

              <button
                class="btn-add-person"
                onclick="addPerson(${Number(list.id)}, this)">
                OK
              </button>
            </div>

            <div class="quick-bulk hidden" data-list-id="${Number(list.id)}" style="padding:0 14px 14px;">
              <textarea
                class="bulk-input"
                data-list-id="${Number(list.id)}"
                placeholder="Pegar lista:&#10;Juan 123&#10;Pedro 456"
                style="width:100%;min-height:110px;background:var(--bg3);border:1px solid var(--border);color:var(--text);border-radius:12px;padding:12px;resize:vertical;"></textarea>

              <button
                class="btn-action btn-add"
                style="margin-top:8px;width:100%;"
                onclick="procesarListaPorLista(${Number(list.id)}, this)">
                Procesar lista
              </button>
            </div>

          </div>
        ` : ''}

      </div>
    `;
  }).join('');
}

function parseBulkText(rawText) {
  const lines = String(rawText || '').trim().split(/\r?\n/);
  const people = [];
  let ignored = 0;

  lines.forEach(line => {
    const cleanLine = line.trim().replace(/\s+/g, ' ').replace(/["""]/g, '');
    if (!cleanLine) return;

    const match = cleanLine.match(/^(.+?)(?:\s*[-–—:\/]\s*|\s+)([A-Za-z0-9]{2,12})$/);

    if (!match) {
      ignored++;
      return;
    }

    const name = match[1].trim().replace(/[-–—:\/\s]+$/, '').trim();
    const note = match[2].trim();

    if (!name || !note || !/[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/.test(name)) {
      ignored++;
      return;
    }

    people.push({ name, note });
  });

  return { people, ignored };
}



function toggleCollapseList(listId) {
  collapsedDoorLists[listId] = !collapsedDoorLists[listId];
  drawPuerta();
}

function toggleQuickAdd(listId) {
  listId = Number(listId);

  if (openQuickAddListId === listId) {
    openQuickAddListId = null;
  } else {
    openQuickAddListId = listId;
  }

  drawPuerta();

  if (openQuickAddListId === listId) {
    setQuickAddMode(listId, 'manual');

    const nameInput = document.getElementById('person-name-' + listId);
    if (nameInput) {
      setTimeout(() => nameInput.focus(), 80);
    }
  }
}

function setQuickAddMode(listId, mode) {
  const panel = document.querySelector(`.quick-add-panel[data-list-id="${listId}"]`);
  if (!panel) return;

  const manual = panel.querySelector(`.quick-manual[data-list-id="${listId}"]`);
  const bulk = panel.querySelector(`.quick-bulk[data-list-id="${listId}"]`);
  const tabs = panel.querySelectorAll('.quick-tab');

  tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.mode === mode));

  if (manual) manual.classList.toggle('hidden', mode !== 'manual');
  if (bulk) bulk.classList.toggle('hidden', mode !== 'bulk');
}

function openAddList() {
  const birthdayCheck = document.getElementById('al-birthday');
  if (birthdayCheck) birthdayCheck.checked = false;
  openModal('modal-add-list');
}
async function addList(btn = null) {
  const birthdayCheck = document.getElementById('al-birthday');
  const isBirthday = !!(birthdayCheck && birthdayCheck.checked);

  const key = `list_add:${isBirthday ? 'birthday' : 'normal'}`;

  if (isActionLocked(key)) {
    console.warn('[DIVINE APP] Lista duplicada bloqueada:', key);
    return;
  }

  lockAction(key, 1500);
  setButtonLoading(btn, true, 'Creando...');

  try {
    const data = await api('list_add', { isBirthday });

    closeModal('modal-add-list');

    await renderPuerta(true);

    if (data.existing && data.message) {
      alert(data.message);
    }
  } catch (error) {
    showError(error);
  } finally {
    setButtonLoading(btn, false);
  }
}

async function deleteList(id, btn = null) {
  id = Number(id);

  if (!id) {
    return;
  }

  const key = `list_delete:${id}`;

  if (isActionLocked(key)) {
    console.warn('[DIVINE APP] Eliminación de lista duplicada bloqueada:', key);
    return;
  }

  const list = doorLists.find(list => Number(list.id) === id);

  if (!list) {
    console.warn('[DIVINE APP] La lista ya no está en memoria:', id);
    return;
  }

  const ok = confirm(`¿Eliminar la lista "${list.name}" completa?`);

  if (!ok) {
    return;
  }

  lockAction(key, 2000);
  setButtonLoading(btn, true, '...');

  try {
    await api('list_delete', { id });

    doorLists = doorLists.filter(list => Number(list.id) !== id);

    drawPuerta();

    await renderPuerta(true);
  } catch (error) {
    showError(error);
  } finally {
    setButtonLoading(btn, false);
  }
}

async function addPerson(listId, btn = null) {
  listId = Number(listId);

  const nameInput = document.getElementById('person-name-' + listId);
  const noteInput = document.getElementById('person-note-' + listId);

  if (!nameInput || !noteInput) {
    return;
  }

  const name = nameInput.value.trim();
  const note = noteInput.value.trim();

  if (!name || !note) {
    alert('Completá nombre y dato/número.');
    return;
  }

  const key = `person_add:${listId}:${normalizeText(name)}:${normalizeText(note)}`;

  if (isActionLocked(key)) {
    console.warn('[DIVINE APP] Persona duplicada bloqueada:', key);
    return;
  }

  lockAction(key, 1800);
  setButtonLoading(btn, true, '...');

  try {
    const data = await api('person_add', { listId, name, note });

    const personId = data.personId || data.person_id || data.id || null;

    if (personId) {
      try {
        await apiLocked(
          'qr_generate',
          { personId: Number(personId) },
          `qr_generate:${Number(personId)}`
        );
      } catch (qrError) {
        console.warn('La persona se agregó, pero no se pudo generar el QR automáticamente:', qrError);
      }
    }

    openQuickAddListId = null;

    nameInput.value = '';
    noteInput.value = '';

    await renderPuerta(true);
  } catch (error) {
    showError(error);
  } finally {
    setButtonLoading(btn, false);
  }
}

async function deletePerson(listId, personId, btn = null) {
  listId = Number(listId);
  personId = Number(personId);

  if (!listId || !personId) {
    return;
  }

  const key = `person_delete:${listId}:${personId}`;

  if (isActionLocked(key)) {
    console.warn('[DIVINE APP] Eliminación de persona duplicada bloqueada:', key);
    return;
  }

  const ok = confirm('¿Eliminar esta persona?');

  if (!ok) {
    return;
  }

  lockAction(key, 1800);
  setButtonLoading(btn, true, '...');

  try {
    await api('person_delete', { listId, personId });

    doorLists = doorLists.map(list => {
      if (Number(list.id) !== listId) {
        return list;
      }

      return {
        ...list,
        people: (list.people || []).filter(person => Number(person.id) !== personId)
      };
    });

    drawPuerta();

    await renderPuerta(true);
  } catch (error) {
    showError(error);
  } finally {
    setButtonLoading(btn, false);
  }
}

async function procesarListaPorLista(listId, btn = null) {
  listId = Number(listId);

  const textarea = document.querySelector(`.bulk-input[data-list-id="${listId}"]`);
  if (!textarea) return;

  const rawText = (textarea.value || '').trim();
  if (!rawText) return;

  const parsed = parseBulkText(rawText);

  if (!parsed.people.length) {
    alert('No se agregó nadie. Revisá que cada línea tenga nombre y dato/número al final.');
    return;
  }

  const key = `people_bulk:${listId}:${normalizeText(rawText)}`;

  if (isActionLocked(key)) {
    console.warn('[DIVINE APP] Pegado de lista duplicado bloqueado:', key);
    return;
  }

  lockAction(key, 2500);
  setButtonLoading(btn, true, 'Procesando...');

  try {
    const data = await api('people_bulk', {
      listId,
      people: parsed.people
    });

    textarea.value = '';

    openQuickAddListId = null;

    await renderPuerta(true);

    const ignoredTotal = Number(parsed.ignored || 0) + Number(data.ignored || 0);

    if (ignoredTotal > 0 || Number(data.repeated || 0) > 0) {
      alert(
        `Agregados: ${data.added || 0}\n` +
        `Repetidos: ${data.repeated || 0}\n` +
        `Ignorados por formato inválido: ${ignoredTotal}`
      );
    }
  } catch (error) {
    showError(error);
  } finally {
    setButtonLoading(btn, false);
  }
}

async function togglePersonStatus(listId, personId) {
  listId = Number(listId);
  personId = Number(personId);

  const key = `person_toggle_status:${listId}:${personId}`;

  if (isActionLocked(key)) {
    console.warn('[DIVINE APP] Cambio de estado duplicado bloqueado:', key);
    return;
  }

  lockAction(key, 900);

  try {
    if (statusAnimationTimer) {
      clearTimeout(statusAnimationTimer);
      statusAnimationTimer = null;
    }

    lastChangedPersonId = null;

    await api('person_toggle_status', {
      listId,
      personId
    });

    lastChangedPersonId = personId;
    statusAnimationUntil = Date.now() + 1100;

    await renderPuerta(true);

    statusAnimationTimer = setTimeout(() => {
      if (lastChangedPersonId === personId) {
        lastChangedPersonId = null;
        statusAnimationTimer = null;
        drawPuerta();
      }
    }, 760);

  } catch (error) {
    lastChangedPersonId = null;
    statusAnimationTimer = null;
    statusAnimationUntil = 0;
    showError(error);
  }
}

/* =========================
   MODALS
========================= */
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('open');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('open');
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) {
      overlay.classList.remove('open');
    }
  });
});

/* =========================
   LIVE RELOAD
========================= */
let liveTimer = null;
let liveIsLoadingPuerta = false;
let liveIsLoadingKioskito = false;

function startLiveApp() {
  if (liveTimer) {
    clearInterval(liveTimer);
  }

  liveTimer = setInterval(async () => {
    const puertaActiva = document.getElementById("page-puerta")?.classList.contains("active");
    const kioskitoActivo = document.getElementById("page-kioskito")?.classList.contains("active");

    const estaEscribiendo = document.activeElement?.matches("input, textarea, select");
    const modalAbierto = !!document.querySelector(".modal-overlay.open");

    const panelQuickAbierto = openQuickAddListId !== null || !!document.querySelector(".quick-add-panel:not(.hidden)");
    const pegandoLista = !!document.querySelector(".bulk-input:focus");

    if (
        puertaActiva &&
        !liveIsLoadingPuerta &&
        !estaEscribiendo &&
        !modalAbierto &&
        !panelQuickAbierto &&
        !pegandoLista &&
        Date.now() > statusAnimationUntil
      ) {
      liveIsLoadingPuerta = true;

      try {
        await renderPuerta();
      } catch (e) {
        console.error("Error actualizando puerta:", e);
      } finally {
        liveIsLoadingPuerta = false;
      }
    }

    if (
      kioskitoActivo &&
      !liveIsLoadingKioskito &&
      !estaEscribiendo &&
      !modalAbierto
    ) {
      liveIsLoadingKioskito = true;

      try {
        await renderKioskito();
        await renderGuardarropas();
        await renderKioskoSummary();
      } catch (e) {
        console.error("Error actualizando kioskito:", e);
      } finally {
        liveIsLoadingKioskito = false;
      }
    }
  }, 2000);
}

/* =========================
   GUARDARROPAS
========================= */

function instalarGuardarropas() {
  const kioskitoPage = document.getElementById("page-kioskito");
  if (!kioskitoPage) return;

  if (document.getElementById("guardarropas-box")) return;

  const sidePanel = document.getElementById("kioskito-side-panel");
  const wrap = document.querySelector(".page-kioskito-wrap");

  if (!sidePanel && !wrap) return;

  const box = document.createElement("div");
  box.id = "guardarropas-box";

  box.innerHTML = `
    <div class="section guardarropas-section">
      <div class="section-title">Guardarropas</div>

      <div class="action-row guardarropas-actions">
        <button class="btn-action btn-add" type="button" onclick="abrirGuardarropas()">
          🧥 + Guardarropas
        </button>
      </div>

      <div class="total-bar guardarropas-total">
        <div>
          <div class="total-label">Guardarropas</div>
          <div style="color:var(--text2);font-size:12px;">
            Activos: <span id="gr-activos">0</span> · Retirados: <span id="gr-retirados">0</span>
          </div>
        </div>

        <div class="total-amount" id="gr-total">$0</div>
      </div>

      <input
        id="gr-search"
        type="text"
        placeholder="Buscar por número, nombre, DNI o teléfono..."
        oninput="renderGuardarropas()"
        class="guardarropas-search"
      >

      <div id="gr-list"></div>
    </div>
  `;

  if (sidePanel) {
    const salesHistory = document.getElementById("sales-history");

    if (salesHistory && salesHistory.parentNode === sidePanel) {
      sidePanel.insertBefore(box, salesHistory);
    } else {
      sidePanel.appendChild(box);
    }
  } else {
    wrap.insertAdjacentElement("afterbegin", box);
  }

  renderGuardarropas();
}

function abrirGuardarropas() {
  document.getElementById("gr-name").value = "";
  document.getElementById("gr-dni").value = "";
  document.getElementById("gr-phone").value = "";
  openModal("modal-guardarropas");
  setTimeout(() => document.getElementById("gr-name")?.focus(), 80);
}

async function crearGuardarropas() {
  const nombre   = document.getElementById("gr-name")?.value.trim()  || "";
  const dni      = document.getElementById("gr-dni")?.value.trim()   || "";
  const telefono = document.getElementById("gr-phone")?.value.trim() || "";

  if (!nombre) {
    alert("Ingresá el nombre.");
    return;
  }

  try {
    await api("guardarropas_add", { nombre, dni, telefono });
    closeModal("modal-guardarropas");
    await renderGuardarropas();
  } catch (error) {
    showError(error);
  }
}

async function entregarGuardarropas(id) {
  const ok = confirm("¿Entregar prenda?");
  if (!ok) return;

  try {
    await api("guardarropas_entregar", { id });
    await renderGuardarropas();
  } catch (error) {
    showError(error);
  }
}

async function eliminarGuardarropas(id) {
  const okDelete = confirm('¿Eliminar este guardarropas?');

  if (!okDelete) {
    return;
  }

  try {
    await api('guardarropas_delete', { id: Number(id) });
    await renderGuardarropas();
  } catch (error) {
    showError(error);
  }
}

async function renderGuardarropas() {
  const list = document.getElementById("gr-list");
  if (!list) return;

  try {
    const data  = await api("guardarropas_list");
    const items = data.items || [];

    const search    = normalizeText(document.getElementById("gr-search")?.value || "");
    const activos   = items.filter(x => x.estado === "pendiente").length;
    const retirados = items.filter(x => x.estado === "retirado").length;
    const total     = items.reduce((acc, item) => acc + Number(item.precio || 0), 0);

    const grTotal     = document.getElementById("gr-total");
    const grActivos   = document.getElementById("gr-activos");
    const grRetirados = document.getElementById("gr-retirados");
    if (grTotal)     grTotal.textContent     = fmt(total);
    if (grActivos)   grActivos.textContent   = activos;
    if (grRetirados) grRetirados.textContent = retirados;

    const filtrados = items.filter(item => {
      if (!search) return true;
      const text = normalizeText(
        `${item.codigo} ${item.numero} ${item.nombre} ${item.dni || ""} ${item.telefono || ""}`
      );
      return text.includes(search);
    });

    if (!filtrados.length) {
      list.innerHTML = `
        <div class="list-card" style="margin:0 14px 14px;">
          <div style="padding:18px;color:var(--text2);">
            ${search ? "Sin resultados para esa búsqueda." : "Sin prendas cargadas."}
          </div>
        </div>
      `;
      return;
    }

    list.innerHTML = filtrados.map(item => `
      <div class="list-card" style="margin:0 14px 14px;">
        <div class="list-header">
          <div class="list-name-txt">
            ${esc(item.codigo)}
            <span style="font-size:12px;color:var(--text2);font-family:'DM Sans',sans-serif;display:block;margin-top:4px;">
              ${esc(item.nombre)}
              ${item.dni      ? " · DNI "  + esc(item.dni)      : ""}
              ${item.telefono ? " · Tel "  + esc(item.telefono) : ""}
              · ${new Date(item.hora_ingreso).toLocaleTimeString("es-AR", { hour:"2-digit", minute:"2-digit" })}
            </span>
          </div>

                  <div style="display:flex;align-items:center;gap:8px;">
          <div style="
            color:var(--gold-2);
            font-family:'Cinzel',serif;
            font-size:18px;
            font-weight:700;
            white-space:nowrap;
          ">
            ${fmt(item.precio)}
          </div>

          <div class="list-badge ${item.estado === "pendiente" ? "badge-orange" : "badge-green"}">
            ${item.estado === "pendiente" ? "Pendiente" : "Retirado"}
          </div>
        </div>
        </div>

        <div style="padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;">
          <div style="color:var(--gold-2);font-family:'Cinzel',serif;font-size:22px;font-weight:700;">
            ${fmt(item.precio)}
          </div>

          ${item.estado === "pendiente"
            ? `<button class="btn-action btn-add" style="max-width:150px;" onclick="entregarGuardarropas(${Number(item.id)})">Entregar</button>`
            : `<div style="color:var(--text2);font-size:12px;">Retirado · ${
                item.hora_retirado
                  ? new Date(item.hora_retirado).toLocaleTimeString("es-AR", { hour:"2-digit", minute:"2-digit" })
                  : ""
              }</div>`
          }
          ${item.estado === 'retirado'
          ? `
              <button
                  class="btn-delete"
                  onclick="eliminarGuardarropas(${item.id})"
              >
                  Eliminar
              </button>
          `
          : ''
      }
        </div>
      </div>
    `).join("");

  } catch (error) {
    showError(error);
  }
}
// ---------------------------------------------------------------
//                      QR DE ENTRADA
// ---------------------------------------------------------------

async function enviarQRPersona(personId, personName, personNote, listName, currentToken = '') {
  let token = currentToken;

  try {
    if (!token) {
      const data = await api('qr_generate', { personId: Number(personId) });

      token = data.token || data.qr_token || '';

      if (!token) {
        throw new Error('No se pudo obtener el token del QR.');
      }

      await renderPuerta();
    }

    await generarImagenQR({
      token,
      personName,
      personNote,
      listName,
      expiresAt: '03:00 AM'
    });
  } catch (error) {
    alert(error.message || 'No se pudo generar el QR');
  }
}

async function generarImagenQR({ token, personName, personNote, listName, expiresAt }) {
  if (typeof QRious === 'undefined') {
    alert('Falta cargar QRious en index.php');
    return;
  }

  const qrLink = `${location.origin}/qr.php?token=${encodeURIComponent(token)}`;

  const qrCanvas = document.createElement('canvas');

  new QRious({
    element: qrCanvas,
    value: qrLink,
    size: 430,
    background: 'white',
    foreground: 'black'
  });

  const canvas = document.createElement('canvas');
  canvas.width = 900;
  canvas.height = 1300;

  const ctx = canvas.getContext('2d');

  const gradient = ctx.createLinearGradient(0, 0, 900, 1300);
  gradient.addColorStop(0, '#17121f');
  gradient.addColorStop(1, '#050506');

  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  ctx.fillStyle = '#f0d48d';
  ctx.font = 'bold 58px Arial';
  ctx.textAlign = 'center';
  ctx.fillText('QR DE ENTRADA', 450, 120);

  const displayName = `${personName || 'Invitado'}${personNote ? ` - ${personNote}` : ''}`;

  ctx.fillStyle = '#ffffff';
  ctx.font = 'bold 48px Arial';
  ctx.fillText(displayName, 450, 220);

  ctx.fillStyle = '#b9b3c9';
  ctx.font = '30px Arial';
  ctx.fillText(`Lista: ${listName || '-'}`, 450, 285);

  ctx.fillStyle = '#ffffff';
  ctx.fillRect(225, 405, 450, 450);
  ctx.drawImage(qrCanvas, 235, 415, 430, 430);

  ctx.fillStyle = '#f0d48d';
  ctx.font = 'bold 38px Arial';
  ctx.fillText(`Válido hasta ${expiresAt}`, 450, 925);

  ctx.fillStyle = '#ffffff';
  ctx.font = '26px Arial';
  ctx.fillText('Mostrá este QR en puerta para ingresar', 450, 985);

  ctx.fillStyle = '#777';
  ctx.font = '22px Arial';
  ctx.fillText(location.host, 450, 1080);

  // La imagen se crea SIEMPRE
  canvas.toBlob(async blob => {
    if (!blob) {
      alert('No se pudo crear la imagen del QR');
      return;
    }

    const safeName = String(personName || 'invitado')
      .replace(/[^\w\-]+/g, '_')
      .slice(0, 40);

    const file = new File([blob], `QR_${safeName}.png`, {
      type: 'image/png'
    });

    const imageUrl = URL.createObjectURL(blob);

    // Después de crear la imagen, intenta compartirla
    try {
      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        await navigator.share({
          files: [file],
          title: 'QR de entrada',
          text: `QR de entrada para ${personName || 'invitado'}`
        });

        setTimeout(() => URL.revokeObjectURL(imageUrl), 3000);
        return;
      }

      // Si no puede compartir archivo, intenta compartir el link
      if (navigator.share) {
        await navigator.share({
          title: 'QR de entrada',
          text: `QR de entrada para ${personName || 'invitado'}`,
          url: qrLink
        });

        setTimeout(() => URL.revokeObjectURL(imageUrl), 3000);
        return;
      }

      // Si el navegador no soporta compartir, recién ahí muestra la imagen
      window.open(imageUrl, '_blank');

    } catch (error) {
      console.error('No se pudo compartir el QR:', error);

      // Si falla compartir, deja ver la imagen ya creada
      window.open(imageUrl, '_blank');
    }
  }, 'image/png');
}


/* =========================
   INIT DE LA APP
========================= */
window.addEventListener("load", () => {
  safeRun('No se pudo iniciar la app', () => {
    buildPinPad();
    goTo("menu");
    startLiveApp();
  });
});