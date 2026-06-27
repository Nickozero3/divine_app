'use strict';

const stockState = {
  items: [],
  summary: { total: 0, empty: 0, low: 0, stock: 0 },
  filter: 'all',
  sector: 'externo',
  search: '',
  pendingIds: new Set(),
};

const stockEndpoint = 'stock_contenedor.php';
let stockToastTimer = null;

function stockEscape(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function stockNormalize(value) {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

function stockStatusLabel(status) {
  if (status === 'empty') return 'Agotado';
  if (status === 'low') return 'Stock bajo';
  return 'En stock';
}

function stockFormatUpdate(item) {
  if (!item.updatedAt) {
    return 'Todavía no fue actualizado.';
  }

  const raw = String(item.updatedAt).replace(' ', 'T');
  const date = new Date(raw);

  if (Number.isNaN(date.getTime())) {
    return item.updatedBy
      ? `Actualizado por ${item.updatedBy}`
      : 'Actualizado recientemente';
  }

  const formatted = date.toLocaleString('es-AR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });

  return item.updatedBy
    ? `Actualizado ${formatted} por ${item.updatedBy}`
    : `Actualizado ${formatted}`;
}

async function stockRequest(action, payload = null) {
  const options = {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  };

  if (payload !== null) {
    options.method = 'POST';
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(payload);
  }

  const response = await fetch(
    `${stockEndpoint}?stock_action=${encodeURIComponent(action)}`,
    options,
  );

  const text = await response.text();
  let data;

  try {
    data = JSON.parse(text);
  } catch {
    throw new Error(`El servidor no devolvió una respuesta válida. HTTP ${response.status}`);
  }

  if (!response.ok || !data.ok) {
    throw new Error(data.error || 'No se pudo actualizar el stock.');
  }

  return data;
}

function stockShowToast(message) {
  const toast = document.getElementById('stockToast');
  if (!toast) return;

  toast.textContent = message;
  toast.classList.add('is-visible');

  window.clearTimeout(stockToastTimer);
  stockToastTimer = window.setTimeout(() => {
    toast.classList.remove('is-visible');
  }, 2400);
}

function stockApplyData(data) {
  stockState.items = Array.isArray(data.items) ? data.items : [];
  stockState.summary = data.summary || { total: 0, empty: 0, low: 0, stock: 0 };
  stockRender();
}

function stockRenderSummary() {
  const sectorItems = stockState.items.filter((item) => item.sector === stockState.sector);
  const summary = {
    total: sectorItems.length,
    empty: sectorItems.filter((item) => item.status === 'empty').length,
    low: sectorItems.filter((item) => item.status === 'low').length,
    stock: sectorItems.filter((item) => item.status === 'stock').length,
  };

  const values = {
    summaryTotal: summary.total,
    summaryEmpty: summary.empty,
    summaryLow: summary.low,
    summaryStock: summary.stock,
  };

  Object.entries(values).forEach(([id, value]) => {
    const element = document.getElementById(id);
    if (element) element.textContent = String(value);
  });
}

function stockVisibleItems() {
  const query = stockNormalize(stockState.search);

  return stockState.items.filter((item) => {
    const matchesSector = item.sector === stockState.sector;
    const matchesFilter = stockState.filter === 'all' || item.status === stockState.filter;
    const haystack = stockNormalize(`${item.name} ${item.category} ${item.sector}`);
    const matchesSearch = query === '' || haystack.includes(query);
    return matchesSector && matchesFilter && matchesSearch;
  });
}

function stockRenderItem(item) {
  const pending = stockState.pendingIds.has(Number(item.id));
  const quantity = Number(item.quantity || 0);

  return `
    <article class="stock-item ${pending ? 'is-saving' : ''}" data-item-id="${Number(item.id)}" data-status="${stockEscape(item.status)}">
      <div class="stock-item-head">
        <div class="stock-item-name">
          <h4>${stockEscape(item.name)}</h4>
          <span>${stockEscape(item.category)}</span>
        </div>
        <span class="stock-status is-${stockEscape(item.status)}">${stockEscape(stockStatusLabel(item.status))}</span>
      </div>

      <div class="stock-counter">
        <button
          type="button"
          class="stock-count-button is-minus"
          data-stock-adjust="-1"
          data-item-id="${Number(item.id)}"
          aria-label="Restar una unidad de ${stockEscape(item.name)}"
        >−</button>

        <input
          type="number"
          class="stock-quantity-input"
          min="0"
          max="100000"
          step="1"
          inputmode="numeric"
          value="${quantity}"
          data-stock-input
          data-item-id="${Number(item.id)}"
          aria-label="Cantidad de ${stockEscape(item.name)}"
        >

        <button
          type="button"
          class="stock-count-button is-plus"
          data-stock-adjust="1"
          data-item-id="${Number(item.id)}"
          aria-label="Sumar una unidad de ${stockEscape(item.name)}"
        >+</button>
      </div>

      <div class="stock-meta">${stockEscape(stockFormatUpdate(item))}</div>
    </article>
  `;
}

const stockCategoryOrder = ['Bebidas alcohólicas', 'Vinos y espumantes', 'Insumos', 'Gaseosas'];

function stockRender() {
  stockRenderSummary();

  const container = document.getElementById('stockContent');
  if (!container) return;

  const visibleItems = stockVisibleItems();

  if (!visibleItems.length) {
    container.innerHTML = `
      <div class="stock-empty-state">
        <div style="font-size:34px">📦</div>
        <strong>No hay artículos para este filtro.</strong>
        <span>Probá con otra búsqueda o cambiá el sector seleccionado.</span>
      </div>
    `;
    return;
  }

  const grouped = new Map();

  visibleItems.forEach((item) => {
    const category = item.category || 'Otros';
    if (!grouped.has(category)) grouped.set(category, []);
    grouped.get(category).push(item);
  });

  const orderedGroups = [...grouped.entries()].sort(([categoryA], [categoryB]) => {
    const indexA = stockCategoryOrder.indexOf(categoryA);
    const indexB = stockCategoryOrder.indexOf(categoryB);
    const safeA = indexA === -1 ? Number.MAX_SAFE_INTEGER : indexA;
    const safeB = indexB === -1 ? Number.MAX_SAFE_INTEGER : indexB;
    return safeA - safeB || categoryA.localeCompare(categoryB, 'es');
  });

  container.innerHTML = orderedGroups.map(([category, items]) => `
    <section class="stock-category">
      <div class="stock-category-head">
        <h3>${stockEscape(category)}</h3>
        <span>${items.length} ${items.length === 1 ? 'artículo' : 'artículos'}</span>
      </div>
      <div class="stock-grid">
        ${items.map(stockRenderItem).join('')}
      </div>
    </section>
  `).join('');
}

async function stockLoad() {
  const container = document.getElementById('stockContent');

  try {
    const data = await stockRequest('list');
    stockApplyData(data);
  } catch (error) {
    if (container) {
      container.innerHTML = `
        <div class="stock-empty-state">
          <div style="font-size:34px">⚠️</div>
          <strong>No se pudo cargar el stock.</strong>
          <span>${stockEscape(error.message)}</span>
          <button type="button" class="stock-primary-button" style="padding:0 18px" onclick="stockLoad()">Reintentar</button>
        </div>
      `;
    }
  }
}

async function stockUpdateItem(id, action, payload) {
  const itemId = Number(id);

  if (!Number.isInteger(itemId) || itemId <= 0 || stockState.pendingIds.has(itemId)) {
    return;
  }

  stockState.pendingIds.add(itemId);
  stockRender();

  try {
    const data = await stockRequest(action, { id: itemId, ...payload });
    stockApplyData(data);
  } catch (error) {
    stockShowToast(error.message);
    await stockLoad();
  } finally {
    stockState.pendingIds.delete(itemId);
    stockRender();
  }
}

function stockBuildReport() {
  const lowItems = stockState.items
    .filter((item) => Number(item.quantity) <= Number(item.lowThreshold ?? 4))
    .sort((a, b) => {
      const sectorOrder = { externo: 0, interno: 1 };
      const sectorDiff = (sectorOrder[a.sector] ?? 99) - (sectorOrder[b.sector] ?? 99);
      if (sectorDiff !== 0) return sectorDiff;

      const categoryA = stockCategoryOrder.indexOf(a.category);
      const categoryB = stockCategoryOrder.indexOf(b.category);
      const safeA = categoryA === -1 ? 99 : categoryA;
      const safeB = categoryB === -1 ? 99 : categoryB;

      return safeA - safeB || Number(a.quantity) - Number(b.quantity) || a.name.localeCompare(b.name, 'es');
    });

  const now = new Date();
  const date = now.toLocaleDateString('es-AR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
  const time = now.toLocaleTimeString('es-AR', {
    hour: '2-digit',
    minute: '2-digit',
  });

  const lines = [
    `FALTANTES DEL CONTENEDOR — ${date} ${time}`,
    '',
  ];

  if (!lowItems.length) {
    lines.push('✅ No hay productos agotados ni con stock bajo.');
    return lines.join('\n');
  }

  const sectors = [
    ['externo', '🚚 EXTERNO — CAJAS DE BEBIDAS'],
    ['interno', '🏠 INTERNO — INSUMOS Y GASEOSAS'],
  ];

  sectors.forEach(([sector, sectorTitle]) => {
    const sectorItems = lowItems.filter((item) => item.sector === sector);
    if (!sectorItems.length) return;

    lines.push(sectorTitle);

    const grouped = new Map();
    sectorItems.forEach((item) => {
      const category = item.category || 'Otros';
      if (!grouped.has(category)) grouped.set(category, []);
      grouped.get(category).push(item);
    });

    [...grouped.entries()]
      .sort(([a], [b]) => {
        const ia = stockCategoryOrder.indexOf(a);
        const ib = stockCategoryOrder.indexOf(b);
        return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib) || a.localeCompare(b, 'es');
      })
      .forEach(([category, items]) => {
        lines.push(category.toUpperCase());
        items.forEach((item) => {
          const quantity = Number(item.quantity);
          lines.push(quantity === 0
            ? `• ${item.name} — AGOTADO`
            : `• ${item.name} — quedan ${quantity}`);
        });
        lines.push('');
      });
  });

  lines.push(`Total para reponer: ${lowItems.length} artículos.`);

  return lines.join('\n').trim();
}

function stockOpenReport() {
  const modal = document.getElementById('stockReportModal');
  const text = document.getElementById('stockReportText');
  if (!modal || !text) return;

  text.value = stockBuildReport();
  modal.classList.add('is-open');
  modal.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
}

function stockCloseReport() {
  const modal = document.getElementById('stockReportModal');
  if (!modal) return;

  modal.classList.remove('is-open');
  modal.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
}

async function stockCopyReport() {
  const text = document.getElementById('stockReportText')?.value || stockBuildReport();

  try {
    await navigator.clipboard.writeText(text);
    stockShowToast('Lista copiada.');
  } catch {
    const textarea = document.getElementById('stockReportText');
    if (textarea) {
      textarea.removeAttribute('readonly');
      textarea.select();
      document.execCommand('copy');
      textarea.setAttribute('readonly', 'readonly');
      stockShowToast('Lista copiada.');
    }
  }
}

async function stockShareReport() {
  const text = document.getElementById('stockReportText')?.value || stockBuildReport();

  if (navigator.share) {
    try {
      await navigator.share({
        title: 'Faltantes del contenedor',
        text,
      });
      return;
    } catch (error) {
      if (error?.name === 'AbortError') return;
    }
  }

  await stockCopyReport();
  stockShowToast('Tu dispositivo no permite compartir directamente; la lista fue copiada.');
}

function stockBindEvents() {
  document.addEventListener('click', (event) => {
    const adjustButton = event.target.closest('[data-stock-adjust]');
    if (adjustButton) {
      stockUpdateItem(
        adjustButton.dataset.itemId,
        'adjust',
        { delta: Number(adjustButton.dataset.stockAdjust) },
      );
      return;
    }

    const sectorButton = event.target.closest('[data-sector]');
    if (sectorButton) {
      stockState.sector = sectorButton.dataset.sector || 'externo';
      document.querySelectorAll('[data-sector]').forEach((button) => {
        button.classList.toggle('is-active', button === sectorButton);
      });
      stockRender();
      return;
    }

    const filterButton = event.target.closest('[data-filter]');
    if (filterButton) {
      stockState.filter = filterButton.dataset.filter || 'all';
      document.querySelectorAll('[data-filter]').forEach((button) => {
        button.classList.toggle('is-active', button === filterButton);
      });
      stockRender();
      return;
    }

    if (event.target.closest('[data-close-report]')) {
      stockCloseReport();
    }
  });

  document.addEventListener('change', (event) => {
    const input = event.target.closest('[data-stock-input]');
    if (!input) return;

    const quantity = Math.max(0, Math.min(100000, Number.parseInt(input.value, 10) || 0));
    input.value = String(quantity);
    stockUpdateItem(input.dataset.itemId, 'set', { quantity });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && event.target.matches('[data-stock-input]')) {
      event.target.blur();
    }

    if (event.key === 'Escape') {
      stockCloseReport();
    }
  });

  document.getElementById('stockSearch')?.addEventListener('input', (event) => {
    stockState.search = event.target.value || '';
    stockRender();
  });

  document.getElementById('stockReportButton')?.addEventListener('click', stockOpenReport);
  document.getElementById('stockCopyButton')?.addEventListener('click', stockCopyReport);
  document.getElementById('stockShareButton')?.addEventListener('click', stockShareReport);
}

window.stockLoad = stockLoad;

document.addEventListener('DOMContentLoaded', () => {
  stockBindEvents();
  stockLoad();
});
