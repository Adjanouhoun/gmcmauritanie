document.querySelectorAll('[data-gmc-contact]').forEach((form) => {
  let sending = false;
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (sending || !form.reportValidity()) return;
    sending = true;
    const button = form.querySelector('[type="submit"]');
    const success = form.querySelector('#contactSuccess');
    const error = form.querySelector('#contactError');
    const message = form.querySelector('#errorMessage');
    button.disabled = true;
    form.setAttribute('aria-busy', 'true');
    success.classList.add('d-none');
    error.classList.add('d-none');
    try {
      const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json'}});
      const data = await response.json();
      if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Le message n’a pas pu être envoyé.');
      success.classList.remove('d-none');
      form.reset();
    } catch (failure) {
      message.textContent = failure.message || 'Une erreur est survenue. Veuillez réessayer.';
      error.classList.remove('d-none');
    } finally {
      sending = false;
      button.disabled = false;
      form.removeAttribute('aria-busy');
    }
  });
});
