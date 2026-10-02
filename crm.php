<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$db = getDB();
$empresa_id = (int)($_SESSION['empresa_id'] ?? 1);
$mensajeAlerta = '';
$tipoAlerta = '';
$tasaBcvActual = $_SESSION['tasa_bcv'] ?? 65.50;

// ====================================================================
// PROCESAR: CREAR CLIENTE CON SERVICIOS ADQUIRIDOS
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_cliente'])) {
    $razonSocial = trim($_POST['razon_social'] ?? '');
    $rif = strtoupper(trim($_POST['rif'] ?? ''));
    $tipoContribuyente = $_POST['tipo_contribuyente'] ?? 'Ordinario';
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $honorarios = floatval(str_replace(',', '.', $_POST['honorarios_usd'] ?? '0'));
    $estatus = $_POST['estatus'] ?? 'al dia';
    $serviciosSeleccionados = $_POST['servicios_seleccionados'] ?? [];

    if (!empty($razonSocial) && !empty($rif)) {
        try {
            $stmt = $db->prepare("INSERT INTO clientes (empresa_id, razon_social, rif, tipo_contribuyente, telefono, email, direccion, honorarios_usd, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$empresa_id, $razonSocial, $rif, $tipoContribuyente, $telefono, $email, $direccion, $honorarios, $estatus]);
            $nuevoId = $db->lastInsertId();

            // Asignar servicios seleccionados al cliente
            if ($nuevoId && !empty($serviciosSeleccionados)) {
                $stmtAddServ = $db->prepare("INSERT INTO servicios_cliente (cliente_id, nombre_servicio, tipo, monto_usd, estatus) VALUES (?, ?, 'recurrente', ?, 'activo')");
                $montoPorServicio = count($serviciosSeleccionados) > 0 ? round($honorarios / count($serviciosSeleccionados), 2) : $honorarios;
                foreach ($serviciosSeleccionados as $nombreS) {
                    $stmtAddServ->execute([$nuevoId, trim($nombreS), $montoPorServicio]);
                }
            } elseif ($nuevoId && $honorarios > 0) {
                $db->prepare("INSERT INTO servicios_cliente (cliente_id, nombre_servicio, tipo, monto_usd, estatus) VALUES (?, 'Honorario Mensual Contable', 'recurrente', ?, 'activo')")
                   ->execute([$nuevoId, $honorarios]);
            }

            // Nota interna de bienvenida
            if ($nuevoId) {
                $db->prepare("INSERT INTO comunicaciones_cliente (cliente_id, tipo, asunto, detalle) VALUES (?, 'Nota Interna', 'Cliente dado de alta en CRM', 'Incorporación formal de la empresa a la cartera del despacho.')")
                   ->execute([$nuevoId]);
            }

            $mensajeAlerta = "¡Cliente <strong>" . htmlspecialchars($razonSocial) . "</strong> incorporado exitosamente al CRM con sus servicios activos!";
            $tipoAlerta = 'success';
        } catch (PDOException $e) {
            $mensajeAlerta = "Error al crear cliente: " . $e->getMessage();
            $tipoAlerta = 'error';
        }
    }
}

// ====================================================================
// PROCESAR: EDITAR DATOS DEL CLIENTE
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_cliente'])) {
    $clienteId = intval($_POST['cliente_id'] ?? 0);
    $razonSocial = trim($_POST['razon_social'] ?? '');
    $rif = strtoupper(trim($_POST['rif'] ?? ''));
    $tipoContribuyente = $_POST['tipo_contribuyente'] ?? 'Ordinario';
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $honorarios = floatval(str_replace(',', '.', $_POST['honorarios_usd'] ?? '0'));
    $estatus = $_POST['estatus'] ?? 'al dia';

    if ($clienteId > 0 && !empty($razonSocial)) {
        try {
            $stmt = $db->prepare("UPDATE clientes SET razon_social = ?, rif = ?, tipo_contribuyente = ?, telefono = ?, email = ?, direccion = ?, honorarios_usd = ?, estatus = ? WHERE id = ?");
            $stmt->execute([$razonSocial, $rif, $tipoContribuyente, $telefono, $email, $direccion, $honorarios, $estatus, $clienteId]);
            $mensajeAlerta = "Datos de <strong>" . htmlspecialchars($razonSocial) . "</strong> actualizados correctamente.";
            $tipoAlerta = 'success';
        } catch (PDOException $e) {
            $mensajeAlerta = "Error al actualizar cliente: " . $e->getMessage();
            $tipoAlerta = 'error';
        }
    }
}

