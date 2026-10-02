<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

global $pdo;
$user_id = $_SESSION['user_id'];$user_nombre = $_SESSION['user_nombre'] ?? 'Contador';$mensajeError = '';

// 1. AUTO-CONFIGURACIÓN DE TABLAS MULTI-EMPRESA
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `empresas` (`id` INT AUTO_INCREMENT PRIMARY KEY, `nombre` VARCHAR(150) NOT NULL, `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `empresa_usuarios` (`empresa_id` INT NOT NULL, `user_id` INT NOT NULL, `rol` ENUM('Administrador', 'Operador', 'Asistente') NOT NULL DEFAULT 'Operador', PRIMARY KEY (`empresa_id`, `user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

// 2. AUTO-CREACIÓN DE EMPRESA (Magia Silenciosa si es usuario nuevo)
$stmtCheck =$pdo->prepare("SELECT COUNT(*) FROM empresa_usuarios WHERE user_id = ?");
$stmtCheck->execute([$user_id]);
if ($stmtCheck->fetchColumn() == 0) {$primerNombre = explode(' ', $user_nombre)[0];$nombreDespacho = "Despacho de " . $primerNombre;
    
    $pdo->beginTransaction();
    try {
        $stmtEmp =$pdo->prepare("INSERT INTO empresas (nombre) VALUES (?)");
        $stmtEmp->execute([$nombreDespacho]);
        $nuevaEmpId =$pdo->lastInsertId();
        
        $stmtVinculo =$pdo->prepare("INSERT INTO empresa_usuarios (empresa_id, user_id, rol) VALUES (?, ?, 'Administrador')");
        $stmtVinculo->execute([$nuevaEmpId,$user_id]);
        
        // Perfil base para cotizaciones
        $stmtConfig =$pdo->prepare("INSERT INTO configuracion_despacho (empresa_id, nombre_despacho) VALUES (?, ?)");
        $stmtConfig->execute([$nuevaEmpId, $nombreDespacho]);$pdo->commit();
        header("Location: seleccionar_empresa.php"); // Recargar para verla reflejada
        exit;
    } catch (Exception $e) {$pdo->rollBack(); }
}

// 3. PROCESAR ACCIONES (Crear, Editar, Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion =$_POST['accion'] ?? '';

    // A. CREAR EMPRESA
    if ($accion === 'crear') {
        $nombre_emp = trim($_POST['nombre_empresa']);
        if (!empty($nombre_emp)) {$pdo->beginTransaction();
            try {
                $stmt =$pdo->prepare("INSERT INTO empresas (nombre) VALUES (?)");
                $stmt->execute([$nombre_emp]);
                $nueva_emp_id =$pdo->lastInsertId();

                $stmt2 =$pdo->prepare("INSERT INTO empresa_usuarios (empresa_id, user_id, rol) VALUES (?, ?, 'Administrador')");
                $stmt2->execute([$nueva_emp_id,$user_id]);
                
                $stmtConfig =$pdo->prepare("INSERT INTO configuracion_despacho (empresa_id, nombre_despacho) VALUES (?, ?)");
                $stmtConfig->execute([$nueva_emp_id,$nombre_emp]);

                $pdo->commit();
                
                // Auto-entrar a la recién creada
                $_SESSION['empresa_id'] =$nueva_emp_id;
                $_SESSION['empresa_nombre'] =$nombre_emp;
                $_SESSION['empresa_rol'] = 'Administrador';
                header("Location: index.php");
                exit;
            } catch (Exception $e) {$pdo->rollBack(); }
        }
    }

    // B. EDITAR EMPRESA (Cambiar Nombre)
    if ($accion === 'editar') {
        $emp_id = (int)$_POST['empresa_id'];
        $nuevo_nombre = trim($_POST['nuevo_nombre']);
        
        // Validar permisos
        $stmtVal =$pdo->prepare("SELECT rol FROM empresa_usuarios WHERE empresa_id = ? AND user_id = ?");
        $stmtVal->execute([$emp_id,$user_id]);
        if ($stmtVal->fetchColumn() === 'Administrador' && !empty($nuevo_nombre)) {
            $stmtUpd =$pdo->prepare("UPDATE empresas SET nombre = ? WHERE id = ?");
            $stmtUpd->execute([$nuevo_nombre,$emp_id]);
            
            // Si la empresa editada estaba en uso en la sesión, actualizo la variable
            if(isset($_SESSION['empresa_id']) && $_SESSION['empresa_id'] ==$emp_id) {
                $_SESSION['empresa_nombre'] =$nuevo_nombre;
            }
            header("Location: seleccionar_empresa.php");
            exit;
        }
    }

    // C. ELIMINAR EMPRESA
    if ($accion === 'eliminar') {
        $emp_id = (int)$_POST['empresa_id'];
        
        // Evitar que el usuario se quede sin empresas
        $stmtCount =$pdo->prepare("SELECT COUNT(*) FROM empresa_usuarios WHERE user_id = ?");
        $stmtCount->execute([$user_id]);
        
        if ($stmtCount->fetchColumn() > 1) {
            $stmtVal =$pdo->prepare("SELECT rol FROM empresa_usuarios WHERE empresa_id = ? AND user_id = ?");
            $stmtVal->execute([$emp_id,$user_id]);
            if ($stmtVal->fetchColumn() === 'Administrador') {$pdo->beginTransaction();
                try {
                    // Se borra el vinculo y la empresa (La data de clientes/ingresos queda huérfana pero no rompe el sistema)
                    $pdo->prepare("DELETE FROM empresa_usuarios WHERE empresa_id = ?")->execute([$emp_id]);$pdo->prepare("DELETE FROM empresas WHERE id = ?")->execute([$emp_id]);$pdo->commit();
                    
                    // Si eliminó la empresa en la que estaba logueado, lo deslogueo de ese entorno
                    if(isset($_SESSION['empresa_id']) && $_SESSION['empresa_id'] ==$emp_id) {
                        unset($_SESSION['empresa_id']);
                        unset($_SESSION['empresa_nombre']);
                        unset($_SESSION['empresa_rol']);
                    }
                    header("Location: seleccionar_empresa.php");
                    exit;
                } catch (Exception $e) {$pdo->rollBack(); }
            }
        } else {
            $mensajeError = "Por seguridad, no puedes eliminar tu única empresa.";
        }
    }
}

