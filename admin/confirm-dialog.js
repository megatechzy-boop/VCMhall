// Centered confirmation box used instead of the browser's own confirm() popup.
// adminConfirm(message) resolves true/false; adminConfirmSubmit(event, message) pauses a form
// submit, asks, and re-submits the same form with the same button when the user agrees.
(() => {
  'use strict';
  const DANGER = /\b(delete|remove|cancel|close|unavailable|make (?:this )?date available|switch off|deactivate)\b/i;
  let dialog = null;
  let resolver = null;

  function build() {
    dialog = document.createElement('dialog');
    dialog.className = 'confirm-dialog';
    dialog.setAttribute('aria-labelledby', 'confirmDialogTitle');
    dialog.setAttribute('aria-describedby', 'confirmDialogText');
    dialog.innerHTML = '<div class="confirm-dialog-body"><span class="confirm-dialog-icon" aria-hidden="true">!</span>'
      + '<h2 id="confirmDialogTitle">Please confirm</h2><p id="confirmDialogText"></p></div>'
      + '<div class="confirm-dialog-actions"><button type="button" class="secondary-button" data-confirm-no>No, go back</button>'
      + '<button type="button" class="primary-button" data-confirm-yes>Yes, continue</button></div>';
    document.body.appendChild(dialog);
    const finish = (answer) => { if (dialog.open) dialog.close(); const done = resolver; resolver = null; if (done) done(answer); };
    dialog.querySelector('[data-confirm-no]').addEventListener('click', () => finish(false));
    dialog.querySelector('[data-confirm-yes]').addEventListener('click', () => finish(true));
    dialog.addEventListener('cancel', (event) => { event.preventDefault(); finish(false); });
    dialog.addEventListener('click', (event) => { if (event.target === dialog) finish(false); });
  }

  window.adminConfirm = (message, options = {}) => new Promise((resolve) => {
    if (!dialog) build();
    if (resolver) resolver(false);
    resolver = resolve;
    const danger = options.danger ?? DANGER.test(message);
    dialog.classList.toggle('is-danger', danger);
    dialog.querySelector('#confirmDialogTitle').textContent = options.title || (danger ? 'Are you sure?' : 'Please confirm');
    dialog.querySelector('#confirmDialogText').textContent = message;
    const yes = dialog.querySelector('[data-confirm-yes]');
    yes.textContent = options.confirmText || 'Yes, continue';
    yes.className = danger ? 'danger-button' : 'primary-button';
    dialog.showModal();
    // Safer default: the "go back" button has focus, so a stray Enter does not confirm.
    dialog.querySelector('[data-confirm-no]').focus();
  });

  window.adminConfirmSubmit = (event, message) => {
    const form = event.target;
    // Already confirmed (this is the re-submit), or another handler is already asking: do nothing.
    if (form.dataset.confirmed === '1' || event.defaultPrevented) return;
    event.preventDefault();
    const submitter = event.submitter && event.submitter.form === form ? event.submitter : null;
    window.adminConfirm(message).then((ok) => {
      if (!ok) return;
      form.dataset.confirmed = '1';
      if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter || undefined);
      else { if (submitter && submitter.name) { const keep = document.createElement('input'); keep.type = 'hidden'; keep.name = submitter.name; keep.value = submitter.value; form.appendChild(keep); } form.submit(); }
      setTimeout(() => { delete form.dataset.confirmed; }, 0);
    });
  };

  // Any button with data-confirm="..." asks first, on every admin page.
  document.addEventListener('submit', (event) => {
    const message = event.submitter?.dataset.confirm;
    if (message) window.adminConfirmSubmit(event, message);
  });
})();
