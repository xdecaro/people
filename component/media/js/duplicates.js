(() => {
  'use strict';

  const mobileQuery = window.matchMedia('(max-width: 767.98px)');

  const activate = (group, index, focusTab = false) => {
    const tabs = Array.from(group.querySelectorAll('[data-duplicate-tab]'));
    const panelHost = group.closest('.card-body')?.querySelector('[data-duplicate-panels]');
    const panels = panelHost ? Array.from(panelHost.querySelectorAll('[data-duplicate-panel]')) : [];

    tabs.forEach((tab) => {
      const active = Number(tab.dataset.duplicateTab) === index;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;

      if (active && focusTab) {
        tab.focus();
      }
    });

    panels.forEach((panel) => {
      const active = Number(panel.dataset.duplicatePanel) === index;
      panel.classList.toggle('is-active', active);
      panel.hidden = mobileQuery.matches ? !active : false;
    });
  };

  const sync = (group) => {
    const activeTab = group.querySelector('[data-duplicate-tab].is-active');
    const index = activeTab ? Number(activeTab.dataset.duplicateTab) : 0;
    activate(group, Number.isFinite(index) ? index : 0);
  };

  const groups = Array.from(document.querySelectorAll('[data-duplicate-group]'));

  groups.forEach((group) => {
    group.addEventListener('click', (event) => {
      const tab = event.target.closest('[data-duplicate-tab]');
      if (!tab || !group.contains(tab)) return;

      activate(group, Number(tab.dataset.duplicateTab) || 0);
    });

    group.addEventListener('keydown', (event) => {
      const tab = event.target.closest('[data-duplicate-tab]');
      if (!tab) return;

      const tabs = Array.from(group.querySelectorAll('[data-duplicate-tab]'));
      const current = tabs.indexOf(tab);
      if (current < 0) return;

      let next = current;
      if (event.key === 'ArrowRight') next = (current + 1) % tabs.length;
      else if (event.key === 'ArrowLeft') next = (current - 1 + tabs.length) % tabs.length;
      else if (event.key === 'Home') next = 0;
      else if (event.key === 'End') next = tabs.length - 1;
      else return;

      event.preventDefault();
      activate(group, Number(tabs[next].dataset.duplicateTab) || 0, true);
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
