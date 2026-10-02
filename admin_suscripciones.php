<?php
// 1. CARGA DEL MOTOR SAAS
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// 2. VERIFICAR SESIÓN BÁSICA
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

global $pdo;
$db = $pdo;

// 3. BARRERA DE SEGURIDAD ESTRICTA (Solo Admin)
$nombreUsuario = strtolower($_SESSION['user_nombre'] ?? '');
$esAdmin = ((int)$_SESSION['user_id'] === 1 || strpos($nombreUsuario, 'mauricio') !== false);

if (!$esAdmin) {
    // Si un contador normal intenta entrar aquí, lo expulsamos al inicio
    header("Location: index.php");
    exit;
}

$pageTitle = "Panel Admin - Suscripciones | Kontify App";
$mensajeAlerta = '';
$tipoAlerta = '';

// 4. LÓGICA PARA APROBAR / RECHAZAR PAGOS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_pago'])) {
    $pago_id = (int)$_POST['pago_id'];
    $nuevo_estado = $_POST['nuevo_estado']; // 'approved' o 'rejected'
    $plan_requested = $_POST['plan_requested'];
    $user_id = (int)$_POST['user_id_pago'];

    try {
        // Actualizar el estado del pago
        $stmt = $db->prepare("UPDATE reported_payments SET status = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $pago_id]);

        // Si se aprueba, actualizamos el plan del usuario
        if ($nuevo_estado === 'approved') {
            // Le damos 30 días de acceso desde hoy
            $updateUser = $db->prepare("UPDATE users SET plan_id = ?, valid_until = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?");
            $updateUser->execute([strtoupper($plan_requested), $user_id]);
            $mensajeAlerta = "Pago aprobado. Se ha activado el plan {$plan_requested} por 30 días.";
            $tipoAlerta = 'success';
        } else {
            $mensajeAlerta = "El pago ha sido marcado como rechazado.";
            $tipoAlerta = 'warning';
        }
    } catch (Exception $e) {
        $mensajeAlerta = "Error en la base de datos: " . $e->getMessage();
        $tipoAlerta = 'error';
    }
}

// 5. CONSULTAR TODOS LOS PAGOS REPORTADOS
$pagos = [];
try {
    $stmt = $db->query("
        SELECT p.*, u.name as usuario_nombre, u.email as usuario_email 
        FROM reported_payments p 
        LEFT JOIN users u ON p.user_id = u.id 
        ORDER BY p.created_at DESC
    ");
    if ($stmt) $pagos = $stmt->fetchAll();
} catch (Exception $e) {
    $mensajeAlerta = "Aviso: No se pudo leer la tabla de pagos reportados. " . $e->getMessage();
    $tipoAlerta = 'error';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <!-- Encabezado de Administración -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-[10px] font-bold tracking-widest text-amber-700 uppercase bg-amber-100 border border-amber-200 px-2 py-0.5 rounded-md"><i class="fa-solid fa-crown mr-1"></i> Control Maestro</span>
            </div>
            <h1 class="text-2xl lg:text-3xl font-extrabold text-k-navy tracking-tight">Gestión de Suscripciones</h1>
            <p class="text-sm text-k-muted mt-1">Aprueba o rechaza los pagos reportados por los contadores para activar sus planes.</p>
        </div>
    </div>

    <!-- Alertas -->
    <?php if (!empty($mensajeAlerta)): ?>
        <div class="mb-6 p-4 rounded-xl text-sm font-medium flex items-center gap-3 shadow-sm <?= $tipoAlerta === 'success' ? 'bg-k-primary-soft text-k-primary-dark border border-k-primary/30' : ($tipoAlerta === 'warning' ? 'bg-k-warning-soft text-k-warning border border-k-warning/30' : 'bg-k-danger-soft text-k-danger border border-k-danger/30') ?>">
            <i class="fa-solid <?= $tipoAlerta === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation' ?>"></i>
            <?= htmlspecialchars($mensajeAlerta) ?>
        </div>
    <?php endif; ?>

    <!-- Tabla de Pagos -->
    <div class="k-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="k-table">
                <thead class="bg-k-bg">
                    <tr>
                        <th>Fecha</th>
                        <th>Despacho / Contador</th>
                        <th>Plan Solicitado</th>
                        <th>Monto / Referencia</th>
                        <th>Estatus</th>
                        <th class="text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pagos)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center">
                                <i class="fa-solid fa-inbox text-3xl text-k-border mb-3 block"></i>
                                <span class="text-sm font-bold text-k-navy">No hay pagos reportados en la plataforma.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pagos as $pago): ?>
                            <tr>
                                <td class="whitespace-nowrap text-k-muted text-xs">
                                    <?= date('d/m/Y h:i A', strtotime($pago['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="font-bold text-k-navy"><?= htmlspecialchars($pago['usuario_nombre'] ?? 'Usuario Eliminado') ?></div>
                                    <div class="text-[10px] text-k-muted"><?= htmlspecialchars($pago['usuario_email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="font-extrabold text-purple-700 bg-purple-50 px-2 py-1 rounded-md text-[11px] border border-purple-200">
                                        <?= htmlspecialchars($pago['plan_requested']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="font-bold text-emerald-600">$<?= number_format($pago['amount'], 2, ',', '.') ?></div>
                                    <div class="text-[10px] font-mono text-k-muted">Ref: <?= htmlspecialchars($pago['reference']) ?></div>
                                </td>
                                <td>
                                    <?php if ($pago['status'] === 'pending'): ?>
                                        <span class="k-badge k-badge-warning"><span class="k-badge-dot bg-k-warning animate-pulse mr-1"></span> Pendiente</span>
                                    <?php elseif ($pago['status'] === 'approved'): ?>
                                        <span class="k-badge k-badge-success"><i class="fa-solid fa-check mr-1"></i> Aprobado</span>
                                    <?php else: ?>
                                        <span class="k-badge k-badge-danger"><i class="fa-solid fa-xmark mr-1"></i> Rechazado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?php if ($pago['status'] === 'pending'): ?>
                                        <div class="flex items-center justify-end gap-2">
                                            <!-- Botón Aprobar -->
                                            <form method="POST" action="" class="inline">
                                                <input type="hidden" name="accion_pago" value="1">
                                                <input type="hidden" name="pago_id" value="<?= $pago['id'] ?>">
                                                <input type="hidden" name="user_id_pago" value="<?= $pago['user_id'] ?>">
                                                <input type="hidden" name="plan_requested" value="<?= htmlspecialchars($pago['plan_requested']) ?>">
                                                <input type="hidden" name="nuevo_estado" value="approved">
                                                <button type="submit" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white border border-emerald-200 transition-colors flex items-center justify-center" title="Aprobar Pago y Activar Plan">
                                                    <i class="fa-solid fa-check text-xs"></i>
                                                </button>
                                            </form>

                                            <!-- Botón Rechazar -->
                                            <form method="POST" action="" class="inline" onsubmit="return confirm('¿Seguro que deseas rechazar este pago?');">
                                                <input type="hidden" name="accion_pago" value="1">
                                                <input type="hidden" name="pago_id" value="<?= $pago['id'] ?>">
                                                <input type="hidden" name="user_id_pago" value="<?= $pago['user_id'] ?>">
                                                <input type="hidden" name="plan_requested" value="<?= htmlspecialchars($pago['plan_requested']) ?>">
                                                <input type="hidden" name="nuevo_estado" value="rejected">
                                                <button type="submit" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white border border-rose-200 transition-colors flex items-center justify-center" title="Rechazar Pago">
                                                    <i class="fa-solid fa-xmark text-xs"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-[10px] text-k-muted italic">Procesado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>