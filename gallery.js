const filters = document.querySelectorAll('.gallery-filter');
const photos = document.querySelectorAll('.gallery-tile');

filters.forEach((filter) => filter.addEventListener('click', () => {
  filters.forEach((button) => button.setAttribute('aria-pressed', String(button === filter)));
  photos.forEach((photo) => {
    photo.hidden = filter.dataset.filter !== 'all' && photo.dataset.category !== filter.dataset.filter;
  });
}));
