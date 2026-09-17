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
  const dateInput = form.elements.date;
  const today = new Date();
  dateInput.min = new Date(today.getTime() - today.getTimezoneOffset() * 60000).toISOString().slice(0, 10);

  document.querySelectorAll('.occasion').forEach((button) => {
    button.addEventListener('click', () => {
      eventSelect.value = button.dataset.event;
      form.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(() => form.elements.name.focus({ preventScroll: true }), 450);
    });
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const details = new FormData(form);
    const message = [
      'Hello, I would like to enquire about an event at Late Venutai Chavan Multipurpose Hall.',
      `Name: ${details.get('name')}`,
      `Phone: ${details.get('phone')}`,
      `Event: ${details.get('event')}`,
      `Date: ${details.get('date')}`,
      details.get('message') ? `Message: ${details.get('message')}` : null,
    ].filter(Boolean).join('\n');
    window.open(`https://wa.me/919359567494?text=${encodeURIComponent(message)}`, '_blank', 'noopener,noreferrer');
  });
}

const spaces = {
  big: { title: 'Big Hall', description: 'Capacity up to 1200 people. Fully air-conditioned.', image: 'assets/hero-hall.png' },
  small: { title: 'Small Hall', description: 'Capacity up to 350 people. Fully air-conditioned.', image: 'assets/small-hall.png' },
  dining: { title: 'Dining Hall', description: 'Capacity up to 400 people. Fully air-conditioned.', image: 'assets/dining-hall.png' },
  vip: { title: 'VIP A/C Dining', description: 'Dining for up to 50 people. Fully air-conditioned.', image: 'assets/vip-dining.png' },
  rooms: { title: 'A/C Guest Rooms', description: 'Up to 8 comfortable, modern bedrooms.', image: 'assets/guest-room.png' },
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
  function showPhoto(index) {
    currentPhoto = (index + galleryButtons.length) % galleryButtons.length;
    const photo = galleryButtons[currentPhoto];
    document.getElementById('lightbox-image').src = photo.dataset.image;
    document.getElementById('lightbox-image').alt = photo.dataset.caption;
    document.getElementById('lightbox-caption').textContent = photo.dataset.caption;
    document.getElementById('photo-count').textContent = `${currentPhoto + 1} / ${galleryButtons.length}`;
    if (!galleryDialog.open) galleryDialog.showModal();
  }
  galleryButtons.forEach((button, index) => button.addEventListener('click', () => showPhoto(index)));
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
