(() => {
  'use strict';

  const init = () => {
    const nationality = document.getElementById('jform_nationality_code');
    const tin = document.getElementById('jform_tax_identifier');

    if (!nationality || !tin) {
      return;
    }

    const label = document.querySelector('label[for="jform_tax_identifier"]');
    if (!label) {
      return;
    }

    const options = Joomla.getOptions('com_xdecaropeople.person', {});
    const baseLabel = options.tinBase || 'TIN';
    const labels = options.tinLabels || {};

    const updateTinLabel = () => {
      const suffix = labels[nationality.value] || '';
      label.textContent = suffix && suffix !== baseLabel ? `${baseLabel} - ${suffix}` : baseLabel;
    };

    nationality.addEventListener('change', updateTinLabel);
    updateTinLabel();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
