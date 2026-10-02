<?php
/**
 * ====================================================================
 * KONTIFY APP - API REST & GESTIÓN DE SUSCRIPCIONES SAAS
 * ====================================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = getDB();
$currentUser = obtenerUsuarioActual($db);
$currentUserId = (int)($currentUser['id'] ?? 1);

function apiSuccess($data = null, $message = "Operación exitosa", $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data
    ]);
    exit;
}

function apiError($error, $message = null, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error'   => $error,
        'message' => $message ?: $error
    ]);
    exit;
}

function getApiJsonInput() {
    $raw = file_get_contents("php://input");
    if (empty($raw)) return $_POST;
    $data = json_decode($raw, true);
    return is_array($data) ? array_merge($_POST, $data) : $_POST;
}

$resource = $_GET['resource'] ?? 'subscriptions';
$action   = $_GET['action'] ?? null;
$method   = $_SERVER['REQUEST_METHOD'];

// --------------------------------------------------------------------
// 1. RECURSO: AUTH & PERFIL
// --------------------------------------------------------------------
if ($resource === 'auth') {
    if ($action === 'me' && $method === 'GET') {
        $sub = verificarSuscripcionActiva($currentUser, $db);
        apiSuccess([
            'user' => [
                'id'          => $currentUser['id'],
                'name'        => $currentUser['name'],
                'username'    => $currentUser['username'],
                'email'       => $currentUser['email'],
                'plan_id'     => $currentUser['plan_id'] ?? 'FREE',
                'valid_until' => $currentUser['valid_until']
            ],
            'subscription' => $sub
        ]);
    }
}

// --------------------------------------------------------------------
// 2. RECURSO: SUBSCRIPTIONS (REPORTE DE PAGO)
// --------------------------------------------------------------------
if ($resource === 'subscriptions' || $action === 'report') {
    if (($action === 'report' || $action === 'crear') && $method === 'POST') {
        $input = getApiJsonInput();

        $planRequested = trim($input['plan_requested'] ?? $input['plan'] ?? 'Plan Pro');
        $amount        = floatval(str_replace(',', '.', $input['amount'] ?? '0'));
        $paymentMethod = trim($input['payment_method'] ?? 'Pago Móvil');
        $reference     = trim($input['reference'] ?? '');
        $receiptData   = trim($input['receipt_data'] ?? '');

        if (empty($reference)) {
            apiError("Referencia requerida", "Debes ingresar el número de referencia del pago bancario", 400);
        }

        if (empty($receiptData)) {
            apiError("Comprobante requerido", "Debes adjuntar la captura o comprobante del pago", 400);
        }

        if ($amount <= 0) {
            $planes = obtenerPlanesSaaS();
            $amount = floatval($planes[$planRequested]['monto'] ?? 10.00);
        }

        try {
            $stmt = $db->prepare("INSERT INTO reported_payments 
                (user_id, plan_requested, amount, payment_method, reference, receipt_data, status, created_at) 
                VALUES (:uid, :plan, :amount, :method, :ref, :receipt, 'pending', NOW())");
            
            $stmt->execute([
                ':uid'     => $currentUserId,
                ':plan'    => $planRequested,
                ':amount'  => $amount,
                ':method'  => $paymentMethod,
                ':ref'     => $reference,
                ':receipt' => $receiptData
            ]);
            $paymentId = (int)$db->lastInsertId();

            apiSuccess([
                'payment_id' => $paymentId,
                'status'     => 'pending',
                'plan'       => $planRequested
            ], "¡Comprobante recibido con éxito! El administrador verificará tu pago a la brevedad para renovar tu suscripción.");
        } catch (PDOException $e) {
            apiError("Database error", "Error al registrar el reporte de pago: " . $e->getMessage(), 500);
        }
    }

    if ($action === 'status' && $method === 'GET') {
        try {
            $stmt = $db->prepare("SELECT id, plan_requested, amount, payment_method, reference, status, created_at 
                FROM reported_payments WHERE user_id = :uid ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([':uid' => $currentUserId]);
            apiSuccess($stmt->fetchAll());
        } catch (PDOException $e) {
            apiError("Database error", $e->getMessage(), 500);
        }
    }
}

// --------------------------------------------------------------------
// 3. RECURSO: ADMIN (PAGOS & APROBACIÓN)
// --------------------------------------------------------------------
if ($resource === 'admin' || in_array($action, ['payments', 'approve', 'reject'])) {
    $esAdmin = ($currentUserId === 1 || strtolower($currentUser['username'] ?? '') === 'mauricio');
    if (!$esAdmin) {
        apiError("Acceso denegado", "Solo el administrador (Mauricio) puede gestionar pagos.", 403);
    }

    // Listar pagos pendientes
    if ($action === 'payments' && $method === 'GET') {
        try {
            $stmt = $db->prepare("
                SELECT p.id, p.user_id, p.plan_requested, p.amount, p.payment_method, p.reference, p.receipt_data, p.status, p.created_at,
                       u.name as user_name, u.username, u.email, u.valid_until, u.plan_id as current_plan
                FROM reported_payments p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.status = 'pending'
                ORDER BY p.created_at DESC
            ");
            $stmt->execute();
            apiSuccess($stmt->fetchAll(), "Pagos pendientes obtenidos");
        } catch (PDOException $e) {
            apiError("Database error", $e->getMessage(), 500);
        }
    }

    // Aprobar pago y sumar 1 mes
    if ($action === 'approve' && $method === 'POST') {
        $input = getApiJsonInput();
        $paymentId = (int)($input['payment_id'] ?? $input['id'] ?? 0);

        if ($paymentId <= 0) {
            apiError("ID requerido", "payment_id inválido o no especificado", 400);
        }

        try {
            $stmt = $db->prepare("SELECT * FROM reported_payments WHERE id = :pid LIMIT 1");
            $stmt->execute([':pid' => $paymentId]);
            $payment = $stmt->fetch();

            if (!$payment) {
                apiError("No encontrado", "El reporte de pago #{$paymentId} no existe", 404);
            }

            $targetUserId  = (int)$payment['user_id'];
            $planRequested = $payment['plan_requested'];

            $db->beginTransaction();

            // 1. Marcar pago como aprobado
            $updPayment = $db->prepare("UPDATE reported_payments SET status = 'approved' WHERE id = :pid");
            $updPayment->execute([':pid' => $paymentId]);

            // 2. Renovar vigencia en users: plan_id = plan_requested, valid_until = DATE_ADD(NOW(), INTERVAL 1 MONTH)
            $updUser = $db->prepare("UPDATE users SET plan_id = :plan, valid_until = DATE_ADD(NOW(), INTERVAL 1 MONTH) WHERE id = :uid");
            $updUser->execute([
                ':plan' => $planRequested,
                ':uid'  => $targetUserId
            ]);

            $db->commit();

            apiSuccess([
                'payment_id' => $paymentId,
                'user_id'    => $targetUserId,
                'plan_id'    => $planRequested
            ], "Pago aprobado con éxito. La suscripción del usuario ha sido renovada por 1 mes.");
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            apiError("Database error", "Error al aprobar pago: " . $e->getMessage(), 500);
        }
    }

    // Rechazar pago
    if ($action === 'reject' && $method === 'POST') {
        $input = getApiJsonInput();
        $paymentId = (int)($input['payment_id'] ?? $input['id'] ?? 0);

        if ($paymentId <= 0) apiError("ID requerido", "payment_id inválido", 400);

        try {
            $upd = $db->prepare("UPDATE reported_payments SET status = 'rejected' WHERE id = :pid");
            $upd->execute([':pid' => $paymentId]);
            apiSuccess(['payment_id' => $paymentId], "Reporte de pago rechazado.");
        } catch (PDOException $e) {
            apiError("Database error", $e->getMessage(), 500);
        }
    }
}

// --------------------------------------------------------------------
// 4. RECURSO: CLIENTS (SERVICIOS Y LISTADO)
// --------------------------------------------------------------------
if ($resource === 'clients') {
    if ($action === 'services' && $method === 'GET') {
        $clientId = intval($_GET['client_id'] ?? 0);
        if ($clientId <= 0) apiError("ID requerido", "client_id inválido", 400);

        try {
            $stmt = $db->prepare("SELECT id, nombre_servicio, tipo, monto_usd, estatus FROM servicios_cliente WHERE cliente_id = ? AND estatus = 'activo'");
            $stmt->execute([$clientId]);
            $servicios = $stmt->fetchAll();

            // Si no tiene servicios activos asignados, devolver catálogo base
            if (empty($servicios)) {
                $stmtBase = $db->query("SELECT id, nombre as nombre_servicio, tipo, precio_sugerido_usd as monto_usd, 'activo' as estatus FROM servicios WHERE activo = 1");
                $servicios = $stmtBase->fetchAll();
            }

            apiSuccess($servicios, "Servicios obtenidos");
        } catch (PDOException $e) {
            apiError("Database error", $e->getMessage(), 500);
        }
    }

    if ($action === 'list' && $method === 'GET') {
        try {
            $stmt = $db->query("SELECT id, razon_social, rif, honorarios_usd, estatus FROM clientes ORDER BY razon_social ASC");
            apiSuccess($stmt->fetchAll(), "Clientes obtenidos");
        } catch (PDOException $e) {
            apiError("Database error", $e->getMessage(), 500);
        }
    }
}

// --------------------------------------------------------------------
// 5. RECURSO: INCOME (INGRESOS Y HONORARIOS)
// --------------------------------------------------------------------
if ($resource === 'income') {
    if ($action === 'list' && $method === 'GET') {
        $cid = !empty($_GET['client_id']) ? intval($_GET['client_id']) : null;
        try {
            if ($cid) {
                $stmt = $db->prepare("SELECT * FROM ingresos WHERE cliente_id = ? ORDER BY fecha DESC LIMIT 50");
                $stmt->execute([$cid]);
            } else {
                $stmt = $db->query("SELECT i.*, c.razon_social as cliente_nombre FROM ingresos i INNER JOIN clientes c ON i.cliente_id = c.id ORDER BY i.fecha DESC LIMIT 50");
            }
            apiSuccess($stmt->fetchAll(), "Ingresos obtenidos");
        } catch (PDOException $e) {
            apiError("Database error", $e->getMessage(), 500);
        }
    }

    if ($action === 'create' && $method === 'POST') {
        $input = getApiJsonInput();
        $clienteId = intval($input['cliente_id'] ?? 0);
        $nombreServicio = trim($input['nombre_servicio'] ?? 'Honorarios Profesionales');
        $monto = floatval(str_replace(',', '.', $input['monto'] ?? '0'));
        $moneda = in_array($input['moneda'] ?? '', ['USD', 'VES']) ? $input['moneda'] : 'USD';
        $tasaCambio = floatval($input['tasa_cambio'] ?? ($_SESSION['tasa_bcv'] ?? 65.50));
        $cuentaReceptora = trim($input['cuenta_receptora'] ?? 'Banco Banesco');
        $metodoPago = trim($input['metodo_pago'] ?? 'Pago Móvil');
        $referencia = trim($input['referencia'] ?? '');
        $tipoIngreso = trim($input['tipo_ingreso'] ?? 'recurrente');
        $fecha = trim($input['fecha'] ?? date('Y-m-d'));

        if ($clienteId <= 0 || $monto <= 0) {
            apiError("Datos incompletos", "Cliente y monto mayor a 0 son obligatorios", 400);
        }

        $montoUsd = ($moneda === 'USD') ? $monto : ($tasaCambio > 0 ? ($monto / $tasaCambio) : 0);
        $montoEquiv = ($moneda === 'USD') ? ($monto * $tasaCambio) : $monto;

        try {
            $stmt = $db->prepare("INSERT INTO ingresos 
                (cliente_id, nombre_servicio, tipo_ingreso, fecha, monto, moneda, tasa_cambio, monto_equivalente, monto_usd, cuenta_receptora, metodo_pago, referencia, estado, creado_el)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'cobrado', NOW())");
            $stmt->execute([$clienteId, $nombreServicio, $tipoIngreso, $fecha, $monto, $moneda, $tasaCambio, $montoEquiv, $montoUsd, $cuentaReceptora, $metodoPago, $referencia]);
            apiSuccess(['id' => $db->lastInsertId()], "Ingreso registrado exitosamente", 201);
        } catch (PDOException $e) {
            apiError("Database error", $e->getMessage(), 500);
        }
    }
}

apiError("Recurso no encontrado", "La ruta o acción solicitada no existe en la API", 404);

