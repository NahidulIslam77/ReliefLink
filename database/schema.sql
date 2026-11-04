-- ReliefLink schema + seed data (MySQL 5.7+/MariaDB 10.3+). Re-running this file resets the database.
CREATE DATABASE IF NOT EXISTS relieflink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE relieflink;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS notifications, rate_limits, audit_logs, password_resets, delivery_photos, mission_members, mission_events, zone_locks, deliveries, relief_missions, demands, zones, vehicles, team_members, inventory, donations, sos_requests, disasters, users, districts;

CREATE TABLE districts (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(60) NOT NULL UNIQUE, division VARCHAR(40) NOT NULL,
  lat DECIMAL(8,5) NOT NULL, lng DECIMAL(8,5) NOT NULL, INDEX (division)
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(60) NOT NULL, last_name VARCHAR(60) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE, phone VARCHAR(15) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','district_admin','ngo','coordinator','audit') NOT NULL,
  district_id INT NULL, zone_id INT NULL, organization VARCHAR(150) NULL,
  status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
  settings TEXT NULL, last_login DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE SET NULL, INDEX (role, status)
) ENGINE=InnoDB;

CREATE TABLE disasters (
  id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(160) NOT NULL,
  type ENUM('flood','cyclone','landslide','erosion','earthquake','fire','drought','other') NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL, status ENUM('active','monitoring','resolved') NOT NULL DEFAULT 'active',
  district_id INT NOT NULL, affected_people INT NOT NULL DEFAULT 0, families INT NOT NULL DEFAULT 0, description TEXT NULL,
  declared_by INT NULL, declared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (district_id) REFERENCES districts(id), INDEX (status, severity)
) ENGINE=InnoDB;

CREATE TABLE zones (
  id INT AUTO_INCREMENT PRIMARY KEY, district_id INT NOT NULL, disaster_id INT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(12) NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium', status ENUM('active','monitoring','resolved') NOT NULL DEFAULT 'active',
  affected_people INT NOT NULL DEFAULT 0, households INT NOT NULL DEFAULT 0, lat DECIMAL(8,5) NOT NULL, lng DECIMAL(8,5) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (district_id) REFERENCES districts(id), FOREIGN KEY (disaster_id) REFERENCES disasters(id) ON DELETE SET NULL, INDEX (district_id)
) ENGINE=InnoDB;

