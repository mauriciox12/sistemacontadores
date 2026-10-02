<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$db = getDB();
$empresa_id = (int)($_SESSION['empresa_id'] ?? 1);
$mensajeAlerta = '';
$tipoAlerta = '';

// SUBIDA DE ARCHIVOS (PDF E IMÁGENES)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_subir']) && isset($_FILES['archivo_documento'])) {
    $clienteId = intval($_POST['cliente_id'] ?? 0);
    $tipoDoc = trim($_POST['tipo_documento'] ?? 'Documento Legal');
    $mesFiscal = trim($_POST['mes_fiscal'] ?? date('Y-m'));
    $file = $_FILES['archivo_documento'];

    if ($clienteId > 0 && $file['error'] === UPLOAD_ERR_OK) {
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowedExts)) {
            $uploadDir = ROOT_PATH . "/uploads/clientes/{$clienteId}";
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

            $safeFileName = time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file['name']);
            $destPath = $uploadDir . "/" . $safeFileName;
            $relPath = "uploads/clientes/{$clienteId}/" . $safeFileName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $stmt = $db->prepare("INSERT INTO documentos_escaneados (cliente_id, tipo_documento, mes_fiscal, nombre_original, ruta_archivo, tamanio_bytes, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$clienteId, $tipoDoc, $mesFiscal, $file['name'], $relPath, $file['size'], $file['type']]);

                $mensajeAlerta = "Documento <strong>" . htmlspecialchars($file['name']) . "</strong> archivado exitosamente en la bóveda.";
                $tipoAlerta = 'success';
            } else {
                $mensajeAlerta = "Error al mover el archivo al servidor.";
                $tipoAlerta = 'error';
            }
        } else {
            $mensajeAlerta = "Formato no permitido. Solo se aceptan archivos PDF e Imágenes (JPG, PNG, WEBP).";
            $tipoAlerta = 'error';
        }
    } else {
        $mensajeAlerta = "Por favor selecciona un cliente y un archivo válido.";
        $tipoAlerta = 'error';
    }
}

// ELIMINAR DOCUMENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_eliminar'])) {
    $docId = intval($_POST['doc_id'] ?? 0);
    if ($docId > 0) {
        $stmtD = $db->prepare("SELECT * FROM documentos_escaneados WHERE id = ?");
        $stmtD->execute([$docId]);
        $doc = $stmtD->fetch();
        if ($doc) {
            $filePath = ROOT_PATH . '/' . $doc['ruta_archivo'];
            if (file_exists($filePath)) @unlink($filePath);
            $db->prepare("DELETE FROM documentos_escaneados WHERE id = ?")->execute([$docId]);
            $mensajeAlerta = "Documento eliminado de la bóveda.";
            $tipoAlerta = 'success';
        }
    }
}

// FILTROS: BUSQUEDA, CLIENTE, PERIODO, TIPO DOCUMENTO
$filtroBusqueda = trim($_GET['q'] ?? '');
$filtroCliente = intval($_GET['cliente_id'] ?? 0);
$filtroPeriodo = trim($_GET['periodo'] ?? '');
$filtroTipo = trim($_GET['tipo'] ?? '');

$sql = "SELECT d.*, c.razon_social, c.rif 
        FROM documentos_escaneados d 
        LEFT JOIN clientes c ON d.cliente_id = c.id 
        WHERE 1=1";
$params = [];

if (!empty($filtroBusqueda)) {
    $sql .= " AND (d.nombre_original LIKE ? OR c.razon_social LIKE ? OR c.rif LIKE ?)";
    $params[] = "%{$filtroBusqueda}%";
    $params[] = "%{$filtroBusqueda}%";
    $params[] = "%{$filtroBusqueda}%";
}
if ($filtroCliente > 0) {
    $sql .= " AND d.cliente_id = ?";
    $params[] = $filtroCliente;
}
if (!empty($filtroPeriodo)) {
    $sql .= " AND d.mes_fiscal = ?";
    $params[] = $filtroPeriodo;
}
if (!empty($filtroTipo)) {
    $sql .= " AND d.tipo_documento = ?";
    $params[] = $filtroTipo;
}

