        </main>
    </div> <!-- Close flex-1 from header.php -->
</div> <!-- Close flex from header.php -->

<!-- ==================================================================== -->
<!-- RESPONSIVE MOBILE APP BOTTOM NAVIGATION (< 1024px)                   -->
<!-- ==================================================================== -->
<nav class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200/90 px-2 py-1.5 pb-safe shadow-[0_-4px_20px_rgba(15,23,42,0.06)]">
    <div class="grid grid-cols-5 items-center max-w-md mx-auto text-center">
        
        <!-- 1. Inicio -->
        <a href="<?= BASE_URL ?>/index.php" class="flex flex-col items-center justify-center py-1 transition-colors <?= $currentPage === 'index' ? 'text-[#00B894] font-bold' : 'text-[#64748B] hover:text-[#0F172A]' ?>">
            <i class="fa-solid fa-chart-pie text-base mb-1"></i>
            <span class="text-[10px] tracking-tight">Inicio</span>
        </a>

        <!-- 2. Clientes -->
        <a href="<?= BASE_URL ?>/crm.php" class="flex flex-col items-center justify-center py-1 transition-colors <?= ($currentPage === 'crm' || $currentPage === 'cliente_detalle') ? 'text-[#00B894] font-bold' : 'text-[#64748B] hover:text-[#0F172A]' ?>">
            <i class="fa-solid fa-users text-base mb-1"></i>
            <span class="text-[10px] tracking-tight">Clientes</span>
        </a>

        <!-- 3. FAB Central (+) -->
        <div class="flex flex-col items-center justify-center -mt-6">
            <button type="button" onclick="abrirActionSheetMovil()" aria-label="Crear nuevo movimiento" class="w-13 h-13 w-12 h-12 rounded-full bg-[#00B894] hover:bg-[#008F72] text-white flex items-center justify-center text-xl shadow-lg shadow-emerald-500/40 active:scale-95 transition-all">
                <i class="fa-solid fa-plus"></i>
            </button>
            <span class="text-[9px] font-bold text-slate-500 mt-1">Nuevo</span>
        </div>

        <!-- 4. Ingresos / Finanzas -->
        <a href="<?= BASE_URL ?>/ingresos.php" class="flex flex-col items-center justify-center py-1 transition-colors <?= $currentPage === 'ingresos' ? 'text-[#00B894] font-bold' : 'text-[#64748B] hover:text-[#0F172A]' ?>">
            <i class="fa-solid fa-receipt text-base mb-1"></i>
            <span class="text-[10px] tracking-tight">Ingresos</span>
        </a>

        <!-- 5. Documentos (Bóveda) -->
        <a href="<?= BASE_URL ?>/boveda.php" class="flex flex-col items-center justify-center py-1 transition-colors <?= $currentPage === 'boveda' ? 'text-[#00B894] font-bold' : 'text-[#64748B] hover:text-[#0F172A]' ?>">
            <i class="fa-solid fa-vault text-base mb-1"></i>
            <span class="text-[10px] tracking-tight">Bóveda</span>
        </a>

    </div>
</nav>

