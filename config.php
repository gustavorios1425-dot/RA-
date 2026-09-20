<?php
declare(strict_types=1);

/**
 * config.php — utilidades compartidas por los cuatro servicios web.
 * Conexión PDO a MySQL, respuestas JSON y manejo de sesión.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: 'db';
        $name = getenv('DB_NAME') ?: 'flights_db';
        $user = getenv('DB_USER') ?: 'flights_user';
        $pass = getenv('DB_PASS') ?: 'flights_pass';
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $code = 400): never
{
    json_out(['ok' => false, 'error' => $message], $code);
}

/** Lee el cuerpo JSON (o form-data como respaldo). */
function input(): array
{
    $json = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($json) ? $json : $_POST;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
        session_start();
    }
}

function require_login(): int
{
    start_session();
    if (empty($_SESSION['user_id'])) {
        fail('Debes iniciar sesión para continuar.', 401);
    }
    return (int) $_SESSION['user_id'];
}

set_exception_handler(function (Throwable $e): void {
    error_log($e->getMessage());
    fail('Error interno del servidor. Intenta de nuevo más tarde.', 500);
});
