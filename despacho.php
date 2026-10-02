<?php
/**
 * ====================================================================
 * KONTIFY PRACTICE MANAGEMENT — MASTER SUITE (ULTRA-PREMIUM DARK EDITION)
 * Módulo 100% Editable e Interactivo (CRUD Completo de Despacho)
 * Control de Horas, Jerarquías, Costos Fijos, Aliados y Contratos
 * ====================================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/DespachoController.php';

$db = getDB();
$empresa_id = (int)($_SESSION['empresa_id'] ?? 1);
$tasaBcv    = (float)($_SESSION['tasa_bcv'] ?? 65.50);

// Auto-inicialización resiliente de tablas si aún no se han importado
try {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS despacho_jerarquias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empresa_id INTEGER DEFAULT 1,
            rol_nombre TEXT NOT NULL,
            nivel_jerarquico INTEGER DEFAULT 1,
            tarifa_costo_hora_usd REAL DEFAULT 0,
            tarifa_cobro_hora_usd REAL DEFAULT 0,
            descripcion TEXT,
            activo INTEGER DEFAULT 1,
            creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );");
        $db->exec("CREATE TABLE IF NOT EXISTS despacho_costos_fijos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empresa_id INTEGER DEFAULT 1,
            categoria TEXT NOT NULL,
            concepto TEXT NOT NULL,
            monto_mensual_usd REAL DEFAULT 0,
            frecuencia TEXT DEFAULT 'Mensual',
            activo INTEGER DEFAULT 1,
            fecha_inicio TEXT,
            observaciones TEXT,
            creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );");
        $db->exec("CREATE TABLE IF NOT EXISTS despacho_horas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empresa_id INTEGER DEFAULT 1,
            cliente_id INTEGER NOT NULL,
            usuario_id INTEGER NOT NULL,
            jerarquia_id INTEGER NOT NULL,
            contrato_id INTEGER,
            fecha TEXT NOT NULL,
            horas REAL DEFAULT 1,
            tipo_tarea TEXT DEFAULT 'Ordinaria',
            sujeta_contrato INTEGER DEFAULT 1,
            es_facturable INTEGER DEFAULT 1,
            facturado INTEGER DEFAULT 0,
            tarifa_costo_aplicada_usd REAL DEFAULT 0,
            tarifa_cobro_aplicada_usd REAL DEFAULT 0,
            costo_overhead_aplicado_usd REAL DEFAULT 0,
            gastos_directos_usd REAL DEFAULT 0,
            descripcion TEXT,
            origen_registro TEXT DEFAULT 'Manual',
            creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );");
        $db->exec("CREATE TABLE IF NOT EXISTS despacho_aliados (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empresa_id INTEGER DEFAULT 1,
            nombre_completo TEXT NOT NULL,
            especialidad TEXT NOT NULL,
            rif_cedula TEXT,
            colegiatura_numero TEXT,
            telefono TEXT,
            email TEXT,
            direccion_ciudad TEXT,
            honorarios_referenciales_usd REAL DEFAULT 0,
            modalidad_cobro TEXT DEFAULT 'Por Dictamen/Informe',
            calificacion_despacho INTEGER DEFAULT 5,
            proyectos_realizados TEXT,
            notas_evaluacion TEXT,
            activo INTEGER DEFAULT 1,
            creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );");
        $db->exec("CREATE TABLE IF NOT EXISTS despacho_contratos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empresa_id INTEGER DEFAULT 1,
            cliente_id INTEGER NOT NULL,
            tipo_plantilla TEXT NOT NULL,
            numero_contrato TEXT NOT NULL,
            titulo TEXT NOT NULL,
            alcance_servicio TEXT,
            honorarios_pactados_usd REAL DEFAULT 0,
            frecuencia_pago TEXT DEFAULT 'Mensual',
            horas_incluidas_mensuales REAL DEFAULT 0,
            tarifa_hora_excedente_usd REAL DEFAULT 0,
            clausula_rescision TEXT,
            responsabilidades_cliente TEXT,
            normativa_aplicable TEXT,
            estado TEXT DEFAULT 'Borrador',
            fecha_emision TEXT,
            fecha_vencimiento TEXT,
            archivo_digital_url TEXT,
            metadata_dinamica TEXT,
            creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );");
    }
} catch (Exception $e) {}

$controller = new DespachoController($db, $empresa_id, $tasaBcv);

// Estado de Notificación
$toastMsg = null;
$toastTipo = 'success';

// ====================================================================
// MANEJO DE ACCIONES POST (CRUD EDITABLE 100%)
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Guardar nuevo registro de tiempo
    if ($action === 'guardar_tiempo') {
        try {
            $idReg = $controller->registrarTiempo([
                'cliente_id' => (int)($_POST['cliente_id'] ?? 0),
                'usuario_id' => (int)($_SESSION['user_id'] ?? 1),
                'jerarquia_id' => (int)($_POST['jerarquia_id'] ?? 1),
                'fecha' => $_POST['fecha'] ?? date('Y-m-d'),
                'horas' => floatval(str_replace(',', '.', $_POST['horas'] ?? '1')),
                'tipo_tarea' => $_POST['tipo_tarea'] ?? 'Ordinaria',
                'sujeta_contrato' => isset($_POST['sujeta_contrato']) ? 1 : 0,
                'es_facturable' => isset($_POST['es_facturable']) ? 1 : 0,
                'descripcion' => trim($_POST['descripcion'] ?? 'Labor profesional imputada'),
                'gastos_directos_usd' => floatval(str_replace(',', '.', $_POST['gastos_directos_usd'] ?? '0')),
                'origen_registro' => $_POST['origen_registro'] ?? 'Cronometro'
            ]);
            $toastMsg = "¡Horas imputadas con éxito! (#{$idReg})";
            $toastTipo = 'success';
        } catch (Exception $e) {
            $toastMsg = "Error al registrar tiempo: " . $e->getMessage();
            $toastTipo = 'error';
        }
    }

    // 2. Actualizar registro de tiempo existente
    if ($action === 'actualizar_tiempo') {
        $id = (int)($_POST['id'] ?? 0);
        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        $jerarquiaId = (int)($_POST['jerarquia_id'] ?? 1);
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        $horas = floatval(str_replace(',', '.', $_POST['horas'] ?? '1'));
        $tipoTarea = $_POST['tipo_tarea'] ?? 'Ordinaria';
        $sujeta = isset($_POST['sujeta_contrato']) ? 1 : 0;
        $facturable = isset($_POST['es_facturable']) ? 1 : 0;
        $gastos = floatval(str_replace(',', '.', $_POST['gastos_directos_usd'] ?? '0'));
        $desc = trim($_POST['descripcion'] ?? '');

        if ($id > 0 && $clienteId > 0 && $horas > 0) {
            try {
                // Obtener tarifas vigentes del rol para actualizar snapshot
                $stmtJ = $db->prepare("SELECT tarifa_costo_hora_usd, tarifa_cobro_hora_usd FROM despacho_jerarquias WHERE id = ?");
                $stmtJ->execute([$jerarquiaId]);
                $tar = $stmtJ->fetch(PDO::FETCH_ASSOC) ?: ['tarifa_costo_hora_usd' => 4.5, 'tarifa_cobro_hora_usd' => 15.0];

                $stmtUp = $db->prepare("
                    UPDATE despacho_horas 
                    SET cliente_id = ?, jerarquia_id = ?, fecha = ?, horas = ?, tipo_tarea = ?, 
                        sujeta_contrato = ?, es_facturable = ?, tarifa_costo_aplicada_usd = ?, 
                        tarifa_cobro_aplicada_usd = ?, gastos_directos_usd = ?, descripcion = ?
                    WHERE id = ? AND empresa_id = ?
                ");
                $stmtUp->execute([
                    $clienteId, $jerarquiaId, $fecha, $horas, $tipoTarea,
                    $sujeta, $facturable, $tar['tarifa_costo_hora_usd'],
                    $tar['tarifa_cobro_hora_usd'], $gastos, $desc, $id, $empresa_id
                ]);
                $toastMsg = "¡Registro de tiempo #{$id} actualizado correctamente!";
                $toastTipo = 'success';
            } catch (Exception $e) {
                $toastMsg = "Error al actualizar: " . $e->getMessage();
                $toastTipo = 'error';
            }
        }
    }

    // 3. Eliminar registro de tiempo
    if ($action === 'eliminar_tiempo') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $db->prepare("DELETE FROM despacho_horas WHERE id = ? AND empresa_id = ?")->execute([$id, $empresa_id]);
                $toastMsg = "Registro de tiempo eliminado correctamente.";
                $toastTipo = 'success';
            } catch (Exception $e) {
                $toastMsg = "Error al eliminar: " . $e->getMessage();
                $toastTipo = 'error';
            }
        }
    }

    // 4. Guardar / Editar Jerarquía o Rol
    if ($action === 'guardar_jerarquia') {
        $id = (int)($_POST['id'] ?? 0);
        $rol = trim($_POST['rol_nombre'] ?? '');
        $nivel = (int)($_POST['nivel_jerarquico'] ?? 1);
        $costo = floatval(str_replace(',', '.', $_POST['tarifa_costo_hora_usd'] ?? '0'));
        $cobro = floatval(str_replace(',', '.', $_POST['tarifa_cobro_hora_usd'] ?? '0'));
        $desc = trim($_POST['descripcion'] ?? '');

        if (!empty($rol) && $cobro >= 0) {
            try {
                if ($id > 0) {
                    $stmt = $db->prepare("
                        UPDATE despacho_jerarquias 
                        SET rol_nombre = ?, nivel_jerarquico = ?, tarifa_costo_hora_usd = ?, tarifa_cobro_hora_usd = ?, descripcion = ?
                        WHERE id = ? AND empresa_id = ?
                    ");
                    $stmt->execute([$rol, $nivel, $costo, $cobro, $desc, $id, $empresa_id]);
                    $toastMsg = "¡Rol '{$rol}' actualizado correctamente!";
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO despacho_jerarquias (empresa_id, rol_nombre, nivel_jerarquico, tarifa_costo_hora_usd, tarifa_cobro_hora_usd, descripcion, activo)
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$empresa_id, $rol, $nivel, $costo, $cobro, $desc]);
                    $toastMsg = "¡Nuevo rol '{$rol}' agregado a la matriz!";
                }
                $toastTipo = 'success';
            } catch (Exception $e) {
                $toastMsg = "Error al guardar rol: " . $e->getMessage();
                $toastTipo = 'error';
            }
        }
    }

    // 5. Eliminar Jerarquía / Rol
    if ($action === 'eliminar_jerarquia') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $db->prepare("UPDATE despacho_jerarquias SET activo = 0 WHERE id = ? AND empresa_id = ?")->execute([$id, $empresa_id]);
                $toastMsg = "Rol desactivado de la matriz.";
                $toastTipo = 'success';
            } catch (Exception $e) {
                $toastMsg = "Error: " . $e->getMessage();
                $toastTipo = 'error';
            }
        }
    }

    // 6. Guardar / Editar Costo Fijo
    if ($action === 'guardar_costo_fijo') {
        $id = (int)($_POST['id'] ?? 0);
        $cat = trim($_POST['categoria'] ?? 'Alquiler de Oficina');
        $concepto = trim($_POST['concepto'] ?? '');
        $monto = floatval(str_replace(',', '.', $_POST['monto_mensual_usd'] ?? '0'));
        $frec = $_POST['frecuencia'] ?? 'Mensual';
        $obs = trim($_POST['observaciones'] ?? '');
        $fecha = $_POST['fecha_inicio'] ?? date('Y-m-01');

        if (!empty($concepto) && $monto >= 0) {
            try {
                if ($id > 0) {
                    $stmt = $db->prepare("
                        UPDATE despacho_costos_fijos 
                        SET categoria = ?, concepto = ?, monto_mensual_usd = ?, frecuencia = ?, observaciones = ?, fecha_inicio = ?
                        WHERE id = ? AND empresa_id = ?
                    ");
                    $stmt->execute([$cat, $concepto, $monto, $frec, $obs, $fecha, $id, $empresa_id]);
                    $toastMsg = "¡Costo fijo '{$concepto}' actualizado!";
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO despacho_costos_fijos (empresa_id, categoria, concepto, monto_mensual_usd, frecuencia, observaciones, fecha_inicio, activo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$empresa_id, $cat, $concepto, $monto, $frec, $obs, $fecha]);
                    $toastMsg = "¡Costo fijo registrado exitosamente!";
                }
                $toastTipo = 'success';
            } catch (Exception $e) {
                $toastMsg = "Error con costo fijo: " . $e->getMessage();
                $toastTipo = 'error';
            }
        }
    }

    // 7. Eliminar Costo Fijo
    if ($action === 'eliminar_costo_fijo') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $db->prepare("DELETE FROM despacho_costos_fijos WHERE id = ? AND empresa_id = ?")->execute([$id, $empresa_id]);
                $toastMsg = "Costo fijo eliminado. El overhead ha sido recalculado.";
                $toastTipo = 'success';
            } catch (Exception $e) {
                $toastMsg = "Error: " . $e->getMessage();
                $toastTipo = 'error';
            }
        }
    }

    // 8. Guardar / Editar Aliado Multidisciplinario
    if ($action === 'guardar_aliado') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre_completo'] ?? '');
        $esp = trim($_POST['especialidad'] ?? 'Perito Avaluador (SOVECT)');
        $rif = strtoupper(trim($_POST['rif_cedula'] ?? ''));
        $col = trim($_POST['colegiatura_numero'] ?? '');
        $telf = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $ciudad = trim($_POST['direccion_ciudad'] ?? 'Caracas');
        $honorario = floatval(str_replace(',', '.', $_POST['honorarios_referenciales_usd'] ?? '0'));
        $modalidad = $_POST['modalidad_cobro'] ?? 'Por Dictamen/Informe';
        $calif = (int)($_POST['calificacion_despacho'] ?? 5);
        $notas = trim($_POST['notas_evaluacion'] ?? '');

        if (!empty($nombre) && !empty($telf)) {
            try {
                if ($id > 0) {
                    $stmt = $db->prepare("
                        UPDATE despacho_aliados 
                        SET nombre_completo = ?, especialidad = ?, rif_cedula = ?, colegiatura_numero = ?,
                            telefono = ?, email = ?, direccion_ciudad = ?, honorarios_referenciales_usd = ?,
                            modalidad_cobro = ?, calificacion_despacho = ?, notas_evaluacion = ?
                        WHERE id = ? AND empresa_id = ?
                    ");
                    $stmt->execute([$nombre, $esp, $rif, $col, $telf, $email, $ciudad, $honorario, $modalidad, $calif, $notas, $id, $empresa_id]);
                    $toastMsg = "¡Aliado '{$nombre}' actualizado correctamente!";
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO despacho_aliados (empresa_id, nombre_completo, especialidad, rif_cedula, colegiatura_numero, telefono, email, direccion_ciudad, honorarios_referenciales_usd, modalidad_cobro, calificacion_despacho, notas_evaluacion, activo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$empresa_id, $nombre, $esp, $rif, $col, $telf, $email, $ciudad, $honorario, $modalidad, $calif, $notas]);
                    $toastMsg = "¡Nuevo aliado agregado al directorio multidisciplinario!";
                }
                $toastTipo = 'success';
            } catch (Exception $e) {
                $toastMsg = "Error con aliado: " . $e->getMessage();
                $toastTipo = 'error';
            }
        }
    }

    // 9. Eliminar Aliado
    if ($action === 'eliminar_aliado') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $db->prepare("DELETE FROM despacho_aliados WHERE id = ? AND empresa_id = ?")->execute([$id, $empresa_id]);
                $toastMsg = "Aliado eliminado del directorio.";
                $toastTipo = 'success';
            } catch (Exception $e) {
                $toastMsg = "Error: " . $e->getMessage();
                $toastTipo = 'error';
            }
        }
    }
}

// Filtros y Pestaña Activa
$tabActiva = $_GET['tab'] ?? 'cronometro';
$mesFiltro = (int)($_GET['mes'] ?? date('m'));
$añoFiltro = (int)($_GET['año'] ?? date('Y'));
$clienteFiltroId = (int)($_GET['cliente_id'] ?? 0);

// Cargar Clientes
$clientes = [];
try {
    $clientes = $db->query("SELECT id, razon_social, rif, tipo_contribuyente, honorarios_usd FROM clientes ORDER BY razon_social ASC")->fetchAll(PDO::FETCH_ASSOC);
    if ($clienteFiltroId <= 0 && !empty($clientes)) {
        $clienteFiltroId = (int)$clientes[0]['id'];
    }
} catch (Exception $e) {}

// Cargar Jerarquías
$jerarquias = [];
try {
    $jerarquias = $db->query("SELECT * FROM despacho_jerarquias WHERE activo = 1 ORDER BY nivel_jerarquico ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
if (empty($jerarquias)) {
    // Si aún no se han insertado en BD, crearlas automáticamente
    $seedRoles = [
        ['Auxiliar Contable', 1, 4.50, 15.00, 'Transcripción de compras/ventas, conciliación bancaria y archivo fiscal.'],
        ['Asistente Contable', 2, 8.00, 25.00, 'Declaraciones quincenales IVA, pre-nóminas LOTTT y TXT SENIAT.'],
        ['Contador Senior', 3, 15.00, 45.00, 'Revisión técnica de estados financieros VEN-NIF y cierre fiscal ISLR.'],
        ['Gerente de Auditoría', 4, 25.00, 70.00, 'Supervisión bajo NIA, control de calidad y mitigación de contingencias.'],
        ['Socio Director (CPA Lead)', 5, 45.00, 120.00, 'Estrategia fiscal, dictámenes de comisario mercantil y litigios SENIAT.']
    ];
    foreach ($seedRoles as $sr) {
        try {
            $db->prepare("INSERT INTO despacho_jerarquias (empresa_id, rol_nombre, nivel_jerarquico, tarifa_costo_hora_usd, tarifa_cobro_hora_usd, descripcion, activo) VALUES (?, ?, ?, ?, ?, ?, 1)")
               ->execute([$empresa_id, $sr[0], $sr[1], $sr[2], $sr[3], $sr[4]]);
        } catch (Exception $e) {}
    }
    $jerarquias = $db->query("SELECT * FROM despacho_jerarquias WHERE activo = 1 ORDER BY nivel_jerarquico ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Cargar Costos Fijos
$costosFijos = [];
$totalCostosFijosMes = 0.0;
try {
    $costosFijos = $db->query("SELECT * FROM despacho_costos_fijos WHERE activo = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($costosFijos)) {
        // Sembrar costos iniciales
        $seedCF = [
            ['Alquiler de Oficina', 'Alquiler Centro Empresarial El Rosal', 650.00, 'Mensual'],
            ['Servicios e Internet', 'Fibra Óptica Simétrica + UPS / Generador', 120.00, 'Mensual'],
            ['Licencias y Software', 'Suite Kontify Pro + Office 365 + Servidor Cloud', 180.00, 'Mensual'],
            ['Nomina Administrativa', 'Recepción & Mensajería Tributaria', 300.00, 'Mensual']
        ];
        foreach ($seedCF as $sc) {
            try {
                $db->prepare("INSERT INTO despacho_costos_fijos (empresa_id, categoria, concepto, monto_mensual_usd, frecuencia, activo, fecha_inicio) VALUES (?, ?, ?, ?, ?, 1, ?)")
                   ->execute([$empresa_id, $sc[0], $sc[1], $sc[2], $sc[3], date('Y-m-01')]);
            } catch (Exception $e) {}
        }
        $costosFijos = $db->query("SELECT * FROM despacho_costos_fijos WHERE activo = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($costosFijos as $cf) {
        $totalCostosFijosMes += (float)$cf['monto_mensual_usd'];
    }
} catch (Exception $e) {}

// Cargar Aliados
$aliados = [];
try {
    $aliados = $db->query("SELECT * FROM despacho_aliados WHERE activo = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($aliados)) {
        $seedAl = [
            ['Ing. Carlos Mendoza (SOVECT)', 'Perito Avaluador (SOVECT)', 'V-11234567', 'SOVECT-412', '0414-2345678', 'carlos.mendoza@gmail.com', 'Caracas', 350.00, 'Por Dictamen/Informe', 5, 'Peritaje de maquinaria y avalúo para VEN-NIF PYMES.'],
            ['Lic. Yelitza Rondón', 'Licenciado en Relaciones Industriales / RRHH', 'V-16789123', 'CRIL-8842', '0412-8765432', 'yrondon.rrhh@gmail.com', 'Valencia', 180.00, 'Por Proyecto', 5, 'Auditoría de pasivos laborales y expedientes LOTTT.'],
            ['Dr. Gustavo A. Alfonzo', 'Abogado Corporativo / Mercantil', 'V-09876543', 'INPREABO-54129', '0424-1122334', 'galfonzo.legal@escritorio.com', 'Caracas', 250.00, 'Por Dictamen/Informe', 5, 'Actas de asamblea, aumentos de capital y registros mercantiles.']
        ];
        foreach ($seedAl as $sa) {
            try {
                $db->prepare("INSERT INTO despacho_aliados (empresa_id, nombre_completo, especialidad, rif_cedula, colegiatura_numero, telefono, email, direccion_ciudad, honorarios_referenciales_usd, modalidad_cobro, calificacion_despacho, notas_evaluacion, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)")
                   ->execute([$empresa_id, $sa[0], $sa[1], $sa[2], $sa[3], $sa[4], $sa[5], $sa[6], $sa[7], $sa[8], $sa[9], $sa[10]]);
            } catch (Exception $e) {}
        }
        $aliados = $db->query("SELECT * FROM despacho_aliados WHERE activo = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// Cargar Últimas Imputaciones de Horas
$ultimasImputaciones = [];
try {
    $stmtUlt = $db->prepare("
        SELECT h.*, c.razon_social, j.rol_nombre, u.name AS usuario_nombre
        FROM despacho_horas h
        JOIN clientes c ON h.cliente_id = c.id
        JOIN despacho_jerarquias j ON h.jerarquia_id = j.id
        LEFT JOIN users u ON h.usuario_id = u.id
        WHERE h.empresa_id = ?
        ORDER BY h.id DESC LIMIT 15
    ");
    $stmtUlt->execute([$empresa_id]);
    $ultimasImputaciones = $stmtUlt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Calcular Rentabilidad
$rentabilidadData = null;
if ($clienteFiltroId > 0) {
    try {
        $rentabilidadData = $controller->calcularRentabilidadCliente($clienteFiltroId, $mesFiltro, $añoFiltro);
    } catch (Exception $e) {}
}

// Calcular Anexo de Horas Facturables
$reporteHoras = null;
if ($clienteFiltroId > 0) {
    try {
        $mesStr = sprintf('%04d-%02d', $añoFiltro, $mesFiltro);
        $reporteHoras = $controller->generarReporteHorasFacturables($clienteFiltroId, [
            'inicio' => "{$mesStr}-01",
            'fin' => date('Y-m-t', strtotime("{$mesStr}-01"))
        ]);
    } catch (Exception $e) {}
}

// Overhead hora
$overheadHoraActual = $controller->calcularOverheadHoraOperativa($mesFiltro, $añoFiltro);
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontify Practice Management — Suite Editable & Rentabilidad</title>
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
                        darkborder: 'rgba(56, 189, 248, 0.28)',
                        neoncyan: '#00F0FF',
                        kprimary: '#00B894',
                        kaccent: '#0284C7'
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace']
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        body {
            background-color: #040814;
            background-image: 
                radial-gradient(circle at 50% 0%, rgba(2, 132, 199, 0.16) 0%, transparent 60%),
                radial-gradient(circle at 100% 100%, rgba(0, 184, 148, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 0% 50%, rgba(6, 182, 212, 0.07) 0%, transparent 40%);
            background-attachment: fixed;
            color: #F8FAFC;
        }

        .glass-premium {
            background: linear-gradient(180deg, rgba(8, 22, 42, 0.88) 0%, rgba(4, 12, 24, 0.96) 100%);
            border: 1.5px solid rgba(56, 189, 248, 0.35);
            box-shadow: 0 10px 35px -5px rgba(0, 0, 0, 0.7), 0 0 25px -5px rgba(2, 132, 199, 0.22);
            backdrop-filter: blur(16px);
        }

        .glass-premium:hover {
            border-color: rgba(56, 189, 248, 0.6);
            box-shadow: 0 15px 40px -5px rgba(0, 0, 0, 0.8), 0 0 35px -2px rgba(6, 182, 212, 0.35);
        }

        .floating-pill {
            background: linear-gradient(180deg, #0284C7 0%, #0369A1 100%);
            border: 1px solid #38BDF8;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.4);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased text-slate-100 pb-16 selection:bg-cyan-500/20 selection:text-cyan-300">

    <!-- Top Navigation Bar Premium Dark -->
    <header class="border-b border-cyan-500/20 bg-darkbg/90 backdrop-blur-xl sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="index.php" class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-kprimary flex items-center justify-center text-darkbg font-extrabold text-xl shadow-lg shadow-cyan-500/30 hover:scale-105 transition">
                    <i class="fa-solid fa-briefcase"></i>
                </a>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-lg font-black tracking-tight text-white">KONTIFY</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 font-semibold tracking-wide">PRACTICE SUITE EDITABLE</span>
                    </div>
                    <p class="text-[11px] text-slate-400 font-medium">Gestión de Despacho & Blindaje Jurídico Venezuela</p>
                </div>
            </div>

            <!-- Tasa BCV & Perfil Rápido -->
            <div class="flex items-center space-x-3">
                <div class="hidden sm:flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-cyan-950/40 border border-cyan-500/30 text-xs">
                    <i class="fa-solid fa-building-columns text-cyan-400"></i>
                    <span class="text-slate-400">BCV:</span>
                    <span class="font-mono font-bold text-white">Bs. <?= number_format($tasaBcv, 2, ',', '.') ?></span>
                </div>
                <a href="index.php" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-300 border border-slate-700 transition flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Pestañas de Navegación de la Suite -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex overflow-x-auto gap-2 py-2 border-t border-slate-800/60 text-xs font-semibold no-scrollbar">
            <a href="?tab=cronometro&cliente_id=<?= $clienteFiltroId ?>" class="px-4 py-2 rounded-lg flex items-center gap-2 transition <?= $tabActiva === 'cronometro' ? 'bg-cyan-500 text-darkbg font-bold shadow-md shadow-cyan-500/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' ?>">
                <i class="fa-solid fa-stopwatch"></i> Cronómetro & Horas
            </a>
            <a href="?tab=rentabilidad&cliente_id=<?= $clienteFiltroId ?>&mes=<?= $mesFiltro ?>&año=<?= $añoFiltro ?>" class="px-4 py-2 rounded-lg flex items-center gap-2 transition <?= $tabActiva === 'rentabilidad' ? 'bg-cyan-500 text-darkbg font-bold shadow-md shadow-cyan-500/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' ?>">
                <i class="fa-solid fa-chart-pie"></i> Rentabilidad por Cliente
            </a>
            <a href="?tab=anexo&cliente_id=<?= $clienteFiltroId ?>" class="px-4 py-2 rounded-lg flex items-center gap-2 transition <?= $tabActiva === 'anexo' ? 'bg-cyan-500 text-darkbg font-bold shadow-md shadow-cyan-500/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' ?>">
                <i class="fa-solid fa-file-invoice-dollar"></i> Anexo Facturable
            </a>
            <a href="?tab=contratos&cliente_id=<?= $clienteFiltroId ?>" class="px-4 py-2 rounded-lg flex items-center gap-2 transition <?= $tabActiva === 'contratos' ? 'bg-cyan-500 text-darkbg font-bold shadow-md shadow-cyan-500/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' ?>">
                <i class="fa-solid fa-shield-halved"></i> Blindaje Contractual (SEC-7 / NIA)
            </a>
            <a href="?tab=aliados" class="px-4 py-2 rounded-lg flex items-center gap-2 transition <?= $tabActiva === 'aliados' ? 'bg-cyan-500 text-darkbg font-bold shadow-md shadow-cyan-500/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' ?>">
                <i class="fa-solid fa-handshake"></i> Directorio de Aliados
            </a>
            <a href="?tab=jerarquias" class="px-4 py-2 rounded-lg flex items-center gap-2 transition <?= $tabActiva === 'jerarquias' ? 'bg-cyan-500 text-darkbg font-bold shadow-md shadow-cyan-500/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' ?>">
                <i class="fa-solid fa-sitemap"></i> Matriz de Tarifas & Gastos
            </a>
        </div>
    </header>

    <!-- Notificación Toast -->
    <?php if ($toastMsg): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="p-4 rounded-xl flex items-center justify-between border <?= $toastTipo === 'success' ? 'bg-emerald-950/80 border-emerald-500/50 text-emerald-300' : 'bg-rose-950/80 border-rose-500/50 text-rose-300' ?> backdrop-blur-md shadow-lg">
            <div class="flex items-center space-x-3">
                <i class="fa-solid <?= $toastTipo === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?> text-lg"></i>
                <span class="text-sm font-semibold"><?= htmlspecialchars($toastMsg) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contenido Principal -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6 flex-1">

        <!-- ====================================================================
             PESTAÑA 1: CRONÓMETRO INTERACTIVO & REGISTRO DE HORAS (EDITABLE)
             ==================================================================== -->
        <?php if ($tabActiva === 'cronometro'): ?>
        <div class="space-y-6">
            <div class="text-center max-w-2xl mx-auto space-y-2 pt-2">
                <span class="px-3.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-widest text-cyan-300 bg-cyan-950/80 border border-cyan-400/40 shadow-sm">
                    Time-Tracking Operativo
                </span>
                <h2 class="text-3xl font-extrabold tracking-tight text-white">Cronómetro & Imputación en Vivo</h2>
                <p class="text-xs text-slate-400">Cronometra labores o imputa horas manuales. Todo es editable y corregible en tiempo real.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-6">
                <!-- Columna Izquierda: Display LED y Mandos -->
                <div class="lg:col-span-5 glass-premium rounded-3xl p-6 sm:p-8 flex flex-col justify-between relative overflow-hidden">
                    <div class="absolute -top-20 -left-20 w-52 h-52 bg-cyan-500/15 rounded-full blur-3xl pointer-events-none"></div>

                    <div>
                        <div class="flex justify-between items-center mb-6">
                            <span class="floating-pill px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider text-white">
                                <i class="fa-solid fa-bolt mr-1"></i> Modo Auxiliar
                            </span>
                            <div class="flex items-center space-x-2 text-xs text-slate-400">
                                <span id="statusIndicator" class="w-2.5 h-2.5 rounded-full bg-slate-500"></span>
                                <span id="statusLabel">Listo</span>
                            </div>
                        </div>

                        <div class="my-6 p-6 rounded-2xl bg-darkbg/95 border border-cyan-500/30 shadow-inner text-center">
                            <div id="chronoTime" class="font-mono text-5xl sm:text-6xl font-black tracking-tight text-white select-all">
                                00:00:00
                            </div>
                            <div class="mt-2 text-xs font-mono text-cyan-400 font-semibold tracking-wide">
                                ≈ <span id="chronoDecimal">0.00</span> Horas Incurridas
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <button type="button" id="chronoStart" onclick="startChrono()" class="py-3 px-3 rounded-xl bg-gradient-to-r from-cyan-500 to-kaccent hover:brightness-110 font-black text-darkbg text-xs uppercase tracking-wider flex items-center justify-center gap-1.5 shadow-lg shadow-cyan-500/30 transition transform active:scale-95">
                                <i class="fa-solid fa-play"></i> Iniciar
                            </button>
                            <button type="button" id="chronoPause" onclick="pauseChrono()" disabled class="py-3 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 font-bold text-slate-300 text-xs uppercase tracking-wider flex items-center justify-center gap-1.5 transition disabled:opacity-40 disabled:cursor-not-allowed">
                                <i class="fa-solid fa-pause"></i> Pausar
                            </button>
                            <button type="button" id="chronoReset" onclick="resetChrono()" class="py-3 px-3 rounded-xl bg-slate-900 border border-slate-700/60 hover:border-rose-500/50 hover:text-rose-400 font-bold text-slate-400 text-xs uppercase tracking-wider flex items-center justify-center gap-1.5 transition">
                                <i class="fa-solid fa-rotate-left"></i> Cero
                            </button>
                        </div>
                    </div>

                    <!-- Previsualización en Vivo -->
                    <div class="mt-8 pt-6 border-t border-cyan-500/20 space-y-3 text-xs">
                        <div class="flex justify-between items-center text-slate-400">
                            <span><i class="fa-solid fa-user-tag text-cyan-400 mr-1"></i> Costo Nómina Asignado:</span>
                            <span id="liveCostoNomina" class="font-mono font-bold text-white">$0.00 USD</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-400">
                            <span><i class="fa-solid fa-file-invoice text-emerald-400 mr-1"></i> Valor Facturable Estimado:</span>
                            <span id="liveValorCobro" class="font-mono font-bold text-emerald-400">$0.00 USD</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-400">
                            <span><i class="fa-solid fa-calculator text-cyan-400 mr-1"></i> Equivalente en Bolívares:</span>
                            <span id="liveBs" class="font-mono font-bold text-slate-300">Bs. 0,00</span>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Formulario de Imputación -->
                <div class="lg:col-span-7 glass-premium rounded-3xl p-6 sm:p-8">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-extrabold uppercase tracking-wide text-white flex items-center gap-2">
                            <i class="fa-solid fa-pen-to-square text-cyan-400"></i> Detalle de la Asignación
                        </h3>
                        <span class="text-xs text-slate-400 font-mono">Overhead: $<?= number_format($overheadHoraActual, 2) ?>/h</span>
                    </div>

                    <form method="POST" id="formRegistroHoras" class="space-y-4">
                        <input type="hidden" name="action" value="guardar_tiempo">
                        <input type="hidden" name="origen_registro" value="Cronometro">
                        <input type="hidden" name="horas" id="inputHorasValue" value="0.00">

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                                Cliente Receptor del Servicio <span class="text-cyan-400">*</span>
                            </label>
                            <div class="relative">
                                <select name="cliente_id" id="selectCliente" required class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-400 transition cursor-pointer appearance-none">
                                    <option value="">-- Seleccionar Razón Social / RIF --</option>
                                    <?php foreach ($clientes as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $clienteFiltroId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['razon_social']) ?> (<?= htmlspecialchars($c['rif']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-4 top-3.5 text-slate-500 pointer-events-none text-xs"></i>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                                    Jerarquía / Rol Técnico <span class="text-cyan-400">*</span>
                                </label>
                                <select name="jerarquia_id" id="selectJerarquia" onchange="recalcularMetricasLive()" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-400 transition cursor-pointer">
                                    <?php foreach ($jerarquias as $j): ?>
                                    <option value="<?= $j['id'] ?>" data-costo="<?= $j['tarifa_costo_hora_usd'] ?>" data-cobro="<?= $j['tarifa_cobro_hora_usd'] ?>">
                                        <?= htmlspecialchars($j['rol_nombre']) ?> ($<?= number_format($j['tarifa_costo_hora_usd'], 2) ?> / $<?= number_format($j['tarifa_cobro_hora_usd'], 2) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">Fecha de Labor</label>
                                <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-400 transition">
                            </div>
                        </div>

                        <!-- Checkboxes de Naturaleza -->
                        <div class="p-3.5 rounded-2xl bg-darkbg/80 border border-cyan-500/20 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Naturaleza</label>
                                <select name="tipo_tarea" id="selectTipoTarea" onchange="ajustarTipoTarea()" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-cyan-400">
                                    <option value="Ordinaria">Ordinaria (Fee Mensual)</option>
                                    <option value="Extraordinaria">Extraordinaria (Especial)</option>
                                </select>
                            </div>
                            <div class="flex items-center pt-3">
                                <label class="flex items-center space-x-2 text-xs text-slate-300 cursor-pointer">
                                    <input type="checkbox" name="sujeta_contrato" id="chkSujetaContrato" checked class="w-4 h-4 rounded bg-darkbg border-slate-600 text-cyan-500 focus:ring-0">
                                    <span>Amparada en Cuota</span>
                                </label>
                            </div>
                            <div class="flex items-center pt-3">
                                <label class="flex items-center space-x-2 text-xs text-slate-300 cursor-pointer">
                                    <input type="checkbox" name="es_facturable" id="chkFacturable" checked class="w-4 h-4 rounded bg-darkbg border-slate-600 text-cyan-500 focus:ring-0">
                                    <span>Es Facturable</span>
                                </label>
                            </div>
                        </div>

                        <!-- Gastos Directos Reembolsables -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5 flex justify-between">
                                <span>Gastos Directos Reembolsables (USD)</span>
                                <span class="text-[11px] text-slate-400 font-normal">Aranceles SENIAT, traslados, timbres fiscales</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-2.5 text-slate-500 text-sm font-mono">$</span>
                                <input type="number" step="0.01" min="0" name="gastos_directos_usd" value="0.00" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl pl-8 pr-4 py-2 text-sm text-white focus:outline-none focus:border-cyan-400 transition font-mono">
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                                Descripción Detallada <span class="text-cyan-400">*</span>
                            </label>
                            <textarea name="descripcion" id="txtDescripcion" rows="2" required placeholder="Describe la labor contable, fiscal o de auditoría..." class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-400 transition placeholder-slate-500"></textarea>
                            
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                <button type="button" onclick="pegarTag('Declaración IVA Portal SENIAT')" class="px-2.5 py-1 rounded-lg bg-cyan-950/60 border border-cyan-500/30 text-[11px] text-cyan-300 hover:bg-cyan-900/80 transition">#IVA SENIAT</button>
                                <button type="button" onclick="pegarTag('Libros de Compras/Ventas')" class="px-2.5 py-1 rounded-lg bg-cyan-950/60 border border-cyan-500/30 text-[11px] text-cyan-300 hover:bg-cyan-900/80 transition">#Libros Fiscales</button>
                                <button type="button" onclick="pegarTag('Nómina Quincenal LOTTT + IVSS')" class="px-2.5 py-1 rounded-lg bg-cyan-950/60 border border-cyan-500/30 text-[11px] text-cyan-300 hover:bg-cyan-900/80 transition">#Nómina LOTTT</button>
                                <button type="button" onclick="pegarTag('Retenciones ISLR en TXT')" class="px-2.5 py-1 rounded-lg bg-cyan-950/60 border border-cyan-500/30 text-[11px] text-cyan-300 hover:bg-cyan-900/80 transition">#Retenciones ISLR</button>
                                <button type="button" onclick="pegarTag('Balance General VEN-NIF PYMES')" class="px-2.5 py-1 rounded-lg bg-cyan-950/60 border border-cyan-500/30 text-[11px] text-cyan-300 hover:bg-cyan-900/80 transition">#VEN-NIF</button>
                            </div>
                        </div>

                        <button type="submit" class="w-full py-4 rounded-xl bg-gradient-to-r from-cyan-500 via-kaccent to-kprimary hover:brightness-110 text-darkbg font-black text-sm uppercase tracking-wider flex items-center justify-center gap-2 shadow-xl shadow-cyan-500/25 transition transform active:scale-[0.99]">
                            <i class="fa-solid fa-floppy-disk text-base"></i> Guardar e Imputar Horas al Cliente
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tabla de Imputaciones: Totalmente EDITABLE y ELIMINABLE -->
            <div class="glass-premium rounded-3xl p-6 sm:p-7">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-extrabold uppercase tracking-wide text-white flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-cyan-400"></i> Registro de Imputaciones Recientes (Editables)
                    </h3>
                    <span class="text-xs text-slate-400 font-mono"><?= count($ultimasImputaciones) ?> registros listados</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 border-b border-cyan-500/30 uppercase font-semibold">
                            <tr>
                                <th class="py-2.5 px-3">Fecha</th>
                                <th class="py-2.5 px-3">Cliente</th>
                                <th class="py-2.5 px-3">Rol</th>
                                <th class="py-2.5 px-3">Tarea Realizada</th>
                                <th class="py-2.5 px-3 text-center">Horas</th>
                                <th class="py-2.5 px-3 text-right">Tarifa</th>
                                <th class="py-2.5 px-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 text-slate-300">
                            <?php if (empty($ultimasImputaciones)): ?>
                            <tr><td colspan="7" class="py-8 text-center text-slate-500 italic">No hay registros de tiempo todavía. ¡Usa el cronómetro para registrar el primero!</td></tr>
                            <?php else: ?>
                                <?php foreach ($ultimasImputaciones as $imp): ?>
                                <tr class="hover:bg-cyan-950/20 transition">
                                    <td class="py-3 px-3 font-mono text-slate-400"><?= htmlspecialchars($imp['fecha']) ?></td>
                                    <td class="py-3 px-3 font-semibold text-white"><?= htmlspecialchars($imp['razon_social']) ?></td>
                                    <td class="py-3 px-3"><span class="px-2 py-0.5 rounded bg-darkbg border border-slate-700 text-[10px] text-cyan-300 font-mono"><?= htmlspecialchars($imp['rol_nombre']) ?></span></td>
                                    <td class="py-3 px-3 max-w-[220px] truncate" title="<?= htmlspecialchars($imp['descripcion']) ?>"><?= htmlspecialchars($imp['descripcion']) ?></td>
                                    <td class="py-3 px-3 text-center font-mono font-bold text-cyan-300"><?= number_format($imp['horas'], 2) ?> h</td>
                                    <td class="py-3 px-3 text-right font-mono text-emerald-400 font-bold">$<?= number_format($imp['tarifa_cobro_aplicada_usd'], 2) ?></td>
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <!-- Botón Editar Registro de Horas -->
                                        <button onclick='abrirModalEditarTiempo(<?= json_encode($imp) ?>)' class="px-2 py-1 rounded-lg bg-cyan-950/60 hover:bg-cyan-900 border border-cyan-500/30 text-cyan-300 text-[11px] font-bold mr-1 transition" title="Editar este registro">
                                            <i class="fa-solid fa-pencil"></i>
                                        </button>
                                        <!-- Form Eliminar Registro de Horas -->
                                        <form method="POST" class="inline" onsubmit="return confirm('¿Seguro que deseas eliminar este registro de tiempo?');">
                                            <input type="hidden" name="action" value="eliminar_tiempo">
                                            <input type="hidden" name="id" value="<?= $imp['id'] ?>">
                                            <button type="submit" class="px-2 py-1 rounded-lg bg-rose-950/60 hover:bg-rose-900 border border-rose-500/30 text-rose-300 text-[11px] font-bold transition" title="Eliminar registro">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ====================================================================
             PESTAÑA 2: RENTABILIDAD POR CLIENTE
             ==================================================================== -->
        <?php elseif ($tabActiva === 'rentabilidad'): ?>
        <div class="space-y-6">
            <div class="glass-premium rounded-2xl p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
                        <i class="fa-solid fa-chart-line text-cyan-400"></i> Análisis de Margen y Costeo Operativo
                    </h2>
                    <p class="text-xs text-slate-400">Desglose analítico de honorarios cobrados vs costo laboral, overhead y margen de contribución.</p>
                </div>

                <form method="GET" class="flex flex-wrap items-center gap-2.5">
                    <input type="hidden" name="tab" value="rentabilidad">
                    <select name="cliente_id" onchange="this.form.submit()" class="bg-darkbg border border-cyan-500/40 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none">
                        <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $clienteFiltroId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['razon_social']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="mes" onchange="this.form.submit()" class="bg-darkbg border border-cyan-500/40 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $mesFiltro ? 'selected' : '' ?>><?= DateTime::createFromFormat('!m', $m)->format('F') ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="año" onchange="this.form.submit()" class="bg-darkbg border border-cyan-500/40 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none">
                        <option value="2025" <?= $añoFiltro == 2025 ? 'selected' : '' ?>>2025</option>
                        <option value="2026" <?= $añoFiltro == 2026 ? 'selected' : '' ?>>2026</option>
                    </select>
                </form>
            </div>

            <?php if ($rentabilidadData): 
                $k = $rentabilidadData['kpis'];
                $diag = $k['diagnostico'];
            ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="glass-premium rounded-2xl p-5 border-l-4 border-l-cyan-400">
                    <span class="text-xs uppercase font-extrabold tracking-wider text-slate-400">Honorarios del Período</span>
                    <div class="text-2xl font-black font-mono text-white mt-1">$<?= number_format($k['honorarios_cobrados_usd'], 2) ?> <span class="text-xs text-cyan-400">USD</span></div>
                    <div class="text-[11px] text-slate-400 mt-1">≈ Bs. <?= number_format($k['honorarios_cobrados_usd'] * $tasaBcv, 2, ',', '.') ?></div>
                </div>

                <div class="glass-premium rounded-2xl p-5 border-l-4 border-l-rose-500">
                    <span class="text-xs uppercase font-extrabold tracking-wider text-slate-400">Costo Operativo Real</span>
                    <div class="text-2xl font-black font-mono text-rose-400 mt-1">$<?= number_format($k['costo_operativo_total_usd'], 2) ?> <span class="text-xs text-rose-300">USD</span></div>
                    <div class="text-[11px] text-slate-400 mt-1">Nómina + Overhead + Gastos Directos</div>
                </div>

                <div class="glass-premium rounded-2xl p-5 border-l-4 <?= $k['margen_contribucion_usd'] >= 0 ? 'border-l-emerald-400' : 'border-l-rose-600' ?>">
                    <span class="text-xs uppercase font-extrabold tracking-wider text-slate-400">Margen de Contribución</span>
                    <div class="text-2xl font-black font-mono <?= $k['margen_contribucion_usd'] >= 0 ? 'text-emerald-400' : 'text-rose-500' ?> mt-1">
                        $<?= number_format($k['margen_contribucion_usd'], 2) ?> <span class="text-xs">USD</span>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-1"><?= $k['margen_contribucion_porcentaje'] ?>% sobre honorarios</div>
                </div>

                <div class="glass-premium rounded-2xl p-5 border-l-4 border-l-cyan-300">
                    <span class="text-xs uppercase font-extrabold tracking-wider text-slate-400">Estado de Rentabilidad</span>
                    <div class="text-base font-extrabold text-white mt-1 flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full <?= $k['margen_contribucion_porcentaje'] >= 30 ? 'bg-emerald-400' : 'bg-rose-400' ?>"></span>
                        <?= htmlspecialchars($diag['estado']) ?>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-1 truncate"><?= htmlspecialchars($diag['mensaje']) ?></div>
                </div>
            </div>

            <!-- Desglose por Jerarquía de Personal Asignado -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div class="lg:col-span-7 glass-premium rounded-3xl p-6">
                    <h3 class="text-sm font-extrabold uppercase tracking-wide text-white mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-users text-cyan-400"></i> Horas Invertidas por Nivel Profesional
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-slate-400 border-b border-slate-700/60 uppercase font-semibold">
                                <tr>
                                    <th class="py-2.5 px-3">Rol</th>
                                    <th class="py-2.5 px-3 text-center">Horas</th>
                                    <th class="py-2.5 px-3 text-right">Costo HH</th>
                                    <th class="py-2.5 px-3 text-right">Cobro Potencial</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 text-slate-300">
                                <?php if (empty($rentabilidadData['desglose_jerarquia'])): ?>
                                <tr><td colspan="4" class="py-6 text-center text-slate-500 italic">No hay horas registradas en este período para este cliente.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($rentabilidadData['desglose_jerarquia'] as $dj): ?>
                                    <tr>
                                        <td class="py-3 px-3 font-semibold text-white flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                                            <?= htmlspecialchars($dj['rol']) ?>
                                        </td>
                                        <td class="py-3 px-3 text-center font-mono font-bold text-cyan-300"><?= number_format($dj['horas_totales'], 2) ?> h</td>
                                        <td class="py-3 px-3 text-right font-mono text-rose-300">$<?= number_format($dj['costo_total_usd'], 2) ?></td>
                                        <td class="py-3 px-3 text-right font-mono text-emerald-400">$<?= number_format($dj['cobro_potencial_usd'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Estructura de Absorción -->
                <div class="lg:col-span-5 glass-premium rounded-3xl p-6 flex flex-col justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold uppercase tracking-wide text-white mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-network-wired text-cyan-400"></i> Estructura de Absorción
                        </h3>
                        <p class="text-xs text-slate-400 mb-4">Prorrateo de costos indirectos del despacho.</p>
                        
                        <div class="space-y-3 text-xs">
                            <div class="flex justify-between p-2.5 rounded-xl bg-darkbg border border-slate-800">
                                <span class="text-slate-400">Costo Directo Laboral:</span>
                                <span class="font-mono font-bold text-white">$<?= number_format($k['costo_directo_laboral_usd'], 2) ?> USD</span>
                            </div>
                            <div class="flex justify-between p-2.5 rounded-xl bg-darkbg border border-slate-800">
                                <span class="text-slate-400">Overhead Prorrateado:</span>
                                <span class="font-mono font-bold text-cyan-400">$<?= number_format($k['costo_overhead_prorrateado_usd'], 2) ?> USD</span>
                            </div>
                            <div class="flex justify-between p-2.5 rounded-xl bg-darkbg border border-slate-800">
                                <span class="text-slate-400">Gastos Directos Reembolsables:</span>
                                <span class="font-mono font-bold text-amber-300">$<?= number_format($k['gastos_directos_usd'], 2) ?> USD</span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($rentabilidadData['recomendaciones'])): ?>
                    <div class="mt-4 p-3.5 rounded-xl bg-cyan-950/60 border border-cyan-500/30 text-xs">
                        <span class="font-bold text-cyan-300 uppercase tracking-wide flex items-center gap-1.5 mb-1.5">
                            <i class="fa-solid fa-lightbulb"></i> Recomendación del Sistema:
                        </span>
                        <ul class="list-disc list-inside text-slate-300 space-y-1 text-[11px]">
                            <?php foreach ($rentabilidadData['recomendaciones'] as $rec): ?>
                            <li><?= htmlspecialchars($rec) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ====================================================================
             PESTAÑA 3: ANEXO FACTURABLE LISTO PARA ADJUNTAR
             ==================================================================== -->
        <?php elseif ($tabActiva === 'anexo'): ?>
        <div class="space-y-6">
            <div class="glass-premium rounded-2xl p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
                        <i class="fa-solid fa-file-contract text-cyan-400"></i> Anexo Detallado de Horas Facturables
                    </h2>
                    <p class="text-xs text-slate-400">Documento anexo a la factura fiscal de honorarios profesionales de conformidad con la Ley de IVA y VEN-NIF.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-200 border border-slate-700 transition flex items-center gap-2">
                        <i class="fa-solid fa-print"></i> Imprimir / Exportar PDF
                    </button>
                </div>
            </div>

            <?php if ($reporteHoras): ?>
            <div class="glass-premium rounded-3xl p-8 border border-cyan-500/40 space-y-6">
                <!-- Membrete Formal -->
                <div class="flex justify-between items-start border-b border-cyan-500/20 pb-6">
                    <div>
                        <span class="text-xs font-mono px-2.5 py-1 rounded bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 uppercase">Anexo Oficial de Horas</span>
                        <h3 class="text-xl font-bold text-white mt-2"><?= htmlspecialchars($reporteHoras['despacho']['nombre_despacho'] ?? 'Kontify Despacho Contable') ?></h3>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($reporteHoras['despacho']['rif'] ?? 'J-00000000-0') ?> — <?= htmlspecialchars($reporteHoras['despacho']['cpc_numero'] ?? 'CPC Colegiado') ?></p>
                    </div>
                    <div class="text-right">
                        <div class="font-mono text-sm font-bold text-cyan-400"><?= $reporteHoras['anexo_numero'] ?></div>
                        <div class="text-xs text-slate-400">Fecha: <?= date('d/m/Y') ?></div>
                        <div class="text-xs text-slate-400">Cliente: <strong class="text-white"><?= htmlspecialchars($reporteHoras['cliente']['razon_social']) ?></strong></div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 border-b border-cyan-500/30 uppercase font-semibold">
                            <tr>
                                <th class="py-2.5 px-3">Fecha</th>
                                <th class="py-2.5 px-3">Profesional / Rol</th>
                                <th class="py-2.5 px-3">Labor Realizada</th>
                                <th class="py-2.5 px-3 text-center">Tipo</th>
                                <th class="py-2.5 px-3 text-center">Horas</th>
                                <th class="py-2.5 px-3 text-right">Tarifa ($/h)</th>
                                <th class="py-2.5 px-3 text-right">Subtotal USD</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 text-slate-300">
                            <?php if (empty($reporteHoras['items'])): ?>
                            <tr><td colspan="7" class="py-8 text-center text-slate-500 italic">No existen registros de horas imputados para este cliente en el período.</td></tr>
                            <?php else: ?>
                                <?php foreach ($reporteHoras['items'] as $item): ?>
                                <tr>
                                    <td class="py-3 px-3 font-mono text-slate-400"><?= $item['fecha'] ?></td>
                                    <td class="py-3 px-3 font-semibold text-white"><?= htmlspecialchars($item['profesional']) ?> <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($item['rol']) ?></span></td>
                                    <td class="py-3 px-3 max-w-[280px]"><?= htmlspecialchars($item['descripcion']) ?></td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] <?= $item['tipo_tarea'] === 'Extraordinaria' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30' : 'bg-slate-800 text-slate-400' ?>">
                                            <?= $item['tipo_tarea'] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-center font-mono font-bold text-cyan-300"><?= number_format($item['horas'], 2) ?> h</td>
                                    <td class="py-3 px-3 text-right font-mono">$<?= number_format($item['tarifa_hora_usd'], 2) ?></td>
                                    <td class="py-3 px-3 text-right font-mono font-bold <?= $item['subtotal_usd'] > 0 ? 'text-emerald-400' : 'text-slate-500' ?>">
                                        $<?= number_format($item['subtotal_usd'], 2) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-cyan-500/20 pt-4 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="text-xs text-slate-400 space-y-1">
                        <div>Horas Ordinarias (en cuota): <strong class="text-slate-200"><?= $reporteHoras['totales']['horas_ordinarias_contrato'] ?> h</strong></div>
                        <div>Horas Extraordinarias Facturables: <strong class="text-cyan-300"><?= $reporteHoras['totales']['horas_extraordinarias'] ?> h</strong></div>
                    </div>
                    <div class="text-right glass-premium p-4 rounded-2xl border border-cyan-500/40">
                        <div class="text-xs uppercase text-slate-400">Total Facturable en Anexo:</div>
                        <div class="text-2xl font-black font-mono text-cyan-300">$<?= number_format($reporteHoras['totales']['total_a_facturar_usd'], 2) ?> USD</div>
                        <div class="text-xs text-slate-400 font-mono">≈ Bs. <?= number_format($reporteHoras['totales']['total_a_facturar_bs'], 2, ',', '.') ?> (Tasa BCV <?= $tasaBcv ?>)</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ====================================================================
             PESTAÑA 4: BLINDAJE JURÍDICO & CONTRATOS NORMATIVOS (TEXTO EDITABLE)
             ==================================================================== -->
        <?php elseif ($tabActiva === 'contratos'): 
            $tipoContratoSeleccionado = $_GET['tipo_contrato'] ?? 'SEC_7_RECURRENTE';
            $contratoGenerado = null;
            try {
                $contratoGenerado = $controller->generarPlantillaContrato($tipoContratoSeleccionado, $clienteFiltroId);
            } catch (Exception $e) {}
        ?>
        <div class="space-y-6">
            <div class="glass-premium rounded-2xl p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
                        <i class="fa-solid fa-shield-halved text-cyan-400"></i> Blindaje Normativo & Cartas de Encargo
                    </h2>
                    <p class="text-xs text-slate-400">Modelos preconfigurados de protección legal, deslinde tributario y cumplimiento colegiado en Venezuela.</p>
                </div>
            </div>

            <!-- Selector de Modelos de Contrato -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="?tab=contratos&cliente_id=<?= $clienteFiltroId ?>&tipo_contrato=SEC_7_RECURRENTE" class="glass-premium rounded-2xl p-4 transition border <?= $tipoContratoSeleccionado === 'SEC_7_RECURRENTE' ? 'border-cyan-400 shadow-md shadow-cyan-500/30 bg-cyan-950/40' : 'hover:border-cyan-500/50' ?>">
                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-cyan-500/20 text-cyan-300">Norma FCCPV</span>
                    <h4 class="text-sm font-bold text-white mt-2">SEC-7 Servicios Contables</h4>
                    <p class="text-[11px] text-slate-400 mt-1">Deslinde de responsabilidad penal y fiscal ante el SENIAT por soportes del cliente.</p>
                </a>

                <a href="?tab=contratos&cliente_id=<?= $clienteFiltroId ?>&tipo_contrato=NIA_210_AUDITORIA" class="glass-premium rounded-2xl p-4 transition border <?= $tipoContratoSeleccionado === 'NIA_210_AUDITORIA' ? 'border-cyan-400 shadow-md shadow-cyan-500/30 bg-cyan-950/40' : 'hover:border-cyan-500/50' ?>">
                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-cyan-500/20 text-cyan-300">Norma IFAC</span>
                    <h4 class="text-sm font-bold text-white mt-2">NIA 210 Encargo Auditoría</h4>
                    <p class="text-[11px] text-slate-400 mt-1">Acuerdo formal de términos y delimitación del control interno corporativo.</p>
                </a>

                <a href="?tab=contratos&cliente_id=<?= $clienteFiltroId ?>&tipo_contrato=NIIF_PYMES_SECCION23" class="glass-premium rounded-2xl p-4 transition border <?= $tipoContratoSeleccionado === 'NIIF_PYMES_SECCION23' ? 'border-cyan-400 shadow-md shadow-cyan-500/30 bg-cyan-950/40' : 'hover:border-cyan-500/50' ?>">
                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-cyan-500/20 text-cyan-300">Sección 23 VEN-NIF</span>
                    <h4 class="text-sm font-bold text-white mt-2">Reconocimiento Ingresos</h4>
                    <p class="text-[11px] text-slate-400 mt-1">Modelo de 5 Pasos para identificar obligaciones de desempeño separadas.</p>
                </a>

                <a href="?tab=contratos&cliente_id=<?= $clienteFiltroId ?>&tipo_contrato=COMISARIO_MERCANTIL" class="glass-premium rounded-2xl p-4 transition border <?= $tipoContratoSeleccionado === 'COMISARIO_MERCANTIL' ? 'border-cyan-400 shadow-md shadow-cyan-500/30 bg-cyan-950/40' : 'hover:border-cyan-500/50' ?>">
                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-cyan-500/20 text-cyan-300">Cód. Comercio VE</span>
                    <h4 class="text-sm font-bold text-white mt-2">Comisario Mercantil</h4>
                    <p class="text-[11px] text-slate-400 mt-1">Aceptación formal bajo arts. 287/309 con declaración jurada de parentesco.</p>
                </a>
            </div>

            <!-- Visor y Editor del Contrato Renderizado -->
            <?php if ($contratoGenerado): ?>
            <div class="glass-premium rounded-3xl p-6 sm:p-8 space-y-4">
                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 border-b border-cyan-500/20 pb-4">
                    <div>
                        <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($contratoGenerado['titulo']) ?></h3>
                        <span class="text-xs text-cyan-400 font-mono"><?= htmlspecialchars($contratoGenerado['normativa_referencia']) ?></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="copiarContrato()" class="px-3.5 py-1.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/40 text-xs font-bold transition flex items-center gap-1.5">
                            <i class="fa-regular fa-copy"></i> Copiar Texto
                        </button>
                    </div>
                </div>

                <!-- Textarea 100% EDITABLE -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1 flex justify-between">
                        <span>Texto del Contrato (Puedes editarlo directamente aquí):</span>
                        <span class="text-cyan-400">Modo Edición Libre Activo</span>
                    </label>
                    <textarea id="contratoTextoEditable" rows="18" class="w-full p-5 rounded-2xl bg-darkbg/95 border border-slate-800 text-xs leading-relaxed text-slate-200 font-mono focus:outline-none focus:border-cyan-400 transition shadow-inner"><?= htmlspecialchars($contratoGenerado['contenido_contrato']) ?></textarea>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ====================================================================
             PESTAÑA 5: DIRECTORIO DE ALIADOS MULTIDISCIPLINARIOS (100% EDITABLE)
             ==================================================================== -->
        <?php elseif ($tabActiva === 'aliados'): ?>
        <div class="space-y-6">
            <div class="glass-premium rounded-2xl p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
                        <i class="fa-solid fa-users-gear text-cyan-400"></i> Red de Aliados Multidisciplinarios
                    </h2>
                    <p class="text-xs text-slate-400">Profesionales externos colegiados para proyectos especiales, litigios, avalúos de activos y dictámenes periciales.</p>
                </div>
                <!-- Botón Agregar Nuevo Aliado -->
                <button onclick="abrirModalAliado()" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-kaccent hover:brightness-110 text-darkbg font-black text-xs uppercase tracking-wider flex items-center gap-2 shadow-lg shadow-cyan-500/20 transition transform active:scale-95">
                    <i class="fa-solid fa-user-plus text-sm"></i> + Nuevo Aliado
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($aliados as $a): ?>
                <div class="glass-premium rounded-3xl p-6 flex flex-col justify-between relative overflow-hidden group">
                    <div>
                        <div class="flex justify-between items-start mb-3">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-cyan-500/20 text-cyan-300 border border-cyan-500/40">
                                <?= htmlspecialchars($a['especialidad']) ?>
                            </span>
                            <!-- Acciones Editar / Eliminar Aliado -->
                            <div class="flex items-center gap-1">
                                <button onclick='abrirModalAliado(<?= json_encode($a) ?>)' class="w-7 h-7 rounded-lg bg-darkbg hover:bg-cyan-950 border border-slate-700 hover:border-cyan-500/50 text-cyan-300 flex items-center justify-center text-xs transition" title="Editar aliado">
                                    <i class="fa-solid fa-pencil"></i>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('¿Seguro que deseas eliminar a este aliado?');">
                                    <input type="hidden" name="action" value="eliminar_aliado">
                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="w-7 h-7 rounded-lg bg-darkbg hover:bg-rose-950 border border-slate-700 hover:border-rose-500/50 text-rose-400 flex items-center justify-center text-xs transition" title="Eliminar aliado">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <h4 class="text-base font-bold text-white"><?= htmlspecialchars($a['nombre_completo']) ?></h4>
                        <p class="text-xs text-slate-400 font-mono mt-0.5"><?= htmlspecialchars($a['colegiatura_numero']) ?> (<?= htmlspecialchars($a['rif_cedula']) ?>)</p>

                        <div class="mt-4 space-y-1.5 text-xs text-slate-300">
                            <div class="flex items-center gap-2"><i class="fa-solid fa-phone text-cyan-400 text-xs w-4"></i> <?= htmlspecialchars($a['telefono']) ?></div>
                            <div class="flex items-center gap-2"><i class="fa-solid fa-envelope text-cyan-400 text-xs w-4"></i> <?= htmlspecialchars($a['email']) ?></div>
                            <div class="flex items-center gap-2"><i class="fa-solid fa-location-dot text-cyan-400 text-xs w-4"></i> <?= htmlspecialchars($a['direccion_ciudad']) ?></div>
                        </div>

                        <?php if (!empty($a['notas_evaluacion'])): ?>
                        <div class="mt-4 p-3 rounded-xl bg-darkbg/80 border border-slate-800 text-[11px] text-slate-400 italic">
                            "<?= htmlspecialchars($a['notas_evaluacion']) ?>"
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-6 pt-4 border-t border-cyan-500/20 flex justify-between items-center">
                        <div>
                            <span class="text-[10px] text-slate-400 block uppercase">Honorario Referencial</span>
                            <span class="font-mono font-bold text-emerald-400 text-sm">$<?= number_format($a['honorarios_referenciales_usd'], 2) ?> USD</span>
                            <span class="text-[10px] text-slate-500 block"><?= htmlspecialchars($a['modalidad_cobro']) ?></span>
                        </div>
                        <a href="https://wa.me/<?= preg_replace('/\D/', '', $a['telefono']) ?>" target="_blank" class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-darkbg font-bold text-xs flex items-center gap-1.5 transition shadow-lg shadow-emerald-500/20">
                            <i class="fa-brands fa-whatsapp text-sm"></i> WhatsApp
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ====================================================================
             PESTAÑA 6: MATRIZ DE JERARQUÍAS & COSTOS FIJOS (100% EDITABLE)
             ==================================================================== -->
        <?php elseif ($tabActiva === 'jerarquias'): ?>
        <div class="space-y-6">
            <div class="glass-premium rounded-2xl p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
                        <i class="fa-solid fa-sitemap text-cyan-400"></i> Matriz de Tarifas & Estructura de Costos Fijos
                    </h2>
                    <p class="text-xs text-slate-400">Todo es editable: ajusta las tarifas de nómina, cobro a clientes y gastos de oficina para recalcular el Overhead.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="abrirModalJerarquia()" class="px-3.5 py-2 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/40 text-xs font-bold transition flex items-center gap-1.5">
                        <i class="fa-solid fa-plus"></i> + Nuevo Rol
                    </button>
                    <button onclick="abrirModalCostoFijo()" class="px-3.5 py-2 rounded-xl bg-kprimary/20 hover:bg-kprimary/30 text-emerald-300 border border-emerald-500/40 text-xs font-bold transition flex items-center gap-1.5">
                        <i class="fa-solid fa-plus"></i> + Nuevo Costo Fijo
                    </button>
                </div>
            </div>

            <!-- Tabla de Jerarquías de la Firma (Editable) -->
            <div class="glass-premium rounded-3xl p-6 sm:p-8">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-extrabold uppercase tracking-wide text-white flex items-center gap-2">
                        <i class="fa-solid fa-user-shield text-cyan-400"></i> Jerarquías y Tarifas Operativas ($/Hora)
                    </h3>
                    <span class="text-xs text-slate-400 font-mono">Tasa Overhead: <strong>$<?= number_format($overheadHoraActual, 2) ?>/h</strong></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 border-b border-cyan-500/30 uppercase font-semibold">
                            <tr>
                                <th class="py-2.5 px-3">Nivel</th>
                                <th class="py-2.5 px-3">Rol Profesional</th>
                                <th class="py-2.5 px-3">Descripción de Alcance</th>
                                <th class="py-2.5 px-3 text-right">Costo Nómina/Hora</th>
                                <th class="py-2.5 px-3 text-right">Tarifa Cobro/Hora</th>
                                <th class="py-2.5 px-3 text-center">Margen Objetivo</th>
                                <th class="py-2.5 px-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 text-slate-300">
                            <?php foreach ($jerarquias as $jer): 
                                $costo = (float)$jer['tarifa_costo_hora_usd'];
                                $cobro = (float)$jer['tarifa_cobro_hora_usd'];
                                $margen = $cobro > 0 ? round((($cobro - $costo) / $cobro) * 100, 1) : 0;
                            ?>
                            <tr class="hover:bg-cyan-950/20 transition">
                                <td class="py-3 px-3 font-mono font-bold text-cyan-400">#<?= $jer['nivel_jerarquico'] ?></td>
                                <td class="py-3 px-3 font-bold text-white"><?= htmlspecialchars($jer['rol_nombre']) ?></td>
                                <td class="py-3 px-3 text-slate-400 max-w-xs"><?= htmlspecialchars($jer['descripcion'] ?? '') ?></td>
                                <td class="py-3 px-3 text-right font-mono text-rose-300 font-semibold">$<?= number_format($costo, 2) ?></td>
                                <td class="py-3 px-3 text-right font-mono text-emerald-400 font-bold">$<?= number_format($cobro, 2) ?></td>
                                <td class="py-3 px-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] bg-cyan-500/20 text-cyan-300 font-mono font-bold">
                                        <?= $margen ?>%
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    <button onclick='abrirModalJerarquia(<?= json_encode($jer) ?>)' class="px-2.5 py-1 rounded-lg bg-cyan-950/60 hover:bg-cyan-900 border border-cyan-500/30 text-cyan-300 text-[11px] font-bold transition mr-1" title="Editar tarifas de este rol">
                                        <i class="fa-solid fa-pencil"></i> Editar
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Estructura de Costos Fijos Mensuales (Editable) -->
            <div class="glass-premium rounded-3xl p-6 sm:p-8">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-extrabold uppercase tracking-wide text-white flex items-center gap-2">
                        <i class="fa-solid fa-building text-cyan-400"></i> Gastos Fijos Operativos del Despacho (Editables)
                    </h3>
                    <span class="font-mono text-xs text-slate-300">Total Mensual: <strong class="text-rose-400">$<?= number_format($totalCostosFijosMes, 2) ?> USD</strong></span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($costosFijos as $cf): ?>
                    <div class="p-4 rounded-2xl bg-darkbg/90 border border-slate-800 flex justify-between items-center group hover:border-cyan-500/40 transition">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-cyan-400 block"><?= htmlspecialchars($cf['categoria']) ?></span>
                            <span class="text-xs font-semibold text-white"><?= htmlspecialchars($cf['concepto']) ?></span>
                            <span class="text-[10px] text-slate-500 block font-mono"><?= htmlspecialchars($cf['frecuencia']) ?></span>
                        </div>
                        <div class="text-right">
                            <div class="font-mono font-bold text-slate-200">$<?= number_format($cf['monto_mensual_usd'], 2) ?></div>
                            <div class="flex items-center justify-end gap-1 mt-1">
                                <button onclick='abrirModalCostoFijo(<?= json_encode($cf) ?>)' class="p-1 rounded bg-darkbg hover:bg-cyan-950 text-cyan-400 text-xs transition" title="Editar monto">
                                    <i class="fa-solid fa-pencil"></i>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('¿Seguro que deseas eliminar este costo fijo?');">
                                    <input type="hidden" name="action" value="eliminar_costo_fijo">
                                    <input type="hidden" name="id" value="<?= $cf['id'] ?>">
                                    <button type="submit" class="p-1 rounded bg-darkbg hover:bg-rose-950 text-rose-400 text-xs transition" title="Eliminar costo">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <!-- ====================================================================
         MODALES EDITABLES (CRUD)
         ==================================================================== -->

    <!-- 1. MODAL EDITAR REGISTRO DE TIEMPO -->
    <div id="modalEditarTiempo" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md hidden">
        <div class="w-full max-w-lg glass-premium rounded-3xl p-6 sm:p-7 border border-cyan-500/50 shadow-2xl relative">
            <div class="flex justify-between items-center border-b border-cyan-500/20 pb-3 mb-4">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-pencil text-cyan-400"></i> Modificar Imputación de Horas
                </h3>
                <button onclick="cerrarModal('modalEditarTiempo')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="space-y-3.5">
                <input type="hidden" name="action" value="actualizar_tiempo">
                <input type="hidden" name="id" id="editTiempoId">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Cliente</label>
                    <select name="cliente_id" id="editTiempoCliente" required class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                        <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['razon_social']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Rol</label>
                        <select name="jerarquia_id" id="editTiempoJerarquia" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                            <?php foreach ($jerarquias as $j): ?>
                            <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['rol_nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Horas</label>
                        <input type="number" step="0.25" min="0.25" name="horas" id="editTiempoHoras" required class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-cyan-400">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Fecha</label>
                        <input type="date" name="fecha" id="editTiempoFecha" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Gastos USD</label>
                        <input type="number" step="0.01" min="0" name="gastos_directos_usd" id="editTiempoGastos" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-cyan-400">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 p-2.5 rounded-xl bg-darkbg border border-slate-800">
                    <label class="flex items-center space-x-2 text-xs text-slate-300 cursor-pointer">
                        <input type="checkbox" name="sujeta_contrato" id="editTiempoSujeta" class="w-4 h-4 rounded bg-darkbg border-slate-600 text-cyan-500">
                        <span>Amparada en Cuota</span>
                    </label>
                    <label class="flex items-center space-x-2 text-xs text-slate-300 cursor-pointer">
                        <input type="checkbox" name="es_facturable" id="editTiempoFacturable" class="w-4 h-4 rounded bg-darkbg border-slate-600 text-cyan-500">
                        <span>Es Facturable</span>
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Descripción</label>
                    <textarea name="descripcion" id="editTiempoDesc" rows="2" required class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-400"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="cerrarModal('modalEditarTiempo')" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs text-slate-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-darkbg font-bold text-xs uppercase tracking-wider">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. MODAL EDITAR / CREAR JERARQUÍA -->
    <div id="modalJerarquia" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md hidden">
        <div class="w-full max-w-md glass-premium rounded-3xl p-6 sm:p-7 border border-cyan-500/50 shadow-2xl">
            <div class="flex justify-between items-center border-b border-cyan-500/20 pb-3 mb-4">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-sitemap text-cyan-400"></i> <span id="modalJerarquiaTitulo">Editar Rol / Jerarquía</span>
                </h3>
                <button onclick="cerrarModal('modalJerarquia')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="space-y-3.5">
                <input type="hidden" name="action" value="guardar_jerarquia">
                <input type="hidden" name="id" id="jerarquiaId" value="0">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Nombre del Rol</label>
                    <input type="text" name="rol_nombre" id="jerarquiaRol" required placeholder="Ej: Especialista Tributario" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Nivel (1-5)</label>
                        <input type="number" min="1" max="5" name="nivel_jerarquico" id="jerarquiaNivel" value="1" required class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-cyan-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Costo Nómina $/h</label>
                        <input type="number" step="0.5" min="0" name="tarifa_costo_hora_usd" id="jerarquiaCosto" required class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-cyan-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Tarifa Cobro $/h</label>
                        <input type="number" step="0.5" min="0" name="tarifa_cobro_hora_usd" id="jerarquiaCobro" required class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-cyan-400">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Descripción del Alcance</label>
                    <textarea name="descripcion" id="jerarquiaDesc" rows="2" placeholder="Responsabilidades asignadas a este nivel..." class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-cyan-400"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="cerrarModal('modalJerarquia')" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs text-slate-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-darkbg font-bold text-xs uppercase tracking-wider">Guardar Rol</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. MODAL EDITAR / CREAR COSTO FIJO -->
    <div id="modalCostoFijo" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md hidden">
        <div class="w-full max-w-md glass-premium rounded-3xl p-6 sm:p-7 border border-cyan-500/50 shadow-2xl">
            <div class="flex justify-between items-center border-b border-cyan-500/20 pb-3 mb-4">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-building text-cyan-400"></i> <span id="modalCostoFijoTitulo">Editar Costo Fijo</span>
                </h3>
                <button onclick="cerrarModal('modalCostoFijo')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="space-y-3.5">
                <input type="hidden" name="action" value="guardar_costo_fijo">
                <input type="hidden" name="id" id="cfId" value="0">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Categoría</label>
                    <select name="categoria" id="cfCategoria" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                        <option value="Alquiler de Oficina">Alquiler de Oficina</option>
                        <option value="Servicios e Internet">Servicios e Internet</option>
                        <option value="Licencias y Software">Licencias y Software</option>
                        <option value="Nomina Administrativa">Nomina Administrativa</option>
                        <option value="Colegiatura y Tributos Municipales">Colegiatura y Tributos Municipales</option>
                        <option value="Otros Costos Operativos">Otros Costos Operativos</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Concepto / Descripción</label>
                    <input type="text" name="concepto" id="cfConcepto" required placeholder="Ej: Fibra Óptica Dedicada + Backup 4G" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Monto Mensual (USD)</label>
                        <input type="number" step="1" min="0" name="monto_mensual_usd" id="cfMonto" required class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3.5 py-2 text-xs text-white font-mono focus:outline-none focus:border-cyan-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Frecuencia</label>
                        <select name="frecuencia" id="cfFrecuencia" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                            <option value="Mensual">Mensual</option>
                            <option value="Trimestral">Trimestral</option>
                            <option value="Semestral">Semestral</option>
                            <option value="Anual">Anual</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="cerrarModal('modalCostoFijo')" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs text-slate-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-darkbg font-bold text-xs uppercase tracking-wider">Guardar Costo Fijo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. MODAL EDITAR / CREAR ALIADO MULTIDISCIPLINARIO -->
    <div id="modalAliado" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md hidden">
        <div class="w-full max-w-lg glass-premium rounded-3xl p-6 sm:p-7 border border-cyan-500/50 shadow-2xl">
            <div class="flex justify-between items-center border-b border-cyan-500/20 pb-3 mb-4">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-user-plus text-cyan-400"></i> <span id="modalAliadoTitulo">Nuevo Aliado Multidisciplinario</span>
                </h3>
                <button onclick="cerrarModal('modalAliado')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="action" value="guardar_aliado">
                <input type="hidden" name="id" id="aliadoId" value="0">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Nombre Completo</label>
                        <input type="text" name="nombre_completo" id="aliadoNombre" required placeholder="Ej: Dr. Alejandro Castillo" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Especialidad</label>
                        <select name="especialidad" id="aliadoEspecialidad" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                            <option value="Perito Avaluador (SOVECT)">Perito Avaluador (SOVECT)</option>
                            <option value="Especialista en Costos & Precios Justos">Especialista en Costos & Precios Justos</option>
                            <option value="Licenciado en Relaciones Industriales / RRHH">Licenciado en RRHH / LOTTT</option>
                            <option value="Ingeniero Civil / Peritaje de Obras">Ingeniero Civil / Peritaje</option>
                            <option value="Corredor de Seguros Patrimoniales">Corredor de Seguros</option>
                            <option value="Gestor Tributario & Aduanas">Gestor Tributario & Aduanas</option>
                            <option value="Abogado Corporativo / Mercantil">Abogado Mercantil / Laboral</option>
                            <option value="Especialista en Ciberseguridad & TI">Ciberseguridad & TI</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">RIF / Cédula</label>
                        <input type="text" name="rif_cedula" id="aliadoRif" placeholder="V-12345678" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">N° Colegiatura</label>
                        <input type="text" name="colegiatura_numero" id="aliadoColegiatura" placeholder="CIV-123456" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Teléfono / WhatsApp</label>
                        <input type="text" name="telefono" id="aliadoTelefono" required placeholder="0414-1234567" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Email</label>
                        <input type="email" name="email" id="aliadoEmail" placeholder="contacto@aliado.com" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Ciudad</label>
                        <input type="text" name="direccion_ciudad" id="aliadoCiudad" value="Caracas, Venezuela" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Honorario Ref. USD</label>
                        <input type="number" step="10" min="0" name="honorarios_referenciales_usd" id="aliadoHonorario" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-cyan-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Modalidad Cobro</label>
                        <select name="modalidad_cobro" id="aliadoModalidad" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-400">
                            <option value="Por Dictamen/Informe">Por Dictamen</option>
                            <option value="Por Proyecto">Por Proyecto</option>
                            <option value="Por Hora">Por Hora</option>
                            <option value="Mixto">Mixto</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Rating (1-5)</label>
                        <input type="number" min="1" max="5" name="calificacion_despacho" id="aliadoRating" value="5" class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-cyan-400">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Notas de Evaluación</label>
                    <textarea name="notas_evaluacion" id="aliadoNotas" rows="2" placeholder="Experiencia con este profesional en casos anteriores..." class="w-full bg-darkbg border border-cyan-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-400"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="cerrarModal('modalAliado')" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs text-slate-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-darkbg font-bold text-xs uppercase tracking-wider">Guardar Aliado</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts de Interacción y Manejo de Modales -->
    <script>
        let chronoTimer = null;
        let chronoSeconds = 0;
        let chronoRunning = false;
        const tasaBcvVal = <?= (float)$tasaBcv ?>;

        function formatChrono(s) {
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            const sec = s % 60;
            return [
                h.toString().padStart(2, '0'),
                m.toString().padStart(2, '0'),
                sec.toString().padStart(2, '0')
            ].join(':');
        }

        function updateChronoUI() {
            const display = document.getElementById('chronoTime');
            if (!display) return;
            display.innerText = formatChrono(chronoSeconds);
            const dec = (chronoSeconds / 3600).toFixed(2);
            document.getElementById('chronoDecimal').innerText = dec;
            document.getElementById('inputHorasValue').value = dec;
            recalcularMetricasLive();
        }

        function startChrono() {
            if (chronoRunning) return;
            chronoRunning = true;
            document.getElementById('chronoStart').disabled = true;
            document.getElementById('chronoStart').classList.add('opacity-40');
            document.getElementById('chronoPause').disabled = false;

            const ind = document.getElementById('statusIndicator');
            ind.className = 'w-2.5 h-2.5 rounded-full bg-cyan-400 animate-ping';
            document.getElementById('statusLabel').innerText = 'Contando';
            document.getElementById('statusLabel').className = 'text-cyan-400 font-bold';

            chronoTimer = setInterval(() => {
                chronoSeconds++;
                updateChronoUI();
            }, 1000);
        }

        function pauseChrono() {
            if (!chronoRunning) return;
            chronoRunning = false;
            clearInterval(chronoTimer);

            document.getElementById('chronoStart').disabled = false;
            document.getElementById('chronoStart').classList.remove('opacity-40');
            document.getElementById('chronoPause').disabled = true;

            const ind = document.getElementById('statusIndicator');
            ind.className = 'w-2.5 h-2.5 rounded-full bg-amber-400';
            document.getElementById('statusLabel').innerText = 'Pausado';
            document.getElementById('statusLabel').className = 'text-amber-400 font-bold';
        }

        function resetChrono() {
            pauseChrono();
            if (chronoSeconds > 0 && !confirm('¿Reiniciar el cronómetro a 0?')) return;
            chronoSeconds = 0;
            updateChronoUI();
            document.getElementById('statusIndicator').className = 'w-2.5 h-2.5 rounded-full bg-slate-500';
            document.getElementById('statusLabel').innerText = 'Listo';
            document.getElementById('statusLabel').className = 'text-slate-400';
        }

        function recalcularMetricasLive() {
            const sel = document.getElementById('selectJerarquia');
            if (!sel) return;
            const opt = sel.options[sel.selectedIndex];
            const costoH = parseFloat(opt.getAttribute('data-costo') || 0);
            const cobroH = parseFloat(opt.getAttribute('data-cobro') || 0);
            const dec = parseFloat(document.getElementById('inputHorasValue').value || 0);

            const costoTotal = (dec * costoH).toFixed(2);
            const cobroTotal = (dec * cobroH).toFixed(2);
            const bsTotal = (cobroTotal * tasaBcvVal).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            document.getElementById('liveCostoNomina').innerText = `$${costoTotal} USD`;
            document.getElementById('liveValorCobro').innerText = `$${cobroTotal} USD`;
            document.getElementById('liveBs').innerText = `Bs. ${bsTotal}`;
        }

        function ajustarTipoTarea() {
            const tipo = document.getElementById('selectTipoTarea').value;
            const chk = document.getElementById('chkSujetaContrato');
            if (tipo === 'Extraordinaria') {
                chk.checked = false;
            } else {
                chk.checked = true;
            }
        }

        function pegarTag(tag) {
            const txt = document.getElementById('txtDescripcion');
            if (!txt) return;
            if (txt.value.trim() === '') {
                txt.value = tag + ': ';
            } else {
                txt.value += ' | ' + tag;
            }
            txt.focus();
        }

        function copiarContrato() {
            const txt = document.getElementById('contratoTextoEditable').value;
            navigator.clipboard.writeText(txt).then(() => {
                alert('¡Texto del contrato copiado al portapapeles!');
            });
        }

        // ==========================================
        // FUNCIONES MODALES EDITABLES (CRUD)
        // ==========================================
        function cerrarModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        // 1. Abrir Modal Editar Registro de Tiempo
        function abrirModalEditarTiempo(data) {
            document.getElementById('editTiempoId').value = data.id;
            document.getElementById('editTiempoCliente').value = data.cliente_id;
            document.getElementById('editTiempoJerarquia').value = data.jerarquia_id;
            document.getElementById('editTiempoHoras').value = data.horas;
            document.getElementById('editTiempoFecha').value = data.fecha;
            document.getElementById('editTiempoGastos').value = data.gastos_directos_usd || '0.00';
            document.getElementById('editTiempoSujeta').checked = (parseInt(data.sujeta_contrato) === 1);
            document.getElementById('editTiempoFacturable').checked = (parseInt(data.es_facturable) === 1);
            document.getElementById('editTiempoDesc').value = data.descripcion || '';
            document.getElementById('modalEditarTiempo').classList.remove('hidden');
        }

        // 2. Abrir Modal Jerarquía
        function abrirModalJerarquia(data = null) {
            if (data) {
                document.getElementById('modalJerarquiaTitulo').innerText = 'Editar Rol / Tarifas';
                document.getElementById('jerarquiaId').value = data.id;
                document.getElementById('jerarquiaRol').value = data.rol_nombre;
                document.getElementById('jerarquiaNivel').value = data.nivel_jerarquico;
                document.getElementById('jerarquiaCosto').value = data.tarifa_costo_hora_usd;
                document.getElementById('jerarquiaCobro').value = data.tarifa_cobro_hora_usd;
                document.getElementById('jerarquiaDesc').value = data.descripcion || '';
            } else {
                document.getElementById('modalJerarquiaTitulo').innerText = 'Nuevo Rol / Jerarquía';
                document.getElementById('jerarquiaId').value = 0;
                document.getElementById('jerarquiaRol').value = '';
                document.getElementById('jerarquiaNivel').value = 1;
                document.getElementById('jerarquiaCosto').value = '10.00';
                document.getElementById('jerarquiaCobro').value = '30.00';
                document.getElementById('jerarquiaDesc').value = '';
            }
            document.getElementById('modalJerarquia').classList.remove('hidden');
        }

        // 3. Abrir Modal Costo Fijo
        function abrirModalCostoFijo(data = null) {
            if (data) {
                document.getElementById('modalCostoFijoTitulo').innerText = 'Editar Costo Fijo';
                document.getElementById('cfId').value = data.id;
                document.getElementById('cfCategoria').value = data.categoria;
                document.getElementById('cfConcepto').value = data.concepto;
                document.getElementById('cfMonto').value = data.monto_mensual_usd;
                document.getElementById('cfFrecuencia').value = data.frecuencia;
            } else {
                document.getElementById('modalCostoFijoTitulo').innerText = 'Nuevo Costo Fijo';
                document.getElementById('cfId').value = 0;
                document.getElementById('cfCategoria').value = 'Alquiler de Oficina';
                document.getElementById('cfConcepto').value = '';
                document.getElementById('cfMonto').value = '100.00';
                document.getElementById('cfFrecuencia').value = 'Mensual';
            }
            document.getElementById('modalCostoFijo').classList.remove('hidden');
        }

        // 4. Abrir Modal Aliado
        function abrirModalAliado(data = null) {
            if (data) {
                document.getElementById('modalAliadoTitulo').innerText = 'Editar Aliado';
                document.getElementById('aliadoId').value = data.id;
                document.getElementById('aliadoNombre').value = data.nombre_completo;
                document.getElementById('aliadoEspecialidad').value = data.especialidad;
                document.getElementById('aliadoRif').value = data.rif_cedula || '';
                document.getElementById('aliadoColegiatura').value = data.colegiatura_numero || '';
                document.getElementById('aliadoTelefono').value = data.telefono || '';
                document.getElementById('aliadoEmail').value = data.email || '';
                document.getElementById('aliadoCiudad').value = data.direccion_ciudad || 'Caracas';
                document.getElementById('aliadoHonorario').value = data.honorarios_referenciales_usd || '0.00';
                document.getElementById('aliadoModalidad').value = data.modalidad_cobro || 'Por Dictamen/Informe';
                document.getElementById('aliadoRating').value = data.calificacion_despacho || 5;
                document.getElementById('aliadoNotas').value = data.notas_evaluacion || '';
            } else {
                document.getElementById('modalAliadoTitulo').innerText = 'Nuevo Aliado Multidisciplinario';
                document.getElementById('aliadoId').value = 0;
                document.getElementById('aliadoNombre').value = '';
                document.getElementById('aliadoEspecialidad').value = 'Perito Avaluador (SOVECT)';
                document.getElementById('aliadoRif').value = '';
                document.getElementById('aliadoColegiatura').value = '';
                document.getElementById('aliadoTelefono').value = '';
                document.getElementById('aliadoEmail').value = '';
                document.getElementById('aliadoCiudad').value = 'Caracas, Venezuela';
                document.getElementById('aliadoHonorario').value = '150.00';
                document.getElementById('aliadoModalidad').value = 'Por Dictamen/Informe';
                document.getElementById('aliadoRating').value = 5;
                document.getElementById('aliadoNotas').value = '';
            }
            document.getElementById('modalAliado').classList.remove('hidden');
        }

        // Validación inicial del cronómetro
        const formHoras = document.getElementById('formRegistroHoras');
        if (formHoras) {
            formHoras.addEventListener('submit', function(e) {
                let h = parseFloat(document.getElementById('inputHorasValue').value);
                if (h <= 0) {
                    const man = prompt('El cronómetro está en cero. Escribe las horas a imputar (ej: 1.5):', '1.0');
                    if (man && !isNaN(parseFloat(man))) {
                        document.getElementById('inputHorasValue').value = parseFloat(man);
                    } else {
                        e.preventDefault();
                        alert('Debes ingresar una cantidad de horas válida.');
                        return;
                    }
                }
            });
        }

        // Inicializar display
        updateChronoUI();
    </script>
</body>
</html>
