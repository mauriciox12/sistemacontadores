<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['empresa_id'])) {
    header("Location: seleccionar_empresa.php");
    exit;
}

global $pdo;
$db = $pdo;
$empresa_id = (int)$_SESSION['empresa_id'];
$empresa_nombre = $_SESSION['empresa_nombre'] ?? 'Empresa Activa';

$mensajeAlerta = '';
$tipoAlerta = '';

// 1. CREAR NUEVO ACCESO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_acceso'])) {
    $nombre = trim($_POST['nombre']);
    $username = trim(strtolower($_POST['username']));
    $password = $_POST['password'];
    $rol = $_POST['rol']; // Administrador, Supervisor, Analista, Cajero

    if (!empty($nombre) && !empty($username) && !empty($password)) {
        try {
            $db->beginTransaction();

            // Verificar si el usuario ya existe en tabla users
            $stmtUser = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmtUser->execute([$username]);
            $userExistenteId = $stmtUser->fetchColumn();

            if ($userExistenteId) {
                $colaboradorId = (int)$userExistenteId;
            } else {
                // Crear usuario central
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $emailFalso = $username . "@nexus.local";
                $stmtInsert = $db->prepare("INSERT INTO users (name, username, email, password_hash) VALUES (?, ?, ?, ?)");
                $stmtInsert->execute([$nombre, $username, $emailFalso, $hash]);
                $colaboradorId = (int)$db->lastInsertId();
            }

            // Vincular al usuario con la empresa activa y su rol
            $stmtVincular = $db->prepare("INSERT INTO empresa_usuarios (empresa_id, user_id, rol) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rol = ?");
            $stmtVincular->execute([$empresa_id, $colaboradorId, $rol, $rol]);

            $db->commit();
            $mensajeAlerta = "¡Acceso concedido exitosamente a {$nombre}!";
            $tipoAlerta = 'success';
        } catch (Exception $e) {
            $db->rollBack();
            $mensajeAlerta = "Error: " . $e->getMessage();
            $tipoAlerta = 'error';
        }
    }
}

// 2. REVOCAR / ELIMINAR ACCESO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revocar_acceso'])) {
    $userIdBorrar = (int)$_POST['user_id_borrar'];
    
    // No permitir que el usuario actual se revoque a sí mismo
    if ($userIdBorrar === (int)$_SESSION['user_id']) {
        $mensajeAlerta = "No puedes revocar tu propio acceso en la empresa activa.";
        $tipoAlerta = 'error';
    } else {
        try {
            $stmtDel = $db->prepare("DELETE FROM empresa_usuarios WHERE empresa_id = ? AND user_id = ?");
            $stmtDel->execute([$empresa_id, $userIdBorrar]);
            $mensajeAlerta = "Acceso revocado correctamente.";
            $tipoAlerta = 'success';
        } catch (Exception $e) {
            $mensajeAlerta = "Error: " . $e->getMessage();
            $tipoAlerta = 'error';
        }
    }
}

