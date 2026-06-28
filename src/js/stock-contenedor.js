'use strict';

const stockState = {
  items: [],
  summary: { total: 0, empty: 0, low: 0, stock: 0 },
  filter: 'all',
  sector: 'externo',
  search: '',
  pendingIds: new Set(),
  deletingId: null,
  selectionAction: null,
  selectionSearch: '',
};

const stockEndpoint = window.location.pathname;
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
  if (!item.updatedAt) return 'Todavía no fue actualizado.';

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

  const separator = stockEndpoint.includes('?') ? '&' : '?';
  const response = await fetch(
    `${stockEndpoint}${separator}stock_action=${encodeURIComponent(action)}`,
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
  }, 2800);
}

function stockApplyData(data) {
  stockState.items = Array.isArray(data.items) ? data.items : [];
  stockState.summary = data.summary || { total: 0, empty: 0, low: 0, stock: 0 };
  stockUpdateCategoryOptions();
  stockUpdateManagementButtons();
  stockRender();

  if (document.getElementById('stockSelectModal')?.classList.contains('is-open')) {
    stockRenderSelection();
  }
}

function stockRenderSummary() {
  const summary = stockState.summary;
  const values = {
    summaryTotal: summary.total ?? stockState.items.length,
    summaryEmpty: summary.empty ?? 0,
    summaryLow: summary.low ?? 0,
    summaryStock: summary.stock ?? 0,
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
    const haystack = stockNormalize(`${item.name} ${item.category} ${item.code}`);
    const matchesSearch = query === '' || haystack.includes(query);
    return matchesSector && matchesFilter && matchesSearch;
  });
}

