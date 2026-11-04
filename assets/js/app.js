/* RentEase front-end: nav toggle, live vehicle feed (fetch every 30 s + visibilitychange),
   booking form, cancellation, confirm dialogs, table search helpers. Vanilla JS. */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const meta = n => (document.querySelector('meta[name="' + n + '"]') || {}).content || '';
  const BASE = meta('base-url');
  const CSRF = meta('csrf-token');
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const inr = n => '₹' + Number(n).toLocaleString('en-IN', { maximumFractionDigits: 0 });
  const fmtDate = d => { const x = new Date(d + 'T00:00:00'); return isNaN(x) ? d : x.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }); };

  /* ---------- mobile nav ---------- */
  const toggle = $('#navToggle'), nav = $('#mainNav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* ---------- confirm dialogs for forms/buttons with data-confirm ---------- */
  document.addEventListener('submit', e => {
    const msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  /* ---------- live vehicle feed ---------- */
  const grid = $('#vehicleGrid');
  if (grid) {
    const limit = grid.dataset.limit || '60';
    const status = $('#feedStatus');
    const filters = { q: '', category: '', fuel: '', from: '', to: '', sort: 'newest' };
    let lastSig = '', known = new Set(), first = true, timer = null, inflight = null, debounce = null;

    const card = (v, isNew) => {
      const img = v.photo_url
        ? '<img src="' + esc(v.photo_url) + '" alt="' + esc(v.name) + '" loading="lazy">'
        : '<div class="ph" aria-hidden="true">' + (v.category === 'Bike' ? '🏍️' : v.category === 'Van' ? '🚐' : '🚗') + '</div>';
      return '<article class="vcard' + (isNew ? ' is-new' : '') + '">' +
        '<a class="vimg" href="' + esc(v.detail_url) + '">' + img + '<span class="chip chip-teal">' + esc(v.category) + '</span></a>' +
        '<div class="vbody"><h3><a href="' + esc(v.detail_url) + '">' + esc(v.name) + '</a></h3>' +
        '<p class="muted small">📍 ' + esc(v.pickup_location) + '</p>' +
        '<ul class="specs"><li>⛽ ' + esc(v.fuel_type) + '</li><li>⚙️ ' + esc(v.transmission) + '</li><li>👥 ' + esc(v.seats) + ' seats</li></ul>' +
        '<p class="small muted">Available ' + esc(fmtDate(v.available_from)) + ' – ' + esc(fmtDate(v.available_until)) + '</p>' +
        '<div class="vfoot"><span class="price">' + inr(v.rent_per_day) + '<small>/day</small></span>' +
        '<a class="btn btn-sm" href="' + esc(v.detail_url) + '">View &amp; Book</a></div></div></article>';
    };

    const setStatus = (txt, bad) => { if (status) { status.textContent = txt; status.classList.toggle('bad', !!bad); } };

    const load = async () => {
      if (inflight) inflight.abort();
      inflight = new AbortController();
      const p = new URLSearchParams({ limit });
      Object.keys(filters).forEach(k => { if (filters[k]) p.set(k, filters[k]); });
      try {
        const res = await fetch(BASE + '/api/vehicles_feed.php?' + p.toString(), { credentials: 'same-origin', cache: 'no-store', signal: inflight.signal, headers: { 'Accept': 'application/json' } });
        if (res.status === 401) { window.location.href = BASE + '/customer/login.php'; return; }
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Feed error');
        const sig = JSON.stringify(data.vehicles);
        if (sig !== lastSig) {                        // update cards only when something changed
          lastSig = sig;
          if (!data.vehicles.length) {
            grid.innerHTML = '<div class="empty">No vehicles match right now. New vehicles appear here automatically.</div>';
          } else {
            grid.innerHTML = data.vehicles.map(v => card(v, !first && !known.has(v.id))).join('');
          }
          known = new Set(data.vehicles.map(v => v.id));
        }
        first = false;
        setStatus('Live · ' + data.count + ' vehicle' + (data.count === 1 ? '' : 's') + ' · updated ' + new Date().toLocaleTimeString());
      } catch (err) {
        if (err.name !== 'AbortError') setStatus('Offline – retrying…', true);
      }
    };
    const start = () => { stop(); timer = setInterval(load, 30000); };
    const stop = () => { if (timer) { clearInterval(timer); timer = null; } };
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); else { load(); start(); } });

    // explore page controls
    const bind = (id, key, ev) => { const el = document.getElementById(id); if (!el) return; filters[key] = el.value; el.addEventListener(ev || 'change', () => { filters[key] = el.value; if (ev === 'input') { clearTimeout(debounce); debounce = setTimeout(load, 300); } else load(); }); };
    bind('fSearch', 'q', 'input'); bind('fFuel', 'fuel'); bind('fSort', 'sort'); bind('fFrom', 'from'); bind('fTo', 'to');
    $$('.chip-filter').forEach(btn => btn.addEventListener('click', () => {
      $$('.chip-filter').forEach(b => b.classList.remove('active'));
      btn.classList.add('active'); filters.category = btn.dataset.cat || ''; load();
    }));
    const reset = $('#fReset');
    if (reset) reset.addEventListener('click', () => {
      ['fSearch', 'fFuel', 'fFrom', 'fTo'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
      const s = $('#fSort'); if (s) s.value = 'newest';
      Object.assign(filters, { q: '', category: '', fuel: '', from: '', to: '', sort: 'newest' });
      $$('.chip-filter').forEach(b => b.classList.toggle('active', !b.dataset.cat));
      load();
    });
    load(); start();
  }

  /* ---------- booking form (vehicle details) ---------- */
  const form = $('#bookForm');
  if (form) {
    const rent = parseFloat(form.dataset.rent), pu = $('#pickup_date'), rt = $('#return_date'), sum = $('#bookSummary'), msg = $('#bookMsg'), btn = $('#bookBtn');
    const calc = () => {
      if (!pu.value || !rt.value) { sum.textContent = 'Select your dates to see the total.'; return; }
      const d = Math.round((new Date(rt.value) - new Date(pu.value)) / 86400000);
      sum.innerHTML = d > 0 ? '<b>' + d + ' day' + (d > 1 ? 's' : '') + '</b> × ' + inr(rent) + ' = <b class="price">' + inr(d * rent) + '</b> <span class="muted small">(pickup & return at 10:00 AM)</span>' : '<span class="text-danger">Return date must be after pickup.</span>';
    };
    pu.addEventListener('change', () => { if (rt.value && rt.value <= pu.value) rt.value = ''; const n = new Date(pu.value); n.setDate(n.getDate() + 1); rt.min = n.toISOString().slice(0, 10); calc(); });
    rt.addEventListener('change', calc);
    const show = (text, type, extra) => { msg.className = 'alert alert-' + type; msg.innerHTML = esc(text); msg.hidden = false; if (extra) msg.appendChild(extra); };
    const submit = async waitlist => {
      btn.disabled = true; msg.hidden = true;
      const fd = new FormData(form); fd.set('csrf_token', CSRF); if (waitlist) fd.set('waitlist', '1');
      try {
        const res = await fetch(BASE + '/api/book.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': CSRF } });
        const d = await res.json();
        if (d.ok) { show(d.message, d.status === 'confirmed' ? 'success' : 'info'); setTimeout(() => { window.location.href = d.redirect; }, 1400); return; }
        if (d.can_waitlist) {
          const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-teal'; b.style.marginLeft = '.6rem'; b.textContent = 'Join waitlist';
          b.addEventListener('click', () => submit(true)); show(d.error, 'warning', b);
        } else show(d.error || 'Booking failed.', 'danger');
      } catch (e) { show('Network error. Please try again.', 'danger'); }
      btn.disabled = false;
    };
    form.addEventListener('submit', e => { e.preventDefault(); if (!pu.value || !rt.value) { show('Please choose pickup and return dates.', 'danger'); return; } submit(false); });
    calc();
  }

  /* ---------- cancel booking buttons ---------- */
  $$('.js-cancel').forEach(b => b.addEventListener('click', async () => {
    if (!window.confirm('Cancel booking ' + (b.dataset.code || '') + '? This cannot be undone.')) return;
    b.disabled = true;
    const fd = new FormData(); fd.set('booking_id', b.dataset.id); fd.set('csrf_token', CSRF);
    try {
      const res = await fetch(BASE + '/api/cancel.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': CSRF } });
      const d = await res.json();
      alert(d.ok ? d.message : (d.error || 'Could not cancel.'));
      if (d.ok) window.location.reload(); else b.disabled = false;
    } catch (e) { alert('Network error.'); b.disabled = false; }
  }));

  /* ---------- print buttons ---------- */
  $$('[data-print]').forEach(b => b.addEventListener('click', () => window.print()));
})();
