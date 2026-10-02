<?php
// 1. CARGA DEL MOTOR SAAS Y SEGURIDAD
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

global $pdo;
$db = $pdo;

$mensajeAlerta = '';
$tipoAlerta = '';

// 2. PROCESAR ACTUALIZACIÓN DE DATOS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_config'])) {
    try {
        $stmt = $db->prepare("UPDATE configuracion_despacho SET nombre_despacho=?, rif=?, telefono=?, email=?, direccion=?, datos_pago=?, mensaje_cobro=? WHERE id=1");
        $stmt->execute([
            trim($_POST['nombre_despacho'] ?? ''),
            strtoupper(trim($_POST['rif'] ?? '')),
            trim($_POST['telefono'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['direccion'] ?? ''),
            trim($_POST['datos_pago'] ?? ''),
            trim($_POST['mensaje_cobro'] ?? '')
        ]);
        $mensajeAlerta = "Configuración del despacho actualizada con éxito.";
        $tipoAlerta = "success";
    } catch (Exception $e) {
        $mensajeAlerta = "Error al guardar: " . $e->getMessage();
        $tipoAlerta = "error";
    }
}

// 3. OBTENER DATOS ACTUALES
$config = [];
try {
    $config = $db->query("SELECT * FROM configuracion_despacho WHERE id=1")->fetch() ?: [];
} catch (Exception $e) {}

$pageTitle = "Configuración del Despacho | Kontify App";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Header del Módulo -->
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-k-navy tracking-tight">Configuración del Despacho</h1>
        <p class="text-sm text-k-muted mt-1">Personaliza los datos que aparecerán en tus cotizaciones y mensajes de cobro.</p>
    </div>

    <!-- Alertas -->
    <?php if (!empty($mensajeAlerta)): ?>
        <div class="mb-6 p-4 rounded-xl text-sm font-medium flex items-center gap-3 <?= $tipoAlerta === 'success' ? 'bg-k-primary-soft text-k-primary-dark border border-k-primary/30' : 'bg-k-danger-soft text-k-danger border border-k-danger/30' ?>">
            <i class="fa-solid <?= $tipoAlerta === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation' ?>"></i>
            <?= htmlspecialchars($mensajeAlerta) ?>
        </div>
    <?php endif; ?>

    <!-- Formulario Premium -->
    <form method="POST" action="" class="k-card p-6 md:p-8">
        <input type="hidden" name="guardar_config" value="1">

        <h3 class="text-sm font-bold text-k-navy mb-4 border-b border-k-border pb-2"><i class="fa-solid fa-building text-k-primary mr-2"></i> Datos Principales</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
            <div>
                <label class="k-label">Nombre del Despacho o Firma</label>
                <input type="text" name="nombre_despacho" value="<?= htmlspecialchars($config['nombre_despacho'] ?? '') ?>" class="k-input" required>
            </div>
            <div>
                <label class="k-label">RIF del Despacho</label>
                <input type="text" name="rif" value="<?= htmlspecialchars($config['rif'] ?? '') ?>" class="k-input font-mono" placeholder="J-12345678-0" required>
            </div>
            <div>
                <label class="k-label">Teléfono / WhatsApp de Contacto</label>
                <input type="text" name="telefono" value="<?= htmlspecialchars($config['telefono'] ?? '') ?>" class="k-input" required>
            </div>
            <div>
                <label class="k-label">Correo Electrónico</label>
                <input type="email" name="email" value="<?= htmlspecialchars($config['email'] ?? '') ?>" class="k-input">
            </div>
            <div class="md:col-span-2">
                <label class="k-label">Dirección Física</label>
                <input type="text" name="direccion" value="<?= htmlspecialchars($config['direccion'] ?? '') ?>" class="k-input">
            </div>
        </div>

        <h3 class="text-sm font-bold text-k-navy mb-4 border-b border-k-border pb-2"><i class="fa-solid fa-money-bill-transfer text-k-primary mr-2"></i> Cuentas y Cobranza</h3>

        <div class="grid grid-cols-1 gap-5 mb-6">
            <div>
                <label class="k-label">Cuentas Bancarias / Pago Móvil (Para Cotizaciones)</label>
                <textarea name="datos_pago" class="k-input h-24 resize-none" placeholder="Ej: Pago Móvil Banesco 0134 - 04141234567 - V12345678"><?= htmlspecialchars($config['datos_pago'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="k-label">Plantilla de Mensaje de Cobro (WhatsApp)</label>
                <textarea name="mensaje_cobro" class="k-input h-24 resize-none"><?= htmlspecialchars($config['mensaje_cobro'] ?? '') ?></textarea>
                <p class="text-[11px] text-k-muted mt-1">Variables mágicas: {cliente}, {monto_usd}, {monto_bs}, {tasa_bcv}.</p>
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-k-border">
            <button type="submit" class="k-btn k-btn-primary k-btn-lg">
                <i class="fa-solid fa-save"></i> Guardar Configuración
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>