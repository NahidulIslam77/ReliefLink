(function () {
  var B = document.body, base = B.getAttribute('data-base') || '', uid = B.getAttribute('data-uid') || '0';
  var offlineOn = B.getAttribute('data-offline') !== '0', autoSync = B.getAttribute('data-autosync') !== '0', saver = B.getAttribute('data-datasaver') === '1';
  function $all(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  // toasts + dismiss
  function toast(msg, type) {
    var w = document.querySelector('.flash-wrap'); if (!w) { w = document.createElement('div'); w.className = 'flash-wrap'; B.appendChild(w); }
    var f = document.createElement('div'); f.className = 'flash ' + (type || 'info'); f.innerHTML = '<span>' + esc(msg) + '</span><button type="button" aria-label="Dismiss">×</button>';
    f.querySelector('button').onclick = function () { f.remove(); }; w.appendChild(f); setTimeout(function () { f.remove(); }, 7000);
  }
  $all('[data-dismiss]').forEach(function (b) { b.addEventListener('click', function () { b.parentNode.remove(); }); });
  setTimeout(function () { $all('.flash-wrap .flash').forEach(function (f) { f.style.display = 'none'; }); }, 7000);

  // password toggle, donation presets, login button label, confirm dialogs
  $all('.pw button').forEach(function (b) { b.addEventListener('click', function () { var i = b.parentNode.querySelector('input'); i.type = i.type === 'password' ? 'text' : 'password'; }); });
  $all('.amounts button').forEach(function (b) { b.addEventListener('click', function () { var i = document.getElementById('amount'); if (i) i.value = b.getAttribute('data-v'); $all('.amounts button').forEach(function (x) { x.classList.toggle('on', x === b); }); }); });
  var lb = document.getElementById('loginBtn');
  if (lb) { var sync = function () { var r = document.querySelector('input[name=role]:checked'); if (r) lb.textContent = 'Sign in as ' + r.getAttribute('data-label'); }; $all('input[name=role]').forEach(function (r) { r.addEventListener('change', sync); }); sync(); }
  $all('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (ev) { if (!confirm(f.getAttribute('data-confirm'))) ev.preventDefault(); }); });
  $all('[data-digits]').forEach(function (i) { i.addEventListener('input', function () { i.value = i.value.replace(/\D/g, '').slice(0, 6); }); });
  $all('[data-photo-input]').forEach(function (i) {
    var out = document.getElementById(i.getAttribute('data-photo-input'));
    i.addEventListener('change', function () { var n = i.files.length; if (out) { out.textContent = n + ' photo' + (n === 1 ? '' : 's') + ' selected' + (n < 3 ? ' (minimum 3)' : ''); out.className = 'geo-state' + (n >= 3 ? ' ok' : ''); } });
  });
  $all('[data-unit-for]').forEach(function (r) { r.addEventListener('change', function () { var u = document.getElementById('unit'); if (u && r.checked) u.value = r.getAttribute('data-unit-for'); }); });

  // geolocation: <button data-geo="latId,lngId,stateId">
  $all('[data-geo]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var ids = btn.getAttribute('data-geo').split(','), la = document.getElementById(ids[0]), lo = document.getElementById(ids[1]), st = document.getElementById(ids[2]);
      if (!navigator.geolocation) { if (st) st.textContent = 'Location is not available on this device. Enter coordinates manually.'; return; }
      if (st) { st.textContent = 'Detecting…'; st.className = 'geo-state'; }
      navigator.geolocation.getCurrentPosition(function (p) {
        la.value = p.coords.latitude.toFixed(5); lo.value = p.coords.longitude.toFixed(5);
        if (st) { st.textContent = 'Location captured (±' + Math.round(p.coords.accuracy) + ' m)'; st.className = 'geo-state ok'; }
      }, function () { if (st) { st.textContent = 'Could not get your location. Allow location access or enter coordinates manually.'; st.className = 'geo-state'; } }, { enableHighAccuracy: true, timeout: 15000 });
    });
  });

  // live counters on public pages
  var live = $all('[data-live]');
  function poll() {
    if (!navigator.onLine) return;
    fetch(base + '/api/stats.php', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
      live.forEach(function (el) { var k = el.getAttribute('data-live'); if (!(k in d)) return; var n = Math.round(+d[k]); el.textContent = el.hasAttribute('data-money') ? '৳' + n.toLocaleString('en-US') : n.toLocaleString('en-US'); });
    }).catch(function () {});
  }
  if (live.length && !saver) setInterval(poll, 30000);

  /* ---------- offline-first queue ---------- */
  var QK = 'rl_queue_' + uid, LK = 'rl_log_' + uid;
  function jget(k) { try { return JSON.parse(localStorage.getItem(k) || '[]'); } catch (e) { return []; } }
  function jset(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { toast('Device storage is full. Sync or clear the queue.', 'error'); } }
  function newUid() { return (window.crypto && crypto.randomUUID ? crypto.randomUUID().replace(/-/g, '') : Math.random().toString(36).slice(2) + Date.now().toString(36)).slice(0, 32); }
  function badge() { var n = jget(QK).length; $all('[data-sync-badge]').forEach(function (b) { b.textContent = n; b.hidden = !n; }); updateNet(); }
  function updateNet() {
    var on = navigator.onLine, n = jget(QK).length;
    $all('[data-offline-banner]').forEach(function (b) { b.hidden = on && !n; var c = b.querySelector('[data-offline-count]'); if (c) c.textContent = n + ' item' + (n === 1 ? '' : 's') + ' saved on this device.'; });
    $all('[data-net]').forEach(function (p) { p.classList.toggle('off', !on); p.querySelector('span').textContent = on ? (n ? 'Online · ' + n + ' to sync' : 'Live') : 'Offline' + (n ? ' · ' + n + ' queued' : ''); });
  }
  $all('form[data-offline-type]').forEach(function (f) {
    var h = f.querySelector('input[name=client_uid]'); if (h && !h.value) h.value = newUid();
    f.addEventListener('submit', function (ev) {
      if (!offlineOn || navigator.onLine) return;
      ev.preventDefault();
      var o = {}; new FormData(f).forEach(function (v, k) { if (typeof v === 'string' && k !== '_csrf') o[k] = v; });
      if (f.querySelector('[data-needs-photos]')) { toast('Photo evidence needs a connection. Reconnect to submit.', 'error'); return; }
      var q = jget(QK); q.push({ uid: o.client_uid, type: f.getAttribute('data-offline-type'), label: f.getAttribute('data-offline-label') || f.getAttribute('data-offline-type'), data: o, at: Date.now() }); jset(QK, q);
      f.reset(); if (h) h.value = newUid(); badge();
      toast('Saved on this device. It will sync automatically when you are back online.', 'info');
    });
  });
  var syncing = false;
  function flush(manual) {
    if (syncing || !navigator.onLine) return Promise.resolve();
    var q = jget(QK); if (!q.length) { if (manual) toast('Nothing to sync.', 'info'); return Promise.resolve(); }
    syncing = true; var log = jget(LK), ok = 0, failed = 0;
    function next() {
      if (!q.length) return Promise.resolve();
      var it = q[0];
      return fetch(base + '/api/sync.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': B.getAttribute('data-csrf') }, body: JSON.stringify({ uid: it.uid, type: it.type, data: it.data }) })
        .then(function (r) { return r.json(); }).then(function (res) {
          q.shift(); log.unshift({ label: it.label, at: Date.now(), ok: !!res.ok, msg: res.ok ? (res.message || 'Synced') : (res.error || 'Rejected') }); res.ok ? ok++ : failed++;
          jset(QK, q); jset(LK, log.slice(0, 20)); return next();
        });
    }
    return next().catch(function () { /* network dropped: keep the rest queued */ }).then(function () {
      syncing = false; badge(); renderSync();
      if (ok || failed) toast(ok + ' item' + (ok === 1 ? '' : 's') + ' synced' + (failed ? ', ' + failed + ' rejected (see Offline & Sync)' : '') + '.', failed ? 'error' : 'success');
    });
  }
  window.addEventListener('online', function () { updateNet(); if (autoSync) flush(false); });
  window.addEventListener('offline', updateNet);
  function ago(t) { var s = Math.round((Date.now() - t) / 1000); return s < 60 ? 'just now' : s < 3600 ? Math.floor(s / 60) + ' min ago' : s < 86400 ? Math.floor(s / 3600) + ' h ago' : new Date(t).toLocaleDateString(); }
  function renderSync() {
    var ql = document.querySelector('[data-queue-list]'); if (!ql) return;
    var q = jget(QK), lg = jget(LK);
    ql.innerHTML = q.length ? q.map(function (i) { return '<div class="q-row"><span><i class="dot"></i>' + esc(i.label) + '<small>Saved ' + ago(i.at) + ' on this device</small></span><span class="badge b-amber">Pending</span></div>'; }).join('') : '<div class="empty">Nothing waiting. Everything is synced.</div>';
    var sl = document.querySelector('[data-sync-log]'); if (sl) sl.innerHTML = lg.length ? lg.map(function (i) { return '<div class="q-row"><span><i class="dot ' + (i.ok ? 'ok' : 'bad') + '"></i>' + esc(i.label) + '<small>' + esc(i.msg) + ' · ' + ago(i.at) + '</small></span><span class="badge ' + (i.ok ? 'b-green' : 'b-red') + '">' + (i.ok ? 'Synced' : 'Rejected') + '</span></div>'; }).join('') : '<div class="empty">No sync history yet.</div>';
    var cp = document.querySelector('[data-count-pending]'); if (cp) cp.textContent = q.length;
    var cs = document.querySelector('[data-count-synced]'); if (cs) cs.textContent = lg.filter(function (i) { return i.ok; }).length;
    var set = function (k, html) { var e = document.querySelector('[data-conn="' + k + '"]'); if (e) e.innerHTML = html; };
    set('net', navigator.onLine ? '<span class="ok">Connected</span>' : '<span class="bad">Offline</span>');
    var t0 = Date.now();
    if (navigator.onLine) fetch(base + '/api/ping.php', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function () { set('server', '<span class="ok">Online</span><small>latency ' + (Date.now() - t0) + ' ms</small>'); }).catch(function () { set('server', '<span class="bad">Unreachable</span>'); });
    else set('server', '<span class="bad">Unreachable</span>');
    if (navigator.permissions && navigator.permissions.query) navigator.permissions.query({ name: 'geolocation' }).then(function (p) { set('gps', p.state === 'granted' ? '<span class="ok">Allowed</span>' : p.state === 'denied' ? '<span class="bad">Blocked</span>' : '<span>Will ask when needed</span>'); }).catch(function () { set('gps', 'Unknown'); }); else set('gps', 'Unknown');
    var bytes = (localStorage.getItem(QK) || '').length + (localStorage.getItem(LK) || '').length; set('store', '<span class="ok">' + (bytes / 1024).toFixed(1) + ' KB</span><small>used by the offline queue</small>');
  }
  $all('[data-sync-now]').forEach(function (b) { b.addEventListener('click', function () { flush(true); }); });
  $all('[data-clear-log]').forEach(function (b) { b.addEventListener('click', function () { jset(LK, []); renderSync(); }); });
  badge(); renderSync();
  if (autoSync && navigator.onLine && jget(QK).length) flush(false);
})();
