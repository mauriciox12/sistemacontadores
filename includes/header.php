<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$db = getDB();

// Actualización rápida de Tasa BCV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_tasa_bcv'])) {
    $nuevaTasa = floatval(str_replace(',', '.', $_POST['tasa_bcv_input']));
    if ($nuevaTasa > 0) {
        $_SESSION['tasa_bcv'] = $nuevaTasa;
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

$tasaBcvActual = $_SESSION['tasa_bcv'] ?? 65.50;
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentUser = obtenerUsuarioActual($db);
$despacho = obtenerConfiguracionDespacho($db);
$subscripcion = verificarSuscripcionActiva($currentUser, $db);

// Contadores rápidos para los badges del menú lateral
$badgeClientes = 0;
$badgeCotizaciones = 0;
$badgeIngresosMes = 0;
$badgeDocsMes = 0;

try {
    $badgeClientes = (int)$db->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
    $badgeCotizaciones = (int)$db->query("SELECT COUNT(*) FROM cotizaciones WHERE estado IN ('Borrador', 'Enviado')")->fetchColumn();
    $badgeDocsMes = (int)$db->query("SELECT COUNT(*) FROM documentos_escaneados WHERE mes_fiscal = '" . date('Y-m') . "'")->fetchColumn();
    $badgeIngresosMes = (float)$db->query("SELECT COALESCE(SUM(monto_usd), 0) FROM ingresos WHERE estado = 'cobrado' AND fecha LIKE '" . date('Y-m') . "%'")->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-[#F8FAFC]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="theme-color" content="#0F172A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Kontify">
    
    <title><?= $pageTitle ?? 'Kontify APP — El centro operativo inteligente del contador moderno' ?></title>
    
    <!-- Favicon SVG Oficial -->
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/favicon.svg">

    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,500&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 Pro/Free Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Chart.js 4.4 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        kprimary: '#00B894',
                        'kprimary-dark': '#008F72',
                        'kprimary-light': '#E6F8F4',
                        knavy: '#0F172A',
                        kmuted: '#64748B',
                        kborder: '#E2E8F0',
                        kbg: '#F8FAFC',
                        ksurface: '#FFFFFF',
                        kpremium: '#2563EB',
                        'kpremium-dark': '#1D4ED8',
                        'kpremium-light': '#EFF6FF',
                        kamber: '#F59E0B',
                        krose: '#EF4444'
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace']
                    },
                    borderRadius: {
                        'xl': '12px',
                        '2xl': '16px',
                        '3xl': '24px'
                    },
                    boxShadow: {
                        'subtle': '0 1px 3px 0 rgba(15, 23, 42, 0.03), 0 1px 2px -1px rgba(15, 23, 42, 0.03)',
                        'card': '0 2px 10px -2px rgba(15, 23, 42, 0.04), 0 1px 3px -1px rgba(15, 23, 42, 0.02)',
                        'card-hover': '0 12px 30px -4px rgba(15, 23, 42, 0.08), 0 4px 10px -2px rgba(15, 23, 42, 0.03)',
                        'glow-primary': '0 8px 24px -4px rgba(0, 184, 148, 0.35)',
                        'glow-premium': '0 8px 24px -4px rgba(37, 99, 235, 0.35)',
                        'fab': '0 10px 25px -3px rgba(0, 184, 148, 0.5), 0 4px 10px -2px rgba(0, 184, 148, 0.3)'
                    }
                }
            }
        }
    </script>

    <style>
        /* ====================================================================
           KONTIFY APP — Design System Tokens & Clean SaaS Styling ($99-$299/mo)
           ==================================================================== */
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: #F8FAFC;
            color: #0F172A;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        ::selection {
            background: rgba(0, 184, 148, 0.18);
            color: #0F172A;
        }

        /* Minimalist Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #94A3B8; }

        /* Glassmorphism */
        .k-glass {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
        }

        /* Clean SaaS Cards */
        .k-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.03), 0 1px 2px -1px rgba(15, 23, 42, 0.03);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .k-card-interactive {
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .k-card-interactive:hover {
            border-color: #CBD5E1;
            box-shadow: 0 12px 30px -4px rgba(15, 23, 42, 0.08);
            transform: translateY(-1px);
        }

        /* Botones Tailwind Premium */
        .k-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 600;
            font-size: 13.5px;
            padding: 9px 18px;
            border-radius: 12px;
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
            border: none;
            outline: none;
            text-decoration: none;
            white-space: nowrap;
        }
        .k-btn:active { transform: scale(0.97); }
        .k-btn-primary {
            background: #00B894;
            color: #FFFFFF;
            box-shadow: 0 4px 14px -2px rgba(0, 184, 148, 0.35);
        }
        .k-btn-primary:hover {
            background: #008F72;
            box-shadow: 0 6px 20px -2px rgba(0, 184, 148, 0.45);
        }
        .k-btn-premium {
            background: #2563EB;
            color: #FFFFFF;
            box-shadow: 0 4px 14px -2px rgba(37, 99, 235, 0.3);
        }
        .k-btn-premium:hover {
            background: #1D4ED8;
            box-shadow: 0 6px 20px -2px rgba(37, 99, 235, 0.4);
        }
        .k-btn-secondary {
            background: #FFFFFF;
            color: #0F172A;
            border: 1px solid #E2E8F0;
            box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.03);
        }
        .k-btn-secondary:hover {
            background: #F8FAFC;
            border-color: #CBD5E1;
        }
        .k-btn-ghost {
            background: transparent;
            color: #64748B;
        }
        .k-btn-ghost:hover {
            background: #F1F5F9;
            color: #0F172A;
        }

        /* Inputs */
        .k-input {
            width: 100%;
            padding: 10px 14px;
            font-size: 13.5px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            color: #0F172A;
            outline: none;
            transition: all 0.15s ease;
        }
        .k-input:focus {
            border-color: #00B894;
            box-shadow: 0 0 0 3px rgba(0, 184, 148, 0.15);
        }
        .k-input::placeholder { color: #94A3B8; }
        .k-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748B;
            margin-bottom: 6px;
        }

        /* Badges */
        .k-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 9px;
            border-radius: 9999px;
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: -0.01em;
            white-space: nowrap;
        }
        .k-badge-success { background: #E6F8F4; color: #00876C; }
        .k-badge-premium { background: #EFF6FF; color: #1D4ED8; }
        .k-badge-warning { background: #FEF3C7; color: #B45309; }
        .k-badge-danger { background: #FFE4E6; color: #BE123C; }
        .k-badge-neutral { background: #F1F5F9; color: #475569; }

        /* Sidebar Nav Items */
        .k-nav-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9.5px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            color: #64748B;
            transition: all 0.15s ease;
            text-decoration: none;
        }
        .k-nav-item:hover {
            color: #0F172A;
            background: #F1F5F9;
        }
        .k-nav-item.active {
            color: #0F172A;
            background: #F1F5F9;
            font-weight: 600;
        }
        .k-nav-item.active .nav-icon {
            color: #00B894;
        }
        .k-nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            width: 3.5px;
            height: 22px;
            background: #00B894;
            border-radius: 0 4px 4px 0;
        }

        /* Tablas */
        .k-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .k-table th {
            padding: 12px 18px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748B;
            border-bottom: 1px solid #E2E8F0;
            background: #FAFCFE;
            text-align: left;
        }
        .k-table td {
            padding: 14px 18px;
            font-size: 13.5px;
            color: #0F172A;
            border-bottom: 1px solid #F1F5F9;
        }
        .k-table tr:hover td { background: #F8FAFC; }
        .k-table tr:last-child td { border-bottom: none; }
    </style>
</head>
<body class="h-full antialiased overflow-hidden flex flex-col lg:flex-row bg-[#F8FAFC]">

    <!-- ==================================================================== -->
    <!-- DESKTOP SIDEBAR RAIL (264px) — Clean SaaS Architecture               -->
    <!-- ==================================================================== -->
    <aside class="hidden lg:flex lg:flex-col w-[264px] bg-white border-r border-slate-200/80 shrink-0 select-none z-30 h-full">
        
        <!-- Workspace Header & Brand Logo Oficial -->
        <div class="p-5 pb-4 border-b border-slate-100">
            <a href="<?= BASE_URL ?>/index.php" class="flex items-center gap-3 group">
                <!-- Isotipo K Oficial Vectorial Ribbon -->
                <div class="w-10 h-10 rounded-xl bg-slate-900 flex items-center justify-center p-1.5 shadow-md shadow-emerald-500/15 group-hover:scale-105 transition-transform shrink-0">
                    <svg viewBox="0 0 120 120" fill="none" class="w-full h-full">
                        <defs>
                            <linearGradient id="sbKg1" x1="20" y1="15" x2="48" y2="105" gradientUnits="userSpaceOnUse">
                                <stop offset="0%" stop-color="#00D2A4"/>
                                <stop offset="60%" stop-color="#00B894"/>
                                <stop offset="100%" stop-color="#008F72"/>
                            </linearGradient>
                            <linearGradient id="sbKg2" x1="40" y1="20" x2="105" y2="60" gradientUnits="userSpaceOnUse">
                                <stop offset="0%" stop-color="#00E5B4"/>
                                <stop offset="100%" stop-color="#00B894"/>
                            </linearGradient>
                            <linearGradient id="sbKg3" x1="42" y1="60" x2="105" y2="105" gradientUnits="userSpaceOnUse">
                                <stop offset="0%" stop-color="#00B894"/>
                                <stop offset="100%" stop-color="#007A60"/>
                            </linearGradient>
                        </defs>
                        <path d="M22 22C22 17.5817 25.5817 14 30 14H38C42.4183 14 46 17.5817 46 22V98C46 102.418 42.4183 106 38 106H30C25.5817 106 22 102.418 22 98V22Z" fill="url(#sbKg1)"/>
                        <path d="M42 58L78.5 21C81.8 17.7 87.2 17.7 90.5 21L95.5 26C98.8 29.3 98.8 34.7 95.5 38L66 68L42 58Z" fill="url(#sbKg2)"/>
                        <path d="M48 62L62 48L95.5 82C98.8 85.3 98.8 90.7 95.5 94L90.5 99C87.2 102.3 81.8 102.3 78.5 99L48 62Z" fill="url(#sbKg3)"/>
                        <path d="M46 54L62 48L66 68L46 72V54Z" fill="#006C54" fill-opacity="0.25"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-1">
                        <span class="font-extrabold text-[15px] tracking-tight text-[#0F172A]">Kontify</span>
                        <span class="text-[9px] font-extrabold tracking-wider px-1.5 py-0.2 rounded bg-emerald-50 text-[#00B894] border border-emerald-200">APP</span>
                    </div>
                    <span class="text-[9px] font-bold text-[#64748B] tracking-widest uppercase block -mt-0.5">Tu despacho, en orden</span>
                </div>
            </a>

            <!-- Workspace Switcher Card -->
            <div class="mt-4 p-2.5 rounded-xl bg-slate-50 border border-slate-200/70 flex items-center justify-between">
                <div class="flex items-center gap-2 truncate">
                    <div class="w-6 h-6 rounded-lg bg-emerald-500/10 text-[#00B894] flex items-center justify-center text-xs font-bold shrink-0">
                        <i class="fa-solid fa-building-columns text-[10px]"></i>
                    </div>
                    <div class="truncate">
                        <div class="text-xs font-bold text-[#0F172A] truncate leading-tight"><?= htmlspecialchars($despacho['nombre_despacho'] ?? 'Mi Despacho') ?></div>
                        <div class="text-[10px] text-[#64748B] font-medium">Plan Pro &bull; $199/mo</div>
                    </div>
                </div>
                <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0" title="Sistema Activo"></span>
            </div>
        </div>

        <!-- Navigation Rail -->
        <nav class="flex-1 px-3 py-4 space-y-6 overflow-y-auto">
            
            <!-- SECCIÓN: OPERATIVO -->
            <div>
                <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    Operativo Principal
                </div>
                <div class="space-y-1">
                    <a href="<?= BASE_URL ?>/index.php" class="k-nav-item relative <?= $currentPage === 'index' ? 'active' : '' ?>">
                        <div class="flex items-center gap-2.5">
                            <i class="fa-solid fa-chart-pie w-4 text-center nav-icon <?= $currentPage === 'index' ? 'text-[#00B894]' : 'text-slate-400' ?>"></i>
                            <span>Dashboard</span>
                        </div>
                        <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-full">En Vivo</span>
                    </a>

                    <a href="<?= BASE_URL ?>/crm.php" class="k-nav-item relative <?= $currentPage === 'crm' || $currentPage === 'cliente_detalle' ? 'active' : '' ?>">
                        <div class="flex items-center gap-2.5">
                            <i class="fa-solid fa-users w-4 text-center nav-icon <?= ($currentPage === 'crm' || $currentPage === 'cliente_detalle') ? 'text-[#00B894]' : 'text-slate-400' ?>"></i>
                            <span>Clientes (CRM)</span>
                        </div>
                        <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded-md"><?= $badgeClientes ?></span>
                    </a>

                    <a href="<?= BASE_URL ?>/ingresos.php" class="k-nav-item relative <?= $currentPage === 'ingresos' ? 'active' : '' ?>">
                        <div class="flex items-center gap-2.5">
                            <i class="fa-solid fa-receipt w-4 text-center nav-icon <?= $currentPage === 'ingresos' ? 'text-[#00B894]' : 'text-slate-400' ?>"></i>
                            <span>Finanzas</span>
                        </div>
                        <span class="text-[10px] font-mono font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded-md">$<?= number_format($badgeIngresosMes, 0) ?></span>
                    </a>
                </div>
            </div>

            <!-- SECCIÓN: PRODUCTIVIDAD -->
            <div>
                <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    Productividad & Propuestas
                </div>
                <div class="space-y-1">
                    <a href="<?= BASE_URL ?>/cotizador.php" class="k-nav-item relative <?= $currentPage === 'cotizador' ? 'active' : '' ?>">
                        <div class="flex items-center gap-2.5">
                            <i class="fa-solid fa-file-invoice-dollar w-4 text-center nav-icon <?= $currentPage === 'cotizador' ? 'text-[#00B894]' : 'text-slate-400' ?>"></i>
                            <span>Cotizador Inteligente</span>
                        </div>
                        <?php if ($badgeCotizaciones > 0): ?>
                            <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded-md"><?= $badgeCotizaciones ?> act.</span>
                        <?php endif; ?>
                    </a>

                    <a href="<?= BASE_URL ?>/boveda.php" class="k-nav-item relative <?= $currentPage === 'boveda' ? 'active' : '' ?>">
                        <div class="flex items-center gap-2.5">
                            <i class="fa-solid fa-vault w-4 text-center nav-icon <?= $currentPage === 'boveda' ? 'text-[#00B894]' : 'text-slate-400' ?>"></i>
                            <span>Bóveda Documental</span>
                        </div>
                        <span class="text-[9px] font-bold uppercase text-purple-600 bg-purple-50 px-1.5 py-0.5 rounded">IA OCR</span>
                    </a>

                    <a href="<?= BASE_URL ?>/reportes.php" class="k-nav-item relative <?= $currentPage === 'reportes' ? 'active' : '' ?>">
                        <div class="flex items-center gap-2.5">
                            <i class="fa-solid fa-chart-line w-4 text-center nav-icon <?= $currentPage === 'reportes' ? 'text-[#00B894]' : 'text-slate-400' ?>"></i>
                            <span>Reportes & BI</span>
                        </div>
                        <span class="text-[9px] font-bold uppercase text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">Rentab.</span>
                    </a>

                    <a href="<?= BASE_URL ?>/despacho.php?tab=cronometro" class="k-nav-item relative <?= ($currentPage === 'despacho' && ($_GET['tab'] ?? '') === 'cronometro') || $currentPage === 'componente_cronometro_despacho' ? 'active' : '' ?>">
                        <div class="flex items-center gap-2.5">
                            <i class="fa-solid fa-stopwatch w-4 text-center nav-icon text-cyan-500 animate-pulse"></i>
                            <span class="font-bold text-slate-800">⏱️ Cronómetro de Horas</span>
                        </div>
                        <span class="text-[9px] font-black uppercase text-cyan-600 bg-cyan-50 border border-cyan-200 px-1.5 py-0.5 rounded-full">EN VIVO</span>
                    </a>

                    <a href="<?= BASE_URL ?>/despacho.php" class="k-nav-item relative <?= $currentPage === 'despacho' && ($_GET['tab'] ?? '') !== 'cronometro' ? 'active' : '' ?>">
                        <div class="flex items-center gap-2.5">
                            <i class="fa-solid fa-briefcase w-4 text-center nav-icon text-cyan-600"></i>
                            <span>Gestión Despacho</span>
                        </div>
                        <span class="text-[9px] font-extrabold uppercase text-cyan-700 bg-cyan-50 border border-cyan-200 px-1.5 py-0.5 rounded-full">Suite PRO</span>
                    </a>
                </div>
            </div>

            <!-- TASA BCV LIVE TICKER CARD -->
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-[10px] font-bold text-[#64748B] uppercase tracking-wider flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Tasa Oficial BCV
                    </span>
                    <button onclick="document.getElementById('modalTasaBcv').classList.remove('hidden')" class="text-[10px] text-blue-600 font-bold hover:underline">
                        Editar
                    </button>
                </div>
                <div class="text-base font-extrabold font-mono text-[#0F172A] leading-tight">
                    Bs. <?= number_format($tasaBcvActual, 2, ',', '.') ?> <span class="text-[10px] font-normal text-slate-400">/ USD</span>
                </div>
            </div>

            <!-- Brand Design System Showcase Link -->
            <div class="px-1">
                <a href="<?= BASE_URL ?>/brand.php" class="k-nav-item text-xs font-semibold text-slate-500 hover:text-[#0F172A]">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-palette text-slate-400 text-xs"></i>
                        <span>Brandbook & UI Kit</span>
                    </div>
                    <span class="text-[9px] font-bold uppercase px-1 py-0.2 bg-slate-200/80 rounded text-slate-600">v2.0</span>
                </a>
            </div>

        </nav>

        <!-- Sidebar Footer & User Profile -->
        <div class="p-3 border-t border-slate-100 bg-white">
            <div class="flex items-center justify-between p-2 rounded-xl hover:bg-slate-50 transition-colors">
                <div class="flex items-center gap-2.5 truncate">
                    <div class="relative shrink-0">
                        <img src="<?= htmlspecialchars($currentUser['avatar']) ?>" class="w-8 h-8 rounded-lg object-cover border border-slate-200" alt="Avatar">
                        <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-emerald-500 border-2 border-white rounded-full"></span>
                    </div>
                    <div class="truncate">
                        <div class="text-xs font-bold text-[#0F172A] truncate leading-tight"><?= htmlspecialchars($currentUser['name']) ?></div>
                        <div class="text-[10px] text-[#64748B] truncate"><?= htmlspecialchars($currentUser['rol']) ?></div>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/logout.php" title="Cerrar Sesión" class="w-7 h-7 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                </a>
            </div>
        </div>

    </aside>

    <!-- ==================================================================== -->
    <!-- MOBILE TOP BAR (< 1024px) — Clean SaaS Mobile Header                 -->
    <!-- ==================================================================== -->
    <header class="lg:hidden h-16 bg-white/95 border-b border-slate-200/80 px-4 flex items-center justify-between shrink-0 z-30 backdrop-blur-md sticky top-0">
        <a href="<?= BASE_URL ?>/index.php" class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-slate-900 flex items-center justify-center p-1 shadow-sm">
                <svg viewBox="0 0 120 120" fill="none" class="w-full h-full">
                    <defs>
                        <linearGradient id="mobKg1" x1="20" y1="15" x2="48" y2="105" gradientUnits="userSpaceOnUse">
                            <stop offset="0%" stop-color="#00D2A4"/><stop offset="100%" stop-color="#008F72"/>
                        </linearGradient>
                    </defs>
                    <path d="M22 22C22 17.5817 25.5817 14 30 14H38C42.4183 14 46 17.5817 46 22V98C46 102.418 42.4183 106 38 106H30C25.5817 106 22 102.418 22 98V22Z" fill="url(#mobKg1)"/>
                    <path d="M42 58L78.5 21C81.8 17.7 87.2 17.7 90.5 21L95.5 26C98.8 29.3 98.8 34.7 95.5 38L66 68L42 58Z" fill="#00B894"/>
                    <path d="M48 62L62 48L95.5 82C98.8 85.3 98.8 90.7 95.5 94L90.5 99C87.2 102.3 81.8 102.3 78.5 99L48 62Z" fill="#008F72"/>
                </svg>
            </div>
            <div>
                <span class="font-extrabold text-base tracking-tight text-[#0F172A]">Kontify</span>
                <span class="text-[8px] font-bold text-slate-400 block -mt-1 tracking-wider uppercase">OS Móvil</span>
            </div>
        </a>

        <div class="flex items-center gap-2.5">
            <button onclick="abrirCommandPalette()" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center text-xs">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <div class="relative">
                <button class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center text-xs relative">
                    <i class="fa-regular fa-bell"></i>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-[#00B894]"></span>
                </button>
            </div>
            <img src="<?= htmlspecialchars($currentUser['avatar']) ?>" class="w-8 h-8 rounded-lg object-cover border border-slate-200" alt="Avatar">
        </div>
    </header>

    <!-- ==================================================================== -->
    <!-- DESKTOP TOP BAR (≥ 1024px)                                           -->
    <!-- ==================================================================== -->
    <div class="flex-1 flex flex-col h-full overflow-hidden">
        
        <header class="hidden lg:flex h-16 bg-white/90 border-b border-slate-200/80 px-8 items-center justify-between shrink-0 z-20 backdrop-blur-md">
            
            <!-- Global Search & Command Palette Trigger -->
            <div class="flex items-center gap-4 flex-1 max-w-xl">
                <button type="button" onclick="abrirCommandPalette()" class="w-full max-w-md flex items-center justify-between px-3.5 py-2 rounded-xl bg-slate-100/80 hover:bg-slate-100 border border-slate-200/60 text-xs text-slate-400 transition-all text-left group">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-magnifying-glass text-slate-400 group-hover:text-[#0F172A] transition-colors"></i>
                        <span>Buscar clientes, RIF, cotizaciones o facturas...</span>
                    </div>
                    <kbd class="hidden sm:inline-block px-1.5 py-0.5 text-[10px] font-mono font-bold bg-white text-slate-500 rounded border border-slate-200 shadow-xs">Ctrl K</kbd>
                </button>
            </div>

            <!-- Header Action Controls -->
            <div class="flex items-center gap-3">
                
                <!-- Tag Estado del Sistema -->
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-600 font-medium">
                    <span class="w-2 h-2 rounded-full bg-[#00B894] animate-pulse"></span>
                    <span>Estado: <strong class="text-[#0F172A]">En línea &bull; Cierre Activo</strong></span>
                </div>

                <!-- Botón Acción Rápida "+ Nuevo" -->
                <div class="relative" id="dropdownNuevoWrap">
                    <button type="button" onclick="toggleDropdownNuevo()" class="k-btn k-btn-primary shadow-sm text-xs py-2 px-3.5">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Nuevo</span>
                        <i class="fa-solid fa-chevron-down text-[10px] ml-1"></i>
                    </button>

                    <!-- Dropdown Content -->
                    <div id="dropdownNuevoMenu" class="hidden absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-200/80 py-1.5 z-50">
                        <a href="<?= BASE_URL ?>/crm.php?accion=nuevo" class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-slate-700 hover:bg-slate-50 hover:text-emerald-600 font-medium">
                            <i class="fa-solid fa-user-plus w-4 text-[#00B894]"></i> Registrar Nuevo Cliente
                        </a>
                        <a href="<?= BASE_URL ?>/ingresos.php?accion=registrar" class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-slate-700 hover:bg-slate-50 hover:text-blue-600 font-medium">
                            <i class="fa-solid fa-sack-dollar w-4 text-blue-600"></i> Registrar Ingreso / Pago
                        </a>
                        <a href="<?= BASE_URL ?>/cotizador.php" class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-slate-700 hover:bg-slate-50 hover:text-amber-600 font-medium">
                            <i class="fa-solid fa-file-signature w-4 text-amber-500"></i> Crear Cotización Pro
                        </a>
                        <a href="<?= BASE_URL ?>/boveda.php" class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-slate-700 hover:bg-slate-50 hover:text-purple-600 font-medium">
                            <i class="fa-solid fa-cloud-arrow-up w-4 text-purple-500"></i> Subir Documento a Bóveda
                        </a>
                    </div>
                </div>

                <!-- Notificaciones -->
                <button class="w-9 h-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center text-slate-500 hover:text-[#0F172A] transition-colors relative">
                    <i class="fa-regular fa-bell text-sm"></i>
                    <span class="absolute top-2 right-2 w-2 h-2 rounded-full bg-[#00B894]"></span>
                </button>

                <!-- Ayuda & Atajos -->
                <button onclick="abrirCommandPalette()" title="Atajos de teclado (Ctrl + K)" class="w-9 h-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center text-slate-500 hover:text-[#0F172A] transition-colors">
                    <i class="fa-solid fa-keyboard text-xs"></i>
                </button>

            </div>

        </header>

        <!-- Main Content Area Scrollable -->
        <main class="flex-1 overflow-y-auto px-4 py-6 sm:px-8 sm:py-8 lg:px-10 lg:py-9 pb-24 lg:pb-9">
