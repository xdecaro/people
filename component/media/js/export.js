(() => {
  'use strict';

  const JoomlaApi = window.Joomla || {};
  const dialog = document.getElementById('xdecaro-people-export-dialog');
  const exportForm = document.getElementById('xdecaro-people-export-form');
  const adminForm = document.getElementById('adminForm');
  const downloadButton = document.getElementById('xdecaro-people-export-download');
  const closeButtons = document.querySelectorAll('[data-xdecaro-export-close]');
  const formatSelect = document.getElementById('xdecaro-people-export-format-ui');
  const selectedRadio = document.getElementById('xdecaro-people-export-scope-selected');
  const selectedCount = document.getElementById('xdecaro-people-export-selected-count');
  const noSelectionMessage = dialog?.dataset.noSelection || 'Select at least one person.';

  if (!dialog || !exportForm || !adminForm || !downloadButton || !formatSelect) return;

  const checkedIds = () => Array.from(adminForm.querySelectorAll('input[name="cid[]"]:checked'))
    .map((input) => String(input.value || '').trim())
    .filter((value) => /^\d+$/.test(value) && Number(value) > 0);

  const refreshSelection = () => {
    const count = checkedIds().length;
    if (selectedRadio) selectedRadio.disabled = count === 0;
    if (selectedCount) selectedCount.textContent = String(count);
  };

  const originalSubmitbutton = typeof JoomlaApi.submitbutton === 'function'
    ? JoomlaApi.submitbutton.bind(JoomlaApi)
    : null;

  JoomlaApi.submitbutton = (task) => {
    if (task === 'export.open') {
      refreshSelection();
      if (typeof dialog.showModal === 'function') dialog.showModal();
      else dialog.setAttribute('open', 'open');
      return false;
    }

    if (originalSubmitbutton) return originalSubmitbutton(task);
    if (typeof JoomlaApi.submitform === 'function') return JoomlaApi.submitform(task, adminForm);
    return true;
  };

  adminForm.addEventListener('change', (event) => {
    if (event.target?.matches?.('input[name="cid[]"], input[name="checkall-toggle"]')) {
      window.setTimeout(refreshSelection, 0);
    }
  });

  closeButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (typeof dialog.close === 'function') dialog.close();
      else dialog.removeAttribute('open');
    });
  });

  downloadButton.addEventListener('click', () => {
    const scope = dialog.querySelector('input[name="xdecaro_export_scope_ui"]:checked')?.value || 'filtered';
    const ids = checkedIds();

    if (scope === 'selected' && ids.length === 0) {
      window.alert(noSelectionMessage);
      refreshSelection();
      return;
    }

    exportForm.querySelector('input[name="export_format"]').value = formatSelect.value || 'xlsx';
    exportForm.querySelector('input[name="export_scope"]').value = scope;
    exportForm.querySelector('input[name="filter_search"]').value = adminForm.querySelector('[name="filter_search"]')?.value || '';
    exportForm.querySelector('input[name="filter_state"]').value = adminForm.querySelector('[name="filter_state"]')?.value || '';

    exportForm.querySelectorAll('input[data-xdecaro-export-cid]').forEach((input) => input.remove());
    if (scope === 'selected') {
      ids.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'cid[]';
        input.value = id;
        input.dataset.xdecaroExportCid = '1';
        exportForm.appendChild(input);
      });
    }

    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
    exportForm.submit();
  });

  refreshSelection();
})();
