<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

/**
 * Servicio de Autenticación
 *   POST auth.php?action=register  {name, email, password}
 *   POST auth.php?action=login     {email, password}
 *   POST auth.php?action=logout
 *   GET  auth.php?action=me
 */
start_session();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método no permitido.', 405);
        $in       = input();
        $name     = trim((string) ($in['name'] ?? ''));
        $email    = strtolower(trim((string) ($in['email'] ?? '')));
        $password = (string) ($in['password'] ?? '');

        if ($name === '' || strlen($name) > 100)            fail('Escribe tu nombre (máximo 100 caracteres).');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))         fail('El correo electrónico no es válido.');
        if (strlen($password) < 6)                              fail('La contraseña debe tener al menos 6 caracteres.');

        $st = db()->prepare('SELECT id FROM Users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) fail('Ya existe una cuenta con ese correo.', 409);

        $st = db()->prepare('INSERT INTO Users (name, email, password_hash) VALUES (?, ?, ?)');
        $st->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        json_out(['ok' => true, 'message' => 'Cuenta creada. Ya puedes iniciar sesión.'], 201);

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método no permitido.', 405);
        $in       = input();
        $email    = strtolower(trim((string) ($in['email'] ?? '')));
        $password = (string) ($in['password'] ?? '');

        $st = db()->prepare('SELECT id, name, email, password_hash FROM Users WHERE email = ?');
        $st->execute([$email]);
        $user = $st->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            fail('Correo o contraseña incorrectos.', 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['name']    = $user['name'];
        json_out(['ok' => true, 'user' => ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email']]]);

    case 'logout':
        $_SESSION = [];
        session_destroy();
        json_out(['ok' => true]);

    case 'me':
        if (empty($_SESSION['user_id'])) json_out(['ok' => true, 'user' => null]);
        json_out(['ok' => true, 'user' => ['id' => (int) $_SESSION['user_id'], 'name' => $_SESSION['name']]]);

    default:
        fail('Acción no válida.', 404);
}
