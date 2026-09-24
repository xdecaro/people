(() => {
  'use strict';

  const mobileQuery = window.matchMedia('(max-width: 767.98px)');

  const groups = Array.from(document.querySelectorAll('[data-duplicate-group]'));

  const setActiveRecord = (group, index) => {
    const tabs = Array.from(group.querySelectorAll('[data-duplicate-tab]'));
    const panels = Array.from(group.querySelectorAll('[data-duplicate-panel]'));

    tabs.forEach((tab) => {
      const active = Number(tab.dataset.duplicateTab) === index;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
    });

    panels.forEach((panel) => {
      const active = Number(panel.dataset.duplicatePanel) === index;
      panel.classList.toggle('is-active', active);

      if (mobileQuery.matches) {
        panel.hidden = !active;
      } else {
        panel.hidden = false;
      }
    });
  };

  const syncGroup = (group) => {
    const current = group.querySelector('[data-duplicate-tab].is-active');
    const index = current ? Number(current.dataset.duplicateTab) : 0;
    setActiveRecord(group, Number.isFinite(index) ? index : 0);
  };

  groups.forEach((group) => {
    group.addEventListener('click', (event) => {
      const tab = event.target.closest('[data-duplicate-tab]');
      if (!tab || !group.contains(tab)) return;

      setActiveRecord(group, Number(tab.dataset.duplicateTab) || 0);

      const panel = group.querySelector('[data-duplicate-panel].is-active');
      panel?.scrollIntoView({block: 'nearest', behavior: 'smooth'});
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
      else return;

      event.preventDefault();
      const nextTab = tabs[next];
      setActiveRecord(group, Number(nextTab.dataset.duplicateTab) || 0);
      nextTab.focus();
    });

    syncGroup(group);
  });

  const onBreakpointChange = () => groups.forEach(syncGroup);

  if (typeof mobileQuery.addEventListener === 'function') {
    mobileQuery.addEventListener('change', onBreakpointChange);
  } else if (typeof mobileQuery.addListener === 'function') {
    mobileQuery.addListener(onBreakpointChange);
  }
})();
