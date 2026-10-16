(() => {
  const qs = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => [...root.querySelectorAll(sel)];
  const todayStr = new Date().toISOString().slice(0, 10);

  qsa('[data-current-year]').forEach((year) => {
    year.textContent = new Date().getFullYear();
  });

  const SPACE_INFO = {
    big: { name: 'Big Hall', cap: 'Up to 1200 guests', ideal: 'Weddings & large events', page: 'big-hall' },
    small: { name: 'Small Hall', cap: 'Up to 350 guests', ideal: 'Engagements & birthdays', page: 'small-hall' },
    dining: { name: 'Dining Hall', cap: 'Up to 400 guests', ideal: 'Meals & reception dining', page: 'dining-hall' },
    vip: { name: 'VIP A/C Dining', cap: 'Up to 50 guests', ideal: 'Close family & VIP dining', page: 'vip-dining' },
    rooms: { name: 'A/C Guest Rooms', cap: 'Up to 8 rooms', ideal: 'Family & invitee stay', page: 'guest-rooms' },
  };

  const HALL_HIGHLIGHTS = {
    big: [
      { label: 'Full A/C Hall', num: '1200', cap: 'up to · guests' },
      { label: 'Big Dining', num: '400', cap: 'at a time' },
      { label: 'VIP A/C Dining', num: '50', cap: 'max · at a time' },
      { label: 'A/C Guest Rooms', num: '8', cap: 'rooms' },
    ],
    small: [
      { label: 'Full A/C Hall', num: '350', cap: 'up to · guests' },
      { label: 'A/C Dining', num: '50', cap: 'at a time' },
    ],
  };

  qsa('input[type="date"]').forEach((input) => {
    if (!input.min) input.min = todayStr;
  });

  const currentFile = (location.pathname.split('/').pop() || '/').toLowerCase();
  qsa('.main-nav a, .nav-drawer-links a, .footer-links a').forEach((link) => {
    const href = (link.getAttribute('href') || '').split('#')[0].toLowerCase();
    if (href && href === currentFile) link.setAttribute('aria-current', 'page');
  });

  const scrollToHash = () => {
    if (!location.hash) return;
    let id = location.hash.slice(1);
    try { id = decodeURIComponent(id); } catch { return; }
    const target = document.getElementById(id);
    if (!target) return;
    const root = document.documentElement;
    const prev = root.style.scrollBehavior;
    root.style.scrollBehavior = 'auto';
    target.scrollIntoView();
    root.style.scrollBehavior = prev;
  };
  const retryHash = (n) => {
    scrollToHash();
    if (n > 0) setTimeout(() => retryHash(n - 1), 450);
  };
  retryHash(3);
  window.addEventListener('hashchange', scrollToHash);
  window.addEventListener('load', () => retryHash(2));
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(scrollToHash);

  const header = qs('.site-header');
  const floats = qs('.float-actions');
  const toTop = qs('.to-top');
  const progressTrack = qs('.reading-progress span');
  const docEl = document.documentElement;
  const onScroll = () => {
    const y = window.scrollY;
    if (header) header.classList.toggle('is-scrolled', y > 12);
    if (floats) floats.classList.toggle('is-visible', y > 420);
    if (toTop) toTop.classList.toggle('is-visible', y > 560);
    if (progressTrack) {
      const max = docEl.scrollHeight - docEl.clientHeight;
      progressTrack.style.width = max > 0 ? ((y / max) * 100) + '%' : '0%';
    }
  };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
  if (toTop) {
    toTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }

  const menuButton = qs('.menu-toggle');
  const drawer = qs('#nav-drawer');
  let drawerTimer = 0;

  const openDrawer = () => {
    if (!drawer || !menuButton) return;
    clearTimeout(drawerTimer);
    drawer.hidden = false;
    void drawer.offsetHeight;
    drawer.classList.add('is-open');
    menuButton.setAttribute('aria-expanded', 'true');
    menuButton.setAttribute('aria-label', 'Close menu');
    const use = qs('use', menuButton);
    if (use) use.setAttribute('href', '#i-close');
    document.body.classList.add('is-locked');
    qs('.nav-drawer-close', drawer)?.focus();
  };

  const closeDrawer = () => {
    if (!drawer || !menuButton) return;
    drawer.classList.remove('is-open');
    menuButton.setAttribute('aria-expanded', 'false');
    menuButton.setAttribute('aria-label', 'Open menu');
    const use = qs('use', menuButton);
    if (use) use.setAttribute('href', '#i-menu');
    document.body.classList.remove('is-locked');
    clearTimeout(drawerTimer);
    drawerTimer = window.setTimeout(() => { drawer.hidden = true; }, 320);
  };

  menuButton?.addEventListener('click', () => {
    if (drawer?.classList.contains('is-open')) closeDrawer();
    else openDrawer();
  });
  qsa('[data-drawer-close]').forEach((el) => el.addEventListener('click', closeDrawer));
  qsa('.nav-drawer-links a, .nav-drawer-actions a').forEach((link) => link.addEventListener('click', closeDrawer));

  const revealEls = qsa('.reveal');
  if (revealEls.length) {
    const guardReveal = (el) => {
      window.setTimeout(() => {
        if (!el.classList.contains('is-visible')) return;
        if (Number(getComputedStyle(el).opacity) >= 1) return;
        el.style.setProperty('transition', 'none', 'important');
        el.style.setProperty('opacity', '1', 'important');
        el.style.setProperty('transform', 'none', 'important');
      }, 1500);
    };
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            guardReveal(entry.target);
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.1, rootMargin: '0px 0px -36px' });
      revealEls.forEach((el) => io.observe(el));
    }
    const checkReveals = () => {
      revealEls.forEach((el) => {
        if (el.classList.contains('is-visible')) return;
        if (el.getBoundingClientRect().top < window.innerHeight * 0.94) {
          el.classList.add('is-visible');
          guardReveal(el);
        }
      });
    };
    checkReveals();
    window.addEventListener('scroll', checkReveals, { passive: true });
    window.addEventListener('resize', checkReveals);
    window.setTimeout(checkReveals, 600);
  }

  const eventCards = qsa('[data-event-card]');
  const revealPanel = qs('#celebrate-reveal');
  if (eventCards.length && revealPanel) {
    const revealTitle = qs('#reveal-title');
    const revealPage = qs('#reveal-page');
    const revealCta = qs('#reveal-cta');

    const closePanel = () => {
      eventCards.forEach((card) => card.setAttribute('aria-expanded', 'false'));
      revealPanel.classList.remove('is-open');
      revealPanel.hidden = true;
    };

    eventCards.forEach((card) => {
      card.addEventListener('click', () => {
        const wasOpen = card.getAttribute('aria-expanded') === 'true';
        eventCards.forEach((other) => other.setAttribute('aria-expanded', 'false'));
        if (wasOpen) {
          closePanel();
          return;
        }
        card.setAttribute('aria-expanded', 'true');
        const eventName = card.dataset.event || '';
        const heading = qs('h3', card)?.textContent || eventName;
        const keys = (card.dataset.spaces || '').split(',').map((s) => s.trim()).filter(Boolean);
        if (revealTitle) revealTitle.textContent = heading;
        const revealHl = qs('#reveal-highlights');
        if (revealHl) {
          revealHl.replaceChildren();
          const halls = [];
          if (keys.includes('big')) halls.push('big');
          if (keys.includes('small')) halls.push('small');
          halls.forEach((h) => {
            const group = document.createElement('div');
            group.className = 'reveal-hl-group';
            const title = document.createElement('p');
            title.className = 'reveal-hl-title';
            title.textContent = h === 'big' ? 'Big Hall highlights' : 'Small Hall highlights';
            group.append(title);
            (HALL_HIGHLIGHTS[h] || []).forEach((pt) => {
              const row = document.createElement('div');
              row.className = 'reveal-hl-row';
              const lbl = document.createElement('span');
              lbl.className = 'hl-label';
              lbl.textContent = pt.label;
              const num = document.createElement('span');
              num.className = 'hl-num';
              num.textContent = pt.num;
              const cap = document.createElement('span');
              cap.className = 'hl-cap';
              cap.textContent = pt.cap;
              row.append(lbl, num, cap);
              group.append(row);
            });
            revealHl.append(group);
          });
        }
        if (revealPage) revealPage.href = card.dataset.page || 'events';
        if (revealCta) revealCta.href = `check-availability?event=${encodeURIComponent(eventName)}`;
        revealPanel.hidden = false;
        void revealPanel.offsetHeight;
        revealPanel.classList.add('is-open');
        revealPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      });
    });
  }

  const filterButtons = qsa('.gallery-filter');
  const galleryTiles = qsa('[data-gallery-item]');
  if (filterButtons.length && galleryTiles.length) {
    const categories = new Set(galleryTiles.map((tile) => tile.dataset.category).filter(Boolean));
    filterButtons.forEach((button) => {
      const value = button.dataset.filter;
      if (value !== 'all' && !categories.has(value)) button.hidden = true;
    });
    const emptyNote = qs('.gallery-empty');
    const applyFilter = (value) => {
      let visible = 0;
      galleryTiles.forEach((tile) => {
        const show = value === 'all' || tile.dataset.category === value;
        tile.hidden = !show;
        if (show) visible += 1;
      });
      if (emptyNote) emptyNote.hidden = visible > 0;
    };
    filterButtons.forEach((button) => {
      button.addEventListener('click', () => {
        filterButtons.forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
        applyFilter(button.dataset.filter || 'all');
      });
    });
  }

  const lightbox = qs('#lightbox');
  if (lightbox && galleryTiles.length) {
    const stage = qs('#lightbox-stage');
    const titleEl = qs('#lightbox-title');
    const categoryEl = qs('#lightbox-category');
    const countEl = qs('#lightbox-count');
    const closeBtn = qs('.lightbox-close', lightbox);
    const prevBtn = qs('#lightbox-prev');
    const nextBtn = qs('#lightbox-next');
    let index = 0;
    let lastFocus = null;

    const visibleTiles = () => galleryTiles.filter((tile) => !tile.hidden);

    const render = () => {
      const tiles = visibleTiles();
      if (!tiles.length) return;
      index = (index + tiles.length) % tiles.length;
      const tile = tiles[index];
      const image = tile.dataset.image || '';
      const caption = tile.dataset.caption || tile.dataset.label || '';
      const crop = tile.dataset.crop || '';
      stage.replaceChildren();
      if (crop) {
        const frame = document.createElement('span');
        frame.className = 'crop-frame';
        frame.setAttribute('role', 'img');
        frame.setAttribute('aria-label', caption);
        frame.style.backgroundImage = `url("${image}")`;
        frame.style.cssText += `;${crop}`;
        stage.append(frame);
      } else {
        const img = document.createElement('img');
        img.src = image;
        img.alt = caption;
        img.draggable = false;
        stage.append(img);
      }
      if (titleEl) titleEl.textContent = caption;
      if (categoryEl) categoryEl.textContent = tile.dataset.label || tile.dataset.category || '';
      if (countEl) countEl.textContent = `${index + 1} / ${tiles.length}`;
    };

    const openLightbox = (tile) => {
      const tiles = visibleTiles();
      const start = tiles.indexOf(tile);
      index = start >= 0 ? start : 0;
      lastFocus = document.activeElement;
      lightbox.hidden = false;
      void lightbox.offsetHeight;
      lightbox.classList.add('is-open');
      document.body.classList.add('is-locked');
      render();
      closeBtn?.focus();
    };

    const closeLightbox = () => {
      lightbox.classList.remove('is-open');
      lightbox.hidden = true;
      document.body.classList.remove('is-locked');
      if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    };

    const step = (delta) => {
      index += delta;
      render();
    };

    galleryTiles.forEach((tile) => tile.addEventListener('click', () => openLightbox(tile)));
    closeBtn?.addEventListener('click', closeLightbox);
    prevBtn?.addEventListener('click', () => step(-1));
    nextBtn?.addEventListener('click', () => step(1));
    lightbox.addEventListener('click', (event) => {
      if (event.target === lightbox) closeLightbox();
    });

    let touchX = 0;
    lightbox.addEventListener('touchstart', (event) => {
      touchX = event.changedTouches[0].clientX;
    }, { passive: true });
    lightbox.addEventListener('touchend', (event) => {
      const delta = event.changedTouches[0].clientX - touchX;
      if (Math.abs(delta) > 46) step(delta > 0 ? -1 : 1);
    }, { passive: true });

    document.addEventListener('keydown', (event) => {
      if (!lightbox.classList.contains('is-open')) return;
      if (event.key === 'Escape') closeLightbox();
      else if (event.key === 'ArrowLeft') step(-1);
      else if (event.key === 'ArrowRight') step(1);
      else if (event.key === 'Tab') {
        const focusables = qsa('button, [href], input, select, textarea', lightbox).filter((el) => !el.disabled && el.offsetParent !== null);
        if (!focusables.length) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && drawer?.classList.contains('is-open')) closeDrawer();
  });

  const setStatus = (el, message, state) => {
    if (!el) return;
    el.textContent = message;
    el.classList.remove('is-loading', 'is-ok', 'is-error');
    if (state) el.classList.add(state);
  };

  const HALL_NAMES = { big: 'Big Hall', small: 'Small Hall' };

  const bookingHall = (value) => (value === 'small' || value === 'vip' ? 'small' : 'big');

  const availabilityCache = new Map();

  const fetchAvailability = (hall) => {
    if (!availabilityCache.has(hall)) {
      availabilityCache.set(
        hall,
        fetch(`booking.php?hall=${hall}`, { cache: 'no-store' })
          .then((response) => (response.ok ? response.json() : null))
          .then((data) => (data && Array.isArray(data.bookedDates) ? new Set(data.bookedDates) : null))
          .catch(() => null)
      );
    }
    return availabilityCache.get(hall);
  };

  const clearFieldError = (field) => {
    const wrap = field.closest('.field') || field.parentElement;
    qsa('.field-error', wrap).forEach((el) => el.remove());
    field.removeAttribute('aria-invalid');
  };

  const setFieldError = (field, message) => {
    const wrap = field.closest('.field') || field.parentElement;
    qsa('.field-error', wrap).forEach((el) => el.remove());
    field.setAttribute('aria-invalid', 'true');
    const error = document.createElement('p');
    error.className = 'field-error';
    error.textContent = message;
    wrap?.append(error);
  };

  const prepareBookingData = (form) => {
    const data = new FormData(form);
    const hallSelect = qs('select[name="hall"]', form);
    if (!hallSelect) return data;
    const raw = hallSelect.value;
    if (!raw) {
      data.delete('hall');
    } else if (raw !== 'small' && raw !== 'big') {
      const label = (hallSelect.selectedOptions[0]?.textContent || '').split(' — ')[0].trim();
      data.set('hall', bookingHall(raw));
      const message = String(data.get('message') || '');
      data.set('message', message ? `${message}\nPreferred space: ${label}.` : `Preferred space: ${label}.`);
    }
    return data;
  };

  const checkAvailability = async (form) => {
    const dateInput = qs('input[type="date"][name="date"]', form);
    if (!dateInput || !dateInput.value) return true;
    const hallSelect = qs('select[name="hall"]', form);
    const hall = bookingHall(hallSelect ? hallSelect.value : '');
    const booked = await fetchAvailability(hall);
    if (!booked || !booked.has(dateInput.value)) return true;
    setFieldError(dateInput, `This date is already booked for the ${HALL_NAMES[hall]}. Please choose another date.`);
    dateInput.focus();
    return false;
  };

  const postForm = async (form) => {
    const status = qs('.form-status', form);
    const submit = qs('[type="submit"]', form);
    const honeypot = form.elements.website;
    if (honeypot && honeypot.value) return;
    if (!form.reportValidity()) return;
    if (submit) submit.disabled = true;
    setStatus(status, 'Sending your enquiry…', 'is-loading');
    try {
      const available = await checkAvailability(form);
      if (!available) {
        setStatus(status, 'That date is already booked. Please choose another date.', 'is-error');
        return;
      }
      const response = await fetch(form.action, { method: 'POST', body: prepareBookingData(form) });
      let result = {};
      try { result = await response.json(); } catch { result = {}; }
      if (response.ok) {
        setStatus(status, result.message || 'Thank you — your enquiry has been received. Our team will contact you shortly.', 'is-ok');
        form.reset();
        qsa('input[type="date"]', form).forEach((input) => { input.min = todayStr; });
      } else {
        setStatus(status, result.error || result.message || 'Could not send your enquiry. Please call the venue on 9359567494.', 'is-error');
      }
    } catch {
      setStatus(status, 'Could not send your enquiry. Please call the venue on 9359567494.', 'is-error');
    } finally {
      if (submit) submit.disabled = false;
    }
  };

  qsa('form.enquiry-form').forEach((form) => {
    const hallSelect = qs('select[name="hall"]', form);
    fetchAvailability(bookingHall(hallSelect ? hallSelect.value : ''));
    qsa('input[type="date"]', form).forEach((input) => input.addEventListener('input', () => clearFieldError(input)));
    if (form.hasAttribute('data-custom-submit')) return;
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      postForm(form);
    });
  });

  const wizard = qs('#wizard');
  if (wizard) {
    const stepsBar = qs('.steps-bar');
    const tabs = qsa('.step-tab', stepsBar || document);
    const panes = qsa('.wizard-pane', wizard);
    const success = qs('#wizard-success');
    const rec = qs('.wizard-rec', wizard);
    const status = qs('.form-status', wizard);
    let current = 1;

    const params = new URLSearchParams(location.search);
    const prefillEvent = params.get('event');
    if (prefillEvent) {
      const select = qs('[name="event"]', wizard);
      if (select) {
        const option = [...select.options].find((o) => o.value === prefillEvent || o.text === prefillEvent);
        if (option) select.value = option.value || option.text;
      }
    }

    const updateRec = () => {
      if (!rec) return;
      const guests = Number(qs('[name="guests"]', wizard)?.value || 0);
      const recText = qs('span', rec);
      if (!recText) return;
      if (!guests) {
        recText.innerHTML = 'Enter your guest count and we will suggest the best-fitting <strong>space</strong>.';
        return;
      }
      const key = guests >= 700 ? 'big' : guests <= 350 ? 'small' : 'big';
      const info = SPACE_INFO[key];
      recText.innerHTML = `Best fit for ${guests} guests: <strong>${info.name}</strong> — ${info.cap.toLowerCase()}.`;
    };

    const clearErrors = (pane) => {
      qsa('.field-error', pane).forEach((el) => el.remove());
      qsa('[aria-invalid]', pane).forEach((el) => el.removeAttribute('aria-invalid'));
    };

    const validatePane = (pane) => {
      clearErrors(pane);
      let ok = true;
      let firstInvalid = null;
      qsa('[required]', pane).forEach((field) => {
        if (field.checkValidity()) return;
        ok = false;
        field.setAttribute('aria-invalid', 'true');
        const wrap = field.closest('.field') || field.parentElement;
        const error = document.createElement('p');
        error.className = 'field-error';
        error.textContent = field.validationMessage || 'This field is required.';
        wrap?.append(error);
        if (!firstInvalid) firstInvalid = field;
      });
      firstInvalid?.focus();
      return ok;
    };

    const goTo = (step) => {
      current = step;
      panes.forEach((pane) => pane.classList.toggle('is-active', Number(pane.dataset.pane) === step));
      tabs.forEach((tab) => {
        const num = Number(tab.dataset.step);
        tab.setAttribute('aria-selected', String(num === step));
        tab.classList.toggle('is-done', num < step);
      });
    };

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        const target = Number(tab.dataset.step);
        if (target < current) goTo(target);
        else if (target === current + 1 && validatePane(panes.find((p) => Number(p.dataset.pane) === current))) goTo(target);
      });
    });

    qs('[data-wizard-next]', wizard)?.addEventListener('click', () => {
      const pane = panes.find((p) => Number(p.dataset.pane) === current);
      if (pane && validatePane(pane)) goTo(current + 1);
    });
    qs('[data-wizard-back]', wizard)?.addEventListener('click', () => goTo(Math.max(1, current - 1)));

    qs('[name="guests"]', wizard)?.addEventListener('input', updateRec);
    qs('[name="event"]', wizard)?.addEventListener('change', updateRec);
    updateRec();

    wizard.addEventListener('submit', async (event) => {
      event.preventDefault();
      const pane = panes.find((p) => Number(p.dataset.pane) === current);
      if (pane && !validatePane(pane)) return;
      const honeypot = wizard.elements.website;
      if (honeypot && honeypot.value) return;
      const submit = qs('[type="submit"]', wizard);
      if (submit) submit.disabled = true;
      setStatus(status, 'Sending your enquiry…', 'is-loading');
      try {
        const available = await checkAvailability(wizard);
        if (!available) {
          setStatus(status, 'That date is already booked. Please choose another date.', 'is-error');
          return;
        }
        const response = await fetch(wizard.action, { method: 'POST', body: prepareBookingData(wizard) });
        let result = {};
        try { result = await response.json(); } catch { result = {}; }
        if (response.ok) {
          setStatus(status, '', null);
          if (success) {
            wizard.hidden = true;
            if (stepsBar) stepsBar.hidden = true;
            success.hidden = false;
            success.scrollIntoView({ behavior: 'smooth', block: 'center' });
          } else {
            setStatus(status, result.message || 'Thank you — your enquiry has been received.', 'is-ok');
            wizard.reset();
            goTo(1);
          }
        } else {
          setStatus(status, result.error || result.message || 'Could not send your enquiry. Please call the venue on 9359567494.', 'is-error');
        }
      } catch {
        setStatus(status, 'Could not send your enquiry. Please call the venue on 9359567494.', 'is-error');
      } finally {
        if (submit) submit.disabled = false;
      }
    });
  }

  const loadWebsitePopup = async () => {
    try {
      const response = await fetch('popup.php', { cache: 'no-store' });
      if (!response.ok) return;
      const popup = await response.json();
      const closedAt = Number(localStorage.getItem('vcm-popup-closed') || 0);
      if (!popup.enabled || Date.now() - closedAt < (popup.cooldownHours || 24) * 3600000) return;
      const overlay = document.createElement('div');
      overlay.className = 'website-popup-overlay';
      const card = document.createElement('section');
      card.className = `website-popup-card website-popup-${popup.type || 'text'}`;
      card.setAttribute('role', 'dialog');
      card.setAttribute('aria-modal', 'true');
      card.setAttribute('aria-label', popup.headline || 'Venue announcement');
      const close = document.createElement('button');
      close.className = 'website-popup-close';
      close.type = 'button';
      close.textContent = '×';
      close.setAttribute('aria-label', 'Close popup');
      card.append(close);
      if (popup.type === 'image' && (popup.desktopImage || popup.mobileImage)) {
        const picture = document.createElement('picture');
        if (popup.mobileImage) {
          const source = document.createElement('source');
          source.media = '(max-width: 600px)';
          source.srcset = popup.mobileImage;
          picture.append(source);
        }
        const image = document.createElement('img');
        image.src = popup.desktopImage || popup.mobileImage;
        image.alt = popup.headline || 'Venue offer';
        picture.append(image);
        if (popup.clickUrl) {
          const link = document.createElement('a');
          link.href = popup.clickUrl;
          link.append(picture);
          card.append(link);
        } else card.append(picture);
      } else {
        const content = document.createElement('div');
        content.className = 'website-popup-copy';
        const title = document.createElement('h2');
        title.textContent = popup.headline || '';
        const text = document.createElement('p');
        text.textContent = popup.text || '';
        content.append(title, text);
        const actions = document.createElement('div');
        actions.className = 'website-popup-actions';
        [[popup.primaryText, popup.primaryUrl, 'primary'], [popup.secondaryText, popup.secondaryUrl, 'secondary']].forEach(([label, url, style]) => {
          if (!label || !url) return;
          const link = document.createElement('a');
          link.textContent = label;
          link.href = url;
          link.className = style;
          actions.append(link);
        });
        content.append(actions);
        card.append(content);
      }
      const dismiss = () => {
        localStorage.setItem('vcm-popup-closed', String(Date.now()));
        overlay.remove();
      };
      close.addEventListener('click', dismiss);
      overlay.addEventListener('click', (event) => { if (event.target === overlay) dismiss(); });
      document.addEventListener('keydown', function escape(event) {
        if (event.key === 'Escape') {
          dismiss();
          document.removeEventListener('keydown', escape);
        }
      });
      overlay.append(card);
      document.body.append(overlay);
      close.focus();
    } catch {
    }
  };
  loadWebsitePopup();

  let faqPrintState = [];
  addEventListener('beforeprint', () => {
    faqPrintState = qsa('details.faq-item').map(d => d.open);
    qsa('details.faq-item').forEach(d => { d.open = true; });
  });
  addEventListener('afterprint', () => {
    qsa('details.faq-item').forEach((d, i) => { if (!faqPrintState[i]) d.open = false; });
    faqPrintState = [];
  });

  const heroScrollBtn = qs('.hero-scroll');
  const tourTarget = qs('.cta-band');
  if (heroScrollBtn && tourTarget) {
    let touring = false;
    let tourRaf = 0;
    const stopTour = () => {
      if (!touring) return;
      touring = false;
      cancelAnimationFrame(tourRaf);
      heroScrollBtn.setAttribute('aria-expanded', 'false');
    };
    const startTour = (e) => {
      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      if (e) e.preventDefault();
      if (touring) { stopTour(); return; }
      const top = tourTarget.getBoundingClientRect().top + window.scrollY - 72;
      const from = window.scrollY;
      const dist = top - from;
      if (dist <= 0) return;
      const duration = Math.min(dist / 0.1, 80000);
      const t0 = performance.now();
      touring = true;
      heroScrollBtn.setAttribute('aria-expanded', 'true');
      const step = (now) => {
        if (!touring) return;
        const p = Math.min((now - t0) / duration, 1);
        window.scrollTo(0, from + dist * p);
        if (p >= 1) { stopTour(); return; }
        tourRaf = requestAnimationFrame(step);
      };
      tourRaf = requestAnimationFrame(step);
    };
    heroScrollBtn.addEventListener('click', startTour);
    const cancelTour = () => { if (touring) stopTour(); };
    window.addEventListener('wheel', cancelTour, { passive: true });
    window.addEventListener('touchmove', cancelTour, { passive: true });
    window.addEventListener('pointerdown', cancelTour);
    window.addEventListener('keydown', (e) => {
      if (touring && ['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', ' ', 'Home', 'End', 'Escape'].includes(e.key)) stopTour();
    });
  }

  const callPicker = qs('#call-picker');
  if (callPicker) {
    const triggers = qsa('[data-call-picker], [data-whatsapp-picker]');
    const callPickerTitle = qs('#call-picker-title', callPicker);
    let lastFocused = null;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const focusable = () => qsa('a[href], button:not([disabled])', callPicker).filter((el) => el.offsetParent !== null);
    const open = (trigger) => {
      lastFocused = trigger;
      if (callPickerTitle) callPickerTitle.textContent = trigger.hasAttribute('data-whatsapp-picker') ? 'WhatsApp the venue' : 'Call the venue';
      callPicker.hidden = false;
      requestAnimationFrame(() => callPicker.classList.add('is-open'));
      document.body.style.overflow = 'hidden';
      const f = focusable();
      if (f.length) f[0].focus();
    };
    const close = () => {
      callPicker.classList.remove('is-open');
      document.body.style.overflow = '';
      const restore = () => { callPicker.hidden = true; if (lastFocused && lastFocused.focus) lastFocused.focus(); };
      if (reduce) restore(); else setTimeout(restore, 260);
    };
    triggers.forEach((t) => t.addEventListener('click', (e) => { e.preventDefault(); open(t); }));
    qsa('[data-call-picker-close]', callPicker).forEach((b) => b.addEventListener('click', close));
    callPicker.addEventListener('click', (e) => { if (e.target === callPicker) close(); });
    document.addEventListener('keydown', (e) => {
      if (!callPicker.classList.contains('is-open')) return;
      if (e.key === 'Escape') { e.preventDefault(); close(); return; }
      if (e.key === 'Tab') {
        const f = focusable();
        if (!f.length) return;
        const first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
  }

  const heroForm = qs('#hero-form');
  if (heroForm) {
    const openBtn = qs('a[href="#hero-form"]');
    const closeBtn = qs('[data-hero-form-close]', heroForm);
    const mqMobile = window.matchMedia('(max-width: 899px)');
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let lastFocused = null;
    const open = () => {
      lastFocused = document.activeElement;
      heroForm.classList.add('is-open');
      document.body.classList.add('hero-form-open');
      document.body.style.overflow = 'hidden';
      const first = qs('input, select', heroForm);
      setTimeout(() => first?.focus(), reduce ? 0 : 320);
    };
    const close = () => {
      heroForm.classList.remove('is-open');
      document.body.classList.remove('hero-form-open');
      document.body.style.overflow = '';
      if (lastFocused && lastFocused.focus) lastFocused.focus();
    };
    if (openBtn) {
      openBtn.addEventListener('click', (e) => {
        if (mqMobile.matches) { e.preventDefault(); open(); }
      });
    }
    qsa('[data-open-hero-form]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        if (!heroForm) return;
        e.preventDefault();
        if (mqMobile.matches) open();
        else heroForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    });
    closeBtn?.addEventListener('click', close);
    heroForm.addEventListener('click', (e) => {
      if (mqMobile.matches && e.target === heroForm) close();
    });
    document.addEventListener('keydown', (e) => {
      if (!heroForm.classList.contains('is-open')) return;
      if (e.key === 'Escape') { e.preventDefault(); close(); }
    });
    if (mqMobile.matches && location.hash === '#hero-form') open();
    mqMobile.addEventListener('change', () => { if (!mqMobile.matches) close(); });
  }

  const checkForm = qs('#check-form');
  if (checkForm) {
    const params = new URLSearchParams(location.search);
    const prefillEvent = params.get('event');
    if (prefillEvent) {
      const select = qs('[name="event"]', checkForm);
      if (select) {
        const option = [...select.options].find((o) => o.value === prefillEvent || o.text === prefillEvent);
        if (option) select.value = option.value || option.text;
      }
    }
  }
})();
