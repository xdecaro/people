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

    if (!control || !summary) return;

    control.classList.add('xdecaro-duplicate-row-control');
    summary.prepend(control);
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
