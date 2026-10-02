<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$db = getDB();
$empresa_id = (int)($_SESSION['empresa_id'] ?? 1);
$despacho   = obtenerConfiguracionDespacho($db);
$tasaBcv    = (float)($_SESSION['tasa_bcv'] ?? 65.50);

// ── Crear tabla tiempo_dedicado si no existe ───────────────────────────────
try {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS tiempo_dedicado (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empresa_id INTEGER DEFAULT 1,
            cliente_id INTEGER NOT NULL,
            tarea TEXT NOT NULL,
            categoria TEXT NOT NULL DEFAULT 'Contabilidad',
            horas REAL NOT NULL DEFAULT 1.00,
            fecha TEXT NOT NULL,
            descripcion TEXT NULL,
            facturado INTEGER NOT NULL DEFAULT 0,
            tarifa_hora_usd REAL NOT NULL DEFAULT 0.00,
            creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS `tiempo_dedicado` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `empresa_id` INT DEFAULT 1,
            `cliente_id` INT NOT NULL,
            `tarea` VARCHAR(200) NOT NULL,
            `categoria` VARCHAR(80) NOT NULL DEFAULT 'Contabilidad',
            `horas` DECIMAL(6,2) NOT NULL DEFAULT 1.00,
            `fecha` DATE NOT NULL,
            `descripcion` TEXT NULL,
            `facturado` TINYINT(1) NOT NULL DEFAULT 0,
            `tarifa_hora_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_td_cliente` (`cliente_id`, `fecha`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
} catch (Exception $e) {}

// ── Seed de ejemplo si está vacío ─────────────────────────────────────────
try {
    $cnt = (int)$db->query("SELECT COUNT(*) FROM tiempo_dedicado")->fetchColumn();
    if ($cnt === 0) {
        $db->exec("INSERT INTO tiempo_dedicado (empresa_id, cliente_id, tarea, categoria, horas, fecha, descripcion, facturado, tarifa_hora_usd) VALUES
            (1,1,'Revisión Libros Fiscales IVA','Fiscal',3.5,'".date('Y-m-01')."','Libros de compras y ventas quincena 1',1,45.00),
            (1,1,'Asientos Contables Mensuales','Contabilidad',4.0,'".date('Y-m-05')."','Diario general y mayor',1,45.00),
            (1,2,'Declaración IVA Portal SENIAT','Fiscal',2.0,'".date('Y-m-03')."','Envío de declaración ordinaria',1,55.00),
            (1,2,'Revisión Facturación Multimoneda','Contabilidad',5.5,'".date('Y-m-07')."','Conciliación en divisas USD/EUR',1,55.00),
            (1,2,'Auditoría Preventiva de Libros','Auditoría',6.0,'".date('Y-m-10')."','Revisión exhaustiva retenciones',0,70.00),
            (1,3,'Nómina Quincena 1 LOTTT','Nómina',3.0,'".date('Y-m-14')."','Cálculo nómina + IVSS/FAOV',1,40.00),
            (1,4,'Declaración ISLR Estimada','Fiscal',2.5,'".date('Y-m-04')."','Pago de anticipo estimado',1,50.00),
            (1,4,'Cierre Contable Mensual','Contabilidad',3.5,'".date('Y-m-25')."','Balance de comprobación',0,50.00),
            (1,5,'Certificación de Ingresos CPC','Asesoría',1.5,'".date('Y-m-15')."','Para trámite bancario',1,60.00),
            (1,6,'Estados Financieros Auditados','Auditoría',8.0,'".date('Y-m-20')."','EEFF con notas y flujo efectivo',0,75.00),
            (1,6,'Declaración IVA Quincenal','Fiscal',2.0,'".date('Y-m-13')."','Quincena 1 y 2',1,50.00)
        ");
    }
} catch (Exception $e) {}

// ── Filtros ────────────────────────────────────────────────────────────────
$mesFiltro    = $_GET['mes']       ?? date('Y-m');
$clienteFiltro= (int)($_GET['cliente_id'] ?? 0);
$msg = ''; $msgTipo = '';

// ── ACCIONES POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'registrar_tiempo') {
        $cliId   = (int)($_POST['cliente_id']    ?? 0);
        $tarea   = trim($_POST['tarea']           ?? '');
        $cat     = trim($_POST['categoria']       ?? 'Contabilidad');
        $horas   = floatval(str_replace(',','.',$_POST['horas'] ?? '1'));
        $fecha   = $_POST['fecha']                ?? date('Y-m-d');
        $desc    = trim($_POST['descripcion']     ?? '');
        $tarifa  = floatval(str_replace(',','.',$_POST['tarifa_hora_usd'] ?? '0'));

        if ($cliId > 0 && !empty($tarea) && $horas > 0) {
            try {
                $db->prepare("INSERT INTO tiempo_dedicado (empresa_id, cliente_id, tarea, categoria, horas, fecha, descripcion, tarifa_hora_usd) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$empresa_id, $cliId, $tarea, $cat, $horas, $fecha, $desc ?: null, $tarifa]);
                $msg = "Registro de tiempo guardado correctamente."; $msgTipo = 'success';
            } catch (Exception $e) {
                $msg = "Error: " . $e->getMessage(); $msgTipo = 'error';
            }
        }
    }
    if ($accion === 'eliminar_tiempo' && isset($_POST['id'])) {
        try {
            $db->prepare("DELETE FROM tiempo_dedicado WHERE id=? AND empresa_id=?")->execute([(int)$_POST['id'], $empresa_id]);
            $msg = "Registro eliminado."; $msgTipo = 'success';
        } catch (Exception $e) {}
    }
}

// ── LEER CLIENTES ──────────────────────────────────────────────────────────
$clientes = [];
try {
    $stmtCli = $db->prepare("SELECT id, razon_social, honorarios_usd FROM clientes WHERE empresa_id=? OR empresa_id=1 ORDER BY razon_social");
    $stmtCli->execute([$empresa_id]);
    $clientes = $stmtCli->fetchAll();
} catch (Exception $e) {}

// ── LEER REGISTROS DE TIEMPO ───────────────────────────────────────────────
$registros = [];
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
try {
    $whereCliente = $clienteFiltro > 0 ? "AND t.cliente_id = $clienteFiltro" : '';
    if ($driver === 'sqlite') {
        $sql = "SELECT t.*, c.razon_social FROM tiempo_dedicado t
                JOIN clientes c ON c.id = t.cliente_id
                WHERE t.empresa_id=? AND strftime('%Y-%m',t.fecha)=? $whereCliente
                ORDER BY t.fecha DESC";
    } else {
        $sql = "SELECT t.*, c.razon_social FROM tiempo_dedicado t
                JOIN clientes c ON c.id = t.cliente_id
                WHERE t.empresa_id=? AND DATE_FORMAT(t.fecha,'%Y-%m')=? $whereCliente
                ORDER BY t.fecha DESC";
    }
    $stmtT = $db->prepare($sql);
    $stmtT->execute([$empresa_id, $mesFiltro]);
    $registros = $stmtT->fetchAll();
} catch (Exception $e) {}

// ── RESUMEN POR CLIENTE (del mes seleccionado) ─────────────────────────────
$resumenCliente = [];
try {
    if ($driver === 'sqlite') {
        $sql2 = "SELECT t.cliente_id, c.razon_social, c.honorarios_usd,
                    SUM(t.horas) as total_horas,
                    SUM(t.horas * t.tarifa_hora_usd) as valor_tiempo_usd,
                    COUNT(t.id) as total_tareas
                 FROM tiempo_dedicado t JOIN clientes c ON c.id=t.cliente_id
                 WHERE t.empresa_id=? AND strftime('%Y-%m',t.fecha)=?
                 GROUP BY t.cliente_id, c.razon_social, c.honorarios_usd
                 ORDER BY total_horas DESC";
    } else {
        $sql2 = "SELECT t.cliente_id, c.razon_social, c.honorarios_usd,
                    SUM(t.horas) as total_horas,
                    SUM(t.horas * t.tarifa_hora_usd) as valor_tiempo_usd,
                    COUNT(t.id) as total_tareas
                 FROM tiempo_dedicado t JOIN clientes c ON c.id=t.cliente_id
                 WHERE t.empresa_id=? AND DATE_FORMAT(t.fecha,'%Y-%m')=?
                 GROUP BY t.cliente_id, c.razon_social, c.honorarios_usd
                 ORDER BY total_horas DESC";
    }
    $stmtR = $db->prepare($sql2);
    $stmtR->execute([$empresa_id, $mesFiltro]);
    $resumenCliente = $stmtR->fetchAll();
} catch (Exception $e) {}

// Totales globales del mes
$totalHorasMes = array_sum(array_column($resumenCliente,'total_horas'));
$totalValorMes = array_sum(array_column($resumenCliente,'valor_tiempo_usd'));
$maxHoras = $totalHorasMes > 0 ? max(array_column($resumenCliente,'total_horas')) : 1;

$pageTitle = "Control de Tiempo — Kontify OS";
require_once __DIR__ . '/includes/header.php';
?>

<!-- HEADER -->
<div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 mb-8">
    <div>
        <div class="flex items-center gap-2 mb-1.5">
            <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-blue-700 bg-blue-50 border border-blue-200/60 px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Time Tracking
            </span>
        </div>
        <h1 class="text-2xl lg:text-3xl font-extrabold text-[#0F172A] tracking-tight">Control de Tiempo por Cliente</h1>
        <p class="text-xs lg:text-sm text-[#64748B] mt-1 font-medium">Registro de horas dedicadas por cliente y servicio. Mide rentabilidad real y productividad del despacho.</p>
    </div>
    <button onclick="document.getElementById('modalNuevoTiempo').classList.remove('hidden')"
            class="k-btn k-btn-primary text-xs shadow-sm shrink-0">
        <i class="fa-solid fa-clock-rotate-left text-xs"></i> Registrar Tiempo
    </button>
</div>

<?php if ($msg): ?>
<div class="mb-5 p-4 rounded-xl text-sm font-semibold border <?= $msgTipo==='success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
    <i class="fa-solid <?= $msgTipo==='success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> mr-2"></i><?= $msg ?>
</div>
<?php endif; ?>

<!-- FILTROS -->
<div class="k-card p-4 mb-6 flex flex-wrap items-center gap-4">
    <form method="GET" class="flex flex-wrap gap-3 items-center w-full">
        <div class="flex items-center gap-2">
            <label class="k-label mb-0 shrink-0">Período:</label>
            <input type="month" name="mes" value="<?= htmlspecialchars($mesFiltro) ?>" class="k-input text-xs w-44">
        </div>
        <div class="flex items-center gap-2">
            <label class="k-label mb-0 shrink-0">Cliente:</label>
            <select name="cliente_id" class="k-input text-xs w-56">
                <option value="0">— Todos los clientes —</option>
                <?php foreach ($clientes as $cl): ?>
                <option value="<?= $cl['id'] ?>" <?= $clienteFiltro==$cl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cl['razon_social']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="k-btn k-btn-secondary text-xs"><i class="fa-solid fa-filter mr-1"></i>Aplicar</button>
        <a href="tiempo.php" class="k-btn k-btn-secondary text-xs text-slate-400"><i class="fa-solid fa-rotate-left mr-1"></i>Reset</a>

        <?php if ($clienteFiltro > 0): ?>
        <a href="reporte_cliente.php?cliente_id=<?= $clienteFiltro ?>&mes=<?= $mesFiltro ?>" target="_blank"
           class="k-btn text-xs bg-indigo-600 hover:bg-indigo-700 text-white shadow ml-auto">
            <i class="fa-solid fa-file-invoice mr-1"></i>Generar Reporte para Cliente
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- KPI ROW -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="k-card p-5">
        <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Total Horas del Mes</span>
        <span class="text-2xl font-extrabold font-mono text-[#0F172A] block"><?= number_format($totalHorasMes,1) ?> h</span>
        <span class="text-[11px] text-slate-400 mt-1 block"><?= count($registros) ?> registros</span>
    </div>
    <div class="k-card p-5">
        <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Valor del Tiempo</span>
        <span class="text-2xl font-extrabold font-mono text-blue-600 block">$<?= number_format($totalValorMes,2) ?></span>
        <span class="text-[11px] text-blue-500 mt-1 block">A tarifa estándar/hora</span>
    </div>
    <div class="k-card p-5">
        <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Clientes con Registro</span>
        <span class="text-2xl font-extrabold font-mono text-emerald-600 block"><?= count($resumenCliente) ?></span>
        <span class="text-[11px] text-slate-400 mt-1 block">de <?= count($clientes) ?> en cartera</span>
    </div>
    <div class="k-card p-5">
        <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Promedio h/Cliente</span>
        <span class="text-2xl font-extrabold font-mono text-violet-600 block">
            <?= count($resumenCliente) > 0 ? number_format($totalHorasMes / count($resumenCliente),1) : '0' ?> h
        </span>
        <span class="text-[11px] text-slate-400 mt-1 block">Por cliente activo</span>
    </div>
</div>

<!-- GRID: RESUMEN CLIENTES + DETALLE REGISTROS -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

    <!-- Ranking de horas por cliente -->
    <div class="k-card p-6">
        <h3 class="text-sm font-bold text-[#0F172A] mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
            <i class="fa-solid fa-chart-bar text-blue-500 text-xs"></i> Horas por Cliente — <?= date('M Y', strtotime($mesFiltro . '-01')) ?>
        </h3>
        <div class="space-y-4">
            <?php if (empty($resumenCliente)): ?>
                <p class="text-xs text-slate-400 italic text-center py-6">Sin registros para este período.</p>
            <?php else: ?>
            <?php
            $clColors = ['#6366F1','#12B99D','#F59E0B','#EF4444','#8B5CF6','#EC4899'];
            $ci2 = 0;
            foreach ($resumenCliente as $rc):
                $pct = $totalHorasMes > 0 ? ($rc['total_horas'] / $totalHorasMes) * 100 : 0;
                $col = $clColors[$ci2 % count($clColors)]; $ci2++;
                $rentabilidad = $rc['honorarios_usd'] > 0 && $rc['valor_tiempo_usd'] > 0
                    ? ($rc['honorarios_usd'] / $rc['valor_tiempo_usd']) * 100 : 0;
            ?>
            <div>
                <div class="flex justify-between items-start mb-1 gap-2">
                    <div class="min-w-0">
                        <span class="text-xs font-bold text-[#0F172A] block truncate" title="<?= htmlspecialchars($rc['razon_social']) ?>"><?= htmlspecialchars($rc['razon_social']) ?></span>
                        <span class="text-[10px] text-slate-400"><?= $rc['total_tareas'] ?> tareas · <?= number_format($rc['total_horas'],1) ?>h</span>
                    </div>
                    <span class="text-xs font-mono font-bold text-slate-700 shrink-0">$<?= number_format($rc['honorarios_usd'],0) ?>/mes</span>
                </div>
                <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full" style="width:<?= min($pct,100) ?>%;background:<?= $col ?>"></div>
                </div>
                <div class="flex justify-between mt-1">
                    <span class="text-[10px] text-slate-400"><?= number_format($pct,1) ?>% del tiempo total</span>
                    <?php if ($rentabilidad > 0): ?>
                    <span class="text-[10px] font-bold <?= $rentabilidad >= 100 ? 'text-emerald-600' : 'text-amber-600' ?>">
                        Rent. <?= number_format($rentabilidad,0) ?>%
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Totales -->
        <?php if (!empty($resumenCliente)): ?>
        <div class="mt-4 pt-3 border-t border-slate-100 space-y-1">
            <div class="flex justify-between text-xs font-bold text-[#0F172A]">
                <span>Total horas facturables</span>
                <span class="font-mono"><?= number_format($totalHorasMes,1) ?> h</span>
            </div>
            <div class="flex justify-between text-xs text-blue-700 font-bold">
                <span>Valor estimado del tiempo</span>
                <span class="font-mono">$<?= number_format($totalValorMes,2) ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tabla detalle de registros -->
    <div class="k-card p-6 lg:col-span-2">
        <h3 class="text-sm font-bold text-[#0F172A] mb-4 pb-2 border-b border-slate-100 flex items-center justify-between">
            <span class="flex items-center gap-2"><i class="fa-solid fa-list-check text-blue-500 text-xs"></i> Registros del Período</span>
            <span class="text-xs text-slate-400"><?= count($registros) ?> entradas</span>
        </h3>
        <div class="overflow-x-auto">
            <table class="k-table w-full">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Tarea</th>
                        <th>Categoría</th>
                        <th>Horas</th>
                        <th>Valor USD</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registros)): ?>
                        <tr><td colspan="7" class="py-10 text-center text-slate-400 text-xs italic">Sin registros. Usa "Registrar Tiempo" para comenzar.</td></tr>
                    <?php else: ?>
                    <?php foreach ($registros as $reg):
                        $valorReg = $reg['horas'] * $reg['tarifa_hora_usd'];
                    ?>
                    <tr>
                        <td class="text-xs text-slate-500 font-mono"><?= date('d/m', strtotime($reg['fecha'])) ?></td>
                        <td class="text-xs font-bold text-[#0F172A] max-w-[120px] truncate" title="<?= htmlspecialchars($reg['razon_social']) ?>"><?= htmlspecialchars($reg['razon_social']) ?></td>
                        <td>
                            <div class="text-xs font-semibold text-[#0F172A]"><?= htmlspecialchars($reg['tarea']) ?></div>
                            <?php if ($reg['descripcion']): ?>
                            <span class="text-[10px] text-slate-400"><?= htmlspecialchars(mb_strimwidth($reg['descripcion'],0,40,'…')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $catColors = ['Contabilidad'=>'bg-blue-100 text-blue-700','Fiscal'=>'bg-emerald-100 text-emerald-700','Auditoría'=>'bg-violet-100 text-violet-700','Nómina'=>'bg-amber-100 text-amber-700','Asesoría'=>'bg-rose-100 text-rose-700'];
                            $cc = $catColors[$reg['categoria']] ?? 'bg-slate-100 text-slate-600';
                            ?>
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold <?= $cc ?>"><?= htmlspecialchars($reg['categoria']) ?></span>
                        </td>
                        <td class="font-mono font-bold text-sm text-[#0F172A]"><?= number_format($reg['horas'],1) ?>h</td>
                        <td class="font-mono font-bold text-xs <?= $valorReg > 0 ? 'text-blue-600' : 'text-slate-400' ?>">
                            <?= $valorReg > 0 ? '$'.number_format($valorReg,2) : '—' ?>
                        </td>
                        <td>
                            <form method="POST" onsubmit="return confirm('¿Eliminar?')">
                                <input type="hidden" name="accion" value="eliminar_tiempo">
                                <input type="hidden" name="id" value="<?= $reg['id'] ?>">
                                <button type="submit" class="text-slate-400 hover:text-rose-500 transition-colors p-1"><i class="fa-solid fa-trash text-xs"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($registros)): ?>
                <tfoot>
                    <tr class="bg-blue-50">
                        <td colspan="4" class="font-bold text-xs text-blue-700 py-3 px-4">TOTAL DEL PERÍODO</td>
                        <td class="font-mono font-extrabold text-base text-[#0F172A] py-3 px-4"><?= number_format($totalHorasMes,1) ?>h</td>
                        <td class="font-mono font-extrabold text-base text-blue-600 py-3 px-4">$<?= number_format($totalValorMes,2) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- MODAL NUEVO REGISTRO DE TIEMPO -->