// 4. SELECCIONAR EMPRESA PARA ENTRAR
if (isset($_GET['select'])) {
    $emp_id = (int)$_GET['select'];
    $stmt =$pdo->prepare("SELECT e.nombre, eu.rol FROM empresas e JOIN empresa_usuarios eu ON e.id = eu.empresa_id WHERE e.id = ? AND eu.user_id = ?");
    $stmt->execute([$emp_id,$user_id]);
    $empresa =$stmt->fetch();
    
    if ($empresa) {
        $_SESSION['empresa_id'] =$emp_id;
        $_SESSION['empresa_nombre'] =$empresa['nombre'];
        $_SESSION['empresa_rol'] =$empresa['rol'];
        header("Location: index.php");
        exit;
    }
}

// 5. LISTAR EMPRESAS DEL USUARIO
$empresas = [];
try {
    $stmt =$pdo->prepare("SELECT e.*, eu.rol FROM empresas e JOIN empresa_usuarios eu ON e.id = eu.empresa_id WHERE eu.user_id = ? ORDER BY e.creado_el ASC");
    $stmt->execute([$user_id]);
    $empresas =$stmt->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Empresa | Kontify App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #1e3a8a; color: white; min-height: 100vh; }
        .glass-card { background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.15); transition: all 0.3s; position: relative; }
        .glass-card:hover { background: rgba(255, 255, 255, 0.12); transform: translateY(-3px); border-color: rgba(255, 255, 255, 0.3); box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .btn-action { transition: all 0.2s; opacity: 0.6; }
        .btn-action:hover { opacity: 1; transform: scale(1.1); }
    </style>
</head>
<body class="flex flex-col items-center justify-center p-6 bg-gradient-to-br from-[#1e3a8a] to-[#1e40af]">

    <div class="text-center mb-12 animate-fade-in mt-10">
        <i class="fa-solid fa-chart-line text-5xl text-blue-300 mb-4 opacity-80"></i>
        <h1 class="text-4xl font-extrabold tracking-widest uppercase mb-2">NEXUS GESTIÓN</h1>
        <p class="text-blue-200 font-medium">Seleccione Empresa para comenzar</p>
    </div>

    <?php if(!empty($mensajeError)): ?>
        <div class="bg-rose-500/20 border border-rose-500 text-rose-100 px-6 py-3 rounded-xl mb-8 flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= $mensajeError ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 w-full max-w-6xl">
        
        <?php foreach ($empresas as$emp): ?>
            <div class="glass-card rounded-2xl flex flex-col items-center justify-center text-center min-h-[160px] group cursor-pointer" onclick="window.location.href='?select=<?= $emp['id'] ?>'">
                
                <!-- Botones de Acción (Editar / Eliminar) -->
                <?php if ($emp['rol'] === 'Administrador'): ?>
                <div class="absolute top-3 right-3 flex gap-2 z-10">
                    <button type="button" onclick="event.stopPropagation(); openEditModal(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['nombre'])) ?>')" class="btn-action w-8 h-8 rounded-lg bg-white/10 hover:bg-blue-500 text-white flex items-center justify-center" title="Editar Nombre">
                        <i class="fa-solid fa-pen text-xs"></i>
                    </button>
                    <button type="button" onclick="event.stopPropagation(); openDeleteModal(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['nombre'])) ?>')" class="btn-action w-8 h-8 rounded-lg bg-white/10 hover:bg-rose-500 text-white flex items-center justify-center" title="Eliminar Empresa">
                        <i class="fa-solid fa-trash text-xs"></i>
                    </button>
                </div>
                <?php endif; ?>

                <div class="p-8 w-full h-full flex flex-col items-center justify-center">
                    <i class="fa-solid fa-building text-3xl mb-4 text-blue-200 group-hover:text-white transition-colors"></i>
                    <h3 class="text-sm font-bold uppercase tracking-wide px-2"><?= htmlspecialchars($emp['nombre']) ?></h3>
                    <span class="text-[9px] text-blue-300 mt-2 uppercase tracking-widest bg-blue-900/50 px-2 py-0.5 rounded"><?= htmlspecialchars($emp['rol']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Botón Añadir Nuevo -->
        <div onclick="document.getElementById('modalNueva').classList.remove('hidden')" class="rounded-2xl flex flex-col items-center justify-center text-center cursor-pointer min-h-[160px] border-2 border-dashed border-blue-300/40 hover:border-blue-300 hover:bg-blue-800/30 transition-all text-blue-200 hover:text-white">
            <div class="w-12 h-12 rounded-full bg-blue-500/20 flex items-center justify-center mb-3 text-xl">
                <i class="fa-solid fa-plus"></i>
            </div>
            <h3 class="text-sm font-bold uppercase tracking-wide">Añadir Nuevo</h3>
        </div>
    </div>

    <!-- MODAL: Nueva Empresa -->
    <div id="modalNueva" class="hidden fixed inset-0 bg-[#0f172a]/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md text-slate-800 shadow-2xl">
            <div class="flex justify-between items-center mb-5 border-b border-slate-100 pb-3">
                <h3 class="font-bold text-lg text-[#1e3a8a]">Registrar Nueva Empresa</h3>
                <button onclick="document.getElementById('modalNueva').classList.add('hidden')" class="text-slate-400 hover:text-rose-500 text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <div class="mb-5">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2 tracking-wide">Nombre Comercial o Despacho</label>
                    <input type="text" name="nombre_empresa" required class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500 transition-colors font-medium text-sm">
                </div>
                <button type="submit" class="w-full bg-[#1e3a8a] hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition-colors shadow-lg shadow-blue-500/30">
                    <i class="fa-solid fa-plus mr-2"></i> Crear Entorno
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL: Editar Empresa -->
    <div id="modalEditar" class="hidden fixed inset-0 bg-[#0f172a]/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md text-slate-800 shadow-2xl">
            <div class="flex justify-between items-center mb-5 border-b border-slate-100 pb-3">
                <h3 class="font-bold text-lg text-[#1e3a8a]">Cambiar Nombre de Empresa</h3>
                <button onclick="document.getElementById('modalEditar').classList.add('hidden')" class="text-slate-400 hover:text-rose-500 text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="empresa_id" id="edit_empresa_id">
                <div class="mb-5">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2 tracking-wide">Nuevo Nombre</label>
                    <input type="text" name="nuevo_nombre" id="edit_nombre_input" required class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500 transition-colors font-medium text-sm uppercase">
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition-colors shadow-lg shadow-blue-500/30">
                    <i class="fa-solid fa-floppy-disk mr-2"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL: Eliminar Empresa -->
    <div id="modalEliminar" class="hidden fixed inset-0 bg-[#0f172a]/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md text-slate-800 shadow-2xl text-center">
            <div class="w-16 h-16 rounded-full bg-rose-100 text-rose-500 flex items-center justify-center mx-auto mb-4 text-2xl">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 class="font-extrabold text-xl text-slate-800 mb-2">¿Eliminar Empresa?</h3>
            <p class="text-sm text-slate-500 mb-6">Estás a punto de eliminar <strong id="delete_nombre_texto" class="text-slate-800 uppercase"></strong>. Esta acción desvinculará a los usuarios asociados. No podrás deshacerlo.</p>
            
            <form method="POST" class="flex gap-3">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="empresa_id" id="delete_empresa_id">
                
                <button type="button" onclick="document.getElementById('modalEliminar').classList.add('hidden')" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 rounded-xl transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 bg-rose-500 hover:bg-rose-600 text-white font-bold py-3 rounded-xl transition-colors shadow-lg shadow-rose-500/30">
                    Sí, Eliminar
                </button>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, nombre) {
            document.getElementById('edit_empresa_id').value = id;
            document.getElementById('edit_nombre_input').value = nombre;
            document.getElementById('modalEditar').classList.remove('hidden');
        }

        function openDeleteModal(id, nombre) {
            document.getElementById('delete_empresa_id').value = id;
            document.getElementById('delete_nombre_texto').innerText = nombre;
            document.getElementById('modalEliminar').classList.remove('hidden');
        }
    </script>
</body>
</html>