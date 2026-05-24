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
    throw new Error('Respuesta inválida del servidor. Revisá que api.php exista y no esté devolviendo HTML.');
  }

  if (!res.ok || !json.ok) {
    throw new Error(json.error || 'Error del servidor');
  }

  return json;
}

function showError(error) {
  console.error(error);
  alert(error.message || 'Ocurrió un error');
}

/* =========================
   NAVIGATION
========================= */
function goTo(page) {
  document.querySelectorAll(".page").forEach(p => p.classList.remove("active"));

  const target = document.getElementById("page-" + page);
  if (!target) return;

  target.classList.add("active");

  if (page === "puerta") {
    renderPuerta();
  }

  if (page === "kioskito") {
  renderKioskito();
  renderSalesHistory();
  setTimeout(() => {
    instalarGuardarropas();
    renderGuardarropas();
  }, 100);
  }

  if (page === "usuarios") {
    renderUsuarios();
  }

  startLiveApp();
  window.scrollTo(0, 0);
}

/* =========================
   KIOSKITO STATE FROM MYSQL
========================= *//* =========================
   KIOSKITO — POS CON CARRITO
========================= */
let products = [];
let cart = {};
let editingProductId = null;
let editProductsMode = false;

function toggleEditProducts() {
  editProductsMode = !editProductsMode;
  renderKioskito();
}

async function renderKioskito() {
  const wrap = document.getElementById('k-categories');
  if (!wrap) return;

  try {
    const data = await api('products_list');
    products = data.products || [];

    const grouped = {};

    products.forEach(p => {
      const cat = p.cat || 'Otros';

      if (!grouped[cat]) {
        grouped[cat] = [];
      }

      grouped[cat].push(p);
    });

    if (!Object.keys(grouped).length) {
      wrap.innerHTML = `
        <div class="section">
          <div style="padding:18px 4px;color:var(--text2);">
            No hay productos cargados.
          </div>
        </div>
      `;
      renderCart();
      return;
    }

    wrap.innerHTML = Object.keys(grouped).map(cat => `
      <div class="section">
        <div class="section-title">${esc(cat)}</div>

        <div class="product-grid">
          ${grouped[cat].map(p => `
            <div class="pos-product-card" onclick="addToCart(${Number(p.id)})">
              ${editProductsMode ? `
                <button class="btn-edit-product" onclick="event.stopPropagation(); openEditProduct(${Number(p.id)})">
                  ✎
                </button>
              ` : ''}

              <div class="pos-product-name">${esc(p.name)}</div>
              <div class="pos-product-price">${fmt(p.price)}</div>
              ${p.sub ? `<div class="product-sub">${esc(p.sub)}</div>` : ''}
            </div>
          `).join('')}
        </div>
      </div>
    `).join('');

    renderCart();

  } catch (error) {
    showError(error);
  }
}
function addToCart(id) {
  cart[id] = (cart[id] || 0) + 1;
  renderCart();
}

function removeFromCart(id) {
  if (!cart[id]) return;
  cart[id]--;
  if (cart[id] <= 0) delete cart[id];
  renderCart();
}

function renderCart() {
  const saleDetail = document.getElementById('sale-detail');
  const totalEl = document.getElementById('k-total');
  const ids = Object.keys(cart);

  if (!ids.length) {
    if (saleDetail) saleDetail.innerHTML = `<div style="padding:14px 16px;font-size:14px;color:var(--text2);">Sin productos agregados.</div>`;
    if (totalEl) totalEl.textContent = '$0';
    return;
  }

  let total = 0;

  const rows = ids.map(id => {
    const product = products.find(p => Number(p.id) === Number(id));
    if (!product) return '';

    const qty = cart[id];
    const subtotal = qty * Number(product.price);
    total += subtotal;

    return `
      <div class="cart-item">
        <div class="cart-item-info">
          <div class="cart-item-name">${esc(product.name)} - ${fmt(product.price)} (x${qty})</div>
          <div class="cart-item-detail">Subtotal: ${fmt(subtotal)}</div>
        </div>
        <button class="cart-minus" onclick="removeFromCart(${Number(product.id)})">−</button>
      </div>
    `;
  }).join('');

  if (saleDetail) saleDetail.innerHTML = rows;
  if (totalEl) totalEl.textContent = fmt(total);
}