<div id="modalNuevoTiempo" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4 flex">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-[#0F172A] text-sm flex items-center gap-2">
                <i class="fa-solid fa-stopwatch text-blue-500"></i> Registrar Tiempo Dedicado
            </h3>
            <button onclick="document.getElementById('modalNuevoTiempo').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="accion" value="registrar_tiempo">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="k-label">Cliente *</label>
                    <select name="cliente_id" required class="k-input text-xs">
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($clientes as $cl): ?>
                        <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['razon_social']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="k-label">Fecha *</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required class="k-input text-xs">
                </div>
            </div>
            <div>
                <label class="k-label">Tarea Realizada *</label>
                <input type="text" name="tarea" required placeholder="ej. Declaración IVA quincenal SENIAT" class="k-input text-xs">
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="k-label">Categoría</label>
                    <select name="categoria" class="k-input text-xs">
                        <option>Contabilidad</option>
                        <option>Fiscal</option>
                        <option>Auditoría</option>
                        <option>Nómina</option>
                        <option>Asesoría</option>
                        <option>Administrativo</option>
                        <option>Otro</option>
                    </select>
                </div>
                <div>
                    <label class="k-label">Horas *</label>
                    <input type="number" name="horas" step="0.25" min="0.25" value="1" required class="k-input text-xs">
                </div>
                <div>
                    <label class="k-label">Tarifa/h USD</label>
                    <input type="number" name="tarifa_hora_usd" step="0.01" min="0" value="50" class="k-input text-xs">
                </div>
            </div>
            <div>
                <label class="k-label">Descripción / Detalle (opc.)</label>
                <textarea name="descripcion" rows="2" placeholder="Detalles de la tarea realizada..." class="k-input text-xs resize-none"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalNuevoTiempo').classList.add('hidden')" class="k-btn k-btn-secondary text-xs">Cancelar</button>
                <button type="submit" class="k-btn k-btn-primary text-xs"><i class="fa-solid fa-floppy-disk mr-1"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<style>
.k-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.k-table th { background: #F8FAFC; color: #64748B; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; padding: 10px 14px; text-align: left; border-bottom: 1px solid #E8EDF1; }
.k-table td { padding: 10px 14px; border-bottom: 1px solid #F1F5F9; vertical-align: middle; }
.k-table tbody tr:hover { background: #F8FAFC; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
