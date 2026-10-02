<?php
/**
 * ====================================================================
 * KONTIFY PRACTICE TRACKER — CRONÓMETRO RÁPIDO (ULTRA-PREMIUM DARK EDITION)
 * Inspirado en la estética Dark Neon Cyan / Deep Midnight del sistema ($99-$299/mo)
 * ====================================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/DespachoController.php';

$db = getDB();
$empresa_id = (int)($_SESSION['empresa_id'] ?? 1);
$tasaBcv    = (float)($_SESSION['tasa_bcv'] ?? 65.50);
$controller = new DespachoController($db, $empresa_id, $tasaBcv);

$mensajeToast = null;
$tipoToast = 'success';

// Manejo de envío rápido POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_tiempo_cronometro') {
    try {
        $idInsertado = $controller->registrarTiempo([
            'cliente_id' => (int)($_POST['cliente_id'] ?? 0),
            'usuario_id' => (int)($_SESSION['user_id'] ?? 1),
            'jerarquia_id' => (int)($_POST['jerarquia_id'] ?? 1),
            'fecha' => $_POST['fecha'] ?? date('Y-m-d'),
            'horas' => floatval(str_replace(',', '.', $_POST['horas'] ?? '0.5')),
            'tipo_tarea' => $_POST['tipo_tarea'] ?? 'Ordinaria',
            'sujeta_contrato' => isset($_POST['sujeta_contrato']) ? 1 : 0,
            'es_facturable' => isset($_POST['es_facturable']) ? 1 : 0,
            'descripcion' => trim($_POST['descripcion'] ?? 'Labor profesional imputada'),
            'gastos_directos_usd' => floatval(str_replace(',', '.', $_POST['gastos_directos_usd'] ?? '0')),
            'origen_registro' => 'Cronometro'
        ]);
        $mensajeToast = "¡Horas imputadas exitosamente en la cuenta del cliente! (#{$idInsertado})";
        $tipoToast = 'success';
    } catch (Exception $e) {
        $mensajeToast = "Error al guardar: " . $e->getMessage();
        $tipoToast = 'error';
    }
}

// Cargar Clientes
$clientes = [];
try {
    $clientes = $db->query("SELECT id, razon_social, rif, tipo_contribuyente, honorarios_usd FROM clientes ORDER BY razon_social ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Cargar Jerarquías
$jerarquias = [];
try {
    $jerarquias = $db->query("SELECT * FROM despacho_jerarquias WHERE activo = 1 ORDER BY nivel_jerarquico ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
if (empty($jerarquias)) {
    $jerarquias = [
        ['id' => 1, 'rol_nombre' => 'Auxiliar Contable', 'tarifa_costo_hora_usd' => 4.50, 'tarifa_cobro_hora_usd' => 15.00],
        ['id' => 2, 'rol_nombre' => 'Asistente Contable', 'tarifa_costo_hora_usd' => 8.00, 'tarifa_cobro_hora_usd' => 25.00],
        ['id' => 3, 'rol_nombre' => 'Contador Senior', 'tarifa_costo_hora_usd' => 15.00, 'tarifa_cobro_hora_usd' => 45.00],
        ['id' => 4, 'rol_nombre' => 'Gerente de Auditoría', 'tarifa_costo_hora_usd' => 25.00, 'tarifa_cobro_hora_usd' => 70.00],
        ['id' => 5, 'rol_nombre' => 'Socio Director (CPA)', 'tarifa_costo_hora_usd' => 45.00, 'tarifa_cobro_hora_usd' => 120.00],
    ];
}

// Últimos registros de hoy
$ultimosRegistros = [];
try {
    $stmtUlt = $db->prepare("
        SELECT h.*, c.razon_social, j.rol_nombre 
        FROM despacho_horas h
        JOIN clientes c ON h.cliente_id = c.id
        JOIN despacho_jerarquias j ON h.jerarquia_id = j.id
        WHERE h.fecha = CURDATE()
        ORDER BY h.id DESC LIMIT 5
    ");
    $stmtUlt->execute();
    $ultimosRegistros = $stmtUlt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontify Practice Tracker — Cronómetro & Time-Tracking</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        darkbg: '#040814',
                        darkcard: '#081426',
                        kaccent: '#0284C7',
                        neoncyan: '#00F0FF',
                        kprimary: '#00B894'
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace']
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        body {
            background-color: #040814;
            background-image: 
                radial-gradient(circle at 50% 0%, rgba(2, 132, 199, 0.18) 0%, transparent 60%),
                radial-gradient(circle at 100% 100%, rgba(0, 184, 148, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 0% 50%, rgba(6, 182, 212, 0.08) 0%, transparent 40%);
            background-attachment: fixed;
            color: #F8FAFC;
        }

        .glass-premium {
            background: linear-gradient(180deg, rgba(8, 22, 42, 0.88) 0%, rgba(4, 12, 24, 0.96) 100%);
            border: 1.5px solid rgba(56, 189, 248, 0.35);
            box-shadow: 0 10px 35px -5px rgba(0, 0, 0, 0.7), 0 0 25px -5px rgba(2, 132, 199, 0.25);
            backdrop-filter: blur(16px);
        }

        .floating-pill {
            background: linear-gradient(180deg, #0284C7 0%, #0369A1 100%);
            border: 1px solid #38BDF8;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.45);
        }
    </style>
</head>
<body class="min-h-screen p-4 sm:p-6 md:p-8 flex items-center justify-center font-sans antialiased">

<div class="w-full max-w-4xl mx-auto space-y-6">

    <!-- Toast de Notificación -->
    <?php if ($mensajeToast): ?>
    <div id="toastNotification" class="flex items-center justify-between p-4 rounded-2xl border <?= $tipoToast === 'success' ? 'bg-emerald-950/80 border-emerald-500/50 text-emerald-300' : 'bg-rose-950/80 border-rose-500/50 text-rose-300' ?> backdrop-blur-md shadow-xl transition-all">
        <div class="flex items-center space-x-3">
            <i class="fa-solid <?= $tipoToast === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-triangle-exclamation text-rose-400' ?> text-lg"></i>
            <span class="text-sm font-semibold"><?= htmlspecialchars($mensajeToast) ?></span>
        </div>
        <button onclick="document.getElementById('toastNotification').remove()" class="text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <?php endif; ?>

    <!-- Encabezado Estilo Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-cyan-500/20 pb-5">
        <div class="flex items-center space-x-3">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-cyan-500 to-kaccent flex items-center justify-center text-darkbg shadow-lg shadow-cyan-500/30">
                <i class="fa-solid fa-stopwatch text-2xl"></i>
            </div>
            <div>
                <div class="flex items-center space-x-2">
                    <h1 class="text-2xl font-black text-white tracking-tight">KONTIFY PRACTICE TRACKER</h1>
                    <span class="floating-pill text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full text-white">PRO</span>
                </div>
                <p class="text-xs text-slate-400">Control de horas-hombre, costos operativos y anexos de facturación en Venezuela.</p>
            </div>
        </div>
        <div class="flex items-center space-x-2">
            <a href="despacho.php" class="px-4 py-2 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-xs font-bold text-cyan-300 border border-cyan-500/30 transition flex items-center gap-2">
                <i class="fa-solid fa-layer-group"></i> Suite Completa Despacho
            </a>
        </div>
    </div>

    <!-- Contenedor Principal: Widget Cronómetro + Formulario -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Columna Izquierda: Cronómetro Digital LED & Mandos -->
        <div class="lg:col-span-5 glass-premium rounded-3xl p-6 sm:p-7 flex flex-col justify-between relative overflow-hidden">
            <div class="absolute -top-16 -right-16 w-44 h-44 bg-cyan-500/15 rounded-full blur-3xl pointer-events-none"></div>

            <div>
                <!-- Badge Superior -->
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                        <span id="statusLed" class="w-2.5 h-2.5 rounded-full bg-slate-500"></span>
                        <span id="statusText">Listo</span>
                    </span>
                    <span class="text-xs font-mono px-2.5 py-1 rounded-lg bg-darkbg border border-cyan-500/30 text-cyan-300">
                        <i class="fa-solid fa-clock text-cyan-400 mr-1"></i> Auto-Sync
                    </span>
                </div>

                <!-- Display LED -->
                <div class="text-center my-6 py-6 bg-darkbg/95 rounded-2xl border border-cyan-500/40 shadow-inner">
                    <div id="timerDisplay" class="font-mono text-5xl sm:text-6xl font-black tracking-tight text-white select-all">
                        00:00:00
                    </div>
                    <div class="mt-2 text-xs font-mono text-cyan-400 font-bold">
                        ≈ <span id="decimalHoursDisplay">0.00</span> Horas Incurridas
                    </div>
                </div>

                <!-- Botonera -->
                <div class="grid grid-cols-3 gap-2.5">
                    <button type="button" id="btnStart" onclick="startTimer()" class="py-3 px-3 rounded-xl bg-gradient-to-r from-cyan-500 to-kaccent hover:brightness-110 text-darkbg font-black text-xs uppercase tracking-wider flex items-center justify-center gap-1.5 shadow-lg shadow-cyan-500/30 transition transform active:scale-95">
                        <i class="fa-solid fa-play"></i> Iniciar
                    </button>
                    <button type="button" id="btnPause" onclick="pauseTimer()" disabled class="py-3 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-1.5 transition disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fa-solid fa-pause"></i> Pausar
                    </button>
                    <button type="button" id="btnReset" onclick="resetTimer()" class="py-3 px-3 rounded-xl bg-darkbg border border-slate-700/80 hover:border-rose-500/50 hover:text-rose-400 text-slate-400 font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-1.5 transition">
                        <i class="fa-solid fa-rotate-left"></i> Cero
                    </button>
                </div>
            </div>

            <!-- Estimación en Vivo -->
            <div class="mt-6 pt-5 border-t border-cyan-500/20 space-y-2.5 text-xs">
                <div class="flex justify-between items-center text-slate-400">
                    <span>Costo Nómina Imputado:</span>
                    <span id="previewCosto" class="font-mono font-bold text-white">$0.00 USD</span>
                </div>
                <div class="flex justify-between items-center text-slate-400">
                    <span>Tarifa Cobro Estimada:</span>
                    <span id="previewCobro" class="font-mono font-bold text-cyan-300">$0.00 USD</span>
                </div>
                <div class="flex justify-between items-center text-slate-400">
                    <span>Equivalente en Bolívares (BCV):</span>
                    <span id="previewBs" class="font-mono font-bold text-slate-300">Bs. 0,00</span>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Formulario de Imputación -->
        <div class="lg:col-span-7 glass-premium rounded-3xl p-6 sm:p-7">
            <form method="POST" id="timeTrackingForm" class="space-y-4">
                <input type="hidden" name="action" value="guardar_tiempo_cronometro">
                <input type="hidden" name="horas" id="inputHoras" value="0.00">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Cliente -->
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            Cliente Asignado <span class="text-cyan-400">*</span>
                        </label>
                        <div class="relative">
                            <select name="cliente_id" id="clienteSelect" required class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-400 transition cursor-pointer appearance-none">
                                <option value="">-- Seleccionar Razón Social / RIF --</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option value="<?= $c['id'] ?>">
                                        <?= htmlspecialchars($c['razon_social']) ?> (<?= htmlspecialchars($c['rif']) ?>) — [<?= $c['tipo_contribuyente'] ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-3.5 text-slate-500 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <!-- Jerarquía -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            Rol Operativo <span class="text-cyan-400">*</span>
                        </label>
                        <select name="jerarquia_id" id="jerarquiaSelect" onchange="actualizarTarifasSnapshot()" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-400 transition cursor-pointer">
                            <?php foreach ($jerarquias as $j): ?>
                                <option value="<?= $j['id'] ?>" data-costo="<?= $j['tarifa_costo_hora_usd'] ?>" data-cobro="<?= $j['tarifa_cobro_hora_usd'] ?>">
                                    <?= htmlspecialchars($j['rol_nombre']) ?> ($<?= number_format($j['tarifa_costo_hora_usd'], 2) ?> / $<?= number_format($j['tarifa_cobro_hora_usd'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fecha -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">Fecha de Labor</label>
                        <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-400 transition">
                    </div>
                </div>

                <!-- Facturación y Naturaleza -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 bg-darkbg/80 rounded-2xl border border-cyan-500/20">
                    <div>
                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Naturaleza</label>
                        <select name="tipo_tarea" id="tipoTareaSelect" onchange="verificarNaturalezaTarea()" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-cyan-400">
                            <option value="Ordinaria">Ordinaria (Fee Mensual)</option>
                            <option value="Extraordinaria">Extraordinaria (Especial)</option>
                        </select>
                    </div>
                    <div class="flex items-center pt-3">
                        <label class="flex items-center space-x-2 text-xs text-slate-300 cursor-pointer">
                            <input type="checkbox" name="sujeta_contrato" id="sujetaContratoCheck" checked class="w-4 h-4 rounded bg-darkbg border-slate-600 text-cyan-500 focus:ring-0">
                            <span>En Contrato</span>
                        </label>
                    </div>
                    <div class="flex items-center pt-3">
                        <label class="flex items-center space-x-2 text-xs text-slate-300 cursor-pointer">
                            <input type="checkbox" name="es_facturable" id="esFacturableCheck" checked class="w-4 h-4 rounded bg-darkbg border-slate-600 text-cyan-500 focus:ring-0">
                            <span>Es Facturable</span>
                        </label>
                    </div>
                </div>

                <!-- Gastos Directos Reembolsables -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5 flex justify-between">
                        <span>Gastos Directos Reembolsables (USD)</span>
                        <span class="text-[11px] text-slate-400 font-normal">Aranceles, traslados, papelería</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-2.5 text-slate-500 text-sm font-mono">$</span>
                        <input type="number" step="0.01" min="0" name="gastos_directos_usd" value="0.00" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl pl-8 pr-4 py-2 text-sm text-white focus:outline-none focus:border-cyan-400 transition font-mono">
                    </div>
                </div>

                <!-- Descripción -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                        Descripción de la Labor <span class="text-cyan-400">*</span>
                    </label>
                    <textarea name="descripcion" id="inputDescripcion" rows="2" required placeholder="Ej: Elaboración de declaración de IVA, cuadre de retenciones y carga en portal SENIAT..." class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-400 transition placeholder-slate-500"></textarea>
                    
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <button type="button" onclick="insertTag('Declaración IVA Portal SENIAT')" class="px-2.5 py-1 rounded-lg bg-cyan-950/60 border border-cyan-500/30 text-[11px] text-cyan-300 hover:bg-cyan-900/80 transition">#IVA SENIAT</button>
                        <button type="button" onclick="insertTag('Libro Compras/Ventas')" class="px-2.5 py-1 rounded-lg bg-cyan-950/60 border border-cyan-500/30 text-[11px] text-cyan-300 hover:bg-cyan-900/80 transition">#Libros Fiscales</button>
                        <button type="button" onclick="insertTag('Nómina Quincenal LOTTT')" class="px-2.5 py-1 rounded-lg bg-cyan-950/60 border border-cyan-500/30 text-[11px] text-cyan-300 hover:bg-cyan-900/80 transition">#Nómina LOTTT</button>
                        <button type="button" onclick="insertTag('Retenciones ISLR en TXT')" class="px-2.5 py-1 rounded-lg bg-cyan-950/60 border border-cyan-500/30 text-[11px] text-cyan-300 hover:bg-cyan-900/80 transition">#Retenciones ISLR</button>
                        <button type="button" onclick="insertTag('Balance General VEN-NIF')" class="px-2.5 py-1 rounded-lg bg-cyan-950/60 border border-cyan-500/30 text-[11px] text-cyan-300 hover:bg-cyan-900/80 transition">#VEN-NIF</button>
                    </div>
                </div>

                <!-- Botón de Guardar -->
                <button type="submit" class="w-full py-4 rounded-xl bg-gradient-to-r from-cyan-500 via-kaccent to-kprimary hover:brightness-110 text-darkbg font-black text-sm uppercase tracking-wider flex items-center justify-center gap-2 shadow-xl shadow-cyan-500/25 transition transform active:scale-[0.99]">
                    <i class="fa-solid fa-floppy-disk text-base"></i> Guardar e Imputar Horas al Cliente
                </button>
            </form>
        </div>
    </div>

    <!-- Tabla: Últimas Imputaciones de Hoy -->
    <div class="glass-premium rounded-3xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-extrabold uppercase tracking-wide text-white flex items-center gap-2">
                <i class="fa-solid fa-list-check text-cyan-400"></i> Imputaciones Registradas Hoy (<?= date('d/m/Y') ?>)
            </h3>
            <span class="text-xs text-slate-400 font-mono">Total: <?= count($ultimosRegistros) ?> registros</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="text-slate-400 border-b border-cyan-500/30 uppercase font-semibold">
                    <tr>
                        <th class="py-2.5 px-3">Cliente</th>
                        <th class="py-2.5 px-3">Rol</th>
                        <th class="py-2.5 px-3">Tarea</th>
                        <th class="py-2.5 px-3 text-center">Horas</th>
                        <th class="py-2.5 px-3 text-right">Tarifa Cobro</th>
                        <th class="py-2.5 px-3 text-center">Tipo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-300">
                    <?php if (empty($ultimosRegistros)): ?>
                        <tr><td colspan="6" class="py-6 text-center text-slate-500 italic">No hay registros de tiempo imputados hoy todavía.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ultimosRegistros as $u): ?>
                        <tr>
                            <td class="py-2.5 px-3 font-semibold text-white"><?= htmlspecialchars($u['razon_social']) ?></td>
                            <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded bg-darkbg border border-slate-700 text-[10px] text-cyan-300 font-mono"><?= htmlspecialchars($u['rol_nombre']) ?></span></td>
                            <td class="py-2.5 px-3 max-w-[220px] truncate" title="<?= htmlspecialchars($u['descripcion']) ?>"><?= htmlspecialchars($u['descripcion']) ?></td>
                            <td class="py-2.5 px-3 text-center font-mono font-bold text-cyan-300"><?= number_format($u['horas'], 2) ?> h</td>
                            <td class="py-2.5 px-3 text-right font-mono text-emerald-400 font-bold">$<?= number_format($u['tarifa_cobro_aplicada_usd'], 2) ?></td>
                            <td class="py-2.5 px-3 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] <?= $u['tipo_tarea'] === 'Extraordinaria' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30' : 'bg-slate-800 text-slate-400' ?>">
                                    <?= htmlspecialchars($u['tipo_tarea']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    let timerInterval = null;
    let totalSeconds = 0;
    let isRunning = false;
    const tasaBcv = <?= (float)$tasaBcv ?>;

    function formatTime(seconds) {
        const hrs = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return [
            hrs.toString().padStart(2, '0'),
            mins.toString().padStart(2, '0'),
            secs.toString().padStart(2, '0')
        ].join(':');
    }

    function updateDisplay() {
        document.getElementById('timerDisplay').innerText = formatTime(totalSeconds);
        const decHours = (totalSeconds / 3600).toFixed(2);
        document.getElementById('decimalHoursDisplay').innerText = decHours;
        document.getElementById('inputHoras').value = decHours;
        actualizarTarifasSnapshot();
    }

    function startTimer() {
        if (isRunning) return;
        isRunning = true;
        document.getElementById('btnStart').disabled = true;
        document.getElementById('btnStart').classList.add('opacity-40');
        document.getElementById('btnPause').disabled = false;
        
        const statusLed = document.getElementById('statusLed');
        statusLed.className = 'w-2.5 h-2.5 rounded-full bg-cyan-400 animate-ping';
        document.getElementById('statusText').innerText = 'Contando';
        document.getElementById('statusText').className = 'text-cyan-400 font-bold';

        timerInterval = setInterval(() => {
            totalSeconds++;
            updateDisplay();
        }, 1000);
    }

    function pauseTimer() {
        if (!isRunning) return;
        isRunning = false;
        clearInterval(timerInterval);
        
        document.getElementById('btnStart').disabled = false;
        document.getElementById('btnStart').classList.remove('opacity-40');
        document.getElementById('btnPause').disabled = true;

        const statusLed = document.getElementById('statusLed');
        statusLed.className = 'w-2.5 h-2.5 rounded-full bg-amber-400';
        document.getElementById('statusText').innerText = 'Pausado';
        document.getElementById('statusText').className = 'text-amber-400 font-bold';
    }

    function resetTimer() {
        pauseTimer();
        if (totalSeconds > 0 && !confirm('¿Reiniciar el cronómetro a cero?')) return;
        totalSeconds = 0;
        updateDisplay();
        document.getElementById('statusLed').className = 'w-2.5 h-2.5 rounded-full bg-slate-500';
        document.getElementById('statusText').innerText = 'Listo';
        document.getElementById('statusText').className = 'text-slate-400';
    }

    function actualizarTarifasSnapshot() {
        const jerSelect = document.getElementById('jerarquiaSelect');
        const selectedOption = jerSelect.options[jerSelect.selectedIndex];
        const costoHora = parseFloat(selectedOption.getAttribute('data-costo') || 0);
        const cobroHora = parseFloat(selectedOption.getAttribute('data-cobro') || 0);

        const decHours = parseFloat(document.getElementById('inputHoras').value || 0);
        const costoTotal = (decHours * costoHora).toFixed(2);
        const cobroTotal = (decHours * cobroHora).toFixed(2);
        const totalBs = (cobroTotal * tasaBcv).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        document.getElementById('previewCosto').innerText = `$${costoTotal} USD`;
        document.getElementById('previewCobro').innerText = `$${cobroTotal} USD`;
        document.getElementById('previewBs').innerText = `Bs. ${totalBs}`;
    }

    function verificarNaturalezaTarea() {
        const tipo = document.getElementById('tipoTareaSelect').value;
        const sujetaCheck = document.getElementById('sujetaContratoCheck');
        if (tipo === 'Extraordinaria') {
            sujetaCheck.checked = false;
        } else {
            sujetaCheck.checked = true;
        }
    }

    function insertTag(tag) {
        const desc = document.getElementById('inputDescripcion');
        if (desc.value.trim() === '') {
            desc.value = tag + ': ';
        } else {
            desc.value += ' | ' + tag;
        }
        desc.focus();
    }

    document.getElementById('timeTrackingForm').addEventListener('submit', function(e) {
        let horas = parseFloat(document.getElementById('inputHoras').value);
        if (horas <= 0) {
            const manual = prompt('El cronómetro está en cero. Escribe las horas manuales a imputar (ej: 1.5):', '1.0');
            if (manual && !isNaN(parseFloat(manual))) {
                document.getElementById('inputHoras').value = parseFloat(manual);
            } else {
                e.preventDefault();
                alert('Debes ingresar una cantidad de horas válida.');
                return;
            }
        }
    });

    updateDisplay();
</script>

</body>
</html>
