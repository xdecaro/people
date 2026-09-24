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
  const clearSelectionButton = document.getElementById('xdecaro-people-export-clear-selection');
  const columnCount = document.getElementById('xdecaro-people-export-column-count');
  const columnsVisibleButton = document.getElementById('xdecaro-people-export-columns-visible');
  const columnsAllButton = document.getElementById('xdecaro-people-export-columns-all');
  const columnsNoneButton = document.getElementById('xdecaro-people-export-columns-none');
  const columnCheckboxes = Array.from(document.querySelectorAll('[data-xdecaro-export-column]'));
  const noSelectionMessage = dialog?.dataset.noSelection || 'Select at least one person.';
  const noColumnsMessage = dialog?.dataset.noColumns || 'Select at least one column.';

  if (!dialog || !exportForm || !adminForm || !downloadButton || !formatSelect) return;

  const SELECTION_KEY = 'com_xdecaropeople.export.selected_ids.v1';
  const COLUMNS_KEY = 'com_xdecaropeople.export.columns.v1';

  const readStoredArray = (key) => {
    try {
      const raw = window.sessionStorage.getItem(key);
      if (raw === null) return null;
      const value = JSON.parse(raw);
      return Array.isArray(value) ? value : null;
    } catch (error) {
      return null;
    }
  };

  const writeStoredArray = (key, values) => {
    try {
      window.sessionStorage.setItem(key, JSON.stringify(values));
    } catch (error) {
      // Export still works for the current page when sessionStorage is unavailable.
    }
  };

  const normalizeIds = (values) => Array.from(new Set((values || [])
    .map((value) => String(value || '').trim())
    .filter((value) => /^\d+$/.test(value) && Number(value) > 0)));

  const storedIds = readStoredArray(SELECTION_KEY);
  const selectedIds = new Set(normalizeIds(storedIds || []));

  const rowCheckboxes = () => Array.from(adminForm.querySelectorAll('input[name="cid[]"]'));
  const checkAllToggle = () => adminForm.querySelector('input[name="checkall-toggle"]');

  const saveSelectedIds = () => {
    writeStoredArray(SELECTION_KEY, Array.from(selectedIds));
  };

  const prepareListNavigation = () => {
    rowCheckboxes().forEach((input) => {
      input.disabled = true;
    });

    const toggle = checkAllToggle();
    if (toggle) toggle.disabled = true;

    const boxchecked = adminForm.querySelector('input[name="boxchecked"]');
    if (boxchecked) boxchecked.value = '0';
  };

  const visibleSelectedCount = () => rowCheckboxes().filter((input) => input.checked).length;

  const updateCheckAllState = () => {
    const rows = rowCheckboxes();
    const toggle = checkAllToggle();
    const checked = rows.filter((input) => input.checked).length;

    if (toggle) {
      toggle.checked = rows.length > 0 && checked === rows.length;
      toggle.indeterminate = checked > 0 && checked < rows.length;
    }

    const boxchecked = adminForm.querySelector('input[name="boxchecked"]');
    if (boxchecked) boxchecked.value = String(checked);

    if (typeof JoomlaApi.isChecked === 'function') {
      JoomlaApi.isChecked(checked);
    }
  };

  const restorePageSelection = () => {
    rowCheckboxes().forEach((input) => {
      input.checked = selectedIds.has(String(input.value));
    });
    updateCheckAllState();
  };

  const refreshSelectionUi = () => {
    const count = selectedIds.size;

    if (selectedRadio) {
      selectedRadio.disabled = count === 0;
      if (count === 0 && selectedRadio.checked) {
        const filtered = document.getElementById('xdecaro-people-export-scope-filtered');
        if (filtered) filtered.checked = true;
      }
    }

    if (selectedCount) selectedCount.textContent = String(count);
    if (clearSelectionButton) clearSelectionButton.disabled = count === 0;
  };

  const syncRowToSelection = (input) => {
    const id = String(input.value || '').trim();
    if (!/^\d+$/.test(id) || Number(id) < 1) return;

    if (input.checked) selectedIds.add(id);
    else selectedIds.delete(id);
  };

  const syncCurrentPageToSelection = () => {
    rowCheckboxes().forEach(syncRowToSelection);
    saveSelectedIds();
    refreshSelectionUi();
    updateCheckAllState();
  };

  const selectedColumns = () => columnCheckboxes
    .filter((input) => input.checked)
    .map((input) => String(input.value || '').trim())
    .filter(Boolean);

  const saveColumns = () => {
    writeStoredArray(COLUMNS_KEY, selectedColumns());
  };

  const refreshColumnUi = () => {
    if (columnCount) columnCount.textContent = String(selectedColumns().length);
  };

  const setColumns = (mode) => {
    columnCheckboxes.forEach((input) => {
      if (mode === 'all') input.checked = true;
      else if (mode === 'none') input.checked = false;
      else input.checked = input.hasAttribute('data-visible-column');
    });

    saveColumns();
    refreshColumnUi();
  };

  const restoreColumns = () => {
    const stored = readStoredArray(COLUMNS_KEY);

    if (stored !== null) {
      const allowed = new Set(columnCheckboxes.map((input) => String(input.value)));
      const saved = new Set(stored.filter((value) => allowed.has(String(value))));
      columnCheckboxes.forEach((input) => {
        input.checked = saved.has(String(input.value));
      });
    }

    refreshColumnUi();
  };

  const appendHidden = (name, value, marker) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = String(value);
    input.dataset[marker] = '1';
    exportForm.appendChild(input);
  };

  const originalSubmitbutton = typeof JoomlaApi.submitbutton === 'function'
    ? JoomlaApi.submitbutton.bind(JoomlaApi)
    : null;

  const originalSubmitform = typeof JoomlaApi.submitform === 'function'
    ? JoomlaApi.submitform.bind(JoomlaApi)
    : null;

  JoomlaApi.submitform = (task, form, validate) => {
    const targetForm = form || adminForm;
    const normalizedTask = String(task || '').trim();

    if (targetForm === adminForm && normalizedTask === '') {
      prepareListNavigation();
    }

    if (originalSubmitform) return originalSubmitform(task, form, validate);
    if (targetForm) targetForm.submit();

    return true;
  };

  adminForm.addEventListener('submit', () => {
    const task = String(adminForm.querySelector('input[name="task"]')?.value || '').trim();

    if (task === '') {
      prepareListNavigation();
    }
  });

  JoomlaApi.submitbutton = (task) => {
    if (task === 'export.open') {
      restorePageSelection();
      refreshSelectionUi();
      restoreColumns();

      if (typeof dialog.showModal === 'function') dialog.showModal();
      else dialog.setAttribute('open', 'open');

      return false;
    }

    if (originalSubmitbutton) return originalSubmitbutton(task);
    if (typeof JoomlaApi.submitform === 'function') return JoomlaApi.submitform(task, adminForm);
    return true;
  };

  adminForm.addEventListener('change', (event) => {
    const target = event.target;

    if (target?.matches?.('input[name="cid[]"]')) {
      syncRowToSelection(target);
      saveSelectedIds();
      refreshSelectionUi();
      updateCheckAllState();
      return;
    }

    if (target?.matches?.('input[name="checkall-toggle"]')) {
      window.setTimeout(syncCurrentPageToSelection, 0);
    }
  });

  closeButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (typeof dialog.close === 'function') dialog.close();
      else dialog.removeAttribute('open');
    });
  });

  clearSelectionButton?.addEventListener('click', () => {
    selectedIds.clear();
    saveSelectedIds();
    restorePageSelection();
    refreshSelectionUi();
  });

  columnCheckboxes.forEach((input) => {
    input.addEventListener('change', () => {
      saveColumns();
      refreshColumnUi();
    });
  });

  columnsVisibleButton?.addEventListener('click', () => setColumns('visible'));
  columnsAllButton?.addEventListener('click', () => setColumns('all'));
  columnsNoneButton?.addEventListener('click', () => setColumns('none'));

  downloadButton.addEventListener('click', () => {
    const scope = dialog.querySelector('input[name="xdecaro_export_scope_ui"]:checked')?.value || 'filtered';
    const columns = selectedColumns();

    if (scope === 'selected' && selectedIds.size === 0) {
      window.alert(noSelectionMessage);
      refreshSelectionUi();
      return;
    }

    if (columns.length === 0) {
      window.alert(noColumnsMessage);
      return;
    }

    exportForm.querySelector('input[name="export_format"]').value = formatSelect.value || 'xlsx';
    exportForm.querySelector('input[name="export_scope"]').value = scope;
    exportForm.querySelector('input[name="export_selected_ids"]').value = scope === 'selected'
      ? Array.from(selectedIds).join(',')
      : '';
    exportForm.querySelector('input[name="filter_search"]').value = adminForm.querySelector('[name="filter_search"]')?.value || '';
    exportForm.querySelector('input[name="filter_state"]').value = adminForm.querySelector('[name="filter_state"]')?.value || '';

    exportForm.querySelectorAll('input[data-xdecaro-export-column-input]')
      .forEach((input) => input.remove());

    columns.forEach((column) => appendHidden('export_columns[]', column, 'xdecaroExportColumnInput'));

    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');

    exportForm.submit();
  });

  restorePageSelection();
  refreshSelectionUi();
  restoreColumns();
})();
