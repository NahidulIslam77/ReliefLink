<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'district_admin', 'ngo', 'coordinator', 'audit']);
$role = $u['role'];
$steps = [
 'super_admin' => ['Declare a disaster under Disaster Management and add its zones.', 'Approve new accounts and assign roles, districts and zones under User & Role Management.', 'Watch Demand Monitoring for critical demands that have no team yet and use Match to assign one.', 'Use Relief Monitoring and Live Tracking to follow every mission end to end.', 'Confirm donations once the transaction ID matches your records.'],
 'district_admin' => ['Keep your Disaster Zones up to date: severity, affected people and status.', 'Open Demand Management and use Match to see which NGO has stock closest to the zone, then assign a team.', 'Respond to SOS requests by assigning a coordinator and/or an NGO team.', 'Use NGO Coordination to see which zones are locked and release a lock if a team cannot proceed.', 'Review District Analytics for coverage and response times.'],
 'ngo' => ['Keep Inventory current. Matching uses your stock and warehouse district.', 'Open Available Demands, check the match and Accept Mission. This locks the zone for that item.', 'Use Dispatch Relief to assign a vehicle, driver and team, then add checkpoints under Live Tracking.', 'When goods arrive, get the 6-digit code from the Spot Coordinator.', 'Submit delivery evidence under Delivery Confirmation: code, quantity, 3+ photos and GPS.'],
 'coordinator' => ['Submit demands for your zone under Submit Demand. Use urgency honestly so critical needs are matched first.', 'Send an Emergency SOS alert for life-threatening situations (and call 999).', 'When relief arrives, give the NGO team your mission code, then confirm the families actually served.', 'No signal? Keep working. Records are saved on your phone and sync later under Offline & Sync.'],
 'audit' => ['Open Pending Verification. Critical missions come first.', 'Review the photos, GPS distance, timestamp and the coordinator confirmation.', 'Complete every required check before you verify. Reject with a clear reason if anything does not match.', 'Verified deliveries update mission totals, public figures and NGO stock.', 'Use Audit Report for summaries and CSV exports.'],
][$role];
$faq = [['What does "zone locked" mean?', 'When a team accepts a demand, the zone is locked for that item type to that team. No other team can accept the same item for that zone, which prevents duplicate deliveries. The lock is released when the mission completes or is cancelled.'],
 ['Why does a delivery need a confirmation code?', 'The Spot Coordinator on the ground gives the NGO team a mission-specific code only when the goods arrive. It proves a local person saw the delivery.'],
 ['When do delivered numbers count?', 'Only after the Audit Wing verifies the evidence. Until then the delivery shows as pending and public totals do not change.'],
 ['What if I lose my connection?', 'Coordinators can keep submitting demands, SOS alerts and confirmations. They are saved on the device and sent automatically when the connection returns. Photo evidence needs a connection.'],
 ['I forgot my password.', 'Use "Forgot password?" on the sign-in page, or change it from My Profile while signed in.']];
dash_head('Help & Emergency Info', 'help', 'Contacts, how-to guides and answers');
?>
<section class="panel" style="border-color:#F4B0AD"><header style="background:var(--red-l);color:var(--red-d)"><h2>Emergency contacts</h2></header><div class="emerg"><div><small>National emergency</small><b>999</b>Police, fire and ambulance</div><div><small>National Emergency Operations Centre (EOC)</small><b><?= e(EOC_PHONE) ?></b>24/7 disaster coordination</div></div></section>
<div class="cols c11"><section class="panel"><header><h2>Your workflow as <?= e(ROLES[$role][0]) ?></h2></header><ol style="margin:0;padding:1rem 1.3rem 1.2rem 2.4rem;display:grid;gap:.6rem;font-size:14.5px"><?php foreach ($steps as $s) echo '<li>' . e($s) . '</li>'; ?></ol></section>
<section class="panel faq"><header><h2>Frequently asked</h2></header><?php foreach ($faq as [$qq, $aa]) echo '<details><summary>' . e($qq) . '</summary><p>' . e($aa) . '</p></details>'; ?></section></div>
<?php dash_foot();
