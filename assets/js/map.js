/* Schematic SVG map of Bangladesh. Hosts: [data-map] holding JSON points
   [{title,name,severity,status,people,lat,lng}] */
(function () {
  var OUTLINE = [[88.08,26.45],[88.45,26.62],[88.95,26.38],[89.5,26.2],[89.82,26.05],[89.85,25.3],[90.3,25.2],[90.85,25.15],[91.3,25.15],[91.75,25.17],[92.05,25.1],[92.25,24.92],[92.15,24.5],[92.4,24.2],[92.05,23.95],[91.85,23.6],[91.85,23.2],[91.78,22.9],[92.1,22.6],[92.38,22.05],[92.3,21.4],[92.35,20.75],[92.25,20.6],[92.05,21.15],[91.85,22.2],[91.4,22.55],[90.95,22.35],[90.8,22.05],[90.4,21.95],[89.9,21.85],[89.4,21.8],[89.1,21.85],[89.0,22.25],[88.85,22.5],[89.05,22.9],[88.9,23.2],[88.7,23.25],[88.6,23.9],[88.2,24.4],[88.1,24.7],[88.4,25.0],[88.0,25.25],[88.2,25.8],[88.0,26.3]];
  var COLORS = {critical:'#E5322D', high:'#F27A1A', medium:'#EFA800', low:'#14A34A'};
  var W = 420, H = 560, S = 85, K = 0.917, LNG0 = 87.9, LAT0 = 26.8;
  function px(lng, lat) { return [20 + (lng - LNG0) * S * K, 12 + (LAT0 - lat) * S]; }
  function fmt(n) { return Number(n || 0).toLocaleString('en-US'); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  var calm = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  function render(host) {
    var pts = [];
    try { pts = JSON.parse(host.getAttribute('data-map') || '[]'); } catch (e) {}
    var path = OUTLINE.map(function (p, i) { var q = px(p[0], p[1]); return (i ? 'L' : 'M') + q[0].toFixed(1) + ' ' + q[1].toFixed(1); }).join('') + 'Z';
    var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Map of Bangladesh with disaster locations">'
      + '<path d="' + path + '" fill="rgba(79,124,255,.14)" stroke="#5C86FF" stroke-width="1.6" stroke-linejoin="round"/>';
    pts.forEach(function (p, i) {
      if (p.lat == null) return;
      var c = px(+p.lng, +p.lat), col = COLORS[p.severity] || '#999';
      var r = 5 + Math.min(7, Math.sqrt((+p.people || 0) / 4000));
      svg += '<g class="pt" data-i="' + i + '" style="cursor:pointer">'
        + '<circle cx="' + c[0] + '" cy="' + c[1] + '" r="' + (r + 5) + '" fill="' + col + '" opacity=".22">'
        + (p.severity === 'critical' && !calm ? '<animate attributeName="r" values="' + r + ';' + (r + 11) + ';' + r + '" dur="2.4s" repeatCount="indefinite"/><animate attributeName="opacity" values=".4;0;.4" dur="2.4s" repeatCount="indefinite"/>' : '') + '</circle>'
        + '<circle cx="' + c[0] + '" cy="' + c[1] + '" r="' + r + '" fill="' + col + '" stroke="#fff" stroke-width="1.5"/></g>';
    });
    if (!pts.length) svg += '<text x="' + W / 2 + '" y="' + H / 2 + '" text-anchor="middle" fill="#9FB0D6" font-size="15">No active disasters to show</text>';
    host.innerHTML = svg + '</svg>';
    var tip = document.createElement('div'); tip.className = 'map-tip'; host.appendChild(tip);
    host.querySelectorAll('.pt').forEach(function (g) {
      var p = pts[+g.getAttribute('data-i')];
      g.addEventListener('mousemove', function (ev) {
        var b = host.getBoundingClientRect();
        tip.innerHTML = '<b>' + esc(p.name) + '</b>' + esc(p.title) + '<br>' + esc((p.severity || '').toUpperCase()) + ' · ' + fmt(p.people) + ' people affected';
        tip.style.display = 'block';
        tip.style.left = Math.min(ev.clientX - b.left + 14, b.width - 240) + 'px';
        tip.style.top = Math.max(ev.clientY - b.top - 10, 4) + 'px';
      });
      g.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
    });
  }
  window.RL_renderMaps = function () { document.querySelectorAll('[data-map]').forEach(render); };
  window.RL_renderMaps();
})();

/* Auto-fitting maps: zones ([data-zones]) and mission routes ([data-route]). */
(function () {
  var COL = {critical:'#E5322D', high:'#F27A1A', medium:'#EFA800', low:'#14A34A', none:'#8A98B8'};
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function fit(pts, W, H, pad) {
    var la = pts.map(function (p) { return +p.lat; }), lo = pts.map(function (p) { return +p.lng; });
    var a0 = Math.min.apply(null, la), a1 = Math.max.apply(null, la), o0 = Math.min.apply(null, lo), o1 = Math.max.apply(null, lo);
    var k = Math.cos(((a0 + a1) / 2) * Math.PI / 180), dx = Math.max((o1 - o0) * k, .02), dy = Math.max(a1 - a0, .02);
    var s = Math.min((W - 2 * pad) / dx, (H - 2 * pad) / dy), ox = (W - dx * s) / 2, oy = (H - dy * s) / 2;
    return function (lat, lng) { return [ox + (lng - o0) * k * s, oy + (a1 - lat) * s]; };
  }
  function grid(W, H) { var g = ''; for (var x = 0; x <= W; x += 50) g += '<line x1="' + x + '" y1="0" x2="' + x + '" y2="' + H + '" stroke="rgba(255,255,255,.05)"/>'; for (var y = 0; y <= H; y += 50) g += '<line x1="0" y1="' + y + '" x2="' + W + '" y2="' + y + '" stroke="rgba(255,255,255,.05)"/>'; return g; }
  function zones(host) {
    var pts = []; try { pts = JSON.parse(host.getAttribute('data-zones')); } catch (e) {}
    var W = 640, H = 320, svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Zone map">' + grid(W, H);
    if (!pts.length) { host.innerHTML = svg + '<text x="320" y="165" text-anchor="middle" fill="#9FB0D6" font-size="14">No zones to show yet</text></svg>'; return; }
    var P = fit(pts, W, H, 60);
    pts.forEach(function (p) {
      var c = P(+p.lat, +p.lng), col = p.status === 'monitoring' || p.status === 'resolved' ? COL.none : (COL[p.severity] || COL.none);
      svg += '<g><circle cx="' + c[0] + '" cy="' + c[1] + '" r="15" fill="' + col + '" opacity=".22"/><circle cx="' + c[0] + '" cy="' + c[1] + '" r="8" fill="' + col + '" stroke="#fff" stroke-width="1.6"/>'
        + (p.locked ? '<rect x="' + (c[0] - 5) + '" y="' + (c[1] - 22) + '" width="10" height="8" rx="1.5" fill="#FFD25A"/><path d="M' + (c[0] - 3) + ' ' + (c[1] - 22) + 'v-3a3 3 0 0 1 6 0v3" fill="none" stroke="#FFD25A" stroke-width="1.6"/>' : '')
        + '<text x="' + c[0] + '" y="' + (c[1] + 26) + '" text-anchor="middle" font-size="11" fill="#DCE6FF">' + esc(p.name) + '</text><title>' + esc(p.name + (p.locked ? ' · locked by ' + p.locked : '')) + '</title></g>';
    });
    host.innerHTML = svg + '</svg>';
  }
  function route(host) {
    var r = {}; try { r = JSON.parse(host.getAttribute('data-route')); } catch (e) {}
    var W = 640, H = 300, svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Mission route map">' + grid(W, H);
    var list = [r.from, r.to].filter(function (x) { return x && x.lat != null; });
    if (list.length < 2) { host.innerHTML = svg + '<text x="320" y="155" text-anchor="middle" fill="#9FB0D6" font-size="14">Route unavailable</text></svg>'; return; }
    var P = fit(list.concat(r.now && r.now.lat != null ? [r.now] : []), W, H, 70), a = P(+r.from.lat, +r.from.lng), b = P(+r.to.lat, +r.to.lng);
    var pr = Math.max(0, Math.min(100, +r.progress || 0)) / 100, n = r.now && r.now.lat != null ? P(+r.now.lat, +r.now.lng) : [a[0] + (b[0] - a[0]) * pr, a[1] + (b[1] - a[1]) * pr];
    svg += '<line x1="' + a[0] + '" y1="' + a[1] + '" x2="' + b[0] + '" y2="' + b[1] + '" stroke="#5C86FF" stroke-width="3" stroke-dasharray="7 6" opacity=".7"/>'
      + '<line x1="' + a[0] + '" y1="' + a[1] + '" x2="' + n[0] + '" y2="' + n[1] + '" stroke="#4ADE80" stroke-width="4" stroke-linecap="round"/>'
      + '<circle cx="' + a[0] + '" cy="' + a[1] + '" r="9" fill="#7EA2FF" stroke="#fff" stroke-width="2"/><text x="' + a[0] + '" y="' + (a[1] + 26) + '" text-anchor="middle" font-size="12" fill="#DCE6FF">' + esc(r.from.label) + '</text>'
      + '<circle cx="' + b[0] + '" cy="' + b[1] + '" r="9" fill="#14A34A" stroke="#fff" stroke-width="2"/><text x="' + b[0] + '" y="' + (b[1] + 26) + '" text-anchor="middle" font-size="12" fill="#DCE6FF">' + esc(r.to.label) + '</text>'
      + '<circle cx="' + n[0] + '" cy="' + n[1] + '" r="15" fill="#F27A1A" opacity=".28"/><circle cx="' + n[0] + '" cy="' + n[1] + '" r="8" fill="#F27A1A" stroke="#fff" stroke-width="2"/>';
    host.innerHTML = svg + '</svg>';
  }
  document.querySelectorAll('[data-zones]').forEach(zones);
  document.querySelectorAll('[data-route]').forEach(route);
})();
