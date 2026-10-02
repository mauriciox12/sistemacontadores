<?php
require_once 'config.php';

// Seguridad: Si no hay sesión, al login directo.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontify App | Ficha de Cliente</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .font-bebas { font-family: 'Bebas Neue', sans-serif; }
        .font-montserrat { font-family: 'Montserrat', sans-serif; }
        .glass-panel { @apply bg-slate-800/40 backdrop-blur-md border border-slate-700 shadow-xl; }
        .premium-input { @apply w-full bg-slate-900/50 border border-slate-700 text-slate-200 rounded-lg p-3 font-montserrat text-sm focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all outline-none; }
        .premium-label { @apply block text-xs font-montserrat text-slate-400 uppercase tracking-widest mb-2 font-semibold; }
        .checkbox-card { @apply flex items-start space-x-3 p-3 bg-slate-900/50 border border-slate-700 rounded-lg cursor-pointer hover:border-cyan-500 transition-colors; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #06b6d4; }
    </style>
</head>
<body class="bg-slate-900 min-h-screen text-slate-200 flex font-montserrat overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-64 glass-panel border-r border-slate-700 hidden md:flex flex-col z-10 relative justify-between">
        <div>
            <div class="p-6 border-b border-slate-700/50">
                <h1 class="text-3xl font-bebas text-white tracking-widest">KONTIFY<span class="text-cyan-500">APP</span></h1>
                <p class="text-[10px] text-slate-400 uppercase tracking-widest mt-1">Directorio Fiscal</p>
            </div>
            <nav class="p-4 space-y-2">
                <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    <span class="text-sm font-medium">Dashboard</span>
                </a>
                <a href="#" class="flex items-center space-x-3 p-3 rounded-lg bg-cyan-500/10 text-cyan-500 border border-cyan-500/20 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <span class="text-sm font-medium">Directorio Clientes</span>
                </a>
            </nav>
        </div>
        
        <!-- Perfil del Usuario Logueado (Google) -->
        <div class="p-6 border-t border-slate-700/50 flex items-center space-x-3">
            <img src="<?php echo $_SESSION['user_avatar'] ?? 'https://ui-avatars.com/api/?name=User&background=0D8ABC&color=fff'; ?>" alt="Avatar" class="w-10 h-10 rounded-full border border-slate-600">
            <div class="overflow-hidden">
                <p class="text-xs font-bold text-white truncate"><?php echo $_SESSION['user_nombre'] ?? 'Contador'; ?></p>
                <a href="logout.php" class="text-[10px] text-slate-400 hover:text-cyan-500 transition-colors uppercase tracking-widest">Cerrar Sesión</a>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT (El mismo formulario que ya tenías, intacto) -->
    <main class="flex-1 h-screen overflow-y-auto p-6 lg:p-10">
        <div class="max-w-6xl mx-auto">
            
            <header class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-end border-b border-slate-800 pb-6">
                <div>
                    <h2 class="text-cyan-500 font-montserrat text-sm tracking-widest uppercase mb-1">Onboarding / Alta</h2>
                    <h1 class="text-5xl font-bebas text-white tracking-wide">Ficha Técnica de Cliente</h1>
                </div>
                <div class="mt-4 md:mt-0 flex items-center space-x-4">
                    <span class="px-4 py-2 glass-panel rounded-full text-xs font-montserrat text-slate-300 flex items-center">
                        <span class="w-2 h-2 rounded-full bg-cyan-500 mr-2 shadow-[0_0_8px_#06b6d4]"></span> API Online
                    </span>
                </div>
            </header>

            <!-- Notificación de Éxito -->
            <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                <div class="bg-cyan-500/10 border border-cyan-500/50 text-cyan-400 px-6 py-4 rounded-xl mb-8 flex items-center shadow-[0_0_15px_rgba(6,182,212,0.2)]">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="font-medium">¡Ficha de cliente registrada y asignada a tu despacho exitosamente!</span>
                </div>
            <?php endif; ?>

            <form action="guardar_ficha.php" method="POST" class="grid grid-cols-1 xl:grid-cols-3 gap-8 pb-20">
                <!-- COLUMNA IZQUIERDA -->
                <div class="xl:col-span-2 space-y-8">
                    <div class="glass-panel rounded-2xl p-8 relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1 h-full bg-cyan-500"></div>
                        <h3 class="text-2xl font-bebas text-white mb-6 tracking-wide">Clasificación Mercantil & Laboral</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2 mb-2">
                                <label class="premium-label text-white">Nombre o Razón Social</label>
                                <input type="text" name="nombre_razon_social" required class="premium-input text-lg font-semibold text-white" placeholder="Ej. Corporación Andina C.A.">
                            </div>

                            <div class="md:col-span-2">
                                <label class="premium-label">Actividad Mercantil o Civil</label>
                                <select name="actividad_mercantil" class="premium-input appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[position:97%_center] bg-[length:12px]">
                                    <option value="">Seleccione una actividad...</option>
                                    <option value="Manufactura Nacional/Exportador">Manufactura con alcance nacional y/o Exportador</option>
                                    <option value="Comercial Distribuidor/Importador">Comercial (Distribuidor productos nacionales o Importador)</option>
                                    <option value="Servicio">Servicio</option>
                                    <option value="Actividad Civil">Actividad Civil</option>
                                    <option value="Fundacion">Fundación / OSFL / Asociación Civil</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="premium-label">Condición Laboral</label>
                                <select name="condicion_laboral" class="premium-input appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[position:97%_center] bg-[length:12px]">
                                    <option value="No asalariado">Trabajador No asalariado</option>
                                    <option value="Asalariado">Asalariado</option>
                                    <option value="Combinacion">Combinación (asalariado / no asalariado)</option>
                                </select>
                            </div>

                            <div>
                                <label class="premium-label">Retención / Percepción</label>
                                <select name="tipo_agente" class="premium-input appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[position:97%_center] bg-[length:12px]">
                                    <option value="Ninguno">No aplica</option>
                                    <option value="Agente de retencion">Agentes de retención</option>
                                    <option value="Agente de percepcion">Agentes de percepción</option>
                                    <option value="Ambos">Ambos</option>
                                </select>
                            </div>

                            <div>
                                <label class="premium-label">Estatus ante la AT</label>
                                <select name="estatus_cliente" class="premium-input appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[position:97%_center] bg-[length:12px]">
                                    <option value="Activo">Activo (Operativo)</option>
                                    <option value="En cese">En cese</option>
                                    <option value="Inactivo">Inactivo</option>
                                    <option value="Etapa preoperativa">En etapa preoperativa</option>
                                    <option value="En liquidacion">En liquidación</option>
                                    <option value="En litigio">En litigio con la AT</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="glass-panel rounded-2xl p-8 relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1 h-full bg-cyan-500"></div>
                        <h3 class="text-2xl font-bebas text-white mb-6 tracking-wide">Tipos de Servicio Prestados</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <label class="checkbox-card"><input type="checkbox" name="servicios[]" value="Teneduria de Libros" class="mt-1 accent-cyan-500"><span class="text-xs">Teneduría de Libros</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="servicios[]" value="Nominas" class="mt-1 accent-cyan-500"><span class="text-xs">Nóminas</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="servicios[]" value="Control Administrativo" class="mt-1 accent-cyan-500"><span class="text-xs">Control Administrativo</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="servicios[]" value="Logistica" class="mt-1 accent-cyan-500"><span class="text-xs">Logística</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="servicios[]" value="Analisis de datos" class="mt-1 accent-cyan-500"><span class="text-xs">Análisis de datos</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="servicios[]" value="Acompañamiento" class="mt-1 accent-cyan-500"><span class="text-xs">Acompañamiento</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="servicios[]" value="Estudios de casos especiales" class="mt-1 accent-cyan-500"><span class="text-xs">Estudios casos especiales</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="servicios[]" value="Creacion sucursales" class="mt-1 accent-cyan-500"><span class="text-xs">Creación nuevas sucursales</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="servicios[]" value="Auditoria" class="mt-1 accent-cyan-500"><span class="text-xs">Auditoría Interna/Externa</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="servicios[]" value="Funcion comisarios" class="mt-1 accent-cyan-500"><span class="text-xs">Función de comisarios</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="servicios[]" value="Informes especiales" class="mt-1 accent-cyan-500"><span class="text-xs">Informes especiales</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="servicios[]" value="Asistencia cotizacion sistemas" class="mt-1 accent-cyan-500"><span class="text-xs">Asistencia Sist. Administrativos</span></label>
                        </div>
                    </div>
                </div>

                <!-- COLUMNA DERECHA -->
                <div class="space-y-8">
                    <div class="glass-panel rounded-2xl p-6 relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1 h-full bg-cyan-500"></div>
                        <h3 class="text-xl font-bebas text-white mb-4">Dedicación & Perfil</h3>
                        
                        <div class="grid grid-cols-3 gap-2 mb-6">
                            <div>
                                <label class="premium-label text-[10px]">Hrs/Sem</label>
                                <input type="number" name="dedicacion_horas" class="premium-input text-center font-bebas text-lg" placeholder="0">
                            </div>
                            <div>
                                <label class="premium-label text-[10px]">Días/Sem</label>
                                <input type="number" name="dedicacion_dias_sem" class="premium-input text-center font-bebas text-lg" placeholder="0">
                            </div>
                            <div>
                                <label class="premium-label text-[10px]">Días/Mes</label>
                                <input type="number" name="dedicacion_dias_mes" class="premium-input text-center font-bebas text-lg" placeholder="0">
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-center justify-between p-3 bg-slate-900/50 border border-slate-700 rounded-lg cursor-pointer hover:border-cyan-500">
                                <span class="font-montserrat text-xs text-slate-300">💎 Mayor Valor Agregado</span>
                                <input type="checkbox" name="agrega_valor" class="w-4 h-4 accent-cyan-500">
                            </label>
                            <label class="flex items-center justify-between p-3 bg-slate-900/50 border border-slate-700 rounded-lg cursor-pointer hover:border-cyan-500">
                                <span class="font-montserrat text-xs text-slate-300">📍 Cliente Foráneo</span>
                                <input type="checkbox" name="es_foraneo" class="w-4 h-4 accent-cyan-500">
                            </label>
                            <label class="flex items-center justify-between p-3 bg-slate-900/50 border border-slate-700 rounded-lg cursor-pointer hover:border-cyan-500">
                                <span class="font-montserrat text-xs text-slate-300">📱 Afiliado/Seguidor RRSS</span>
                                <input type="checkbox" name="afiliado_rrss" class="w-4 h-4 accent-cyan-500">
                            </label>
                        </div>
                    </div>

                    <!-- Módulo de Cobranzas -->
                    <div class="bg-slate-800/60 backdrop-blur-md border border-yellow-600/50 rounded-2xl p-6 shadow-[0_0_20px_rgba(202,138,4,0.1)] relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1 h-full bg-yellow-500"></div>
                        <h3 class="text-xl font-bebas text-yellow-500 mb-4 tracking-wide">Indicador de Cobranzas</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="premium-label text-yellow-500/80">Antigüedad Facturas</label>
                                <div class="flex items-center space-x-2">
                                    <input type="number" name="antiguedad_dias" class="w-24 bg-slate-900 border border-yellow-600/50 text-white rounded-lg p-2 font-bebas text-xl text-center focus:border-yellow-500 outline-none" placeholder="0">
                                    <span class="text-sm font-montserrat text-slate-400">Días</span>
                                </div>
                            </div>
                            <div>
                                <label class="premium-label text-yellow-500/80">Pérdida Antigüedad (> 30 días)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-slate-400 font-montserrat">$</span>
                                    <input type="number" step="0.01" name="perdida_valor" class="w-full bg-slate-900 border border-yellow-600/50 text-yellow-400 rounded-lg py-2 pl-7 pr-3 font-bebas text-xl focus:border-yellow-500 outline-none" placeholder="0.00">
                                </div>
                                <p class="text-[10px] text-slate-400 font-montserrat mt-2 leading-relaxed">
                                    Menos Valor Agregado a la firma. Referencia: Cotizacion por Servicios de Honorarios.xlsx
                                </p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-cyan-500 hover:bg-cyan-400 text-slate-900 font-bebas text-2xl py-4 rounded-xl transition-all shadow-[0_0_15px_rgba(6,182,212,0.4)] hover:shadow-[0_0_25px_rgba(6,182,212,0.6)] tracking-widest mt-4">
                        GUARDAR FICHA CLIENTE
                    </button>
                </div>
            </form>
        </div>
    </main>

</body>
</html>