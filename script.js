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
