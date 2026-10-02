<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$db = getDB();
$empresa_id = (int)($_SESSION['empresa_id'] ?? 1);
$despacho = obtenerConfiguracionDespacho($db);
$tasaBcvActual = $_SESSION['tasa_bcv'] ?? 65.50;

$mensajeAlerta = '';
$tipoAlerta = '';

// PROCESAR GUARDADO DE COTIZACIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_cotizacion']) && $_POST['accion_cotizacion'] === 'guardar') {
    $numCot = trim($_POST['numero_cotizacion'] ?? 'COT-' . date('Y') . '-' . rand(100, 999));
    $clienteId = !empty($_POST['cliente_id']) ? intval($_POST['cliente_id']) : null;
    $prospectoNombre = trim($_POST['prospecto_nombre'] ?? '');
    $prospectoRif = strtoupper(trim($_POST['prospecto_rif'] ?? ''));
    $prospectoEmail = trim($_POST['prospecto_email'] ?? '');
    $prospectoTel = trim($_POST['prospecto_telefono'] ?? '');
    $serviciosJson = $_POST['servicios_json'] ?? '[]';
    $subtotalUsd = floatval(str_replace(',', '.', $_POST['subtotal_usd'] ?? '0'));
    $descuentoUsd = floatval(str_replace(',', '.', $_POST['descuento_usd'] ?? '0'));
    $totalBcv = floatval(str_replace(',', '.', $_POST['total_bcv'] ?? '0'));
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $validezDias = intval($_POST['validez_dias'] ?? 7);
    $observaciones = trim($_POST['observaciones'] ?? '');
    $estado = $_POST['estado'] ?? 'Borrador';

    if (!empty($numCot) && !empty($prospectoNombre)) {
        try {
            $stmt = $db->prepare("INSERT INTO cotizaciones 
                (empresa_id, numero_cotizacion, cliente_id, prospecto_nombre, prospecto_rif, prospecto_email, prospecto_telefono, servicios_json, subtotal_usd, descuento_usd, tasa_bcv, total_bcv, fecha, validez_dias, observaciones, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $empresa_id, $numCot, $clienteId, $prospectoNombre, $prospectoRif, $prospectoEmail, $prospectoTel,
                $serviciosJson, $subtotalUsd, $descuentoUsd, $tasaBcvActual, $totalBcv, $fecha, $validezDias, $observaciones, $estado
            ]);

            $mensajeAlerta = "Propuesta <strong>{$numCot}</strong> guardada con éxito en estado <strong>{$estado}</strong>.";
            $tipoAlerta = 'success';
        } catch (PDOException $e) {
            $mensajeAlerta = "Error al guardar cotización: " . $e->getMessage();
            $tipoAlerta = 'error';
        }
    }
}

// ACTUALIZAR ESTADO DE COTIZACIÓN VÍA GET O POST RÁPIDO
if (isset($_GET['cambiar_estado']) && isset($_GET['id'])) {
    $cId = intval($_GET['id']);
    $nEst = $_GET['cambiar_estado'];
    if (in_array($nEst, ['Borrador', 'Enviado', 'Aceptado', 'Rechazado'])) {
        $db->prepare("UPDATE cotizaciones SET estado = ? WHERE id = ?")->execute([$nEst, $cId]);
        $mensajeAlerta = "Estado de la cotización actualizado a: <strong>{$nEst}</strong>";
        $tipoAlerta = 'success';
    }
}

// CARGAR CATÁLOGO DE SERVICIOS
$servicios = [];
try {
    $stmtS = $db->query("SELECT * FROM servicios WHERE activo = 1 ORDER BY nombre ASC");
    if ($stmtS) $servicios = $stmtS->fetchAll();
} catch (Exception $e) {}

// CARGAR CLIENTES PARA SELECTOR
$clientesList = [];
try {
    $stmtC = $db->query("SELECT id, razon_social, rif, telefono, email FROM clientes ORDER BY razon_social ASC");
    if ($stmtC) $clientesList = $stmtC->fetchAll();
} catch (Exception $e) {}

