<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

/**
 * Servicio de Reserva de Vuelos (reserva + pago simulado)
 *   POST reserve_flight.php {flight_id, passengers, card_name, card_number, card_exp, card_cvv}
 *
 * El pago es una simulación con fines académicos: se valida la tarjeta (Luhn y vigencia),
 * pero solo se guardan los últimos 4 dígitos; nunca el número completo ni el CVV.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método no permitido.', 405);
$userId = require_login();
$in     = input();

$flightId   = (int) ($in['flight_id'] ?? 0);
$passengers = (int) ($in['passengers'] ?? 1);
$cardName   = trim((string) ($in['card_name'] ?? ''));
$cardNumber = preg_replace('/\D/', '', (string) ($in['card_number'] ?? ''));
$cardExp    = trim((string) ($in['card_exp'] ?? ''));
$cardCvv    = preg_replace('/\D/', '', (string) ($in['card_cvv'] ?? ''));

if ($flightId < 1)                         fail('Selecciona un vuelo.');
if ($passengers < 1 || $passengers > 9)    fail('El número de pasajeros debe estar entre 1 y 9.');
if ($cardName === '')                      fail('Escribe el nombre que aparece en la tarjeta.');

function luhn_ok(string $n): bool
{
    $len = strlen($n);
    if ($len < 13 || $len > 19) return false;
    $sum = 0;
    for ($i = 0; $i < $len; $i++) {
        $d = (int) $n[$len - 1 - $i];
        if ($i % 2 === 1) { $d *= 2; if ($d > 9) $d -= 9; }
        $sum += $d;
    }
    return $sum % 10 === 0;
}
if (!luhn_ok($cardNumber))                 fail('El número de tarjeta no es válido.');
if (strlen($cardCvv) < 3 || strlen($cardCvv) > 4) fail('El CVV no es válido.');
if (!preg_match('#^(0[1-9]|1[0-2])/(\d{2})$#', $cardExp, $m)) fail('La vigencia debe tener formato MM/AA.');
$expEnd = new DateTimeImmutable(sprintf('20%s-%s-01', $m[2], $m[1]));
if ($expEnd->modify('last day of this month 23:59:59') < new DateTimeImmutable('now')) fail('La tarjeta está vencida.');

$pdo = db();
$pdo->beginTransaction();
try {
    // Bloquea la fila del vuelo para evitar sobreventa de asientos.
    $st = $pdo->prepare('SELECT id, price, seats_available, departure FROM Flights WHERE id = ? FOR UPDATE');
    $st->execute([$flightId]);
    $flight = $st->fetch();

    if (!$flight)                                        { $pdo->rollBack(); fail('El vuelo no existe.', 404); }
    if (strtotime($flight['departure']) <= time())       { $pdo->rollBack(); fail('Ese vuelo ya salió.'); }
    if ((int) $flight['seats_available'] < $passengers)  { $pdo->rollBack(); fail('No hay asientos suficientes en este vuelo.', 409); }

    $total = round((float) $flight['price'] * $passengers, 2);
    $code  = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

    $pdo->prepare('UPDATE Flights SET seats_available = seats_available - ? WHERE id = ?')
        ->execute([$passengers, $flightId]);
    $pdo->prepare('INSERT INTO Reservations (code, user_id, flight_id, passengers, total, card_last4)
                   VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$code, $userId, $flightId, $passengers, $total, substr($cardNumber, -4)]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

json_out([
    'ok'          => true,
    'message'     => 'Reserva confirmada y pago aprobado.',
    'reservation' => ['code' => $code, 'passengers' => $passengers, 'total' => $total],
], 201);
