<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$db = getDB();
$clienteId = intval($_GET['id'] ?? 0);

if ($clienteId <= 0) {
    header("Location: " . BASE_URL . "/crm.php");
    exit;
}

$mensajeAlerta = '';
$tipoAlerta = '';
$tasaBcvActual = $_SESSION['tasa_bcv'] ?? 65.50;

// PROCESAR ACCIONES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // 1. ACTUALIZAR ESTADO DEL CLIENTE
    if ($accion === 'actualizar_estatus') {
        $nuevoEstatus = $_POST['nuevo_estatus'] ?? 'al dia';
        $db->prepare("UPDATE clientes SET estatus = ? WHERE id = ?")->execute([$nuevoEstatus, $clienteId]);
        $mensajeAlerta = "Estado del cliente actualizado a: <strong>" . strtoupper($nuevoEstatus) . "</strong>";
        $tipoAlerta = 'success';
    }

    // 2. ACTUALIZAR DATOS FISCALES
    elseif ($accion === 'actualizar_fiscal') {
        $razon = trim($_POST['razon_social'] ?? '');
        $rif = strtoupper(trim($_POST['rif'] ?? ''));
        $tipoCont = $_POST['tipo_contribuyente'] ?? 'Ordinario';
        $tel = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $dir = trim($_POST['direccion'] ?? '');
        $hon = floatval(str_replace(',', '.', $_POST['honorarios_usd'] ?? '0'));

        $stmtU = $db->prepare("UPDATE clientes SET razon_social = ?, rif = ?, tipo_contribuyente = ?, telefono = ?, email = ?, direccion = ?, honorarios_usd = ? WHERE id = ?");
        $stmtU->execute([$razon, $rif, $tipoCont, $tel, $email, $dir, $hon, $clienteId]);
        $mensajeAlerta = "Datos fiscales actualizados con éxito.";
        $tipoAlerta = 'success';
    }

    // 3. REGISTRAR PAGO
    elseif ($accion === 'registrar_pago') {
        $montoUsd = floatval(str_replace(',', '.', $_POST['monto_usd'] ?? '0'));
        $metodo = trim($_POST['metodo_pago'] ?? 'Pago Móvil');
        $referencia = trim($_POST['referencia'] ?? '');
        $fecha = $_POST['fecha_pago'] ?? date('Y-m-d');
        $mesPeriodo = $_POST['mes_periodo'] ?? date('Y-m');
        $observacion = trim($_POST['observaciones'] ?? 'Cobro de honorario contable');
        $tasa = floatval(str_replace(',', '.', $_POST['tasa_cambio'] ?? $tasaBcvActual));
        if ($tasa <= 0) $tasa = $tasaBcvActual;
        $montoBs = $montoUsd * $tasa;

        if ($montoUsd > 0) {
            $db->beginTransaction();
            try {
                // Pagos honorarios
                $stmtP = $db->prepare("INSERT INTO pagos_honorarios (cliente_id, mes_periodo, monto_usd, monto_bs, tasa_bcv, metodo_pago, referencia, fecha_pago, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtP->execute([$clienteId, $mesPeriodo, $montoUsd, $montoBs, $tasa, $metodo, $referencia, $fecha, $observacion]);
                $pagoId = $db->lastInsertId();

                // Ingresos
                $stmtI = $db->prepare("INSERT INTO ingresos (empresa_id, cliente_id, nombre_servicio, tipo_ingreso, fecha, monto, moneda, tasa_cambio, monto_equivalente, monto_usd, cuenta_receptora, metodo_pago, referencia, descripcion, pago_honorario_id, estado)
                    VALUES (1, ?, 'Honorario Mensual Contable', 'recurrente', ?, ?, 'USD', ?, ?, ?, 'Banco Banesco', ?, ?, ?, ?, 'cobrado')");
                $stmtI->execute([$clienteId, $fecha, $montoUsd, $tasa, $montoBs, $montoUsd, $metodo, $referencia, $observacion, $pagoId]);

                // Actualizar a 'al dia'
                if (isset($_POST['marcar_al_dia'])) {
                    $db->prepare("UPDATE clientes SET estatus = 'al dia' WHERE id = ?")->execute([$clienteId]);
                }

                // Bitácora
                $db->prepare("INSERT INTO comunicaciones_cliente (cliente_id, tipo, asunto, detalle) VALUES (?, 'WhatsApp', 'Cobro Registrado', ?)")
                   ->execute([$clienteId, "Pago recibido por \${$montoUsd} USD vía {$metodo}. Ref: {$referencia}."]);

                $db->commit();
                $mensajeAlerta = "¡Cobro de <strong>\${$montoUsd} USD</strong> registrado y conciliado en Ingresos!";
                $tipoAlerta = 'success';
            } catch (Exception $e) {
                $db->rollBack();
                $mensajeAlerta = "Error al registrar pago: " . $e->getMessage();
                $tipoAlerta = 'error';
            }
        }
    }

    // 4. AGREGAR SERVICIO
    elseif ($accion === 'agregar_servicio') {
        $nombreServicio = trim($_POST['nombre_servicio'] ?? '');
        $tipoServicio = $_POST['tipo_servicio'] ?? 'recurrente';
        $montoServicio = floatval(str_replace(',', '.', $_POST['monto_usd'] ?? '0'));
        $obs = trim($_POST['observaciones'] ?? '');

        if (!empty($nombreServicio) && $montoServicio > 0) {
            $stmtS = $db->prepare("INSERT INTO servicios_cliente (cliente_id, nombre_servicio, tipo, monto_usd, estatus, observaciones) VALUES (?, ?, ?, ?, 'activo', ?)");
            $stmtS->execute([$clienteId, $nombreServicio, $tipoServicio, $montoServicio, $obs]);
            
            // Recalcular honorario base si es recurrente
            if ($tipoServicio === 'recurrente') {
                $db->prepare("UPDATE clientes SET honorarios_usd = (SELECT COALESCE(SUM(monto_usd), 0) FROM servicios_cliente WHERE cliente_id = ? AND tipo = 'recurrente' AND estatus = 'activo') WHERE id = ?")
                   ->execute([$clienteId, $clienteId]);
            }

            $mensajeAlerta = "Servicio <strong>" . htmlspecialchars($nombreServicio) . "</strong> agregado exitosamente.";
            $tipoAlerta = 'success';
        }
    }

    // 5. REGISTRAR NOTA EN BITÁCORA
    elseif ($accion === 'agregar_nota') {
        $tipoNota = $_POST['tipo_comunicacion'] ?? 'Nota Interna';
        $asunto = trim($_POST['asunto'] ?? 'Nota de seguimiento');
        $detalle = trim($_POST['detalle'] ?? '');

        if (!empty($detalle)) {
            $stmtN = $db->prepare("INSERT INTO comunicaciones_cliente (cliente_id, tipo, asunto, detalle) VALUES (?, ?, ?, ?)");
            $stmtN->execute([$clienteId, $tipoNota, $asunto, $detalle]);
            $mensajeAlerta = "Nota registrada en la bitácora del cliente.";
            $tipoAlerta = 'success';
        }
    }

    // 6. SUBIR DOCUMENTO
    elseif ($accion === 'subir_documento' && isset($_FILES['archivo_doc'])) {
        $tipoDoc = $_POST['tipo_documento'] ?? 'Documento Legal';
        $mesFiscal = $_POST['mes_fiscal'] ?? date('Y-m');
        $file = $_FILES['archivo_doc'];

        if ($file['error'] === UPLOAD_ERR_OK) {
            $uploadDir = ROOT_PATH . "/uploads/clientes/{$clienteId}";
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

            $safeName = time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file['name']);
            $destPath = $uploadDir . "/" . $safeName;
            $relPath = "uploads/clientes/{$clienteId}/" . $safeName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $db->prepare("INSERT INTO documentos_escaneados (cliente_id, tipo_documento, mes_fiscal, nombre_original, ruta_archivo, tamanio_bytes, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$clienteId, $tipoDoc, $mesFiscal, $file['name'], $relPath, $file['size'], $file['type']]);
                
                $mensajeAlerta = "Documento <strong>" . htmlspecialchars($file['name']) . "</strong> archivado en bóveda.";
                $tipoAlerta = 'success';
            }
        }
    }
}

