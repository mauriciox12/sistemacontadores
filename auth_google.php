<?php
session_start();
require_once 'config.php';

if (isset($_POST['credential'])) {
    $jwt_token = $_POST['credential'];
    
    // Validación directa con Google
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $jwt_token;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $payload = json_decode($response, true);
    
    if (isset($payload['email'])) {
        $google_id = $payload['sub'];
        $email = $payload['email'];
        $nombre = $payload['name'];
        $avatar = $payload['picture'];
        
        try {
            $stmt = $pdo->prepare("SELECT id, rol FROM usuarios WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();
            
            if ($usuario) {
                $update = $pdo->prepare("UPDATE usuarios SET google_id = :google_id, avatar = :avatar WHERE email = :email");
                $update->execute([':google_id' => $google_id, ':avatar' => $avatar, ':email' => $email]);
                
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['user_rol'] = $usuario['rol'];
            } else {
                $insert = $pdo->prepare("INSERT INTO usuarios (google_id, nombre, email, avatar, rol) VALUES (:google_id, :nombre, :email, :avatar, 'Contador')");
                $insert->execute([':google_id' => $google_id, ':nombre' => $nombre, ':email' => $email, ':avatar' => $avatar]);
                
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['user_rol'] = 'Contador';
            }
            
            $_SESSION['user_nombre'] = $nombre;
            $_SESSION['user_avatar'] = $avatar;
            
            // REDIRECCIÓN CORREGIDA AL DASHBOARD
            header("Location: index.php");
            exit;
            
        } catch (PDOException $e) {
            die("Error crítico de base de datos: " . $e->getMessage());
        }
    } else {
        die("Token de Google inválido.");
    }
} else {
    header("Location: login.php?error=no_token");
    exit;
}
?>