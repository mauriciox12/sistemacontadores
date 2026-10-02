<?php
/**
 * ====================================================================
 * KONTIFY DESPACHO - Endpoint de Subida Segura de Documentos / Fotos
 * ====================================================================
 * Compatible con XAMPP Local y Namecheap Shared Hosting (cPanel)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Configurar encabezado si es petición AJAX / Fetch
$esAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' 
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($esAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        exit;
    }
    header("Location: " . BASE_URL . "/boveda.php");
    exit;
}

$clienteId = intval($_POST['cliente_id'] ?? 0);
$tipoDocumento = trim($_POST['tipo_documento'] ?? 'General');
$mesFiscal = trim($_POST['mes_fiscal'] ?? date('Y-m'));

if ($clienteId <= 0) {
    $error = 'Debe seleccionar un cliente válido.';
    if ($esAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
    header("Location: " . BASE_URL . "/boveda.php?error=" . urlencode($error));
    exit;
}

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $codigoError = $_FILES['archivo']['error'] ?? 'Sin archivo';
    $mensajes = [
        UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor.',
        UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño del formulario.',
        UPLOAD_ERR_PARTIAL => 'El archivo se subió solo parcialmente.',
        UPLOAD_ERR_NO_FILE => 'No se seleccionó ni capturó ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal en el servidor.',
        UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en el disco del servidor.',
    ];
    $error = $mensajes[$codigoError] ?? 'Error al procesar la subida del archivo.';
    
    if ($esAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
    header("Location: " . BASE_URL . "/boveda.php?cliente_id={$clienteId}&error=" . urlencode($error));
    exit;
}

$archivo = $_FILES['archivo'];
$nombreOriginal = basename($archivo['name']);
$tamanioBytes = $archivo['size'];
$tmpPath = $archivo['tmp_name'];

// 1. Validar tipos de archivo permitidos (Imágenes para cámara y PDFs)
$extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
$ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

if (!in_array($ext, $extensionesPermitidas)) {
    $error = 'Formato no permitido. Solo se aceptan imágenes (JPG, PNG, WEBP) o documentos PDF.';
    if ($esAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
    header("Location: " . BASE_URL . "/boveda.php?cliente_id={$clienteId}&error=" . urlencode($error));
    exit;
}

// 2. Validar MIME Type real
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $tmpPath);
finfo_close($finfo);

$mimesPermitidos = [
    'image/jpeg', 'image/pjpeg', 'image/png', 'image/webp',
    'application/pdf', 'application/x-pdf'
];

if (!in_array($mimeType, $mimesPermitidos)) {
    $error = 'El contenido del archivo no corresponde a una imagen o PDF válido.';
    if ($esAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
    header("Location: " . BASE_URL . "/boveda.php?cliente_id={$clienteId}&error=" . urlencode($error));
    exit;
}

// 3. Preparar directorio específico del cliente (uploads/clientes/{cliente_id}/)
$directorioCliente = UPLOAD_DIR . DIRECTORY_SEPARATOR . $clienteId;
$directorioClienteUrl = UPLOAD_URL . '/' . $clienteId;

if (!asegurarDirectorioUploads($directorioCliente)) {
    $error = 'Error de permisos: No se pudo crear o escribir en el directorio de almacenamiento en el servidor.';
    if ($esAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
    header("Location: " . BASE_URL . "/boveda.php?cliente_id={$clienteId}&error=" . urlencode($error));
    exit;
}

// 4. Nombre seguro y único para evitar colisiones
$nombreSeguro = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($tipoDocumento)) . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$rutaDestinoFisica = $directorioCliente . DIRECTORY_SEPARATOR . $nombreSeguro;
$rutaRelativaBD = 'uploads/clientes/' . $clienteId . '/' . $nombreSeguro;

// 5. Mover archivo al destino final
if (!move_uploaded_file($tmpPath, $rutaDestinoFisica)) {
    $error = 'No se pudo mover el archivo al directorio de destino.';
    if ($esAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
    header("Location: " . BASE_URL . "/boveda.php?cliente_id={$clienteId}&error=" . urlencode($error));
    exit;
}

// Establecer permisos de lectura al archivo para Namecheap / Apache
@chmod($rutaDestinoFisica, 0644);

// 6. Registrar en Base de Datos MySQL
try {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO documentos_escaneados (cliente_id, tipo_documento, mes_fiscal, nombre_original, ruta_archivo, tamanio_bytes, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$clienteId, $tipoDocumento, $mesFiscal, $nombreOriginal, $rutaRelativaBD, $tamanioBytes, $mimeType]);

    if ($esAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Documento escaneado y almacenado correctamente.',
            'ruta' => BASE_URL . '/' . $rutaRelativaBD,
            'nombre' => $nombreOriginal
        ]);
        exit;
    }

    header("Location: " . BASE_URL . "/boveda.php?cliente_id={$clienteId}&exito=1");
    exit;

} catch (PDOException $e) {
    // Si falla la BD, remover el archivo para no dejar huérfanos
    @unlink($rutaDestinoFisica);
    $error = 'Error al registrar en base de datos: ' . $e->getMessage();
    if ($esAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
    header("Location: " . BASE_URL . "/boveda.php?cliente_id={$clienteId}&error=" . urlencode($error));
    exit;
}
