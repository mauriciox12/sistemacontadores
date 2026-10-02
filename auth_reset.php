<?php
/**
 * AUTH RESET PASSWORD - KontifyApp
 * Endpoint para restablecer la contraseña con token válido.
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

$token = isset($input['token']) ? trim($input['token']) : '';
$password = isset($input['password']) ? $input['password'] : '';

// Validaciones
if (empty($token)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Token de recuperación no proporcionado']);
    exit;
}

if (empty($password) || mb_strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}

try {
    // Buscar usuario con token válido y no expirado
    $stmt = $pdo->prepare("
        SELECT id, nombre, email 
        FROM usuarios 
        WHERE reset_token = :token 
          AND reset_token_expira > NOW() 
        LIMIT 1
    ");
    $stmt->execute([':token' => $token]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'El enlace de recuperación es inválido o ha expirado. Solicita uno nuevo.']);
        exit;
    }

    // Hash de la nueva contraseña
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Actualizar contraseña y limpiar token
    $update = $pdo->prepare("
        UPDATE usuarios 
        SET password_hash = :hash, 
            reset_token = NULL, 
            reset_token_expira = NULL 
        WHERE id = :id
    ");
    $update->execute([
        ':hash' => $hash,
        ':id' => $usuario['id']
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'Contraseña restablecida exitosamente. Ya puedes iniciar sesión.'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>
