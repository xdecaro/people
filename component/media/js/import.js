(() => {
  'use strict';

  const JoomlaApi = window.Joomla || {};
  const options = JoomlaApi.getOptions?.('com_xdecaropeople.import') || {};
  const strings = options.strings || {};
  const targets = Array.isArray(options.targets) ? options.targets : [];
  const batchSize = Math.max(1, Math.min(100, Number(options.batchSize || 100)));

  const fileInput = document.getElementById('xdecaro-people-import-file');
  const fileInfo = document.getElementById('xdecaro-people-import-file-info');
  const mappingCard = document.getElementById('xdecaro-people-import-mapping-card');
  const mappingBody = document.getElementById('xdecaro-people-import-mapping');
  const analyzeButton = document.getElementById('xdecaro-people-import-analyze');
  const summaryCard = document.getElementById('xdecaro-people-import-summary-card');
  const summary = document.getElementById('xdecaro-people-import-summary');
  const startButton = document.getElementById('xdecaro-people-import-start');
  const progressCard = document.getElementById('xdecaro-people-import-progress-card');
  const progressBar = document.getElementById('xdecaro-people-import-progress');
  const progressText = document.getElementById('xdecaro-people-import-progress-text');
  const reportCard = document.getElementById('xdecaro-people-import-report-card');
  const reportBody = document.getElementById('xdecaro-people-import-report');
  const downloadReportButton = document.getElementById('xdecaro-people-import-download-report');

  if (!fileInput || !mappingBody || !analyzeButton || !startButton) return;

  const state = {
    headers: [],
    rows: [],
    mapping: {},
    profile: 'generic',
    encoding: '',
    delimiter: ';',
    prepared: [],
    invalid: [],
    existing: [],
    candidates: [],
    report: [],
    fileDuplicateCount: 0,
    busy: false,
  };

  const normalizeHeader = (value) => String(value || '')
    .replace(/\u00a0/g, ' ')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
    .replace(/[_\-]+/g, ' ')
    .replace(/\s+/g, ' ');

  const clean = (value) => String(value ?? '').trim();

  const aliases = {
    first_name: ['nome', 'first name', 'firstname'],
    last_name: ['cognome', 'last name', 'lastname'],
    tax_identifier: ['codice fiscale', 'codicefiscale', 'tax identifier', 'tin'],
    birth_date: ['data di nascita', 'birth date', 'birthdate'],
    sex: ['sesso', 'sex'],
    birth_place: ['comune di nascita', 'luogo di nascita', 'birth place'],
    birth_region: ['provincia di nascita', 'birth region', 'birth province'],
    address_line: ['indirizzo di residenza', 'indirizzo', 'address'],
    address_number: ['numero civico', 'civico', 'street number', 'address number'],
    postal_code: ['cap', 'postal code', 'postcode', 'zip'],
    city: ['comune di residenza', 'localita', 'citta', 'city'],
    region: ['provincia di residenza', 'provincia', 'region', 'province'],
    country_code: ['country code', 'country_code', 'iso2'],
    phone: ['cellulare', 'telefono', 'phone', 'mobile'],
    email: ['e mail', 'email'],
  };

  const countNonEmpty = (index) => state.rows.reduce((count, row) => count + (clean(row[index]) !== '' ? 1 : 0), 0);

  const indexesForAliases = (values) => {
    const normalizedAliases = values.map(normalizeHeader);
    return state.headers
      .map((header, index) => ({ index, normalized: normalizeHeader(header.name), nonEmpty: header.nonEmpty }))
      .filter((item) => normalizedAliases.includes(item.normalized))
      .sort((a, b) => b.nonEmpty - a.nonEmpty || a.index - b.index);
  };

  const bestIndex = (targetKey) => {
    const matches = indexesForAliases(aliases[targetKey] || []);
    return matches.length ? matches[0].index : null;
  };

  const detectProfile = () => {
    const names = new Set(state.headers.map((header) => normalizeHeader(header.name)));
    const ensMarkers = ['categoria socio', 'codice socio', 'codice fiscale', 'data delibera prima iscrizione socio'];
    return ensMarkers.every((marker) => names.has(normalizeHeader(marker))) ? 'ens_soci_2026' : 'generic';
  };

  const parseCsv = (text, delimiter) => {
    const output = [];
    let row = [];
    let field = '';
    let quoted = false;

    for (let index = 0; index < text.length; index++) {
      const char = text[index];

      if (quoted) {
        if (char === '"') {
          if (text[index + 1] === '"') {
            field += '"';
            index++;
          } else {
            quoted = false;
          }
        } else {
          field += char;
        }
        continue;
      }

      if (char === '"') {
        quoted = true;
      } else if (char === delimiter) {
        row.push(field);
        field = '';
      } else if (char === '\n') {
        row.push(field.replace(/\r$/, ''));
        output.push(row);
        row = [];
        field = '';
      } else {
        field += char;
      }
    }

    if (field !== '' || row.length) {
      row.push(field.replace(/\r$/, ''));
      output.push(row);
    }

    return output;
  };

  const detectDelimiter = (text) => {
    const candidates = [';', ',', '\t'];
    let best = ';';
    let bestCount = 0;

    candidates.forEach((candidate) => {
      const parsed = parseCsv(text.slice(0, 12000), candidate);
      const count = parsed[0]?.length || 0;
      if (count > bestCount) {
        best = candidate;
        bestCount = count;
      }
    });

    return best;
  };

  const decodeFile = async (file) => {
    const bytes = await file.arrayBuffer();

    try {
      return {
        text: new TextDecoder('utf-8', { fatal: true }).decode(bytes).replace(/^\uFEFF/, ''),
        encoding: 'utf-8',
      };
    } catch (error) {
      return {
        text: new TextDecoder('windows-1252').decode(bytes).replace(/^\uFEFF/, ''),
        encoding: 'windows-1252',
      };
    }
  };

  const renderMapping = () => {
    mappingBody.replaceChildren();

    targets.forEach((target) => {
      const row = document.createElement('tr');
      const labelCell = document.createElement('td');
      const selectCell = document.createElement('td');
      const label = document.createElement('label');
      const select = document.createElement('select');

      select.className = 'form-select form-select-sm';
      select.id = `xdecaro-map-${target.key}`;
      label.htmlFor = select.id;
      label.textContent = `${target.label}${target.required ? ' *' : ''}`;

      const emptyOption = document.createElement('option');
      emptyOption.value = '';
      emptyOption.textContent = strings.notMapped || '—';
      select.appendChild(emptyOption);

      state.headers.forEach((header, index) => {
        const option = document.createElement('option');
        option.value = String(index);
        option.textContent = header.label;
        select.appendChild(option);
      });

      const autoIndex = bestIndex(target.key);
      if (autoIndex !== null) {
        select.value = String(autoIndex);
      }

      select.addEventListener('change', () => {
        state.mapping[target.key] = select.value === '' ? null : Number(select.value);
      });

      state.mapping[target.key] = select.value === '' ? null : Number(select.value);

      labelCell.appendChild(label);
      selectCell.appendChild(select);
      row.append(labelCell, selectCell);
      mappingBody.appendChild(row);
    });
  };

  const valueFromMapping = (row, key) => {
    const index = state.mapping[key];
    return Number.isInteger(index) ? clean(row[index]) : '';
  };

  const firstHeaderIndex = (name) => {
    const normalized = normalizeHeader(name);
    const matches = state.headers
      .map((header, index) => ({ index, normalized: normalizeHeader(header.name), nonEmpty: header.nonEmpty }))
      .filter((item) => item.normalized === normalized)
      .sort((a, b) => b.nonEmpty - a.nonEmpty || a.index - b.index);
    return matches.length ? matches[0].index : null;
  };

  const normalizeSourceRow = (sourceRow, sourceIndex) => {
    const mapped = { _row: sourceIndex + 2 };

    targets.forEach((target) => {
      mapped[target.key] = valueFromMapping(sourceRow, target.key);
    });

    if (state.profile === 'ens_soci_2026') {
      const streetTypeIndex = firstHeaderIndex('toponimo');
      const streetType = streetTypeIndex === null ? '' : clean(sourceRow[streetTypeIndex]);
      if (streetType && mapped.address_line && !mapped.address_line.toLowerCase().startsWith(streetType.toLowerCase() + ' ')) {
        mapped.address_line = `${streetType} ${mapped.address_line}`.trim();
      }

      if (!mapped.phone) {
        for (const fallbackName of ['cellulare', 'telefono']) {
          const index = firstHeaderIndex(fallbackName);
          if (index !== null && clean(sourceRow[index])) {
            mapped.phone = clean(sourceRow[index]);
            break;
          }
        }
      }
    }

    mapped.tax_identifier = clean(mapped.tax_identifier).toUpperCase().replace(/\s+/g, '');
    mapped.email = clean(mapped.email).toLowerCase();

    return mapped;
  };

  const validationErrors = (row) => {
    const errors = [];
    if (!clean(row.first_name)) errors.push('missing_first_name');
    if (!clean(row.last_name)) errors.push('missing_last_name');

    if (state.profile === 'ens_soci_2026') {
      if (!row.tax_identifier) {
        errors.push('missing_tax_identifier');
      } else if (!/^[A-Z0-9]{16}$/.test(row.tax_identifier)) {
        errors.push('invalid_tax_identifier');
      }
    }

    return errors;
  };

  const identityKey = (row) => {
    if (row.tax_identifier) return `tax:${row.tax_identifier}`;
    if (row.first_name && row.last_name && row.birth_date && row.birth_date !== '0000-00-00') {
      return `person:${clean(row.first_name).toUpperCase()}|${clean(row.last_name).toUpperCase()}|${clean(row.birth_date)}`;
    }
    return `row:${row._row}`;
  };

  const completenessScore = (row) => targets.reduce(
    (score, target) => score + (clean(row[target.key]) !== '' ? 1 : 0),
    0
  );

  const consolidateDuplicates = (rows) => {
    const groups = new Map();

    rows.forEach((row) => {
      const key = identityKey(row);
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(row);
    });

    let duplicates = 0;
    const consolidated = [];
    const conflicts = [];

    groups.forEach((group) => {
      if (group.length === 1) {
        consolidated.push(group[0]);
        return;
      }

      duplicates += group.length - 1;

      const conflictingFields = targets
        .map((target) => target.key)
        .filter((key) => {
          const values = new Set(
            group
              .map((row) => clean(row[key]).toLocaleUpperCase())
              .filter(Boolean)
          );
          return values.size > 1;
        });

      if (conflictingFields.length) {
        const rowNumbers = group.map((row) => row._row).sort((a, b) => a - b);
        conflicts.push({
          row: rowNumbers[0],
          status: 'invalid',
          message: 'duplicate_conflict',
          warnings: [],
          details: `${strings.conflictRows || 'Rows'}: ${rowNumbers.join(', ')} — ${strings.conflictFields || 'Fields'}: ${conflictingFields.join(', ')}`,
        });
        return;
      }

      const sorted = [...group].sort((a, b) => completenessScore(b) - completenessScore(a) || a._row - b._row);
      const winner = { ...sorted[0] };

      sorted.slice(1).forEach((candidate) => {
        targets.forEach((target) => {
          if (!clean(winner[target.key]) && clean(candidate[target.key])) {
            winner[target.key] = candidate[target.key];
          }
        });
      });

      consolidated.push(winner);
    });

    return {
      rows: consolidated.sort((a, b) => a._row - b._row),
      duplicates,
      conflicts,
    };
  };

  const postPayload = async (url, payload) => {
    const formData = new FormData();
    formData.append(options.token, '1');
    formData.append('payload', JSON.stringify(payload));

    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
      body: formData,
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const json = await response.json();
    if (json?.success === false) {
      throw new Error(json.message || strings.requestError || 'Request failed.');
    }

    return json?.data ?? json;
  };

  const renderSummary = (counts) => {
    const entries = [
      [strings.rows || 'Rows', counts.total],
      [strings.valid || 'Valid', counts.valid],
      [strings.duplicates || 'Duplicates', counts.duplicates],
      [strings.invalid || 'Invalid', counts.invalid],
      [strings.existing || 'Existing', counts.existing],
      [strings.newPeople || 'New', counts.newPeople],
    ];

    summary.replaceChildren();
    entries.forEach(([label, value]) => {
      const item = document.createElement('div');
      item.className = 'xdecaro-import-summary-item';
      const number = document.createElement('strong');
      const text = document.createElement('span');
      number.textContent = String(value);
      text.textContent = label;
      item.append(number, text);
      summary.appendChild(item);
    });
  };

  const resetAnalysis = () => {
    state.prepared = [];
    state.invalid = [];
    state.existing = [];
    state.candidates = [];
    state.report = [];
    state.fileDuplicateCount = 0;
    summaryCard.hidden = true;
    progressCard.hidden = true;
    reportCard.hidden = true;
    startButton.disabled = true;
    reportBody?.replaceChildren();
  };

  const statusLabel = (status) => {
    const map = {
      inserted: strings.statusInserted || 'Inserted',
      existing: strings.statusExisting || 'Existing',
      invalid: strings.statusInvalid || 'Invalid',
      error: strings.statusError || 'Error',
    };
    return map[status] || status;
  };

  const translateCode = (code) => {
    const codes = options.codes || {};
    return codes[code] || code || '';
  };

  const appendReportRows = () => {
    if (!reportBody) return;
    reportBody.replaceChildren();

    [...state.report]
      .sort((a, b) => Number(a.row || 0) - Number(b.row || 0))
      .forEach((item) => {
        const row = document.createElement('tr');
        const rowCell = document.createElement('td');
        const statusCell = document.createElement('td');
        const messageCell = document.createElement('td');

        rowCell.textContent = String(item.row || '—');
        statusCell.textContent = statusLabel(item.status);
        const messages = [];
        if (item.message) messages.push(translateCode(item.message));
        (item.warnings || []).forEach((warning) => messages.push(translateCode(warning)));
        if (item.details) messages.push(String(item.details));
        messageCell.textContent = messages.filter(Boolean).join('; ');

        row.append(rowCell, statusCell, messageCell);
        reportBody.appendChild(row);
      });
  };

  const updateProgress = (done, total) => {
    const percent = total > 0 ? Math.round((done / total) * 100) : 100;
    progressBar.style.width = `${percent}%`;
    progressBar.textContent = `${percent}%`;
    progressBar.parentElement?.setAttribute('aria-valuenow', String(percent));
    progressText.textContent = `${strings.importing || 'Importing'} ${done}/${total}`;
  };

  const analyze = async () => {
    if (state.busy) return;

    const missingRequired = targets
      .filter((target) => target.required && !Number.isInteger(state.mapping[target.key]));

    if (missingRequired.length) {
      window.alert(strings.mappingMissing || 'Required mapping is missing.');
      return;
    }

    state.busy = true;
    analyzeButton.disabled = true;
    analyzeButton.textContent = strings.analyzing || 'Analyzing…';
    resetAnalysis();

    try {
      const normalized = state.rows
        .map(normalizeSourceRow)
        .filter((row) => Object.values(row).some((value, index) => index === 0 || clean(value) !== ''));

      const invalid = [];
      const valid = [];

      normalized.forEach((row) => {
        const errors = validationErrors(row);
        if (errors.length) {
          invalid.push({
            row: row._row,
            status: 'invalid',
            message: errors.join(', '),
            warnings: [],
          });
        } else {
          valid.push(row);
        }
      });

      const consolidated = consolidateDuplicates(valid);
      state.fileDuplicateCount = consolidated.duplicates;
      state.prepared = consolidated.rows;
      state.invalid = [...invalid, ...consolidated.conflicts];

      const identityRows = state.prepared.map((row) => ({
        _row: row._row,
        first_name: row.first_name,
        last_name: row.last_name,
        birth_date: row.birth_date,
        tax_identifier: row.tax_identifier,
      }));

      const data = identityRows.length
        ? await postPayload(options.analyzeUrl, { rows: identityRows })
        : { existing_rows: [] };

      const existingSet = new Set((data.existing_rows || []).map(Number));
      state.existing = state.prepared
        .filter((row) => existingSet.has(Number(row._row)))
        .map((row) => ({
          row: row._row,
          status: 'existing',
          message: 'existing_person',
          warnings: [],
        }));
      state.candidates = state.prepared.filter((row) => !existingSet.has(Number(row._row)));

      renderSummary({
        total: normalized.length,
        valid: state.prepared.length,
        duplicates: state.fileDuplicateCount,
        invalid: state.invalid.length,
        existing: state.existing.length,
        newPeople: state.candidates.length,
      });

      summaryCard.hidden = false;
      startButton.disabled = state.candidates.length === 0;
      analyzeButton.textContent = strings.ready || 'Ready';
    } catch (error) {
      analyzeButton.textContent = strings.requestError || 'Error';
      window.alert(error?.message || strings.requestError || 'Request failed.');
    } finally {
      state.busy = false;
      analyzeButton.disabled = false;
    }
  };

  const importRows = async () => {
    if (state.busy || !state.candidates.length) return;

    state.busy = true;
    startButton.disabled = true;
    progressCard.hidden = false;
    reportCard.hidden = true;
    state.report = [...state.invalid, ...state.existing];
    updateProgress(0, state.candidates.length);

    let done = 0;

    try {
      for (let index = 0; index < state.candidates.length; index += batchSize) {
        const batch = state.candidates.slice(index, index + batchSize);
        const data = await postPayload(options.batchUrl, {
          profile: state.profile,
          rows: batch,
        });

        const results = Array.isArray(data.results) ? data.results : [];
        state.report.push(...results);
        done += batch.length;
        updateProgress(done, state.candidates.length);
      }

      progressText.textContent = strings.complete || 'Import complete.';
      reportCard.hidden = false;
      appendReportRows();
      startButton.textContent = strings.complete || 'Complete';
    } catch (error) {
      progressText.textContent = error?.message || strings.requestError || 'Request failed.';
      reportCard.hidden = false;
      appendReportRows();
    } finally {
      state.busy = false;
    }
  };

  const csvEscape = (value) => {
    const text = String(value ?? '');
    return `"${text.replace(/"/g, '""')}"`;
  };

  const downloadReport = () => {
    const rows = [
      [
        strings.reportRow || 'Row',
        strings.reportStatus || 'Status',
        strings.reportMessage || 'Message',
      ],
      ...[...state.report]
        .sort((a, b) => Number(a.row || 0) - Number(b.row || 0))
        .map((item) => [
          item.row || '',
          statusLabel(item.status),
          [
            ...[item.message, ...(item.warnings || [])].filter(Boolean).map(translateCode),
            item.details || '',
          ].filter(Boolean).join('; '),
        ]),
    ];

    const csv = '\uFEFF' + rows.map((row) => row.map(csvEscape).join(';')).join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'people-import-report.csv';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
  };

  const loadFile = async () => {
    resetAnalysis();
    mappingCard.hidden = true;
    fileInfo.textContent = '';

    const file = fileInput.files?.[0];
    if (!file) return;

    try {
      const decoded = await decodeFile(file);
      const delimiter = detectDelimiter(decoded.text);
      const matrix = parseCsv(decoded.text, delimiter);

      if (matrix.length < 2 || (matrix[0]?.length || 0) < 2) {
        throw new Error(strings.fileError || 'Invalid CSV file.');
      }

      const rawHeaders = matrix.shift().map((value) => clean(value));
      const width = rawHeaders.length;
      const dataRows = matrix
        .map((row) => {
          const copy = row.slice(0, width);
          while (copy.length < width) copy.push('');
          return copy;
        })
        .filter((row) => row.some((value) => clean(value) !== ''));

      state.rows = dataRows;
      state.headers = rawHeaders.map((name, index) => ({
        name,
        index,
        nonEmpty: dataRows.reduce((count, row) => count + (clean(row[index]) !== '' ? 1 : 0), 0),
        label: '',
      }));

      const occurrences = new Map();
      state.headers.forEach((header) => {
        const key = normalizeHeader(header.name);
        occurrences.set(key, (occurrences.get(key) || 0) + 1);
      });

      state.headers.forEach((header, index) => {
        const duplicate = (occurrences.get(normalizeHeader(header.name)) || 0) > 1;
        header.label = duplicate ? `${header.name} (#${index + 1})` : header.name;
      });

      state.encoding = decoded.encoding;
      state.delimiter = delimiter;
      state.profile = detectProfile();
      state.mapping = {};

      renderMapping();
      mappingCard.hidden = false;

      const encodingLabel = decoded.encoding === 'windows-1252'
        ? (strings.encodingCp1252 || 'Windows-1252')
        : (strings.encodingUtf8 || 'UTF-8');
      fileInfo.textContent = `${file.name} — ${dataRows.length} ${strings.rows || 'rows'} — ${encodingLabel}`;
    } catch (error) {
      state.rows = [];
      state.headers = [];
      fileInfo.textContent = error?.message || strings.fileError || 'Invalid CSV file.';
    }
  };

  fileInput.addEventListener('change', loadFile);
  analyzeButton.addEventListener('click', analyze);
  startButton.addEventListener('click', importRows);
  downloadReportButton?.addEventListener('click', downloadReport);
})();
