<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$s = [
    'active' => (int)val('SELECT COUNT(*) FROM disasters WHERE status="active"'),
    'dist'   => (int)val('SELECT COUNT(DISTINCT district_id) FROM disasters WHERE status="active"'),
    'miss'   => (int)val('SELECT COUNT(*) FROM relief_missions'),
    'people' => (int)val('SELECT COALESCE(SUM(recipients),0) FROM deliveries WHERE status="verified"'),
    'donated'=> (float)val('SELECT COALESCE(SUM(amount),0) FROM donations WHERE status="confirmed"'),
    'verif'  => (int)val('SELECT COUNT(*) FROM deliveries WHERE status="verified"'),
    'dtotal' => (int)val('SELECT COUNT(*) FROM deliveries WHERE status<>"pending"'),
];
$rate = $s['dtotal'] ? round($s['verif'] / $s['dtotal'] * 100, 1) : 100;
$points = rows('SELECT d.title, d.severity, d.status, d.affected_people AS people, di.name, di.lat, di.lng FROM disasters d JOIN districts di ON di.id=d.district_id WHERE d.status="active"');

public_head('Smarter Disaster Response', 'home'); ?>
<section class="hero"><div class="container">
  <div>
    <span class="tag"><span class="dot"></span> Live · <?= $s['active'] ?> active disasters across <?= $s['dist'] ?> districts</span>
    <h1>Smarter Disaster Response. <span>Faster Relief.</span> Zero Overlap.</h1>
    <p class="lead">One national platform where government, NGOs and spot coordinators see the same needs, the same stock and the same deliveries, so help reaches the right place once.</p>
    <div class="actions"><a class="btn btn-primary" href="<?= url('login.php') ?>">Access dashboard</a><a class="btn btn-ghost" href="<?= url('disasters.php') ?>">View active disasters</a></div>
    <div class="trust"><span>🔐 Role-based secure access</span><span>🇧🇩 64 districts covered</span><span>🕐 24/7 operations</span></div>
  </div>
  <div class="livecard" aria-label="Live disaster map">
    <header><span>National disaster map</span><span class="live">● LIVE</span></header>
    <div class="mapbox" style="height:340px" data-map='<?= e(json_encode($points)) ?>'></div>
    <div class="mini">
      <div><b data-live="active_disasters"><?= $s['active'] ?></b>Active</div>
      <div><b data-live="districts"><?= $s['dist'] ?></b>Districts</div>
      <div><b data-live="missions"><?= $s['miss'] ?></b>Missions</div>
    </div>
  </div>
</div></section>

<div class="stat-strip"><div class="container"><div class="grid">
  <div class="stat"><div><small>Active disasters</small><b data-live="active_disasters"><?= $s['active'] ?></b></div><span class="ic" style="background:var(--red-l)">🌊</span></div>
  <div class="stat"><div><small>Districts affected</small><b data-live="districts"><?= $s['dist'] ?></b></div><span class="ic" style="background:var(--amber-l)">📍</span></div>
  <div class="stat"><div><small>Relief missions</small><b data-live="missions"><?= number_format($s['miss']) ?></b></div><span class="ic" style="background:var(--blue-l)">📦</span></div>
  <div class="stat"><div><small>People reached (verified)</small><b data-live="people_reached"><?= number_format($s['people']) ?></b></div><span class="ic" style="background:var(--green-l)">✅</span></div>
</div></div></div>

<section class="s"><div class="container">
  <div class="sh"><span class="kicker">Platform</span><h2>Everything you need to coordinate relief operations</h2><p>Built with the people who run response on the ground, from the National EOC to the last mile.</p></div>
  <div class="grid-3">
    <?php foreach ([
      ['b','🗺️','Real-time disaster mapping','See every declared disaster, its severity and affected population on one national map.'],
      ['r','🚨','Emergency SOS intake','Citizens send a request in under a minute. It reaches district admins and coordinators instantly.'],
      ['g','📦','Relief mission tracking','Targets, deliveries and balances for every mission, with no double-counting between agencies.'],
      ['a','🏬','Shared inventory','NGOs publish stock by warehouse so supplies are sent from the nearest source.'],
      ['p','✅','Verified delivery','Coordinators report on the spot, the Audit Wing verifies before anything counts as delivered.'],
      ['o','💰','Transparent donations','Every taka donated is recorded, confirmed and linked to a disaster.'],
    ] as [$c,$i,$t,$d]): ?>
      <div class="card feat"><div class="ic <?= $c ?>"><?= $i ?></div><h3><?= $t ?></h3><p><?= $d ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="s alt"><div class="container">
  <div class="sh"><span class="kicker">Process</span><h2>From disaster declaration to verified delivery</h2><p>Five steps, one shared record.</p></div>
  <div class="steps">
    <?php foreach ([['Declare','The Super Admin or District Admin declares a disaster with severity and affected numbers.'],['Plan','Relief missions are created with item types, quantities and budgets.'],['Assign','Missions go to an NGO team and a spot coordinator, using stock already available.'],['Deliver','The coordinator reports each delivery from the field with quantities and recipients.'],['Verify','The Audit Wing verifies. Only verified deliveries count toward totals.']] as $i => [$t,$d]): ?>
      <div class="step"><i><?= $i + 1 ?></i><h4><?= $t ?></h4><p><?= $d ?></p></div>
    <?php endforeach; ?>
  </div>
  <p style="text-align:center;margin-top:2rem"><a href="<?= url('how-it-works.php') ?>">See the full workflow</a></p>