// ====================================================================
// PROCESAR: REGISTRAR COBRO RÁPIDO DESDE EL DIRECTORIO
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cobro_rapido_crm'])) {
    $clienteId = intval($_POST['cliente_id'] ?? 0);
    $montoUsd = floatval(str_replace(',', '.', $_POST['monto_usd'] ?? '0'));
    $metodo = trim($_POST['metodo_pago'] ?? 'Pago Móvil');
    $referencia = trim($_POST['referencia'] ?? '');
    $fecha = $_POST['fecha_pago'] ?? date('Y-m-d');
    $montoBs = $montoUsd * $tasaBcvActual;

    if ($clienteId > 0 && $montoUsd > 0) {
        try {
            $stmtI = $db->prepare("INSERT INTO ingresos (empresa_id, cliente_id, nombre_servicio, tipo_ingreso, fecha, monto, moneda, tasa_cambio, monto_equivalente, monto_usd, cuenta_receptora, metodo_pago, referencia, descripcion, estado) VALUES (?, ?, 'Honorarios Contables Mensuales', 'recurrente', ?, ?, 'USD', ?, ?, ?, 'Banco Banesco', ?, ?, 'Cobro registrado desde Directorio CRM', 'cobrado')");
            $stmtI->execute([$empresa_id, $clienteId, $fecha, $montoUsd, $tasaBcvActual, $montoBs, $montoUsd, $metodo, $referencia]);

            // Actualizar estado a 'al dia'
            $db->prepare("UPDATE clientes SET estatus = 'al dia' WHERE id = ?")->execute([$clienteId]);

            // Registrar en historial de pagos
            $db->prepare("INSERT INTO pagos_honorarios (cliente_id, mes_periodo, monto_usd, monto_bs, tasa_bcv, metodo_pago, referencia, fecha_pago, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Cobro registrado desde CRM')")
               ->execute([$clienteId, date('Y-m'), $montoUsd, $montoBs, $tasaBcvActual, $metodo, $referencia, $fecha]);

            $mensajeAlerta = "Cobro de <strong>\${$montoUsd} USD (Bs. " . number_format($montoBs, 2, ',', '.') . ")</strong> registrado exitosamente. Cliente ahora está Solvente.";
            $tipoAlerta = 'success';
        } catch (Exception $e) {
            $mensajeAlerta = "Error al registrar cobro: " . $e->getMessage();
            $tipoAlerta = 'error';
        }
    }
}

// ====================================================================
// PROCESAR: ELIMINAR CLIENTE
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_cliente'])) {
    $clienteId = intval($_POST['cliente_id'] ?? 0);
    if ($clienteId > 0) {
        try {
            $db->prepare("DELETE FROM clientes WHERE id = ?")->execute([$clienteId]);
            $mensajeAlerta = "Cliente eliminado de la cartera.";
            $tipoAlerta = 'success';
        } catch (Exception $e) {
            $mensajeAlerta = "Error al eliminar cliente: " . $e->getMessage();
            $tipoAlerta = 'error';
        }
    }
}

// ====================================================================
// CARGAR CARTERA DE CLIENTES, SERVICIOS ADQUIRIDOS Y DEUDAS
// ====================================================================
$clientes = [];
$totalCarteraUsd = 0;
$totalDeudaUsd = 0;
$totalAlDia = $totalPendiente = $totalMora = 0;
$serviciosPorCliente = [];
$catalogoServicios = [];

