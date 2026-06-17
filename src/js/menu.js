document.addEventListener('DOMContentLoaded', () => {
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
    tab.addEventListener('click', () => activateTab(tab));

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

  const themeToggle = document.getElementById('themeToggle');

  const savedTheme = localStorage.getItem('menuTheme') || 'red';

  if (savedTheme === 'red') {
    document.body.classList.add('theme-red');
  }

  themeToggle.textContent = document.body.classList.contains('theme-red')
    ? 'Modo original'
    : 'Modo rojo';

  themeToggle.addEventListener('click', () => {
    document.body.classList.toggle('theme-red');

    const isRed = document.body.classList.contains('theme-red');

    localStorage.setItem('menuTheme', isRed ? 'red' : 'original');

    themeToggle.textContent = isRed
      ? 'Modo original'
      : 'Modo rojo';
  });