<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pageTitle = 'Brandbook & Design System — Kontify APP';
require_once __DIR__ . '/includes/header.php';
?>

<!-- MAIN CONTENT CONTAINER -->
<div class="flex-1 overflow-y-auto px-4 py-6 lg:p-8 space-y-10 max-w-7xl mx-auto w-full">

    <!-- ==================================================================== -->
    <!-- BRAND HERO BANNER                                                    -->
    <!-- ==================================================================== -->
    <div class="relative overflow-hidden rounded-3xl bg-[#0F172A] text-white p-8 lg:p-12 shadow-2xl border border-slate-800">
        <!-- Abstract Glow Background -->
        <div class="absolute -top-32 -right-32 w-96 h-96 bg-[#00B894]/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-[#2563EB]/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 max-w-3xl">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[#00B894] text-xs font-mono font-bold uppercase tracking-wider mb-6">
                <i class="fa-solid fa-gem text-xs"></i>
                <span>Guía Oficial de Identidad Visual v2.0</span>
            </div>

            <h1 class="text-3xl lg:text-5xl font-extrabold tracking-tight text-white mb-4 leading-tight">
                El centro operativo inteligente del <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#00B894] to-[#00D2A4]">contador moderno</span>.
            </h1>

            <p class="text-slate-300 text-sm lg:text-base leading-relaxed mb-8">
                Kontify APP redefine la percepción de los despachos contables empresariales. Diseñado bajo los estándares de los software SaaS B2B de alto valor ($99–$299/mes), combinando minimalismo, precisión financiera, orden y control absoluto.
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6 border-t border-slate-800/80">
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-slate-400 font-bold">Concepto</div>
                    <div class="text-sm font-bold text-white mt-0.5">Control Inteligente</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-slate-400 font-bold">Tagline</div>
                    <div class="text-sm font-bold text-emerald-400 mt-0.5">Tu despacho, en orden</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-slate-400 font-bold">Categoría</div>
                    <div class="text-sm font-bold text-white mt-0.5">Fintech SaaS B2B</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-slate-400 font-bold">Tier de Valor</div>
                    <div class="text-sm font-bold text-blue-400 mt-0.5">$99 – $299 / mes</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- 1. SISTEMA DE LOGOTIPO & SÍMBOLOS                                    -->
    <!-- ==================================================================== -->
    <section class="space-y-6">
        <div>
            <div class="flex items-center gap-2 text-xs font-mono font-bold uppercase tracking-wider text-[#00B894]">
                <span>01. Identidad Simbólica</span>
            </div>
            <h2 class="text-2xl font-extrabold tracking-tight text-[#0F172A] mt-1">Sistema de Logotipo</h2>
            <p class="text-xs text-[#64748B] mt-0.5">Construcción geométrica, isótipo plegado y aplicaciones responsive.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Versión Principal Horizontal -->
            <div class="k-card p-6 flex flex-col justify-between lg:col-span-2">
                <div>
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Versión Principal Horizontal</span>
                        <span class="text-[11px] font-mono text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded font-semibold">Oficial SVG</span>
                    </div>

                    <div class="bg-[#F8FAFC] border border-dashed border-slate-200 rounded-2xl p-10 flex items-center justify-center min-h-[180px]">
                        <img src="<?= BASE_URL ?>/assets/kontify-logo.svg" alt="Kontify APP Logo" class="h-14 max-w-full drop-shadow-sm">
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-[#64748B]">
                    <span>Uso: Cabeceras web, facturas, cotizaciones oficiales y membretes.</span>
                    <a href="<?= BASE_URL ?>/assets/kontify-logo.svg" download class="k-btn k-btn-secondary text-xs py-1.5 px-3">
                        <i class="fa-solid fa-download text-xs"></i>
                        <span>SVG</span>
                    </a>
                </div>
            </div>

            <!-- Isotipo Símbolo Independiente -->
            <div class="k-card p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Isotipo Símbolo "K"</span>
                        <span class="text-[11px] font-mono text-blue-600 bg-blue-50 px-2 py-0.5 rounded font-semibold">Cinta 3D</span>
                    </div>

                    <div class="bg-[#F8FAFC] border border-dashed border-slate-200 rounded-2xl p-8 flex items-center justify-center min-h-[180px]">
                        <div class="w-24 h-24 rounded-2xl bg-white p-4 shadow-xl border border-slate-200/80 flex items-center justify-center hover:scale-105 transition-transform">
                            <img src="<?= BASE_URL ?>/assets/kontify-icon.svg" alt="Kontify Isotipo" class="w-full h-full">
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-[#64748B]">
                    <span>Representa crecimiento exponencial, orden y control.</span>
                    <a href="<?= BASE_URL ?>/assets/kontify-icon.svg" download class="k-btn k-btn-secondary text-xs py-1.5 px-3">
                        <i class="fa-solid fa-download text-xs"></i>
                        <span>SVG</span>
                    </a>
                </div>
            </div>

        </div>

        <!-- Mosaico de Aplicaciones del Icono -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <!-- App Icon Oscuro -->
            <div class="k-card p-5 text-center">
                <div class="w-16 h-16 rounded-2xl bg-[#0F172A] p-3 shadow-lg mx-auto mb-3 flex items-center justify-center border border-slate-800">
                    <img src="<?= BASE_URL ?>/assets/kontify-icon.svg" class="w-full h-full" alt="Dark App Icon">
                </div>
                <div class="text-xs font-bold text-[#0F172A]">App Icon Dark</div>
                <div class="text-[11px] text-slate-400 font-mono mt-0.5">iOS / Android</div>
            </div>

            <!-- App Icon Claro -->
            <div class="k-card p-5 text-center">
                <div class="w-16 h-16 rounded-2xl bg-white p-3 shadow-lg mx-auto mb-3 flex items-center justify-center border border-slate-200">
                    <img src="<?= BASE_URL ?>/assets/kontify-icon.svg" class="w-full h-full" alt="Light App Icon">
                </div>
                <div class="text-xs font-bold text-[#0F172A]">App Icon Light</div>
                <div class="text-[11px] text-slate-400 font-mono mt-0.5">Superficie Blanca</div>
            </div>

            <!-- Favicon Browser -->
            <div class="k-card p-5 text-center">
                <div class="w-16 h-16 rounded-xl bg-slate-100 p-4 mx-auto mb-3 flex items-center justify-center border border-slate-200">
                    <img src="<?= BASE_URL ?>/assets/favicon.svg" class="w-8 h-8 rounded" alt="Favicon">
                </div>
                <div class="text-xs font-bold text-[#0F172A]">Favicon Navegador</div>
                <div class="text-[11px] text-slate-400 font-mono mt-0.5">32x32 / 64x64 SVG</div>
            </div>

            <!-- Avatar del Sistema -->
            <div class="k-card p-5 text-center">
                <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-[#008F72] to-[#00B894] p-3.5 mx-auto mb-3 flex items-center justify-center shadow-md shadow-emerald-500/20">
                    <svg viewBox="0 0 120 120" fill="none" class="w-full h-full">
                        <path d="M22 22C22 17.5817 25.5817 14 30 14H38C42.4183 14 46 17.5817 46 22V98C46 102.418 42.4183 106 38 106H30C25.5817 106 22 102.418 22 98V22Z" fill="#FFFFFF"/>
                        <path d="M42 58L78.5 21C81.8 17.7 87.2 17.7 90.5 21L95.5 26C98.8 29.3 98.8 34.7 95.5 38L66 68L42 58Z" fill="#FFFFFF" fill-opacity="0.9"/>
                        <path d="M48 62L62 48L95.5 82C98.8 85.3 98.8 90.7 95.5 94L90.5 99C87.2 102.3 81.8 102.3 78.5 99L48 62Z" fill="#FFFFFF" fill-opacity="0.75"/>
                    </svg>
                </div>
                <div class="text-xs font-bold text-[#0F172A]">Avatar / Botón</div>
                <div class="text-[11px] text-slate-400 font-mono mt-0.5">Circular Monocromo</div>
            </div>
        </div>

        <!-- Criterios de Marca: Lo que se debe y NO se debe usar -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="k-card p-5 border-l-4 border-l-emerald-500">
                <div class="flex items-center gap-2 text-xs font-bold text-emerald-800 uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-circle-check text-emerald-600"></i>
                    <span>Criterios Esenciales de Marca (Aprobado)</span>
                </div>
                <ul class="text-xs text-[#64748B] space-y-1.5">
                    <li>&bull; Minimalismo geométrico con sensación tecnológica vanguardista.</li>
                    <li>&bull; Espaciado respirado y negativo, sin sobrecarga informativa.</li>
                    <li>&bull; Jerarquía tipográfica con números financieros en negrita impactante.</li>
                    <li>&bull; Sensación de despacho internacional de primer nivel.</li>
                </ul>
            </div>

            <div class="k-card p-5 border-l-4 border-l-rose-500">
                <div class="flex items-center gap-2 text-xs font-bold text-rose-800 uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-circle-xmark text-rose-600"></i>
                    <span>Estrictamente Prohibido (Evitar)</span>
                </div>
                <ul class="text-xs text-[#64748B] space-y-1.5">
                    <li>&bull; <strong class="text-rose-700">Calculadoras tradicionales</strong> o dibujos caricaturescos.</li>
                    <li>&bull; Símbolos de carpetas amarillas o archivadores físicos obsoletos.</li>
                    <li>&bull; Tablas sobrecargadas sin aire ni bordes redondeados.</li>
                    <li>&bull; Colores primarios no armonizados (rojo puro, azul chillón).</li>
                </ul>
            </div>
        </div>

    </section>

    <!-- ==================================================================== -->
    <!-- 2. PALETA VISUAL & TOKENS                                            -->
    <!-- ==================================================================== -->
    <section class="space-y-6">
        <div>
            <div class="flex items-center gap-2 text-xs font-mono font-bold uppercase tracking-wider text-[#00B894]">
                <span>02. Sistema Cromático</span>
            </div>
            <h2 class="text-2xl font-extrabold tracking-tight text-[#0F172A] mt-1">Paleta de Colores Oficial</h2>
            <p class="text-xs text-[#64748B] mt-0.5">Haz clic en cualquier tarjeta para copiar el código HEX al portapapeles.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- Verde Esmeralda Principal -->
            <div onclick="copiarColor('#00B894', this)" class="k-card p-4 cursor-pointer hover:scale-[1.02] transition-transform">
                <div class="h-24 rounded-xl bg-[#00B894] shadow-md shadow-emerald-500/20 mb-3 flex items-end p-2.5">
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-white/90 text-emerald-950 backdrop-blur-sm">Principal</span>
                </div>
                <div class="font-bold text-sm text-[#0F172A]">Verde Esmeralda</div>
                <div class="flex items-center justify-between text-xs font-mono text-slate-500 mt-1">
                    <span>#00B894</span>
                    <i class="fa-regular fa-copy text-[10px]"></i>
                </div>
                <div class="text-[11px] text-slate-400 mt-2">Confianza, crecimiento y frescura fintech.</div>
            </div>

            <!-- Verde Profundo -->
            <div onclick="copiarColor('#008F72', this)" class="k-card p-4 cursor-pointer hover:scale-[1.02] transition-transform">
                <div class="h-24 rounded-xl bg-[#008F72] shadow-md mb-3 flex items-end p-2.5">
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-white/90 text-emerald-950 backdrop-blur-sm">Hover / Depth</span>
                </div>
                <div class="font-bold text-sm text-[#0F172A]">Verde Profundo</div>
                <div class="flex items-center justify-between text-xs font-mono text-slate-500 mt-1">
                    <span>#008F72</span>
                    <i class="fa-regular fa-copy text-[10px]"></i>
                </div>
                <div class="text-[11px] text-slate-400 mt-2">Botones activos y estados de hover.</div>
            </div>

            <!-- Azul Tecnológico Secundario -->
            <div onclick="copiarColor('#2563EB', this)" class="k-card p-4 cursor-pointer hover:scale-[1.02] transition-transform">
                <div class="h-24 rounded-xl bg-[#2563EB] shadow-md shadow-blue-500/20 mb-3 flex items-end p-2.5">
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-white/90 text-blue-950 backdrop-blur-sm">Tecnológico</span>
                </div>
                <div class="font-bold text-sm text-[#0F172A]">Azul Profesional</div>
                <div class="flex items-center justify-between text-xs font-mono text-slate-500 mt-1">
                    <span>#2563EB</span>
                    <i class="fa-regular fa-copy text-[10px]"></i>
                </div>
                <div class="text-[11px] text-slate-400 mt-2">Acentos SaaS, gráficos y funciones pro.</div>
            </div>

            <!-- Texto Principal Slate Dark -->
            <div onclick="copiarColor('#0F172A', this)" class="k-card p-4 cursor-pointer hover:scale-[1.02] transition-transform">
                <div class="h-24 rounded-xl bg-[#0F172A] shadow-md mb-3 flex items-end p-2.5">
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-white/90 text-slate-950 backdrop-blur-sm">Texto Principal</span>
                </div>
                <div class="font-bold text-sm text-[#0F172A]">Slate Navy 900</div>
                <div class="flex items-center justify-between text-xs font-mono text-slate-500 mt-1">
                    <span>#0F172A</span>
                    <i class="fa-regular fa-copy text-[10px]"></i>
                </div>
                <div class="text-[11px] text-slate-400 mt-2">Titulares, números y contrastes sólidos.</div>
            </div>

            <!-- Texto Secundario -->
            <div onclick="copiarColor('#64748B', this)" class="k-card p-4 cursor-pointer hover:scale-[1.02] transition-transform">
                <div class="h-24 rounded-xl bg-[#64748B] mb-3 flex items-end p-2.5">
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-white/90 text-slate-900 backdrop-blur-sm">Secundario</span>
                </div>
                <div class="font-bold text-sm text-[#0F172A]">Muted Slate 500</div>
                <div class="flex items-center justify-between text-xs font-mono text-slate-500 mt-1">
                    <span>#64748B</span>
                    <i class="fa-regular fa-copy text-[10px]"></i>
                </div>
                <div class="text-[11px] text-slate-400 mt-2">Descripciones, etiquetas y metadatos.</div>
            </div>

            <!-- Bordes Elegantes -->
            <div onclick="copiarColor('#E2E8F0', this)" class="k-card p-4 cursor-pointer hover:scale-[1.02] transition-transform">
                <div class="h-24 rounded-xl bg-[#E2E8F0] mb-3 flex items-end p-2.5 border border-slate-300">
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-slate-800 text-white">Bordes</span>
                </div>
                <div class="font-bold text-sm text-[#0F172A]">Border Slate 200</div>
                <div class="flex items-center justify-between text-xs font-mono text-slate-500 mt-1">
                    <span>#E2E8F0</span>
                    <i class="fa-regular fa-copy text-[10px]"></i>
                </div>
                <div class="text-[11px] text-slate-400 mt-2">Separaciones sutiles y tarjetas sin ruido.</div>
            </div>

            <!-- Fondo de Aplicación -->
            <div onclick="copiarColor('#F8FAFC', this)" class="k-card p-4 cursor-pointer hover:scale-[1.02] transition-transform">
                <div class="h-24 rounded-xl bg-[#F8FAFC] mb-3 flex items-end p-2.5 border border-slate-200">
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-slate-800 text-white">Lienzo</span>
                </div>
                <div class="font-bold text-sm text-[#0F172A]">Canvas Fondo</div>
                <div class="flex items-center justify-between text-xs font-mono text-slate-500 mt-1">
                    <span>#F8FAFC</span>
                    <i class="fa-regular fa-copy text-[10px]"></i>
                </div>
                <div class="text-[11px] text-slate-400 mt-2">Fondo relajante para descansos visuales.</div>
            </div>

            <!-- Superficie Cards -->
            <div onclick="copiarColor('#FFFFFF', this)" class="k-card p-4 cursor-pointer hover:scale-[1.02] transition-transform">
                <div class="h-24 rounded-xl bg-white mb-3 flex items-end p-2.5 border border-slate-200 shadow-sm">
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-slate-800 text-white">Superficie</span>
                </div>
                <div class="font-bold text-sm text-[#0F172A]">Blanco Puro</div>
                <div class="flex items-center justify-between text-xs font-mono text-slate-500 mt-1">
                    <span>#FFFFFF</span>
                    <i class="fa-regular fa-copy text-[10px]"></i>
                </div>
                <div class="text-[11px] text-slate-400 mt-2">Cards flotantes, modales y tablas limpias.</div>
            </div>

        </div>
    </section>

    <!-- ==================================================================== -->
    <!-- 3. TIPOGRAFÍA & NÚMEROS FINANCIEROS                                 -->
    <!-- ==================================================================== -->
    <section class="space-y-6">
        <div>
            <div class="flex items-center gap-2 text-xs font-mono font-bold uppercase tracking-wider text-[#00B894]">
                <span>03. Tipografía & Jerarquía</span>
            </div>
            <h2 class="text-2xl font-extrabold tracking-tight text-[#0F172A] mt-1">Plus Jakarta Sans + JetBrains Mono</h2>
            <p class="text-xs text-[#64748B] mt-0.5">Diseñado para legibilidad empresarial y visualización contable de alta precisión.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Tipografía de Textos e Interfaces -->
            <div class="k-card p-6 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h4 class="font-extrabold text-base text-[#0F172A]">Plus Jakarta Sans</h4>
                        <span class="text-xs text-[#64748B]">Tipografía Primaria UI (Google Fonts)</span>
                    </div>
                    <span class="text-xs font-mono bg-slate-100 px-2 py-0.5 rounded text-slate-600 font-bold">Weights: 500, 600, 700, 800</span>
                </div>

                <div class="space-y-4">
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">H1 Titular Principal (28px - 36px)</div>
                        <div class="text-3xl font-extrabold tracking-tight text-[#0F172A]">El centro de control de tu despacho.</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">H2 Subtítulo de Sección (20px)</div>
                        <div class="text-xl font-bold tracking-tight text-[#0F172A]">Gestión de honorarios y declaraciones fiscales.</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Cuerpo de Texto / UI (13px - 14px)</div>
                        <p class="text-xs text-[#64748B] leading-relaxed">
                            Kontify organiza clientes, genera propuestas comerciales aprobadas por directores de finanzas y automatiza las alertas de vencimiento del calendario tributario nacional.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Estilo Financiero: Números Grandes e Impactantes -->
            <div class="k-card p-6 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h4 class="font-extrabold text-base text-[#0F172A]">JetBrains Mono (Finanzas)</h4>
                        <span class="text-xs text-[#64748B]">Métricas Bold, Balances & Tasas</span>
                    </div>
                    <span class="text-xs font-mono bg-emerald-50 text-[#00B894] border border-emerald-200 px-2 py-0.5 rounded font-bold">Mono Tabular</span>
                </div>

                <div class="bg-[#F8FAFC] border border-slate-200/80 rounded-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200/70">
                        <div>
                            <div class="text-xs text-[#64748B] font-medium">Ingresos del mes</div>
                            <div class="text-3xl font-extrabold font-mono tracking-tight text-[#0F172A] mt-0.5">$4,850 <span class="text-sm font-semibold text-slate-400">USD</span></div>
                        </div>
                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full flex items-center gap-1">
                            <i class="fa-solid fa-arrow-trend-up text-[10px]"></i> +18%
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-1">
                        <div>
                            <div class="text-[11px] text-[#64748B]">Por cobrar</div>
                            <div class="text-xl font-extrabold font-mono text-[#0F172A]">$1,200 <span class="text-xs font-normal text-slate-400">USD</span></div>
                        </div>
                        <div>
                            <div class="text-[11px] text-[#64748B]">Tasa BCV Oficial</div>
                            <div class="text-xl font-extrabold font-mono text-blue-600">Bs. 65,50</div>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-[#64748B]">
                    El uso de fuentes monoespaciadas tabulares garantiza que las columnas numéricas nunca se desalineen, transmitiendo seriedad bancaria institucional.
                </p>
            </div>

        </div>
    </section>

    <!-- ==================================================================== -->
    <!-- 4. BIBLIOTECA DE COMPONENTES TAILWIND                                -->
    <!-- ==================================================================== -->
    <section class="space-y-6">
        <div>
            <div class="flex items-center gap-2 text-xs font-mono font-bold uppercase tracking-wider text-[#00B894]">
                <span>04. Componentes UI Reutilizables</span>
            </div>
            <h2 class="text-2xl font-extrabold tracking-tight text-[#0F172A] mt-1">Design System Tailwind</h2>
            <p class="text-xs text-[#64748B] mt-0.5">Patrones visuales consistentes en toda la plataforma Kontify.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Botones -->
            <div class="k-card p-6 space-y-4">
                <h4 class="font-bold text-xs uppercase tracking-wider text-slate-400">Botones de Acción</h4>
                <div class="space-y-2.5">
                    <button class="k-btn k-btn-primary w-full">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Primary Action (#00B894)</span>
                    </button>
                    <button class="k-btn k-btn-secondary w-full">
                        <i class="fa-solid fa-download text-xs"></i>
                        <span>Secondary Button (White)</span>
                    </button>
                    <button class="k-btn k-btn-tech w-full">
                        <i class="fa-solid fa-bolt text-xs"></i>
                        <span>Tech Action (#2563EB)</span>
                    </button>
                </div>
            </div>

            <!-- Badges & Chips -->
            <div class="k-card p-6 space-y-4">
                <h4 class="font-bold text-xs uppercase tracking-wider text-slate-400">Pills de Estatus Fiscal</h4>
                <div class="flex flex-wrap gap-2">
                    <span class="k-badge k-badge-success">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Al Día
                    </span>
                    <span class="k-badge k-badge-warning">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        Por Vencer
                    </span>
                    <span class="k-badge k-badge-danger">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                        En Mora
                    </span>
                    <span class="k-badge k-badge-premium">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                        IA OCR Listo
                    </span>
                    <span class="k-badge k-badge-neutral">
                        Contribuyente Especial
                    </span>
                </div>
            </div>

            <!-- Inputs & Selects -->
            <div class="k-card p-6 space-y-4">
                <h4 class="font-bold text-xs uppercase tracking-wider text-slate-400">Controles de Entrada</h4>
                <div>
                    <label class="k-label">Razón Social del Cliente</label>
                    <input type="text" class="k-input text-xs" value="Inversiones El Ávila, C.A." readonly>
                </div>
                <div>
                    <label class="k-label">Monto de Honorario (USD)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs text-slate-400 font-mono font-bold">$</span>
                        <input type="text" class="k-input pl-7 text-xs font-mono font-bold" value="380.00" readonly>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ==================================================================== -->
    <!-- 5. EXPERIENCIA RESPONSIVE MOBILE APP                                 -->
    <!-- ==================================================================== -->
    <section class="space-y-6">
        <div>
            <div class="flex items-center gap-2 text-xs font-mono font-bold uppercase tracking-wider text-[#00B894]">
                <span>05. Experiencia Móvil de Primera Clase</span>
            </div>
            <h2 class="text-2xl font-extrabold tracking-tight text-[#0F172A] mt-1">Mobile Native Experience</h2>
            <p class="text-xs text-[#64748B] mt-0.5">Navegación ergonómica en el pulgar (Bottom Bar) y botón flotante de alta velocidad.</p>
        </div>

        <div class="k-card p-8 bg-gradient-to-br from-slate-900 via-slate-950 to-slate-900 text-white border-slate-800">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                
                <div class="lg:col-span-7 space-y-6">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[#00B894] text-xs font-mono font-bold">
                        <i class="fa-solid fa-mobile-screen text-xs"></i>
                        <span>Diseño Operativo a Una Sola Mano</span>
                    </span>

                    <h3 class="text-2xl lg:text-3xl font-extrabold text-white leading-snug">
                        El contador en movimiento: control total desde su teléfono.
                    </h3>

                    <p class="text-slate-300 text-xs lg:text-sm leading-relaxed">
                        En América Latina los contadores atienden a sus clientes entre reuniones, bancos y notarías. Kontify cuenta con navegación nativa en la parte inferior de la pantalla para acceder a Inicio, Clientes, Finanzas, Documentos y Perfil al instante.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                            <div class="text-xs font-bold text-emerald-400 flex items-center gap-2 mb-1">
                                <i class="fa-solid fa-hand text-xs"></i> Bottom Navigation
                            </div>
                            <div class="text-[11px] text-slate-300">5 accesos directos estratégicos al alcance del pulgar sin estiramientos.</div>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                            <div class="text-xs font-bold text-emerald-400 flex items-center gap-2 mb-1">
                                <i class="fa-solid fa-circle-plus text-xs"></i> Floating FAB (+)
                            </div>
                            <div class="text-[11px] text-slate-300">Action Sheet instantáneo para registrar ingresos, cotizar o subir recibos en 3 segundos.</div>
                        </div>
                    </div>
                </div>

                <!-- Mockup Interactivo del Teléfono -->
                <div class="lg:col-span-5 flex justify-center">
                    <div class="w-[280px] bg-slate-900 rounded-[38px] p-3 shadow-2xl border-4 border-slate-700/80 relative">
                        <!-- Dynamic Island Notch -->
                        <div class="w-24 h-4 bg-black rounded-full mx-auto mb-3"></div>

                        <!-- Pantalla del Celular -->
                        <div class="bg-[#F8FAFC] rounded-[28px] overflow-hidden text-[#0F172A] flex flex-col justify-between h-[480px] relative border border-slate-300">
                            
                            <!-- Header Móvil Simulado -->
                            <div class="p-3 bg-white border-b border-slate-100 flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-6 h-6 rounded-lg bg-slate-900 flex items-center justify-center p-0.5">
                                        <img src="<?= BASE_URL ?>/assets/kontify-icon.svg" class="w-full h-full" alt="K">
                                    </div>
                                    <span class="text-xs font-extrabold text-[#0F172A]">Kontify</span>
                                </div>
                                <span class="text-[9px] font-mono font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">65,50 Bs</span>
                            </div>

                            <!-- Contenido Dashboard Móvil -->
                            <div class="p-3 space-y-2.5 flex-1 overflow-y-auto text-[11px]">
                                <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-sm">
                                    <div class="text-[10px] text-[#64748B]">Ingresos de Septiembre</div>
                                    <div class="text-lg font-extrabold font-mono text-[#0F172A]">$4,850</div>
                                    <span class="text-[9px] text-emerald-600 font-bold">+18% vs mes ant.</span>
                                </div>

                                <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-sm space-y-1.5">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Atención Requerida</div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-600">IVA Comercializadora</span>
                                        <span class="text-[9px] font-bold text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded">Hoy</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-600">ISLR Farmacia Andes</span>
                                        <span class="text-[9px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded">3 días</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Nav Mockup con FAB Elevado -->
                            <div class="bg-white border-t border-slate-200/90 py-2 px-3 relative">
                                <!-- Floating Button Mockup -->
                                <div class="absolute -top-5 left-1/2 -translate-x-1/2 w-10 h-10 rounded-full bg-[#00B894] text-white flex items-center justify-center shadow-lg shadow-emerald-500/30 text-sm font-bold">
                                    <i class="fa-solid fa-plus"></i>
                                </div>

                                <div class="flex items-center justify-between text-[8px] text-slate-400 font-bold pt-1">
                                    <div class="text-center text-[#00B894]">
                                        <i class="fa-solid fa-chart-pie block text-xs"></i>
                                        <span>Inicio</span>
                                    </div>
                                    <div class="text-center">
                                        <i class="fa-solid fa-users block text-xs"></i>
                                        <span>Clientes</span>
                                    </div>
                                    <div class="w-6"></div>
                                    <div class="text-center">
                                        <i class="fa-solid fa-vault block text-xs"></i>
                                        <span>Bóveda</span>
                                    </div>
                                    <div class="text-center">
                                        <i class="fa-solid fa-circle-user block text-xs"></i>
                                        <span>Perfil</span>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Home Bar -->
                        <div class="w-28 h-1 bg-slate-600 rounded-full mx-auto mt-3"></div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ==================================================================== -->
    <!-- 6. CENTRO DE DESCARGAS DE ASSETS                                     -->
    <!-- ==================================================================== -->
    <div class="k-card p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#00B894] flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-box-archive"></i>
            </div>
            <div>
                <h4 class="font-extrabold text-sm text-[#0F172A]">Kit Oficial de Identidad y Vectores</h4>
                <p class="text-xs text-[#64748B]">Descarga los logotipos maestros en formato SVG escalable de alta resolución.</p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <a href="<?= BASE_URL ?>/assets/kontify-logo.svg" download class="k-btn k-btn-secondary text-xs">
                <i class="fa-solid fa-file-arrow-down text-xs"></i>
                <span>Logo Horizontal</span>
            </a>
            <a href="<?= BASE_URL ?>/assets/kontify-icon.svg" download class="k-btn k-btn-primary text-xs">
                <i class="fa-solid fa-file-arrow-down text-xs"></i>
                <span>Isotipo Cinta K</span>
            </a>
        </div>
    </div>

</div>

<!-- Copy Color Script -->
<script>
function copiarColor(hex, el) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(hex).then(() => {
            mostrarToast("Código " + hex + " copiado al portapapeles.");
        });
    } else {
        const dummy = document.createElement("input");
        document.body.appendChild(dummy);
        dummy.value = hex;
        dummy.select();
        document.execCommand("copy");
        document.body.removeChild(dummy);
        mostrarToast("Código " + hex + " copiado.");
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
