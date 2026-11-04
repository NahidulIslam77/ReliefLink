<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$div  = $_GET['division'] ?? ''; $type = $_GET['type'] ?? ''; $sev = $_GET['severity'] ?? ''; $st = $_GET['status'] ?? 'active';
$where = ['1=1']; $p = [];
if ($div !== '')  { $where[] = 'di.division=?'; $p[] = $div; }
if (in_array($type, DISASTER_TYPES, true)) { $where[] = 'd.type=?'; $p[] = $type; }
if (in_array($sev, SEVERITIES, true))      { $where[] = 'd.severity=?'; $p[] = $sev; }
if (in_array($st, ['active', 'monitoring', 'resolved'], true)) { $where[] = 'd.status=?'; $p[] = $st; }
$list = rows('SELECT d.*, di.name AS district, di.division, di.lat, di.lng,
    (SELECT COUNT(*) FROM relief_missions m WHERE m.disaster_id=d.id) AS missions,
    (SELECT COALESCE(SUM(m.delivered_qty),0) FROM relief_missions m WHERE m.disaster_id=d.id) AS delivered
    FROM disasters d JOIN districts di ON di.id=d.district_id WHERE ' . implode(' AND ', $where) . '
    ORDER BY FIELD(d.severity,"critical","high","medium","low"), d.declared_at DESC', $p);
$points = array_map(fn($d) => ['title' => $d['title'], 'name' => $d['district'], 'severity' => $d['severity'], 'status' => $d['status'], 'people' => $d['affected_people'], 'lat' => $d['lat'], 'lng' => $d['lng']], $list);
$divisions = array_column(rows('SELECT DISTINCT division FROM districts ORDER BY division'), 'division');

public_head('Active Disasters', 'disasters'); ?>
<section class="page-hero"><div class="container"><span class="tag"><span class="dot"></span> Live</span><h1>Active disaster areas</h1><p>Declared disasters across Bangladesh, updated by district administrations.</p></div></section>
<section class="s" style="padding-top:2rem"><div class="container">
  <form class="filters" method="get">
    <select name="division" aria-label="Division"><option value="">All divisions</option><?php foreach ($divisions as $d): ?><option <?= $div === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?></select>
    <select name="type" aria-label="Type"><option value="">All types</option><?= opts(DISASTER_TYPES, $type) ?></select>
    <select name="severity" aria-label="Severity"><option value="">All severities</option><?= opts(SEVERITIES, $sev) ?></select>
    <select name="status" aria-label="Status"><option value="">Any status</option><?= opts(['active', 'monitoring', 'resolved'], $st) ?></select>
    <button class="btn btn-primary btn-sm">Apply filters</button>
  </form>
  <div class="legend"><span><i style="background:var(--red)"></i>Critical</span><span><i style="background:var(--orange)"></i>High</span><span><i style="background:var(--amber)"></i>Medium</span><span><i style="background:var(--green)"></i>Low</span></div>
  <div class="maplg" data-map='<?= e(json_encode($points)) ?>'></div>
  <?php if (!$list): ?><div class="card empty">No disasters match these filters.</div><?php endif; ?>
  <div class="dgrid">
    <?php foreach ($list as $d): ?>
      <article class="dcard <?= e($d['severity']) ?>">
        <header><div><h3><?= e($d['district']) ?></h3><small><?= e($d['division']) ?> · <?= e(ucfirst($d['type'])) ?></small></div><?= badge($d['severity']) ?></header>
        <p style="font-size:13.5px;color:#334155"><?= e($d['title']) ?></p>
        <div class="nums"><div><b><?= number_format($d['affected_people']) ?></b>people affected</div><div><b><?= number_format($d['families']) ?></b>families</div><div><b><?= (int)$d['missions'] ?></b>relief missions</div><div><b><?= number_format($d['delivered']) ?></b>units delivered</div></div>
        <div style="margin-top:.8rem;display:flex;justify-content:space-between;align-items:center"><?= badge($d['status']) ?><a href="<?= url('donate.php?disaster=' . $d['id']) ?>" style="font-size:13px;font-weight:600">Donate</a></div>
      </article>
    <?php endforeach; ?>
  </div>
</div></section>
<?php public_foot();