try {
    $catalogoServicios = $db->query("SELECT id, nombre, precio_sugerido_usd FROM servicios WHERE activo = 1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Obtener servicios activos agrupados por cliente
    $stmtS = $db->query("SELECT cliente_id, nombre_servicio, monto_usd FROM servicios_cliente WHERE estatus = 'activo' ORDER BY id ASC");
    if ($stmtS) {
        while ($row = $stmtS->fetch(PDO::FETCH_ASSOC)) {
            $serviciosPorCliente[$row['cliente_id']][] = $row['nombre_servicio'];
        }
    }

    // Obtener clientes con cálculo de documentos y deudas
    $stmt = $db->query("SELECT c.*, 
        (SELECT COUNT(*) FROM documentos_escaneados d WHERE d.cliente_id = c.id) as total_docs,
        (SELECT COALESCE(SUM(monto_usd), 0) FROM ingresos i WHERE i.cliente_id = c.id AND i.estado = 'pendiente') as deuda_ingresos_usd,
        (SELECT MAX(fecha_pago) FROM pagos_honorarios p WHERE p.cliente_id = c.id) as ultimo_pago_fecha
        FROM clientes c 
        ORDER BY c.id DESC");
        
    if ($stmt) {
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($clientes as &$c) {
            $totalCarteraUsd += (float)($c['honorarios_usd'] ?? 0);
            $st = strtolower($c['estatus'] ?? 'al dia');
            
            // Cálculo de deuda en USD y Bs
            $deudaUsd = (float)($c['deuda_ingresos_usd'] ?? 0);
            if ($deudaUsd == 0 && ($st === 'mora' || $st === 'pendiente')) {
                $deudaUsd = (float)($c['honorarios_usd'] ?? 0);
            }
            $c['deuda_usd'] = $deudaUsd;
            $c['deuda_bs'] = $deudaUsd * $tasaBcvActual;
            $totalDeudaUsd += $deudaUsd;

            if ($st === 'al dia') $totalAlDia++;
            elseif ($st === 'pendiente') $totalPendiente++;
            else $totalMora++;
        }
        unset($c);
    }
} catch (Exception $e) {}

$totalClientes = count($clientes);
$ticketPromedio = $totalClientes > 0 ? $totalCarteraUsd / $totalClientes : 0;

$pageTitle = "Directorio de Clientes — Kontify APP";
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

<!-- ==================================================================== -->
<!-- HEADER DIRECTORIO DE CLIENTES (CON TASA DEL DÍA Y BOTÓN NUEVO)       -->
<!-- ==================================================================== -->
<div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-8">
    <div>
        <div class="flex items-center gap-2 mb-1.5">
            <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200/60 px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Directorio de Clientes
            </span>
            <span class="text-xs text-slate-400 font-medium">&bull; Gestión Fiscal y Cobranza Contable</span>
        </div>
        <h1 class="text-2xl lg:text-3xl font-extrabold text-[#0F172A] tracking-tight">Directorio de Clientes</h1>
        <p class="text-xs lg:text-sm text-[#64748B] mt-1 font-medium">
            Control de empresas, servicios adquiridos, deudas pendientes y enlace directo al expediente individual.
        </p>
    </div>

    <!-- Actions & Tasa del Día (Estilo B2B) -->
    <div class="flex items-center gap-3 flex-wrap">
        <div class="p-2.5 px-3.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-arrow-right-arrow-left text-xs text-blue-600"></i>
            <span class="text-xs text-[#64748B] font-medium">Tasa del día:</span>
            <span class="font-mono font-extrabold text-xs text-[#0F172A]">$ 1 = Bs. <?= number_format($tasaBcvActual, 2, ',', '.') ?></span>
        </div>

        <button onclick="document.getElementById('modalNuevoClienteCRM').classList.remove('hidden')" class="k-btn k-btn-primary text-xs shadow-sm py-2.5 px-4">
            <i class="fa-solid fa-plus text-xs"></i>
            <span>+ NUEVO CLIENTE</span>
        </button>
    </div>
</div>

<!-- ==================================================================== -->
<!-- CRM EXECUTIVE METRICS CARDS                                          -->
<!-- ==================================================================== -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="k-card p-5">
        <div class="text-[11px] font-bold uppercase tracking-wider text-[#64748B] mb-1">Clientes Registrados</div>
        <div class="text-2xl font-extrabold font-mono text-[#0F172A]"><?= $totalClientes ?></div>
        <div class="text-[11px] text-slate-400 mt-1 font-medium">Empresas en la cartera activa</div>
    </div>
    <div class="k-card p-5">
        <div class="text-[11px] font-bold uppercase tracking-wider text-[#64748B] mb-1">Cartera Mensual Proyectada</div>
        <div class="text-2xl font-extrabold font-mono text-emerald-600">$<?= number_format($totalCarteraUsd, 2, ',', '.') ?></div>
        <div class="text-[11px] text-emerald-700 mt-1 font-semibold">Total honorarios pactados</div>
    </div>
    <div class="k-card p-5 border-l-4 border-l-rose-500">
        <div class="text-[11px] font-bold uppercase tracking-wider text-[#64748B] mb-1">Deuda Total por Cobrar</div>
        <div class="text-2xl font-extrabold font-mono text-rose-600">$<?= number_format($totalDeudaUsd, 2, ',', '.') ?></div>
        <div class="text-[11px] text-rose-700 mt-1 font-semibold">Equiv. Bs. <?= number_format($totalDeudaUsd * $tasaBcvActual, 2, ',', '.') ?></div>
    </div>
    <div class="k-card p-5 border-l-4 border-l-emerald-500">
        <div class="text-[11px] font-bold uppercase tracking-wider text-[#64748B] mb-1">Clientes Solventes</div>
        <div class="text-2xl font-extrabold font-mono text-emerald-700"><?= $totalAlDia ?> <span class="text-xs text-slate-400 font-normal">/ <?= $totalClientes ?></span></div>
        <div class="text-[11px] text-emerald-600 font-semibold mt-1">Al día con sus declaraciones y pagos</div>
    </div>
</div>

<!-- ==================================================================== -->
<!-- BARRA DE BÚSQUEDA Y FILTROS POR ESTADO                               -->
<!-- ==================================================================== -->
<div class="k-card p-4 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
    
    <!-- Filtros de Píldoras -->
    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0 select-none" id="filterStatusGroup">
        <button onclick="filtrarPorEstado('todos', this)" class="filter-pill px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#0F172A] text-white transition-colors">
            Todos (<?= $totalClientes ?>)
        </button>
        <button onclick="filtrarPorEstado('al dia', this)" class="filter-pill px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">
            Solventes (<?= $totalAlDia ?>)
        </button>
        <button onclick="filtrarPorEstado('pendiente', this)" class="filter-pill px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">
            Pendientes (<?= $totalPendiente ?>)
        </button>
        <button onclick="filtrarPorEstado('mora', this)" class="filter-pill px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">
            En Mora / Deuda (<?= $totalMora ?>)
        </button>
    </div>

    <!-- Buscador en tiempo real -->
    <div class="relative w-full md:w-80">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
        <input type="text" id="buscadorCliente" oninput="buscarClienteEnVivo()" placeholder="Buscar por Nombre, RIF, Servicio o Ciudad..." class="k-input pl-9 text-xs">
    </div>

</div>

<!-- ==================================================================== -->
<!-- TABLA DE CLIENTES COMPLETA (ENLACES, SERVICIOS, ESTADO, DEUDAS)      -->
<!-- ==================================================================== -->
<div class="k-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="k-table" id="tablaClientesCRM">
            <thead>
                <tr>
                    <th>Empresa / Razón Social</th>
                    <th>Cédula / RIF</th>
                    <th>Servicios que Adquirió</th>
                    <th>Estado</th>
                    <th class="text-right">Deuda (DÓLARES)</th>
                    <th class="text-right">Deuda (BOLÍVARES)</th>
                    <th class="text-right">Honorario Mensual</th>
                    <th>Ubicación & Contacto</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clientes)): ?>
                    <tr>
                        <td colspan="9" class="py-16 text-center text-slate-400">
                            <i class="fa-solid fa-users text-4xl text-slate-300 mb-3 block"></i>
                            <span class="text-sm font-bold text-[#0F172A] block">Aún no hay clientes en la cartera</span>
                            <span class="text-xs text-slate-400 mt-1 block">Comienza registrando tu primera empresa con el botón "+ NUEVO CLIENTE".</span>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($clientes as $cli): ?>
                        <?php 
                        $st = strtolower($cli['estatus'] ?? 'al dia');
                        $serviciosCli = $serviciosPorCliente[$cli['id']] ?? ['Contabilidad General VEN-NIF'];
                        $serviciosTexto = strtolower(implode(' ', $serviciosCli));
                        ?>
                        <tr class="cliente-row" 
                            data-estatus="<?= $st ?>" 
                            data-nombre="<?= strtolower(htmlspecialchars($cli['razon_social'])) ?>" 
                            data-rif="<?= strtolower(htmlspecialchars($cli['rif'])) ?>"
                            data-servicios="<?= htmlspecialchars($serviciosTexto) ?>"
                            data-direccion="<?= strtolower(htmlspecialchars($cli['direccion'] ?? '')) ?>">
                            
                            <!-- 1. NOMBRE / RAZÓN SOCIAL (ENLAZADO) -->
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-900 text-white font-extrabold text-sm flex items-center justify-center shadow-sm shrink-0 border border-slate-700/60">
                                        <?= strtoupper(substr($cli['razon_social'], 0, 1)) ?>
                                    </div>
                                    <div class="truncate">
                                        <!-- Enlace directo al módulo específico del cliente -->
                                        <a href="<?= BASE_URL ?>/cliente_detalle.php?id=<?= $cli['id'] ?>" class="font-extrabold text-sm text-[#0F172A] hover:text-[#00B894] transition-colors leading-tight block truncate max-w-[210px]" title="Ver expediente e información específica de <?= htmlspecialchars($cli['razon_social']) ?>">
                                            <?= htmlspecialchars($cli['razon_social']) ?>
                                        </a>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-[11px] text-[#64748B] truncate max-w-[140px]"><?= htmlspecialchars($cli['email'] ?: 'Sin correo') ?></span>
                                            <a href="<?= BASE_URL ?>/cliente_detalle.php?id=<?= $cli['id'] ?>" class="text-[10px] text-blue-600 hover:text-blue-800 font-bold inline-flex items-center gap-0.5">
                                                <span>Ficha 360°</span> <i class="fa-solid fa-arrow-up-right-from-square text-[8px]"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- 2. CÉDULA / RIF -->
                            <td>
                                <span class="font-mono font-bold text-xs text-slate-800 bg-slate-100 px-2.5 py-1 rounded-md border border-slate-200 block text-center">
                                    <?= htmlspecialchars($cli['rif']) ?>
                                </span>
                                <span class="text-[10px] text-slate-400 block text-center mt-0.5 font-medium">
                                    <?= htmlspecialchars($cli['tipo_contribuyente']) ?>
                                </span>
                            </td>

                            <!-- 3. SERVICIOS QUE ADQUIRIÓ (BADGES VISUALES) -->
                            <td>
                                <div class="flex flex-wrap gap-1 max-w-[210px]">
                                    <?php 
                                    $badgeColors = [
                                        'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        'bg-blue-50 text-blue-800 border-blue-200',
                                        'bg-purple-50 text-purple-800 border-purple-200',
                                        'bg-amber-50 text-amber-800 border-amber-200'
                                    ];
                                    $colorIdx = 0;
                                    foreach (array_slice($serviciosCli, 0, 3) as $sNom):
                                        $cStyle = $badgeColors[$colorIdx % count($badgeColors)];
                                        $colorIdx++;
                                    ?>
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md border <?= $cStyle ?> truncate max-w-[190px]" title="<?= htmlspecialchars($sNom) ?>">
                                            <?= htmlspecialchars($sNom) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($serviciosCli) > 3): ?>
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600" title="Ver todos en la ficha">
                                            +<?= count($serviciosCli) - 3 ?> más
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- 4. ESTADO / SOLVENCIA -->
                            <td>
                                <?php if ($st === 'al dia'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#E6F8F4] text-[#00876C] border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Solvente
                                    </span>
                                <?php elseif ($st === 'pendiente'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#FEF3C7] text-[#B45309] border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pendiente
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#FFE4E6] text-[#BE123C] border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> En Mora
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- 5. DEUDA (DÓLARES) -->
                            <td class="text-right">
                                <?php if ($cli['deuda_usd'] > 0): ?>
                                    <span class="font-mono font-extrabold text-sm text-rose-600 block">
                                        $<?= number_format($cli['deuda_usd'], 2, ',', '.') ?>
                                    </span>
                                    <span class="text-[10px] text-rose-500 font-bold uppercase">Debe</span>
                                <?php else: ?>
                                    <span class="font-mono font-bold text-sm text-emerald-600 block">
                                        $0.00
                                    </span>
                                    <span class="text-[10px] text-emerald-700 font-medium">Solvente</span>
                                <?php endif; ?>
                            </td>

                            <!-- 6. DEUDA (BOLÍVARES) -->
                            <td class="text-right">
                                <?php if ($cli['deuda_bs'] > 0): ?>
                                    <span class="font-mono font-extrabold text-xs text-rose-600 block">
                                        Bs. <?= number_format($cli['deuda_bs'], 2, ',', '.') ?>
                                    </span>
                                    <span class="text-[9px] text-slate-400 font-mono">Tasa: <?= number_format($tasaBcvActual, 2) ?></span>
                                <?php else: ?>
                                    <span class="font-mono text-xs text-slate-500 block">
                                        Bs. 0,00
                                    </span>
                                    <span class="text-[9px] text-slate-400 font-medium">Sin deuda</span>
                                <?php endif; ?>
                            </td>

                            <!-- 7. HONORARIO MENSUAL -->
                            <td class="text-right">
                                <span class="font-mono font-extrabold text-sm text-[#0F172A] block">
                                    $<?= number_format($cli['honorarios_usd'], 2, ',', '.') ?>
                                </span>
                                <span class="text-[10px] text-slate-400">/ mes</span>
                            </td>

                            <!-- 8. UBICACIÓN & CONTACTO -->
                            <td>
                                <div class="text-xs">
                                    <div class="text-[#0F172A] font-medium flex items-center gap-1.5 truncate max-w-[170px]" title="<?= htmlspecialchars($cli['direccion'] ?: 'Sin dirección registrada') ?>">
                                        <i class="fa-solid fa-location-dot text-slate-400 text-[11px] shrink-0"></i>
                                        <span class="truncate"><?= htmlspecialchars($cli['direccion'] ?: 'No registrada') ?></span>
                                    </div>
                                    <div class="text-slate-500 text-[11px] mt-0.5 flex items-center gap-1.5">
                                        <i class="fa-solid fa-phone text-slate-400 text-[10px]"></i>
                                        <span><?= htmlspecialchars($cli['telefono'] ?: 'S/Telf') ?></span>
                                    </div>
                                </div>
                            </td>

                            <!-- 9. ACCIONES -->
                            <td class="text-right">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    
                                    <!-- Botón Historial / Ver Información Específica -->
                                    <a href="<?= BASE_URL ?>/cliente_detalle.php?id=<?= $cli['id'] ?>" title="Ver Expediente Completo de <?= htmlspecialchars($cli['razon_social']) ?>" class="k-btn k-btn-secondary text-xs py-1.5 px-2.5">
                                        <i class="fa-solid fa-eye text-slate-500 text-[11px]"></i>
                                        <span>Historial</span>
                                    </a>

                                    <!-- Botón Cobrar Rápido -->
                                    <button type="button" onclick='abrirCobroRapido(<?= json_encode($cli) ?>)' title="Registrar Cobro" class="k-btn k-btn-primary text-xs py-1.5 px-2.5 bg-emerald-600 hover:bg-emerald-700 text-white">
                                        <i class="fa-solid fa-circle-plus text-[11px]"></i>
                                        <span>Cobrar</span>
                                    </button>

                                    <!-- Botón WhatsApp -->
                                    <?php if (!empty($cli['telefono'])): ?>
                                        <?php 
                                        $waMsg = urlencode("Estimado(a) " . $cli['razon_social'] . ", le saludamos de Kontify Despacho Contable. " . ($cli['deuda_usd'] > 0 ? "Le recordamos su honorario contable pendiente de $" . number_format($cli['deuda_usd'], 2) . " USD (Bs. " . number_format($cli['deuda_bs'], 2) . ")." : "Su cuenta contable se encuentra al día."));
                                        $cleanTel = preg_replace('/[^0-9]/', '', $cli['telefono']);
                                        if (substr($cleanTel, 0, 1) === '0') $cleanTel = '58' . substr($cleanTel, 1);
                                        ?>
                                        <a href="https://wa.me/<?= $cleanTel ?>?text=<?= $waMsg ?>" target="_blank" title="Contactar por WhatsApp" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 flex items-center justify-center transition-colors">
                                            <i class="fa-brands fa-whatsapp text-sm"></i>
                                        </a>
                                    <?php endif; ?>

                                    <!-- Botón Editar -->
                                    <button type="button" onclick='abrirEditarCliente(<?= json_encode($cli) ?>)' title="Editar Datos" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition-colors">
                                        <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                    </button>

                                    <!-- Botón Eliminar -->
                                    <form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar a <?= htmlspecialchars(addslashes($cli['razon_social'])) ?> de la cartera?');" class="inline">
                                        <input type="hidden" name="eliminar_cliente" value="1">
                                        <input type="hidden" name="cliente_id" value="<?= $cli['id'] ?>">
                                        <button type="submit" title="Eliminar Cliente" class="w-8 h-8 rounded-lg text-slate-300 hover:text-rose-500 hover:bg-rose-50 flex items-center justify-center transition-colors">
                                            <i class="fa-solid fa-trash-can text-[11px]"></i>
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================================================================== -->
<!-- MODAL: REGISTRAR NUEVO CLIENTE (CON SERVICIOS ADQUIRIDOS)            -->
<!-- ==================================================================== -->
<div id="modalNuevoClienteCRM" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-xl w-full p-6 animate-in fade-in zoom-in duration-150 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-user-plus text-sm"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-[#0F172A]">Registrar Nuevo Cliente</h3>
                    <p class="text-xs text-[#64748B]">Incorporación a la cartera y asignación de honorario</p>
                </div>
            </div>
            <button onclick="document.getElementById('modalNuevoClienteCRM').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="crear_cliente" value="1">
            
            <div>
                <label class="k-label">Razón Social Completa</label>
                <input type="text" name="razon_social" placeholder="Ej: Inversiones El Ávila, C.A." required class="k-input font-medium">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="k-label">RIF / Identificación Fiscal</label>
                    <input type="text" name="rif" placeholder="J-12345678-0" required class="k-input font-mono font-bold uppercase">
                </div>
                <div>
                    <label class="k-label">Régimen Contribuyente</label>
                    <select name="tipo_contribuyente" class="k-input font-medium">
                        <option value="Ordinario">Contribuyente Ordinario</option>
                        <option value="Especial">Contribuyente Especial</option>
                        <option value="Formal">Contribuyente Formal</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="k-label">Teléfono (WhatsApp)</label>
                    <input type="text" name="telefono" placeholder="+58 414 1234567" class="k-input">
                </div>
                <div>
                    <label class="k-label">Correo Electrónico</label>
                    <input type="email" name="email" placeholder="finanzas@empresa.com" class="k-input">
                </div>
            </div>

            <div>
                <label class="k-label">Domicilio Fiscal / Ubicación</label>
                <input type="text" name="direccion" placeholder="Av. Principal, Edificio Torre Financiera..." class="k-input">
            </div>

            <!-- Servicios que Adquiere el Cliente -->
            <div>
                <label class="k-label flex items-center justify-between">
                    <span>Servicios que Adquiere el Cliente</span>
                    <span class="text-[10px] text-emerald-600 font-semibold">Selecciona los aplicables</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-3 bg-slate-50 border border-slate-200/80 rounded-xl max-h-40 overflow-y-auto">
                    <?php if (!empty($catalogoServicios)): ?>
                        <?php foreach ($catalogoServicios as $cs): ?>
                            <label class="flex items-center gap-2 text-xs text-[#0F172A] cursor-pointer hover:bg-white p-1.5 rounded-lg transition-colors">
                                <input type="checkbox" name="servicios_seleccionados[]" value="<?= htmlspecialchars($cs['nombre']) ?>" class="rounded text-emerald-600 focus:ring-emerald-500">
                                <span class="truncate"><?= htmlspecialchars($cs['nombre']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <label class="flex items-center gap-2 text-xs text-[#0F172A]"><input type="checkbox" name="servicios_seleccionados[]" value="Contabilidad General VEN-NIF" checked> Contabilidad General VEN-NIF</label>
                        <label class="flex items-center gap-2 text-xs text-[#0F172A]"><input type="checkbox" name="servicios_seleccionados[]" value="Declaración de IVA & Libros Fiscales" checked> Declaración de IVA</label>
                        <label class="flex items-center gap-2 text-xs text-[#0F172A]"><input type="checkbox" name="servicios_seleccionados[]" value="Gestión de Nómina"> Gestión de Nómina</label>
                        <label class="flex items-center gap-2 text-xs text-[#0F172A]"><input type="checkbox" name="servicios_seleccionados[]" value="Declaración ISLR"> Declaración ISLR</label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="k-label">Honorario Mensual ($ USD)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-bold text-slate-400">$</span>
                        <input type="number" step="0.01" name="honorarios_usd" placeholder="250.00" required class="k-input pl-8 font-mono font-bold text-emerald-600">
                    </div>
                </div>
                <div>
                    <label class="k-label">Estado Inicial</label>
                    <select name="estatus" class="k-input font-medium">
                        <option value="al dia">Solvente / Al Día</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="mora">En Mora</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('modalNuevoClienteCRM').classList.add('hidden')" class="k-btn k-btn-secondary text-xs">
                    Cancelar
                </button>
                <button type="submit" class="k-btn k-btn-primary text-xs">
                    Guardar Cliente
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================================================================== -->
<!-- MODAL: REGISTRAR COBRO RÁPIDO DESDE EL CRM                           -->
<!-- ==================================================================== -->
<div id="modalCobroRapidoCRM" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full p-6 animate-in fade-in zoom-in duration-150">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-dollar-sign text-sm"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-[#0F172A]">Registrar Cobro de Honorarios</h3>
                    <p class="text-xs text-[#64748B]">Actualiza la solvencia del cliente en tiempo real</p>
                </div>
            </div>
            <button onclick="document.getElementById('modalCobroRapidoCRM').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="cobro_rapido_crm" value="1">
            <input type="hidden" name="cliente_id" id="cobroClienteId" value="">

            <div>
                <label class="k-label">Cliente / Razón Social</label>
                <input type="text" id="cobroClienteNombre" readonly class="k-input bg-slate-50 font-bold text-xs">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="k-label">Monto ($ USD)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-bold text-slate-400">$</span>
                        <input type="number" step="0.01" name="monto_usd" id="cobroMontoUsd" required oninput="calcularEquivBs(this.value)" class="k-input pl-8 font-mono font-bold text-emerald-600">
                    </div>
                </div>
                <div>
                    <label class="k-label">Equivalente Oficial (Bs.)</label>
                    <input type="text" id="cobroMontoBs" readonly class="k-input bg-slate-50 font-mono font-bold text-xs text-slate-700">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="k-label">Método de Pago</label>
                    <select name="metodo_pago" class="k-input font-medium">
                        <option value="Pago Móvil">Pago Móvil (VES)</option>
                        <option value="Transferencia Bancaria">Transferencia Bancaria</option>
                        <option value="Zelle">Zelle USD</option>
                        <option value="Efectivo USD">Efectivo USD</option>
                        <option value="Binance USDT">Binance Pay</option>
                    </select>
                </div>
                <div>
                    <label class="k-label">Fecha de Pago</label>
                    <input type="date" name="fecha_pago" value="<?= date('Y-m-d') ?>" required class="k-input text-xs font-mono">
                </div>
            </div>

            <div>
                <label class="k-label">Referencia Bancaria (Opcional)</label>
                <input type="text" name="referencia" placeholder="Ej: 0092144 / Zelle ID" class="k-input font-mono text-xs">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('modalCobroRapidoCRM').classList.add('hidden')" class="k-btn k-btn-secondary text-xs">
                    Cancelar
                </button>
                <button type="submit" class="k-btn k-btn-primary text-xs bg-emerald-600 hover:bg-emerald-700">
                    Registrar Cobro
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================================================================== -->
<!-- MODAL: EDITAR CLIENTE                                                -->
<!-- ==================================================================== -->
<div id="modalEditarClienteCRM" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 animate-in fade-in zoom-in duration-150 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-user-pen text-sm"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-[#0F172A]">Editar Información del Cliente</h3>
                    <p class="text-xs text-[#64748B]">Actualización de datos fiscales y honorarios</p>
                </div>
            </div>
            <button onclick="document.getElementById('modalEditarClienteCRM').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="editar_cliente" value="1">
            <input type="hidden" name="cliente_id" id="editClienteId" value="">

            <div>
                <label class="k-label">Razón Social Completa</label>
                <input type="text" name="razon_social" id="editRazonSocial" required class="k-input font-medium text-xs">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="k-label">RIF / Identificación Fiscal</label>
                    <input type="text" name="rif" id="editRif" required class="k-input font-mono font-bold uppercase text-xs">
                </div>
                <div>
                    <label class="k-label">Régimen Contribuyente</label>
                    <select name="tipo_contribuyente" id="editTipoCont" class="k-input font-medium text-xs">
                        <option value="Ordinario">Contribuyente Ordinario</option>
                        <option value="Especial">Contribuyente Especial</option>
                        <option value="Formal">Contribuyente Formal</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="k-label">Teléfono (WhatsApp)</label>
                    <input type="text" name="telefono" id="editTelefono" class="k-input text-xs">
                </div>
                <div>
                    <label class="k-label">Correo Electrónico</label>
                    <input type="email" name="email" id="editEmail" class="k-input text-xs">
                </div>
            </div>

            <div>
                <label class="k-label">Domicilio Fiscal / Ubicación</label>
                <input type="text" name="direccion" id="editDireccion" class="k-input text-xs">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="k-label">Honorario Mensual ($ USD)</label>
                    <input type="number" step="0.01" name="honorarios_usd" id="editHonorarios" required class="k-input font-mono font-bold text-emerald-600 text-xs">
                </div>
                <div>
                    <label class="k-label">Estado de Solvencia</label>
                    <select name="estatus" id="editEstatus" class="k-input font-medium text-xs">
                        <option value="al dia">Solvente / Al Día</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="mora">En Mora</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('modalEditarClienteCRM').classList.add('hidden')" class="k-btn k-btn-secondary text-xs">
                    Cancelar
                </button>
                <button type="submit" class="k-btn k-btn-primary text-xs">
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================================================================== -->
<!-- SCRIPT: FILTROS, BÚSQUEDA Y CONTROL DE MODALES                       -->
<!-- ==================================================================== -->
<script>
const tasaBcvOficial = <?= (float)$tasaBcvActual ?>;
let estadoActual = 'todos';

function filtrarPorEstado(estado, btn) {
    estadoActual = estado;
    document.querySelectorAll('#filterStatusGroup .filter-pill').forEach(b => {
        b.className = 'filter-pill px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors';
    });
    btn.className = 'filter-pill px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#0F172A] text-white transition-colors';
    aplicarFiltros();
}

function buscarClienteEnVivo() {
    aplicarFiltros();
}

function aplicarFiltros() {
    const q = document.getElementById('buscadorCliente').value.toLowerCase().trim();
    const filas = document.querySelectorAll('#tablaClientesCRM tbody tr.cliente-row');

    filas.forEach(row => {
        const est = row.getAttribute('data-estatus') || '';
        const nom = row.getAttribute('data-nombre') || '';
        const rif = row.getAttribute('data-rif') || '';
        const serv = row.getAttribute('data-servicios') || '';
        const dir = row.getAttribute('data-direccion') || '';

        const coincideEstado = (estadoActual === 'todos' || est === estadoActual);
        const coincideBusqueda = (q === '' || nom.includes(q) || rif.includes(q) || serv.includes(q) || dir.includes(q));

        if (coincideEstado && coincideBusqueda) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function abrirCobroRapido(cli) {
    document.getElementById('cobroClienteId').value = cli.id;
    document.getElementById('cobroClienteNombre').value = cli.razon_social + ' (' + cli.rif + ')';
    const monto = parseFloat(cli.deuda_usd > 0 ? cli.deuda_usd : cli.honorarios_usd);
    document.getElementById('cobroMontoUsd').value = monto.toFixed(2);
    calcularEquivBs(monto);
    document.getElementById('modalCobroRapidoCRM').classList.remove('hidden');
}

function calcularEquivBs(valUsd) {
    const usd = parseFloat(valUsd) || 0;
    const bs = usd * tasaBcvOficial;
    document.getElementById('cobroMontoBs').value = 'Bs. ' + bs.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function abrirEditarCliente(cli) {
    document.getElementById('editClienteId').value = cli.id;
    document.getElementById('editRazonSocial').value = cli.razon_social;
    document.getElementById('editRif').value = cli.rif;
    document.getElementById('editTipoCont').value = cli.tipo_contribuyente || 'Ordinario';
    document.getElementById('editTelefono').value = cli.telefono || '';
    document.getElementById('editEmail').value = cli.email || '';
    document.getElementById('editDireccion').value = cli.direccion || '';
    document.getElementById('editHonorarios').value = parseFloat(cli.honorarios_usd || 0).toFixed(2);
    document.getElementById('editEstatus').value = cli.estatus || 'al dia';
    document.getElementById('modalEditarClienteCRM').classList.remove('hidden');
}

// Abrir modal si viene con parametro GET ?accion=nuevo
if (window.location.search.includes('accion=nuevo')) {
    document.getElementById('modalNuevoClienteCRM').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