// CARGAR HISTORIAL DE COTIZACIONES
$cotizaciones = [];
try {
    $stmtCot = $db->query("SELECT * FROM cotizaciones ORDER BY id DESC");
    if ($stmtCot) $cotizaciones = $stmtCot->fetchAll();
} catch (Exception $e) {}

// Número de cotización sugerido
$numeroSugerido = 'COT-' . date('Y') . '-' . str_pad((count($cotizaciones) + 1), 3, '0', STR_PAD_LEFT);

$pageTitle = "Cotizador Inteligente — Kontify OS";
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

<!-- HEADER COTIZADOR -->
<div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 mb-8">
    <div>
        <div class="flex items-center gap-2 mb-1.5">
            <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200/60 px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Emisión Comercial
            </span>
            <span class="text-xs text-slate-400 font-medium">&bull; Propuestas de Honorarios Profesionales</span>
        </div>
        <h1 class="text-2xl lg:text-3xl font-extrabold text-[#0F172A] tracking-tight">Cotizador de Servicios Contables</h1>
        <p class="text-xs lg:text-sm text-[#64748B] mt-1 font-medium">Crea propuestas corporativas membretadas, configura precios, aplica descuentos y genera PDF en tiempo real.</p>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3">
        <button type="button" onclick="imprimirCotizacion()" class="k-btn k-btn-secondary text-xs">
            <i class="fa-solid fa-print text-slate-500"></i>
            <span>Imprimir / Guardar PDF</span>
        </button>
        <button type="button" onclick="document.getElementById('formCotizador').submit()" class="k-btn k-btn-primary text-xs shadow-sm">
            <i class="fa-solid fa-floppy-disk text-xs"></i>
            <span>Guardar Propuesta</span>
        </button>
    </div>
</div>

