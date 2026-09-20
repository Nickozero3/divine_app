document.addEventListener('DOMContentLoaded', () => {
  /* =====================================
     PESTAÑAS DE CATEGORÍAS
  ===================================== */

  const tabs = Array.from(document.querySelectorAll('.tab-button'));
  const panels = Array.from(document.querySelectorAll('.tab-panel'));

  function activateTab(tab, focus = false) {
    if (!tab) return;

    const target = tab.dataset.target;

    tabs.forEach(item => {
      const active = item === tab;

      item.classList.toggle('active', active);
      item.setAttribute('aria-selected', active ? 'true' : 'false');
      item.tabIndex = active ? 0 : -1;
    });

    panels.forEach(panel => {
      const active = panel.dataset.panel === target;

      panel.classList.toggle('active', active);
      panel.hidden = !active;
    });

    if (focus) {
      tab.focus({ preventScroll: true });
    }
  }

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => {
      activateTab(tab);
    });

    tab.addEventListener('keydown', event => {
      let nextIndex = null;

      if (event.key === 'ArrowRight') {
        nextIndex = (index + 1) % tabs.length;
      }

      if (event.key === 'ArrowLeft') {
        nextIndex = (index - 1 + tabs.length) % tabs.length;
      }

      if (event.key === 'Home') {
        nextIndex = 0;
      }

      if (event.key === 'End') {
        nextIndex = tabs.length - 1;
      }

      if (nextIndex !== null) {
        event.preventDefault();
        activateTab(tabs[nextIndex], true);
      }
    });
  });
});


  /* =====================================
     EDICIÓN DE PRECIOS PARA ADMIN
  ===================================== */
  const priceInputs = Array.from(document.querySelectorAll('.menu-price-input'));
  const saveButtons = Array.from(document.querySelectorAll('.menu-price-save'));

  async function saveMenuPrice(button) {
    const id = Number(button.dataset.productId || 0);
    const input = document.querySelector(`.menu-price-input[data-product-id="${id}"]`);

    if (!id || !input) return;

    const price = Number(input.value);
    if (!Number.isInteger(price) || price < 0) {
      alert('Ingresá un precio válido (entero mayor o igual a 0).');
      input.focus();
      return;
    }

    const original = Number(input.dataset.originalPrice || 0);
    if (price === original) {
      return;
    }

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Guardando...';

    try {
      const response = await fetch(`api.php?action=product_edit`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          id,
          name: input.closest('.product-card')?.querySelector('.product-name')?.textContent.trim() || '',
          price,
          cat: input.closest('.tab-panel')?.querySelector('h2')?.textContent.trim() || 'Otros'
        })
      });

      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.error || 'No se pudo actualizar el precio.');
      }

      input.dataset.originalPrice = String(price);
      button.textContent = '✓ Guardado';
      button.classList.add('saved');

      setTimeout(() => {
        button.textContent = originalText;
        button.classList.remove('saved');
      }, 1600);
    } catch (error) {
      alert(error.message || 'No se pudo actualizar el precio.');
      input.value = original;
      button.textContent = originalText;
    } finally {
      button.disabled = false;
    }
  }

  saveButtons.forEach(button => {
    button.addEventListener('click', () => saveMenuPrice(button));
  });

  priceInputs.forEach(input => {
    input.addEventListener('keydown', event => {
      if (event.key === 'Enter') {
        event.preventDefault();
        const button = document.querySelector(`.menu-price-save[data-product-id="${input.dataset.productId}"]`);
        if (button) saveMenuPrice(button);
      }
    });
  });
