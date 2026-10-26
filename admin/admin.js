(() => {
  document.addEventListener('submit', (event) => {
    const action = event.target.querySelector('[name="action"]')?.value || event.submitter?.value || '';
    const prompts = {
      confirm: 'Confirm this enquiry as a booking? The date will be reserved.',
      cancel: 'Continue? This will close the enquiry or cancel the booking, and make a reserved date available.',
      block: 'Mark the selected hall date as unavailable?',
      add_booking: 'Add this booking and reserve the selected date?',
      add_payment: 'Record this payment? Check the amount and payment method first.',
      send_email: 'Send this email to the guest now?'
    };
    if (prompts[action] && !window.confirm(prompts[action])) event.preventDefault();
  });
  const menu = document.getElementById('sidebar'); const menuToggle = document.getElementById('menuToggle');
  menuToggle?.addEventListener('click', () => { const open = menu.classList.toggle('open'); menuToggle.setAttribute('aria-expanded', String(open)); });
  menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => { menu.classList.remove('open'); menuToggle?.setAttribute('aria-expanded', 'false'); }));
  const dataNode = document.getElementById('dashboardData');
  if (!dataNode) return;
  const data = JSON.parse(dataNode.textContent);
  const rows = new Map(data.rows.map((row) => [String(row.id), row]));
  const drawer = document.getElementById('detailDrawer');
  const backdrop = document.getElementById('drawerBackdrop');
  const csrf = document.querySelector('input[name="csrf"]')?.value || '';
  const statusLabels = { pending: 'New enquiry', in_discussion: 'In discussion', quote_sent: 'Quote sent', follow_up: 'Reminder set', converted: 'Booking created', confirmed: 'Booked', blocked: 'Date unavailable', cancelled: 'Cancelled', completed: 'Completed' };
  const sourceLabels = { website: 'Website', 'meta-ads': 'Meta Ads', whatsapp: 'WhatsApp', instagram: 'Instagram', phone: 'Phone', 'walk-in': 'Walk-in', other: 'Other', admin: 'Added by staff' };
  const paymentMethodLabels = { upi: 'UPI', cash: 'Cash', 'bank-transfer': 'Bank transfer', card: 'Card', cheque: 'Cheque', other: 'Other' };
  const money = (value) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(Number(value || 0));
  const dateText = (value, withTime = false) => value ? new Intl.DateTimeFormat('en-IN', withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' }).format(new Date(value.replace(' ', 'T'))) : 'Not set';
  const addText = (parent, tag, text, className = '') => { const element = document.createElement(tag); element.textContent = text; if (className) element.className = className; parent.appendChild(element); return element; };
  const postForm = (action, id, label, className = '') => {
    const form = document.createElement('form'); form.method = 'post'; if (className) form.className = className;
    [['csrf', csrf], ['id', id], ['action', action]].forEach(([name, value]) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.appendChild(input); });
    const button = addText(form, 'button', label); button.type = 'submit'; return form;
  };
  const detailItem = (parent, label, value) => { const item = document.createElement('div'); addText(item, 'span', label); addText(item, 'strong', value || 'Not set'); parent.appendChild(item); };

  function switchDrawerTab(name) {
    document.querySelectorAll('[data-drawer-tab]').forEach((button) => button.classList.toggle('active', button.dataset.drawerTab === name));
    document.querySelectorAll('[data-drawer-panel]').forEach((panel) => panel.classList.toggle('active', panel.dataset.drawerPanel === name));
  }

  function openDrawer(id) {
    const row = rows.get(String(id)); if (!row) return;
    const payments = data.payments[String(id)] || [];
    const activities = data.activity[String(id)] || [];
    const reference = `VCM-${String(id).padStart(6, '0')}`;
    document.getElementById('drawerReference').textContent = reference;
    document.getElementById('drawerTitle').textContent = row.status === 'blocked' ? 'Date unavailable' : row.record_type === 'enquiry' ? 'Enquiry details' : 'Booking details';
    document.querySelectorAll('[data-record-input]').forEach((input) => { input.value = id; });
    const details = document.getElementById('drawerDetails'); details.replaceChildren();
    [['Guest name', row.name], ['Phone', row.phone], ['Email', row.email], ['Event type', row.event_type], ['Hall', `${row.hall[0].toUpperCase()}${row.hall.slice(1)} Hall`], ['Event date', dateText(`${row.event_date}T00:00:00`)], ['Guest count', row.guests ? `${row.guests} guests` : 'Not set'], ['Contact method', sourceLabels[row.source] || 'Other'], ['Status', statusLabels[row.status] || row.status]].forEach(([label, value]) => detailItem(details, label, value));
    const contacts = document.getElementById('drawerContactActions'); contacts.replaceChildren();
    if (row.phone) { let phone = String(row.phone).replace(/\D/g, ''); if (phone.length === 10) phone = `91${phone}`; const link = addText(contacts, 'a', 'Send WhatsApp'); link.href = `https://wa.me/${phone}?text=${encodeURIComponent(`Hello ${row.name}, following up about your ${row.event_type} in ${row.hall} hall on ${dateText(`${row.event_date}T00:00:00`)}. - Late Venutai Chavan Multipurpose Hall`)}`; link.target = '_blank'; link.rel = 'noopener'; }
    if (row.email) { const emailForm = postForm('send_email', id, 'Email guest'); contacts.appendChild(emailForm); }
    const conversion = document.getElementById('drawerConversion'); conversion.replaceChildren();
    if (row.record_type === 'enquiry' && ['pending', 'in_discussion', 'quote_sent', 'follow_up'].includes(row.status)) { const form = postForm('confirm', id, 'Confirm as booking', 'conversion-form'); form.querySelector('button').className = 'primary-button'; conversion.appendChild(form); }
    const source = document.getElementById('drawerSource'); source.value = row.source === 'admin' ? 'other' : row.source; source.disabled = row.status === 'blocked';
    const totalInput = document.getElementById('drawerTotal'); totalInput.value = row.total_amount || 0; totalInput.disabled = row.status === 'blocked';
    document.getElementById('drawerNotes').value = row.internal_notes || '';
    document.getElementById('drawerFollowup').value = row.follow_up_at ? row.follow_up_at.replace(' ', 'T').slice(0, 16) : '';
    const overdue = row.follow_up_at && !row.follow_up_completed_at && row.follow_up_at < new Date().toISOString().slice(0, 19).replace('T', ' ');
    document.getElementById('followupStatus').textContent = row.follow_up_completed_at ? `Reminder completed ${dateText(row.follow_up_completed_at, true)}` : row.follow_up_at ? `${overdue ? 'Reminder overdue: ' : 'Reminder set for '}${dateText(row.follow_up_at, true)}` : 'No reminder set yet.';
    const total = Number(row.total_amount || 0); const received = Number(row.paid_amount || 0); const balance = Math.max(0, total - received);
    document.getElementById('paymentTotal').textContent = money(total); document.getElementById('paymentReceived').textContent = money(received); document.getElementById('paymentBalance').textContent = money(balance);
    const steps = document.getElementById('paymentSteps'); steps.replaceChildren(); for (let step = 1; step <= data.paymentLimit; step += 1) { const item = addText(steps, 'span', step); if (step <= payments.length) item.classList.add('complete'); }
    const paymentStatus = document.getElementById('drawerPaymentStatus'); const status = received <= 0 ? 'Not Paid' : balance > 0 ? 'Partial' : 'Paid'; paymentStatus.textContent = status; paymentStatus.className = `payment-status ${status.toLowerCase().replace(' ', '-')}`;
    const paymentList = document.getElementById('paymentList'); paymentList.replaceChildren();
    payments.forEach((payment) => { const item = document.createElement('article'); addText(item, 'strong', `${money(payment.amount)} · ${paymentMethodLabels[payment.payment_method] || payment.payment_method}`); addText(item, 'span', `${dateText(payment.received_at, true)}${payment.reference ? ` · ${payment.reference}` : ''}`); if (payment.notes) addText(item, 'span', payment.notes); paymentList.appendChild(item); });
    if (!payments.length) addText(paymentList, 'span', 'No payments recorded.', 'muted');
    const paymentForm = drawer.querySelector('.payment-form'); paymentForm.hidden = row.status !== 'confirmed' || payments.length >= data.paymentLimit || total <= 0 || balance <= 0; paymentForm.querySelector('input[name="amount"]').max = String(balance);
    const activityList = document.getElementById('activityList'); activityList.replaceChildren();
    activities.forEach((activity) => { const item = document.createElement('article'); addText(item, 'strong', activity.action.replaceAll('_', ' ')); addText(item, 'span', dateText(activity.created_at, true)); if (activity.details) addText(item, 'span', activity.details); activityList.appendChild(item); });
    if (!activities.length) addText(activityList, 'span', 'No activity recorded yet.', 'muted');
    switchDrawerTab('details'); drawer.classList.add('open'); drawer.setAttribute('aria-hidden', 'false'); backdrop.hidden = false; document.body.style.overflow = 'hidden'; document.getElementById('closeDrawer').focus();
  }
  function closeDrawer() { drawer.classList.remove('open'); drawer.setAttribute('aria-hidden', 'true'); backdrop.hidden = true; document.body.style.overflow = ''; }
  document.querySelectorAll('[data-calendar-date]').forEach((button) => button.addEventListener('click', () => { const date = encodeURIComponent(button.dataset.calendarDate); const target = button.classList.contains('booked') ? `bookings.php?date=${date}` : button.classList.contains('blocked') ? `bookings.php?tab=blocked&date=${date}` : button.classList.contains('enquiry') ? `enquiries.php?from=${date}&to=${date}` : `bookings.php?date=${date}`; window.location.href = target; }));
  document.querySelectorAll('[data-view-id]').forEach((button) => button.addEventListener('click', (event) => { event.stopPropagation(); openDrawer(button.dataset.viewId); }));
  document.querySelectorAll('tr[data-record-id]').forEach((row) => { row.addEventListener('click', (event) => { if (!event.target.closest('a,button,details,form')) openDrawer(row.dataset.recordId); }); row.addEventListener('keydown', (event) => { if (event.key === 'Enter') openDrawer(row.dataset.recordId); }); });
  document.getElementById('closeDrawer')?.addEventListener('click', closeDrawer); backdrop?.addEventListener('click', closeDrawer);
  document.querySelectorAll('[data-drawer-tab]').forEach((button) => button.addEventListener('click', () => switchDrawerTab(button.dataset.drawerTab)));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && drawer.classList.contains('open')) closeDrawer(); });
  const bookingDialog = document.getElementById('bookingDialog'); const blockDialog = document.getElementById('blockDialog');
  document.querySelector('[data-open-booking]')?.addEventListener('click', () => bookingDialog.showModal()); document.querySelector('[data-open-block]')?.addEventListener('click', () => blockDialog.showModal());
  document.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => button.closest('dialog').close()));
  [bookingDialog, blockDialog].forEach((dialog) => dialog?.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); }));
})();
