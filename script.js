const menuButton = document.querySelector('.menu-toggle');
const mainNav = document.querySelector('.main-nav');

menuButton.addEventListener('click', () => {
  const isOpen = mainNav.classList.toggle('is-open');
  menuButton.setAttribute('aria-expanded', String(isOpen));
  menuButton.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
  menuButton.querySelector('use').setAttribute('href', isOpen ? '#i-close' : '#i-menu');
});

mainNav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
  mainNav.classList.remove('is-open');
  menuButton.setAttribute('aria-expanded', 'false');
  menuButton.setAttribute('aria-label', 'Open menu');
  menuButton.querySelector('use').setAttribute('href', '#i-menu');
}));

const form = document.querySelector('.enquiry-card');
if (form) {
  const eventSelect = form.elements.event;
  const monthSelect = form.querySelector('.booking-month');
  const dateSelect = form.elements.date;
  const submitButton = form.querySelector('[type="submit"]');
  const status = form.querySelector('.booking-status');
  let bookedDates = new Set();
  let today = '';

  const showStatus = (message) => { status.textContent = message; };
  const fillDates = () => {
    const selected = dateSelect.value;
    dateSelect.replaceChildren(new Option('Select an available date', ''));
    if (!monthSelect.value) return;
    const [year, month] = monthSelect.value.split('-').map(Number);
    const days = new Date(year, month, 0).getDate();
    for (let day = 1; day <= days; day += 1) {
      const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
      if (date < today || bookedDates.has(date)) continue;
      const label = new Date(year, month - 1, day).toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short' });
      dateSelect.add(new Option(label, date));
    }
    if ([...dateSelect.options].some((option) => option.value === selected)) dateSelect.value = selected;
    if (dateSelect.options.length === 1) showStatus('No available dates this month. Choose another month.');
  };
  const loadAvailability = async () => {
    const response = await fetch(form.action, { cache: 'no-store' });
    if (!response.ok) throw new Error('Availability unavailable');
    const data = await response.json();
    bookedDates = new Set(data.bookedDates);
    today = data.today;
    if (!monthSelect.options.length || !monthSelect.options[1]) {
      const [year, month] = today.split('-').map(Number);
      monthSelect.replaceChildren(new Option('Select a month', ''));
      for (let offset = 0; offset < 24; offset += 1) {
        const value = new Date(year, month - 1 + offset, 1);
        monthSelect.add(new Option(value.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' }), `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}`));
      }
    }
    fillDates();
    submitButton.disabled = false;
  };
  monthSelect.addEventListener('change', () => { showStatus(''); fillDates(); });
  submitButton.disabled = true;
  loadAvailability().catch(() => showStatus('Booking service is unavailable. Please call the venue.'));

  document.querySelectorAll('.occasion').forEach((button) => {
    button.addEventListener('click', () => {
      eventSelect.value = button.dataset.event;
      form.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(() => form.elements.name.focus({ preventScroll: true }), 450);
    });
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    submitButton.disabled = true;
    showStatus('Saving your request...');
    try {
      const response = await fetch(form.action, { method: 'POST', body: new FormData(form) });
      const result = await response.json();
      showStatus(result.message || result.error || 'Please try again.');
      if (response.ok) {
        form.reset();
        fillDates();
      } else if (response.status === 409) {
        await loadAvailability();
      }
    } catch {
      showStatus('Could not save your request. Please call the venue.');
    } finally {
      submitButton.disabled = false;
    }
  });
}

const spaces = {
  big: { title: 'Big Hall', description: 'Capacity up to 1200 people. Fully air-conditioned.', image: 'assets/hero-hall.jpg' },
  small: { title: 'Small Hall', description: 'Capacity up to 350 people. Fully air-conditioned.', image: 'assets/small-hall.jpg' },
  dining: { title: 'Dining Hall', description: 'Capacity up to 400 people. Fully air-conditioned.', image: 'assets/dining-hall.jpg' },
  vip: { title: 'VIP A/C Dining', description: 'Dining for up to 50 people. Fully air-conditioned.', image: 'assets/vip-dining.jpg' },
  rooms: { title: 'A/C Guest Rooms', description: 'Up to 8 comfortable, modern bedrooms.', image: 'assets/guest-room.jpg' },
};

const spaceDialog = document.getElementById('space-dialog');
document.querySelectorAll('[data-space]').forEach((button) => button.addEventListener('click', () => {
  const space = spaces[button.dataset.space];
  document.getElementById('dialog-image').src = space.image;
  document.getElementById('dialog-image').alt = space.title;
  document.getElementById('dialog-title').textContent = space.title;
  document.getElementById('dialog-description').textContent = space.description;
  spaceDialog.showModal();
}));
spaceDialog?.querySelector('.dialog-close').addEventListener('click', () => spaceDialog.close());
document.getElementById('dialog-enquire')?.addEventListener('click', () => spaceDialog.close());

const galleryDialog = document.getElementById('gallery-dialog');
if (galleryDialog) {
  const galleryButtons = [...document.querySelectorAll('.gallery-photo')];
  let currentPhoto = 0;
  const visiblePhotos = () => galleryButtons.filter((button) => !button.hidden);
  function showPhoto(index) {
    const photos = visiblePhotos();
    currentPhoto = (index + photos.length) % photos.length;
    const photo = photos[currentPhoto];
    const image = document.getElementById('lightbox-image');
    if (image.tagName === 'IMG') {
      image.src = photo.dataset.image;
      image.alt = photo.dataset.caption;
    } else {
      image.classList.toggle('reference-photo', Boolean(photo.dataset.crop));
      image.style.cssText = `${photo.dataset.crop || ''}background-image:url("${photo.dataset.image}")`;
      image.setAttribute('aria-label', photo.dataset.caption);
    }
    document.getElementById('lightbox-caption').textContent = photo.dataset.caption;
    document.getElementById('photo-count').textContent = `${currentPhoto + 1} / ${photos.length}`;
    if (!galleryDialog.open) galleryDialog.showModal();
  }
  galleryButtons.forEach((button) => button.addEventListener('click', () => showPhoto(visiblePhotos().indexOf(button))));
  document.querySelector('.gallery-more')?.addEventListener('click', () => showPhoto(0));
  document.getElementById('photo-prev').addEventListener('click', () => showPhoto(currentPhoto - 1));
  document.getElementById('photo-next').addEventListener('click', () => showPhoto(currentPhoto + 1));
  galleryDialog.querySelector('.dialog-close').addEventListener('click', () => galleryDialog.close());
  galleryDialog.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowLeft') showPhoto(currentPhoto - 1);
    if (event.key === 'ArrowRight') showPhoto(currentPhoto + 1);
  });
}