CREATE TABLE inventory (
  id INT AUTO_INCREMENT PRIMARY KEY, ngo_id INT NOT NULL, item VARCHAR(120) NOT NULL,
  category ENUM('food','water','medicine','shelter','hygiene','clothing','rescue','other') NOT NULL,
  quantity INT NOT NULL DEFAULT 0, reserved INT NOT NULL DEFAULT 0, unit VARCHAR(30) NOT NULL DEFAULT 'units',
  warehouse VARCHAR(120) NOT NULL, district_id INT NULL, low_threshold INT NOT NULL DEFAULT 50,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (ngo_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY, ngo_id INT NOT NULL, plate VARCHAR(30) NOT NULL, type VARCHAR(40) NOT NULL, capacity VARCHAR(40) NOT NULL DEFAULT '',
  status ENUM('available','on_mission','maintenance') NOT NULL DEFAULT 'available', FOREIGN KEY (ngo_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE team_members (
  id INT AUTO_INCREMENT PRIMARY KEY, ngo_id INT NOT NULL, name VARCHAR(100) NOT NULL, title VARCHAR(60) NOT NULL DEFAULT 'Field worker',
  phone VARCHAR(15) NOT NULL DEFAULT '', is_driver TINYINT(1) NOT NULL DEFAULT 0, status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  FOREIGN KEY (ngo_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE demands (
  id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(20) NULL, zone_id INT NOT NULL, disaster_id INT NOT NULL, district_id INT NOT NULL,
  item_type ENUM('food','water','medicine','shelter','hygiene','clothing','rescue','other') NOT NULL, quantity INT NOT NULL, unit VARCHAR(30) NOT NULL,
  households INT NOT NULL DEFAULT 0, access_mode VARCHAR(30) NOT NULL DEFAULT 'Road', priority ENUM('critical','high','medium','low') NOT NULL DEFAULT 'high',
  status ENUM('pending','matched','dispatched','delivered','cancelled') NOT NULL DEFAULT 'pending',
  contact_name VARCHAR(100) NOT NULL DEFAULT '', contact_phone VARCHAR(15) NOT NULL DEFAULT '', description VARCHAR(500) NULL,
  lat DECIMAL(8,5) NULL, lng DECIMAL(8,5) NULL, reported_by INT NULL, client_uid VARCHAR(40) NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zone_id) REFERENCES zones(id), FOREIGN KEY (district_id) REFERENCES districts(id), INDEX (status, priority)
) ENGINE=InnoDB;

CREATE TABLE relief_missions (
  id INT AUTO_INCREMENT PRIMARY KEY, disaster_id INT NOT NULL, title VARCHAR(160) NOT NULL, code VARCHAR(20) NULL,
  item_type ENUM('food','water','medicine','shelter','hygiene','clothing','rescue','other') NOT NULL, unit VARCHAR(30) NOT NULL DEFAULT 'units',
  target_qty INT NOT NULL, delivered_qty INT NOT NULL DEFAULT 0, budget DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('planned','assigned','accepted','in_transit','arrived','completed','cancelled') NOT NULL DEFAULT 'planned',
  priority ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
  ngo_id INT NULL, coordinator_id INT NULL, created_by INT NULL, demand_id INT NULL, zone_id INT NULL, inventory_id INT NULL,
  vehicle_id INT NULL, driver_id INT NULL, cargo VARCHAR(200) NULL, dispatched_qty INT NULL,
  planned_departure DATETIME NULL, eta DATETIME NULL, dispatched_at DATETIME NULL, arrived_at DATETIME NULL, progress TINYINT NOT NULL DEFAULT 0,
  confirmation_code CHAR(6) NULL, coord_confirmed TINYINT(1) NOT NULL DEFAULT 0, coord_families INT NULL, coord_notes VARCHAR(300) NULL, coord_confirmed_at DATETIME NULL,
  notes VARCHAR(300) NULL, reserved_qty INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (disaster_id) REFERENCES disasters(id) ON DELETE CASCADE, INDEX (status), INDEX (ngo_id), INDEX (coordinator_id)
) ENGINE=InnoDB;

CREATE TABLE zone_locks (
  id INT AUTO_INCREMENT PRIMARY KEY, zone_id INT NOT NULL, item_type VARCHAR(20) NOT NULL, ngo_id INT NOT NULL, mission_id INT NOT NULL,
  active_key TINYINT NULL, locked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, released_at DATETIME NULL,
  UNIQUE KEY one_active_lock (zone_id, item_type, active_key)
) ENGINE=InnoDB;

CREATE TABLE mission_members (
  mission_id INT NOT NULL, member_id INT NOT NULL, PRIMARY KEY (mission_id, member_id)
) ENGINE=InnoDB;

CREATE TABLE mission_events (
  id INT AUTO_INCREMENT PRIMARY KEY, mission_id INT NOT NULL, status VARCHAR(30) NOT NULL, note VARCHAR(250) NOT NULL DEFAULT '',
  user_id INT NULL, lat DECIMAL(8,5) NULL, lng DECIMAL(8,5) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (mission_id) REFERENCES relief_missions(id) ON DELETE CASCADE, INDEX (mission_id)
) ENGINE=InnoDB;

CREATE TABLE deliveries (
  id INT AUTO_INCREMENT PRIMARY KEY, mission_id INT NOT NULL, reported_by INT NULL, quantity INT NOT NULL, recipients INT NOT NULL,
  location VARCHAR(200) NOT NULL, notes VARCHAR(500) NULL, gps_lat DECIMAL(8,5) NULL, gps_lng DECIMAL(8,5) NULL, evidence_at DATETIME NULL,
  status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending', reviewed_by INT NULL, reviewed_at DATETIME NULL, review_note VARCHAR(300) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (mission_id) REFERENCES relief_missions(id) ON DELETE CASCADE, INDEX (status)
) ENGINE=InnoDB;

CREATE TABLE delivery_photos (
  id INT AUTO_INCREMENT PRIMARY KEY, delivery_id INT NOT NULL, path VARCHAR(160) NOT NULL,
  FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sos_requests (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, phone VARCHAR(15) NOT NULL, district_id INT NOT NULL, location VARCHAR(200) NOT NULL,
  people_count INT NOT NULL DEFAULT 1, need_type ENUM('rescue','medical','food','water','shelter','fire','flooding','other') NOT NULL, description TEXT NULL,
  source ENUM('public','coordinator') NOT NULL DEFAULT 'public', severity ENUM('critical','high','medium') NOT NULL DEFAULT 'high',
  lat DECIMAL(8,5) NULL, lng DECIMAL(8,5) NULL, reported_by INT NULL, client_uid VARCHAR(40) NULL UNIQUE,
  status ENUM('new','assigned','resolved') NOT NULL DEFAULT 'new', assigned_to INT NULL, assigned_ngo_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, resolved_at DATETIME NULL,
  FOREIGN KEY (district_id) REFERENCES districts(id), INDEX (status, district_id)
) ENGINE=InnoDB;

CREATE TABLE donations (
  id INT AUTO_INCREMENT PRIMARY KEY, donor_name VARCHAR(100) NOT NULL DEFAULT 'Anonymous', email VARCHAR(150) NOT NULL DEFAULT '',
  amount DECIMAL(14,2) NOT NULL, method VARCHAR(20) NOT NULL, trx_ref VARCHAR(60) NOT NULL, disaster_id INT NULL, message VARCHAR(255) NULL,
  status ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending', confirmed_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (status)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX (token_hash)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, action VARCHAR(60) NOT NULL, entity VARCHAR(40) NOT NULL DEFAULT '', entity_id INT NULL,
  detail VARCHAR(255) NOT NULL DEFAULT '', ip VARCHAR(45) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (action), INDEX (created_at)
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, title VARCHAR(160) NOT NULL, body VARCHAR(300) NOT NULL DEFAULT '', link VARCHAR(160) NOT NULL DEFAULT '',
  category VARCHAR(20) NOT NULL DEFAULT 'system', is_read TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX (user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE rate_limits (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, k VARCHAR(30) NOT NULL, ip VARCHAR(45) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (k, ip, created_at)
) ENGINE=InnoDB;

-- ---------- 64 districts ----------
INSERT INTO districts (name, division, lat, lng) VALUES
('Barguna','Barishal',22.15,90.12),('Barishal','Barishal',22.70,90.37),('Bhola','Barishal',22.19,90.65),('Jhalokati','Barishal',22.64,90.20),('Patuakhali','Barishal',22.36,90.33),('Pirojpur','Barishal',22.58,89.97),
('Bandarban','Chattogram',22.20,92.22),('Brahmanbaria','Chattogram',23.96,91.11),('Chandpur','Chattogram',23.23,90.65),('Chattogram','Chattogram',22.34,91.83),('Cumilla','Chattogram',23.46,91.18),('Cox''s Bazar','Chattogram',21.43,92.01),('Feni','Chattogram',23.02,91.40),('Khagrachhari','Chattogram',23.12,91.98),('Lakshmipur','Chattogram',22.94,90.84),('Noakhali','Chattogram',22.82,91.10),('Rangamati','Chattogram',22.65,92.17),
('Dhaka','Dhaka',23.81,90.41),('Faridpur','Dhaka',23.60,89.84),('Gazipur','Dhaka',24.00,90.42),('Gopalganj','Dhaka',23.01,89.83),('Kishoreganj','Dhaka',24.43,90.78),('Madaripur','Dhaka',23.17,90.20),('Manikganj','Dhaka',23.86,90.00),('Munshiganj','Dhaka',23.55,90.53),('Narayanganj','Dhaka',23.63,90.50),('Narsingdi','Dhaka',23.92,90.72),('Rajbari','Dhaka',23.76,89.64),('Shariatpur','Dhaka',23.24,90.43),('Tangail','Dhaka',24.25,89.92),
('Bagerhat','Khulna',22.66,89.79),('Chuadanga','Khulna',23.64,88.84),('Jashore','Khulna',23.17,89.21),('Jhenaidah','Khulna',23.54,89.17),('Khulna','Khulna',22.82,89.55),('Kushtia','Khulna',23.90,89.12),('Magura','Khulna',23.49,89.42),('Meherpur','Khulna',23.76,88.63),('Narail','Khulna',23.17,89.50),('Satkhira','Khulna',22.72,89.07),
('Jamalpur','Mymensingh',24.92,89.94),('Mymensingh','Mymensingh',24.75,90.41),('Netrokona','Mymensingh',24.87,90.73),('Sherpur','Mymensingh',25.02,90.02),
('Bogura','Rajshahi',24.85,89.37),('Chapainawabganj','Rajshahi',24.60,88.28),('Joypurhat','Rajshahi',25.10,89.02),('Naogaon','Rajshahi',24.80,88.94),('Natore','Rajshahi',24.41,89.00),('Pabna','Rajshahi',24.00,89.23),('Rajshahi','Rajshahi',24.37,88.60),('Sirajganj','Rajshahi',24.45,89.70),
('Dinajpur','Rangpur',25.63,88.64),('Gaibandha','Rangpur',25.33,89.53),('Kurigram','Rangpur',25.81,89.65),('Lalmonirhat','Rangpur',25.99,89.45),('Nilphamari','Rangpur',25.93,88.86),('Panchagarh','Rangpur',26.34,88.55),('Rangpur','Rangpur',25.75,89.25),('Thakurgaon','Rangpur',26.03,88.47),
('Habiganj','Sylhet',24.38,91.42),('Moulvibazar','Sylhet',24.48,91.77),('Sunamganj','Sylhet',25.07,91.40),('Sylhet','Sylhet',24.90,91.87);

-- ---------- demo users (password for all: Relief@123) ----------
INSERT INTO users (id,first_name,last_name,email,phone,password_hash,role,district_id,zone_id,organization,status) VALUES
(1,'A.K.M. Zahurul','Huq','admin@relieflink.gov.bd','01711000001','$2y$10$0tY5Yw2O4BT/eN6vH/1h0O46mBBX6TW17kakqP9zLshHBU0pAFE.O','super_admin',NULL,NULL,'Department of Disaster Management (DDM)','active'),
(2,'Hafizul','Islam','district@relieflink.gov.bd','01711000002','$2y$10$0tY5Yw2O4BT/eN6vH/1h0O46mBBX6TW17kakqP9zLshHBU0pAFE.O','district_admin',(SELECT id FROM districts WHERE name='Sylhet'),NULL,'DC Office, Sylhet','active'),
(3,'Aminur','Rahman','ngo@relieflink.org','01711000003','$2y$10$0tY5Yw2O4BT/eN6vH/1h0O46mBBX6TW17kakqP9zLshHBU0pAFE.O','ngo',NULL,NULL,'BRAC Emergency Response Team 04','active'),
(4,'Nazmul','Karim','spot@relieflink.org','01711000004','$2y$10$0tY5Yw2O4BT/eN6vH/1h0O46mBBX6TW17kakqP9zLshHBU0pAFE.O','coordinator',(SELECT id FROM districts WHERE name='Sylhet'),1,'Spot Coordinator, Sadar North Zone A','active'),
(5,'Sabrina','Hossain','audit@relieflink.gov.bd','01711000005','$2y$10$0tY5Yw2O4BT/eN6vH/1h0O46mBBX6TW17kakqP9zLshHBU0pAFE.O','audit',NULL,NULL,'DDM Audit & Transparency Unit','active'),
(6,'Farida','Begum','red.crescent@relieflink.org','01711000006','$2y$10$0tY5Yw2O4BT/eN6vH/1h0O46mBBX6TW17kakqP9zLshHBU0pAFE.O','ngo',NULL,NULL,'Red Crescent BD','active'),
(7,'Rafiq','Ahmed','rafiq@relieflink.org','01711000007','$2y$10$0tY5Yw2O4BT/eN6vH/1h0O46mBBX6TW17kakqP9zLshHBU0pAFE.O','coordinator',(SELECT id FROM districts WHERE name='Sunamganj'),7,'Spot Coordinator, Tahirpur','active'),
(8,'Selina','Khatun','selina@relieflink.org','01711000008','$2y$10$0tY5Yw2O4BT/eN6vH/1h0O46mBBX6TW17kakqP9zLshHBU0pAFE.O','coordinator',(SELECT id FROM districts WHERE name='Cox''s Bazar'),10,'Spot Coordinator, Teknaf','active'),
(9,'Joy','Chakma','joy@relieflink.org','01711000009','$2y$10$0tY5Yw2O4BT/eN6vH/1h0O46mBBX6TW17kakqP9zLshHBU0pAFE.O','coordinator',(SELECT id FROM districts WHERE name='Rangamati'),12,'Spot Coordinator, Kaptai','pending'),
(10,'Mahbub','Alam','mahbub@savethechildren.example','01711000010','$2y$10$0tY5Yw2O4BT/eN6vH/1h0O46mBBX6TW17kakqP9zLshHBU0pAFE.O','ngo',NULL,NULL,'Save the Children (volunteer unit)','pending'),
(11,'Kamrul','Hasan','dss@relieflink.org','01711000011','$2y$10$0tY5Yw2O4BT/eN6vH/1h0O46mBBX6TW17kakqP9zLshHBU0pAFE.O','ngo',NULL,NULL,'DSS Unit 07','active'),
(12,'Nargis','Akter','khulna.admin@relieflink.gov.bd','01711000012','$2y$10$0tY5Yw2O4BT/eN6vH/1h0O46mBBX6TW17kakqP9zLshHBU0pAFE.O','district_admin',(SELECT id FROM districts WHERE name='Sunamganj'),NULL,'DC Office, Sunamganj','active');

-- ---------- disasters ----------
INSERT INTO disasters (id,title,type,severity,status,district_id,affected_people,families,description,declared_by,declared_at) VALUES
(1,'Sylhet flash flood 2026','flood','critical','active',(SELECT id FROM districts WHERE name='Sylhet'),149300,31200,'Flash flooding after record upstream rainfall. Roads cut in Sadar and Companiganj.',1,NOW() - INTERVAL 5 DAY),
(2,'Sunamganj haor flooding','flood','critical','active',(SELECT id FROM districts WHERE name='Sunamganj'),87500,18400,'Haor embankments breached; most unions under water.',1,NOW() - INTERVAL 6 DAY),
(3,'Cyclone Midhili aftermath','cyclone','high','active',(SELECT id FROM districts WHERE name='Cox''s Bazar'),45700,9800,'Coastal surge damage along Teknaf and Ukhiya.',1,NOW() - INTERVAL 9 DAY),
(4,'Rangamati landslide event','landslide','medium','active',(SELECT id FROM districts WHERE name='Rangamati'),20100,4300,'Slope failures on three hill roads.',1,NOW() - INTERVAL 12 DAY),
(5,'Teesta char flooding','flood','high','active',(SELECT id FROM districts WHERE name='Kurigram'),41200,8900,'River rise displacing char families.',1,NOW() - INTERVAL 4 DAY),
(6,'Netrokona haor flood','flood','high','active',(SELECT id FROM districts WHERE name='Netrokona'),48100,10100,'Early flash flood damaged standing crops.',1,NOW() - INTERVAL 3 DAY),
(7,'Meghna river erosion','erosion','medium','monitoring',(SELECT id FROM districts WHERE name='Bhola'),8400,1900,'Riverbank erosion threatening villages; monitored.',1,NOW() - INTERVAL 20 DAY),
(8,'Dhaka slum fire','fire','low','resolved',(SELECT id FROM districts WHERE name='Dhaka'),3200,760,'Fire at Korail settlement. Relief completed.',1,NOW() - INTERVAL 30 DAY);

-- ---------- zones ----------
INSERT INTO zones (id,district_id,disaster_id,name,code,severity,status,affected_people,households,lat,lng) VALUES
(1,(SELECT id FROM districts WHERE name='Sylhet'),1,'Sadar North Zone A','ZN-01','critical','active',18400,4100,24.95,91.84),
(2,(SELECT id FROM districts WHERE name='Sylhet'),1,'Sadar South Zone B','ZN-02','critical','active',12200,2700,24.86,91.88),
(3,(SELECT id FROM districts WHERE name='Sylhet'),1,'Companiganj Zone','ZN-03','high','active',9800,2100,25.07,91.76),
(4,(SELECT id FROM districts WHERE name='Sylhet'),1,'Golapganj Zone','ZN-04','medium','active',6400,1400,24.86,92.02),
(5,(SELECT id FROM districts WHERE name='Sylhet'),1,'Biswanath Zone','ZN-05','high','active',7100,1500,24.78,91.72),
(6,(SELECT id FROM districts WHERE name='Sylhet'),1,'Jaintiapur Zone','ZN-06','low','monitoring',3200,700,25.13,92.12),
(7,(SELECT id FROM districts WHERE name='Sunamganj'),2,'Tahirpur Zone','ZN-07','critical','active',24500,5200,25.14,91.12),
(8,(SELECT id FROM districts WHERE name='Sunamganj'),2,'Jamalganj Zone','ZN-08','high','active',17800,3900,24.87,91.30),
(9,(SELECT id FROM districts WHERE name='Sunamganj'),2,'Dharampasha Zone','ZN-09','high','active',14200,3000,25.00,91.00),
(10,(SELECT id FROM districts WHERE name='Cox''s Bazar'),3,'Teknaf Sadar','ZN-10','high','active',21000,4600,20.86,92.30),
(11,(SELECT id FROM districts WHERE name='Cox''s Bazar'),3,'Ukhiya Zone','ZN-11','medium','active',13500,2900,21.26,92.10),
(12,(SELECT id FROM districts WHERE name='Rangamati'),4,'Kaptai Zone','ZN-12','medium','active',9800,2100,22.50,92.22),
(13,(SELECT id FROM districts WHERE name='Kurigram'),5,'Char Rajibpur','ZN-13','high','active',16500,3600,25.60,89.65),
(14,(SELECT id FROM districts WHERE name='Netrokona'),6,'Khaliajuri Zone','ZN-14','high','active',19000,4100,24.72,90.95),
(15,(SELECT id FROM districts WHERE name='Bhola'),7,'Char Fasson Zone','ZN-15','medium','monitoring',8400,1900,22.18,90.75),
(16,(SELECT id FROM districts WHERE name='Dhaka'),8,'Korail Settlement','ZN-16','low','resolved',3200,760,23.78,90.40);

-- ---------- vehicles & team ----------
INSERT INTO vehicles (id,ngo_id,plate,type,capacity,status) VALUES
(1,3,'DHA-X-5521','Truck','5 ton','on_mission'),(2,3,'DHA-X-5522','Truck','5 ton','available'),(3,3,'SYL-B-2210','Motor boat','2 ton','available'),(4,3,'DHA-L-3301','Pickup','1 ton','maintenance'),
(5,6,'CTG-X-4410','Truck','7 ton','available'),(6,6,'DHA-X-7788','Pickup','1.5 ton','available'),(7,11,'DHA-T-9012','Truck','5 ton','available');
INSERT INTO team_members (id,ngo_id,name,title,phone,is_driver) VALUES
(1,3,'Aminur Rahman','Team Leader','01711000003',0),(2,3,'Md. Shafiqul Islam','Driver / Field worker','01712223344',1),(3,3,'Fatema Begum','Field worker','01713334455',0),(4,3,'Ruksana Khatun','Medical volunteer','01714445566',0),(5,3,'Harun ur Rashid','Driver','01715556677',1),
(6,6,'Farida Begum','Team Leader','01711000006',0),(7,6,'Jahangir Alam','Driver','01716667788',1),(8,6,'Mitu Akter','Field worker','01717778899',0),(9,11,'Kamrul Hasan','Team Leader','01711000011',0),(10,11,'Delwar Hossain','Driver','01718889900',1);

-- ---------- inventory (reserved = stock held for accepted missions) ----------
INSERT INTO inventory (id,ngo_id,item,category,quantity,reserved,unit,warehouse,district_id,low_threshold) VALUES
(1,3,'Dry food package (family)','food',1240,0,'packs','Sylhet Central Warehouse',(SELECT id FROM districts WHERE name='Sylhet'),200),
(2,3,'Drinking water (20L jerry can)','water',680,400,'cans','Sylhet Central Warehouse',(SELECT id FROM districts WHERE name='Sylhet'),150),
(3,3,'Emergency medicine kit','medicine',320,150,'kits','Sylhet DHIO Store',(SELECT id FROM districts WHERE name='Sylhet'),100),
(4,3,'Waterproof tarpaulin (12x15 ft)','shelter',480,0,'tarps','Sylhet Central Warehouse',(SELECT id FROM districts WHERE name='Sylhet'),100),
(5,3,'ORS packet (25-pack box)','medicine',210,0,'boxes','Sylhet DHIO Store',(SELECT id FROM districts WHERE name='Sylhet'),60),
(6,3,'Baby food (infant formula)','food',95,30,'tins','Sylhet DHIO Store',(SELECT id FROM districts WHERE name='Sylhet'),80),
(7,6,'Blankets','clothing',1200,0,'pieces','Dhaka Red Crescent Depot',(SELECT id FROM districts WHERE name='Dhaka'),300),
(8,6,'Water purification tablets','water',90,0,'boxes','Cox''s Bazar Depot',(SELECT id FROM districts WHERE name='Cox''s Bazar'),100),
(9,6,'Hygiene kit','hygiene',640,0,'kits','Cox''s Bazar Depot',(SELECT id FROM districts WHERE name='Cox''s Bazar'),150),
(10,6,'Dry food package (family)','food',5000,0,'packs','Cox''s Bazar Depot',(SELECT id FROM districts WHERE name='Cox''s Bazar'),500),
(11,6,'Drinking water (20L jerry can)','water',900,0,'cans','Sunamganj Store',(SELECT id FROM districts WHERE name='Sunamganj'),200),
(12,11,'Dry food package (family)','food',2000,0,'packs','Dhaka Warehouse',(SELECT id FROM districts WHERE name='Dhaka'),300),
(13,11,'Waterproof tarpaulin','shelter',700,0,'tarps','Mymensingh Store',(SELECT id FROM districts WHERE name='Mymensingh'),150),
(14,11,'Emergency medicine kit','medicine',400,0,'kits','Sunamganj Store',(SELECT id FROM districts WHERE name='Sunamganj'),100);

-- ---------- demands ----------
INSERT INTO demands (id,code,zone_id,disaster_id,district_id,item_type,quantity,unit,households,access_mode,priority,status,contact_name,contact_phone,description,lat,lng,reported_by,created_at) VALUES
(1,'DEM-2026-1201',1,1,(SELECT id FROM districts WHERE name='Sylhet'),'food',800,'packages',200,'Road + Boat','critical','pending','Kamal Uddin','01711001122','Families on rooftops; stored food spoiled.',24.95,91.84,4,NOW() - INTERVAL 2 HOUR),
(2,'DEM-2026-1198',2,1,(SELECT id FROM districts WHERE name='Sylhet'),'water',400,'jerry cans',100,'Road','critical','matched','Rina Begum','01911002233','Tube wells submerged.',24.86,91.88,2,NOW() - INTERVAL 4 HOUR),
(3,'DEM-2026-1195',3,1,(SELECT id FROM districts WHERE name='Sylhet'),'medicine',150,'kits',60,'Road','high','dispatched','Sufia Khatun','01911004455','Diarrhoea cases rising.',25.07,91.76,4,NOW() - INTERVAL 6 HOUR),
(4,'DEM-2026-1192',5,1,(SELECT id FROM districts WHERE name='Sylhet'),'shelter',100,'tarps',100,'Road','high','pending','Sufia Khatun','01911004455','Homes destroyed.',24.78,91.72,2,NOW() - INTERVAL 8 HOUR),
(5,'DEM-2026-1189',4,1,(SELECT id FROM districts WHERE name='Sylhet'),'food',300,'packages',75,'Boat only','medium','matched','Jahir Uddin','01719555566','Access by boat only.',24.86,92.02,2,NOW() - INTERVAL 10 HOUR),
(6,'DEM-2026-1180',1,1,(SELECT id FROM districts WHERE name='Sylhet'),'food',600,'packages',148,'Road','high','delivered','Nazmul Karim','01711000004','First-wave food relief.',24.95,91.84,4,NOW() - INTERVAL 3 DAY),
(7,'DEM-2026-1203',7,2,(SELECT id FROM districts WHERE name='Sunamganj'),'water',500,'jerry cans',125,'Road','critical','pending','Rina Begum','01911002233','No clean water for two days.',25.14,91.12,7,NOW() - INTERVAL 5 HOUR),
(8,'DEM-2026-1204',10,3,(SELECT id FROM districts WHERE name='Cox''s Bazar'),'food',600,'packages',150,'Road','medium','matched','Selina Khatun','01711000008','Ward-level shortage after surge.',20.86,92.30,8,NOW() - INTERVAL 7 HOUR),
(9,'DEM-2026-1205',13,5,(SELECT id FROM districts WHERE name='Kurigram'),'shelter',200,'tarps',100,'Boat only','high','pending','Char leader','01719000000','Stranded char households.',25.60,89.65,1,NOW() - INTERVAL 1 DAY),
(10,'DEM-2026-1206',14,6,(SELECT id FROM districts WHERE name='Netrokona'),'food',400,'packages',100,'Boat only','high','pending','Union chairman','01719000001','Crops lost; families need dry food.',24.72,90.95,1,NOW() - INTERVAL 20 HOUR),
(11,'DEM-2026-1207',1,1,(SELECT id FROM districts WHERE name='Sylhet'),'medicine',80,'kits',40,'Road','medium','delivered','Nazmul Karim','01711000004','Medicine for elderly residents.',24.95,91.84,4,NOW() - INTERVAL 4 DAY);

-- ---------- missions ----------
INSERT INTO relief_missions (id,disaster_id,title,code,item_type,unit,target_qty,delivered_qty,budget,status,priority,ngo_id,coordinator_id,created_by,demand_id,zone_id,inventory_id,vehicle_id,driver_id,cargo,dispatched_qty,planned_departure,eta,dispatched_at,progress,confirmation_code,coord_confirmed,coord_families,coord_confirmed_at,notes,reserved_qty,created_at) VALUES
(1,2,'Dry food packs · Sunamganj','RL-2026-1101','food','packs',12000,7400,3600000,'in_transit','critical',3,7,1,NULL,7,NULL,NULL,NULL,'12,000 dry food packs',12000,NULL,NOW()+INTERVAL 5 HOUR,NOW() - INTERVAL 5 DAY,62,'384712',1,2400,NOW() - INTERVAL 1 DAY,NULL,0,NOW() - INTERVAL 5 DAY),
(2,2,'Drinking water · Sunamganj','RL-2026-1102','water','jerry cans',6000,2400,720000,'in_transit','critical',6,7,1,NULL,8,NULL,NULL,NULL,'6,000 jerry cans',6000,NULL,NOW()+INTERVAL 8 HOUR,NOW() - INTERVAL 5 DAY,40,'551209',0,NULL,NULL,NULL,0,NOW() - INTERVAL 5 DAY),
(3,1,'Dry food packs · Sylhet Sadar','RL-2026-1103','food','packs',9000,2100,2700000,'in_transit','high',3,4,2,NULL,1,NULL,NULL,NULL,'9,000 dry food packs',9000,NULL,NOW()+INTERVAL 9 HOUR,NOW() - INTERVAL 4 DAY,35,'720458',0,NULL,NULL,NULL,0,NOW() - INTERVAL 4 DAY),
(4,1,'Emergency medicine kits · Sylhet','RL-2026-1104','medicine','kits',1500,0,900000,'assigned','high',6,4,2,NULL,2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,NULL,NULL,0,NOW() - INTERVAL 3 DAY),
(5,1,'Tarpaulins · Companiganj','RL-2026-1105','shelter','tarps',2000,0,600000,'planned','medium',NULL,NULL,2,NULL,3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,NULL,NULL,0,NOW() - INTERVAL 2 DAY),
(6,3,'Dry food · Teknaf','RL-2026-1106','food','packs',5000,3100,1500000,'in_transit','high',6,8,1,NULL,10,NULL,NULL,NULL,'5,000 dry food packs',5000,NULL,NOW()+INTERVAL 2 HOUR,NOW() - INTERVAL 8 DAY,70,'903346',1,13950,NOW() - INTERVAL 5 DAY,NULL,0,NOW() - INTERVAL 8 DAY),
(7,5,'Water purification · Kurigram','RL-2026-1107','water','boxes',3000,0,210000,'planned','high',NULL,NULL,1,NULL,13,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,NULL,NULL,0,NOW() - INTERVAL 2 DAY),
(8,8,'Blankets and clothing · Korail','RL-2026-1108','clothing','sets',900,900,270000,'completed','low',6,NULL,1,NULL,16,NULL,NULL,NULL,'900 blanket sets',900,NULL,NULL,NOW() - INTERVAL 28 DAY,100,'118820',0,NULL,NULL,NULL,0,NOW() - INTERVAL 28 DAY),
(9,1,'Water · Sadar South Zone B','RL-2026-1109','water','jerry cans',400,0,0,'accepted','critical',3,4,3,2,2,2,2,2,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,NULL,'Road is passable until noon.',400,NOW() - INTERVAL 3 HOUR),
(10,1,'Medicine · Companiganj Zone','RL-2026-1110','medicine','kits',150,0,0,'in_transit','high',3,4,3,3,3,3,1,5,'150 emergency medicine kits',150,NOW() - INTERVAL 3 HOUR,NOW()+INTERVAL 2 HOUR,NOW() - INTERVAL 3 HOUR,60,'482915',0,NULL,NULL,NULL,150,NOW() - INTERVAL 5 HOUR),
(11,1,'Food · Golapganj Zone','RL-2026-1111','food','packages',300,0,0,'assigned','medium',3,4,2,5,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,NULL,NULL,0,NOW() - INTERVAL 1 HOUR),
(12,1,'Food · Sadar North Zone A','RL-2026-1112','food','packages',600,600,0,'completed','high',3,4,3,6,1,1,NULL,2,'600 dry food packages',600,NULL,NULL,NOW() - INTERVAL 3 DAY,100,'260731',1,148,NOW() - INTERVAL 2 DAY,NULL,0,NOW() - INTERVAL 3 DAY),
(13,3,'Food · Teknaf Sadar','RL-2026-1113','food','packages',600,0,0,'assigned','medium',6,8,2,8,10,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,NULL,NULL,0,NOW() - INTERVAL 2 HOUR),
(14,1,'Medicine kits · Sadar North Zone A','RL-2026-1114','medicine','kits',80,80,0,'completed','medium',3,4,3,11,1,3,NULL,4,'80 medicine kits',80,NULL,NULL,NOW() - INTERVAL 4 DAY,100,'339018',1,40,NOW() - INTERVAL 3 DAY,NULL,0,NOW() - INTERVAL 4 DAY);
INSERT INTO zone_locks (zone_id,item_type,ngo_id,mission_id,active_key) VALUES (2,'water',3,9,1),(3,'medicine',3,10,1);
INSERT INTO mission_members (mission_id,member_id) VALUES (10,1),(10,5),(10,4),(9,2),(9,3);
INSERT INTO mission_events (mission_id,status,note,user_id,lat,lng,created_at) VALUES
(10,'accepted','Mission accepted and zone locked for medicine',3,NULL,NULL,NOW() - INTERVAL 4 HOUR),
(10,'dispatched','Mission dispatched from Sylhet DHIO Store',3,24.90,91.87,NOW() - INTERVAL 3 HOUR),
(10,'in_transit','Checkpoint 1: Sylhet bypass reached',3,24.96,91.84,NOW() - INTERVAL 140 MINUTE),
(10,'in_transit','Checkpoint 2: Osmani Bridge crossing',3,25.00,91.81,NOW() - INTERVAL 100 MINUTE),
(10,'in_transit','Road flooded ahead. Switching to boat.',3,25.04,91.78,NOW() - INTERVAL 45 MINUTE),
(9,'accepted','Mission accepted and zone locked for water',3,NULL,NULL,NOW() - INTERVAL 3 HOUR),
(3,'dispatched','Mission dispatched',3,24.90,91.87,NOW() - INTERVAL 4 DAY),
(1,'dispatched','Mission dispatched',3,24.90,91.87,NOW() - INTERVAL 5 DAY);

-- ---------- deliveries (evidence submitted by NGO teams) ----------
INSERT INTO deliveries (id,mission_id,reported_by,quantity,recipients,location,notes,gps_lat,gps_lng,evidence_at,status,reviewed_by,reviewed_at,review_note,created_at) VALUES
(1,1,3,3000,12400,'Tahirpur union council ground','Distributed in two shifts.',25.1402,91.1195,NOW() - INTERVAL 4 DAY,'verified',5,NOW() - INTERVAL 3 DAY,'Matches dispatch record.',NOW() - INTERVAL 4 DAY),
(2,1,3,4400,19800,'Dharampasha Sadar','Two boat trips.',25.0010,91.0020,NOW() - INTERVAL 2 DAY,'verified',5,NOW() - INTERVAL 1 DAY,'Verified.',NOW() - INTERVAL 2 DAY),
(3,1,3,600,2700,'Tahirpur union 3','Partial delivery, road blocked.',25.1455,91.1250,NOW() - INTERVAL 5 HOUR,'pending',NULL,NULL,NULL,NOW() - INTERVAL 5 HOUR),
(4,2,6,2400,10800,'Jamalganj bazar','Water cans handed out at 4 points.',24.8742,91.3040,NOW() - INTERVAL 3 DAY,'verified',5,NOW() - INTERVAL 2 DAY,'Verified.',NOW() - INTERVAL 3 DAY),
(5,2,6,300,1350,'Companiganj, Sunamganj','Report from the field.',25.0500,91.4000,NOW() - INTERVAL 9 HOUR,'pending',NULL,NULL,NULL,NOW() - INTERVAL 9 HOUR),
(6,3,3,2100,9450,'Sadar North door-to-door','Door-to-door distribution.',24.9512,91.8390,NOW() - INTERVAL 2 DAY,'verified',5,NOW() - INTERVAL 1 DAY,'Verified.',NOW() - INTERVAL 2 DAY),
(7,3,3,800,3600,'Sadar North Zone A','Distribution at the school shelter.',24.9488,91.8421,NOW() - INTERVAL 7 HOUR,'pending',NULL,NULL,NULL,NOW() - INTERVAL 7 HOUR),
(8,6,6,3100,13950,'Teknaf Sadar','Coastal ward distribution.',20.8601,92.3005,NOW() - INTERVAL 6 DAY,'verified',5,NOW() - INTERVAL 5 DAY,'Verified.',NOW() - INTERVAL 6 DAY),
(9,6,6,500,2250,'Ukhiya','Recipient list incomplete.',21.2610,92.1010,NOW() - INTERVAL 5 DAY,'rejected',5,NOW() - INTERVAL 4 DAY,'GPS evidence does not match the delivery zone and recipient list is incomplete.',NOW() - INTERVAL 5 DAY),
(10,8,6,900,3600,'Korail, Dhaka','Delivered to displaced families.',23.7810,90.4020,NOW() - INTERVAL 27 DAY,'verified',5,NOW() - INTERVAL 26 DAY,'Verified.',NOW() - INTERVAL 27 DAY),
(11,12,3,600,2700,'Sadar North Zone A','Delivered to displaced families.',24.9503,91.8410,NOW() - INTERVAL 3 DAY,'verified',5,NOW() - INTERVAL 2 DAY,'Verified.',NOW() - INTERVAL 3 DAY),
(12,14,3,80,180,'Sadar North Zone A','Medicine for elderly.',24.9498,91.8402,NOW() - INTERVAL 4 DAY,'verified',5,NOW() - INTERVAL 3 DAY,'Verified.',NOW() - INTERVAL 4 DAY);
INSERT INTO delivery_photos (delivery_id,path) VALUES
(1,'uploads/evidence/seed_1.png'),(1,'uploads/evidence/seed_2.png'),(1,'uploads/evidence/seed_3.png'),
(2,'uploads/evidence/seed_2.png'),(2,'uploads/evidence/seed_3.png'),(2,'uploads/evidence/seed_4.png'),
(3,'uploads/evidence/seed_1.png'),(3,'uploads/evidence/seed_3.png'),(3,'uploads/evidence/seed_4.png'),
(4,'uploads/evidence/seed_4.png'),(4,'uploads/evidence/seed_1.png'),(4,'uploads/evidence/seed_2.png'),
(5,'uploads/evidence/seed_2.png'),(5,'uploads/evidence/seed_3.png'),
(6,'uploads/evidence/seed_1.png'),(6,'uploads/evidence/seed_2.png'),(6,'uploads/evidence/seed_4.png'),
(7,'uploads/evidence/seed_3.png'),(7,'uploads/evidence/seed_4.png'),(7,'uploads/evidence/seed_1.png'),
(8,'uploads/evidence/seed_1.png'),(8,'uploads/evidence/seed_2.png'),(8,'uploads/evidence/seed_3.png'),
(9,'uploads/evidence/seed_4.png'),
(10,'uploads/evidence/seed_2.png'),(10,'uploads/evidence/seed_3.png'),(10,'uploads/evidence/seed_4.png'),
(11,'uploads/evidence/seed_1.png'),(11,'uploads/evidence/seed_3.png'),(11,'uploads/evidence/seed_4.png'),
(12,'uploads/evidence/seed_1.png'),(12,'uploads/evidence/seed_2.png'),(12,'uploads/evidence/seed_3.png');

-- ---------- SOS ----------
INSERT INTO sos_requests (id,name,phone,district_id,location,people_count,need_type,description,source,severity,reported_by,status,assigned_to,assigned_ngo_id,created_at) VALUES
(1,'Dr. Nargis Alam','01811991122',(SELECT id FROM districts WHERE name='Sylhet'),'Kaliganj Para, Sadar North Zone A',340,'medical','340 patients need insulin. No medication in 3 km radius.','public','critical',NULL,'new',NULL,NULL,NOW() - INTERVAL 5 MINUTE),
(2,'Alam Hossain','01711334455',(SELECT id FROM districts WHERE name='Sunamganj'),'Boalia village, Tahirpur',12,'rescue','12 households on rooftops, water rising. Boat rescue required.','public','critical',NULL,'assigned',7,3,NOW() - INTERVAL 50 MINUTE),
(3,'Rina Begum','01911002233',(SELECT id FROM districts WHERE name='Sunamganj'),'Tahirpur union 3',125,'water','No clean drinking water for two days.','public','high',NULL,'new',NULL,NULL,NOW() - INTERVAL 2 HOUR),
(4,'Kamal Uddin','01711001122',(SELECT id FROM districts WHERE name='Sylhet'),'Biswanath, near the bazar',100,'shelter','Homes destroyed. Families need tarpaulins.','public','high',NULL,'assigned',4,NULL,NOW() - INTERVAL 5 HOUR),
(5,'Sufia Khatun','01911004455',(SELECT id FROM districts WHERE name='Kurigram'),'Char Rajibpur',60,'food','Stranded char, boats needed for supplies.','public','high',NULL,'new',NULL,NULL,NOW() - INTERVAL 1 DAY),
(6,'Jahir Uddin','01719555566',(SELECT id FROM districts WHERE name='Cox''s Bazar'),'Teknaf Sadar',40,'shelter','Cyclone damage to roofs.','public','medium',NULL,'resolved',8,NULL,NOW() - INTERVAL 3 DAY),
(7,'Nazmul Karim','01711000004',(SELECT id FROM districts WHERE name='Sylhet'),'Sadar North Zone A',18,'flooding','Embankment overtopping near the school shelter. Water rising fast.','coordinator','critical',4,'new',NULL,NULL,NOW() - INTERVAL 25 MINUTE);

-- ---------- donations ----------
INSERT INTO donations (donor_name,email,amount,method,trx_ref,disaster_id,message,status,confirmed_by,created_at) VALUES
('Anonymous','',50000,'bkash','8A7K2L9QW1',2,'For flood families.','confirmed',5,NOW() - INTERVAL 4 DAY),
('Tanvir Ahmed','tanvir@example.com',10000,'nagad','NG29384712',1,'','confirmed',5,NOW() - INTERVAL 3 DAY),
('Dhaka Rotary Club','rotary@example.com',250000,'bank','BNK-20260711',NULL,'Wherever needed.','confirmed',1,NOW() - INTERVAL 3 DAY),
('Anonymous','',1000,'bkash','9X1B77QPZ2',3,'','confirmed',5,NOW() - INTERVAL 2 DAY),
('Mitu Rahman','mitu@example.com',5000,'rocket','RK55102938',1,'Stay safe.','confirmed',5,NOW() - INTERVAL 1 DAY),
('Anonymous','',2000,'card','CARD-77120',5,'','pending',NULL,NOW() - INTERVAL 3 HOUR),
('Sumon Das','',500,'bkash','4Q8D0M1ZZ7',2,'','pending',NULL,NOW() - INTERVAL 1 HOUR);

-- ---------- audit log + notifications ----------
INSERT INTO audit_logs (user_id,action,entity,entity_id,detail,ip,created_at) VALUES
(1,'disaster.declare','disaster',1,'Sylhet flash flood 2026','127.0.0.1',NOW() - INTERVAL 5 DAY),
(1,'disaster.declare','disaster',2,'Sunamganj haor flooding','127.0.0.1',NOW() - INTERVAL 6 DAY),
(2,'demand.match','demand',2,'DEM-2026-1198 assigned to BRAC Emergency Response Team 04','127.0.0.1',NOW() - INTERVAL 4 HOUR),
(3,'mission.accept','mission',9,'Water · Sadar South Zone B · zone locked','127.0.0.1',NOW() - INTERVAL 3 HOUR),
(3,'mission.dispatch','mission',10,'Medicine · Companiganj Zone','127.0.0.1',NOW() - INTERVAL 3 HOUR),
(5,'delivery.verify','delivery',2,'4,400 packs verified','127.0.0.1',NOW() - INTERVAL 1 DAY),
(5,'delivery.reject','delivery',9,'GPS mismatch and incomplete list','127.0.0.1',NOW() - INTERVAL 4 DAY),
(5,'donation.confirm','donation',1,'৳50,000 via bkash','127.0.0.1',NOW() - INTERVAL 4 DAY);
INSERT INTO notifications (user_id,title,body,link,category,is_read,created_at) VALUES
(2,'SOS alert (critical)','Flooding at Sadar North Zone A: embankment overtopping near the school shelter.','sos.php','sos',0,NOW() - INTERVAL 25 MINUTE),
(2,'New critical demand','DEM-2026-1201 · 800 packages of food in Sadar North Zone A','demands.php','demand',0,NOW() - INTERVAL 2 HOUR),
(3,'New mission assignment','RL-2026-1111 · Golapganj Zone: 300 food packages requested. 75 households. Access by boat only.','available.php','mission',0,NOW() - INTERVAL 1 HOUR),
(3,'Inventory alert: low stock','Baby food (infant formula) has 65 available, at or below your 80 alert level.','inventory.php','inventory',0,NOW() - INTERVAL 3 HOUR),
(4,'Mission accepted for your zone','Water · Sadar South Zone B (BRAC Emergency Response Team 04)','missions.php','mission',0,NOW() - INTERVAL 3 HOUR),
(5,'Delivery awaiting verification','RL-2026-1103 · 800 packs (High priority)','deliveries.php','verification',0,NOW() - INTERVAL 7 HOUR),
(1,'2 accounts waiting for approval','Review new registrations.','users.php','account',0,NOW() - INTERVAL 1 DAY);
SET FOREIGN_KEY_CHECKS=1;
