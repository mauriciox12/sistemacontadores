<?php
/**
 * REPORTE PARA CLIENTE — KONTIFY OS
 * Reporte mensual imprimible que puede anexarse a la Factura
 * Acceso: reporte_cliente.php?cliente_id=X&mes=YYYY-MM
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$db = getDB();
$empresa_id  = (int)($_SESSION['empresa_id'] ?? 1);
$cliente_id  = (int)($_GET['cliente_id'] ?? 0);
$mesFiltro   = $_GET['mes'] ?? date('Y-m');
$despacho    = obtenerConfiguracionDespacho($db);
$tasaBcv     = (float)($_SESSION['tasa_bcv'] ?? 65.50);

if ($cliente_id <= 0) {
    die('<p style="font-family:sans-serif;padding:40px;color:#ef4444">Error: Debe especificar un cliente. <a href="tiempo.php">← Volver</a></p>');
}

// ── Datos del cliente ──────────────────────────────────────────────────────
$cliente = null;
try {
    $stmtCli = $db->prepare("SELECT * FROM clientes WHERE id=?");
    $stmtCli->execute([$cliente_id]);
    $cliente = $stmtCli->fetch();
} catch (Exception $e) {}

if (!$cliente) {
    die('<p style="font-family:sans-serif;padding:40px;color:#ef4444">Error: Cliente no encontrado. <a href="tiempo.php">← Volver</a></p>');
}

// ── Servicios contratados ──────────────────────────────────────────────────
$servicios = [];
try {
    $stmtSvc = $db->prepare("SELECT * FROM servicios_cliente WHERE cliente_id=? AND estatus='activo' ORDER BY tipo");
    $stmtSvc->execute([$cliente_id]);
    $servicios = $stmtSvc->fetchAll();
} catch (Exception $e) {}

// ── Registros de tiempo del mes ────────────────────────────────────────────
$registrosTiempo = [];
$totalHoras = 0;
$totalValor = 0;
try {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? "SELECT * FROM tiempo_dedicado WHERE cliente_id=? AND strftime('%Y-%m',fecha)=? ORDER BY fecha ASC"
        : "SELECT * FROM tiempo_dedicado WHERE cliente_id=? AND DATE_FORMAT(fecha,'%Y-%m')=? ORDER BY fecha ASC";
    $stmtT = $db->prepare($sql);
    $stmtT->execute([$cliente_id, $mesFiltro]);
    $registrosTiempo = $stmtT->fetchAll();
    foreach ($registrosTiempo as $r) {
        $totalHoras += $r['horas'];
        $totalValor += $r['horas'] * $r['tarifa_hora_usd'];
    }
} catch (Exception $e) {}

// ── Pagos/ingresos cobrados del mes ───────────────────────────────────────
$pagosDelMes = [];
$totalCobrado = 0;
try {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sqlP = $driver === 'sqlite'
        ? "SELECT * FROM ingresos WHERE cliente_id=? AND estado='cobrado' AND strftime('%Y-%m',fecha)=? ORDER BY fecha"
        : "SELECT * FROM ingresos WHERE cliente_id=? AND estado='cobrado' AND DATE_FORMAT(fecha,'%Y-%m')=? ORDER BY fecha";
    $stmtP = $db->prepare($sqlP);
    $stmtP->execute([$cliente_id, $mesFiltro]);
    $pagosDelMes = $stmtP->fetchAll();
    foreach ($pagosDelMes as $p) $totalCobrado += $p['monto_usd'];
} catch (Exception $e) {}

// ── Fecha formateada ───────────────────────────────────────────────────────
$mesNombre = strftime('%B %Y', strtotime($mesFiltro . '-01'));
setlocale(LC_TIME, 'es_VE.UTF-8', 'es_ES.UTF-8', 'Spanish');
$meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
[$anioMes, $mesNum] = explode('-', $mesFiltro);
$mesNombreEs = ($meses[$mesNum] ?? 'Mes') . ' ' . $anioMes;

$numReporte = 'RPT-' . date('Ymd') . '-' . str_pad($cliente_id, 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reporte Mensual — <?= htmlspecialchars($cliente['razon_social']) ?> — <?= $mesNombreEs ?></title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Montserrat', sans-serif;
        background: #F0F4F8;
        color: #172330;
        font-size: 12px;
        line-height: 1.5;
    }
    .page {
        max-width: 800px;
        margin: 0 auto;
        background: #fff;
        min-height: 100vh;
    }
    /* ── HEADER DESPACHO ── */
    .doc-header {
        background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 100%);
        color: #fff;
        padding: 32px 40px 28px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
    }
    .despacho-logo {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .logo-icon {
        width: 52px; height: 52px;
        background: linear-gradient(135deg,#12B99D,#0A947D);
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px; font-weight: 800; color: #fff;
        letter-spacing: -1px;
        box-shadow: 0 4px 16px rgba(18,185,157,.4);
    }
    .despacho-name { font-size: 15px; font-weight: 800; color: #fff; line-height: 1.2; }
    .despacho-sub  { font-size: 9px; color: rgba(255,255,255,.55); font-weight: 600; letter-spacing: 1px; text-transform: uppercase; margin-top: 3px; }
    .doc-meta { text-align: right; }
    .doc-meta .doc-tipo { font-size: 11px; font-weight: 700; color: #12B99D; letter-spacing: 1px; text-transform: uppercase; }
    .doc-meta .doc-num  { font-size: 13px; font-weight: 800; color: #fff; margin-top: 4px; }
    .doc-meta .doc-fecha{ font-size: 10px; color: rgba(255,255,255,.55); margin-top: 2px; }

    /* ── BANDA PERÍODO ── */
    .periodo-band {
        background: #12B99D;
        color: #fff;
        padding: 10px 40px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .5px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* ── BODY ── */
    .doc-body { padding: 32px 40px; }

    /* ── DATOS CLIENTE ── */
    .section { margin-bottom: 24px; }
    .section-title {
        font-size: 10px;
        font-weight: 800;
        color: #64748B;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 2px solid #E8EDF1;
        padding-bottom: 6px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .section-title::before {
        content: '';
        display: inline-block;
        width: 3px; height: 14px;
        background: #12B99D;
        border-radius: 2px;
    }
    .client-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 24px;
    }
    .client-grid .field label {
        font-size: 9px;
        color: #94A3B8;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        display: block;
    }
    .client-grid .field span {
        font-size: 12px;
        font-weight: 600;
        color: #172330;
    }

    /* ── TABLA BASE ── */
    table { width: 100%; border-collapse: collapse; font-size: 11px; }
    thead th {
        background: #F8FAFC;
        color: #64748B;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        padding: 8px 10px;
        text-align: left;
        border-bottom: 1px solid #E8EDF1;
    }
    tbody td {
        padding: 8px 10px;
        border-bottom: 1px solid #F1F5F9;
        vertical-align: middle;
    }
    tfoot td {
        padding: 9px 10px;
        font-weight: 700;
    }

    /* ── SERVICIOS ── */
    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 9px;
        font-weight: 700;
    }
    .badge-rec  { background: #D1FAE5; color: #065F46; }
    .badge-ext  { background: #EDE9FE; color: #5B21B6; }

    /* ── TIEMPO ── */
    .cat-badge {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 20px;
        font-size: 9px;
        font-weight: 700;
    }
    .cat-Contabilidad { background: #DBEAFE; color: #1D4ED8; }
    .cat-Fiscal { background: #D1FAE5; color: #065F46; }
    .cat-Auditoría { background: #EDE9FE; color: #5B21B6; }
    .cat-Nómina { background: #FEF3C7; color: #92400E; }
    .cat-Asesoría { background: #FCE7F3; color: #9D174D; }

    /* ── RESUMEN FINANCIERO ── */
    .resumen-fin {
        background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 100%);
        color: #fff;
        border-radius: 12px;
        padding: 20px 24px;
        margin-bottom: 24px;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    .rf-item label {
        font-size: 9px;
        color: rgba(255,255,255,.55);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        display: block;
        margin-bottom: 4px;
    }
    .rf-item .rf-value {
        font-size: 17px;
        font-weight: 800;
        color: #fff;
        font-variant-numeric: tabular-nums;
    }
    .rf-item .rf-value.green { color: #34D399; }
    .rf-item .rf-value.amber { color: #FBBF24; }

    /* ── FOOTER ── */
    .doc-footer {
        background: #F8FAFC;
        border-top: 1px solid #E8EDF1;
        padding: 16px 40px;
        font-size: 9px;
        color: #94A3B8;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .doc-footer strong { color: #172330; }

    /* ── DATOS PAGO ── */
    .pago-box {
        background: #F0FDF4;
        border: 1px solid #A7F3D0;
        border-radius: 10px;
        padding: 14px 18px;
        font-size: 11px;
        color: #065F46;
        margin-bottom: 16px;
    }
    .pago-box strong { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #047857; margin-bottom: 6px; }

    /* ── ACTIONS BAR (no imprime) ── */
    .no-print {
        background: #0F172A;
        color: #fff;
        padding: 12px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        font-size: 12px;
        position: sticky;
        top: 0;
        z-index: 100;
    }
    .no-print .btn-act {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 11px;
        cursor: pointer;
        border: none;
        text-decoration: none;
        transition: opacity .15s;
    }
    .btn-print { background: #12B99D; color: #fff; }
    .btn-back  { background: rgba(255,255,255,.1); color: #fff; }
    .btn-act:hover { opacity: .85; }

    @media print {
        .no-print { display: none !important; }
        body { background: #fff; }
        .page { box-shadow: none; }
    }
</style>
</head>
<body>

<!-- BARRA DE ACCIONES (no imprime) -->
<div class="no-print">
    <div>
        <span style="color:#12B99D;font-weight:800;">KONTIFY OS</span>
        <span style="color:rgba(255,255,255,.4);margin:0 8px;">·</span>
        <span>Reporte Mensual para Cliente — <?= htmlspecialchars($cliente['razon_social']) ?></span>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <a href="tiempo.php?cliente_id=<?= $cliente_id ?>&mes=<?= $mesFiltro ?>" class="btn-act btn-back">← Volver al Panel</a>
        <button onclick="window.print()" class="btn-act btn-print">🖨 Imprimir / Guardar PDF</button>
    </div>
</div>

<!-- PÁGINA DEL DOCUMENTO -->
<div class="page">

    <!-- HEADER -->
    <div class="doc-header">
        <div class="despacho-logo">
            <div class="logo-icon">K</div>
            <div>
                <div class="despacho-name"><?= htmlspecialchars($despacho['nombre_despacho'] ?? 'Despacho Contable') ?></div>
                <div class="despacho-sub"><?= htmlspecialchars($despacho['tipo_entidad'] ?? 'Firma Contable') ?></div>
                <?php if (!empty($despacho['cpc_numero'])): ?>
                <div style="font-size:9px;color:rgba(255,255,255,.4);margin-top:2px;">CPC Nº <?= htmlspecialchars($despacho['cpc_numero']) ?> · RIF <?= htmlspecialchars($despacho['rif'] ?? '') ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="doc-meta">
            <div class="doc-tipo">📄 Reporte Mensual de Servicios</div>
            <div class="doc-num"><?= $numReporte ?></div>
            <div class="doc-fecha">Emitido el <?= date('d/m/Y') ?> · <?= $mesNombreEs ?></div>
            <?php if (!empty($despacho['email'])): ?>
            <div class="doc-fecha" style="margin-top:4px;"><?= htmlspecialchars($despacho['email']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- BANDA PERÍODO -->
    <div class="periodo-band">
        <span>📅 Período de Servicio: <strong><?= $mesNombreEs ?></strong></span>
        <span>Documento Interno + Comprobante para Cliente</span>
    </div>

    <!-- BODY -->
    <div class="doc-body">

        <!-- DATOS DEL CLIENTE -->
        <div class="section">
            <div class="section-title">Datos del Cliente</div>
            <div class="client-grid">
                <div class="field">
                    <label>Razón Social</label>
                    <span style="font-weight:800;font-size:13px;"><?= htmlspecialchars($cliente['razon_social']) ?></span>
                </div>
                <div class="field">
                    <label>RIF</label>
                    <span style="font-family:monospace;"><?= htmlspecialchars($cliente['rif']) ?></span>
                </div>
                <div class="field">
                    <label>Régimen</label>
                    <span><?= htmlspecialchars($cliente['tipo_contribuyente']) ?></span>
                </div>
                <div class="field">
                    <label>Teléfono</label>
                    <span><?= htmlspecialchars($cliente['telefono']) ?></span>
                </div>
                <?php if (!empty($cliente['email'])): ?>
                <div class="field">
                    <label>Email</label>
                    <span><?= htmlspecialchars($cliente['email']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($cliente['direccion'])): ?>
                <div class="field" style="grid-column:span 2;">
                    <label>Dirección</label>
                    <span><?= htmlspecialchars($cliente['direccion']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RESUMEN FINANCIERO -->
        <div class="resumen-fin">
            <div class="rf-item">
                <label>Honorario Mensual</label>
                <div class="rf-value green">$<?= number_format($cliente['honorarios_usd'],2,',','.') ?></div>
                <div style="font-size:9px;color:rgba(255,255,255,.4);margin-top:3px;">Retainer del período</div>
            </div>
            <div class="rf-item">
                <label>Total Cobrado en el Mes</label>
                <div class="rf-value green">$<?= number_format($totalCobrado,2,',','.') ?></div>
                <div style="font-size:9px;color:rgba(255,255,255,.4);margin-top:3px;"><?= count($pagosDelMes) ?> transacción(es)</div>
            </div>
            <div class="rf-item">
                <label>Horas Dedicadas al Cliente</label>
                <div class="rf-value amber"><?= number_format($totalHoras,1) ?> h</div>
                <div style="font-size:9px;color:rgba(255,255,255,.4);margin-top:3px;"><?= count($registrosTiempo) ?> tareas registradas</div>
            </div>
        </div>

        <!-- SERVICIOS CONTRATADOS -->
        <?php if (!empty($servicios)): ?>
        <div class="section">
            <div class="section-title">Servicios Contratados Vigentes</div>
            <table>
                <thead>
                    <tr>
                        <th>Servicio</th>
                        <th>Tipo</th>
                        <th style="text-align:right;">Honorario USD</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $subTotal = 0; foreach ($servicios as $svc): $subTotal += $svc['monto_usd']; ?>
                    <tr>
                        <td style="font-weight:600;color:#172330;"><?= htmlspecialchars($svc['nombre_servicio']) ?>
                            <?php if (!empty($svc['observaciones'])): ?>
                            <div style="font-size:9px;color:#94A3B8;margin-top:2px;"><?= htmlspecialchars(mb_strimwidth($svc['observaciones'],0,60,'…')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $svc['tipo']==='recurrente' ? 'badge-rec' : 'badge-ext' ?>"><?= ucfirst($svc['tipo']) ?></span></td>
                        <td style="font-family:monospace;font-weight:700;text-align:right;">$<?= number_format($svc['monto_usd'],2,',','.') ?></td>
                        <td style="color:#065F46;font-weight:700;font-size:10px;">● Activo</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#F0FDF4;">
                        <td colspan="2" style="color:#065F46;font-size:11px;">SUBTOTAL SERVICIOS</td>
                        <td style="font-family:monospace;font-size:14px;color:#059669;text-align:right;">$<?= number_format($subTotal,2,',','.') ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>

        <!-- DETALLE DE TIEMPO DEDICADO -->
        <?php if (!empty($registrosTiempo)): ?>
        <div class="section">
            <div class="section-title">Detalle de Trabajo Realizado — <?= $mesNombreEs ?></div>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tarea Realizada</th>
                        <th>Categoría</th>
                        <th style="text-align:center;">Horas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrosTiempo as $r): ?>
                    <tr>
                        <td style="font-family:monospace;white-space:nowrap;"><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($r['tarea']) ?></div>
                            <?php if (!empty($r['descripcion'])): ?>
                            <div style="font-size:9px;color:#94A3B8;margin-top:2px;"><?= htmlspecialchars($r['descripcion']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="cat-badge cat-<?= htmlspecialchars($r['categoria']) ?>">
                                <?= htmlspecialchars($r['categoria']) ?>
                            </span>
                        </td>
                        <td style="font-family:monospace;font-weight:700;text-align:center;"><?= number_format($r['horas'],1) ?>h</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#F0F9FF;">
                        <td colspan="3" style="color:#1D4ED8;font-size:10px;">TOTAL HORAS DEDICADAS AL CLIENTE</td>
                        <td style="font-family:monospace;font-size:14px;font-weight:800;color:#1D4ED8;text-align:center;"><?= number_format($totalHoras,1) ?>h</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>

        <!-- PAGOS REGISTRADOS DEL MES -->
        <?php if (!empty($pagosDelMes)): ?>
        <div class="section">
            <div class="section-title">Pagos Recibidos — <?= $mesNombreEs ?></div>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Método de Pago</th>
                        <th>Referencia</th>
                        <th style="text-align:right;">Monto USD</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagosDelMes as $pago): ?>
                    <tr>
                        <td style="font-family:monospace;white-space:nowrap;"><?= date('d/m/Y', strtotime($pago['fecha'])) ?></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($pago['nombre_servicio']) ?></td>
                        <td style="color:#64748B;"><?= htmlspecialchars($pago['metodo_pago']) ?></td>
                        <td style="font-family:monospace;font-size:10px;color:#94A3B8;"><?= htmlspecialchars($pago['referencia'] ?? '—') ?></td>
                        <td style="font-family:monospace;font-weight:700;text-align:right;color:#059669;">$<?= number_format($pago['monto_usd'],2,',','.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#F0FDF4;">
                        <td colspan="4" style="color:#059669;font-size:10px;">TOTAL COBRADO EN EL PERÍODO</td>
                        <td style="font-family:monospace;font-size:15px;font-weight:800;color:#059669;text-align:right;">$<?= number_format($totalCobrado,2,',','.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>

        <!-- DATOS DE PAGO DEL DESPACHO -->
        <?php if (!empty($despacho['datos_pago'])): ?>
        <div class="pago-box">
            <strong>💳 Instrucciones de Pago</strong>
            <?= nl2br(htmlspecialchars($despacho['datos_pago'])) ?>
        </div>
        <?php endif; ?>

        <!-- NOTA LEGAL -->
        <div style="background:#F8FAFC;border:1px solid #E8EDF1;border-radius:8px;padding:12px 16px;font-size:9px;color:#94A3B8;line-height:1.6;">
            <strong style="color:#64748B;display:block;margin-bottom:4px;">⚠ Nota Legal</strong>
            El presente reporte es un documento informativo emitido por <?= htmlspecialchars($despacho['nombre_despacho'] ?? 'el Despacho') ?> para uso exclusivo
            del cliente indicado. Los servicios descritos corresponden a trabajos profesionales de carácter contable, fiscal y administrativo
            prestados durante el período <?= $mesNombreEs ?>. Este documento puede ser anexado a la factura correspondiente como
            respaldo detallado de los servicios facturados.
            <?php if (!empty($despacho['cpc_numero'])): ?>
            Firmado digitalmente por Contador Público Colegiado Nº <?= htmlspecialchars($despacho['cpc_numero']) ?>.
            <?php endif; ?>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="doc-footer">
        <div>
            <strong><?= htmlspecialchars($despacho['nombre_despacho'] ?? '') ?></strong> ·
            <?= htmlspecialchars($despacho['telefono'] ?? '') ?> · <?= htmlspecialchars($despacho['email'] ?? '') ?>
        </div>
        <div><?= $numReporte ?> · Generado por Kontify OS · <?= date('d/m/Y H:i') ?></div>
    </div>

</div><!-- /page -->

<script>
// Auto-print si viene con ?print=1
if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.onload = () => setTimeout(() => window.print(), 400);
}
</script>
</body>
</html>
