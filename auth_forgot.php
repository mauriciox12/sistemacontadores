<?php
/**
 * AUTH FORGOT PASSWORD - KontifyApp
 * Endpoint para solicitar recuperación de contraseña.
 * Genera un token y envía un correo con el enlace.
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
$email = isset($input['email']) ? trim(strtolower($input['email'])) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Ingresa un correo válido']);
    exit;
}

try {
    // Buscar usuario
    $stmt = $pdo->prepare("SELECT id, nombre, email FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();

    // SIEMPRE responder con éxito por seguridad (no revelar si el email existe)
    if (!$usuario) {
        echo json_encode([
            'status' => 'ok',
            'message' => 'Si el correo está registrado, recibirás un enlace de recuperación en los próximos minutos.'
        ]);
        exit;
    }

    // Generar token único
    $token = bin2hex(random_bytes(32));
    $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Guardar token en BD
    $update = $pdo->prepare("UPDATE usuarios SET reset_token = :token, reset_token_expira = :expira WHERE id = :id");
    $update->execute([
        ':token' => $token,
        ':expira' => $expira,
        ':id' => $usuario['id']
    ]);

    // Construir enlace de restablecimiento
    $reset_link = BASE_URL . "/login.php?reset_token=" . $token;

    // Enviar correo
    $to = $usuario['email'];
    $subject = '=?UTF-8?B?' . base64_encode('Restablecer contraseña - KontifyApp') . '?=';
    
    $html_body = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0; padding:0; background-color:#0b1120; font-family:Montserrat,Arial,sans-serif;">
        <div style="max-width:600px; margin:0 auto; padding:40px 20px;">
            <div style="background:rgba(17,24,39,0.95); border:1px solid rgba(56,189,248,0.15); border-radius:16px; padding:40px; text-align:center;">
                <h1 style="font-family:Arial,sans-serif; font-size:28px; color:#ffffff; margin-bottom:8px; letter-spacing:4px;">
                    KONTIFY<span style="color:#22d3ee;">APP</span>
                </h1>
                <p style="color:#94a3b8; font-size:14px; margin-bottom:30px;">Recuperación de contraseña</p>
                
                <p style="color:#cbd5e1; font-size:15px; margin-bottom:8px;">Hola <strong>' . htmlspecialchars($usuario['nombre']) . '</strong>,</p>
                <p style="color:#94a3b8; font-size:14px; margin-bottom:30px;">
                    Recibimos una solicitud para restablecer tu contraseña. Haz clic en el botón de abajo para crear una nueva contraseña.
                </p>
                
                <a href="' . $reset_link . '" style="display:inline-block; padding:14px 40px; background:linear-gradient(135deg,#06b6d4,#2563eb); color:#ffffff; text-decoration:none; border-radius:12px; font-weight:600; font-size:15px; margin-bottom:30px;">
                    Restablecer Contraseña
                </a>
                
                <p style="color:#64748b; font-size:12px; margin-top:20px;">
                    Este enlace expira en <strong>1 hora</strong>. Si no solicitaste este cambio, ignora este correo.
                </p>
                
                <hr style="border:none; border-top:1px solid rgba(51,65,85,0.5); margin:30px 0;">
                
                <p style="color:#475569; font-size:11px;">
                    Si el botón no funciona, copia y pega esta URL en tu navegador:<br>
                    <a href="' . $reset_link . '" style="color:#22d3ee; word-break:break-all;">' . $reset_link . '</a>
                </p>
            </div>
        </div>
    </body>
    </html>';

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: KontifyApp <no-reply@nexusgestions.online>\r\n";
    $headers .= "Reply-To: no-reply@nexusgestions.online\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    mail($to, $subject, $html_body, $headers);

    echo json_encode([
        'status' => 'ok',
        'message' => 'Si el correo está registrado, recibirás un enlace de recuperación en los próximos minutos.'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>
