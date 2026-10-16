(() => {
  const menu = document.getElementById('sidebar');
  const toggle = document.getElementById('menuToggle');
  toggle?.addEventListener('click', () => {
    const open = menu.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(open));
  });
  menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
    menu.classList.remove('open');
    toggle?.setAttribute('aria-expanded', 'false');
  }));

  document.addEventListener('submit', (event) => {
    const action = event.submitter?.value || event.target.elements.namedItem('action')?.value || '';
    const prompts = {
      event_delete: 'Delete this event type? Existing bookings keep their saved event names.',
      test_email: 'Send a test email to the venue email address now?'
    };
    if (prompts[action] && !window.confirm(prompts[action])) event.preventDefault();
  });

  const form = document.querySelector('.popup-form');
  if (!form) return;
  const value = (name) => form.elements.namedItem(name)?.value?.trim() || '';
  let previewUrls = [];
  const objectUrl = (file) => { const url = URL.createObjectURL(file); previewUrls.push(url); return url; };
  const type = () => value('popup_type') || 'text';
  const imageFor = (mobile = false) => {
    const field = form.elements.namedItem(mobile ? 'popup_mobile_image' : 'popup_desktop_image');
    if (field?.files?.[0]) return objectUrl(field.files[0]);
    const uploaded = form.dataset[mobile ? 'mobileImage' : 'desktopImage'];
    const fallback = form.dataset[mobile ? 'desktopImage' : 'mobileImage'];
    const path = uploaded || fallback;
    return path ? `../../${path.replace(/^\/+/, '')}` : '';
  };
  const popupClickUrl = () => {
    const action = value('popup_click_type');
    if (action === 'enquiry') return '../../contact.html#enquire';
    if (action === 'custom') return value('popup_click_url');
    if (action === 'whatsapp') {
      let phone = (form.dataset.venuePhone || '').replace(/\D/g, '');
      if (phone.length === 10) phone = `91${phone}`;
      return phone ? `https://wa.me/${phone}` : '';
    }
    return '';
  };
  const isWhatsApp = (url) => /^(?:https?:)?\/\/(?:wa\.me|api\.whatsapp\.com|web\.whatsapp\.com)\//i.test(url);
  const makeActionLink = (label, url, className) => {
    if (!label || !url) return null;
    const link = document.createElement('a');
    link.className = className;
    link.href = url;
    link.textContent = label;
    if (isWhatsApp(url)) { link.target = '_blank'; link.rel = 'noopener noreferrer'; }
    return link;
  };
  const makeCopy = () => {
    const copy = document.createElement('div');
    copy.className = 'website-popup-copy';
    if (value('popup_headline')) {
      const heading = document.createElement('h2');
      heading.textContent = value('popup_headline');
      copy.append(heading);
    }
    if (value('popup_text')) {
      const text = document.createElement('p');
      text.textContent = value('popup_text');
      copy.append(text);
    }
    const actions = document.createElement('div');
    actions.className = 'website-popup-actions';
    const primary = makeActionLink(value('popup_primary_text'), value('popup_primary_url'), 'primary');
    const secondary = makeActionLink(value('popup_secondary_text'), value('popup_secondary_url'), 'secondary');
    if (primary) actions.append(primary);
    if (secondary) actions.append(secondary);
    if (actions.childElementCount) copy.append(actions);
    return copy;
  };
  const makeCard = (mobile = false) => {
    const card = document.createElement('section');
    card.className = `website-popup-card website-popup-${type()}`;
    const close = document.createElement('button');
    close.className = 'website-popup-close';
    close.type = 'button';
    close.textContent = '×';
    close.setAttribute('aria-label', 'Close preview');
    close.addEventListener('click', () => document.getElementById('popupPreviewDialog')?.close());
    card.append(close);

    const previewImage = imageFor(mobile);
    if (type() === 'image' && previewImage) {
      const image = document.createElement('img');
      image.src = previewImage;
      image.alt = value('popup_headline') || 'Popup image preview';
      image.onerror = () => {
        card.classList.remove('website-popup-image');
        card.classList.add('website-popup-text');
        image.remove();
        card.append(makeCopy());
      };
      const clickUrl = popupClickUrl();
      if (clickUrl) {
        const link = document.createElement('a');
        link.href = clickUrl;
        if (isWhatsApp(clickUrl)) { link.target = '_blank'; link.rel = 'noopener noreferrer'; }
        link.append(image);
        card.append(link);
      } else card.append(image);
      card.append(makeCopy());
    } else card.append(makeCopy());
    return card;
  };
  const renderSmallPreview = (box, mobile) => {
    box.replaceChildren(makeCard(mobile));
  };
  const render = () => {
    previewUrls.forEach((url) => URL.revokeObjectURL(url));
    previewUrls = [];
    const selected = type();
    form.querySelectorAll('[data-popup-fields]').forEach((section) => {
      section.hidden = section.dataset.popupFields !== selected;
    });
    renderSmallPreview(document.getElementById('desktopPreview'), false);
    renderSmallPreview(document.getElementById('mobilePreview'), true);
  };
  form.querySelectorAll('input[name="popup_type"]').forEach((input) => input.addEventListener('change', render));
  form.querySelectorAll('input,textarea,select').forEach((input) => {
    input.addEventListener('input', render);
    input.addEventListener('change', render);
  });
  render();

  document.getElementById('openPopupPreview')?.addEventListener('click', () => {
    let dialog = document.getElementById('popupPreviewDialog');
    if (!dialog) {
      dialog = document.createElement('dialog');
      dialog.id = 'popupPreviewDialog';
      dialog.className = 'popup-preview-dialog';
      dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
      document.body.append(dialog);
    }
    dialog.replaceChildren(makeCard(window.matchMedia('(max-width: 600px)').matches));
    dialog.showModal();
  });
})();
