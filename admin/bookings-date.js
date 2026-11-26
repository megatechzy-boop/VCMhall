(() => {
  'use strict';
  const dialog = document.getElementById('dateDialog');
  if (!dialog) return;
  const $ = (id) => document.getElementById(id);
  const hallName = (hall) => (hall === 'small' ? 'Small Hall' : 'Big Hall');
  const dateLabel = (ymd) => new Date(`${ymd}T00:00:00`).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });

  document.querySelectorAll('[data-date-id]').forEach((button) => button.addEventListener('click', () => {
    const { dateId, dateRef, dateName, dateHall, dateValue } = button.dataset;
    $('dateDialogId').value = dateId;
    $('dateDialogRef').textContent = dateRef;
    $('dateDialogGuest').textContent = dateName;
    $('dateDialogCurrent').textContent = `${hallName(dateHall)}, ${dateLabel(dateValue)}`;
    $('dateDialogHall').value = dateHall === 'small' ? 'small' : 'big';
    $('dateDialogDate').value = '';
    dialog.showModal();
    $('dateDialogDate').focus();
  }));
  dialog.querySelectorAll('[data-date-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
})();
