<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
public_head('How It Works', 'works'); ?>
<section class="page-hero"><div class="container"><span class="tag blue">Workflow</span><h1>How ReliefLink works</h1><p>A shared, auditable record from the first alert to the last verified delivery.</p></div></section>
<section class="s"><div class="container">
  <div class="sh"><span class="kicker">The journey</span><h2>From disaster to verified relief</h2></div>
  <div class="timeline">
    <?php foreach ([
      ['Disaster declared','The Super Admin or a District Admin records the disaster, severity and affected population.','Super Admin · District Admin'],
      ['Needs reported','Citizens send Emergency SOS requests. Coordinators add field reports.','Citizens · Spot Coordinators'],
      ['Missions created','Admins define relief missions: item type, target quantity, budget.','District Admin · Super Admin'],
      ['Stock matched','NGOs publish warehouse inventory so missions are fed from the nearest source.','NGO / Volunteer Teams'],
      ['Missions assigned','Each mission gets an NGO team and a spot coordinator.','District Admin'],
      ['Delivery on the ground','Coordinators record what was delivered, to how many people, and where.','Spot Coordinators'],
      ['Independent verification','The Audit Wing verifies or rejects each report. Verified quantities update mission totals.','Audit Wing'],
      ['Public transparency','Verified numbers and confirmed donations appear on the public site.','Everyone'],
    ] as $i => [$t,$d,$w]): ?>
      <div class="tl"><i><?= $i + 1 ?></i><div><h4><?= $t ?></h4><p><?= $d ?></p><div class="who"><span class="badge b-blue"><?= $w ?></span></div></div></div>
    <?php endforeach; ?>
  </div>
</div></section>
<section class="s alt"><div class="container">
  <div class="sh"><span class="kicker">Roles</span><h2>Who does what on ReliefLink</h2></div>
  <div class="grid-2">
    <div class="card"><h3>🏛️ Government</h3><ul class="checks"><li><div><b>Super Admin</b><small>Declares disasters, approves accounts, sees every record.</small></div></li><li><div><b>District Admin</b><small>Runs missions and SOS triage for one district.</small></div></li></ul></div>
    <div class="card"><h3>🤝 Responders</h3><ul class="checks"><li><div><b>NGO / Volunteer team</b><small>Manages inventory and delivers assigned missions.</small></div></li><li><div><b>Spot Coordinator</b><small>Reports from the field and answers SOS requests.</small></div></li></ul></div>
    <div class="card"><h3>✅ Oversight</h3><ul class="checks"><li><div><b>Audit Wing</b><small>Verifies deliveries, reviews donations and the audit log.</small></div></li></ul></div>
    <div class="card"><h3>🙋 Citizens &amp; donors</h3><ul class="checks"><li><div><b>No account needed</b><small>Send an SOS or donate straight from the public site.</small></div></li></ul></div>
  </div>
</div></section>
<section class="s"><div class="container">
  <div class="sh"><span class="kicker">Trust</span><h2>Secure, transparent, auditable</h2></div>
  <div class="grid-3">
    <div class="card feat"><div class="ic b">🔐</div><h3>Role-based access</h3><p>Every page checks the signed-in role. New accounts need approval.</p></div>
    <div class="card feat"><div class="ic g">🧾</div><h3>Full audit trail</h3><p>Declarations, assignments, verifications and approvals are logged with user and time.</p></div>
    <div class="card feat"><div class="ic a">👁️</div><h3>Public transparency</h3><p>Only verified deliveries and confirmed donations feed the public numbers.</p></div>
  </div>
</div></section>
<?php public_foot();
