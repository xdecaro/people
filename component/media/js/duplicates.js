(() => {
  'use strict';

  const mobileQuery = window.matchMedia('(max-width: 767.98px)');

  const groups = Array.from(document.querySelectorAll('[data-duplicate-group]'));

  const getPanels = (group) => {
    const panelHost = group.closest('.card-body')?.querySelector('[data-duplicate-panels]');
    return panelHost ? Array.from(panelHost.querySelectorAll('[data-duplicate-panel]')) : [];
  };

  const activate = (group, index) => {
    const panels = getPanels(group);
    if (panels.length === 0) return;

    const safeIndex = Math.max(0, Math.min(index, panels.length - 1));
    group.dataset.duplicateIndex = String(safeIndex);

    panels.forEach((panel) => {
      const active = Number(panel.dataset.duplicatePanel) === safeIndex;
      panel.classList.toggle('is-active', active);
      panel.hidden = mobileQuery.matches ? !active : false;
    });

    const position = group.querySelector('[data-duplicate-position]');
    if (position) position.textContent = `${safeIndex + 1} / ${panels.length}`;

    const prev = group.querySelector('[data-duplicate-prev]');
    const next = group.querySelector('[data-duplicate-next]');

    if (prev) prev.disabled = safeIndex === 0;
    if (next) next.disabled = safeIndex >= panels.length - 1;
  };

  const sync = (group) => {
    const index = Number(group.dataset.duplicateIndex || 0);
    activate(group, Number.isFinite(index) ? index : 0);
  };

  groups.forEach((group) => {
    group.addEventListener('click', (event) => {
      const prev = event.target.closest('[data-duplicate-prev]');
      const next = event.target.closest('[data-duplicate-next]');

      if (!prev && !next) return;

      const current = Number(group.dataset.duplicateIndex || 0);
      activate(group, current + (next ? 1 : -1));
    });

    sync(group);
  });

  const resync = () => groups.forEach(sync);

  if (typeof mobileQuery.addEventListener === 'function') {
    mobileQuery.addEventListener('change', resync);
  } else if (typeof mobileQuery.addListener === 'function') {
    mobileQuery.addListener(resync);
  }
})();