// Consultar datos actualizados del cliente
$stmtCli = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmtCli->execute([$clienteId]);
$cliente = $stmtCli->fetch();

if (!$cliente) {
    header("Location: " . BASE_URL . "/crm.php");
    exit;
}

// Consultar Servicios Contratados
$servicios = [];
try {
    $stmtS = $db->prepare("SELECT * FROM servicios_cliente WHERE cliente_id = ? ORDER BY id DESC");
    $stmtS->execute([$clienteId]);
    $servicios = $stmtS->fetchAll();
} catch (Exception $e) {}

// Consultar Historial de Pagos
$pagos = [];
$totalPagadoHist = 0;
try {
    $stmtP = $db->prepare("SELECT * FROM pagos_honorarios WHERE cliente_id = ? ORDER BY fecha_pago DESC, id DESC");
    $stmtP->execute([$clienteId]);
    $pagos = $stmtP->fetchAll();
    foreach ($pagos as $p) $totalPagadoHist += (float)$p['monto_usd'];
} catch (Exception $e) {}

// Consultar Documentos en Bóveda
$documentos = [];
try {
    $stmtD = $db->prepare("SELECT * FROM documentos_escaneados WHERE cliente_id = ? ORDER BY id DESC");
    $stmtD->execute([$clienteId]);
    $documentos = $stmtD->fetchAll();
} catch (Exception $e) {}

