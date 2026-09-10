(() => {
  'use strict';

  const options = Joomla.getOptions('com_xdecaropeople.person', {});

  const fieldGroup = (element) => element?.closest('.control-group') || element?.parentElement || null;

  const setVisible = (element, visible, compact = false) => {
    const group = fieldGroup(element);
    if (!group) {
      return;
    }

    group.hidden = !visible;
    group.classList.toggle('xdecaro-conditional-small', Boolean(visible && compact));
  };

  const selectedValues = (select) => Array.from(select?.selectedOptions || []).map((option) => option.value);

  const initConditionalFields = () => {
    const disabilityStatus = document.getElementById('jform_disability_status');
    const disabilityTypes = document.getElementById('jform_disability_types');
    const disabilityOther = document.getElementById('jform_disability_other');
    const accessibilityNeeds = document.getElementById('jform_accessibility_needs');
    const accessibilityOther = document.getElementById('jform_accessibility_other');

    const updateDisability = () => {
      if (!disabilityStatus || !disabilityTypes || !disabilityOther) {
        return;
      }

      const enabled = disabilityStatus.value === '1';
      setVisible(disabilityTypes, enabled);
      setVisible(disabilityOther, enabled && selectedValues(disabilityTypes).includes('other'), true);
    };

    const updateAccessibility = () => {
      if (!accessibilityNeeds || !accessibilityOther) {
        return;
      }

      setVisible(accessibilityOther, selectedValues(accessibilityNeeds).includes('other'), true);
    };

    disabilityStatus?.addEventListener('change', updateDisability);
    disabilityTypes?.addEventListener('change', updateDisability);
    accessibilityNeeds?.addEventListener('change', updateAccessibility);

    updateDisability();
    updateAccessibility();
  };

  const setSelectValue = (select, value) => {
    if (!select || !value) {
      return;
    }

    select.value = value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const initWorldCity = ({ inputId, countryId, placeId, regionId }) => {
    const input = document.getElementById(inputId);
    const country = document.getElementById(countryId);
    const hiddenId = document.getElementById(placeId);
    const region = document.getElementById(regionId);

    if (!input || !hiddenId || !options.locationUrl || !options.token) {
      return;
    }

    const container = document.createElement('div');
    container.className = 'xdecaro-location-results';
    container.hidden = true;
    container.setAttribute('role', 'listbox');
    container.setAttribute('aria-label', input.getAttribute('aria-label') || input.name || 'Location');
    input.insertAdjacentElement('afterend', container);
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');

    let timer = null;
    let request = null;
    let activeIndex = -1;
    let items = [];

    const close = () => {
      container.hidden = true;
      container.replaceChildren();
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activeIndex = -1;
      items = [];
    };

    const render = (results) => {
      container.replaceChildren();
      items = Array.isArray(results) ? results : [];
      activeIndex = -1;

      if (!items.length) {
        const empty = document.createElement('div');
        empty.className = 'xdecaro-location-empty';
        empty.textContent = options.locationEmpty || 'No locations found.';
        container.appendChild(empty);
      } else {
        items.forEach((item, index) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'xdecaro-location-option';
          button.id = `${inputId}-location-${index}`;
          button.setAttribute('role', 'option');
          button.dataset.index = String(index);
          button.textContent = item.label || item.name || '';
          button.addEventListener('mousedown', (event) => event.preventDefault());
          button.addEventListener('click', () => selectItem(index));
          container.appendChild(button);
        });
      }

      container.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    };

    const selectItem = (index) => {
      const item = items[index];
      if (!item) {
        return;
      }

      input.value = item.name || '';
      hiddenId.value = item.id || '';
      if (region) {
        region.value = item.admin1 || '';
      }
      if (country && item.country_code) {
        setSelectValue(country, item.country_code);
      }
      close();
      input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const setActive = (index) => {
      const buttons = Array.from(container.querySelectorAll('.xdecaro-location-option'));
      if (!buttons.length) {
        return;
      }

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

      const url = new URL(options.locationUrl, window.location.href);
      url.searchParams.set('q', query);
      url.searchParams.set('limit', '15');
      url.searchParams.set(options.token, '1');
      if (country?.value) {
        url.searchParams.set('country', country.value);
      }

      try {
        const response = await fetch(url.toString(), {
          method: 'GET',
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
          signal: request.signal,
        });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const payload = await response.json();
        if (payload?.success === false) {
          throw new Error(payload.message || options.locationError || 'Location search failed.');
        }

        render(payload?.data?.items || []);
      } catch (error) {
        if (error?.name === 'AbortError') {
          return;
        }
        close();
      }
    };

    input.addEventListener('input', () => {
      hiddenId.value = '';
      if (region) {
        region.value = '';
      }
      window.clearTimeout(timer);
      timer = window.setTimeout(search, 300);
    });

    input.addEventListener('keydown', (event) => {
      if (container.hidden) {
        return;
      }

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

    input.addEventListener('blur', () => window.setTimeout(close, 150));

    country?.addEventListener('change', () => {
      hiddenId.value = '';
      if (region) {
        region.value = '';
      }
    });
  };

  const initNationalityCompatibility = () => {
    const nationalities = document.getElementById('jform_nationality_codes');
    const legacy = document.getElementById('jform_nationality_code');
    if (!nationalities || !legacy) {
      return;
    }

    const sync = () => {
      legacy.value = selectedValues(nationalities).filter(Boolean)[0] || '';
    };

    nationalities.addEventListener('change', sync);
    sync();
  };

  const init = () => {
    initConditionalFields();
    initNationalityCompatibility();

    initWorldCity({
      inputId: 'jform_birth_place',
      countryId: 'jform_birth_country_code',
      placeId: 'jform_birth_place_id',
      regionId: 'jform_birth_region',
    });

    initWorldCity({
      inputId: 'jform_city',
      countryId: 'jform_country_code',
      placeId: 'jform_residence_place_id',
      regionId: 'jform_region',
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
