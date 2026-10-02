<?php
// ====================================================================
// KONTIFY APP — Configuration & Multi-Environment Engine
// ====================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. CONSTANTES DEL SISTEMA
$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$hostOnly = explode(':', $httpHost)[0];
$is_local = in_array($hostOnly, ['localhost', '127.0.0.1', '::1']) 
    || strpos($httpHost, 'localhost') !== false 
    || strpos($httpHost, '127.0.0.1') !== false 
    || php_sapi_name() === 'cli';

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

if ($is_local) {
    if (!defined('ENTORNO')) define('ENTORNO', 'local');
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $subDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    if (!defined('BASE_URL')) define('BASE_URL', $protocol . "://" . $httpHost . $subDir);
    
    $host = 'localhost';
    $dbname = 'kontify_db'; 
    $user = 'root';
    $pass = '';
} else {
    // Entorno Namecheap / Producción
    if (!defined('ENTORNO')) define('ENTORNO', 'produccion');
    if (!defined('BASE_URL')) define('BASE_URL', 'https://nexusgestions.online/Kontifyapp');
    
    $host = 'localhost'; 
    $dbname = 'nexujqdq_kontifyap'; 
    $user = 'nexujqdq_Mau'; 
    $pass = 'Estrella20.'; 
}

// 2. CONFIGURACIÓN CORS & HEADERS
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
}

// 3. SESIÓN POR DEFECTO PARA ENTORNO LOCAL / DEMO (Garantiza acceso inmediato)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_nombre'] = 'Mauricio Rojas';
    $_SESSION['user_email'] = 'mauricio@nexusgestions.online';
    $_SESSION['user_rol'] = 'Lead Partner / CPA';
    $_SESSION['user_plan'] = 'PRO_ENTERPRISE';
    $_SESSION['empresa_id'] = 1;
    $_SESSION['empresa_nombre'] = 'Despacho Rojas & Asociados';
    $_SESSION['tasa_bcv'] = 65.50;
}

// 4. CONEXIÓN A LA BASE DE DATOS ($pdo) CON RESILIENCIA HÍBRIDA
$pdo = null;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2
    ]);
} catch (PDOException $e) {
    if ($is_local) {
        try {
            $sqlitePath = ROOT_PATH . '/kontify_local.sqlite';
            $pdo = new PDO("sqlite:" . $sqlitePath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("PRAGMA foreign_keys = ON;");
        } catch (Exception $sqe) {
            die(json_encode(['status' => 'error', 'message' => 'Error al inicializar la base de datos local: ' . $sqe->getMessage()]));
        }
    } else {
        die(json_encode(['status' => 'error', 'message' => 'Error de conexión a la infraestructura central.']));
    }
}
?>
