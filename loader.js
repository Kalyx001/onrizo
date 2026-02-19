(function () {
  const loader = document.getElementById('pageLoader');
  if (!loader) return;

  const show = () => loader.classList.add('active');
  const hide = () => loader.classList.remove('active');

  let pendingFetches = 0;

  window.showPageLoader = show;
  window.hidePageLoader = hide;

  window.addEventListener('load', () => hide());
  document.addEventListener('submit', () => show());

  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!target) return;
    if (target.closest('[data-show-loader]')) {
      show();
    }
  });

  if (window.fetch) {
    const originalFetch = window.fetch.bind(window);
    window.fetch = (...args) => {
      pendingFetches += 1;
      show();
      return originalFetch(...args)
        .catch((err) => {
          throw err;
        })
        .finally(() => {
          pendingFetches = Math.max(0, pendingFetches - 1);
          if (pendingFetches === 0) hide();
        });
    };
  }
})();
