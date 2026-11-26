(() => {
  'use strict';
  const dialog = document.getElementById('paymentDialog');
  const dataEl = document.getElementById('bookingPaymentData');
  if (!dialog || !dataEl) return;
  const data = JSON.parse(dataEl.textContent);
  const $ = (id) => document.getElementById(id);
  const money = (value) => '₹' + Number(value || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
  const el = (tag, text, cls) => { const n = document.createElement(tag); if (cls) n.className = cls; n.textContent = text; return n; };

  function open(id) {
    const row = data.rows[String(id)];
    if (!row) return;
    const payments = data.payments[String(id)] || [];
    const balance = Math.max(0, row.total - row.paid);
    $('paymentDialogRef').textContent = row.ref;
    $('paymentDialogTitle').textContent = 'Payments · ' + row.name;
    $('paymentDialogGuest').textContent = `${row.event} · ${row.date}`;
    $('pdTotal').textContent = money(row.total);
    $('pdPaid').textContent = money(row.paid);
    $('pdBalance').textContent = money(balance);
    dialog.querySelectorAll('[data-pd-id]').forEach((input) => { input.value = id; });
    dialog.querySelector('[data-pd-source]').value = row.source;

    const list = $('pdList');
    list.replaceChildren();
    if (!payments.length) list.append(el('span', 'No payments recorded yet.', 'muted'));
    for (const p of payments) {
      const item = document.createElement('article');
      item.append(el('strong', `${money(p.amount)} · ${data.labels[p.payment_method] || p.payment_method}`));
      item.append(el('span', `${p.received_at || ''}${p.reference ? ' · ' + p.reference : ''}`));
      if (p.notes) item.append(el('span', p.notes));
      list.append(item);
    }

    // Explain why payments cannot be recorded instead of hiding the form silently.
    let blocked = '';
    if (row.total <= 0) blocked = 'Set the agreed total first, then you can record payments.';
    else if (balance <= 0) blocked = 'This booking is fully paid.';
    else if (payments.length >= data.limit) blocked = `The limit of ${data.limit} payments per booking is reached. Change it in Settings → Payments.`;
    const totalForm = $('pdTotalForm'), paymentForm = $('pdPaymentForm'), notice = $('pdBlocked');
    totalForm.hidden = !data.canEditTotal;
    $('pdTotalInput').value = row.total > 0 ? row.total : '';
    $('pdTotalHint').textContent = row.total > 0 ? 'Change the agreed total if the deal changed.' : 'Set the total amount agreed for this booking before recording payments.';
    paymentForm.hidden = blocked !== '';
    $('pdAmount').max = String(balance);
    notice.hidden = blocked === '' || (row.total <= 0 && data.canEditTotal);
    notice.textContent = blocked + (row.total <= 0 && !data.canEditTotal ? ' Ask a manager to set it.' : '');
    dialog.showModal();
    (paymentForm.hidden ? $('pdTotalInput') : $('pdAmount')).focus();
  }

  document.querySelectorAll('[data-payment-id]').forEach((button) => button.addEventListener('click', () => open(button.dataset.paymentId)));
  dialog.querySelectorAll('[data-payment-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
  const wanted = new URLSearchParams(window.location.search).get('pay');
  if (wanted && data.rows[wanted]) open(wanted);
})();
