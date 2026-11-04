(() => {
  'use strict';
  const root = document.getElementById('inbox');
  if (!root) return;
  const csrf = root.dataset.csrf;
  const $ = (id) => document.getElementById(id);
  const shell = $('inboxShell'), list = $('convList'), empty = $('convEmpty'), search = $('convSearch');
  const view = $('threadView'), placeholder = $('threadPlaceholder'), box = $('threadMessages');
  const form = $('composer'), text = $('composerText'), send = $('sendButton');
  const templateButton = $('templateButton'), note = $('windowNote'), errorBox = $('composerError');
  const details = $('threadDetails'), records = $('recordList');
  const STATUS_TICKS = { sent: '✓', delivered: '✓✓', read: '✓✓', failed: '!' };
  const STATUS_LABELS = { pending: 'New enquiry', in_discussion: 'In discussion', quote_sent: 'Quote sent', follow_up: 'Reminder set', converted: 'Booking created', cancelled: 'Cancelled', confirmed: 'Confirmed' };
  let activeName = '', active = '', lastId = 0, lastDay = '', searchTimer = 0, listTimer = 0, threadTimer = 0, busy = false;

  const el = (tag, cls, txt) => { const n = document.createElement(tag); if (cls) n.className = cls; if (txt !== undefined) n.textContent = txt; return n; };
  const parse = (s) => new Date(String(s).replace(' ', 'T') + '+05:30');
  const sameDay = (a, b) => a.toDateString() === b.toDateString();
  const timeLabel = (s) => parse(s).toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' });
  const listTime = (s) => { const d = parse(s), now = new Date(); if (sameDay(d, now)) return timeLabel(s); const y = new Date(now); y.setDate(now.getDate() - 1); return sameDay(d, y) ? 'Yesterday' : d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }); };
  const dayLabel = (s) => { const d = parse(s), now = new Date(); if (sameDay(d, now)) return 'Today'; const y = new Date(now); y.setDate(now.getDate() - 1); return sameDay(d, y) ? 'Yesterday' : d.toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }); };
  const pretty = (p) => p.length === 12 && p.startsWith('91') ? `+91 ${p.slice(2, 7)} ${p.slice(7)}` : '+' + p;
  const initials = (name, phone) => (name || '').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || phone.slice(-2);

  async function api(params, body) {
    const opts = body ? { method: 'POST', body: new URLSearchParams({ ...body, csrf }), headers: { 'Content-Type': 'application/x-www-form-urlencoded' } } : {};
    const res = await fetch('inbox-api.php?' + new URLSearchParams(params), { credentials: 'same-origin', ...opts });
    if (res.status === 401) { window.location.href = 'index.php'; throw new Error('Signed out'); }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Something went wrong. Try again.');
    return data;
  }

  function setBadge(count) {
    document.querySelectorAll('[data-inbox-badge]').forEach((b) => { b.textContent = count > 99 ? '99+' : String(count); b.hidden = count === 0; });
  }

  function renderList(items) {
    list.replaceChildren();
    empty.hidden = items.length > 0;
    for (const c of items) {
      const li = el('li');
      const btn = el('button', 'conv' + (c.phone === active ? ' active' : '') + (c.unread ? ' unread' : ''));
      btn.type = 'button';
      btn.dataset.phone = c.phone;
      const av = el('span', 'avatar', initials(c.name, c.phone));
      av.setAttribute('aria-hidden', 'true');
      const body = el('span', 'conv-body');
      const top = el('span', 'conv-top');
      top.append(el('strong', 'conv-name', c.name || pretty(c.phone)), el('time', 'conv-time', listTime(c.time)));
      const bottom = el('span', 'conv-bottom');
      const preview = el('span', 'conv-preview', (c.direction === 'out' ? 'You: ' : '') + c.preview);
      bottom.append(preview);
      if (c.unread) bottom.append(el('span', 'unread-count', String(c.unread)));
      body.append(top, bottom);
      if (c.linked) body.append(el('span', 'conv-tag', c.linked));
      btn.append(av, body);
      btn.addEventListener('click', () => openThread(c.phone));
      li.append(btn);
      list.append(li);
    }
  }

  async function loadList() {
    try {
      const data = await api({ action: 'list', q: search.value.trim() });
      renderList(data.conversations);
      setBadge(data.unread);
    } catch (e) { /* the next poll retries */ }
  }

  function messageNode(m) {
    const wrap = el('div', 'msg ' + (m.direction === 'out' ? 'msg-out' : 'msg-in') + (m.status === 'failed' ? ' msg-failed' : ''));
    const bubble = el('div', 'bubble');
    if (m.has_media && m.msg_type === 'image') {
      const img = el('img', 'msg-image');
      img.src = 'inbox-media.php?id=' + m.id;
      img.alt = m.body || 'Photo from guest';
      img.loading = 'lazy';
      const link = el('a');
      link.href = img.src; link.target = '_blank'; link.rel = 'noopener';
      link.append(img);
      bubble.append(link);
    } else if (m.has_media && (m.msg_type === 'audio' || m.msg_type === 'video')) {
      const media = el(m.msg_type);
      media.controls = true; media.preload = 'none'; media.src = 'inbox-media.php?id=' + m.id;
      bubble.append(media);
    } else if (m.has_media) {
      const link = el('a', 'msg-file', '📄 ' + (m.body || 'Download file'));
      link.href = 'inbox-media.php?id=' + m.id;
      bubble.append(link);
    } else if (m.msg_type === 'location') {
      const [label, coords] = m.body.includes('\n') ? m.body.split('\n') : ['', m.body];
      const link = el('a', 'msg-file', '📍 ' + (label || 'Shared location'));
      link.href = 'https://www.google.com/maps?q=' + encodeURIComponent(coords); link.target = '_blank'; link.rel = 'noopener';
      bubble.append(link);
    } else if (m.msg_type === 'reaction') {
      bubble.append(el('span', 'msg-reaction', 'Reacted ' + m.body));
    } else if (!m.body) {
      bubble.append(el('em', 'msg-unsupported', 'This message type can only be viewed on the phone.'));
    }
    if (m.body && !(m.has_media && m.msg_type === 'document') && m.msg_type !== 'location' && m.msg_type !== 'reaction') {
      bubble.append(el('p', 'msg-text', m.body));
    }
    const meta = el('span', 'msg-meta', timeLabel(m.sent_at));
    if (m.direction === 'out') {
      const tick = el('span', 'tick tick-' + (m.status || 'sent'), STATUS_TICKS[m.status] || '');
      tick.title = m.status === 'failed' ? (m.error || 'Not delivered') : (m.status || '');
      meta.append(tick);
    }
    bubble.append(meta);
    if (m.status === 'failed' && m.error) wrap.append(el('small', 'msg-error', m.error));
    wrap.prepend(bubble);
    return wrap;
  }

  function appendMessages(messages) {
    const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 120;
    for (const m of messages) {
      const day = dayLabel(m.sent_at);
      if (day !== lastDay) { box.append(el('div', 'day-divider', day)); lastDay = day; }
      const node = messageNode(m);
      node.dataset.id = m.id;
      box.append(node);
      lastId = Math.max(lastId, m.id);
    }
    if (messages.length && (nearBottom || messages.some((m) => m.direction === 'out'))) box.scrollTop = box.scrollHeight;
  }

  function renderRecords(data) {
    records.replaceChildren();
    if (!data.records.length) records.append(el('p', 'muted', 'No enquiry or booking matches this number yet.'));
    for (const r of data.records) {
      const a = el('a', 'record-item');
      a.href = (r.type === 'booking' ? 'bookings.php' : 'enquiries.php?q=') + (r.type === 'booking' ? '' : encodeURIComponent(r.ref));
      a.append(el('strong', '', r.ref + ' · ' + r.event), el('span', '', `${new Date(r.date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })} · ${r.hall}`), el('span', 'record-status', STATUS_LABELS[r.status] || r.status));
      records.append(a);
    }
    const link = $('newEnquiryLink');
    link.href = 'enquiries.php?' + new URLSearchParams({ new: '1', name: data.name || '', phone: data.phone.length === 12 && data.phone.startsWith('91') ? data.phone.slice(2) : data.phone });
  }

  function renderWindow(data) {
    const open = data.window_open;
    form.hidden = !open;
    note.hidden = false;
    if (open) {
      const closes = parse(data.window_closes);
      note.textContent = 'You can reply freely until ' + closes.toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }) + '.';
      note.className = 'composer-note';
    } else {
      note.textContent = 'More than 24 hours since the guest last wrote. WhatsApp only allows an approved template now (📄 Send template below); their reply re-opens the chat.';
      note.className = 'composer-note warn';
    }
  }

  async function loadThread(initial) {
    if (!active || busy) return;
    const phone = active;
    try {
      const data = await api({ action: 'thread', phone, after: initial ? 0 : lastId });
      if (phone !== active) return;
      if (initial) {
        activeName = data.name || '';
        $('threadName').textContent = data.name || pretty(phone);
        $('threadPhone').textContent = pretty(phone);
        $('threadAvatar').textContent = initials(data.name, phone);
        $('threadCall').href = 'tel:+' + phone;
        renderRecords(data);
      }
      appendMessages(data.messages);
      renderWindow(data);
      setBadge(data.unread);
      list.querySelector(`.conv[data-phone="${phone}"]`)?.classList.remove('unread');
      list.querySelector(`.conv[data-phone="${phone}"] .unread-count`)?.remove();
    } catch (e) { if (initial) showError(e.message); }
  }

  function openThread(phone) {
    active = phone; activeName = ''; lastId = 0; lastDay = '';
    Object.values(panels).forEach((p) => { p.hidden = true; });
    box.replaceChildren();
    errorBox.hidden = true;
    placeholder.hidden = true; view.hidden = false; details.hidden = false;
    shell.classList.add('has-thread');
    list.querySelectorAll('.conv').forEach((c) => c.classList.toggle('active', c.dataset.phone === phone));
    history.replaceState(null, '', '?chat=' + phone);
    loadThread(true).then(() => { box.scrollTop = box.scrollHeight; });
  }

  function showError(message) { errorBox.textContent = message; errorBox.hidden = false; }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const body = text.value.trim();
    if (!body || !active) return;
    send.disabled = true; errorBox.hidden = true; busy = true;
    try {
      await api({}, { action: 'send', phone: active, body });
      text.value = ''; autosize();
    } catch (e) { showError(e.message); }
    busy = false; send.disabled = false;
    loadThread(false); loadList();
  });
  text.addEventListener('keydown', (event) => { if (event.key === 'Enter' && !event.shiftKey && window.matchMedia('(min-width: 900px)').matches) { event.preventDefault(); form.requestSubmit(); } });
  const autosize = () => { text.style.height = 'auto'; text.style.height = Math.min(text.scrollHeight, 160) + 'px'; };
  text.addEventListener('input', autosize);

  /* ---------- Emojis, saved replies and templates ---------- */
  const EMOJIS = '🙏 😊 😀 😄 😍 🥰 😇 🤝 👍 👌 👏 🙌 💐 🎉 🎊 🥳 💍 💒 👰 🤵 🎂 🍰 🎈 🎁 🪔 🌸 🌺 🌹 ✨ ⭐ ❤️ 💖 🏛️ 🍽️ 🥘 ☕ 🎶 📸 💡 🚗 🅿️ ❄️ 🛏️ 📅 🗓️ ⏰ 🕘 📍 🗺️ 📞 📱 ✉️ 📄 💰 💳 ✅ ☑️ ❌ ⚠️ ℹ️ 👉 👆 👇 🔔 🆗 🙂 😃 😁 😉 🤗 🤔 😔 🙇 👋'.split(' ');
  const panels = { emoji: $('emojiPanel'), replies: $('repliesPanel'), template: $('templatePanel') };
  let replies = [], templates = [];
  const firstName = () => (activeName || '').trim().split(/\s+/)[0] || 'ji';

  function togglePanel(name) {
    for (const [key, panel] of Object.entries(panels)) panel.hidden = key === name ? !panel.hidden : true;
    if (name !== 'emoji' && !panels[name].hidden) loadReplies();
  }
  function insertText(value) {
    const start = text.selectionStart ?? text.value.length, end = text.selectionEnd ?? text.value.length;
    text.value = text.value.slice(0, start) + value + text.value.slice(end);
    text.focus(); text.selectionStart = text.selectionEnd = start + value.length; autosize();
  }

  const grid = $('emojiGrid');
  for (const emoji of EMOJIS) {
    const b = el('button', 'emoji', emoji); b.type = 'button'; b.setAttribute('aria-label', 'Insert ' + emoji);
    b.addEventListener('click', () => insertText(emoji));
    grid.append(b);
  }

  async function loadReplies() {
    try {
      const data = await api({ action: 'replies' });
      replies = data.replies; templates = data.templates;
      renderReplies(); renderTemplateOptions();
    } catch (e) { showError(e.message); }
  }
  function renderReplies() {
    const ul = $('replyList'); ul.replaceChildren();
    if (!replies.length) ul.append(el('li', 'muted', 'No saved replies yet. Type a message and save it below.'));
    for (const r of replies) {
      const li = el('li', 'reply-item');
      const use = el('button', 'reply-use'); use.type = 'button';
      use.append(el('strong', '', r.title), el('span', '', r.body));
      use.addEventListener('click', () => { insertText(r.body.replaceAll('{name}', firstName())); panels.replies.hidden = true; });
      const del = el('button', 'reply-delete', '×'); del.type = 'button'; del.title = 'Delete ' + r.title; del.setAttribute('aria-label', 'Delete saved reply ' + r.title);
      del.addEventListener('click', async () => {
        if (!window.confirm('Delete the saved reply "' + r.title + '"?')) return;
        try { replies = (await api({}, { action: 'reply_delete', id: r.id })).replies; renderReplies(); } catch (e) { showError(e.message); }
      });
      li.append(use, del); ul.append(li);
    }
  }
  $('replySave').addEventListener('submit', async (event) => {
    event.preventDefault();
    const title = $('replyTitle').value.trim(), body = text.value.trim();
    if (!body) { showError('Type the message in the box first, then save it.'); return; }
    if (!title) { $('replyTitle').focus(); return; }
    try { replies = (await api({}, { action: 'reply_save', title, body })).replies; $('replyTitle').value = ''; renderReplies(); errorBox.hidden = true; } catch (e) { showError(e.message); }
  });

  const select = $('templateSelect'), fields = $('templateFields'), preview = $('templatePreview');
  function renderTemplateOptions() {
    const current = select.value;
    select.replaceChildren();
    if (!templates.length) { const o = el('option', '', 'No approved templates yet'); o.value = ''; select.append(o); }
    for (const t of templates) { const o = el('option', '', `${t.name} (${t.language})`); o.value = `${t.name}|${t.language}`; select.append(o); }
    if ([...select.options].some((o) => o.value === current)) select.value = current;
    renderTemplateFields();
  }
  function selectedTemplate() { return templates.find((t) => `${t.name}|${t.language}` === select.value); }
  function renderTemplateFields() {
    const t = selectedTemplate();
    fields.replaceChildren(); preview.textContent = t ? t.body : '';
    for (let i = 1; i <= (t?.variables || 0); i += 1) {
      const label = el('label', '', `{{${i}}}`);
      const input = el('input'); input.name = 'param'; input.maxLength = 200; input.required = true;
      if (i === 1) input.value = activeName || '';
      input.addEventListener('input', updatePreview);
      label.append(input); fields.append(label);
    }
    updatePreview();
  }
  function updatePreview() {
    const t = selectedTemplate(); if (!t) return;
    const values = [...fields.querySelectorAll('input')].map((i) => i.value.trim());
    preview.textContent = t.body.replace(/\{\{(\d+)\}\}/g, (m, n) => values[n - 1] || m);
  }
  select.addEventListener('change', renderTemplateFields);
  $('templateSend').addEventListener('click', async () => {
    const t = selectedTemplate(); if (!t || !active) return;
    const params = [...fields.querySelectorAll('input')].map((i) => i.value.trim());
    if (params.some((v) => !v)) { showError('Fill in every template field.'); return; }
    const button = $('templateSend'); button.disabled = true; errorBox.hidden = true;
    try {
      const body = new URLSearchParams({ action: 'template_send', phone: active, name: t.name, language: t.language, csrf });
      params.forEach((v) => body.append('params[]', v));
      const res = await fetch('inbox-api.php', { method: 'POST', credentials: 'same-origin', body });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.error || 'Template not sent.');
      panels.template.hidden = true;
    } catch (e) { showError(e.message); }
    button.disabled = false;
    loadThread(false); loadList();
  });

  $('emojiButton').addEventListener('click', () => togglePanel('emoji'));
  $('repliesButton').addEventListener('click', () => togglePanel('replies'));
  templateButton.addEventListener('click', () => { togglePanel('template'); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') Object.values(panels).forEach((p) => { p.hidden = true; }); });

  search.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadList, 250); });
  $('threadBack').addEventListener('click', () => { shell.classList.remove('has-thread'); active = ''; history.replaceState(null, '', 'inbox.php'); });

  function schedule() {
    clearInterval(listTimer); clearInterval(threadTimer);
    if (document.hidden) return;
    listTimer = setInterval(loadList, 10000);
    threadTimer = setInterval(() => loadThread(false), 5000);
  }
  document.addEventListener('visibilitychange', () => { schedule(); if (!document.hidden) { loadList(); loadThread(false); } });

  const menu = $('sidebar'), toggle = $('menuToggle');
  toggle?.addEventListener('click', () => { const open = menu.classList.toggle('open'); toggle.setAttribute('aria-expanded', String(open)); });

  const start = new URLSearchParams(location.search).get('chat');
  loadList().then(() => { if (start && /^\d{8,20}$/.test(start)) openThread(start); });
  schedule();
})();