[spaceDialog, galleryDialog].filter(Boolean).forEach((dialog) => dialog.addEventListener('click', (event) => {
  const bounds = dialog.getBoundingClientRect();
  if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
}));

const loadWebsitePopup = async () => {
  const response = await fetch('popup.php', { cache: 'no-store' });
  if (!response.ok) return;
  const popup = await response.json();
  const closedAt = Number(localStorage.getItem('vcm-popup-closed') || 0);
  if (!popup.enabled || Date.now() - closedAt < popup.cooldownHours * 3600000) return;
  const overlay = document.createElement('div'); overlay.className = 'website-popup-overlay';
  const card = document.createElement('section'); card.className = `website-popup-card website-popup-${popup.type}`; card.setAttribute('role', 'dialog'); card.setAttribute('aria-modal', 'true'); card.setAttribute('aria-label', popup.headline || 'Venue announcement');
  const close = document.createElement('button'); close.className = 'website-popup-close'; close.type = 'button'; close.textContent = '×'; close.setAttribute('aria-label', 'Close popup'); card.append(close);
  if (popup.type === 'image' && (popup.desktopImage || popup.mobileImage)) {
    const picture = document.createElement('picture');
    if (popup.mobileImage) { const source = document.createElement('source'); source.media = '(max-width: 600px)'; source.srcset = popup.mobileImage; picture.append(source); }
    const image = document.createElement('img'); image.src = popup.desktopImage || popup.mobileImage; image.alt = popup.headline || 'Venue offer'; picture.append(image);
    if (popup.clickUrl) { const link = document.createElement('a'); link.href = popup.clickUrl; link.append(picture); card.append(link); } else card.append(picture);
  } else {
    const content = document.createElement('div'); content.className = 'website-popup-copy';
    const title = document.createElement('h2'); title.textContent = popup.headline || ''; const text = document.createElement('p'); text.textContent = popup.text || ''; content.append(title, text);
    const actions = document.createElement('div'); actions.className = 'website-popup-actions';
    [[popup.primaryText, popup.primaryUrl, 'primary'], [popup.secondaryText, popup.secondaryUrl, 'secondary']].forEach(([label, url, style]) => { if (!label || !url) return; const link = document.createElement('a'); link.textContent = label; link.href = url; link.className = style; actions.append(link); });
    content.append(actions); card.append(content);
  }
  const dismiss = () => { localStorage.setItem('vcm-popup-closed', String(Date.now())); overlay.remove(); };
  close.addEventListener('click', dismiss); overlay.addEventListener('click', (event) => { if (event.target === overlay) dismiss(); }); document.addEventListener('keydown', function escape(event) { if (event.key === 'Escape') { dismiss(); document.removeEventListener('keydown', escape); } });
  overlay.append(card); document.body.append(overlay); close.focus();
};
loadWebsitePopup().catch(() => {});
