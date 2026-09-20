<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

/**
 * Servicio de Búsqueda de Vuelos
 *   GET search_flights.php?action=cities
 *   GET search_flights.php?origin=&destination=&date=YYYY-MM-DD&passengers=1
 */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail('Método no permitido.', 405);

if (($_GET['action'] ?? '') === 'cities') {
    $rows = db()->query(
        'SELECT origin AS city FROM Flights UNION SELECT destination FROM Flights ORDER BY city'
    )->fetchAll(PDO::FETCH_COLUMN);
    json_out(['ok' => true, 'cities' => $rows]);
}

$origin      = trim((string) ($_GET['origin'] ?? ''));
$destination = trim((string) ($_GET['destination'] ?? ''));
$date        = trim((string) ($_GET['date'] ?? ''));
$passengers  = max(1, min(9, (int) ($_GET['passengers'] ?? 1)));

$sql    = 'SELECT id, flight_number, origin, destination, departure, arrival, price, seats_available
           FROM Flights WHERE departure > NOW() AND seats_available >= :p';
$params = [':p' => $passengers];

if ($origin !== '')      { $sql .= ' AND origin = :o';      $params[':o'] = $origin; }
if ($destination !== '') { $sql .= ' AND destination = :d'; $params[':d'] = $destination; }
if ($date !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) fail('La fecha debe tener formato AAAA-MM-DD.');
    $sql .= ' AND DATE(departure) = :f';
    $params[':f'] = $date;
}
$sql .= ' ORDER BY departure ASC LIMIT 60';

$st = db()->prepare($sql);
$st->execute($params);
json_out(['ok' => true, 'flights' => $st->fetchAll()]);
