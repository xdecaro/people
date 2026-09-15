(() => {
  'use strict';

  const JoomlaApi = window.Joomla || {};
  const options = typeof JoomlaApi.getOptions === 'function'
    ? JoomlaApi.getOptions('com_xdecaropeople.person', {})
    : {};

  document.documentElement.dataset.xdecaroPeopleForm = '1.2.14';

  const locationValidators = [];

  const byField = (id, name) => document.getElementById(id)
    || document.querySelector(`[name="${name}"]`)
    || document.querySelector(`[name="${name}[]"]`);

  const fieldGroup = (element) => element?.closest('.control-group') || element?.parentElement || null;

  const setVisible = (element, visible, compact = false) => {
    const group = fieldGroup(element);
    if (!group) return;
    group.hidden = !visible;
    group.classList.toggle('xdecaro-conditional-small', Boolean(visible && compact));
  };

  const setConditionalRequired = (element, required) => {
    if (!element) return;

    const group = fieldGroup(element);
    element.required = Boolean(required);
    element.setAttribute('aria-required', required ? 'true' : 'false');
    group?.classList.toggle('xdecaro-field-required', Boolean(required));

    if (!required) {
      element.setCustomValidity('');
      element.removeAttribute('aria-invalid');
    }
  };

  const selectedValues = (select) => {
    if (!select) return [];
    if (select.selectedOptions) {
      return Array.from(select.selectedOptions).map((option) => option.value);
    }
    return select.value !== undefined ? [String(select.value)] : [];
  };

  const formatBirthDateInput = (value) => {
    const source = String(value ?? '').trim();
    if (source === '') return '';
    if (!/^[0-9\s./-]+$/.test(source)) return source;

    const digits = source.replace(/\D/g, '').slice(0, 8);
    if (digits === '') return '';

    const parts = [digits.slice(0, 2)];
    if (digits.length > 2) parts.push(digits.slice(2, 4));
    if (digits.length > 4) parts.push(digits.slice(4, 8));

    return parts.filter(Boolean).join('/');
  };

  const initBirthDateInput = () => {
    const input = byField('jform_birth_date', 'jform[birth_date]');
    if (!input || input.dataset.xdecaroBirthDateReady === '1') return;

    input.dataset.xdecaroBirthDateReady = '1';
    input.setAttribute('inputmode', 'numeric');

    const format = () => {
      const formatted = formatBirthDateInput(input.value);
      if (formatted !== input.value) input.value = formatted;
    };

    input.addEventListener('input', format);
    input.addEventListener('paste', () => window.setTimeout(format, 0));
  };

  const normalizePhoneValue = (value) => String(value ?? '').trim().replace(/\s+/g, '');

  const phoneFields = () => [
    byField('jform_phone', 'jform[phone]'),
    byField('jform_whatsapp', 'jform[whatsapp]'),
  ].filter(Boolean);

  const normalizePhoneFields = () => {
    phoneFields().forEach((field) => {
      const normalized = normalizePhoneValue(field.value);
      if (field.value !== normalized) field.value = normalized;
    });
  };

  const initPhoneNormalization = () => {
    phoneFields().forEach((field) => {
      if (field.dataset.xdecaroPhoneReady === '1') return;
      field.dataset.xdecaroPhoneReady = '1';
      field.addEventListener('blur', () => {
        const normalized = normalizePhoneValue(field.value);
        if (field.value !== normalized) field.value = normalized;
      });
    });
  };

  const initConditionalFields = () => {
    const disabilityStatus = byField('jform_disability_status', 'jform[disability_status]');
    const disabilityTypes = byField('jform_disability_types', 'jform[disability_types]');
    const disabilityOther = byField('jform_disability_other', 'jform[disability_other]');
    const accessibilityNeeds = byField('jform_accessibility_needs', 'jform[accessibility_needs]');
    const accessibilityOther = byField('jform_accessibility_other', 'jform[accessibility_other]');

    const update = () => {
      if (disabilityStatus && disabilityTypes) {
        const enabled = String(disabilityStatus.value) === '1';
        const requiresOther = enabled && selectedValues(disabilityTypes).includes('other');
        setVisible(disabilityTypes, enabled);
        if (disabilityOther) {
          setVisible(disabilityOther, requiresOther, true);
          setConditionalRequired(disabilityOther, requiresOther);
        }
      } else if (disabilityOther) {
        setConditionalRequired(disabilityOther, false);
      }

      if (accessibilityNeeds && accessibilityOther) {
        const requiresOther = selectedValues(accessibilityNeeds).includes('other');
        setVisible(accessibilityOther, requiresOther, true);
        setConditionalRequired(accessibilityOther, requiresOther);
      } else if (accessibilityOther) {
        setConditionalRequired(accessibilityOther, false);
      }
    };

    document.addEventListener('change', (event) => {
      if ([disabilityStatus, disabilityTypes, accessibilityNeeds].includes(event.target)) {
        window.setTimeout(update, 0);
      }
    });

    update();
    window.setTimeout(update, 100);
  };

  const tokenName = () => {
    if (options.token) return options.token;
    const token = document.querySelector('#adminForm input[type="hidden"][name][value="1"]');
    return token?.name || '';
  };

  const setSelectValue = (select, value) => {
    if (!select || !value) return;
    select.value = value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const revealAndReport = (entry) => {
    const input = entry.input;
    const pane = input.closest('.tab-pane');

    if (pane && pane.id && !pane.classList.contains('active')) {
      const escapedId = window.CSS?.escape ? CSS.escape(pane.id) : pane.id.replace(/([:.])/g, '\\$1');
      const trigger = document.querySelector(`[data-bs-target="#${escapedId}"], [href="#${escapedId}"]`);
      trigger?.click();
    }

    window.setTimeout(() => {
      input.focus();
      entry.validate(true);
    }, 0);
  };

  const validateAllLocations = (report = false) => {
    for (const entry of locationValidators) {
      if (!entry.validate(false)) {
        entry.close();
        if (report) revealAndReport(entry);
        return false;
      }
    }
    return true;
  };

  const fieldLabelFor = (field) => {
    const label = field?.labels?.[0] || null;
    const text = label?.textContent?.replace(/\s*\*\s*$/, '').trim();
    if (text) return text;

    return field?.getAttribute('aria-label')
      || field?.getAttribute('placeholder')
      || field?.name
      || field?.id
      || 'Campo';
  };

  const tabLabelFor = (field) => {
    const pane = field?.closest('.tab-pane');
    if (!pane?.id) return '';

    const escapedId = window.CSS?.escape ? CSS.escape(pane.id) : pane.id.replace(/([:.])/g, '\\$1');
    const trigger = document.querySelector(`[data-bs-target="#${escapedId}"], [href="#${escapedId}"]`);
    return trigger?.textContent?.trim() || '';
  };

  const collectInvalidFields = (form) => {
    validateAllLocations(false);

    const seen = new Set();
    return Array.from(form.querySelectorAll(':invalid'))
      .filter((field) => field instanceof HTMLElement && !field.disabled)
      .map((field) => {
        const key = field.id || field.name || String(seen.size);
        if (seen.has(key)) return null;
        seen.add(key);

        return {
          field,
          label: fieldLabelFor(field),
          tab: tabLabelFor(field),
        };
      })
      .filter(Boolean);
  };

  const revealInvalidField = (field) => {
    if (!field) return;

    const pane = field.closest('.tab-pane');
    if (pane && pane.id && !pane.classList.contains('active')) {
      const escapedId = window.CSS?.escape ? CSS.escape(pane.id) : pane.id.replace(/([:.])/g, '\\$1');
      const trigger = document.querySelector(`[data-bs-target="#${escapedId}"], [href="#${escapedId}"]`);
      trigger?.click();
    }

    window.setTimeout(() => {
      field.focus({ preventScroll: true });
      field.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }, 0);
  };

  const showValidationSummary = (invalidFields) => {
    if (!invalidFields.length) return;

    const details = invalidFields.map(({ label, tab }) => tab ? `${tab} → ${label}` : label);
    const title = options.validationSummaryTitle || 'Impossibile salvare: controlla i campi indicati.';
    const intro = options.validationSummaryIntro || 'Controlla:';
    const message = `${title} ${intro} ${details.join('; ')}.`;

    if (typeof JoomlaApi.removeMessages === 'function') {
      JoomlaApi.removeMessages();
    }

    if (typeof JoomlaApi.renderMessages === 'function') {
      JoomlaApi.renderMessages({ error: [message] });
    } else {
      window.alert(message);
    }

    revealInvalidField(invalidFields[0].field);
  };

  const validateFormForSave = (form, report = true) => {
    if (!form) return true;

    normalizePhoneFields();

    const invalidFields = collectInvalidFields(form);
    if (!invalidFields.length) return true;

    if (report) showValidationSummary(invalidFields);
    return false;
  };

  const initWorldCity = ({ inputId, inputName, countryId, countryName, placeId, placeName, regionId, regionName }) => {
    const input = byField(inputId, inputName);
    const country = byField(countryId, countryName);
    const hiddenId = byField(placeId, placeName);
    const region = byField(regionId, regionName);

    if (!input || !hiddenId || input.dataset.xdecaroLocationReady === '1') return;

    input.dataset.xdecaroLocationReady = '1';
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');

    const container = document.createElement('div');
    container.className = 'xdecaro-location-results';
    container.hidden = true;
    container.setAttribute('role', 'listbox');
    container.setAttribute('aria-label', input.getAttribute('aria-label') || input.name || 'Location');
    document.body.appendChild(container);

    let timer = 0;
    let request = null;
    let activeIndex = -1;
    let items = [];
    let internalCountryChange = false;
    let confirmedValue = hiddenId.value.trim() !== '' ? input.value.trim() : '';

    const selectionMessage = options.locationSelectionRequired || 'Seleziona una località dall’elenco.';

    const validateSelection = (report = false) => {
      const currentValue = input.value.trim();
      const invalid = currentValue !== ''
        && (hiddenId.value.trim() === '' || confirmedValue === '' || confirmedValue !== currentValue);

      input.setCustomValidity(invalid ? selectionMessage : '');
      input.setAttribute('aria-invalid', invalid ? 'true' : 'false');

      if (invalid && report) {
        input.reportValidity();
      }

      return !invalid;
    };

    const positionMenu = () => {
      if (container.hidden) return;

      const rect = input.getBoundingClientRect();
      const gap = 4;
      const viewportPadding = 8;
      const below = Math.max(0, window.innerHeight - rect.bottom - gap - viewportPadding);
      const above = Math.max(0, rect.top - gap - viewportPadding);
      const openAbove = below < 180 && above > below;
      const available = Math.max(120, Math.min(240, openAbove ? above : below));
      const width = Math.max(240, Math.min(rect.width, window.innerWidth - (viewportPadding * 2)));
      const left = Math.max(viewportPadding, Math.min(rect.left, window.innerWidth - viewportPadding - width));

      container.classList.toggle('is-above', openAbove);
      container.style.left = `${left}px`;
      container.style.width = `${width}px`;
      container.style.maxHeight = `${available}px`;

      if (openAbove) {
        container.style.top = 'auto';
        container.style.bottom = `${Math.max(viewportPadding, window.innerHeight - rect.top + gap)}px`;
      } else {
        container.style.bottom = 'auto';
        container.style.top = `${Math.min(window.innerHeight - viewportPadding, rect.bottom + gap)}px`;
      }
    };

    const close = (abortRequest = true) => {
      if (abortRequest && request) {
        request.abort();
        request = null;
      }
      container.hidden = true;
      container.replaceChildren();
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activeIndex = -1;
      items = [];
    };

    const showContainer = () => {
      container.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      positionMenu();
    };

    const openMessage = (message, className = 'xdecaro-location-empty') => {
      container.replaceChildren();
      const row = document.createElement('div');
      row.className = className;
      row.textContent = message;
      container.appendChild(row);
      showContainer();
    };

    const selectItem = (index) => {
      const item = items[index];
      if (!item) return;

      input.value = item.name || '';
      hiddenId.value = item.id || '';
      confirmedValue = input.value.trim();

      if (region) region.value = item.admin1 || '';

      if (country && item.country_code && country.value !== item.country_code) {
        internalCountryChange = true;
        try {
          setSelectValue(country, item.country_code);
        } finally {
          internalCountryChange = false;
        }
      }

      input.setCustomValidity('');
      input.setAttribute('aria-invalid', 'false');
      close(false);
      input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const render = (results) => {
      container.replaceChildren();
      items = Array.isArray(results) ? results : [];
      activeIndex = -1;

      if (!items.length) {
        openMessage(options.locationEmpty || 'Nessuna località trovata.');
        return;
      }

      items.forEach((item, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'xdecaro-location-option';
        button.id = `${inputId}-location-${index}`;
        button.setAttribute('role', 'option');
        button.textContent = item.label || item.name || '';
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => selectItem(index));
        container.appendChild(button);
      });

      showContainer();
    };

    const setActive = (index) => {
      const buttons = Array.from(container.querySelectorAll('.xdecaro-location-option'));
      if (!buttons.length) return;

      activeIndex = Math.max(0, Math.min(buttons.length - 1, index));
      buttons.forEach((button, buttonIndex) => {
        const active = buttonIndex === activeIndex;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      input.setAttribute('aria-activedescendant', buttons[activeIndex].id);
      buttons[activeIndex].scrollIntoView({ block: 'nearest' });
    };

    const search = async () => {
      const query = input.value.trim();
      const minChars = Number(options.locationMinChars || 2);
      if (query.length < minChars) {
        close();
        return;
      }

      request?.abort();
      request = new AbortController();
      const currentRequest = request;
      openMessage(options.locationLoading || 'Ricerca località…', 'xdecaro-location-loading');

      const endpoint = options.locationUrl || 'index.php?option=com_xdecaropeople&task=location.search&format=json';
      const url = new URL(endpoint, window.location.href);
      url.searchParams.set('q', query);
      url.searchParams.set('limit', '15');

      const token = tokenName();
      if (token) url.searchParams.set(token, '1');
      if (country?.value) url.searchParams.set('country', country.value);

      try {
        const response = await fetch(url.toString(), {
          method: 'GET',
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { Accept: 'application/json' },
          signal: currentRequest.signal,
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const payload = await response.json();
        if (payload?.success === false) {
          throw new Error(payload.message || options.locationError || 'Location search failed.');
        }

        if (request !== currentRequest) return;
        request = null;
        render(payload?.data?.items || payload?.items || []);
      } catch (error) {
        if (request === currentRequest) request = null;
        if (error?.name === 'AbortError') return;
        openMessage(options.locationError || 'Impossibile cercare le località. Riprova.', 'xdecaro-location-error');
      }
    };

    const queueSearch = () => {
      hiddenId.value = '';
      confirmedValue = '';
      if (region) region.value = '';
      input.setCustomValidity('');
      input.setAttribute('aria-invalid', 'false');
      window.clearTimeout(timer);
      timer = window.setTimeout(search, 250);
    };

    input.addEventListener('input', queueSearch);

    input.addEventListener('keydown', (event) => {
      if (container.hidden) return;

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setActive(activeIndex + 1);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActive(activeIndex <= 0 ? items.length - 1 : activeIndex - 1);
      } else if (event.key === 'Enter' && activeIndex >= 0) {
        event.preventDefault();
        selectItem(activeIndex);
      } else if (event.key === 'Escape') {
        close();
      }
    });

    input.addEventListener('focus', () => {
      if (input.value.trim().length >= Number(options.locationMinChars || 2) && hiddenId.value === '') {
        queueSearch();
      }
    });

    input.addEventListener('blur', () => {
      window.setTimeout(() => {
        close();
        validateSelection(false);
      }, 180);
    });

    country?.addEventListener('change', () => {
      if (internalCountryChange) return;

      hiddenId.value = '';
      confirmedValue = '';
      if (region) region.value = '';
      input.setCustomValidity('');
      input.setAttribute('aria-invalid', 'false');
      close();

      if (input.value.trim().length >= Number(options.locationMinChars || 2)) {
        queueSearch();
      }
    });

    document.addEventListener('pointerdown', (event) => {
      if (container.hidden) return;
      if (event.target === input || input.contains(event.target) || container.contains(event.target)) return;
      close();
    }, true);

    document.addEventListener('show.bs.tab', close);
    document.addEventListener('hide.bs.tab', close);

    locationValidators.push({ input, validate: validateSelection, close });

    window.addEventListener('resize', positionMenu, { passive: true });
    window.addEventListener('scroll', positionMenu, { passive: true, capture: true });
  };

  const initToolbarValidation = () => {
    const form = document.getElementById('adminForm');

    if (form && form.dataset.xdecaroValidationBound !== '1') {
      form.dataset.xdecaroValidationBound = '1';
      form.addEventListener('submit', (event) => {
        const task = String(form.querySelector('[name="task"]')?.value || '');
        if (task === 'person.cancel') return;

        if (!validateFormForSave(form, true)) {
          event.preventDefault();
          event.stopImmediatePropagation();
        }
      }, true);
    }

    if (typeof JoomlaApi.submitbutton !== 'function' || JoomlaApi.__xdecaroPeopleSubmitWrapped) return;

    const originalSubmitbutton = JoomlaApi.submitbutton.bind(JoomlaApi);
    JoomlaApi.__xdecaroPeopleSubmitWrapped = true;

    JoomlaApi.submitbutton = (task) => {
      if (/^person\.(apply|save|save2new)$/.test(String(task || '')) && !validateFormForSave(form, true)) {
        return false;
      }

      return originalSubmitbutton(task);
    };
  };

  const initNationalityCompatibility = () => {
    const nationalities = byField('jform_nationality_codes', 'jform[nationality_codes]');
    const legacy = byField('jform_nationality_code', 'jform[nationality_code]');
    if (!nationalities || !legacy) return;

    const sync = () => {
      legacy.value = selectedValues(nationalities).filter(Boolean)[0] || '';
    };

    nationalities.addEventListener('change', sync);
    sync();
  };

  const init = () => {
    initConditionalFields();
    initNationalityCompatibility();
    initBirthDateInput();
    initPhoneNormalization();

    initWorldCity({
      inputId: 'jform_birth_place',
      inputName: 'jform[birth_place]',
      countryId: 'jform_birth_country_code',
      countryName: 'jform[birth_country_code]',
      placeId: 'jform_birth_place_id',
      placeName: 'jform[birth_place_id]',
      regionId: 'jform_birth_region',
      regionName: 'jform[birth_region]',
    });

    initWorldCity({
      inputId: 'jform_city',
      inputName: 'jform[city]',
      countryId: 'jform_country_code',
      countryName: 'jform[country_code]',
      placeId: 'jform_residence_place_id',
      placeName: 'jform[residence_place_id]',
      regionId: 'jform_region',
      regionName: 'jform[region]',
    });

    initToolbarValidation();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
