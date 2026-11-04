/* Tiny SVG charts. Hosts: .chart[data-chart='{"type":"bar|line|donut","labels":[],"series":[{"name":"","values":[],"color":""}]}'] */
(function () {
  var PAL = ['#2F6BFF', '#14A34A', '#F27A1A', '#7A4DFF', '#E5322D', '#EFA800', '#0EA5C6'];
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function fmt(n) { n = +n; return n >= 1e6 ? (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'M' : n >= 1e4 ? Math.round(n / 1e3) + 'k' : n >= 1e3 ? (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'k' : String(Math.round(n * 10) / 10); }
  function nice(max) { if (max <= 0) return 1; var p = Math.pow(10, Math.floor(Math.log10(max))), f = max / p; return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * p; }
  function legend(series) {
    if (series.length < 2) return '';
    return '<div class="chart-legend">' + series.map(function (s, i) { return '<span><i style="background:' + (s.color || PAL[i % PAL.length]) + '"></i>' + esc(s.name) + '</span>'; }).join('') + '</div>';
  }
  function axes(W, H, m, max, labels, n) {
    var out = '', ticks = 4;
    for (var t = 0; t <= ticks; t++) {
      var y = m.t + (H - m.t - m.b) * (1 - t / ticks);
      out += '<line x1="' + m.l + '" x2="' + (W - m.r) + '" y1="' + y + '" y2="' + y + '" stroke="#E6EBF4"/><text x="' + (m.l - 8) + '" y="' + (y + 4) + '" text-anchor="end" font-size="11" fill="#7A87A3">' + fmt(max * t / ticks) + '</text>';
    }
    return out;
  }
  function bar(d) {
    var W = d.W || 560, H = 240, m = {l: 44, r: 10, t: 10, b: 30}, n = d.labels.length, k = d.series.length;
    var max = nice(Math.max.apply(null, [0].concat.apply([], d.series.map(function (s) { return s.values; }))));
    var gw = (W - m.l - m.r) / Math.max(n, 1), bw = Math.min(34, (gw * .7) / k), svg = axes(W, H, m, max, d.labels, n);
    d.labels.forEach(function (lb, i) {
      var x0 = m.l + gw * i + (gw - bw * k) / 2;
      d.series.forEach(function (s, j) {
        var v = +s.values[i] || 0, h = (H - m.t - m.b) * v / max, y = H - m.b - h;
        svg += '<rect x="' + (x0 + bw * j) + '" y="' + y + '" width="' + (bw - 3) + '" height="' + Math.max(h, 0) + '" rx="4" fill="' + (s.color || PAL[j % PAL.length]) + '"><title>' + esc(s.name + ' · ' + lb + ': ' + v) + '</title></rect>';
      });
      svg += '<text x="' + (m.l + gw * i + gw / 2) + '" y="' + (H - 10) + '" text-anchor="middle" font-size="11" fill="#7A87A3">' + esc(lb) + '</text>';
    });
    return '<svg viewBox="0 0 ' + W + ' ' + H + '">' + svg + '</svg>' + legend(d.series);
  }
  function line(d) {
    var W = d.W || 560, H = 240, m = {l: 44, r: 14, t: 12, b: 30}, n = d.labels.length;
    var max = nice(Math.max.apply(null, [0].concat.apply([], d.series.map(function (s) { return s.values; })))), svg = axes(W, H, m, max, d.labels, n);
    var step = n > 1 ? (W - m.l - m.r) / (n - 1) : 0;
    function X(i) { return m.l + step * i; } function Y(v) { return H - m.b - (H - m.t - m.b) * v / max; }
    d.series.forEach(function (s, j) {
      var c = s.color || PAL[j % PAL.length], pts = s.values.map(function (v, i) { return X(i) + ',' + Y(+v || 0); });
      if (j === 0 && n > 1) svg += '<polygon points="' + X(0) + ',' + (H - m.b) + ' ' + pts.join(' ') + ' ' + X(n - 1) + ',' + (H - m.b) + '" fill="' + c + '" opacity=".1"/>';
      svg += '<polyline points="' + pts.join(' ') + '" fill="none" stroke="' + c + '" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>';
      s.values.forEach(function (v, i) { svg += '<circle cx="' + X(i) + '" cy="' + Y(+v || 0) + '" r="3.5" fill="#fff" stroke="' + c + '" stroke-width="2"><title>' + esc(s.name + ' · ' + d.labels[i] + ': ' + v) + '</title></circle>'; });
    });
    var every = Math.ceil(n / 8);
    d.labels.forEach(function (lb, i) { if (i % every === 0) svg += '<text x="' + X(i) + '" y="' + (H - 10) + '" text-anchor="middle" font-size="11" fill="#7A87A3">' + esc(lb) + '</text>'; });
    return '<svg viewBox="0 0 ' + W + ' ' + H + '">' + svg + '</svg>' + legend(d.series);
  }
  function donut(d) {
    var vals = d.series[0].values.map(Number), total = vals.reduce(function (a, b) { return a + b; }, 0), R = 62, r = 40, C = 80, a0 = -Math.PI / 2, svg = '';
    if (!total) return '<div class="empty">No data yet</div>';
    vals.forEach(function (v, i) {
      if (!v) return;
      var a1 = a0 + (v / total) * Math.PI * 2 - (vals.filter(Boolean).length > 1 ? .02 : 0), large = (a1 - a0) > Math.PI ? 1 : 0;
      if (vals.filter(Boolean).length === 1) a1 = a0 + Math.PI * 2 - .001;
      var p = function (rad, a) { return (C + rad * Math.cos(a)).toFixed(2) + ' ' + (C + rad * Math.sin(a)).toFixed(2); };
      svg += '<path d="M' + p(R, a0) + 'A' + R + ' ' + R + ' 0 ' + large + ' 1 ' + p(R, a1) + 'L' + p(r, a1) + 'A' + r + ' ' + r + ' 0 ' + large + ' 0 ' + p(r, a0) + 'Z" fill="' + PAL[i % PAL.length] + '"><title>' + esc(d.labels[i] + ': ' + v) + '</title></path>';
      a0 += (v / total) * Math.PI * 2;
    });
    svg += '<text x="' + C + '" y="' + (C - 2) + '" text-anchor="middle" font-size="22" font-weight="800" fill="#0E1A38">' + fmt(total) + '</text><text x="' + C + '" y="' + (C + 15) + '" text-anchor="middle" font-size="10.5" fill="#7A87A3">total</text>';
    var lg = d.labels.map(function (l, i) { return '<div class="list-row" style="padding:.3rem 0;border:0"><span><i class="sev-dot" style="background:' + PAL[i % PAL.length] + '"></i>' + esc(l) + '</span><b>' + fmt(vals[i]) + '</b></div>'; }).join('');
    return '<div class="donut-box"><svg viewBox="0 0 160 160">' + svg + '</svg><div style="flex:1">' + lg + '</div></div>';
  }
  document.querySelectorAll('.chart[data-chart]').forEach(function (el) {
    try {
      var d = JSON.parse(el.getAttribute('data-chart')); d.W = Math.max(360, Math.round((el.clientWidth || 600) - 42));
      if (!d.labels || !d.labels.length) { el.innerHTML = '<div class="empty">No data yet</div>'; return; }
      el.innerHTML = d.type === 'line' ? line(d) : d.type === 'donut' ? donut(d) : bar(d);
    } catch (e) { el.innerHTML = ''; }
  });
})();