<!-- ==================================================================== -->
<!-- ACTION SHEET MÓVIL (CREAR NUEVO MOVIMIENTO CON UNA MANO)             -->
<!-- ==================================================================== -->
<div id="actionSheetMovil" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-end justify-center" onclick="cerrarActionSheetMovil(event)">
    <div class="bg-white rounded-t-3xl border-t border-slate-200 shadow-2xl max-w-md w-full p-6 pb-8 animate-in slide-in-from-bottom duration-200" onclick="event.stopPropagation()">
        
        <div class="w-12 h-1 bg-slate-300 rounded-full mx-auto mb-4"></div>
        
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <div>
                <h3 class="text-base font-bold text-[#0F172A]">Crear Nuevo Movimiento</h3>
                <p class="text-xs text-[#64748B]">Acciones rápidas del despacho</p>
            </div>
            <button onclick="cerrarActionSheetMovil()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <a href="<?= BASE_URL ?>/crm.php?accion=nuevo" class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50 hover:bg-emerald-50/50 hover:border-[#00B894] text-left transition-all">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-[#00B894] flex items-center justify-center text-lg mb-2">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <div class="font-bold text-xs text-[#0F172A]">Nuevo Cliente</div>
                <div class="text-[10px] text-slate-500">Expediente fiscal</div>
            </a>

            <a href="<?= BASE_URL ?>/ingresos.php?accion=registrar" class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50 hover:bg-blue-50/50 hover:border-[#2563EB] text-left transition-all">
                <div class="w-10 h-10 rounded-xl bg-blue-100 text-[#2563EB] flex items-center justify-center text-lg mb-2">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
                <div class="font-bold text-xs text-[#0F172A]">Registrar Cobro</div>
                <div class="text-[10px] text-slate-500">Honorario o proyecto</div>
            </a>

            <a href="<?= BASE_URL ?>/cotizador.php" class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50 hover:bg-amber-50/50 hover:border-amber-500 text-left transition-all">
                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg mb-2">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
                <div class="font-bold text-xs text-[#0F172A]">Cotización Pro</div>
                <div class="text-[10px] text-slate-500">Propuesta con PDF</div>
            </a>

            <a href="<?= BASE_URL ?>/boveda.php" class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50 hover:bg-purple-50/50 hover:border-purple-500 text-left transition-all">
                <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center text-lg mb-2">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
                <div class="font-bold text-xs text-[#0F172A]">Subir a Bóveda</div>
                <div class="text-[10px] text-slate-500">PDF / Foto comprobante</div>
            </a>
        </div>
    </div>
</div>

<!-- ==================================================================== -->
<!-- MODAL: ACTUALIZAR TASA OFICIAL BCV                                   -->
<!-- ==================================================================== -->
<div id="modalTasaBcv" class="fixed inset-0 z-50 hidden bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-sm w-full p-6 animate-in fade-in zoom-in duration-150">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-[#00B894] flex items-center justify-center font-bold">
                    <i class="fa-solid fa-coins text-sm"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[#0F172A]">Ajustar Tasa Oficial BCV</h3>
                    <p class="text-[11px] text-[#64748B]">Referencial para conversión de honorarios</p>
                </div>
            </div>
            <button onclick="document.getElementById('modalTasaBcv').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="actualizar_tasa_bcv" value="1">
            <div class="mb-4">
                <label class="k-label">Valor en Bolívares (Bs. por USD)</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-mono font-bold text-slate-400">Bs.</span>
                    <input type="number" step="0.01" name="tasa_bcv_input" value="<?= number_format($tasaBcvActual, 2, '.', '') ?>" required class="k-input pl-10 font-mono font-bold text-base text-[#0F172A]">
                </div>
                <p class="text-[10px] text-slate-400 mt-1.5">Aplica instantáneamente a cotizaciones y cálculos de cobranza.</p>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalTasaBcv').classList.add('hidden')" class="k-btn k-btn-secondary text-xs">
                    Cancelar
                </button>
                <button type="submit" class="k-btn k-btn-primary text-xs">
                    Guardar Tasa
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================================================================== -->
<!-- COMMAND PALETTE (Ctrl / Cmd + K)                                     -->
<!-- ==================================================================== -->
<div id="commandPalette" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-start justify-center pt-24 p-4" onclick="cerrarCommandPalette(event)">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-xl w-full overflow-hidden" onclick="event.stopPropagation()">
        
        <!-- Input Search -->
        <div class="p-4 border-b border-slate-100 flex items-center gap-3">
            <i class="fa-solid fa-magnifying-glass text-slate-400 text-sm"></i>
            <input type="text" id="commandInput" placeholder="Escribe para buscar cliente, módulo o acción rápida..." oninput="filtrarCommandPalette()" class="w-full text-sm outline-none text-[#0F172A] placeholder:text-slate-400">
            <kbd class="px-1.5 py-0.5 text-[10px] font-mono font-bold bg-slate-100 text-slate-500 rounded border border-slate-200">ESC</kbd>
        </div>

        <!-- Command List -->
        <div class="max-h-80 overflow-y-auto p-2" id="commandResults">
            <div class="px-3 py-1.5 text-[10px] font-bold uppercase text-slate-400 tracking-wider">Módulos del Sistema</div>
            <a href="<?= BASE_URL ?>/index.php" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 text-xs font-semibold text-slate-700">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-emerald-50 text-[#00B894] flex items-center justify-center text-xs"><i class="fa-solid fa-chart-pie"></i></div>
                    <span>Dashboard Principal</span>
                </div>
                <span class="text-[10px] text-slate-400 font-mono">Inicio</span>
            </a>
            <a href="<?= BASE_URL ?>/crm.php" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 text-xs font-semibold text-slate-700">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-blue-50 text-[#2563EB] flex items-center justify-center text-xs"><i class="fa-solid fa-users"></i></div>
                    <span>Clientes (CRM Contable 360°)</span>
                </div>
                <span class="text-[10px] text-slate-400 font-mono">Cartera</span>
            </a>
            <a href="<?= BASE_URL ?>/ingresos.php" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 text-xs font-semibold text-slate-700">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-emerald-50 text-[#00B894] flex items-center justify-center text-xs"><i class="fa-solid fa-receipt"></i></div>
                    <span>Finanzas del Despacho (Ingresos & Rentabilidad)</span>
                </div>
                <span class="text-[10px] text-slate-400 font-mono">Finanzas</span>
            </a>
            <a href="<?= BASE_URL ?>/cotizador.php" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 text-xs font-semibold text-slate-700">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    <span>Cotizador Inteligente (Propuestas Comerciales B2B)</span>
                </div>
                <span class="text-[10px] text-slate-400 font-mono">Cotizar</span>
            </a>
            <a href="<?= BASE_URL ?>/boveda.php" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 text-xs font-semibold text-slate-700">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center text-xs"><i class="fa-solid fa-vault"></i></div>
                    <span>Bóveda Documental (PDFs e Imágenes con IA OCR)</span>
                </div>
                <span class="text-[10px] text-slate-400 font-mono">Bóveda</span>
            </a>
            <a href="<?= BASE_URL ?>/reportes.php" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 text-xs font-semibold text-slate-700">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs"><i class="fa-solid fa-chart-line"></i></div>
                    <span>Reportes Ejecutivos & Rentabilidad</span>
                </div>
                <span class="text-[10px] text-slate-400 font-mono">BI</span>
            </a>
            <a href="<?= BASE_URL ?>/brand.php" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 text-xs font-semibold text-slate-700">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center text-xs"><i class="fa-solid fa-palette"></i></div>
                    <span>Brandbook & UI Kit de Kontify APP</span>
                </div>
                <span class="text-[10px] text-slate-400 font-mono">Marca</span>
            </a>
        </div>

        <div class="p-2.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 px-4">
            <div class="flex items-center gap-2">
                <span>Navegar con <kbd class="px-1 py-0.5 bg-white border border-slate-200 rounded font-mono text-[9px]">↑</kbd> <kbd class="px-1 py-0.5 bg-white border border-slate-200 rounded font-mono text-[9px]">↓</kbd></span>
                <span>•</span>
                <span>Abrir con <kbd class="px-1 py-0.5 bg-white border border-slate-200 rounded font-mono text-[9px]">Enter</kbd></span>
            </div>
            <span>Kontify APP v2.6</span>
        </div>
    </div>
</div>

<script>
// Atajo global Ctrl/Cmd + K
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        abrirCommandPalette();
    }
    if (e.key === 'Escape') {
        document.getElementById('commandPalette').classList.add('hidden');
        const dMenu = document.getElementById('dropdownNuevoMenu');
        if (dMenu) dMenu.classList.add('hidden');
        cerrarActionSheetMovil();
    }
});

function abrirCommandPalette() {
    const cp = document.getElementById('commandPalette');
    cp.classList.remove('hidden');
    setTimeout(() => document.getElementById('commandInput').focus(), 50);
}

function cerrarCommandPalette(e) {
    document.getElementById('commandPalette').classList.add('hidden');
}

function filtrarCommandPalette() {
    const query = document.getElementById('commandInput').value.toLowerCase();
    const items = document.querySelectorAll('#commandResults .cmd-item');
    items.forEach(el => {
        const text = el.textContent.toLowerCase();
        el.style.display = text.includes(query) ? 'flex' : 'none';
    });
}

function toggleDropdownNuevo() {
    const menu = document.getElementById('dropdownNuevoMenu');
    if (menu) menu.classList.toggle('hidden');
}

function abrirActionSheetMovil() {
    const sheet = document.getElementById('actionSheetMovil');
    if (sheet) sheet.classList.remove('hidden');
}

function cerrarActionSheetMovil() {
    const sheet = document.getElementById('actionSheetMovil');
    if (sheet) sheet.classList.add('hidden');
}

// Cerrar dropdown al hacer click fuera
document.addEventListener('click', (e) => {
    const wrap = document.getElementById('dropdownNuevoWrap');
    if (wrap && !wrap.contains(e.target)) {
        const menu = document.getElementById('dropdownNuevoMenu');
        if (menu) menu.classList.add('hidden');
    }
});
</script>

</body>
</html>
