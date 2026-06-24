(() => {
  'use strict';

  const STORAGE_KEY = 'menuTheme';
  const RED_THEME = 'red';
  const PURPLE_THEME = 'original';

  function normalizeTheme(value) {
    return value === PURPLE_THEME || value === 'purple' || value === 'morado'
      ? PURPLE_THEME
      : RED_THEME;
  }

  function getStoredTheme() {
    try {
      return normalizeTheme(localStorage.getItem(STORAGE_KEY) || RED_THEME);
    } catch {
      return RED_THEME;
    }
  }

  function saveTheme(theme) {
    try {
      localStorage.setItem(STORAGE_KEY, theme);
    } catch {
      // La app sigue funcionando aunque el navegador bloquee localStorage.
    }
  }

  function ensureThemeColorMeta() {
    let meta = document.querySelector('meta[name="theme-color"]');

    if (!meta) {
      meta = document.createElement('meta');
      meta.name = 'theme-color';
      document.head.appendChild(meta);
    }

    return meta;
  }

  function buildButtonContent(button) {
    if (button.querySelector('[data-theme-label]')) {
      return;
    }

    button.innerHTML = `
      <span class="theme-toggle__icon" aria-hidden="true">◐</span>
      <span class="theme-toggle__copy">
        <span class="theme-toggle__eyebrow">Tema visual</span>
        <span class="theme-toggle__label" data-theme-label>Cambiar tema</span>
      </span>
      <span class="theme-toggle__track" aria-hidden="true">
        <span class="theme-toggle__thumb"></span>
      </span>
    `;
  }

  function updateButtons(theme) {
    const isRed = theme === RED_THEME;
    const nextThemeName = isRed ? 'morado' : 'rojo';
    const text = `Cambiar tema a ${nextThemeName}`;

    document
      .querySelectorAll('[data-theme-toggle], #themeToggle')
      .forEach(button => {
        buildButtonContent(button);

        const label = button.querySelector('[data-theme-label]');
        if (label) {
          label.textContent = text;
        }

        button.dataset.currentTheme = theme;
        button.setAttribute('aria-label', text);
        button.setAttribute('title', text);
        button.setAttribute('aria-pressed', isRed ? 'true' : 'false');

        if (button.dataset.themeBound !== 'true') {
          button.dataset.themeBound = 'true';
          button.addEventListener('click', () => {
            const currentTheme = normalizeTheme(
              document.documentElement.dataset.theme || getStoredTheme()
            );

            const nextTheme = currentTheme === RED_THEME
              ? PURPLE_THEME
              : RED_THEME;

            applyTheme(nextTheme, true);
          });
        }
      });
  }

  function applyTheme(theme, persist = false) {
    const normalizedTheme = normalizeTheme(theme);
    const isRed = normalizedTheme === RED_THEME;

    document.documentElement.dataset.theme = normalizedTheme;
    document.documentElement.style.colorScheme = 'dark';

    if (document.body) {
      document.body.classList.toggle('theme-red', isRed);
      document.body.classList.toggle('theme-purple', !isRed);
    }

    const meta = ensureThemeColorMeta();
    meta.content = isRed ? '#090002' : '#0c0a12';

    updateButtons(normalizedTheme);

    if (persist) {
      saveTheme(normalizedTheme);
    }

    window.dispatchEvent(new CustomEvent('divine:themechange', {
      detail: { theme: normalizedTheme }
    }));
  }

  const initialTheme = getStoredTheme();
  document.documentElement.dataset.theme = initialTheme;

  function initTheme() {
    applyTheme(getStoredTheme());
  }

  if (document.body) {
    initTheme();
  } else {
    document.addEventListener('DOMContentLoaded', initTheme, { once: true });
  }

  window.addEventListener('storage', event => {
    if (event.key === STORAGE_KEY) {
      applyTheme(normalizeTheme(event.newValue || RED_THEME));
    }
  });

  window.DivineTheme = {
    apply: theme => applyTheme(theme, true),
    current: () => normalizeTheme(
      document.documentElement.dataset.theme || getStoredTheme()
    )
  };
})();