<!-- ==================================================================== -->
<!-- BUILDER: PANEL DE CONFIGURACIÓN & VISTA PREVIA PDF EN VIVO          -->
<!-- ==================================================================== -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
    
    <!-- COLUMNA IZQUIERDA (5 COLS): CONFIGURADOR -->
    <div class="lg:col-span-5 space-y-6">
        
        <!-- Tarjeta 1: Datos de la Propuesta & Cliente -->
        <div class="k-card p-6">
            <h3 class="text-sm font-bold text-[#0F172A] mb-4 pb-2 border-b border-slate-100 flex items-center justify-between">
                <span>1. Datos de la Propuesta</span>
                <span class="text-xs font-mono font-bold text-slate-400"><?= $numeroSugerido ?></span>
            </h3>

            <form id="formCotizador" method="POST" class="space-y-4">
                <input type="hidden" name="accion_cotizacion" value="guardar">
                <input type="hidden" name="numero_cotizacion" id="hiddenNumeroCot" value="<?= $numeroSugerido ?>">
                <input type="hidden" name="servicios_json" id="hiddenServiciosJson" value="[]">
                <input type="hidden" name="subtotal_usd" id="hiddenSubtotalUsd" value="0.00">
                <input type="hidden" name="descuento_usd" id="hiddenDescuentoUsd" value="0.00">
                <input type="hidden" name="total_bcv" id="hiddenTotalBcv" value="0.00">

                <!-- Estado de la Propuesta Requerido -->
                <div>
                    <label class="k-label">Estado de la Propuesta</label>
                    <select name="estado" id="selectEstadoPropuesta" onchange="actualizarVistaPrevia()" class="k-input font-bold text-xs py-2">
                        <option value="Borrador">● Borrador (Draft)</option>
                        <option value="Enviado" selected>● Enviado (En Negociación)</option>
                        <option value="Aceptado">● Aceptado (Ganada / Cerrada)</option>
                        <option value="Rechazado">● Rechazado (Perdida)</option>
                    </select>
                </div>

                <!-- Selector de Cliente o Prospecto -->
                <div>
                    <label class="k-label">Cliente Registrado (Opcional)</label>
                    <select name="cliente_id" id="selectClienteExistente" onchange="cargarDatosCliente(this)" class="k-input text-xs font-medium">
                        <option value="">-- Ingresar prospecto manualmente --</option>
                        <?php foreach ($clientesList as $cl): ?>
                            <option value="<?= $cl['id'] ?>" data-nombre="<?= htmlspecialchars($cl['razon_social']) ?>" data-rif="<?= htmlspecialchars($cl['rif']) ?>" data-tel="<?= htmlspecialchars($cl['telefono']) ?>" data-email="<?= htmlspecialchars($cl['email']) ?>">
                                <?= htmlspecialchars($cl['razon_social']) ?> (<?= htmlspecialchars($cl['rif']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="k-label">Razón Social del Prospecto / Cliente</label>
                    <input type="text" name="prospecto_nombre" id="inputProspectoNombre" placeholder="Ej: Comercializadora Los Andes, C.A." required oninput="actualizarVistaPrevia()" class="k-input font-medium">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="k-label">RIF / ID Fiscal</label>
                        <input type="text" name="prospecto_rif" id="inputProspectoRif" placeholder="J-12345678-0" oninput="actualizarVistaPrevia()" class="k-input font-mono font-bold uppercase">
                    </div>
                    <div>
                        <label class="k-label">Días de Validez</label>
                        <select name="validez_dias" id="inputValidez" onchange="actualizarVistaPrevia()" class="k-input">
                            <option value="5">5 días hábiles</option>
                            <option value="7" selected>7 días hábiles</option>
                            <option value="15">15 días continuos</option>
                            <option value="30">30 días</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="k-label">Teléfono (WhatsApp)</label>
                        <input type="text" name="prospecto_telefono" id="inputProspectoTel" placeholder="+58 414..." class="k-input">
                    </div>
                    <div>
                        <label class="k-label">Email Prospecto</label>
                        <input type="email" name="prospecto_email" id="inputProspectoEmail" placeholder="contacto@empresa.com" class="k-input">
                    </div>
                </div>

                <div>
                    <label class="k-label">Observaciones o Condiciones Especiales</label>
                    <textarea name="observaciones" id="inputObservaciones" rows="2" placeholder="Honorarios sujetos a tasa oficial BCV del día de facturación..." oninput="actualizarVistaPrevia()" class="k-input text-xs"></textarea>
                </div>
            </form>
        </div>

        <!-- Tarjeta 2: Catálogo de Servicios con Precios Configurables y Descuentos -->
        <div class="k-card p-6">
            <h3 class="text-sm font-bold text-[#0F172A] mb-4 pb-2 border-b border-slate-100 flex items-center justify-between">
                <span>2. Catálogo de Servicios</span>
                <span class="text-xs text-slate-400">Click para añadir</span>
            </h3>

            <!-- Buscador Rápido del Catálogo -->
            <div class="space-y-2 mb-4 max-h-56 overflow-y-auto pr-1">
                <?php foreach ($servicios as $srv): ?>
                    <button type="button" onclick="agregarServicioACotizacion('<?= htmlspecialchars(addslashes($srv['nombre'])) ?>', <?= (float)$srv['precio_sugerido_usd'] ?>, '<?= $srv['tipo'] ?>')" class="w-full p-2.5 rounded-xl border border-slate-200/70 hover:border-[#00B894] hover:bg-emerald-50/40 text-left transition-all flex items-center justify-between group">
                        <div class="truncate pr-2">
                            <div class="text-xs font-bold text-[#0F172A] group-hover:text-emerald-700 truncate"><?= htmlspecialchars($srv['nombre']) ?></div>
                            <span class="text-[10px] text-slate-400 uppercase"><?= $srv['tipo'] ?></span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="font-mono font-extrabold text-xs text-[#0F172A]">$<?= number_format($srv['precio_sugerido_usd'], 2) ?></span>
                            <span class="w-6 h-6 rounded-lg bg-slate-100 group-hover:bg-[#00B894] group-hover:text-white flex items-center justify-center text-xs text-slate-500 transition-colors">
                                <i class="fa-solid fa-plus text-[10px]"></i>
                            </span>
                        </div>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Descuento Configurable -->
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-[#0F172A]">Descuento Comercial</span>
                    <span class="text-[10px] text-slate-400 font-mono">En USD</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-bold text-slate-400 text-xs">$</span>
                        <input type="number" step="0.01" id="inputDescuentoMonto" value="0.00" oninput="recalcularTotales()" class="k-input pl-7 text-xs font-bold font-mono">
                    </div>
                    <button type="button" onclick="aplicarDescuentoRapido(10)" class="k-btn k-btn-secondary text-[10px] py-1.5 px-2">10%</button>
                    <button type="button" onclick="aplicarDescuentoRapido(15)" class="k-btn k-btn-secondary text-[10px] py-1.5 px-2">15%</button>
                    <button type="button" onclick="aplicarDescuentoRapido(0)" class="k-btn k-btn-secondary text-[10px] py-1.5 px-2 text-rose-500">0%</button>
                </div>
            </div>

        </div>

    </div>

    <!-- COLUMNA DERECHA (7 COLS): VISTA PREVIA PDF PROFESIONAL MEMBRETADO EN VIVO -->
    <div class="lg:col-span-7">
        
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold uppercase tracking-wider text-[#64748B] flex items-center gap-2">
                <i class="fa-solid fa-eye text-[#00B894]"></i> Vista Previa en Vivo (Formato Membretado A4)
            </span>
            <div class="flex items-center gap-2">
                <button type="button" onclick="enviarPropuestaWhatsApp()" class="k-btn k-btn-secondary text-xs text-emerald-700 hover:text-emerald-800">
                    <i class="fa-brands fa-whatsapp text-sm text-emerald-600"></i>
                    <span>WhatsApp</span>
                </button>
                <button type="button" onclick="imprimirCotizacion()" class="k-btn k-btn-secondary text-xs">
                    <i class="fa-solid fa-file-pdf text-rose-600"></i>
                    <span>Descargar PDF</span>
                </button>
            </div>
        </div>

        <!-- HOJA DE MEMBRETE PDF DE ALTA GAMA ($299/mo tier) -->
        <div id="hojaCotizacionPdf" class="bg-white rounded-2xl border border-slate-200/90 shadow-xl p-8 lg:p-10 relative overflow-hidden text-slate-800 transition-all">
            
            <!-- Marca de Agua de Fondo Ligera -->
            <div class="absolute right-0 top-0 w-80 h-80 bg-gradient-to-bl from-slate-50 via-transparent to-transparent pointer-events-none rounded-bl-full"></div>

            <!-- Membrete Despacho Header -->
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 pb-6 border-b border-slate-200">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-[#00B894] to-[#2563EB] text-white font-extrabold text-2xl flex items-center justify-center shadow-md">
                        K
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-[#0F172A] tracking-tight leading-tight">
                            <?= htmlspecialchars($despacho['nombre_despacho'] ?? 'Despacho Contable') ?>
                        </h2>
                        <div class="text-[11px] text-slate-500 font-mono mt-0.5">
                            RIF: <?= htmlspecialchars($despacho['rif'] ?? 'J-50123891-2') ?> &bull; CPC: <?= htmlspecialchars($despacho['cpc_numero'] ?? 'CPC-102948') ?>
                        </div>
                        <div class="text-[10px] text-slate-400">
                            <?= htmlspecialchars($despacho['direccion'] ?? 'Torre Financiera Empresarial') ?> &bull; <?= htmlspecialchars($despacho['telefono'] ?? '+58 414 123 4567') ?>
                        </div>
                    </div>
                </div>

                <div class="text-left sm:text-right">
                    <span id="badgePreviewEstado" class="k-badge k-badge-premium text-[11px] mb-1.5">Enviado</span>
                    <div class="text-xs font-mono font-bold text-[#0F172A]" id="previewNumeroCot"><?= $numeroSugerido ?></div>
                    <div class="text-[11px] text-slate-400 font-medium mt-0.5">Fecha: <?= date('d/m/Y') ?></div>
                    <div class="text-[10px] text-amber-600 font-medium">Válida por <span id="previewValidez">7</span> días hábiles</div>
                </div>
            </div>

            <!-- Datos del Prospecto -->
            <div class="my-6 p-4 rounded-xl bg-slate-50 border border-slate-100 flex flex-col sm:flex-row justify-between gap-4">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block">Propuesta Preparada Para</span>
                    <h3 class="text-sm font-bold text-[#0F172A] mt-0.5" id="previewProspectoNombre">Cliente / Empresa Prospecto</h3>
                    <div class="text-[11px] text-slate-500 font-mono mt-0.5" id="previewProspectoRif">RIF: J-00000000-0</div>
                </div>
                <div class="sm:text-right">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block">Tipo de Contratación</span>
                    <span class="text-xs font-semibold text-[#00B894] mt-0.5 block">Honorarios Profesionales B2B</span>
                    <span class="text-[10px] text-slate-400 font-mono">Tasa Ref. BCV: Bs. <?= number_format($tasaBcvActual, 2, ',', '.') ?></span>
                </div>
            </div>

            <!-- Tabla de Servicios Cotizados -->
            <div class="mb-6">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b-2 border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            <th class="py-2">Descripción del Servicio</th>
                            <th class="py-2 text-center w-20">Tipo</th>
                            <th class="py-2 text-right w-24">Precio USD</th>
                            <th class="py-2 text-right w-12 print:hidden"></th>
                        </tr>
                    </thead>
                    <tbody id="previewListaServicios" class="divide-y divide-slate-100 text-xs text-slate-700">
                        <tr id="previewFilaVacia">
                            <td colspan="4" class="py-8 text-center text-slate-400 text-xs italic">
                                Haz clic en los servicios del catálogo a la izquierda para agregarlos a la propuesta.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Desglose de Totales -->
            <div class="pt-4 border-t-2 border-slate-200 flex flex-col sm:flex-row sm:items-start justify-between gap-6">
                <div class="text-[11px] text-slate-500 space-y-1 max-w-xs">
                    <strong class="text-slate-700 block uppercase text-[10px] tracking-wider mb-1">Cuentas Bancarias Disponibles</strong>
                    <div>&bull; <strong>Pago Móvil:</strong> <?= htmlspecialchars($despacho['datos_pago'] ?? 'Banesco 0134 - 04141234567') ?></div>
                    <div>&bull; <strong>Zelle USD:</strong> pagos@kontify.app</div>
                </div>

                <div class="sm:text-right space-y-1.5 min-w-[200px]">
                    <div class="flex justify-between sm:justify-end gap-6 text-xs text-slate-500">
                        <span>Subtotal:</span>
                        <span class="font-mono font-bold text-[#0F172A]" id="previewSubtotal">$0.00</span>
                    </div>
                    <div class="flex justify-between sm:justify-end gap-6 text-xs text-rose-600">
                        <span>Descuento:</span>
                        <span class="font-mono font-bold" id="previewDescuento">-$0.00</span>
                    </div>
                    <div class="flex justify-between sm:justify-end gap-6 text-base font-extrabold text-[#0F172A] pt-2 border-t border-slate-200">
                        <span>TOTAL USD:</span>
                        <span class="font-mono text-xl text-[#00B894]" id="previewTotalUsd">$0.00</span>
                    </div>
                    <div class="text-xs font-mono font-bold text-[#2563EB]" id="previewTotalBs">
                        ≈ Bs. 0,00
                    </div>
                </div>
            </div>

            <!-- Observaciones / Pie de Página -->
            <div class="mt-8 pt-4 border-t border-dashed border-slate-200 text-[10px] text-slate-400 leading-relaxed flex flex-col sm:flex-row justify-between items-end gap-4">
                <div class="max-w-md" id="previewObservacionesTexto">
                    * Todos los servicios contables y tributarios se rigen conforme a las Normas Internacionales VEN-NIF y la legislación tributaria venezolana vigente.
                </div>
                <div class="text-center sm:text-right">
                    <div class="w-32 h-10 border-b border-slate-400 mx-auto sm:ml-auto mb-1"></div>
                    <span class="font-bold text-[10px] text-slate-600 block">Firma y Sello Profesional</span>
                    <span class="text-[9px] text-slate-400">Contador Público Colegiado</span>
                </div>
            </div>

        </div>

    </div>

</div>

<!-- ==================================================================== -->
<!-- HISTÓRICO DE COTIZACIONES EMITIDAS                                   -->
<!-- ==================================================================== -->
<div class="k-card overflow-hidden">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-bold text-[#0F172A]">Historial de Propuestas Comerciales</h3>
            <p class="text-xs text-[#64748B] mt-0.5">Control de pipeline, conversión y estados de cotización</p>
        </div>
        <span class="text-xs font-mono text-slate-400"><?= count($cotizaciones) ?> propuestas</span>
    </div>

    <div class="overflow-x-auto">
        <table class="k-table">
            <thead>
                <tr>
                    <th>N° Cotización</th>
                    <th>Prospecto / Cliente</th>
                    <th>Fecha</th>
                    <th>Monto USD</th>
                    <th>Equiv. Bs</th>
                    <th>Estado de Propuesta</th>
                    <th class="text-right">Cambiar Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cotizaciones)): ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400 text-xs italic">
                            Aún no has generado cotizaciones. Crea tu primera propuesta arriba.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($cotizaciones as $cot): ?>
                        <tr>
                            <td>
                                <span class="font-mono font-bold text-xs text-[#0F172A]">
                                    <?= htmlspecialchars($cot['numero_cotizacion']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="font-bold text-xs text-[#0F172A]">
                                    <?= htmlspecialchars($cot['prospecto_nombre'] ?: 'Cliente ID ' . $cot['cliente_id']) ?>
                                </div>
                                <span class="text-[10px] font-mono text-slate-400"><?= htmlspecialchars($cot['prospecto_rif'] ?: 'S/RIF') ?></span>
                            </td>
                            <td class="text-xs text-slate-600"><?= date('d/m/Y', strtotime($cot['fecha'])) ?></td>
                            <td>
                                <span class="font-mono font-extrabold text-sm text-[#00B894]">
                                    $<?= number_format($cot['subtotal_usd'] - ($cot['descuento_usd'] ?? 0), 2, ',', '.') ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-xs font-mono text-slate-500">
                                    Bs. <?= number_format($cot['total_bcv'], 2, ',', '.') ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $est = $cot['estado'] ?? 'Borrador';
                                if ($est === 'Aceptado'): ?>
                                    <span class="k-badge k-badge-success text-[10px]">Aceptado</span>
                                <?php elseif ($est === 'Enviado'): ?>
                                    <span class="k-badge k-badge-premium text-[10px]">Enviado</span>
                                <?php elseif ($est === 'Rechazado'): ?>
                                    <span class="k-badge k-badge-danger text-[10px]">Rechazado</span>
                                <?php else: ?>
                                    <span class="k-badge k-badge-neutral text-[10px]">Borrador</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center gap-1">
                                    <a href="<?= BASE_URL ?>/cotizador.php?cambiar_estado=Aceptado&id=<?= $cot['id'] ?>" title="Marcar Aceptado" class="px-2 py-1 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors">
                                        ✓ Ganada
                                    </a>
                                    <a href="<?= BASE_URL ?>/cotizador.php?cambiar_estado=Enviado&id=<?= $cot['id'] ?>" title="Marcar Enviado" class="px-2 py-1 rounded text-[10px] font-bold bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors">
                                        Enviada
                                    </a>
                                    <a href="<?= BASE_URL ?>/cotizador.php?cambiar_estado=Rechazado&id=<?= $cot['id'] ?>" title="Marcar Rechazado" class="px-2 py-1 rounded text-[10px] font-bold bg-rose-50 text-rose-700 hover:bg-rose-100 transition-colors">
                                        ✕
                                    </a>
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
<!-- SCRIPT: LÓGICA INTERACTIVA DEL COTIZADOR Y GENERACIÓN PDF            -->
<!-- ==================================================================== -->
<script>
const tasaBcv = <?= (float)$tasaBcvActual ?>;
let itemsCotizacion = [];

function agregarServicioACotizacion(nombre, precio, tipo) {
    itemsCotizacion.push({
        nombre: nombre,
        precio: parseFloat(precio) || 0,
        tipo: tipo || 'recurrente'
    });
    renderizarItemsPreview();
    recalcularTotales();
}

function eliminarServicio(index) {
    itemsCotizacion.splice(index, 1);
    renderizarItemsPreview();
    recalcularTotales();
}

function renderizarItemsPreview() {
    const tbody = document.getElementById('previewListaServicios');
    if (itemsCotizacion.length === 0) {
        tbody.innerHTML = `<tr id="previewFilaVacia"><td colspan="4" class="py-8 text-center text-slate-400 text-xs italic">Haz clic en los servicios del catálogo a la izquierda para agregarlos a la propuesta.</td></tr>`;
        return;
    }

    let html = '';
    itemsCotizacion.forEach((item, idx) => {
        html += `
        <tr>
            <td class="py-2.5">
                <input type="text" value="${item.nombre}" onchange="itemsCotizacion[${idx}].nombre = this.value; actualizarJsonForm();" class="w-full text-xs font-bold text-[#0F172A] bg-transparent border-b border-transparent hover:border-slate-300 focus:border-[#00B894] outline-none">
            </td>
            <td class="py-2.5 text-center">
                <span class="text-[10px] uppercase font-semibold px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">${item.tipo}</span>
            </td>
            <td class="py-2.5 text-right font-mono font-bold text-xs text-[#0F172A]">
                <div class="flex items-center justify-end gap-1">
                    <span>$</span>
                    <input type="number" step="0.01" value="${item.precio.toFixed(2)}" onchange="itemsCotizacion[${idx}].precio = parseFloat(this.value) || 0; recalcularTotales();" class="w-16 text-right font-mono text-xs font-bold bg-transparent border-b border-transparent hover:border-slate-300 focus:border-[#00B894] outline-none">
                </div>
            </td>
            <td class="py-2.5 text-right print:hidden">
                <button type="button" onclick="eliminarServicio(${idx})" class="text-slate-300 hover:text-rose-500 text-xs"><i class="fa-solid fa-trash-can"></i></button>
            </td>
        </tr>
        `;
    });
    tbody.innerHTML = html;
}

function recalcularTotales() {
    let subtotal = 0;
    itemsCotizacion.forEach(item => subtotal += (parseFloat(item.precio) || 0));

    let descuento = parseFloat(document.getElementById('inputDescuentoMonto').value) || 0;
    if (descuento > subtotal) descuento = subtotal;

    let totalUsd = Math.max(subtotal - descuento, 0);
    let totalBs = totalUsd * tasaBcv;

    // Actualizar vista previa
    document.getElementById('previewSubtotal').innerText = '$' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('previewDescuento').innerText = '-$' + descuento.toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('previewTotalUsd').innerText = '$' + totalUsd.toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('previewTotalBs').innerText = '≈ Bs. ' + totalBs.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Actualizar hidden fields del formulario
    document.getElementById('hiddenSubtotalUsd').value = subtotal.toFixed(2);
    document.getElementById('hiddenDescuentoUsd').value = descuento.toFixed(2);
    document.getElementById('hiddenTotalBcv').value = totalBs.toFixed(2);
    actualizarJsonForm();
}

function aplicarDescuentoRapido(pct) {
    let subtotal = 0;
    itemsCotizacion.forEach(item => subtotal += (parseFloat(item.precio) || 0));
    let desc = (subtotal * pct) / 100;
    document.getElementById('inputDescuentoMonto').value = desc.toFixed(2);
    recalcularTotales();
}

function actualizarJsonForm() {
    document.getElementById('hiddenServiciosJson').value = JSON.stringify(itemsCotizacion);
}

function actualizarVistaPrevia() {
    const nom = document.getElementById('inputProspectoNombre').value.trim() || 'Cliente / Empresa Prospecto';
    const rif = document.getElementById('inputProspectoRif').value.trim() || 'RIF: J-00000000-0';
    const val = document.getElementById('inputValidez').value;
    const est = document.getElementById('selectEstadoPropuesta').value;
    const obs = document.getElementById('inputObservaciones').value.trim();

    document.getElementById('previewProspectoNombre').innerText = nom;
    document.getElementById('previewProspectoRif').innerText = 'RIF: ' + rif;
    document.getElementById('previewValidez').innerText = val;

    // Badge estado
    const bEst = document.getElementById('badgePreviewEstado');
    bEst.innerText = est;
    if (est === 'Aceptado') bEst.className = 'k-badge k-badge-success text-[11px] mb-1.5';
    else if (est === 'Enviado') bEst.className = 'k-badge k-badge-premium text-[11px] mb-1.5';
    else if (est === 'Rechazado') bEst.className = 'k-badge k-badge-danger text-[11px] mb-1.5';
    else bEst.className = 'k-badge k-badge-neutral text-[11px] mb-1.5';

    if (obs) {
        document.getElementById('previewObservacionesTexto').innerText = obs;
    }
}

function cargarDatosCliente(select) {
    const opt = select.options[select.selectedIndex];
    if (opt.value) {
        document.getElementById('inputProspectoNombre').value = opt.getAttribute('data-nombre') || '';
        document.getElementById('inputProspectoRif').value = opt.getAttribute('data-rif') || '';
        document.getElementById('inputProspectoTel').value = opt.getAttribute('data-tel') || '';
        document.getElementById('inputProspectoEmail').value = opt.getAttribute('data-email') || '';
        actualizarVistaPrevia();
    }
}

function imprimirCotizacion() {
    window.print();
}

function enviarPropuestaWhatsApp() {
    const tel = document.getElementById('inputProspectoTel').value;
    const nom = document.getElementById('inputProspectoNombre').value;
    const num = document.getElementById('hiddenNumeroCot').value;
    const total = document.getElementById('previewTotalUsd').innerText;
    const totalBs = document.getElementById('previewTotalBs').innerText;

    let cleanTel = tel.replace(/[^0-9]/g, '');
    if (cleanTel.startsWith('0')) cleanTel = '58' + cleanTel.substring(1);

    const txt = encodeURIComponent(`Estimado(a) ${nom},\n\nLe saludamos desde *<?= htmlspecialchars($despacho['nombre_despacho'] ?? 'Kontify Despacho') ?>*.\n\nAdjuntamos los detalles de su Propuesta de Honorarios Profesionales *${num}* por un monto de *${total} USD* (${totalBs} a tasa oficial BCV).\n\nQuedamos a su entera disposición para cualquier consulta.`);
    window.open(`https://wa.me/${cleanTel}?text=${txt}`, '_blank');
}

// Inicializar con un servicio de ejemplo
document.addEventListener('DOMContentLoaded', () => {
    agregarServicioACotizacion('Declaración de IVA & Libros Fiscales', 60.00, 'recurrente');
    agregarServicioACotizacion('Contabilidad General VEN-NIF', 85.00, 'recurrente');
});
</script>

<style>
@media print {
    body * { visibility: hidden; }
    #hojaCotizacionPdf, #hojaCotizacionPdf * { visibility: visible; }
    #hojaCotizacionPdf {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 20px;
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