// Consultar Notas & Bitácora
$notas = [];
try {
    $stmtN = $db->prepare("SELECT * FROM comunicaciones_cliente WHERE cliente_id = ? ORDER BY fecha DESC, id DESC");
    $stmtN->execute([$clienteId]);
    $notas = $stmtN->fetchAll();
} catch (Exception $e) {}

$pageTitle = htmlspecialchars($cliente['razon_social']) . " — Ficha 360° | Kontify OS";
require_once __DIR__ . '/includes/header.php';
?>

<!-- BANNER DE ALERTA -->
<?php if (!empty($mensajeAlerta)): ?>
    <div class="mb-6 p-4 rounded-xl flex items-center justify-between border <?= $tipoAlerta === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?> animate-in fade-in">
        <div class="flex items-center gap-3">
            <i class="fa-solid <?= $tipoAlerta === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?> text-lg"></i>
            <span class="text-sm font-medium"><?= $mensajeAlerta ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-700">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
<?php endif; ?>

<!-- BREADCRUMBS -->
<div class="flex items-center gap-2 text-xs text-slate-400 mb-4">
    <a href="<?= BASE_URL ?>/crm.php" class="hover:text-[#0F172A] transition-colors">CRM Contable</a>
    <span>/</span>
    <span class="text-[#0F172A] font-semibold truncate"><?= htmlspecialchars($cliente['razon_social']) ?></span>
</div>

