(() => {
  'use strict';

  const JoomlaApi = window.Joomla || {};

  document.documentElement.dataset.xdecaroPeopleForm = '1.2.7';

  if (typeof JoomlaApi.submitbutton !== 'function' || JoomlaApi.__xdecaroPeopleCancelWrapped) {
    return;
  }

  const previousSubmitbutton = JoomlaApi.submitbutton.bind(JoomlaApi);
  JoomlaApi.__xdecaroPeopleCancelWrapped = true;

  JoomlaApi.submitbutton = (task) => {
    if (String(task || '') === 'person.cancel') {
      // The cancel action must discard edits without being blocked by the
      // worldwide-location save validation registered by person-form.js.
      // Clearing only the transient location values makes that validation a
      // no-op; the controller receives person.cancel and discards the form.
      for (const id of ['jform_birth_place', 'jform_city']) {
        const input = document.getElementById(id);
        if (input) {
          input.setCustomValidity('');
          input.removeAttribute('aria-invalid');
          input.value = '';
        }
      }

      for (const id of ['jform_birth_place_id', 'jform_residence_place_id']) {
        const input = document.getElementById(id);
        if (input) input.value = '';
      }
    }

    return previousSubmitbutton(task);
  };
})();
