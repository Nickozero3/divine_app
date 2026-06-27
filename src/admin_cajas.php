<?php
/**
 * Historial de cajas cerradas integrado en admin.php.
 *
 * Insertar dentro de <main>, preferentemente antes de la sección de QR:
 * require __DIR__ . '/admin_cajas.php';
 */
?>
<section class="adm-section adm-cash-history" aria-labelledby="cash-history-title">
  <article class="adm-panel adm-panel--wide">
    <header class="adm-panel__header adm-cash-history__header">
      <div>
        <span class="adm-panel__eyebrow">Kioskito</span>
        <h2 id="cash-history-title">Historial de cajas cerradas</h2>
        <p>Consultá cada cierre, revisá la recaudación y eliminá registros del historial.</p>
      </div>

      <div class="adm-cash-history__actions">
        <span class="adm-cash-history__count" id="closed-cashes-count" aria-live="polite">Cargando…</span>
        <button class="adm-button adm-cash-history__refresh" type="button" id="closed-cashes-refresh">
          Actualizar
        </button>
      </div>
    </header>

    <div class="adm-cash-history__summary" aria-label="Resumen de cajas cerradas">
      <div class="adm-cash-history__stat">
        <span>Cajas visibles</span>
        <strong id="closed-cashes-total-count">—</strong>
      </div>
      <div class="adm-cash-history__stat">
        <span>Total cerrado</span>
        <strong id="closed-cashes-total-amount">—</strong>
      </div>
      <div class="adm-cash-history__stat">
        <span>Ventas incluidas</span>
        <strong id="closed-cashes-sales-count">—</strong>
      </div>
      <div class="adm-cash-history__stat">
        <span>Último cierre</span>
        <strong id="closed-cashes-last-date">—</strong>
      </div>
    </div>

    <div class="adm-cash-history__list" id="admin-closed-cashes" aria-live="polite">
      <div class="adm-loading-list" aria-hidden="true"><span></span><span></span><span></span></div>
    </div>
  </article>
</section>

