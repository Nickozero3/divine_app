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

let doorView = localStorage.getItem("doorView") || "mine";
function setDoorView(view) {

  doorView = view;

  localStorage.setItem("doorView", view);

  document.getElementById("door-view-mine")?.classList.toggle(
    "active",
    view === "mine"
  );

  document.getElementById("door-view-all")?.classList.toggle(
    "active",
    view === "all"
  );

  renderPuerta(true);

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

// Debounce genérico: agrupa llamadas seguidas (ej. tipeo en un buscador)
// en una sola ejecución, evitando recalcular/rerenderizar en cada tecla.
function debounce(fn, wait = 150) {
  let timer = null;
  return function debounced(...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), wait);
  };
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

async function api(action, data = null, params = {}) {

  const query = new URLSearchParams({
    action,
    ...params
  });

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

  const res = await fetch(
    `api.php?${query.toString()}`,
    options
  );

  const text = await res.text();

  let json;

  try {
    json = JSON.parse(text);
  } catch {
    throw new Error(text);
  }

  if (!res.ok || !json.ok) {
    throw new Error(json.error || 'Error');
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
  const routes = {
    menu: 'index.php',
    kioskito: 'kioskito.php',
    puerta: 'listas.php'
  };

  const route = routes[page];

  if (!route) {
    showAppBroken('No se pudo abrir la página', new Error(`Ruta desconocida: ${page}`));
    return;
  }

  window.location.href = route;
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
let productsLoadedAt = 0;
const PRODUCTS_CACHE_TTL_MS = 30_000;
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

async function renderKioskito(refreshProducts = true) {
  const wrap = document.getElementById('k-categories');
  if (!wrap) return;

  try {
    const needsProductsRefresh = refreshProducts || !products.length ||
      (Date.now() - productsLoadedAt) > PRODUCTS_CACHE_TTL_MS;

    if (needsProductsRefresh) {
      const data = await api('products_list');
      products = data.products || [];
      productsLoadedAt = Date.now();
    }

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
          <div class="section-title">
Ventas de la caja actual</div>
          <div style="padding:14px 16px;color:var(--text2);font-size:14px;">
            La caja actual todavía no tiene ventas.
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
let hiddenEmptyDoorLists = [];
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

// Mismo ciclo que aplica el backend en person_toggle_status (api.php):
// no_vino -> entro -> se_fue -> no_vino. Se usa para poder mostrar/actualizar
// el próximo estado en el cliente sin esperar la respuesta del servidor.
function nextDoorStatus(status) {
  if (status === 'no_vino') return 'entro';
  if (status === 'entro') return 'se_fue';
  return 'no_vino';
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

    const data = await api(
      'door_lists',
      null,
      {
        mine: doorView === 'mine' ? 1 : 0
      }
    );
    const newLists = Array.isArray(data.lists) ? data.lists : [];
    const newHiddenEmpty = Array.isArray(data.hiddenEmptyLists) ? data.hiddenEmptyLists : [];
    const newSnapshot = makeDoorSnapshot(newLists) + '|' + JSON.stringify(newHiddenEmpty);

    if (!forceRender && puertaYaRenderizada && newSnapshot === lastDoorSnapshot) {
      return;
    }

    doorLists = newLists;
    hiddenEmptyDoorLists = newHiddenEmpty;
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

  // Guardamos la posición de scroll para restaurarla después de reescribir
  // el HTML: evita que la puerta "salte" arriba con cada actualización en vivo.
  const previousScrollTop = wrap.scrollTop;

  const user = window.DIVINE_USER || {};
  const role = normalizeText(user.role || '');

  const isAdmin = role === 'admin';
  const isPuerta = role === 'puerta';

  // Admin y puerta pueden ver/controlar todas las listas.
  const canManageDoor = isAdmin || isPuerta;

  const hiddenNoticeHtml = (canManageDoor && hiddenEmptyDoorLists.length)
    ? `
      <div class="list-card list-notice">
        👁️‍🗨️ ${hiddenEmptyDoorLists.length} lista${hiddenEmptyDoorLists.length === 1 ? '' : 's'} oculta${hiddenEmptyDoorLists.length === 1 ? '' : 's'} por estar vacía${hiddenEmptyDoorLists.length === 1 ? '' : 's'} (sin personas cargadas):
        <b style="color:var(--text);">${hiddenEmptyDoorLists.map(l => esc(l.ownerName ? `${l.name} (${l.ownerName})` : l.name)).join(', ')}</b>
      </div>
    `
    : '';

  // Usuario común solo busca dentro de sus listas.
  const listTerm = canManageDoor ? normalizeText(searchInput ? searchInput.value : '') : '';
  const personTerm = normalizeText(personSearchInput ? personSearchInput.value : '');

  const visibleLists = doorLists.filter(list =>
    listMatchesSearch(list, listTerm) &&
    personMatchesSearch(list, personTerm)
  );

  if (!visibleLists.length) {
    // Distinguimos "no hay listas todavía" de "no hay resultados para esta búsqueda"
    // para que el mensaje sea preciso.
    const emptyMessage = (listTerm || personTerm)
      ? 'No se encontraron listas o personas con esa búsqueda.'
      : 'Todavía no hay listas creadas.';

    wrap.innerHTML = `
      ${hiddenNoticeHtml}
      <div class="list-card">
        <div class="list-empty-msg">
          ${emptyMessage}
        </div>
      </div>
    `;
    wrap.scrollTop = previousScrollTop;
    return;
  }

  wrap.innerHTML = hiddenNoticeHtml + visibleLists.map(list => {
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
      const text = `${statusLabel(person.status)}${person.status === 'entro' ? ` · ${fmt(pricePerPerson)}` : ''}`;
      const nextLabel = statusLabel(nextDoorStatus(person.status));

      return canManageDoor
        ? `
          <button
            class="btn-status ${esc(person.status)}"
            aria-label="${esc(person.name)}: ${esc(statusLabel(person.status))}. Tocar para pasar a ${esc(nextLabel)}."
            onclick="event.stopPropagation(); togglePersonStatus(${Number(list.id)}, ${Number(person.id)})">
            ${text}
          </button>
        `
        : `
          <button
            class="btn-status ${esc(person.status)} btn-status-readonly"
            aria-label="${esc(person.name)}: ${esc(statusLabel(person.status))}"
            disabled>
            ${text}
          </button>
        `;
    };

    const filteredPeople = (list.people || []).filter(person => {
      const matchesSearch = !personTerm ||
        normalizeText(person.name).includes(personTerm) ||
        normalizeText(person.note || '').includes(personTerm);
      return matchesSearch;
    });

    return `
      <div class="list-card ${collapsed ? 'collapsed' : ''}">

        <div class="list-header">
          <div
            class="list-name-txt"
            role="button"
            tabindex="0"
            aria-expanded="${collapsed ? 'false' : 'true'}"
            aria-label="${collapsed ? 'Expandir' : 'Colapsar'} lista ${esc(list.name)}"
            onclick="toggleCollapseList(${Number(list.id)})"
            onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleCollapseList(${Number(list.id)});}">
            <span class="list-collapse-icon" aria-hidden="true">${collapsed ? '▸' : '▾'}</span>
            ${esc(list.name)}
            ${list.isBirthday ? `<span class="list-badge badge-orange" style="margin-left:8px;">Cumpleaños</span>` : ''}
            ${owner}

            <span class="list-subinfo">
              ${stats.entered} (${fmt(stats.collected)}) · ${fmt(pricePerPerson)} c/u
            </span>
          </div>

          ${canEditThisList ? `
            <button
              class="btn-add-person btn-quick-add"
              aria-label="Agregar persona a ${esc(list.name)}"
              onclick="event.stopPropagation(); toggleQuickAdd(${Number(list.id)});">
              ＋
            </button>
          ` : ''}

          <div class="list-badge badge-green">
            ${fmt(stats.collected)}
          </div>

          ${canEditThisList ? `
            <button
              class="list-delete"
              aria-label="Eliminar lista ${esc(list.name)}"
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
        ? filteredPeople.map(person => {
          /*
          * Admin puede eliminar cualquier persona.
          * RRPP/Pública solamente cuando todavía está en no_vino.
          * Puerta no puede eliminar porque canEditThisList será false.
          */
          const canDeletePerson =
            canEditThisList &&
            (isAdmin || person.status === 'no_vino');

          return `
                    <div
                      class="person-row ${esc(person.status)} ${canManageDoor ? 'person-row-clickable' : ''} ${lastChangedPersonId === Number(person.id) ? `status-${esc(person.status)}` : ''}"
                      ${canManageDoor
              ? `role="button" tabindex="0" aria-label="${esc(person.name)}, ${esc(statusLabel(person.status))}. Tocar para cambiar estado." onclick="togglePersonStatus(${Number(list.id)}, ${Number(person.id)})" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();togglePersonStatus(${Number(list.id)}, ${Number(person.id)});}"`
              : ''}
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
                            aria-label="Enviar QR a ${esc(person.name)}"
                            onclick="event.stopPropagation(); enviarQRPersonaById(${Number(list.id)}, ${Number(person.id)})">
                            📤 Enviar QR
                          </button>
                        ` : ''}

                        ${canDeletePerson ? `
                          <button
                            class="btn-del-person"
                            aria-label="Eliminar a ${esc(person.name)}"
                            onclick="event.stopPropagation(); deletePerson(
                              ${Number(list.id)},
                              ${Number(person.id)}
                            )">
                            ✕
                          </button>
                        ` : ''}
                      </div>
                    </div>
                  `;
        }).join('')
        : `
                <div class="list-empty-msg list-empty-msg-inline">
                  ${personTerm ? 'Sin coincidencias en esta lista.' : 'Sin personas en esta lista.'}
                </div>
              `
      }
          </div>
        ${canEditThisList ? `
          <div class="quick-add-panel ${openQuickAddListId === Number(list.id) ? '' : 'hidden'}" data-list-id="${Number(list.id)}">

            <div class="quick-add-tabs">
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

            <div class="quick-manual" data-list-id="${Number(list.id)}">
              <input
                id="person-name-${Number(list.id)}"
                placeholder="Nombre"
                aria-label="Nombre de la persona a agregar"
                class="quick-manual-name">

              <input
                id="person-note-${Number(list.id)}"
                placeholder="Dato"
                aria-label="Dato o número de la persona a agregar"
                class="quick-manual-note">

              <button
                class="btn-add-person"
                onclick="addPerson(${Number(list.id)}, this)">
                OK
              </button>
            </div>

            <div class="quick-bulk hidden" data-list-id="${Number(list.id)}">
              <textarea
                class="bulk-input"
                data-list-id="${Number(list.id)}"
                placeholder="Pegar lista:&#10;Juan 123&#10;Pedro 456"
                oninput="renderBulkPreview(${Number(list.id)})"></textarea>

              <div class="bulk-preview hidden" id="bulk-preview-${Number(list.id)}"></div>

              <button
                class="btn-action btn-add btn-add-full"
                onclick="procesarListaPorLista(${Number(list.id)}, this)">
                Procesar lista
              </button>
            </div>

          </div>
        ` : ''}

      </div>
    `;
  }).join('');

  wrap.scrollTop = previousScrollTop;
}

// Versión "debounced" de drawPuerta para usar en los buscadores: evita
// recalcular y reescribir todo el HTML en cada tecla que se tipea.
const debouncedDrawPuerta = debounce(drawPuerta, 180);

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



// Muestra una vista previa de lo que se va a cargar antes de tocar "Procesar
// lista", para que el usuario pueda detectar líneas mal formateadas o nombres
// repetidos sin tener que enviarlo primero y enterarse después por el alert.
function renderBulkPreview(listId) {
  listId = Number(listId);

  const textarea = document.querySelector(`.bulk-input[data-list-id="${listId}"]`);
  const preview = document.getElementById(`bulk-preview-${listId}`);

  if (!textarea || !preview) return;

  const rawText = textarea.value || '';

  if (!rawText.trim()) {
    preview.classList.add('hidden');
    preview.innerHTML = '';
    return;
  }

  const { people, ignored } = parseBulkText(rawText);

  const list = doorLists.find(l => Number(l.id) === listId);
  const existingNames = new Set(
    ((list && list.people) || []).map(p => normalizeText(p.name))
  );

  const rows = people.map(person => {
    const isDuplicate = existingNames.has(normalizeText(person.name));
    return `
      <div class="bulk-preview-row ${isDuplicate ? 'bulk-preview-dup' : ''}">
        <span class="bulk-preview-name">${esc(person.name)}</span>
        <span class="bulk-preview-note">${esc(person.note)}</span>
        ${isDuplicate ? '<span class="bulk-preview-tag">¿repetido?</span>' : ''}
      </div>
    `;
  }).join('');

  preview.classList.remove('hidden');
  preview.innerHTML = `
    <div class="bulk-preview-summary">
      ${people.length} persona${people.length === 1 ? '' : 's'} detectada${people.length === 1 ? '' : 's'}
      ${ignored ? ` · ${ignored} línea${ignored === 1 ? '' : 's'} ignorada${ignored === 1 ? '' : 's'} (formato inválido)` : ''}
    </div>
    ${rows}
  `;
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
    renderBulkPreview(listId);

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

function puertaFeedback(type = 'ok') {
  // Vibración corta: pensada para teléfonos usados en la puerta.
  try {
    if (navigator.vibrate) {
      navigator.vibrate(
        type === 'ok' ? 35 :
        type === 'warning' ? [45, 35, 45] :
        [90]
      );
    }
  } catch (_) {}

  // Sonido local, sin archivos externos.
  try {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) return;

    const ctx = new AudioContextClass();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();

    osc.type = 'sine';
    osc.frequency.value =
      type === 'ok' ? 880 :
      type === 'warning' ? 520 :
      240;

    gain.gain.setValueAtTime(0.0001, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.045, ctx.currentTime + 0.008);
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.105);

    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.11);

    osc.onended = () => {
      try { ctx.close(); } catch (_) {}
    };
  } catch (_) {}
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

  const list = doorLists.find(l => Number(l.id) === listId);
  const person = list && (list.people || []).find(p => Number(p.id) === personId);

  if (!person) {
    console.warn('[DIVINE APP] Persona no encontrada en memoria:', personId);
    return;
  }

  // Actualización optimista: cambiamos el estado en memoria y repintamos
  // de inmediato, sin esperar la respuesta del servidor. Así la puerta se
  // siente instantánea aunque la red esté lenta. Si el pedido falla,
  // revertimos el estado y avisamos.
  const previousStatus = person.status;

  if (statusAnimationTimer) {
    clearTimeout(statusAnimationTimer);
    statusAnimationTimer = null;
  }

  person.status = nextDoorStatus(previousStatus);
  lastChangedPersonId = personId;
  statusAnimationUntil = Date.now() + 1100;

  drawPuerta();

  try {
    await api('person_toggle_status', {
      listId,
      personId
    });

    puertaFeedback(person.status === 'se_fue' ? 'warning' : 'ok');

    // Confirmamos con el servidor en segundo plano (sin bloquear la UI)
    // para traer cualquier otro cambio concurrente de otro dispositivo.
    renderPuerta(true).catch(error => console.error('Error sincronizando puerta:', error));

    statusAnimationTimer = setTimeout(() => {
      if (lastChangedPersonId === personId) {
        lastChangedPersonId = null;
        statusAnimationTimer = null;
        drawPuerta();
      }
    }, 760);

  } catch (error) {
    // Revertimos el cambio optimista porque el servidor lo rechazó.
    person.status = previousStatus;
    lastChangedPersonId = null;
    statusAnimationTimer = null;
    statusAnimationUntil = 0;
    drawPuerta();
    puertaFeedback('error');
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
    if (document.hidden) return;

    const currentPage = document.body.dataset.page || '';
    const puertaActiva = currentPage === 'listas' || document.getElementById("page-puerta")?.classList.contains("active");
    const kioskitoActivo = currentPage === 'kioskito' || document.getElementById("page-kioskito")?.classList.contains("active");

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
        await Promise.all([
          renderKioskito(false),
          renderGuardarropas(),
          renderKioskoSummary(),
        ]);
      } catch (e) {
        console.error("Error actualizando kioskito:", e);
      } finally {
        liveIsLoadingKioskito = false;
      }
    }
  }, 6000);
}

document.addEventListener('visibilitychange', () => {
  if (document.hidden) return;

  const currentPage = document.body.dataset.page || '';
  if (currentPage === 'listas') {
    renderPuerta().catch(error => console.error('Error sincronizando puerta:', error));
  }
});

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
  const nombre = document.getElementById("gr-name")?.value.trim() || "";
  const dni = document.getElementById("gr-dni")?.value.trim() || "";
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
    const data = await api("guardarropas_list");
    const items = data.items || [];

    const search = normalizeText(document.getElementById("gr-search")?.value || "");
    const activos = items.filter(x => x.estado === "pendiente").length;
    const retirados = items.filter(x => x.estado === "retirado").length;
    const total = items.reduce((acc, item) => acc + Number(item.precio || 0), 0);

    const grTotal = document.getElementById("gr-total");
    const grActivos = document.getElementById("gr-activos");
    const grRetirados = document.getElementById("gr-retirados");
    if (grTotal) grTotal.textContent = fmt(total);
    if (grActivos) grActivos.textContent = activos;
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
              ${item.dni ? " · DNI " + esc(item.dni) : ""}
              ${item.telefono ? " · Tel " + esc(item.telefono) : ""}
              · ${new Date(item.hora_ingreso).toLocaleTimeString("es-AR", { hour: "2-digit", minute: "2-digit" })}
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
        : `<div style="color:var(--text2);font-size:12px;">Retirado · ${item.hora_retirado
          ? new Date(item.hora_retirado).toLocaleTimeString("es-AR", { hour: "2-digit", minute: "2-digit" })
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

// Punto de entrada seguro desde el HTML: en vez de incrustar nombre/nota/token
// de la persona (que pueden traer comillas, ej. "O'Connor") dentro del atributo
// onclick -lo que antes podía romper el HTML generado o, en el peor caso, ser
// usado para inyectar código-, buscamos los datos ya cargados en memoria por id.
async function enviarQRPersonaById(listId, personId) {
  const list = doorLists.find(l => Number(l.id) === Number(listId));
  const person = list && (list.people || []).find(p => Number(p.id) === Number(personId));

  if (!list || !person) {
    alert('No se encontró a la persona (recargá la puerta e intentá de nuevo).');
    return;
  }

  return enviarQRPersona(
    person.id,
    person.name,
    person.note || '',
    list.name,
    person.qr_token || ''
  );
}

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
    alert('Falta cargar QRious en listas.php');
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
  // Texto adicional opcional 
  ctx.fillText('Veni a disfrutar', 450, 1080);

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
/* =====================================
   VOLVER AL INDEX AL USAR ATRÁS
===================================== */

document.addEventListener('DOMContentLoaded', () => {
  setDoorView(doorView);
  const currentPage = document.body.dataset.page || '';

  // No se aplica dentro del propio index.
  if (currentPage === 'menu' || currentPage === 'index') {
    return;
  }

  // Crea una entrada para detectar el botón o gesto "Atrás".
  history.pushState(
    { divinePage: currentPage },
    '',
    window.location.href
  );

  window.addEventListener('popstate', () => {
    window.location.replace('index.php');
  });
});

let qrScanner = null;
let qrScannerRunning = false;
let qrScannerStarting = false;
let qrScannerLibraryPromise = null;

function loadQrScannerLibrary() {
  if (window.Html5Qrcode) return Promise.resolve();
  if (qrScannerLibraryPromise) return qrScannerLibraryPromise;

  qrScannerLibraryPromise = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://unpkg.com/html5-qrcode';
    script.async = true;
    script.onload = resolve;
    script.onerror = () => reject(new Error('No se pudo cargar el lector QR.'));
    document.head.appendChild(script);
  });

  return qrScannerLibraryPromise;
}

function canUseDoorScanner() {
  const user = window.DIVINE_USER || {};
  if (user.can_use_scanner === true) return true;

  const role = String(user.role || '').toLowerCase().trim();
  return role === 'admin' || role === 'puerta';
}

async function openScanner() {
  if (!canUseDoorScanner()) {
    console.warn('[DIVINE APP] Scanner bloqueado: solo admin o puerta.');
    return;
  }

  const modal = document.getElementById("scanner-modal");
  const reader = document.getElementById("reader");
  const result = document.getElementById("qr-result");

  if (!modal || !reader || !result) {
    console.error('[DIVINE APP] Faltan elementos del scanner.');
    return;
  }

  modal.style.display = "flex";

  // Si ya está inicializado o arrancando, no crear otra instancia.
  if (qrScanner || qrScannerStarting) {
    return;
  }

  qrScannerStarting = true;

  try {
    await loadQrScannerLibrary();

    if (!window.Html5Qrcode) {
      throw new Error('No se pudo inicializar el lector QR.');
    }

    qrScanner = new Html5Qrcode("reader");

    await qrScanner.start(
      { facingMode: "environment" },
      { fps: 10, qrbox: 250 },
      onQrSuccess,
      () => {}
    );

    qrScannerRunning = true;

  } catch (error) {
    console.error('[DIVINE APP] Error al abrir scanner:', error);

    qrScannerRunning = false;

    // clear() es síncrono/void en algunas versiones de html5-qrcode.
    // Nunca se encadena .catch() sobre su resultado.
    if (qrScanner) {
      try {
        qrScanner.clear();
      } catch (_) {}
    }

    qrScanner = null;

    result.innerHTML = `
      <div class="status-pill status-error">
        No se pudo abrir la cámara
      </div>
      <div class="qr-detail" style="margin-top:14px">
        ${esc(error?.message || 'Error desconocido al abrir la cámara.')}
      </div>
    `;
  } finally {
    qrScannerStarting = false;
  }
}

async function onQrSuccess(text) {

  try {

    let token = text;

    // Si el QR es una URL, obtener solo el token.
    try {

      const url = new URL(text);

      const t = url.searchParams.get("token");

      if (t) {
        token = t;
      }

    } catch (_) {
      // El QR ya contiene solo el token.
    }

    const data = await api("qr_check", {
      token
    });

    window.currentQrToken = token;
    window.currentQrPerson = data.person;
    puertaFeedback('ok');

    document.getElementById("qr-result").innerHTML = `
      <div class="status-pill status-ok">
        🟢 QR válido
      </div>

      <div class="qr-detail" style="margin-top:14px">

        <div style="font-size:22px;font-weight:800">
          ${esc(data.person.name)}
        </div>

        <div style="margin-top:6px;color:var(--text2)">
          📋 ${esc(data.person.list_name)}
        </div>

        ${data.person.note ? `
          <div style="margin-top:4px;color:var(--text2)">
            📝 ${esc(data.person.note)}
          </div>
        ` : ""}

      </div>

      <div style="
        display:flex;
        gap:12px;
        margin-top:22px;
      ">

        <button
          class="btn-modal btn-cancel"
          style="flex:1"
          onclick="closeScanner()">

          Cancelar

        </button>

        <button
          class="btn-modal btn-confirm"
          style="flex:1"
          onclick="confirmQrEntry()">

          ✅ Confirmar ingreso

        </button>

      </div>
    `;

  } catch (e) {

    puertaFeedback('error');

    document.getElementById("qr-result").innerHTML = `
      <div class="status-pill status-error">
        🔴 QR inválido
      </div>

      <div class="qr-detail" style="margin-top:14px">
        ${esc(e.message)}
      </div>
    `;

  }

}
async function confirmQrEntry() {

  try {

    const data = await api(
      "qr_confirm",
      {
        token: window.currentQrToken
      }
    );

    const person = window.currentQrPerson || {};

    puertaFeedback('ok');

    document.getElementById("qr-result").innerHTML = `

      <div class="status-pill status-ok">

        ✅ Ingreso registrado

      </div>

      <div class="qr-detail">

        <div style="
          font-size:22px;
          font-weight:800;
          margin-top:10px;
        ">

          ${esc(person.name || "")}

        </div>

        <div style="
          color:var(--text2);
          margin-top:8px;
        ">

          ${esc(data.message)}

        </div>

      </div>

    `;

    setTimeout(() => {

      closeScanner();

      renderPuerta(true);

    }, 1200);

  } catch (e) {

    puertaFeedback('error');

    document.getElementById("qr-result").innerHTML = `

      <div class="status-pill status-error">

        🔴 Error

      </div>

      <div class="qr-detail">

        ${esc(e.message)}

      </div>

      <div style="margin-top:20px">

        <button
          class="btn-modal btn-confirm"
          onclick="confirmQrEntry()">

          Reintentar

        </button>

      </div>

    `;

  }

}

async function closeScanner() {
  const modal = document.getElementById("scanner-modal");
  if (modal) {
    modal.style.display = "none";
  }

  const scanner = qrScanner;
  qrScanner = null;

  // Si todavía estaba arrancando, openScanner() se encargará de terminar.
  if (!scanner) {
    qrScannerRunning = false;
    return;
  }

  if (qrScannerRunning) {
    try {
      await scanner.stop();
    } catch (error) {
      // Evita el error "scanner is not running or paused" al cerrar durante una carrera.
      console.debug('[DIVINE APP] Scanner ya estaba detenido:', error?.message || error);
    }
  }

  qrScannerRunning = false;

  // clear() puede ser síncrono y devolver undefined.
  try {
    scanner.clear();
  } catch (_) {}
}


/* =========================
   INIT DE LA APP
========================= */
window.addEventListener("load", () => {
  safeRun('No se pudo iniciar la app', () => {
    const currentPage = document.body.dataset.page || '';

    if (currentPage === 'kioskito') {
      buildPinPad();

      safeRunAsync('No se pudo cargar Kioskito', renderKioskito, 'k-categories');
      safeRunAsync('No se pudo cargar historial de ventas', renderSalesHistory, 'sales-history');
      safeRunAsync('No se pudo cargar resumen de caja', renderKioskoSummary, 'kiosko-summary');

      setTimeout(() => {
        safeRun('No se pudo instalar Guardarropas', instalarGuardarropas);
        safeRunAsync('No se pudo cargar Guardarropas', renderGuardarropas, 'gr-list');
      }, 100);

      startLiveApp();
      return;
    }

    if (currentPage === 'listas') {
      safeRunAsync('No se pudo cargar Puerta', () => renderPuerta(true), 'p-lists');
      startLiveApp();
    }
  });
});
