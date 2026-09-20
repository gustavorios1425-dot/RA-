SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS Users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Flights (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  flight_number VARCHAR(10) NOT NULL UNIQUE,
  origin VARCHAR(60) NOT NULL,
  destination VARCHAR(60) NOT NULL,
  departure DATETIME NOT NULL,
  arrival DATETIME NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  seats_total INT UNSIGNED NOT NULL DEFAULT 150,
  seats_available INT UNSIGNED NOT NULL DEFAULT 150,
  INDEX idx_route (origin, destination, departure)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Reservations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code CHAR(8) NOT NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  flight_id INT UNSIGNED NOT NULL,
  passengers TINYINT UNSIGNED NOT NULL DEFAULT 1,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('confirmada','cancelada') NOT NULL DEFAULT 'confirmada',
  payment_status ENUM('pagado','reembolsado') NOT NULL DEFAULT 'pagado',
  card_last4 CHAR(4) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES Users(id),
  CONSTRAINT fk_res_flight FOREIGN KEY (flight_id) REFERENCES Flights(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vuelos de ejemplo: 12 rutas x 30 días a partir de la fecha de creación de la BD
INSERT INTO Flights (flight_number, origin, destination, departure, arrival, price, seats_total, seats_available)
SELECT CONCAT('QZ', r.id * 100 + d.n),
       r.origin, r.destination,
       TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL d.n DAY), r.dep_time),
       TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL d.n DAY), r.dep_time) + INTERVAL r.duration MINUTE,
       r.price + (d.n MOD 5) * 120, 150, 150
FROM (
  SELECT 1 id, 'Ciudad de México' origin, 'Cancún' destination, '07:30:00' dep_time, 170 duration, 1890 price UNION ALL
  SELECT 2, 'Cancún', 'Ciudad de México', '11:10:00', 165, 1950 UNION ALL
  SELECT 3, 'Ciudad de México', 'Guadalajara', '09:00:00', 75, 1150 UNION ALL
  SELECT 4, 'Guadalajara', 'Ciudad de México', '13:45:00', 75, 1180 UNION ALL
  SELECT 5, 'Ciudad de México', 'Monterrey', '06:20:00', 105, 1420 UNION ALL
  SELECT 6, 'Monterrey', 'Ciudad de México', '18:30:00', 105, 1390 UNION ALL
  SELECT 7, 'Ciudad de México', 'Tijuana', '08:15:00', 235, 2650 UNION ALL
  SELECT 8, 'Tijuana', 'Ciudad de México', '15:00:00', 225, 2590 UNION ALL
  SELECT 9, 'Ciudad de México', 'Mérida', '10:40:00', 120, 1630 UNION ALL
  SELECT 10, 'Mérida', 'Ciudad de México', '16:20:00', 120, 1660 UNION ALL
  SELECT 11, 'Guadalajara', 'Cancún', '12:00:00', 190, 2480 UNION ALL
  SELECT 12, 'Monterrey', 'Cancún', '14:05:00', 165, 2320
) r
CROSS JOIN (
  SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
  SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL
  SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL
  SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL
  SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25 UNION ALL
  SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30
) d;
