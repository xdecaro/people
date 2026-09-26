(() => {
  'use strict';

  const form = document.querySelector('[data-duplicate-bulk-form]');
  if (!form) return;

  const selectAll = form.querySelector('[data-duplicate-select-all]');
  const countLabel = form.querySelector('[data-duplicate-selected-count]');
  const hidden = form.querySelector('[data-duplicate-selected-signatures]');
  const submit = form.querySelector('[data-duplicate-bulk-dismiss]');
  const checkboxes = Array.from(document.querySelectorAll('[data-duplicate-select]'));

  document.querySelectorAll('.xdecaro-duplicate-select-row').forEach((row) => {
    const control = row.querySelector('.xdecaro-duplicate-select-box');
    const summary = row.querySelector('.xdecaro-duplicate-accordion-summary');
    const summaryMain = summary?.querySelector('.xdecaro-duplicate-summary-main');
    const counts = summary?.querySelector('.xdecaro-duplicate-summary-counts');

    if (!control || !summary || !summaryMain || !counts) return;

    control.classList.add('xdecaro-duplicate-row-control');
    summary.prepend(control);

    const identity = summaryMain.firstElementChild;
    const matchReason = summaryMain.querySelector('.text-body-secondary');
    const differences = summaryMain.querySelector('.xdecaro-duplicate-difference-summary');

    if (!identity) return;

    const line1 = document.createElement('div');
    line1.className = 'xdecaro-duplicate-summary-line1';

    const line2 = document.createElement('div');
    line2.className = 'xdecaro-duplicate-summary-line2';

    const line3 = document.createElement('div');
    line3.className = 'xdecaro-duplicate-summary-line3';

    counts.classList.add('xdecaro-duplicate-summary-right');
    line1.append(identity, counts);

    const names = Array.from(row.querySelectorAll('.xdecaro-duplicate-person-heading h3'))
      .map((node) => node.textContent.trim())
      .filter(Boolean);

    if (names.length === 0) {
      row.querySelectorAll('.xdecaro-duplicate-mobile-person-head strong').forEach((node) => {
        const name = node.textContent.trim();
        if (name) names.push(name);
      });
    }

    const nameComparison = document.createElement('strong');
    nameComparison.className = 'xdecaro-duplicate-name-comparison';
    nameComparison.textContent = names.join(' ↔ ');

    const matchSummary = document.createElement('span');
    matchSummary.className = 'xdecaro-duplicate-match-summary small text-body-secondary';

    const summaryParts = [];
    if (matchReason?.textContent.trim()) {
      summaryParts.push(matchReason.textContent.replace(/\s+/g, ' ').trim());
    }
    if (differences?.textContent.trim()) {
      summaryParts.push(differences.textContent.replace(/\s+/g, ' ').trim());
    }
    matchSummary.textContent = summaryParts.join(' · ');

    line2.append(nameComparison);
    if (summaryParts.length > 0) line3.append(matchSummary);

    summaryMain.replaceChildren(line1, line2, line3);
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-duplicate-select]')) return;
    event.stopPropagation();
  }, true);

  const update = () => {
    const selected = checkboxes.filter((box) => box.checked);
    const signatures = selected.map((box) => box.value).filter(Boolean);

    if (hidden) hidden.value = signatures.join(',');
    if (countLabel) {
      const template = countLabel.dataset.countTemplate || '{count} selezionati';
      countLabel.textContent = template.replace('{count}', String(signatures.length));
    }
    if (submit) submit.disabled = signatures.length === 0;

    if (selectAll) {
      selectAll.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
      selectAll.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
    }

    selected.forEach((box) => box.closest('.xdecaro-duplicate-select-row')?.classList.add('is-selected'));
    checkboxes.filter((box) => !box.checked).forEach((box) => box.closest('.xdecaro-duplicate-select-row')?.classList.remove('is-selected'));
  };

  if (countLabel) {
    countLabel.dataset.countTemplate = countLabel.textContent.replace(/0/, '{count}');
  }

  selectAll?.addEventListener('change', () => {
    checkboxes.forEach((box) => {
      box.checked = selectAll.checked;
    });
    update();
  });

  checkboxes.forEach((box) => box.addEventListener('change', update));

  form.addEventListener('submit', (event) => {
    update();

    if (!hidden?.value) {
      event.preventDefault();
      return;
    }

    const message = form.dataset.confirmMessage || '';
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  update();
})();