<!-- ==================================================================== -->
<!-- CLIENT HERO HEADER (FICHA 360°)                                      -->
<!-- ==================================================================== -->
<div class="k-card p-6 lg:p-8 mb-8">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        
        <!-- Left: Client Profile Info -->
        <div class="flex items-start gap-4">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-[#00B894] to-[#2563EB] text-white font-extrabold text-2xl flex items-center justify-center shadow-lg shadow-emerald-500/10 shrink-0">
                <?= strtoupper(substr($cliente['razon_social'], 0, 1)) ?>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2.5 mb-1">
                    <h1 class="text-xl lg:text-2xl font-extrabold text-[#0F172A] tracking-tight leading-tight">
                        <?= htmlspecialchars($cliente['razon_social']) ?>
                    </h1>
                    
                    <!-- RIF Copiable -->
                    <button onclick="navigator.clipboard.writeText('<?= $cliente['rif'] ?>'); alert('RIF copiado al portapapeles');" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md font-mono text-xs font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors" title="Copiar RIF">
                        <span><?= htmlspecialchars($cliente['rif']) ?></span>
                        <i class="fa-regular fa-copy text-[10px] text-slate-400"></i>
                    </button>

                    <!-- Régimen -->
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-100">
                        <?= htmlspecialchars($cliente['tipo_contribuyente']) ?>
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-4 text-xs text-[#64748B] mt-2">
                    <?php if (!empty($cliente['telefono'])): ?>
                        <span class="flex items-center gap-1.5">
                            <i class="fa-solid fa-phone text-slate-400 text-[11px]"></i>
                            <?= htmlspecialchars($cliente['telefono']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($cliente['email'])): ?>
                        <span class="flex items-center gap-1.5">
                            <i class="fa-solid fa-envelope text-slate-400 text-[11px]"></i>
                            <?= htmlspecialchars($cliente['email']) ?>
                        </span>
                    <?php endif; ?>
                    <span class="flex items-center gap-1.5">
                        <i class="fa-regular fa-calendar-check text-slate-400 text-[11px]"></i>
                        Cliente desde <?= date('M Y', strtotime($cliente['creado_el'])) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Right: Status Dropdown & Action Toolbar -->
        <div class="flex flex-wrap items-center gap-3">
            
            <!-- Selector de Estado Interactivo -->
            <form method="POST" class="inline-block">
                <input type="hidden" name="accion" value="actualizar_estatus">
                <select name="nuevo_estatus" onchange="this.form.submit()" class="k-input text-xs font-bold py-2 px-3 pr-8 rounded-xl cursor-pointer <?= strtolower($cliente['estatus']) === 'al dia' ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : (strtolower($cliente['estatus']) === 'pendiente' ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-rose-700 bg-rose-50 border-rose-200') ?>">
                    <option value="al dia" <?= strtolower($cliente['estatus']) === 'al dia' ? 'selected' : '' ?>>● Estado: Al Día</option>
                    <option value="pendiente" <?= strtolower($cliente['estatus']) === 'pendiente' ? 'selected' : '' ?>>● Estado: Pendiente</option>
                    <option value="mora" <?= strtolower($cliente['estatus']) === 'mora' ? 'selected' : '' ?>>● Estado: En Mora</option>
                    <option value="auditoria" <?= strtolower($cliente['estatus']) === 'auditoria' ? 'selected' : '' ?>>● Estado: En Auditoría</option>
                    <option value="pausado" <?= strtolower($cliente['estatus']) === 'pausado' ? 'selected' : '' ?>>● Estado: Pausado</option>
                </select>
            </form>

            <?php if (!empty($cliente['telefono'])): ?>
                <?php 
                $waMsg = urlencode("Estimado(a) " . $cliente['razon_social'] . ", le saludamos de Kontify. Le enviamos su estado de cuenta contable del mes por $" . number_format($cliente['honorarios_usd'], 2) . " USD.");
                $cleanTel = preg_replace('/[^0-9]/', '', $cliente['telefono']);
                if (substr($cleanTel, 0, 1) === '0') $cleanTel = '58' . substr($cleanTel, 1);
                ?>
                <a href="https://wa.me/<?= $cleanTel ?>?text=<?= $waMsg ?>" target="_blank" class="k-btn k-btn-secondary text-xs">
                    <i class="fa-brands fa-whatsapp text-emerald-600 text-sm"></i>
                    <span>WhatsApp</span>
                </a>
            <?php endif; ?>

            <button onclick="document.getElementById('modalRegistrarPago').classList.remove('hidden')" class="k-btn k-btn-primary text-xs shadow-sm">
                <i class="fa-solid fa-receipt text-xs"></i>
                <span>+ Registrar Cobro</span>
            </button>
        </div>

    </div>

    <!-- Quick Financial Health Chips -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6 pt-6 border-t border-slate-100">
        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Honorario Mensual (MRR)</span>
            <span class="text-lg font-extrabold font-mono text-[#0F172A] mt-0.5 block">
                $<?= number_format($cliente['honorarios_usd'], 2, ',', '.') ?>
            </span>
        </div>
        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Cobrado (Histórico)</span>
            <span class="text-lg font-extrabold font-mono text-emerald-600 mt-0.5 block">
                $<?= number_format($totalPagadoHist, 2, ',', '.') ?>
            </span>
        </div>
        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Servicios Contratados</span>
            <span class="text-lg font-extrabold font-mono text-blue-600 mt-0.5 block">
                <?= count($servicios) ?> activos
            </span>
        </div>
        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Documentos en Bóveda</span>
            <span class="text-lg font-extrabold font-mono text-slate-700 mt-0.5 block">
                <?= count($documentos) ?> archivos
            </span>
        </div>
    </div>
</div>

<!-- ==================================================================== -->
<!-- 360° DOSSIER TABS NAVIGATION                                         -->
<!-- ==================================================================== -->
<div class="flex items-center gap-2 border-b border-slate-200 mb-6 overflow-x-auto select-none" id="tabsDossier">
    <button onclick="cambiarTabDossier('fiscal', this)" class="tab-btn px-4 py-2.5 text-xs font-bold border-b-2 border-[#00B894] text-[#00B894] flex items-center gap-2">
        <i class="fa-solid fa-file-lines"></i>
        <span>1. Datos Fiscales</span>
    </button>
    <button onclick="cambiarTabDossier('servicios', this)" class="tab-btn px-4 py-2.5 text-xs font-semibold border-b-2 border-transparent text-[#64748B] hover:text-[#0F172A] flex items-center gap-2">
        <i class="fa-solid fa-layer-group"></i>
        <span>2. Servicios Contratados (<?= count($servicios) ?>)</span>
    </button>
    <button onclick="cambiarTabDossier('pagos', this)" class="tab-btn px-4 py-2.5 text-xs font-semibold border-b-2 border-transparent text-[#64748B] hover:text-[#0F172A] flex items-center gap-2">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <span>3. Historial de Pagos (<?= count($pagos) ?>)</span>
    </button>
    <button onclick="cambiarTabDossier('documentos', this)" class="tab-btn px-4 py-2.5 text-xs font-semibold border-b-2 border-transparent text-[#64748B] hover:text-[#0F172A] flex items-center gap-2">
        <i class="fa-solid fa-vault"></i>
        <span>4. Documentos (<?= count($documentos) ?>)</span>
    </button>
    <button onclick="cambiarTabDossier('notas', this)" class="tab-btn px-4 py-2.5 text-xs font-semibold border-b-2 border-transparent text-[#64748B] hover:text-[#0F172A] flex items-center gap-2">
        <i class="fa-solid fa-book-bookmark"></i>
        <span>5. Notas & Bitácora (<?= count($notas) ?>)</span>
    </button>
</div>

<!-- ==================================================================== -->
<!-- TAB CONTENT PANELS                                                   -->
<!-- ==================================================================== -->

<!-- TAB 1: DATOS FISCALES -->
<div id="tab-fiscal" class="tab-panel">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Ficha de Datos Fiscales Editable -->
        <div class="lg:col-span-2 k-card p-6">
            <h3 class="text-sm font-bold text-[#0F172A] mb-4 pb-3 border-b border-slate-100 flex items-center justify-between">
                <span>Expediente Tributario & Fiscal</span>
                <span class="text-xs font-normal text-[#64748B]">Actualizado según SENIAT</span>
            </h3>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="accion" value="actualizar_fiscal">
                
                <div>
                    <label class="k-label">Razón Social</label>
                    <input type="text" name="razon_social" value="<?= htmlspecialchars($cliente['razon_social']) ?>" required class="k-input font-semibold">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="k-label">RIF / Identificación Tributaria</label>
                        <input type="text" name="rif" value="<?= htmlspecialchars($cliente['rif']) ?>" required class="k-input font-mono font-bold uppercase">
                    </div>
                    <div>
                        <label class="k-label">Tipo de Contribuyente</label>
                        <select name="tipo_contribuyente" class="k-input font-medium">
                            <option value="Ordinario" <?= $cliente['tipo_contribuyente'] === 'Ordinario' ? 'selected' : '' ?>>Contribuyente Ordinario</option>
                            <option value="Especial" <?= $cliente['tipo_contribuyente'] === 'Especial' ? 'selected' : '' ?>>Contribuyente Especial</option>
                            <option value="Formal" <?= $cliente['tipo_contribuyente'] === 'Formal' ? 'selected' : '' ?>>Contribuyente Formal</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="k-label">Teléfono de Contacto</label>
                        <input type="text" name="telefono" value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>" class="k-input">
                    </div>
                    <div>
                        <label class="k-label">Correo Electrónico Contable</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>" class="k-input">
                    </div>
                </div>

                <div>
                    <label class="k-label">Domicilio Fiscal Registrado</label>
                    <input type="text" name="direccion" value="<?= htmlspecialchars($cliente['direccion'] ?? '') ?>" placeholder="Av. Principal, Edificio, Piso, Oficina..." class="k-input">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="k-label">Honorario Contable Mensual ($ USD)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-bold text-slate-400">$</span>
                            <input type="number" step="0.01" name="honorarios_usd" value="<?= number_format($cliente['honorarios_usd'], 2, '.', '') ?>" class="k-input pl-8 font-mono font-bold text-emerald-600">
                        </div>
                    </div>
                    <div>
                        <label class="k-label">Equivalente a Tasa BCV Oficial</label>
                        <div class="k-input bg-slate-50 font-mono font-bold text-slate-600">
                            ≈ Bs. <?= number_format($cliente['honorarios_usd'] * $tasaBcvActual, 2, ',', '.') ?>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex justify-end">
                    <button type="submit" class="k-btn k-btn-primary text-xs">
                        Guardar Cambios Fiscales
                    </button>
                </div>
            </form>
        </div>

        <!-- Columna Lateral: Resumen de Cumplimiento -->
        <div class="space-y-4">
            <div class="k-card p-5">
                <h4 class="text-xs font-bold uppercase tracking-wider text-[#0F172A] mb-3">Obligaciones Tributarias</h4>
                <div class="space-y-2.5 text-xs">
                    <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50">
                        <span class="text-slate-600">Declaración IVA</span>
                        <span class="font-bold text-emerald-600"><?= $cliente['tipo_contribuyente'] === 'Especial' ? 'Quincenal' : 'Mensual' ?></span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50">
                        <span class="text-slate-600">Libros de Compras y Ventas</span>
                        <span class="font-bold text-slate-700">VEN-NIF Obligatorio</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50">
                        <span class="text-slate-600">Retenciones ISLR / IGTF</span>
                        <span class="font-bold text-blue-600"><?= $cliente['tipo_contribuyente'] === 'Especial' ? 'Agente de Retención' : 'Sujeto Pasivo' ?></span>
                    </div>
                </div>
            </div>

            <div class="k-card p-5">
                <h4 class="text-xs font-bold uppercase tracking-wider text-[#0F172A] mb-2">Acceso a Bóveda</h4>
                <p class="text-xs text-[#64748B] mb-3">Sube o consulta declaraciones, balances y solvencias laborales.</p>
                <button onclick="document.getElementById('modalSubirDocCliente').classList.remove('hidden')" class="k-btn k-btn-secondary w-full text-xs">
                    <i class="fa-solid fa-cloud-arrow-up text-[#00B894]"></i>
                    <span>Subir Documento</span>
                </button>
            </div>
        </div>

    </div>
</div>

<!-- TAB 2: SERVICIOS CONTRATADOS -->
<div id="tab-servicios" class="tab-panel hidden">
    <div class="k-card p-6">
        <div class="flex items-center justify-between mb-6 pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-sm font-bold text-[#0F172A]">Servicios y Honorarios Contratados</h3>
                <p class="text-xs text-[#64748B] mt-0.5">Gestión de retainers mensuales y proyectos extraordinarios</p>
            </div>
            <button onclick="document.getElementById('modalAgregarServicio').classList.remove('hidden')" class="k-btn k-btn-primary text-xs">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>+ Agregar Servicio</span>
            </button>
        </div>

        <?php if (empty($servicios)): ?>
            <div class="py-12 text-center text-slate-400 text-xs italic">
                No hay servicios asignados a este cliente. Usa el botón "+ Agregar Servicio".
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($servicios as $s): ?>
                    <div class="p-4 rounded-xl border border-slate-200/80 bg-white hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <h4 class="font-bold text-xs text-[#0F172A] leading-tight">
                                    <?= htmlspecialchars($s['nombre_servicio']) ?>
                                </h4>
                                <span class="k-badge <?= $s['tipo'] === 'recurrente' ? 'k-badge-success' : 'k-badge-premium' ?> text-[10px]">
                                    <?= ucfirst($s['tipo']) ?>
                                </span>
                            </div>
                            <?php if (!empty($s['observaciones'])): ?>
                                <p class="text-[11px] text-slate-500 mb-3"><?= htmlspecialchars($s['observaciones']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-slate-100 mt-2">
                            <span class="text-[11px] text-slate-400 font-medium">Honorario:</span>
                            <span class="font-mono font-extrabold text-sm text-[#0F172A]">
                                $<?= number_format($s['monto_usd'], 2, ',', '.') ?> <span class="text-[10px] text-slate-400 font-normal">USD</span>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- TAB 3: HISTORIAL DE PAGOS -->
<div id="tab-pagos" class="tab-panel hidden">
    <div class="k-card overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-[#0F172A]">Historial de Pagos y Cobranzas</h3>
                <p class="text-xs text-[#64748B] mt-0.5">Sincronizado automáticamente con Finanzas e Ingresos</p>
            </div>
            <button onclick="document.getElementById('modalRegistrarPago').classList.remove('hidden')" class="k-btn k-btn-primary text-xs">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Registrar Pago</span>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="k-table">
                <thead>
                    <tr>
                        <th>Fecha de Pago</th>
                        <th>Período</th>
                        <th>Método</th>
                        <th>Referencia</th>
                        <th>Monto USD</th>
                        <th>Equiv. Bs (Tasa)</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pagos)): ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 text-xs italic">
                                No hay pagos registrados para este cliente aún.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pagos as $p): ?>
                            <tr>
                                <td class="font-medium"><?= date('d/m/Y', strtotime($p['fecha_pago'])) ?></td>
                                <td>
                                    <span class="font-mono text-xs font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
                                        <?= htmlspecialchars($p['mes_periodo']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-xs font-semibold text-slate-600">
                                        <?= htmlspecialchars($p['metodo_pago']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="font-mono text-xs text-slate-500">
                                        <?= htmlspecialchars($p['referencia'] ?: 'S/Ref') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="font-mono font-extrabold text-[#00B894] text-sm">
                                        $<?= number_format($p['monto_usd'], 2, ',', '.') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-xs text-slate-600 font-mono">
                                        Bs. <?= number_format($p['monto_bs'], 2, ',', '.') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="k-badge k-badge-success text-[10px]">Conciliado</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB 4: DOCUMENTOS EN BÓVEDA -->
<div id="tab-documentos" class="tab-panel hidden">
    <div class="k-card p-6">
        <div class="flex items-center justify-between mb-6 pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-sm font-bold text-[#0F172A]">Bóveda Documental del Cliente</h3>
                <p class="text-xs text-[#64748B] mt-0.5">Expediente digitalizado de declaraciones, certificados y comprobantes</p>
            </div>
            <button onclick="document.getElementById('modalSubirDocCliente').classList.remove('hidden')" class="k-btn k-btn-primary text-xs">
                <i class="fa-solid fa-cloud-arrow-up text-xs"></i>
                <span>Subir Documento</span>
            </button>
        </div>

        <?php if (empty($documentos)): ?>
            <div class="py-12 text-center text-slate-400 text-xs italic">
                No hay documentos digitalizados para este cliente. Usa el botón "Subir Documento".
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($documentos as $doc): ?>
                    <div class="p-4 rounded-xl border border-slate-200/80 bg-white hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="k-badge k-badge-neutral text-[10px]">
                                    <?= htmlspecialchars($doc['mes_fiscal']) ?>
                                </span>
                                <span class="text-[10px] text-slate-400">
                                    <?= round($doc['tamanio_bytes'] / 1024, 1) ?> KB
                                </span>
                            </div>
                            <div class="flex items-center gap-2.5 my-2">
                                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                                    <i class="fa-regular fa-file-pdf text-sm"></i>
                                </div>
                                <div class="truncate">
                                    <div class="text-xs font-bold text-[#0F172A] truncate" title="<?= htmlspecialchars($doc['nombre_original']) ?>">
                                        <?= htmlspecialchars($doc['nombre_original']) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-medium">
                                        <?= htmlspecialchars($doc['tipo_documento']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between mt-2">
                            <span class="text-[10px] text-slate-400"><?= date('d/m/Y', strtotime($doc['subido_el'])) ?></span>
                            <a href="<?= BASE_URL ?>/<?= htmlspecialchars($doc['ruta_archivo']) ?>" target="_blank" class="k-btn k-btn-secondary text-[11px] py-1 px-2.5">
                                <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                <span>Ver Archivo</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- TAB 5: NOTAS & BITÁCORA -->
<div id="tab-notas" class="tab-panel hidden">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Timeline de Notas (Estilo Linear / Notion) -->
        <div class="lg:col-span-2 k-card p-6">
            <h3 class="text-sm font-bold text-[#0F172A] mb-4 pb-3 border-b border-slate-100">
                Bitácora Cronológica de Gestión
            </h3>

            <?php if (empty($notas)): ?>
                <div class="py-12 text-center text-slate-400 text-xs italic">
                    No hay notas registradas para este cliente aún. Añade la primera nota a la derecha.
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($notas as $nota): ?>
                        <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/60 relative">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="k-badge <?= $nota['tipo'] === 'Reunión' ? 'k-badge-premium' : ($nota['tipo'] === 'WhatsApp' ? 'k-badge-success' : 'k-badge-neutral') ?> text-[10px]">
                                        <?= htmlspecialchars($nota['tipo']) ?>
                                    </span>
                                    <span class="text-xs font-bold text-[#0F172A]"><?= htmlspecialchars($nota['asunto']) ?></span>
                                </div>
                                <span class="text-[11px] text-slate-400 font-mono"><?= date('d/m/Y H:i', strtotime($nota['fecha'])) ?></span>
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed"><?= nl2br(htmlspecialchars($nota['detalle'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Formulario para Añadir Nota Rápida -->
        <div class="k-card p-6">
            <h3 class="text-sm font-bold text-[#0F172A] mb-4 pb-3 border-b border-slate-100">
                Añadir Nota a la Bitácora
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="accion" value="agregar_nota">
                
                <div>
                    <label class="k-label">Tipo de Comunicación</label>
                    <select name="tipo_comunicacion" class="k-input font-medium">
                        <option value="Nota Interna">Nota Interna (Despacho)</option>
                        <option value="WhatsApp">Mensaje WhatsApp</option>
                        <option value="Llamada">Llamada Telefónica</option>
                        <option value="Reunión">Reunión de Cierre</option>
                    </select>
                </div>

                <div>
                    <label class="k-label">Asunto / Título</label>
                    <input type="text" name="asunto" placeholder="Ej: Acuerdos de cierre de mes" required class="k-input">
                </div>

                <div>
                    <label class="k-label">Detalles de la Nota</label>
                    <textarea name="detalle" rows="4" placeholder="Escribe aquí los acuerdos, incidencias o recordatorios..." required class="k-input"></textarea>
                </div>

                <button type="submit" class="k-btn k-btn-primary w-full text-xs">
                    Guardar Nota en Bitácora
                </button>
            </form>
        </div>

    </div>
</div>

<!-- ==================================================================== -->
<!-- MODALES: PAGOS, SERVICIOS, DOCUMENTOS                                -->
<!-- ==================================================================== -->

<!-- MODAL REGISTRAR PAGO -->
<div id="modalRegistrarPago" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full p-6 animate-in fade-in zoom-in duration-150">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-[#0F172A]">Registrar Cobro de Honorarios</h3>
            <button onclick="document.getElementById('modalRegistrarPago').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="accion" value="registrar_pago">
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="k-label">Monto ($ USD)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-bold text-slate-400">$</span>
                        <input type="number" step="0.01" name="monto_usd" value="<?= number_format($cliente['honorarios_usd'], 2, '.', '') ?>" required class="k-input pl-8 font-mono font-bold text-emerald-600">
                    </div>
                </div>
                <div>
                    <label class="k-label">Período Fiscal</label>
                    <input type="month" name="mes_periodo" value="<?= date('Y-m') ?>" required class="k-input font-bold">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="k-label">Método de Pago</label>
                    <select name="metodo_pago" class="k-input">
                        <option value="Pago Móvil">Pago Móvil</option>
                        <option value="Transferencia Bancaria">Transferencia Bancaria</option>
                        <option value="Zelle">Zelle USD</option>
                        <option value="Efectivo USD">Efectivo USD</option>
                        <option value="Binance USDT">Binance Pay</option>
                    </select>
                </div>
                <div>
                    <label class="k-label">Referencia / Comprobante</label>
                    <input type="text" name="referencia" placeholder="Ref: 004812" class="k-input font-mono">
                </div>
            </div>

            <div>
                <label class="k-label">Fecha de Cobro</label>
                <input type="date" name="fecha_pago" value="<?= date('Y-m-d') ?>" required class="k-input">
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" id="marcar_al_dia" name="marcar_al_dia" value="1" checked class="rounded border-slate-300 text-emerald-600">
                <label for="marcar_al_dia" class="text-xs text-slate-700 select-none">Actualizar estado del cliente a "Al Día"</label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('modalRegistrarPago').classList.add('hidden')" class="k-btn k-btn-secondary text-xs">Cancelar</button>
                <button type="submit" class="k-btn k-btn-primary text-xs">Confirmar Cobro</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL AGREGAR SERVICIO -->
<div id="modalAgregarServicio" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full p-6 animate-in fade-in zoom-in duration-150">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-[#0F172A]">Contratar Nuevo Servicio</h3>
            <button onclick="document.getElementById('modalAgregarServicio').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="accion" value="agregar_servicio">
            <div>
                <label class="k-label">Nombre del Servicio</label>
                <input type="text" name="nombre_servicio" placeholder="Ej: Declaración Definitiva ISLR" required class="k-input">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="k-label">Tipo de Servicio</label>
                    <select name="tipo_servicio" class="k-input">
                        <option value="recurrente">Recurrente (Mensual)</option>
                        <option value="extraordinario">Extraordinario (Puntual)</option>
                    </select>
                </div>
                <div>
                    <label class="k-label">Monto ($ USD)</label>
                    <input type="number" step="0.01" name="monto_usd" placeholder="80.00" required class="k-input font-bold text-emerald-600">
                </div>
            </div>
            <div>
                <label class="k-label">Observaciones</label>
                <input type="text" name="observaciones" placeholder="Detalles o frecuencia pactada" class="k-input">
            </div>
            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('modalAgregarServicio').classList.add('hidden')" class="k-btn k-btn-secondary text-xs">Cancelar</button>
                <button type="submit" class="k-btn k-btn-primary text-xs">Agregar Servicio</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL SUBIR DOCUMENTO CLIENTE -->
<div id="modalSubirDocCliente" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full p-6 animate-in fade-in zoom-in duration-150">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-[#0F172A]">Subir Documento a Bóveda</h3>
            <button onclick="document.getElementById('modalSubirDocCliente').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="accion" value="subir_documento">
            <div>
                <label class="k-label">Tipo de Documento</label>
                <select name="tipo_documento" class="k-input">
                    <option value="Declaración SENIAT (Certificado)">Declaración SENIAT (Certificado)</option>
                    <option value="Comprobante Retención IVA">Comprobante Retención IVA</option>
                    <option value="Comprobante Retención ISLR">Comprobante Retención ISLR</option>
                    <option value="Estado de Cuenta Bancario">Estado de Cuenta Bancario</option>
                    <option value="Factura de Compra / Venta">Factura de Compra / Venta</option>
                    <option value="Planilla IVSS / FAOV / INCES">Planilla IVSS / FAOV / INCES</option>
                    <option value="Documento Legal / RIF">Documento Legal / RIF</option>
                </select>
            </div>
            <div>
                <label class="k-label">Mes Fiscal (Período)</label>
                <input type="month" name="mes_fiscal" value="<?= date('Y-m') ?>" required class="k-input">
            </div>
            <div>
                <label class="k-label">Seleccionar Archivo (PDF o Imagen)</label>
                <input type="file" name="archivo_doc" accept=".pdf,image/*" required class="k-input text-xs">
            </div>
            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('modalSubirDocCliente').classList.add('hidden')" class="k-btn k-btn-secondary text-xs">Cancelar</button>
                <button type="submit" class="k-btn k-btn-primary text-xs">Subir a Bóveda</button>
            </div>
        </form>
    </div>
</div>

<script>
function cambiarTabDossier(tabName, btn) {
    document.querySelectorAll('#tabsDossier .tab-btn').forEach(b => {
        b.className = 'tab-btn px-4 py-2.5 text-xs font-semibold border-b-2 border-transparent text-[#64748B] hover:text-[#0F172A] flex items-center gap-2';
    });
    btn.className = 'tab-btn px-4 py-2.5 text-xs font-bold border-b-2 border-[#00B894] text-[#00B894] flex items-center gap-2';

    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    const target = document.getElementById('tab-' + tabName);
    if (target) target.classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