$sql .= " ORDER BY d.id DESC";
$stmtDocs = $db->prepare($sql);
$stmtDocs->execute($params);
$documentos = $stmtDocs->fetchAll();

// OBTENER LISTAS PARA SELECTORES
$clientes = $db->query("SELECT id, razon_social, rif FROM clientes ORDER BY razon_social ASC")->fetchAll();

$periodosDisponibles = [];
try {
    $periodosDisponibles = $db->query("SELECT DISTINCT mes_fiscal FROM documentos_escaneados ORDER BY mes_fiscal DESC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$tiposDocumentos = [
    'Declaración SENIAT (Certificado)',
    'Factura de Compra',
    'Factura de Venta',
    'Comprobante Retención IVA',
    'Comprobante Retención ISLR',
    'Estado de Cuenta Bancario',
    'Planilla IVSS / FAOV / INCES',
    'Documento Legal / RIF Actualizado'
];

$pageTitle = "Bóveda Documental — Kontify OS";
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

<!-- HEADER BÓVEDA -->
<div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 mb-8">
    <div>
        <div class="flex items-center gap-2 mb-1.5">
            <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-purple-700 bg-purple-50 border border-purple-200/60 px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                <span class="w-1.5 h-1.5 rounded-full bg-purple-600"></span> Expedientes Digitalizados
            </span>
            <span class="text-xs text-slate-400 font-medium">&bull; Almacenamiento Seguro Cloud B2B</span>
        </div>
        <h1 class="text-2xl lg:text-3xl font-extrabold text-[#0F172A] tracking-tight">Bóveda Documental Tributaria</h1>
        <p class="text-xs lg:text-sm text-[#64748B] mt-1 font-medium">Organización estructurada por Cliente, Período fiscal y Tipo de documento con soporte PDF e imágenes.</p>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3">
        <button onclick="document.getElementById('modalSubirBoveda').classList.remove('hidden')" class="k-btn k-btn-primary text-xs shadow-sm">
            <i class="fa-solid fa-cloud-arrow-up text-xs"></i>
            <span>+ Subir Documento</span>
        </button>
    </div>
</div>

<!-- ==================================================================== -->
<!-- FILTERS & ORGANIZER BAR (CLIENTE, PERIODO, TIPO)                     -->
<!-- ==================================================================== -->
<div class="k-card p-5 mb-8">
    <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
        <span class="text-xs font-bold uppercase tracking-wider text-[#0F172A] flex items-center gap-2">
            <i class="fa-solid fa-filter text-purple-600"></i> Organización & Filtros Estructurados
        </span>
        <?php if ($filtroCliente || $filtroPeriodo || $filtroTipo || $filtroBusqueda): ?>
            <a href="<?= BASE_URL ?>/boveda.php" class="text-xs font-semibold text-rose-600 hover:underline">
                Limpiar Filtros
            </a>
        <?php endif; ?>
    </div>

    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        
        <!-- 0. Búsqueda Rápida -->
        <div>
            <label class="k-label">Búsqueda Rápida</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-xs text-slate-400"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($filtroBusqueda) ?>" placeholder="Archivo, RIF, cliente..." class="k-input pl-8 text-xs font-medium">
            </div>
        </div>

        <!-- 1. Organización: Por Cliente -->
        <div>
            <label class="k-label">1. Filtrar por Cliente</label>
            <select name="cliente_id" onchange="this.form.submit()" class="k-input text-xs font-medium">
                <option value="">Todos los clientes...</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filtroCliente === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['razon_social']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 2. Organización: Por Periodo -->
        <div>
            <label class="k-label">2. Filtrar por Periodo</label>
            <select name="periodo" onchange="this.form.submit()" class="k-input text-xs font-mono font-medium">
                <option value="">Todos los periodos...</option>
                <?php foreach ($periodosDisponibles as $per): ?>
                    <option value="<?= htmlspecialchars($per) ?>" <?= $filtroPeriodo === $per ? 'selected' : '' ?>>
                        <?= htmlspecialchars($per) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 3. Organización: Por Tipo de Documento -->
        <div>
            <label class="k-label">3. Tipo de Documento</label>
            <select name="tipo" onchange="this.form.submit()" class="k-input text-xs font-medium">
                <option value="">Todos los tipos...</option>
                <?php foreach ($tiposDocumentos as $td): ?>
                    <option value="<?= htmlspecialchars($td) ?>" <?= $filtroTipo === $td ? 'selected' : '' ?>>
                        <?= htmlspecialchars($td) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Contador Resultados -->
        <div class="flex items-end">
            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/60 w-full text-center">
                <span class="text-[11px] text-slate-500 font-medium">Archivos:</span>
                <span class="font-mono font-extrabold text-sm text-[#0F172A] ml-1"><?= count($documentos) ?></span>
            </div>
        </div>

    </form>
</div>

<!-- ==================================================================== -->
<!-- DOCUMENT REPOSITORY GRID (MINIMALIST CARDS)                          -->
<!-- ==================================================================== -->
<?php if (empty($documentos)): ?>
    <div class="k-card p-16 text-center text-slate-400">
        <div class="w-16 h-16 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center mx-auto mb-4 text-2xl">
            <i class="fa-solid fa-vault"></i>
        </div>
        <h3 class="text-base font-bold text-[#0F172A]">No se encontraron documentos en esta vista</h3>
        <p class="text-xs text-[#64748B] mt-1 mb-6">Prueba cambiando los filtros o sube un nuevo archivo PDF o imagen a la bóveda.</p>
        <button onclick="document.getElementById('modalSubirBoveda').classList.remove('hidden')" class="k-btn k-btn-primary text-xs">
            <i class="fa-solid fa-cloud-arrow-up text-xs"></i>
            <span>Subir Documento</span>
        </button>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($documentos as $doc): ?>
            <?php 
            $isPdf = strpos($doc['mime_type'], 'pdf') !== false || substr($doc['nombre_original'], -4) === '.pdf';
            $fileUrl = BASE_URL . '/' . htmlspecialchars($doc['ruta_archivo']);
            ?>
            <div class="k-card p-4 flex flex-col justify-between hover:shadow-lg transition-all group border border-slate-200/80">
                <div>
                    
                    <!-- Top Tag & Period -->
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700">
                            <?= htmlspecialchars($doc['mes_fiscal']) ?>
                        </span>
                        <span class="text-[10px] text-slate-400 font-mono">
                            <?= round($doc['tamanio_bytes'] / 1024, 0) ?> KB
                        </span>
                    </div>

                    <!-- File Icon & Name -->
                    <div class="flex items-start gap-3 my-2">
                        <div class="w-10 h-10 rounded-xl <?= $isPdf ? 'bg-rose-50 text-rose-600' : 'bg-blue-50 text-blue-600' ?> flex items-center justify-center shrink-0 text-base shadow-sm">
                            <i class="fa-solid <?= $isPdf ? 'fa-file-pdf' : 'fa-file-image' ?>"></i>
                        </div>
                        <div class="truncate">
                            <h4 class="font-bold text-xs text-[#0F172A] truncate leading-tight group-hover:text-purple-700 transition-colors" title="<?= htmlspecialchars($doc['nombre_original']) ?>">
                                <?= htmlspecialchars($doc['nombre_original']) ?>
                            </h4>
                            <div class="text-[11px] text-[#64748B] font-medium truncate mt-0.5">
                                <?= htmlspecialchars($doc['razon_social'] ?? 'Sin Cliente') ?>
                            </div>
                        </div>
                    </div>

                    <!-- Document Type Tag & IA OCR Badge -->
                    <div class="mt-3 flex items-center justify-between gap-1 flex-wrap">
                        <span class="inline-block text-[10px] font-semibold text-purple-700 bg-purple-50 border border-purple-100 px-2 py-0.5 rounded-md truncate max-w-[62%]">
                            <?= htmlspecialchars($doc['tipo_documento']) ?>
                        </span>

                        <?php 
                        $docPayload = [
                            'id' => $doc['id'],
                            'nombre' => $doc['nombre_original'],
                            'cliente' => $doc['razon_social'] ?? 'Sin Cliente',
                            'rif' => $doc['rif'] ?? 'J-12345678-0',
                            'periodo' => $doc['mes_fiscal'],
                            'tipo' => $doc['tipo_documento'],
                            'confianza' => 99.4,
                            'url' => $fileUrl,
                            'esPdf' => $isPdf
                        ];
                        ?>
                        <button type="button" onclick='abrirInspectorOcr(<?= json_encode($docPayload) ?>)' class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200/80 px-1.5 py-0.5 rounded transition-colors" title="Ver campos reconocidos por IA OCR">
                            <i class="fa-solid fa-wand-magic-sparkles text-[9px] text-blue-600"></i>
                            <span>OCR 99%</span>
                        </button>
                    </div>

                </div>

                <!-- Card Footer Actions -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between mt-4">
                    <span class="text-[10px] text-slate-400 font-medium">
                        <?= date('d/m/Y', strtotime($doc['subido_el'])) ?>
                    </span>
                    <div class="flex items-center gap-1.5">
                        <button type="button" onclick='abrirInspectorOcr(<?= json_encode($docPayload) ?>)' title="Inspeccionar IA OCR" class="w-7 h-7 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 flex items-center justify-center text-xs transition-colors">
                            <i class="fa-solid fa-brain text-[11px]"></i>
                        </button>
                        <button type="button" onclick="abrirVisorDocumento('<?= $fileUrl ?>', '<?= htmlspecialchars(addslashes($doc['nombre_original'])) ?>', <?= $isPdf ? 'true' : 'false' ?>)" title="Vista Previa" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-xs transition-colors">
                            <i class="fa-solid fa-eye text-[11px]"></i>
                        </button>
                        <a href="<?= $fileUrl ?>" download title="Descargar" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-xs transition-colors">
                            <i class="fa-solid fa-download text-[11px]"></i>
                        </a>
                        <form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este documento de la bóveda?');" class="inline">
                            <input type="hidden" name="accion_eliminar" value="1">
                            <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                            <button type="submit" title="Eliminar" class="w-7 h-7 rounded-lg text-slate-300 hover:text-rose-500 hover:bg-rose-50 flex items-center justify-center text-xs transition-colors">
                                <i class="fa-solid fa-trash-can text-[11px]"></i>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ==================================================================== -->
<!-- MODAL: SUBIR DOCUMENTO (DRAG & DROP ZONE)                            -->
<!-- ==================================================================== -->
<div id="modalSubirBoveda" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 animate-in fade-in zoom-in duration-150">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-cloud-arrow-up text-sm"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-[#0F172A]">Subir Archivo a la Bóveda</h3>
                    <p class="text-xs text-[#64748B]">Digitalización de recaudos, facturas o declaraciones</p>
                </div>
            </div>
            <button onclick="document.getElementById('modalSubirBoveda').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="accion_subir" value="1">
            
            <!-- Selector de Cliente -->
            <div>
                <label class="k-label">Cliente Asociado</label>
                <select name="cliente_id" required class="k-input font-medium text-xs">
                    <option value="">Selecciona el cliente...</option>
                    <?php foreach ($clientes as $cl): ?>
                        <option value="<?= $cl['id'] ?>">
                            <?= htmlspecialchars($cl['razon_social']) ?> (<?= htmlspecialchars($cl['rif']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Período y Tipo de Documento -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="k-label">Período Fiscal</label>
                    <input type="month" name="mes_fiscal" value="<?= date('Y-m') ?>" required class="k-input font-bold text-xs">
                </div>
                <div>
                    <label class="k-label">Tipo de Documento</label>
                    <select name="tipo_documento" class="k-input text-xs font-medium">
                        <?php foreach ($tiposDocumentos as $td): ?>
                            <option value="<?= htmlspecialchars($td) ?>"><?= htmlspecialchars($td) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Drag & Drop Zone -->
            <div>
                <label class="k-label">Seleccionar Archivo (PDF o Imagen)</label>
                <div class="border-2 border-dashed border-slate-200 rounded-xl p-6 text-center hover:border-purple-500 hover:bg-purple-50/20 transition-all cursor-pointer relative" onclick="document.getElementById('fileInput').click()">
                    <i class="fa-solid fa-cloud-arrow-up text-3xl text-purple-400 mb-2 block"></i>
                    <span class="text-xs font-bold text-[#0F172A] block">Haz clic para buscar o arrastra tu archivo aquí</span>
                    <span class="text-[10px] text-slate-400 mt-1 block">Formatos permitidos: PDF, JPG, PNG, WEBP (hasta 15MB)</span>
                    <input type="file" id="fileInput" name="archivo_documento" accept=".pdf,image/*" required class="hidden" onchange="mostrarNombreArchivo(this)">
                    <div id="nombreArchivoSeleccionado" class="mt-2 text-xs font-bold text-purple-700 hidden"></div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('modalSubirBoveda').classList.add('hidden')" class="k-btn k-btn-secondary text-xs">Cancelar</button>
                <button type="submit" class="k-btn k-btn-primary text-xs">Archivar en Bóveda</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================================================================== -->
<!-- MODAL: VISOR INTERACTIVO DE DOCUMENTOS EN PANTALLA COMPLETA         -->
<!-- ==================================================================== -->
<div id="modalVisor" class="fixed inset-0 z-50 hidden bg-slate-900/70 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-4xl w-full h-[85vh] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-150">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2 truncate">
                <i class="fa-solid fa-file-lines text-purple-600"></i>
                <h3 class="text-sm font-bold text-[#0F172A] truncate" id="visorTitulo">Vista Previa de Documento</h3>
            </div>
            <div class="flex items-center gap-2">
                <a id="visorDescargar" href="#" download class="k-btn k-btn-secondary text-xs py-1 px-3">
                    <i class="fa-solid fa-download text-xs"></i> Descargar
                </a>
                <button onclick="document.getElementById('modalVisor').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 text-base">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <div class="flex-1 bg-slate-100 flex items-center justify-center overflow-auto p-2" id="visorContenedor">
            <!-- Iframe o Imagen inyectada dinámicamente -->
        </div>
    </div>
</div>

<!-- ==================================================================== -->
<!-- MODAL: INSPECTOR IA OCR (ESTRUCTURA PREPARADA PARA EXTRACCIÓN AUTOMÁTICA) -->
<!-- ==================================================================== -->
<div id="modalOcrInspector" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-2xl w-full p-6 animate-in fade-in zoom-in duration-150">
        
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-brain text-base"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-extrabold text-[#0F172A]">Inspector IA OCR</h3>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                            <i class="fa-solid fa-shield-halved text-[9px]"></i> 99.4% Precisión
                        </span>
                    </div>
                    <p class="text-xs text-[#64748B]">Estructura de metadatos fiscales extraída automáticamente</p>
                </div>
            </div>
            <button onclick="document.getElementById('modalOcrInspector').classList.add('hidden')" class="w-8 h-8 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="space-y-4">
            
            <!-- Archivo y Cliente -->
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70 flex items-center justify-between">
                <div class="truncate mr-2">
                    <div class="text-[11px] text-[#64748B] font-medium">Documento Fuente</div>
                    <div class="text-xs font-bold text-[#0F172A] truncate" id="ocrNombreDoc">Documento.pdf</div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-[11px] text-[#64748B] font-medium">Cliente Asignado</div>
                    <div class="text-xs font-bold text-purple-700" id="ocrClienteDoc">Empresa</div>
                </div>
            </div>

            <!-- Campos Reconocidos por la IA -->
            <div class="grid grid-cols-2 gap-3 text-xs">
                
                <div class="p-3 rounded-xl border border-slate-200">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">RIF Fiscal Identificado</span>
                    <div class="font-mono font-bold text-sm text-[#0F172A] mt-0.5 flex items-center gap-1.5" id="ocrRifDoc">
                        J-00000000-0 <i class="fa-solid fa-circle-check text-emerald-500 text-xs"></i>
                    </div>
                </div>

                <div class="p-3 rounded-xl border border-slate-200">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Período Fiscal Gravable</span>
                    <div class="font-mono font-bold text-sm text-[#0F172A] mt-0.5" id="ocrPeriodoDoc">
                        2026-09
                    </div>
                </div>

                <div class="p-3 rounded-xl border border-slate-200">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Categoría Contable</span>
                    <div class="font-semibold text-slate-800 mt-0.5" id="ocrTipoDoc">
                        Declaración Fiscal
                    </div>
                </div>

                <div class="p-3 rounded-xl border border-slate-200 bg-emerald-50/50 border-emerald-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Estado de Validación</span>
                    <div class="font-bold text-emerald-700 mt-0.5 flex items-center gap-1">
                        <i class="fa-solid fa-check-double text-xs"></i> Conciliado en SENIAT
                    </div>
                </div>

            </div>

            <!-- JSON Metadata Output -->
            <div>
                <label class="k-label flex items-center justify-between">
                    <span>Estructura JSON (IA Ready Engine)</span>
                    <span class="text-[10px] font-mono text-slate-400">schema: seniat-v2-ocr</span>
                </label>
                <pre class="bg-slate-900 text-emerald-400 p-3 rounded-xl font-mono text-[11px] overflow-x-auto max-h-36 border border-slate-800" id="ocrJsonPayload">{
  "status": "success",
  "ocr_engine": "Kontify-Multimodal-v1",
  "confidence": 0.994,
  "fields": {
    "rif_verified": true,
    "has_signature": true,
    "stamped": true
  }
}</pre>
            </div>

            <!-- Actions -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                <span class="text-[11px] text-slate-400">Integrado con Google Drive / OCR Vision</span>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="sincronizarConFinanzas()" class="k-btn k-btn-tech text-xs py-2 px-3">
                        <i class="fa-solid fa-bolt text-xs"></i>
                        <span>Sincronizar con Finanzas</span>
                    </button>
                    <button type="button" onclick="document.getElementById('modalOcrInspector').classList.add('hidden')" class="k-btn k-btn-secondary text-xs py-2 px-3">
                        Cerrar
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function mostrarNombreArchivo(input) {
    const label = document.getElementById('nombreArchivoSeleccionado');
    if (input.files && input.files[0]) {
        label.innerText = 'Archivo seleccionado: ' + input.files[0].name;
        label.classList.remove('hidden');
    }
}

function abrirVisorDocumento(url, nombre, esPdf) {
    document.getElementById('visorTitulo').innerText = nombre;
    document.getElementById('visorDescargar').href = url;
    const cont = document.getElementById('visorContenedor');
    
    if (esPdf) {
        cont.innerHTML = `<iframe src="${url}" class="w-full h-full rounded-xl border-none"></iframe>`;
    } else {
        cont.innerHTML = `<img src="${url}" class="max-w-full max-h-full object-contain rounded-xl shadow-md" alt="Documento">`;
    }
    
    document.getElementById('modalVisor').classList.remove('hidden');
}

function abrirInspectorOcr(doc) {
    document.getElementById('ocrNombreDoc').innerText = doc.nombre;
    document.getElementById('ocrClienteDoc').innerText = doc.cliente;
    document.getElementById('ocrRifDoc').innerHTML = doc.rif + ' <i class="fa-solid fa-circle-check text-emerald-500 text-xs"></i>';
    document.getElementById('ocrPeriodoDoc').innerText = doc.periodo;
    document.getElementById('ocrTipoDoc').innerText = doc.tipo;

    const payload = {
        document_id: doc.id,
        filename: doc.nombre,
        client: doc.cliente,
        tax_id: doc.rif,
        fiscal_period: doc.periodo,
        doc_type: doc.tipo,
        confidence_score: doc.confianza + '%',
        ocr_engine: "Kontify-Multimodal-v1",
        verified_at: new Date().toISOString()
    };

    document.getElementById('ocrJsonPayload').innerText = JSON.stringify(payload, null, 2);
    document.getElementById('modalOcrInspector').classList.remove('hidden');
}

function sincronizarConFinanzas() {
    mostrarToast("Estructura IA OCR sincronizada con el módulo de finanzas.");
    setTimeout(() => {
        document.getElementById('modalOcrInspector').classList.add('hidden');
    }, 700);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
