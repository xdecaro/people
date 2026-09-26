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
});