function stockRenderItem(item) {
  const itemId = Number(item.id);
  const pending = stockState.pendingIds.has(itemId);
  const quantity = Math.max(0, Number(item.quantity || 0));
  const minimum = Math.max(0, Number(item.minimum ?? item.lowThreshold ?? 0));
  const maximum = Math.max(1, Number(item.maximum ?? 100));
  const percentage = Math.min(100, Math.round((quantity / maximum) * 100));

  return `
    <article class="stock-item ${pending ? 'is-saving' : ''}" data-item-id="${itemId}" data-status="${stockEscape(item.status)}">
      <div class="stock-item-head">
        <div class="stock-item-name">
          <h4>${stockEscape(item.name)}</h4>
          <span>${stockEscape(item.category)}</span>
        </div>
        <span class="stock-status is-${stockEscape(item.status)}">${stockEscape(stockStatusLabel(item.status))}</span>
      </div>

      <div class="stock-limits" aria-label="Límites de stock">
        <span class="stock-limit-chip is-min"><small>Mín.</small><strong>${minimum}</strong></span>
        <span class="stock-limit-chip is-current"><small>Actual</small><strong>${quantity}</strong></span>
        <span class="stock-limit-chip is-max"><small>Máx.</small><strong>${maximum}</strong></span>
      </div>

      <div class="stock-capacity" aria-hidden="true">
        <span style="width:${percentage}%"></span>
      </div>

      <div class="stock-counter">
        <button
          type="button"
          class="stock-count-button is-minus"
          data-stock-adjust="-1"
          data-item-id="${itemId}"
          aria-label="Restar una unidad de ${stockEscape(item.name)}"
          ${quantity <= 0 || pending ? 'disabled' : ''}
        >−</button>

        <input
          type="number"
          class="stock-quantity-input"
          min="0"
          max="${maximum}"
          step="1"
          inputmode="numeric"
          value="${quantity}"
          data-stock-input
          data-item-id="${itemId}"
          aria-label="Cantidad de ${stockEscape(item.name)}. Máximo ${maximum}"
          ${pending ? 'disabled' : ''}
        >

        <button
          type="button"
          class="stock-count-button is-plus"
          data-stock-adjust="1"
          data-item-id="${itemId}"
          aria-label="Sumar una unidad de ${stockEscape(item.name)}"
          ${quantity >= maximum || pending ? 'disabled' : ''}
        >+</button>
      </div>

      <div class="stock-meta">${stockEscape(stockFormatUpdate(item))}</div>
    </article>
  `;
}

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
        <span>Probá con otra búsqueda o añadí un producto nuevo.</span>
        <button type="button" class="stock-primary-button stock-empty-add" data-stock-open-add>Añadir producto</button>
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

  container.innerHTML = [...grouped.entries()].map(([category, items]) => `
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
          <button type="button" class="stock-primary-button stock-retry-button" data-stock-retry>Reintentar</button>
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

function stockFindItem(id) {
  const itemId = Number(id);
  return stockState.items.find((item) => Number(item.id) === itemId) || null;
}

function stockUpdateManagementButtons() {
  const disabled = stockState.items.length === 0;
  const editButton = document.getElementById('stockEditButton');
  const deleteButton = document.getElementById('stockDeleteButton');

  if (editButton) editButton.disabled = disabled;
  if (deleteButton) deleteButton.disabled = disabled;
}

function stockSelectionItems() {
  const query = stockNormalize(stockState.selectionSearch);

  return [...stockState.items]
    .filter((item) => {
      const haystack = stockNormalize(`${item.name} ${item.category} ${item.sector} ${item.code}`);
      return query === '' || haystack.includes(query);
    })
    .sort((a, b) => {
      const sectorOrder = Number(a.sector !== stockState.sector) - Number(b.sector !== stockState.sector);
      return sectorOrder
        || String(a.category).localeCompare(String(b.category), 'es')
        || String(a.name).localeCompare(String(b.name), 'es');
    });
}

function stockRenderSelection() {
  const container = document.getElementById('stockSelectList');
  if (!container) return;

  const items = stockSelectionItems();
  const isDelete = stockState.selectionAction === 'delete';

  if (!items.length) {
    container.innerHTML = `
      <div class="stock-select-empty">
        <span aria-hidden="true">⌕</span>
        <strong>No se encontraron productos.</strong>
        <small>Probá con otro nombre o categoría.</small>
      </div>
    `;
    return;
  }

  container.innerHTML = items.map((item) => `
    <button
      type="button"
      class="stock-select-item ${isDelete ? 'is-danger' : ''}"
      data-stock-select-item="${Number(item.id)}"
      role="listitem"
    >
      <span class="stock-select-item-main">
        <strong>${stockEscape(item.name)}</strong>
        <small>${stockEscape(item.category)} · ${item.sector === 'externo' ? 'Externo' : 'Interno'}</small>
      </span>
      <span class="stock-select-item-side">
        <b>${Number(item.quantity || 0)} / ${Number(item.maximum ?? 100)}</b>
        <small>Mín. ${Number(item.minimum ?? item.lowThreshold ?? 0)}</small>
      </span>
      <span class="stock-select-arrow" aria-hidden="true">›</span>
    </button>
  `).join('');
}

function stockOpenSelector(action) {
  if (!['edit', 'delete'].includes(action)) return;

  if (!stockState.items.length) {
    stockShowToast('No hay productos para administrar.');
    return;
  }

  stockState.selectionAction = action;
  stockState.selectionSearch = '';

  const isDelete = action === 'delete';
  const modal = document.getElementById('stockSelectModal');
  const input = document.getElementById('stockSelectSearch');

  document.getElementById('stockSelectEyebrow').textContent = isDelete
    ? 'ELIMINAR ARTÍCULO'
    : 'EDITAR ARTÍCULO';
  document.getElementById('stockSelectTitle').textContent = isDelete
    ? 'Elegí qué producto eliminar'
    : 'Elegí qué producto editar';
  document.getElementById('stockSelectHelp').textContent = isDelete
    ? 'Seleccioná un producto para revisar la eliminación antes de confirmarla.'
    : 'Seleccioná un producto para abrir sus datos y modificarlos.';

  if (input) input.value = '';
  modal?.classList.toggle('is-delete-mode', isDelete);
  stockRenderSelection();
  stockOpenModal(modal);
  window.setTimeout(() => input?.focus(), 80);
}

function stockCloseSelector() {
  stockCloseModal(document.getElementById('stockSelectModal'));
  stockState.selectionAction = null;
  stockState.selectionSearch = '';
}

function stockChooseSelectedItem(id) {
  const item = stockFindItem(id);
  const action = stockState.selectionAction;
  if (!item || !action) return;

  stockCloseSelector();

  if (action === 'edit') {
    stockOpenProduct(item);
    return;
  }

  stockOpenDelete(item);
}

function stockUpdateCategoryOptions() {
  const datalist = document.getElementById('stockCategoryOptions');
  if (!datalist) return;

  const categories = [...new Set(
    stockState.items
      .map((item) => String(item.category || '').trim())
      .filter(Boolean),
  )].sort((a, b) => a.localeCompare(b, 'es'));

  datalist.innerHTML = categories
    .map((category) => `<option value="${stockEscape(category)}"></option>`)
    .join('');
}

function stockRefreshBodyLock() {
  const hasOpenModal = Boolean(document.querySelector('.stock-modal.is-open'));
  document.body.style.overflow = hasOpenModal ? 'hidden' : '';
}

function stockOpenModal(modal) {
  if (!modal) return;
  modal.classList.add('is-open');
  modal.setAttribute('aria-hidden', 'false');
  stockRefreshBodyLock();
}

function stockCloseModal(modal) {
  if (!modal) return;
  modal.classList.remove('is-open');
  modal.setAttribute('aria-hidden', 'true');
  stockRefreshBodyLock();
}

function stockSetProductError(message = '') {
  const errorBox = document.getElementById('stockProductError');
  if (!errorBox) return;
  errorBox.textContent = message;
  errorBox.classList.toggle('is-visible', message !== '');
}

function stockOpenProduct(item = null) {
  const modal = document.getElementById('stockProductModal');
  const form = document.getElementById('stockProductForm');
  if (!modal || !form) return;

  form.reset();
  stockSetProductError();

  const editing = Boolean(item);
  document.getElementById('stockProductId').value = editing ? String(item.id) : '';
  document.getElementById('stockProductName').value = editing ? item.name : '';
  document.getElementById('stockProductCategory').value = editing ? item.category : '';
  document.getElementById('stockProductSector').value = editing ? item.sector : stockState.sector;
  document.getElementById('stockProductQuantity').value = editing ? String(item.quantity) : '0';
  document.getElementById('stockProductMinimum').value = editing ? String(item.minimum ?? item.lowThreshold ?? 4) : '4';
  document.getElementById('stockProductMaximum').value = editing ? String(item.maximum ?? 100) : '100';

  document.getElementById('stockProductEyebrow').textContent = editing ? 'EDITAR ARTÍCULO' : 'NUEVO ARTÍCULO';
  document.getElementById('stockProductTitle').textContent = editing ? 'Editar producto' : 'Añadir producto';
  document.getElementById('stockProductSubmit').textContent = editing ? 'Guardar cambios' : 'Guardar producto';

  stockOpenModal(modal);
  window.setTimeout(() => document.getElementById('stockProductName')?.focus(), 80);
}

function stockCloseProduct() {
  const modal = document.getElementById('stockProductModal');
  stockCloseModal(modal);
  stockSetProductError();
}

async function stockSubmitProduct(event) {
  event.preventDefault();

  const form = event.currentTarget;
  if (!(form instanceof HTMLFormElement) || !form.reportValidity()) return;

  const submit = document.getElementById('stockProductSubmit');
  const id = Number(document.getElementById('stockProductId').value || 0);
  const action = id > 0 ? 'edit' : 'add';

  const payload = {
    name: document.getElementById('stockProductName').value.trim(),
    category: document.getElementById('stockProductCategory').value.trim(),
    sector: document.getElementById('stockProductSector').value,
    quantity: Number(document.getElementById('stockProductQuantity').value),
    minimum: Number(document.getElementById('stockProductMinimum').value),
    maximum: Number(document.getElementById('stockProductMaximum').value),
  };

  if (payload.minimum > payload.maximum) {
    stockSetProductError('El stock mínimo no puede ser mayor que el máximo.');
    return;
  }

  if (payload.quantity > payload.maximum) {
    stockSetProductError('La cantidad actual no puede superar el stock máximo.');
    return;
  }

  if (id > 0) payload.id = id;

  stockSetProductError();
  submit.disabled = true;
  submit.textContent = action === 'edit' ? 'Guardando cambios…' : 'Guardando…';

  try {
    const data = await stockRequest(action, payload);
    stockApplyData(data);
    stockCloseProduct();
    stockShowToast(data.message || (action === 'edit' ? 'Producto actualizado.' : 'Producto añadido.'));
  } catch (error) {
    stockSetProductError(error.message);
  } finally {
    submit.disabled = false;
    submit.textContent = action === 'edit' ? 'Guardar cambios' : 'Guardar producto';
  }
}

function stockOpenDelete(item) {
  if (!item) return;

  stockState.deletingId = Number(item.id);
  const description = document.getElementById('stockDeleteDescription');
  if (description) {
    description.textContent = `Vas a eliminar “${item.name}”. También se eliminará su historial de movimientos y no se puede deshacer.`;
  }

  stockOpenModal(document.getElementById('stockDeleteModal'));
  window.setTimeout(() => document.getElementById('stockDeleteConfirm')?.focus(), 80);
}

function stockCloseDelete() {
  stockState.deletingId = null;
  stockCloseModal(document.getElementById('stockDeleteModal'));
}

async function stockConfirmDelete() {
  const itemId = Number(stockState.deletingId);
  const button = document.getElementById('stockDeleteConfirm');

  if (!Number.isInteger(itemId) || itemId <= 0 || !button) return;

  button.disabled = true;
  button.textContent = 'Eliminando…';

  try {
    const data = await stockRequest('delete', { id: itemId });
    stockApplyData(data);
    stockCloseDelete();
    stockShowToast(data.message || 'Producto eliminado.');
  } catch (error) {
    stockShowToast(error.message);
  } finally {
    button.disabled = false;
    button.textContent = 'Eliminar';
  }
}

function stockBuildReport() {
  const lowItems = stockState.items
    .filter((item) => item.sector === stockState.sector)
    .filter((item) => Number(item.quantity) <= Number(item.minimum ?? item.lowThreshold ?? 0))
    .sort((a, b) => Number(a.quantity) - Number(b.quantity) || a.name.localeCompare(b.name, 'es'));

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
  const sectorLabel = stockState.sector === 'externo' ? 'EXTERNO' : 'INTERNO';

  const lines = [
    `FALTANTES DEL CONTENEDOR — ${sectorLabel} — ${date} ${time}`,
    '',
  ];

  if (!lowItems.length) {
    lines.push('✅ No hay productos agotados ni con stock bajo.');
    return lines.join('\n');
  }

  const empty = lowItems.filter((item) => Number(item.quantity) === 0);
  const low = lowItems.filter((item) => Number(item.quantity) > 0);

  if (empty.length) {
    lines.push('🔴 AGOTADOS');
    empty.forEach((item) => lines.push(`• ${item.name} — 0 (mín. ${item.minimum ?? item.lowThreshold ?? 0} / máx. ${item.maximum ?? 100})`));
    lines.push('');
  }

  if (low.length) {
    lines.push('🟠 STOCK BAJO');
    low.forEach((item) => lines.push(`• ${item.name} — quedan ${item.quantity} (mín. ${item.minimum ?? item.lowThreshold ?? 0} / máx. ${item.maximum ?? 100})`));
    lines.push('');
  }

  lines.push(`Total para reponer: ${lowItems.length} artículos.`);
  return lines.join('\n').trim();
}

function stockOpenReport() {
  const modal = document.getElementById('stockReportModal');
  const text = document.getElementById('stockReportText');
  if (!modal || !text) return;

  text.value = stockBuildReport();
  stockOpenModal(modal);
}

function stockCloseReport() {
  stockCloseModal(document.getElementById('stockReportModal'));
}

async function stockCopyReport() {
  const text = document.getElementById('stockReportText')?.value || stockBuildReport();

  try {
    await navigator.clipboard.writeText(text);
    stockShowToast('Lista copiada.');
  } catch {
    const textarea = document.getElementById('stockReportText');
    if (!textarea) return;
    textarea.removeAttribute('readonly');
    textarea.select();
    document.execCommand('copy');
    textarea.setAttribute('readonly', 'readonly');
    stockShowToast('Lista copiada.');
  }
}

async function stockShareReport() {
  const text = document.getElementById('stockReportText')?.value || stockBuildReport();

  if (navigator.share) {
    try {
      await navigator.share({ title: 'Faltantes del contenedor', text });
      return;
    } catch (error) {
      if (error?.name === 'AbortError') return;
    }
  }

  await stockCopyReport();
  stockShowToast('La lista fue copiada para compartirla.');
}

function stockBindEvents() {
  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const adjustButton = target.closest('[data-stock-adjust]');
    if (adjustButton) {
      stockUpdateItem(
        adjustButton.dataset.itemId,
        'adjust',
        { delta: Number(adjustButton.dataset.stockAdjust) },
      );
      return;
    }


    const selectedItem = target.closest('[data-stock-select-item]');
    if (selectedItem) {
      stockChooseSelectedItem(selectedItem.dataset.stockSelectItem);
      return;
    }

    if (target.closest('[data-stock-open-add]')) {
      stockOpenProduct();
      return;
    }

    if (target.closest('[data-stock-retry]')) {
      stockLoad();
      return;
    }

    const filterButton = target.closest('[data-filter]');
    if (filterButton) {
      stockState.filter = filterButton.dataset.filter || 'all';
      document.querySelectorAll('[data-filter]').forEach((button) => {
        button.classList.toggle('is-active', button === filterButton);
      });
      stockRender();
      return;
    }

    const sectorButton = target.closest('[data-sector]');
    if (sectorButton) {
      stockState.sector = sectorButton.dataset.sector || 'externo';
      document.querySelectorAll('[data-sector]').forEach((button) => {
        button.classList.toggle('is-active', button === sectorButton);
      });
      stockRender();
      return;
    }

    if (target.closest('[data-close-report]')) {
      stockCloseReport();
      return;
    }

    if (target.closest('[data-close-product]')) {
      stockCloseProduct();
      return;
    }

    if (target.closest('[data-close-select]')) {
      stockCloseSelector();
      return;
    }

    if (target.closest('[data-close-delete]')) {
      stockCloseDelete();
    }
  });

  document.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const input = target.closest('[data-stock-input]');
    if (!input) return;

    const quantity = Number(input.value);
    const item = stockFindItem(input.dataset.itemId);
    const maximum = Math.max(1, Number(item?.maximum ?? 100));

    if (!Number.isInteger(quantity) || quantity < 0 || quantity > maximum) {
      stockShowToast(`La cantidad debe ser un número entero entre 0 y ${maximum}.`);
      stockRender();
      return;
    }

    stockUpdateItem(input.dataset.itemId, 'set', { quantity });
  });

  document.getElementById('stockSearch')?.addEventListener('input', (event) => {
    stockState.search = event.target.value || '';
    stockRender();
  });

  document.getElementById('stockAddButton')?.addEventListener('click', () => stockOpenProduct());
  document.getElementById('stockEditButton')?.addEventListener('click', () => stockOpenSelector('edit'));
  document.getElementById('stockDeleteButton')?.addEventListener('click', () => stockOpenSelector('delete'));
  document.getElementById('stockSelectSearch')?.addEventListener('input', (event) => {
    stockState.selectionSearch = event.target.value || '';
    stockRenderSelection();
  });
  document.getElementById('stockProductForm')?.addEventListener('submit', stockSubmitProduct);
  document.getElementById('stockDeleteConfirm')?.addEventListener('click', stockConfirmDelete);
  document.getElementById('stockReportButton')?.addEventListener('click', stockOpenReport);
  document.getElementById('stockCopyButton')?.addEventListener('click', stockCopyReport);
  document.getElementById('stockShareButton')?.addEventListener('click', stockShareReport);

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    if (document.getElementById('stockDeleteModal')?.classList.contains('is-open')) {
      stockCloseDelete();
      return;
    }

    if (document.getElementById('stockProductModal')?.classList.contains('is-open')) {
      stockCloseProduct();
      return;
    }

    if (document.getElementById('stockSelectModal')?.classList.contains('is-open')) {
      stockCloseSelector();
      return;
    }

    if (document.getElementById('stockReportModal')?.classList.contains('is-open')) {
      stockCloseReport();
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  stockBindEvents();
  stockLoad();
});
