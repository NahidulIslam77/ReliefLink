# ReliefLink: Bangladesh Disaster Coordination Platform



## Quick start (XAMPP)
1. Copy this folder to `htdocs/relieflink`.
2. Start Apache and MySQL. In phpMyAdmin choose **Import** and run `database/schema.sql`
   (creates the `relieflink` database, 64 districts and demo data; re-running it resets everything).
3. Edit `config/config.php` if your MySQL user/password differ (XAMPP default: `root`, empty password).
4. Make sure `uploads/` is writable by the web server (delivery photos are stored in `uploads/evidence/`).
5. Open **http://localhost/relieflink/**. PHP 8.0+ with the PDO MySQL extension is required.

## Demo accounts (password for all: `Relief@123`; pick the matching role on the sign-in page)
| Role | Login | Notes |
|---|---|---|
| Super Admin | admin@relieflink.gov.bd | national oversight |
| District Admin | district@relieflink.gov.bd | Sylhet |
| NGO / Volunteer | ngo@relieflink.org | BRAC Emergency Response Team 04 |
| Spot Coordinator | spot@relieflink.org | Sylhet, Sadar North Zone A |
| Audit Wing | audit@relieflink.gov.bd | |

## The workflow 
1. **Demand reporting.** The Spot Coordinator submits a demand for their zone (category, quantity, households, urgency, access mode, GPS). Works offline.
2. **Smart matchmaking.** Admins press *Match* on a demand and see NGOs ranked by sufficient stock and distance from their warehouse to the zone. NGOs also see demands sorted by priority and distance, with their own stock match.
3. **Zone locking.** An NGO accepts a demand: the zone is locked **for that item type**, stock is reserved, and no other team can take it. A database unique key makes this safe under concurrent clicks. Admins can release a lock before dispatch.
4. **Dispatch and live tracking.** The team picks a vehicle, driver and members, dispatches, and adds route checkpoints. Coordinator and admins watch progress, ETA and a route map.
5. **Delivery confirmation.** On dispatch a 6-digit mission code is generated. The **Spot Coordinator** sees it, gives it to the NGO team on arrival and confirms the families actually served.
6. **Evidence.** The NGO enters the code, quantity, families, **3 to 8 photos**, GPS and timestamp.
7. **Audit.** The Audit Wing reviews photos, GPS distance from the zone, timestamp, coordinator signature and quantities against a checklist, then verifies or rejects. Only then do mission totals, public "people reached" figures and NGO stock change (one database transaction).
8. **SOS.** Citizens use the public SOS form; coordinators send alerts from their dashboard; admins assign a coordinator and/or NGO.
9. **Notifications.** Role-specific in-app alerts by category, with per-user preferences.
10. **Dashboards and analytics.** Role dashboards, district analytics, audit report with CSV export and print.

## Pages by role
| Role | Pages |
|---|---|
| Super Admin | Dashboard, Disaster Management, Disaster Zones, District Management, NGO/Volunteer Mgmt (incl. zone locks), User & Role Management, Demand Monitoring, Relief Monitoring, Live Tracking, Deliveries, SOS Queue, Inventory (view), Donations, Audit log |
| District Admin | Dashboard, Disasters, Disaster Zones, Demand Management (match/assign), NGO Coordination (zone locks), Relief Dispatch, Live Tracking, SOS Monitoring, District Analytics |
| NGO / Volunteer | Dashboard, Inventory Management, Available Demands (accept + zone lock), My Missions, Dispatch Relief, Live Tracking, Team Management, Vehicle Management, Delivery Confirmation |
| Spot Coordinator | Dashboard, Submit Demand, Demand History, Active Missions, Emergency SOS, Delivery Confirmation (shows the code), Offline & Sync |
| Audit Wing | Audit Dashboard, Pending Verification, evidence review, Audit History, Audit Report, Donations, Audit log |

Everyone also has Notifications, My Profile, Settings and Help & Emergency Info. Public (no login): landing page, how it works, active disasters map, **Emergency SOS**, **Donate**.

## Offline-first
Coordinators can submit demands, SOS alerts and delivery confirmations with no signal. They are stored in the browser (localStorage), shown under **Offline & Sync**, and sent automatically when the connection returns (or via *Sync now*). Each record has a client ID so a retry never creates duplicates (`api/sync.php`). Photo evidence needs a connection.

