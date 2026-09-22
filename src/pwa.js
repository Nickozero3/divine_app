(() => {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('sw.js').catch(error => {
        console.warn('[DIVINE PWA] No se pudo registrar:', error);
      });
    });
  }
})();
