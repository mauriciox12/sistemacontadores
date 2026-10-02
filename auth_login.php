<?php
/**
 * AUTH LOGIN - KontifyApp
 * Endpoint para login con email/contraseña.
 * Responde en JSON para compatibilidad web + APK.
 */
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// Leer JSON del body
$input = json_decode(file_get_contents('php://input'), true);

$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? $input['password'] : '';

// Validaciones básicas
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Correo y contraseña son obligatorios']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Formato de correo inválido']);
    exit;
}

try {
    // Buscar usuario por email
    $stmt = $pdo->prepare("SELECT id, nombre, email, avatar, password_hash, rol FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Correo o contraseña incorrectos']);
        exit;
    }

    // Verificar que tenga password (podría ser usuario solo-Google)
    if (empty($usuario['password_hash'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Esta cuenta usa inicio de sesión con Google. Usa el botón de Google para acceder.']);
        exit;
    }

    // Verificar contraseña
    if (!password_verify($password, $usuario['password_hash'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Correo o contraseña incorrectos']);
        exit;
    }

    // Login exitoso: crear sesión
    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['user_nombre'] = $usuario['nombre'];
    $_SESSION['user_avatar'] = $usuario['avatar'] ?: '';
    $_SESSION['user_rol'] = $usuario['rol'] ?: 'Contador';

    echo json_encode([
        'status' => 'ok',
        'message' => 'Inicio de sesión exitoso',
        'redirect' => 'index.php',
        'user' => [
            'id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email']
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>
