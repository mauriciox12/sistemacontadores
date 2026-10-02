<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

global $pdo;
$db = $pdo;

// Datos de usuario en sesión
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentUserName = $_SESSION['user_nombre'] ?? 'Contador';
$currentUserAvatar = $_SESSION['user_avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($currentUserName) . '&background=12B99D&color=fff';
$empresaNombre = $_SESSION['empresa_nombre'] ?? 'Mi Despacho';
$empresaRol = $_SESSION['empresa_rol'] ?? 'Administrador';

// Control de Tasa BCV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_tasa_bcv'])) {
    $nuevaTasa = floatval(str_replace(',', '.', $_POST['tasa_bcv_input']));
    if ($nuevaTasa > 0) {
        $_SESSION['tasa_bcv'] = $nuevaTasa;
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

$tasaBcvActual = (float)($_SESSION['tasa_bcv'] ?? 65.50);
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$esAdminPlataforma = ($currentUserId === 1 || strpos(strtolower($currentUserName), 'mauricio') !== false);
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= $pageTitle ?? 'Kontify App | Gestión Administrativa y Fiscal' ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'k-bg': '#F6F8FA',
                        'k-navy': '#13202C',
                        'k-text': '#172330',
                        'k-muted': '#758293',
                        'k-border': '#E8EDF1',
                        'k-primary': '#12B99D',
                        'k-primary-dark': '#0A947D',
                        'k-primary-soft': '#EAF9F5',
                        'k-warning': '#B98500',
                        'k-danger': '#D85A70',
                    },
                    fontFamily: {
                        sans: ['Montserrat', 'system-ui', 'sans-serif'],
                        display: ['"Bebas Neue"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #F6F8FA; color: #172330; }
        .k-card { background: #FFFFFF; border: 1px solid #E8EDF1; border-radius: 18px; box-shadow: 0 4px 20px rgba(19,32,44,0.04); }
        .k-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px; border-radius: 12px; font-weight: 600; font-size: 13px; transition: all 180ms ease; cursor: pointer; border: none; }
        .k-btn-primary { background: #12B99D; color: #fff; box-shadow: 0 4px 14px rgba(18,185,157,0.25); }
        .k-btn-primary:hover { background: #0A947D; }
        .k-btn-secondary { background: #FFFFFF; color: #172330; border: 1px solid #E8EDF1; }
        .k-btn-secondary:hover { background: #F6F8FA; }
        .k-input { width: 100%; padding: 10px 14px; background: #FFFFFF; border: 1px solid #E8EDF1; border-radius: 10px; font-size: 13px; outline: none; transition: border-color 200ms; }
        .k-input:focus { border-color: #12B99D; box-shadow: 0 0 0 3px rgba(18,185,157,0.15); }
        .k-label { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: #758293; margin-bottom: 5px; }
        .k-nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 12px; font-size: 13px; font-weight: 500; color: #758293; text-decoration: none; transition: all 180ms; }
        .k-nav-item:hover { background: #F6F8FA; color: #172330; }
        .k-nav-item.active { background: #EAF9F5; color: #0A947D; font-weight: 700; }
    </style>
</head>
<body class="min-h-screen antialiased">
    <div class="flex h-screen overflow-hidden">
        
        <!-- SIDEBAR -->
        <aside class="hidden lg:flex lg:flex-col w-[252px] bg-white border-r border-k-border shrink-0">
            <div class="p-5 border-b border-k-border">
                <a href="index.php" class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#20d3b5] to-[#0ea88e] flex items-center justify-center text-white font-bold text-lg shadow-md">K</div>
                    <div>
                        <span class="text-sm font-extrabold text-k-navy tracking-tight">KONTIFY <span class="text-k-primary">APP</span></span>
                        <span class="text-[9px] text-k-muted block tracking-widest uppercase font-semibold">Sistema Fiscal VE</span>
                    </div>
                </a>
            </div>

            <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-3 pt-2 pb-1">Principal</div>
                <a href="index.php" class="k-nav-item <?= $currentPage === 'index' ? 'active' : '' ?>">
                    <i class="fa-solid fa-gauge w-5 text-center"></i> Dashboard
                </a>
                <a href="index.php#tablaClientes" class="k-nav-item">
                    <i class="fa-solid fa-users w-5 text-center"></i> Clientes
                </a>
                <!-- MULTIACCESO EN EL MENÚ -->
                <a href="usuarios_lista.php" class="k-nav-item <?= $currentPage === 'usuarios_lista' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users-gear w-5 text-center text-blue-600"></i> Multiacceso
                </a>
                <a href="ingresos.php" class="k-nav-item <?= $currentPage === 'ingresos' ? 'active' : '' ?>">
                    <i class="fa-solid fa-sack-dollar w-5 text-center"></i> Ingresos & Honorarios
                </a>
                
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-3 pt-4 pb-1">Gestión Fiscal</div>
                <a href="cotizador.php" class="k-nav-item <?= $currentPage === 'cotizador' ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-invoice-dollar w-5 text-center"></i> Cotizador
                </a>
                <a href="boveda.php" class="k-nav-item <?= $currentPage === 'boveda' ? 'active' : '' ?>">
                    <i class="fa-solid fa-vault w-5 text-center"></i> Bóveda Digital
                </a>

                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-3 pt-4 pb-1">Análisis & Control</div>
                <a href="costos.php" class="k-nav-item <?= $currentPage === 'costos' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-pie w-5 text-center text-rose-500"></i> Estructura de Costos
                </a>
                <a href="tiempo.php" class="k-nav-item <?= $currentPage === 'tiempo' ? 'active' : '' ?>">
                    <i class="fa-solid fa-stopwatch w-5 text-center text-blue-500"></i> Control de Tiempo
                </a>
                <a href="despacho.php" class="k-nav-item <?= $currentPage === 'despacho' ? 'active' : '' ?>">
                    <i class="fa-solid fa-briefcase w-5 text-center text-cyan-500"></i> Gestión de Despacho
                    <span class="text-[9px] font-black uppercase text-cyan-600 bg-cyan-50 px-1.5 py-0.5 rounded-full border border-cyan-200">PRO</span>
                </a>
                <a href="reportes.php" class="k-nav-item <?= $currentPage === 'reportes' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-column w-5 text-center text-indigo-500"></i> Reportes Ejecutivos
                </a>

                <?php if ($esAdminPlataforma): ?>
                    <div class="text-[10px] font-bold text-amber-600 uppercase tracking-widest px-3 pt-4 pb-1">Administración</div>
                    <a href="admin_suscripciones.php" class="k-nav-item <?= $currentPage === 'admin_suscripciones' ? 'active' : '' ?> text-amber-700 bg-amber-50/60 font-semibold">
                        <i class="fa-solid fa-crown w-5 text-center text-amber-500"></i> Panel Admin
                    </a>
                <?php endif; ?>
            </nav>

            <div class="p-3 border-t border-k-border">
                <a href="configuracion.php" class="k-nav-item <?= $currentPage === 'configuracion' ? 'active' : '' ?>"><i class="fa-solid fa-gear w-5 text-center"></i> Configuración</a>
                <a href="logout.php" class="k-nav-item text-k-danger hover:bg-rose-50"><i class="fa-solid fa-right-from-bracket w-5 text-center"></i> Cerrar Sesión</a>
            </div>
        </aside>

        <!-- WRAPPER CENTRAL -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            
            <!-- TOPBAR -->
            <header class="h-[68px] bg-white border-b border-k-border px-6 flex items-center justify-between shrink-0">
                <div class="text-xs text-k-muted font-medium hidden md:block">
                    Espacio de Trabajo Activo
                </div>
                
                <div class="flex items-center gap-3 ml-auto">
                    <!-- Selector de Empresa -->
                    <a href="seleccionar_empresa.php" class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-blue-50 border border-blue-200 hover:bg-blue-100 transition-colors" title="Cambiar Empresa">
                        <i class="fa-solid fa-building text-blue-600 text-xs"></i>
                        <span class="text-xs font-bold text-blue-900 uppercase max-w-[130px] truncate"><?= htmlspecialchars($empresaNombre) ?></span>
                        <i class="fa-solid fa-chevron-down text-[9px] text-blue-500"></i>
                    </a>

                    <!-- Botón Rápido Multiacceso -->
                    <a href="usuarios_lista.php" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition-colors" title="Gestionar Colaboradores de esta Empresa">
                        <i class="fa-solid fa-users-gear text-xs"></i>
                    </a>

                    <!-- Tasa BCV -->
                    <button type="button" onclick="document.getElementById('modalTasaBcv').classList.remove('hidden')" class="flex items-center gap-2 bg-k-bg border border-k-border rounded-xl px-3 py-1.5 text-xs font-bold text-k-navy hover:border-k-primary transition-all">
                        <span class="w-2 h-2 rounded-full bg-k-primary animate-pulse"></span>
                        Bs. <?= number_format($tasaBcvActual, 2, ',', '.') ?>
                    </button>

                    <div class="w-px h-6 bg-k-border"></div>

                    <!-- Perfil -->
                    <div class="flex items-center gap-2">
                        <div class="text-right hidden sm:block">
                            <span class="text-xs font-bold text-k-navy block leading-tight"><?= htmlspecialchars($currentUserName) ?></span>
                            <span class="text-[10px] text-emerald-600 font-semibold">Online</span>
                        </div>
                        <img src="<?= htmlspecialchars($currentUserAvatar) ?>" class="w-8 h-8 rounded-xl border border-k-border" alt="Avatar">
                    </div>
                </div>
            </header>

            <!-- MODAL TASA BCV -->
            <div id="modalTasaBcv" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4 flex">
                <div class="bg-white rounded-2xl p-6 w-full max-w-sm shadow-2xl">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-k-navy text-sm">Actualizar Tasa BCV</h3>
                        <button onclick="document.getElementById('modalTasaBcv').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="actualizar_tasa_bcv" value="1">
                        <input type="number" step="0.01" name="tasa_bcv_input" value="<?= htmlspecialchars($tasaBcvActual) ?>" required class="k-input font-bold text-lg mb-4">
                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="document.getElementById('modalTasaBcv').classList.add('hidden')" class="k-btn k-btn-secondary">Cancelar</button>
                            <button type="submit" class="k-btn k-btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- CONTENIDO PRINCIPAL SCROLLABLE -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">