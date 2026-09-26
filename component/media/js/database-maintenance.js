document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-database-confirm]').forEach((form) => {
    const input = form.querySelector('[name="database_confirmation"]');
    const button = form.querySelector('[type="submit"]');
    const expected = form.getAttribute('data-database-confirm') || '';

    if (!input || !button || expected === '') {
      return;
    }

    const sync = () => {
      button.disabled = input.value !== expected;
    };

    input.addEventListener('input', sync);
    sync();
  });

  const activityList = document.querySelector('.xdecaro-activity-list');
  if (activityList) {
    const items = Array.from(activityList.querySelectorAll('.xdecaro-activity-item'));
    const visibleItems = items.slice(0, 5);
    const extraItems = items.slice(5);

    if (extraItems.length > 0) {
      extraItems.forEach((item) => {
        item.hidden = true;
      });

      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'btn btn-sm btn-outline-secondary xdecaro-activity-toggle';
      toggle.textContent = `Mostra tutte (${items.length})`;
      toggle.setAttribute('aria-expanded', 'false');

      toggle.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        extraItems.forEach((item) => {
          item.hidden = expanded;
        });
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        toggle.textContent = expanded ? `Mostra tutte (${items.length})` : 'Mostra meno';
      });

      activityList.insertAdjacentElement('afterend', toggle);
    }

    visibleItems.forEach((item) => {
      item.hidden = false;
    });
  }

  const host = document.querySelector('.xdecaro-database-maintenance');
  if (!host || typeof Joomla === 'undefined' || typeof Joomla.getOptions !== 'function') {
    return;
  }

  const schema = Joomla.getOptions('com_xdecaropeople.schema-differences', {});
  const container = document.createElement('div');
  container.className = 'xdecaro-schema-differences';

  const title = document.createElement('h4');
  title.textContent = 'Differenze rilevate';
  container.appendChild(title);

  const insertBeforeActions = () => {
    const actions = host.querySelector('.xdecaro-maintenance-actions');
    if (actions) {
      host.insertBefore(container, actions);
    } else {
      host.appendChild(container);
    }
  };

  if (schema && schema.ok) {
    const clean = document.createElement('div');
    clean.className = 'alert alert-success mb-0';
    clean.textContent = 'Schema conforme: nessuna differenza rilevata.';
    container.appendChild(clean);
    insertBeforeActions();
    return;
  }

  const intro = document.createElement('p');
  intro.textContent = 'Queste sono le differenze tra il database reale e lo schema canonico People. Gli elementi non previsti vengono segnalati ma non eliminati automaticamente da Ripara database.';
  container.appendChild(intro);

  const groups = [
    ['missing_tables', 'Tabelle mancanti'],
    ['unexpected_tables', 'Tabelle non previste'],
    ['missing_columns', 'Colonne mancanti'],
    ['incompatible_columns', 'Colonne incompatibili'],
    ['unknown_columns', 'Colonne non previste'],
    ['missing_indexes', 'Indici mancanti'],
    ['incompatible_indexes', 'Indici incompatibili'],
    ['unknown_indexes', 'Indici non previsti'],
    ['engine_differences', 'Engine differente'],
    ['collation_differences', 'Collation differente'],
  ];

  const flatten = (value) => {
    if (Array.isArray(value)) {
      return value.map((item) => String(item));
    }

    if (!value || typeof value !== 'object') {
      return value === undefined || value === null || value === '' ? [] : [String(value)];
    }

    return Object.entries(value).flatMap(([parent, details]) => {
      if (Array.isArray(details)) {
        return details.map((detail) => `${parent} → ${detail}`);
      }

      if (details && typeof details === 'object') {
        return Object.entries(details).map(([name, detail]) => `${parent} → ${name}: ${detail}`);
      }

      return [`${parent} → ${details}`];
    });
  };

  const grid = document.createElement('div');
  grid.className = 'xdecaro-schema-difference-grid';
  let rendered = 0;

  groups.forEach(([key, label]) => {
    const items = flatten(schema ? schema[key] : null);
    if (items.length === 0) {
      return;
    }

    rendered += 1;
    const group = document.createElement('section');
    group.className = 'xdecaro-schema-difference-group';

    const heading = document.createElement('h5');
    const headingText = document.createElement('span');
    headingText.textContent = label;
    const count = document.createElement('span');
    count.className = 'badge bg-secondary';
    count.textContent = String(items.length);
    heading.append(headingText, count);
    group.appendChild(heading);

    const list = document.createElement('ul');
    list.className = 'xdecaro-schema-difference-list';
    items.forEach((item) => {
      const row = document.createElement('li');
      const code = document.createElement('code');
      code.textContent = item;
      row.appendChild(code);
      list.appendChild(row);
    });

    group.appendChild(list);
    grid.appendChild(group);
  });

  if (rendered > 0) {
    container.appendChild(grid);
  } else {
    const fallback = document.createElement('div');
    fallback.className = 'alert alert-warning mb-0';
    fallback.textContent = 'Il database risulta da verificare, ma non sono disponibili dettagli classificati.';
    container.appendChild(fallback);
  }

  insertBeforeActions();
});