<style>
  .adm-cash-history__header {
    align-items: flex-start;
    gap: 18px;
  }

  .adm-cash-history__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
  }

  .adm-cash-history__count {
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    padding: 0 12px;
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 999px;
    color: var(--text2, #b9ac8a);
    background: rgba(255, 255, 255, .035);
    font-size: 11px;
    font-weight: 850;
  }

  .adm-cash-history__refresh {
    min-height: 40px;
    padding-inline: 15px;
    cursor: pointer;
  }

  .adm-cash-history__refresh:disabled {
    cursor: wait;
    opacity: .65;
  }

  .adm-cash-history__summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    padding: 0 20px 20px;
  }

  .adm-cash-history__stat {
    min-width: 0;
    display: grid;
    gap: 7px;
    padding: 15px;
    border: 1px solid rgba(255, 255, 255, .075);
    border-radius: 17px;
    background: rgba(255, 255, 255, .025);
  }

  .adm-cash-history__stat span {
    color: var(--text2, #b9ac8a);
    font-size: 10px;
    font-weight: 850;
    letter-spacing: .08em;
    text-transform: uppercase;
  }

  .adm-cash-history__stat strong {
    overflow: hidden;
    color: var(--text, #f7f1df);
    font-size: clamp(17px, 2.3vw, 23px);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .adm-cash-history__list {
    display: grid;
    gap: 12px;
    padding: 0 20px 20px;
  }

  .adm-cash-card {
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 20px;
    background:
      radial-gradient(circle at 96% 0%, color-mix(in srgb, var(--green, #36c985) 9%, transparent), transparent 30%),
      rgba(255, 255, 255, .025);
  }

  .adm-cash-card__main {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 18px;
    padding: 17px;
  }

  .adm-cash-card__identity {
    min-width: 0;
    display: flex;
    align-items: flex-start;
    gap: 12px;
  }

  .adm-cash-card__number {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    display: grid;
    place-items: center;
    border: 1px solid color-mix(in srgb, var(--green, #36c985) 34%, transparent);
    border-radius: 14px;
    color: var(--green, #36c985);
    background: color-mix(in srgb, var(--green, #36c985) 10%, transparent);
    font-size: 12px;
    font-weight: 950;
  }

  .adm-cash-card__copy {
    min-width: 0;
    display: grid;
    gap: 4px;
  }

  .adm-cash-card__copy strong {
    color: var(--text, #f7f1df);
    font-size: 15px;
  }

  .adm-cash-card__copy span {
    color: var(--text2, #b9ac8a);
    font-size: 12px;
    line-height: 1.45;
  }

  .adm-cash-card__amount {
    display: grid;
    justify-items: end;
    gap: 8px;
  }

  .adm-cash-card__amount strong {
    color: var(--gold-2, #f0d48d);
    font-size: 22px;
    white-space: nowrap;
  }

  .adm-cash-delete {
    min-height: 38px;
    padding: 0 13px;
    border: 1px solid color-mix(in srgb, var(--red, #ff5f6d) 35%, transparent);
    border-radius: 12px;
    color: #ff8b96;
    background: color-mix(in srgb, var(--red, #ff5f6d) 9%, transparent);
    cursor: pointer;
    font-size: 11px;
    font-weight: 900;
  }

  .adm-cash-delete:hover {
    border-color: color-mix(in srgb, var(--red, #ff5f6d) 65%, transparent);
    background: color-mix(in srgb, var(--red, #ff5f6d) 15%, transparent);
  }

  .adm-cash-delete:disabled {
    cursor: wait;
    opacity: .6;
  }

  .adm-cash-card__payments {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    padding: 0 17px 17px;
  }

  .adm-cash-payment {
    min-width: 0;
    display: grid;
    gap: 4px;
    padding: 11px 12px;
    border: 1px solid rgba(255, 255, 255, .065);
    border-radius: 13px;
    background: rgba(0, 0, 0, .09);
  }

  .adm-cash-payment span {
    color: var(--text2, #b9ac8a);
    font-size: 10px;
    font-weight: 800;
  }

  .adm-cash-payment strong {
    overflow: hidden;
    color: var(--text, #f7f1df);
    font-size: 13px;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .adm-cash-products {
    margin: 0 17px 17px;
    border-top: 1px solid rgba(255, 255, 255, .07);
  }

  .adm-cash-products summary {
    padding: 13px 0 4px;
    color: var(--text2, #b9ac8a);
    cursor: pointer;
    font-size: 12px;
    font-weight: 850;
  }

  .adm-cash-products__list {
    display: grid;
    gap: 7px;
    padding-top: 9px;
  }

  .adm-cash-product {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: 12px;
    align-items: center;
    padding: 9px 11px;
    border-radius: 11px;
    background: rgba(255, 255, 255, .025);
    color: var(--text2, #b9ac8a);
    font-size: 11px;
  }

  .adm-cash-product strong {
    overflow: hidden;
    color: var(--text, #f7f1df);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .adm-cash-history__empty,
  .adm-cash-history__error {
    padding: 28px 18px;
    border: 1px dashed rgba(255, 255, 255, .10);
    border-radius: 18px;
    text-align: center;
  }

  .adm-cash-history__empty strong,
  .adm-cash-history__error strong {
    display: block;
    color: var(--text, #f7f1df);
  }

  .adm-cash-history__empty p,
  .adm-cash-history__error p {
    margin: 7px 0 0;
    color: var(--text2, #b9ac8a);
    font-size: 12px;
  }

  @media (max-width: 900px) {
    .adm-cash-history__summary,
    .adm-cash-card__payments {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 620px) {
    .adm-cash-history__header,
    .adm-cash-card__main {
      grid-template-columns: 1fr;
    }

    .adm-cash-history__actions {
      width: 100%;
      justify-content: space-between;
    }

    .adm-cash-history__summary {
      grid-template-columns: 1fr 1fr;
      padding-inline: 14px;
    }

    .adm-cash-history__list {
      padding-inline: 14px;
    }

    .adm-cash-card__amount {
      justify-items: stretch;
    }

    .adm-cash-card__amount strong {
      font-size: 20px;
    }

    .adm-cash-delete {
      width: 100%;
    }
  }

  @media (max-width: 430px) {
    .adm-cash-history__summary,
    .adm-cash-card__payments {
      grid-template-columns: 1fr;
    }

    .adm-cash-product {
      grid-template-columns: minmax(0, 1fr) auto;
    }

    .adm-cash-product__subtotal {
      grid-column: 1 / -1;
    }
  }
</style>

<script>
(() => {
  'use strict';

  const endpoint = 'api.php';
  const listElement = document.getElementById('admin-closed-cashes');
  const refreshButton = document.getElementById('closed-cashes-refresh');
  const countElement = document.getElementById('closed-cashes-count');

  if (!listElement) return;

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function money(value) {
    return '$' + Number(value || 0).toLocaleString('es-AR');
  }

  function dateTime(value) {
    if (!value) return 'Sin fecha';

    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return String(value);

    return parsed.toLocaleString('es-AR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  function setText(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = value;
  }

  async function request(action, payload = null) {
    const options = {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    };

    if (payload !== null) {
      options.method = 'POST';
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(payload);
    }

    const response = await fetch(`${endpoint}?action=${encodeURIComponent(action)}`, options);
    const text = await response.text();

    let data;
    try {
      data = JSON.parse(text);
    } catch {
      throw new Error(`Respuesta inválida del servidor. HTTP ${response.status}`);
    }

    if (!response.ok || !data.ok) {
      throw new Error(data.error || 'No se pudo procesar el historial de cajas.');
    }

    return data;
  }

  function renderSummary(summary, closings) {
    setText('closed-cashes-total-count', Number(summary?.closings || 0).toLocaleString('es-AR'));
    setText('closed-cashes-total-amount', money(summary?.total || 0));
    setText('closed-cashes-sales-count', Number(summary?.sales || 0).toLocaleString('es-AR'));
    setText('closed-cashes-last-date', closings.length ? dateTime(closings[0].closed_at || closings[0].created_at) : 'Sin cierres');

    if (countElement) {
      const count = Number(summary?.closings || 0);
      countElement.textContent = `${count} ${count === 1 ? 'caja' : 'cajas'}`;
    }
  }

  function renderProducts(products) {
    const safeProducts = Array.isArray(products) ? products : [];

    if (!safeProducts.length) {
      return '<p style="color:var(--text2,#b9ac8a);font-size:12px;">Este cierre no tiene detalle de productos guardado.</p>';
    }

    return `
      <div class="adm-cash-products__list">
        ${safeProducts.map(product => `
          <div class="adm-cash-product">
            <strong title="${escapeHtml(product.name || 'Producto')}">${escapeHtml(product.name || 'Producto')}</strong>
            <span>${Number(product.qty || 0)} u.</span>
            <span class="adm-cash-product__subtotal">${money(product.subtotal || 0)}</span>
          </div>
        `).join('')}
      </div>
    `;
  }

  function renderClosings(closings) {
    if (!closings.length) {
      listElement.innerHTML = `
        <div class="adm-cash-history__empty">
          <strong>No hay cajas cerradas para mostrar</strong>
          <p>Cuando cierres una caja desde Kioskito, aparecerá en este listado.</p>
        </div>
      `;
      return;
    }

    listElement.innerHTML = closings.map(closing => {
      const fromSale = Number(closing.from_sale_id || 0);
      const toSale = Number(closing.to_sale_id || 0);
      const range = fromSale > 0 && toSale > 0
        ? `Ventas #${fromSale} a #${toSale}`
        : 'Rango de ventas no disponible';

      return `
        <article class="adm-cash-card" data-closing-id="${Number(closing.id || 0)}">
          <div class="adm-cash-card__main">
            <div class="adm-cash-card__identity">
              <span class="adm-cash-card__number">#${Number(closing.id || 0)}</span>
              <div class="adm-cash-card__copy">
                <strong>Caja cerrada por ${escapeHtml(closing.closed_by || 'Administrador')}</strong>
                <span>${dateTime(closing.closed_at || closing.created_at)}</span>
                <span>${Number(closing.sales_count || 0)} ventas · ${escapeHtml(range)}</span>
              </div>
            </div>

            <div class="adm-cash-card__amount">
              <strong>${money(closing.total || 0)}</strong>
              <button
                class="adm-cash-delete"
                type="button"
                data-delete-closing="${Number(closing.id || 0)}"
              >Eliminar del historial</button>
            </div>
          </div>

          <div class="adm-cash-card__payments">
            <div class="adm-cash-payment"><span>Efectivo</span><strong>${money(closing.efectivo_total || 0)}</strong></div>
            <div class="adm-cash-payment"><span>Transferencia</span><strong>${money(closing.transferencia_total || 0)}</strong></div>
            <div class="adm-cash-payment"><span>Tarjeta</span><strong>${money(closing.tarjeta_total || 0)}</strong></div>
            <div class="adm-cash-payment"><span>Regalo</span><strong>${money(closing.regalo_total || 0)}</strong></div>
          </div>

          <details class="adm-cash-products">
            <summary>Ver productos incluidos</summary>
            ${renderProducts(closing.products)}
          </details>
        </article>
      `;
    }).join('');
  }

  async function loadClosings() {
    if (refreshButton) refreshButton.disabled = true;
    if (countElement) countElement.textContent = 'Actualizando…';

    try {
      const data = await request('kiosko_closings_list');
      const closings = Array.isArray(data.closings) ? data.closings : [];

      renderSummary(data.summary || {}, closings);
      renderClosings(closings);
    } catch (error) {
      if (countElement) countElement.textContent = 'Error';
      listElement.innerHTML = `
        <div class="adm-cash-history__error">
          <strong>No se pudo cargar el historial</strong>
          <p>${escapeHtml(error?.message || 'Error desconocido')}</p>
        </div>
      `;
    } finally {
      if (refreshButton) refreshButton.disabled = false;
    }
  }

  async function deleteClosing(id, button) {
    const accepted = window.confirm(
      '¿Eliminar esta caja del historial?\n\nLas ventas seguirán cerradas y no volverán a aparecer en la caja actual.'
    );

    if (!accepted) return;

    button.disabled = true;
    const previousText = button.textContent;
    button.textContent = 'Eliminando…';

    try {
      await request('kiosko_closing_delete', { id });
      await loadClosings();
    } catch (error) {
      window.alert(error?.message || 'No se pudo eliminar el cierre.');
      button.disabled = false;
      button.textContent = previousText;
    }
  }

  listElement.addEventListener('click', event => {
    const button = event.target.closest('[data-delete-closing]');
    if (!button) return;

    const id = Number(button.dataset.deleteClosing || 0);
    if (id <= 0) return;

    deleteClosing(id, button);
  });

  refreshButton?.addEventListener('click', loadClosings);
  window.addEventListener('load', loadClosings, { once: true });
})();
</script>
