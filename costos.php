<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$db = getDB();
$empresa_id = (int)($_SESSION['empresa_id'] ?? 1);
$despacho   = obtenerConfiguracionDespacho($db);
$tasaBcv    = (float)($_SESSION['tasa_bcv'] ?? 65.50);

// ── Crear tabla de costos_estructura si no existe ──────────────────────────
try {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS costos_estructura (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empresa_id INTEGER DEFAULT 1,
            categoria TEXT NOT NULL DEFAULT 'Operativo',
            subcategoria TEXT NULL,
            descripcion TEXT NOT NULL,
            tipo TEXT NOT NULL DEFAULT 'fijo',
            monto_usd REAL NOT NULL DEFAULT 0.00,
            frecuencia TEXT NOT NULL DEFAULT 'mensual',
            proveedor TEXT NULL,
            activo INTEGER NOT NULL DEFAULT 1,
            creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS `costos_estructura` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `empresa_id` INT DEFAULT 1,
            `categoria` VARCHAR(80) NOT NULL DEFAULT 'Operativo',
            `subcategoria` VARCHAR(80) NULL,
            `descripcion` VARCHAR(255) NOT NULL,
            `tipo` ENUM('fijo','variable','semi-variable') NOT NULL DEFAULT 'fijo',
            `monto_usd` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `frecuencia` ENUM('mensual','trimestral','anual','unico') NOT NULL DEFAULT 'mensual',
            `proveedor` VARCHAR(120) NULL,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
} catch (Exception $e) {}

// ── Seed inicial si está vacío ─────────────────────────────────────────────
try {
    $cnt = (int)$db->query("SELECT COUNT(*) FROM costos_estructura")->fetchColumn();
    if ($cnt === 0) {
        $db->exec("INSERT INTO costos_estructura (empresa_id, categoria, subcategoria, descripcion, tipo, monto_usd, frecuencia, proveedor) VALUES
            (1,'Tecnología & Software','SaaS','Suscripción Kontify OS & Servidor Cloud','fijo',65.00,'mensual','Amazon Web Services'),
            (1,'Tecnología & Software','SaaS','Microsoft 365 Empresa','fijo',12.00,'mensual','Microsoft'),
            (1,'Tecnología & Software','Herramientas','Adobe Acrobat Pro (PDF Fiscal)','fijo',9.50,'mensual','Adobe'),
            (1,'Personal & Honorarios','Asistentes','Honorarios Asistente Contable (tiempo parcial)','fijo',250.00,'mensual','Lic. Andrea Méndez'),
            (1,'Personal & Honorarios','Asistentes','Honorarios Asistente Administrativo','variable',120.00,'mensual','Asistente Interno'),
            (1,'Oficina & Infraestructura','Internet','Fibra Óptica Empresarial 300Mbps','fijo',55.00,'mensual','NetUno Fibra'),
            (1,'Oficina & Infraestructura','Alquiler','Renta Oficina Compartida Coworking','fijo',180.00,'mensual','WeWork Caracas'),
            (1,'Papelería & Legal','CPC','Timbres Fiscales & Visados CPC','variable',40.00,'mensual','Col. de Contadores Públicos'),
            (1,'Papelería & Legal','Notaría','Gastos Notariales y Autenticaciones','variable',30.00,'mensual','Notaría Pública'),
            (1,'Marketing & Comercial','Publicidad','Google Ads / Redes Sociales','semi-variable',35.00,'mensual','Meta & Google'),
            (1,'Capacitación','Formación','Cursos Tributarios AVCCPV / SENIAT','variable',20.00,'mensual','AVCCPV')
        ");
    }
} catch (Exception $e) {}

// ── ACCIONES POST ──────────────────────────────────────────────────────────
$msg = ''; $msgTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $cat    = trim($_POST['categoria']    ?? 'Operativo');
        $sub    = trim($_POST['subcategoria'] ?? '');
        $desc   = trim($_POST['descripcion']  ?? '');
        $tipo   = $_POST['tipo']              ?? 'fijo';
        $monto  = floatval(str_replace(',','.',$_POST['monto_usd'] ?? '0'));
        $freq   = $_POST['frecuencia']        ?? 'mensual';
        $prov   = trim($_POST['proveedor']    ?? '');

        if (!empty($desc) && $monto > 0) {
            try {
                $db->prepare("INSERT INTO costos_estructura (empresa_id, categoria, subcategoria, descripcion, tipo, monto_usd, frecuencia, proveedor) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$empresa_id, $cat, $sub ?: null, $desc, $tipo, $monto, $freq, $prov ?: null]);
                $msg = "Costo estructural <strong>$desc</strong> registrado correctamente.";
                $msgTipo = 'success';
            } catch (Exception $e) {
                $msg = "Error al guardar: " . $e->getMessage(); $msgTipo = 'error';
            }
        }
    }

    if ($accion === 'eliminar' && isset($_POST['id'])) {
        try {
            $db->prepare("UPDATE costos_estructura SET activo = 0 WHERE id = ? AND empresa_id = ?")
               ->execute([(int)$_POST['id'], $empresa_id]);
            $msg = "Costo eliminado."; $msgTipo = 'success';
        } catch (Exception $e) {}
    }
}

// ── LEER DATOS ─────────────────────────────────────────────────────────────
$costos = [];
try {
    $stmtC = $db->prepare("SELECT * FROM costos_estructura WHERE empresa_id = ? AND activo = 1 ORDER BY categoria, tipo");
    $stmtC->execute([$empresa_id]);
    $costos = $stmtC->fetchAll();
} catch (Exception $e) {}

// Ingresos cobrados del mes actual
$mesActual = date('Y-m');
$ingMes = 0;
try {
    $stmtI = $db->prepare("SELECT COALESCE(SUM(monto_usd),0) FROM ingresos WHERE empresa_id=? AND estado='cobrado' AND strftime('%Y-%m',fecha)=?");
    $stmtI->execute([$empresa_id, $mesActual]);
    $ingMes = (float)$stmtI->fetchColumn();
} catch (Exception $e) {
    // MySQL fallback
    try {
        $stmtI = $db->prepare("SELECT COALESCE(SUM(monto_usd),0) FROM ingresos WHERE empresa_id=? AND estado='cobrado' AND DATE_FORMAT(fecha,'%Y-%m')=?");
        $stmtI->execute([$empresa_id, $mesActual]);
        $ingMes = (float)$stmtI->fetchColumn();
    } catch (Exception $e2) {}
}

// Calcular totales por categoría
$totalFijo = 0; $totalVariable = 0; $totalSemi = 0;
$porCategoria = [];
foreach ($costos as $c) {
    $montoMes = $c['monto_usd'];
    if ($c['frecuencia'] === 'trimestral') $montoMes /= 3;
    if ($c['frecuencia'] === 'anual')      $montoMes /= 12;

    if ($c['tipo'] === 'fijo')           $totalFijo     += $montoMes;
    elseif ($c['tipo'] === 'variable')   $totalVariable += $montoMes;
    else                                 $totalSemi     += $montoMes;

    $cat = $c['categoria'];
    if (!isset($porCategoria[$cat])) $porCategoria[$cat] = 0;
    $porCategoria[$cat] += $montoMes;
}
$totalCostosMes = $totalFijo + $totalVariable + $totalSemi;
$margenBruto = $ingMes > 0 ? (($ingMes - $totalCostosMes) / $ingMes) * 100 : 0;
$beneficioNeto = $ingMes - $totalCostosMes;

arsort($porCategoria);

$pageTitle = "Estructura de Costos — Kontify OS";
require_once __DIR__ . '/includes/header.php';
?>

<!-- HEADER -->
<div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 mb-8">
    <div>
        <div class="flex items-center gap-2 mb-1.5">
            <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-rose-700 bg-rose-50 border border-rose-200/60 px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Control de Costos
            </span>
            <span class="text-xs text-slate-400 font-medium">• <?= date('F Y') ?></span>
        </div>
        <h1 class="text-2xl lg:text-3xl font-extrabold text-[#0F172A] tracking-tight">Estructura de Costos del Despacho</h1>
        <p class="text-xs lg:text-sm text-[#64748B] mt-1 font-medium">Registro y análisis de costos fijos, variables y semi-variables del despacho contable.</p>
    </div>
    <button onclick="document.getElementById('modalNuevoCosto').classList.remove('hidden')"
            class="k-btn k-btn-primary text-xs shadow-sm shrink-0">
        <i class="fa-solid fa-plus text-xs"></i> Registrar Costo
    </button>
</div>

<?php if ($msg): ?>
<div class="mb-6 p-4 rounded-xl text-sm font-semibold border <?= $msgTipo === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
    <i class="fa-solid <?= $msgTipo === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> mr-2"></i><?= $msg ?>
</div>
<?php endif; ?>

<!-- KPI ROW -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <div class="k-card p-5 col-span-2 lg:col-span-1">
        <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Ingresos del Mes</span>
        <span class="text-2xl font-extrabold font-mono text-emerald-600 block">$<?= number_format($ingMes,2,',','.') ?></span>
        <span class="text-[11px] text-slate-400 mt-1 block"><?= date('M Y') ?></span>
    </div>
    <div class="k-card p-5">
        <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Costos Fijos</span>
        <span class="text-xl font-extrabold font-mono text-slate-700 block">$<?= number_format($totalFijo,2) ?></span>
        <span class="text-[11px] text-rose-500 mt-1 block">Obligatorio</span>
    </div>
    <div class="k-card p-5">
        <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Variables</span>
        <span class="text-xl font-extrabold font-mono text-amber-600 block">$<?= number_format($totalVariable,2) ?></span>
        <span class="text-[11px] text-amber-500 mt-1 block">Fluctuante</span>
    </div>
    <div class="k-card p-5">
        <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Total Costos/Mes</span>
        <span class="text-xl font-extrabold font-mono text-rose-600 block">$<?= number_format($totalCostosMes,2) ?></span>
        <span class="text-[11px] text-slate-400 mt-1 block"><?= count($costos) ?> partidas activas</span>
    </div>
    <div class="k-card p-5 border-l-4 <?= $margenBruto >= 60 ? 'border-l-emerald-500' : ($margenBruto >= 30 ? 'border-l-amber-400' : 'border-l-rose-500') ?>">
        <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Margen Bruto</span>
        <span class="text-xl font-extrabold font-mono <?= $margenBruto >= 60 ? 'text-emerald-600' : ($margenBruto >= 30 ? 'text-amber-600' : 'text-rose-600') ?> block"><?= number_format($margenBruto,1) ?>%</span>
        <span class="text-[11px] <?= $beneficioNeto >= 0 ? 'text-emerald-600' : 'text-rose-600' ?> mt-1 block">Neto: $<?= number_format($beneficioNeto,2) ?></span>
    </div>
</div>

<!-- ANÁLISIS VISUAL POR CATEGORÍA + TABLA -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

    <!-- Distribución por categoría -->
    <div class="k-card p-6">
        <h3 class="text-sm font-bold text-[#0F172A] mb-4 pb-2 border-b border-slate-100">Distribución por Categoría</h3>
        <div class="space-y-3">
            <?php
            $colors = ['#12B99D','#6366F1','#F59E0B','#EF4444','#8B5CF6','#EC4899','#14B8A6'];
            $ci = 0;
            foreach ($porCategoria as $cat => $total):
                $pct = $totalCostosMes > 0 ? ($total / $totalCostosMes) * 100 : 0;
                $color = $colors[$ci % count($colors)]; $ci++;
            ?>
            <div>
                <div class="flex justify-between items-center mb-1">
                    <span class="text-xs font-semibold text-slate-700 truncate max-w-[150px]" title="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></span>
                    <span class="text-xs font-mono font-bold text-slate-800">$<?= number_format($total,2) ?></span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500" style="width:<?= min($pct,100) ?>%;background:<?= $color ?>"></div>
                </div>
                <span class="text-[10px] text-slate-400"><?= number_format($pct,1) ?>% del total</span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($porCategoria)): ?>
                <p class="text-xs text-slate-400 italic text-center py-4">Sin costos registrados.</p>
            <?php endif; ?>
        </div>

        <!-- Breakdown tipo -->
        <div class="mt-6 pt-4 border-t border-slate-100 space-y-2">
            <div class="flex items-center justify-between text-xs">
                <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-rose-500 inline-block"></span> Fijos</span>
                <span class="font-mono font-bold">$<?= number_format($totalFijo,2) ?></span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span> Variables</span>
                <span class="font-mono font-bold">$<?= number_format($totalVariable,2) ?></span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-violet-500 inline-block"></span> Semi-variables</span>
                <span class="font-mono font-bold">$<?= number_format($totalSemi,2) ?></span>
            </div>
        </div>
    </div>

    <!-- Tabla de costos -->
    <div class="k-card p-6 lg:col-span-2">
        <h3 class="text-sm font-bold text-[#0F172A] mb-4 pb-2 border-b border-slate-100 flex items-center justify-between">
            <span>Detalle de Partidas de Costo</span>
            <span class="text-xs text-slate-400"><?= count($costos) ?> registros activos</span>
        </h3>
        <div class="overflow-x-auto">
            <table class="k-table w-full">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Tipo</th>
                        <th>Frecuencia</th>
                        <th>Monto/Mes USD</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($costos)): ?>
                        <tr><td colspan="6" class="py-8 text-center text-slate-400 text-xs italic">Sin costos registrados. Haz clic en "Registrar Costo".</td></tr>
                    <?php else: ?>
                    <?php foreach ($costos as $c):
                        $montoMes = $c['monto_usd'];
                        if ($c['frecuencia']==='trimestral') $montoMes /= 3;
                        if ($c['frecuencia']==='anual')      $montoMes /= 12;
                    ?>
                        <tr>
                            <td>
                                <div class="font-semibold text-xs text-[#0F172A]"><?= htmlspecialchars($c['descripcion']) ?></div>
                                <?php if ($c['proveedor']): ?>
                                <span class="text-[10px] text-slate-400"><?= htmlspecialchars($c['proveedor']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-xs text-slate-600"><?= htmlspecialchars($c['categoria']) ?><?= $c['subcategoria'] ? ' · <span class="text-slate-400">'.htmlspecialchars($c['subcategoria']).'</span>' : '' ?></td>
                            <td>
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold
                                    <?= $c['tipo']==='fijo' ? 'bg-rose-100 text-rose-700' : ($c['tipo']==='variable' ? 'bg-amber-100 text-amber-700' : 'bg-violet-100 text-violet-700') ?>">
                                    <?= ucfirst($c['tipo']) ?>
                                </span>
                            </td>
                            <td class="text-xs text-slate-500 capitalize"><?= htmlspecialchars($c['frecuencia']) ?></td>
                            <td class="font-mono font-bold text-sm text-[#0F172A]">$<?= number_format($montoMes,2) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('¿Eliminar esta partida?')">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="text-slate-400 hover:text-rose-500 transition-colors p-1" title="Eliminar">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($costos)): ?>
                <tfoot>
                    <tr class="bg-slate-50">
                        <td colspan="4" class="font-bold text-xs text-slate-700 py-3 px-4">TOTAL MENSUAL ESTIMADO</td>
                        <td class="font-mono font-extrabold text-base text-rose-600 py-3 px-4">$<?= number_format($totalCostosMes,2) ?></td>
                        <td></td>
                    </tr>
                    <tr class="bg-emerald-50">
                        <td colspan="4" class="font-bold text-xs text-emerald-700 py-3 px-4">BENEFICIO NETO ESTIMADO (Ingresos - Costos)</td>
                        <td class="font-mono font-extrabold text-base <?= $beneficioNeto >= 0 ? 'text-emerald-600' : 'text-rose-600' ?> py-3 px-4">$<?= number_format($beneficioNeto,2) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- MODAL NUEVO COSTO -->
<div id="modalNuevoCosto" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4 flex">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-[#0F172A] text-sm flex items-center gap-2">
                <i class="fa-solid fa-circle-plus text-rose-500"></i> Registrar Nueva Partida de Costo
            </h3>
            <button onclick="document.getElementById('modalNuevoCosto').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="accion" value="crear">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="k-label">Categoría</label>
                    <select name="categoria" class="k-input text-xs">
                        <option>Tecnología & Software</option>
                        <option>Personal & Honorarios</option>
                        <option>Oficina & Infraestructura</option>
                        <option>Papelería & Legal</option>
                        <option>Marketing & Comercial</option>
                        <option>Capacitación</option>
                        <option>Impuestos & Obligaciones</option>
                        <option>Otros</option>
                    </select>
                </div>
                <div>
                    <label class="k-label">Subcategoría (opc.)</label>
                    <input type="text" name="subcategoria" placeholder="ej. SaaS, Nómina..." class="k-input text-xs">
                </div>
            </div>
            <div>
                <label class="k-label">Descripción del Costo *</label>
                <input type="text" name="descripcion" required placeholder="ej. Suscripción Contaplus Mensual" class="k-input text-xs">
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="k-label">Tipo</label>
                    <select name="tipo" class="k-input text-xs">
                        <option value="fijo">Fijo</option>
                        <option value="variable">Variable</option>
                        <option value="semi-variable">Semi-variable</option>
                    </select>
                </div>
                <div>
                    <label class="k-label">Frecuencia</label>
                    <select name="frecuencia" class="k-input text-xs">
                        <option value="mensual">Mensual</option>
                        <option value="trimestral">Trimestral</option>
                        <option value="anual">Anual</option>
                        <option value="unico">Único</option>
                    </select>
                </div>
                <div>
                    <label class="k-label">Monto USD *</label>
                    <input type="number" name="monto_usd" step="0.01" min="0.01" required placeholder="0.00" class="k-input text-xs">
                </div>
            </div>
            <div>
                <label class="k-label">Proveedor / Beneficiario (opc.)</label>
                <input type="text" name="proveedor" placeholder="ej. Google LLC, Freelancer..." class="k-input text-xs">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalNuevoCosto').classList.add('hidden')" class="k-btn k-btn-secondary text-xs">Cancelar</button>
                <button type="submit" class="k-btn k-btn-primary text-xs"><i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Costo</button>
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