async function confirmCurrentSale() {
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

    const qty = cart[id];
    const subtotal = qty * Number(product.price);
    total += subtotal;

    lines.push({
      id: Number(product.id),
      name: product.name,
      qty,
      price: Number(product.price),
      subtotal
    });
  });

  if (!confirm(`Confirmar venta por ${fmt(total)}?`)) return;

  try {
    await api('sale_register', { items: lines, total });
  } catch (error) {
    showError(error);
    return;
  }

  if (confirm('¿Imprimir ticket?')) {
    printTicket(lines, total);
  }

  cart = {};
  renderCart();
  await renderSalesHistory();
}

function printTicket(lines, total) {
  let html = '<h2>Kioskito</h2><hr>';

  lines.forEach(item => {
    html += `<div>${esc(item.name)} x${item.qty} — ${fmt(item.subtotal)}</div>`;
  });

  html += `<hr><h3>Total: ${fmt(total)}</h3>`;

  const win = window.open('', '', 'width=300,height=600');
  win.document.write(html);
  win.document.close();
  win.print();
}

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
          <div style="padding:14px 16px;color:var(--text2);font-size:14px;">Sin ventas registradas.</div>
        </div>
      `;
      return;
    }

    const totalGlobal = sales.reduce((acc, sale) => acc + Number(sale.total), 0);

    wrap.innerHTML = `
      <div class="section">
        <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;">
          <span>Historial de ventas</span>
          <span style="font-size:13px;color:var(--text2);">Total: ${fmt(totalGlobal)}</span>
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
                  <span style="font-size:13px;font-weight:500;">${hora}</span>
                  <span style="font-size:12px;color:var(--text2);display:block;margin-top:2px;">
                    ${items.map(i => `${esc(i.name)} ×${Number(i.qty)}`).join(' · ')}
                  </span>
                </div>
                <div class="list-badge badge-green">${fmt(sale.total)}</div>
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

  document.getElementById('ap-name').value = product.name;
  document.getElementById('ap-price').value = product.price;
  document.getElementById('ap-cat').value = product.cat || 'Otros';

  const btn = document.getElementById('ap-submit-btn');
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
      await api('product_edit', { id: editingProductId, name, price, cat });
    } else {
      await api('product_add', { name, price, cat });
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
let collapsedDoorLists = {};
let lastChangedPersonId = null;

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

async function renderPuerta() {
  const wrap = document.getElementById('p-lists');
  if (!wrap) return;

  try {
    const data = await api('door_lists');
    doorLists = data.lists || [];
    drawPuerta();
  } catch (error) {
    showError(error);
  }
}
function drawPuerta() {
  const wrap = document.getElementById('p-lists');
  const searchInput = document.getElementById('list-search');
  const personSearchInput = document.getElementById('person-search');

  if (!wrap) return;

  const user = window.DIVINE_USER || {};
  const isAdmin = user.role === 'admin';

  const listTerm = isAdmin
    ? normalizeText(searchInput ? searchInput.value : '')
    : '';

  const personTerm = normalizeText(
    personSearchInput ? personSearchInput.value : ''
  );

  const visibleLists = doorLists.filter(list =>
    listMatchesSearch(list, listTerm) &&
    personMatchesSearch(list, personTerm)
  );

  if (!visibleLists.length) {
    wrap.innerHTML = `
      <div class="list-card">
        <div style="padding:18px 16px;color:var(--text2);">
          ${(listTerm || personTerm)
            ? 'No se encontraron resultados con esa búsqueda.'
            : 'Todavía no hay listas creadas.'}
        </div>
      </div>
    `;
    return;
  }

  wrap.innerHTML = visibleLists.map(list => {

    const stats = getListStats(list);
    const pricePerPerson = getListPrice(list);

    // SI BUSCA PERSONA => SE ABRE SOLO
    const collapsed = personTerm
      ? false
      : !!collapsedDoorLists[list.id];

    const owner = isAdmin && list.ownerName
      ? `
        <span style="
          font-size:11px;
          color:var(--text2);
          font-family:'DM Sans',sans-serif;
          display:block;
          margin-top:3px;
        ">
          Creada por: ${esc(list.ownerName)}
        </span>
      `
      : '';

    const canEditThisList =
      isAdmin || Number(list.userId) === Number(user.id);

    const statusControl = (person) => {
      const text =
        `${statusLabel(person.status)}${
          person.status === 'entro'
            ? ` · $${pricePerPerson.toLocaleString('es-AR')}`
            : ''
        }`;

      if (isAdmin) {
        return `
          <button
            class="btn-status ${esc(person.status)}"
            onclick="togglePersonStatus(${Number(list.id)}, ${Number(person.id)})"
          >
            ${text}
          </button>
        `;
      }

      return `
        <button
          class="btn-status ${esc(person.status)} btn-status-readonly"
          type="button"
          disabled
        >
          ${text}
        </button>
      `;
    };

    const deleteListButton = canEditThisList
      ? `
        <button
          class="list-delete"
          onclick="deleteList(${Number(list.id)})"
        >
          ✕
        </button>
      `
      : '';

    const quickAddButton = canEditThisList
      ? `
        <button
          class="btn-add-person btn-quick-add"
          onclick="toggleQuickAdd(${Number(list.id)}); event.stopPropagation();"
        >
          ＋
        </button>
      `
      : '';

    // FILTRADO REAL DE PERSONAS
    const filteredPeople = (list.people || []).filter(person => {
      if (!personTerm) return true;

      return (
        normalizeText(person.name).includes(personTerm) ||
        normalizeText(person.note || '').includes(personTerm)
      );
    });

    return `
      <div class="list-card ${collapsed ? 'collapsed' : ''}">

        <div class="list-header">
          <div class="list-name-txt" onclick="toggleCollapseList(${Number(list.id)})">

            <span class="list-collapse-icon">
              ${collapsed ? '▸' : '▾'}
            </span>

            ${esc(list.name)}

            ${list.isBirthday
              ? `
                <span
                  class="list-badge badge-orange"
                  style="margin-left:8px;vertical-align:middle;"
                >
                  Cumpleaños
                </span>
              `
              : ''
            }

            ${owner}

            <span style="
              font-size:12px;
              color:var(--text2);
              font-family:'DM Sans',sans-serif;
              display:block;
              margin-top:4px;
            ">
              ${stats.entered}
              (${stats.collected.toLocaleString('es-AR')} ARS)
              ·
              $${pricePerPerson.toLocaleString('es-AR')} c/u
            </span>
          </div>

          ${quickAddButton}

          <div class="list-badge badge-green">
            $${stats.collected.toLocaleString('es-AR')}
          </div>

          ${deleteListButton}
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
              <div class="person-row ${esc(person.status)}">

                <div class="person-info">
                  <div class="person-name">
                    ${esc(person.name)}
                  </div>

                  <div class="person-note">
                    ${esc(person.note || '')}
                  </div>
                </div>

                ${statusControl(person)}

                ${canEditThisList
                  ? `
                    <button
                      class="btn-del-person"
                      onclick="deletePerson(${Number(list.id)}, ${Number(person.id)})"
                    >
                      ✕
                    </button>
                  `
                  : ''
                }

              </div>
            `).join('')
            : `
              <div style="padding:8px 4px;color:var(--text2);">
                Sin personas en esta lista.
              </div>
            `
          }

        </div>

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

async function procesarListaPorLista(listId) {
  const textarea = document.querySelector(`.bulk-input[data-list-id="${listId}"]`);
  if (!textarea) return;

  const rawText = (textarea.value || '').trim();
  if (!rawText) return;

  const parsed = parseBulkText(rawText);
  if (!parsed.people.length) {
    alert('No se agregó nadie. Revisá que cada línea tenga nombre y dato/número al final.');
    return;
  }

  try {
    const data = await api('people_bulk', { listId, people: parsed.people });
    textarea.value = '';
    await renderPuerta();

    const ignoredTotal = Number(parsed.ignored || 0) + Number(data.ignored || 0);
    if (ignoredTotal > 0 || Number(data.repeated || 0) > 0) {
      alert(`Agregados: ${data.added || 0}\nRepetidos: ${data.repeated || 0}\nIgnorados por formato inválido: ${ignoredTotal}`);
    }
  } catch (error) {
    showError(error);
  }
}

function toggleCollapseList(listId) {
  collapsedDoorLists[listId] = !collapsedDoorLists[listId];
  drawPuerta();
}

function toggleQuickAdd(listId) {
  const panel = document.querySelector(`.quick-add-panel[data-list-id="${listId}"]`);
  if (!panel) return;

  panel.classList.toggle('hidden');

  if (!panel.classList.contains('hidden')) {
    setQuickAddMode(listId, 'manual');
    const nameInput = document.getElementById('person-name-' + listId);
    if (nameInput) setTimeout(() => nameInput.focus(), 80);
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

async function addList() {
  const birthdayCheck = document.getElementById('al-birthday');
  const isBirthday = !!(birthdayCheck && birthdayCheck.checked);

  try {
    const data = await api('list_add', { isBirthday });
    closeModal('modal-add-list');
    await renderPuerta();

    if (data.existing && data.message) {
      alert(data.message);
    }
  } catch (error) {
    showError(error);
  }
}

async function deleteList(id) {
  const list = doorLists.find(list => Number(list.id) === Number(id));
  if (!list) return;

  const ok = confirm(`¿Eliminar la lista "${list.name}" completa?`);
  if (!ok) return;

  try {
    await api('list_delete', { id });
    await renderPuerta();
  } catch (error) {
    showError(error);
  }
}

async function addPerson(listId) {
  const nameInput = document.getElementById('person-name-' + listId);
  const noteInput = document.getElementById('person-note-' + listId);
  if (!nameInput || !noteInput) return;

  const name = nameInput.value.trim();
  const note = noteInput.value.trim();

  if (!name || !note) {
    alert('Completá nombre y dato/número.');
    return;
  }

  try {
    await api('person_add', { listId, name, note });
    await renderPuerta();
  } catch (error) {
    showError(error);
  }
}

async function togglePersonStatus(listId, personId) {
  try {
    const data = await api('person_toggle_status', { listId, personId });
    lastChangedPersonId = Number(personId);
    await renderPuerta();

    setTimeout(() => {
      if (lastChangedPersonId === Number(personId)) {
        lastChangedPersonId = null;
        drawPuerta();
      }
    }, 580);
  } catch (error) {
    showError(error);
  }
}

async function deletePerson(listId, personId) {
  const ok = confirm('¿Eliminar esta persona?');
  if (!ok) return;

  try {
    await api('person_delete', { listId, personId });
    await renderPuerta();
  } catch (error) {
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

    const panelQuickAbierto = !!document.querySelector(".quick-add-panel:not(.hidden)");
    const pegandoLista = !!document.querySelector(".bulk-input:focus");

    if (
      puertaActiva &&
      !liveIsLoadingPuerta &&
      !estaEscribiendo &&
      !modalAbierto &&
      !panelQuickAbierto &&
      !pegandoLista
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
      } catch (e) {
        console.error("Error actualizando kioskito:", e);
      } finally {
        liveIsLoadingKioskito = false;
      }
    }
  }, 1000);
}

/* =========================
   GUARDARROPAS
========================= */
const GUARDARROPAS_PRECIO = 2000;

function instalarGuardarropas() {
  const kioskitoPage = document.getElementById("page-kioskito");
  if (!kioskitoPage) return;

  if (document.getElementById("guardarropas-box")) return;

  const salesHistory = document.getElementById("sales-history");
  if (!salesHistory) return;

  const box = document.createElement("div");
  box.id = "guardarropas-box";
  box.innerHTML = `
    <div class="section">
      <div class="section-title">Guardarropas</div>

      <div class="action-row">
        <button class="btn-action btn-add" type="button" onclick="abrirGuardarropas()">
          🧥 + Guardarropas
        </button>
      </div>

      <div class="total-bar">
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
        style="width:calc(100% - 28px);margin:0 14px 12px;padding:12px 14px;border-radius:14px;border:1px solid var(--border);background:var(--bg3);color:var(--text);outline:none;font-size:14px;"
      >

      <div id="gr-list"></div>
    </div>
  `;

  const wrap = document.querySelector(".page-kioskito-wrap");
  if (!wrap) return;

  wrap.insertAdjacentElement("afterbegin", box);
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

    const okDelete = confirm(
        '¿Eliminar este guardarropas?'
    );

    if (!okDelete) {
        return;
    }

    try {

        const res = await fetch(
            'api.php?action=guardarropas_delete',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id
                })
            }
        );

        const data = await res.json();

        if (!data.ok) {
            throw new Error(
                data.error || 'Error al eliminar'
            );
        }


    } catch (err) {

        alert(err.message);

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

/* =========================
   INIT
========================= */
window.addEventListener("load", () => {
  buildPinPad();
  goTo("menu");
  startLiveApp();
});