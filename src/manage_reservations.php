<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

/**
 * Servicio de Gestión de Reservas (solo reservas del usuario autenticado)
 *   GET  manage_reservations.php                              → lista
 *   POST manage_reservations.php {action:"cancel", id}        → cancela y reembolsa
 *   POST manage_reservations.php {action:"update", id, passengers} → cambia el número de pasajeros
 */
$userId = require_login();
$pdo    = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $st = $pdo->prepare(
        'SELECT r.id, r.code, r.passengers, r.total, r.status, r.payment_status, r.card_last4, r.created_at,
                f.flight_number, f.origin, f.destination, f.departure, f.arrival
         FROM Reservations r JOIN Flights f ON f.id = r.flight_id
         WHERE r.user_id = ? ORDER BY r.created_at DESC, r.id DESC'
    );
    $st->execute([$userId]);
    json_out(['ok' => true, 'reservations' => $st->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método no permitido.', 405);

$in     = input();
$action = (string) ($in['action'] ?? '');
$id     = (int) ($in['id'] ?? 0);
if ($id < 1) fail('Reserva no válida.');

$pdo->beginTransaction();
try {
    $st = $pdo->prepare(
        'SELECT r.id, r.passengers, r.status, f.id AS flight_id, f.price, f.departure, f.seats_available
         FROM Reservations r JOIN Flights f ON f.id = r.flight_id
         WHERE r.id = ? AND r.user_id = ? FOR UPDATE'
    );
    $st->execute([$id, $userId]);
    $res = $st->fetch();

    if (!$res)                              { $pdo->rollBack(); fail('No se encontró la reserva.', 404); }
    if ($res['status'] === 'cancelada')     { $pdo->rollBack(); fail('Esta reserva ya está cancelada.', 409); }
    if (strtotime($res['departure']) <= time()) { $pdo->rollBack(); fail('El vuelo ya salió; no se puede modificar.', 409); }

    if ($action === 'cancel') {
        $pdo->prepare('UPDATE Flights SET seats_available = seats_available + ? WHERE id = ?')
            ->execute([$res['passengers'], $res['flight_id']]);
        $pdo->prepare("UPDATE Reservations SET status = 'cancelada', payment_status = 'reembolsado' WHERE id = ?")
            ->execute([$id]);
        $pdo->commit();
        json_out(['ok' => true, 'message' => 'Reserva cancelada. El pago fue reembolsado.']);
    }

    if ($action === 'update') {
        $new = (int) ($in['passengers'] ?? 0);
        if ($new < 1 || $new > 9)           { $pdo->rollBack(); fail('El número de pasajeros debe estar entre 1 y 9.'); }
        $diff = $new - (int) $res['passengers'];
        if ($diff > 0 && (int) $res['seats_available'] < $diff) { $pdo->rollBack(); fail('No hay asientos suficientes para ese cambio.', 409); }

        $pdo->prepare('UPDATE Flights SET seats_available = seats_available - ? WHERE id = ?')
            ->execute([$diff, $res['flight_id']]);
        $pdo->prepare('UPDATE Reservations SET passengers = ?, total = ? WHERE id = ?')
            ->execute([$new, round((float) $res['price'] * $new, 2), $id]);
        $pdo->commit();
        json_out(['ok' => true, 'message' => 'Reserva actualizada.']);
    }

    $pdo->rollBack();
    fail('Acción no válida.', 404);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}
