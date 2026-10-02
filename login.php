<?php
// BUSTING DE CACHÉ ESTRICTO Y POLÍTICAS DE GOOGLE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Cross-Origin-Opener-Policy: same-origin-allow-popups");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    
    <title>Login | Kontify App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Script Oficial de Google Identity -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0B1117; color: #ffffff; }
        .k-input {
            background-color: #E2E8F0;
            color: #0F172A;
            border: none;
        }
        .k-input:focus { outline: 2px solid #10B981; }
        
        /* Animación suave para el cambio de formularios */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-up { animation: fadeUp 0.3s ease-out forwards; }
    </style>
</head>
<body class="min-h-screen flex">

    <!-- Lado Izquierdo: Branding -->
    <div class="hidden lg:flex w-1/2 flex-col justify-center px-20 border-r border-slate-800 relative">
        <div class="relative z-10 max-w-md mx-auto w-full">
            
            <!-- Logo -->
            <div class="flex items-center gap-3 mb-10">
                <div class="w-8 h-8 rounded bg-[#10B981] flex items-center justify-center text-white shadow-lg shadow-emerald-500/20">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12h4l3-8 4 16 3-8h4"></path>
                    </svg>
                </div>
                <h1 class="text-xl font-bold tracking-tight">KontifyApp</h1>
            </div>
            
            <!-- Títulos -->
            <h2 class="text-[40px] font-bold tracking-tight mb-6 leading-tight">
                Tu despacho<br>
                contable,<br>
                en piloto automático.
            </h2>
            <p class="text-sm text-slate-400 font-normal leading-relaxed mb-16">
                Plataforma financiera y fiscal diseñada para contadores en Venezuela. Gestiona clientes, cobranzas e impuestos desde un solo lugar.
            </p>
            
            <!-- KPIs -->
            <div class="flex gap-10">
                <div>
                    <div class="text-2xl font-bold mb-1">99.9%</div>
                    <div class="text-[10px] text-slate-500 uppercase tracking-widest font-semibold">Uptime</div>
                </div>
                <div>
                    <div class="text-2xl font-bold mb-1">E2E</div>
                    <div class="text-[10px] text-slate-500 uppercase tracking-widest font-semibold">Encriptado</div>
                </div>
                <div>
                    <div class="text-2xl font-bold mb-1">24/7</div>
                    <div class="text-[10px] text-slate-500 uppercase tracking-widest font-semibold">Acceso</div>
                </div>
            </div>
        </div>

        <!-- Footer Izquierdo -->
        <div class="absolute bottom-10 left-0 w-full px-20">
            <div class="max-w-md mx-auto flex items-center gap-2 text-xs text-[#10B981]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Protegido con cifrado de extremo a extremo
            </div>
        </div>
    </div>

    <!-- Lado Derecho: Formularios -->
    <div class="w-full lg:w-1/2 flex flex-col items-center justify-center p-8 relative bg-[#0B1117]">
        
        <div class="w-full max-w-[380px]"> 
            
            <!-- Pestañas Interactivas -->
            <div id="tabsContainer" class="flex border-b border-slate-800 mb-8 relative transition-opacity duration-300">
                <button id="tabLogin" class="w-1/2 text-center pb-3 border-b-2 border-[#10B981] text-sm font-semibold text-white transition-colors z-10">
                    Iniciar sesión
                </button>
                <button id="tabRegistro" class="w-1/2 text-center pb-3 border-b-2 border-transparent text-sm font-medium text-slate-500 hover:text-slate-300 transition-colors z-10">
                    Crear cuenta
                </button>
            </div>

            <!-- FORMULARIO 1: INICIAR SESIÓN -->
            <form id="formLogin" action="#" method="POST" class="space-y-4 animate-fade-up">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Correo electrónico</label>
                    <input type="email" value="mau@nexusfinanzas.com" class="k-input w-full px-4 py-3 rounded-md text-sm" required>
                </div>
                
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Contraseña</label>
                    <div class="relative">
                        <input type="password" value="12345678" class="k-input w-full px-4 py-3 rounded-md text-sm" required>
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="button" id="btnOlvide" class="text-[11px] text-slate-400 hover:text-[#10B981] transition-colors">¿Olvidaste tu contraseña?</button>
                </div>

                <button type="submit" class="w-full bg-[#10B981] hover:bg-[#0da06f] text-white font-semibold py-3 rounded-md text-sm transition-colors mt-2 shadow-lg shadow-emerald-500/20">
                    Iniciar sesión
                </button>
            </form>

            <!-- FORMULARIO 2: CREAR CUENTA (Oculto) -->
            <form id="formRegistro" action="#" method="POST" class="space-y-4 hidden animate-fade-up">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Nombre completo</label>
                    <input type="text" placeholder="Ej. Mauricio Rojas" class="k-input w-full px-4 py-3 rounded-md text-sm" required>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Correo electrónico</label>
                    <input type="email" placeholder="mau@nexusfinanzas.com" class="k-input w-full px-4 py-3 rounded-md text-sm" required>
                </div>
                
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Crear contraseña</label>
                    <div class="relative">
                        <input type="password" placeholder="••••••••" class="k-input w-full px-4 py-3 rounded-md text-sm" required>
                    </div>
                </div>

                <button type="submit" class="w-full bg-[#10B981] hover:bg-[#0da06f] text-white font-semibold py-3 rounded-md text-sm transition-colors mt-4 shadow-lg shadow-emerald-500/20">
                    Crear cuenta gratuita
                </button>
            </form>

            <!-- FORMULARIO 3: RECUPERAR CONTRASEÑA (Oculto) -->
            <form id="formRecuperar" action="#" method="POST" class="space-y-4 hidden animate-fade-up">
                <div class="mb-4">
                    <h3 class="text-lg font-bold text-white mb-1">Recuperar acceso</h3>
                    <p class="text-xs text-slate-400">Ingresa tu correo y te enviaremos un enlace seguro para restablecer tu contraseña.</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Correo electrónico</label>
                    <input type="email" placeholder="mau@nexusfinanzas.com" class="k-input w-full px-4 py-3 rounded-md text-sm" required>
                </div>

                <button type="submit" class="w-full bg-[#10B981] hover:bg-[#0da06f] text-white font-semibold py-3 rounded-md text-sm transition-colors mt-4 shadow-lg shadow-emerald-500/20">
                    Enviar instrucciones
                </button>

                <div class="flex justify-center mt-4">
                    <button type="button" id="btnVolver" class="text-[11px] text-slate-400 hover:text-white transition-colors flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Volver a iniciar sesión
                    </button>
                </div>
            </form>

            <!-- Contenedor Inferior (Separador y Google) -->
            <div id="socialLoginContainer" class="transition-opacity duration-300">
                <!-- Separador -->
                <div class="flex items-center w-full my-6">
                    <div class="flex-grow border-t border-slate-800"></div>
                    <span class="px-3 text-[10px] text-slate-500 uppercase">O CONTINÚA CON</span>
                    <div class="flex-grow border-t border-slate-800"></div>
                </div>

                <!-- MOTOR DE GOOGLE -->
                <div id="g_id_onload"
                     data-client_id="576395490023-25s7dqup3uj8svsjt6qhc9qb21hb2h1c.apps.googleusercontent.com"
                     data-context="signin"
                     data-ux_mode="popup"
                     data-login_uri="https://nexusgestions.online/Kontifyapp/auth_google.php"
                     data-auto_prompt="false">
                </div>

                <!-- Botón UI de Google renderizado automáticamente -->
                <div class="flex justify-center w-full">
                    <div class="g_id_signin"
                         data-type="standard"
                         data-shape="rectangular"
                         data-theme="filled_black"
                         data-text="continue_with"
                         data-size="large"
                         data-logo_alignment="center"
                         data-width="380">
                    </div>
                </div>
            </div>

            <!-- Footer del Formulario -->
            <p class="text-[10px] text-center text-slate-500 mt-6 px-4">
                Al continuar, aceptas los Términos de Servicio y la Política de<br>Privacidad
            </p>
        </div>
    </div>

    <!-- Script de Interactividad Total -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabLogin = document.getElementById('tabLogin');
            const tabRegistro = document.getElementById('tabRegistro');
            
            const formLogin = document.getElementById('formLogin');
            const formRegistro = document.getElementById('formRegistro');
            const formRecuperar = document.getElementById('formRecuperar');
            
            const btnOlvide = document.getElementById('btnOlvide');
            const btnVolver = document.getElementById('btnVolver');
            
            const tabsContainer = document.getElementById('tabsContainer');
            const socialLoginContainer = document.getElementById('socialLoginContainer');

            // --- 1. Cambio a pestaña Iniciar Sesión ---
            tabLogin.addEventListener('click', () => {
                tabLogin.classList.add('border-[#10B981]', 'text-white', 'font-semibold');
                tabLogin.classList.remove('border-transparent', 'text-slate-500', 'font-medium');
                
                tabRegistro.classList.add('border-transparent', 'text-slate-500', 'font-medium');
                tabRegistro.classList.remove('border-[#10B981]', 'text-white', 'font-semibold');
                
                formLogin.classList.remove('hidden');
                formRegistro.classList.add('hidden');
                formRecuperar.classList.add('hidden');
                
                tabsContainer.classList.remove('hidden', 'opacity-0');
                socialLoginContainer.classList.remove('hidden', 'opacity-0');
            });

            // --- 2. Cambio a pestaña Crear Cuenta ---
            tabRegistro.addEventListener('click', () => {
                tabRegistro.classList.add('border-[#10B981]', 'text-white', 'font-semibold');
                tabRegistro.classList.remove('border-transparent', 'text-slate-500', 'font-medium');
                
                tabLogin.classList.add('border-transparent', 'text-slate-500', 'font-medium');
                tabLogin.classList.remove('border-[#10B981]', 'text-white', 'font-semibold');
                
                formRegistro.classList.remove('hidden');
                formLogin.classList.add('hidden');
                formRecuperar.classList.add('hidden');
                
                tabsContainer.classList.remove('hidden', 'opacity-0');
                socialLoginContainer.classList.remove('hidden', 'opacity-0');
            });

            // --- 3. Clic en "Olvidé mi contraseña" ---
            btnOlvide.addEventListener('click', (e) => {
                e.preventDefault();
                formLogin.classList.add('hidden');
                formRegistro.classList.add('hidden');
                formRecuperar.classList.remove('hidden');
                
                // Ocultar pestañas y botón de Google para mantenerlo limpio
                tabsContainer.classList.add('hidden');
                socialLoginContainer.classList.add('hidden');
            });

            // --- 4. Clic en "Volver" desde Recuperar ---
            btnVolver.addEventListener('click', (e) => {
                e.preventDefault();
                formRecuperar.classList.add('hidden');
                formLogin.classList.remove('hidden');
                
                // Mostrar pestañas y botón de Google de nuevo
                tabsContainer.classList.remove('hidden');
                socialLoginContainer.classList.remove('hidden');
                
                // Asegurar que la pestaña Login esté visualmente activa
                tabLogin.click(); 
            });
        });
    </script>
</body>
</html>