</div></section>

<section class="s"><div class="container split">
  <div>
    <span class="tag blue">For every stakeholder</span>
    <h2 style="font-size:2rem;margin:.9rem 0 .4rem;font-weight:800">Every stakeholder. One platform.</h2>
    <p style="color:var(--muted)">Each role sees exactly what it needs and nothing more.</p>
    <ul class="checks">
      <li><span class="ic b">🏛️</span><div><b>Super Admin</b><small>National oversight, disaster declaration, user approval.</small></div></li>
      <li><span class="ic g">🏢</span><div><b>District Admin</b><small>Coordinates missions and SOS requests inside the district.</small></div></li>
      <li><span class="ic a">🤝</span><div><b>NGO / Volunteer teams</b><small>Manage stock and run assigned missions.</small></div></li>
      <li><span class="ic r">📍</span><div><b>Spot Coordinators</b><small>Respond to SOS and record deliveries on the ground.</small></div></li>
      <li><span class="ic p">✅</span><div><b>Audit Wing</b><small>Verify deliveries and review the full audit trail.</small></div></li>
    </ul>
  </div>
  <div class="mock" aria-hidden="true">
    <div class="row"><b>Dry food packs · Sunamganj</b><span class="badge b-blue">In transit</span></div>
    <div style="padding:.8rem 0"><div class="bar"><i style="width:62%"></i></div><div class="bar-label">7,400 of 12,000 packs delivered</div></div>
    <div class="row"><span>Companiganj · 300 kits</span><span class="badge b-amber">Awaiting audit</span></div>
    <div class="row"><span>Tahirpur · 2,400 packs</span><span class="badge b-green">Verified</span></div>
    <div class="row"><span>New SOS · Tahirpur</span><span class="badge b-red">New</span></div>
  </div>
</div></section>

<section class="s impact"><div class="container">
  <div class="sh"><span class="kicker" style="color:#8FB0FF">Accountability</span><h2>Every taka of relief aid. Tracked and verified.</h2><p>Live figures from the platform, not estimates.</p></div>
  <div class="grid-3">
    <div class="card"><b data-live="people_reached"><?= number_format($s['people']) ?></b><span>People reached with verified deliveries</span></div>
    <div class="card"><b><?= $rate ?>%</b><span>Deliveries passing audit</span></div>
    <div class="card"><b data-live="donated" data-money><?= money($s['donated']) ?></b><span>Donations confirmed</span></div>
  </div>
</div></section>

<section class="band-red"><div class="container">
  <span class="tag">🚨 Emergency</span>
  <h2>Are you in an affected area?</h2>
  <p>If you or your family need rescue, medicine, food or shelter, send an SOS now. It takes under a minute and no account is needed. In immediate danger, call 999.</p>
  <a class="btn btn-white" href="<?= url('sos.php') ?>">Send emergency SOS</a>
</div></section>

<section class="s"><div class="container">
  <div class="sh"><span class="kicker">From the field</span><h2>What responders say</h2></div>
  <!-- Sample testimonials: replace with real, consented quotes. -->
  <div class="grid-3 quotes">
    <div class="card"><p>“Before this, three groups delivered to the same union and another had nothing. Now we see each other's missions.”</p><div class="who"><b>Sample: District Admin</b>Sylhet</div></div>
    <div class="card"><p>“I log the delivery on my phone, the Audit Wing verifies it, and the numbers update the same day.”</p><div class="who"><b>Sample: Spot Coordinator</b>Sunamganj</div></div>
    <div class="card"><p>“Publishing our warehouse stock means requests reach us when we are the nearest source.”</p><div class="who"><b>Sample: NGO logistics lead</b>Dhaka</div></div>
  </div>
</div></section>
<?php public_foot();
