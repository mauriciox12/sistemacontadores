<?php
/**
 * AUTH REGISTER - KontifyApp
 * Endpoint para registro de cuenta nueva con email/contraseña.
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

$nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
$email = isset($input['email']) ? trim(strtolower($input['email'])) : '';
$password = isset($input['password']) ? $input['password'] : '';

// Validaciones
$errors = [];

if (empty($nombre) || mb_strlen($nombre) < 3) {
    $errors[] = 'El nombre debe tener al menos 3 caracteres';
}

if (empty($email)) {
    $errors[] = 'El correo es obligatorio';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Formato de correo inválido';
}

if (empty($password)) {
    $errors[] = 'La contraseña es obligatoria';
} elseif (mb_strlen($password) < 8) {
    $errors[] = 'La contraseña debe tener al menos 8 caracteres';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => implode('. ', $errors)]);
    exit;
}

try {
    // Verificar si el email ya existe
    $stmt = $pdo->prepare("SELECT id, password_hash, google_id FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $existente = $stmt->fetch();

    if ($existente) {
        // Si existe pero solo con Google (sin password), permitir agregar password
        if (!empty($existente['google_id']) && empty($existente['password_hash'])) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $update = $pdo->prepare("UPDATE usuarios SET password_hash = :hash, nombre = :nombre WHERE id = :id");
            $update->execute([':hash' => $hash, ':nombre' => $nombre, ':id' => $existente['id']]);

            // Crear sesión
            $_SESSION['user_id'] = $existente['id'];
            $_SESSION['user_nombre'] = $nombre;
            $_SESSION['user_avatar'] = '';
            $_SESSION['user_rol'] = 'Contador';

            echo json_encode([
                'status' => 'ok',
                'message' => 'Contraseña configurada exitosamente. Ahora puedes acceder con email y contraseña.',
                'redirect' => 'index.php'
            ]);
            exit;
        }

        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Ya existe una cuenta con este correo. Intenta iniciar sesión.']);
        exit;
    }

    // Hash de la contraseña
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Insertar nuevo usuario
    $insert = $pdo->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (:nombre, :email, :hash, 'Contador')");
    $insert->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':hash' => $hash
    ]);

    $newId = $pdo->lastInsertId();

    // Crear sesión automáticamente (login inmediato tras registro)
    $_SESSION['user_id'] = $newId;
    $_SESSION['user_nombre'] = $nombre;
    $_SESSION['user_avatar'] = '';
    $_SESSION['user_rol'] = 'Contador';

    echo json_encode([
        'status' => 'ok',
        'message' => '¡Cuenta creada exitosamente!',
        'redirect' => 'index.php',
        'user' => [
            'id' => $newId,
            'nombre' => $nombre,
            'email' => $email
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>