// 3. CONSULTAR PERSONAL AUTORIZADO
$personal = [];
try {
    $stmtLista = $db->prepare("
        SELECT u.id, u.name, u.username, eu.rol 
        FROM users u 
        JOIN empresa_usuarios eu ON u.id = eu.user_id 
        WHERE eu.empresa_id = ? 
        ORDER BY u.id ASC
    ");
    $stmtLista->execute([$empresa_id]);
    $personal = $stmtLista->fetchAll();
} catch (Exception $e) {}

$pageTitle = "Multiacceso | Panel de Seguridad";
require_once __DIR__ . '/includes/header.php';
?>

<!-- BANNER NEXUS -->
<div class="bg-[#1e3a8a] text-white px-6 py-4 rounded-2xl mb-6 flex justify-between items-center shadow-lg">
    <div class="flex items-center gap-3">
        <i class="fa-solid fa-border-all text-xl text-cyan-300"></i>
        <span class="font-extrabold tracking-widest uppercase text-sm">NEXUS DASHBOARD</span>
    </div>
    <div class="text-xs font-semibold text-blue-200">Panel de Seguridad y Accesos</div>
</div>

<?php if (!empty($mensajeAlerta)): ?>
    <div class="mb-6 p-4 rounded-xl flex items-center justify-between shadow-sm <?= $tipoAlerta === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-800' ?>">
        <span class="text-sm font-bold"><?= htmlspecialchars($mensajeAlerta) ?></span>
        <button onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <!-- COLUMNA IZQUIERDA: NUEVO ACCESO -->
    <div class="lg:col-span-4">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 border-t-4 border-t-[#2ecc71]">
            <h3 class="text-base font-extrabold text-[#1d2731] flex items-center gap-2 mb-5">
                <i class="fa-solid fa-user-plus text-[#2ecc71]"></i> Nuevo Acceso
            </h3>

            <!-- Recuadro Empresa Actual -->
            <div class="bg-slate-50 border-l-4 border-[#1d2731] p-3.5 rounded-lg mb-5">
                <span class="block text-[10px] text-slate-500 font-bold uppercase tracking-wider">Empresa Actual:</span>
                <span class="text-sm font-extrabold text-[#1d2731] uppercase"><?= htmlspecialchars($empresa_nombre) ?></span>
            </div>

            <form method="POST">
                <input type="hidden" name="crear_acceso" value="1">
                
                <div class="mb-4">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Nombre del Colaborador</label>
                    <input type="text" name="nombre" placeholder="NOMBRE COMPLETO" required class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-xs font-semibold uppercase outline-none focus:border-[#2ecc71]">
                </div>

                <div class="mb-4">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Nombre de Usuario (Login)</label>
                    <input type="text" name="username" placeholder="ej: admin.central" required class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-xs font-semibold outline-none focus:border-[#2ecc71]">
                </div>

                <div class="mb-4">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Contraseña de Acceso</label>
                    <input type="password" name="password" placeholder="••••••••" required class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-xs font-semibold outline-none focus:border-[#2ecc71]">
                </div>

                <div class="mb-5">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Rol en el Sistema</label>
                    <select name="rol" class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-xs font-semibold bg-white outline-none focus:border-[#2ecc71]">
                        <option value="Administrador">Administrador (Total)</option>
                        <option value="Operador">Operador (Ingresos / Cotizador)</option>
                        <option value="Asistente">Asistente (Solo Lectura)</option>
                    </select>
                </div>

                <button type="submit" class="w-full bg-[#2ecc71] hover:bg-[#27ae60] text-white font-extrabold py-3 rounded-lg text-xs tracking-wider uppercase transition-colors shadow-md shadow-emerald-500/20 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-lock text-xs"></i> Activar Acceso
                </button>
            </form>
        </div>
    </div>

    <!-- COLUMNA DERECHA: PERSONAL AUTORIZADO -->
    <div class="lg:col-span-8">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 min-h-[460px]">
            <h3 class="text-base font-extrabold text-[#1d2731] flex items-center gap-2 mb-6 border-b border-slate-100 pb-4">
                <i class="fa-solid fa-list-ul text-slate-400"></i> Personal Autorizado
            </h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider border-b border-slate-100">
                            <th class="pb-3 pl-2">Usuario</th>
                            <th class="pb-3">Nombre</th>
                            <th class="pb-3">Rol / Permiso</th>
                            <th class="pb-3 text-center">Estado</th>
                            <th class="pb-3 text-right pr-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($personal)): ?>
                            <tr>
                                <td colspan="5" class="py-8 text-center text-xs text-slate-400 italic">No hay personal registrado para esta empresa.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($personal as $colab): ?>
                                <tr class="hover:bg-slate-50 transition-colors border-b border-slate-100">
                                    <td class="py-3.5 pl-2 font-bold text-xs text-[#1d2731] font-mono"><?= htmlspecialchars($colab['username']) ?></td>
                                    <td class="py-3.5 text-xs text-slate-600 font-medium uppercase"><?= htmlspecialchars($colab['name']) ?></td>
                                    <td class="py-3.5 text-xs">
                                        <span class="bg-slate-100 border border-slate-200 px-2.5 py-1 rounded text-[11px] font-bold text-slate-700">
                                            <?= htmlspecialchars($colab['rol']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 text-center">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#2ecc71] inline-block shadow-[0_0_8px_rgba(46,204,113,0.7)]" title="Activo"></span>
                                    </td>
                                    <td class="py-3.5 text-right pr-2">
                                        <form method="POST" class="inline" onsubmit="return confirm('¿Revocar el acceso a este colaborador?');">
                                            <input type="hidden" name="revocar_acceso" value="1">
                                            <input type="hidden" name="user_id_borrar" value="<?= $colab['id'] ?>">
                                            <button type="submit" class="text-slate-400 hover:text-rose-500 p-1.5 transition-colors" title="Revocar Acceso">
                                                <i class="fa-solid fa-trash text-xs"></i>
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

</div>

</main>
</div>
</div>
</body>
</html>