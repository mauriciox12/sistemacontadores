<?php
/**
 * ====================================================================
 * KONTIFY APP — Data Layer & Database Engine (Hybrid MySQL / SQLite)
 * ====================================================================
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $initialized = false;

    public static function getConnection(): PDO {
        global $pdo;
        
        if ($pdo === null) {
            die("Error crítico: El motor principal de conexión no está activo.");
        }

        if (!self::$initialized) {
            self::asegurarTablas($pdo);
            self::$initialized = true;
        }

        return $pdo;
    }

    private static function asegurarTablas(PDO $pdo): void {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isSqlite = ($driver === 'sqlite');

        try {
            if ($isSqlite) {
                // SQLite Table Definitions
                $pdo->exec("CREATE TABLE IF NOT EXISTS configuracion_despacho (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    nombre_despacho TEXT NOT NULL DEFAULT 'Despacho Rojas & Asociados',
                    tipo_entidad TEXT NOT NULL DEFAULT 'Firma',
                    cpc_numero TEXT NULL DEFAULT 'CPC-102948',
                    rif TEXT NULL DEFAULT 'J-50123891-2',
                    telefono TEXT NULL DEFAULT '+58 414 123 4567',
                    email TEXT NULL DEFAULT 'contacto@kontify.app',
                    direccion TEXT NULL DEFAULT 'Torre Financiera Empresarial, Piso 14',
                    datos_pago TEXT NULL DEFAULT 'Pago Móvil: Banesco 0134 - 04141234567 - J501238912 | Zelle: pagos@kontify.app',
                    mensaje_cobro TEXT NULL DEFAULT 'Estimado(a) {cliente}, le recordamos su honorario contable de \${monto_usd} USD correspondiente al período.',
                    actualizado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );");

                $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    empresa_id INTEGER DEFAULT 1,
                    razon_social TEXT NOT NULL,
                    rif TEXT NOT NULL UNIQUE,
                    tipo_contribuyente TEXT NOT NULL DEFAULT 'Ordinario',
                    telefono TEXT NOT NULL,
                    email TEXT NULL,
                    direccion TEXT NULL,
                    honorarios_usd REAL NOT NULL DEFAULT 0.00,
                    estatus TEXT NOT NULL DEFAULT 'al dia',
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    actualizado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );");

                $pdo->exec("CREATE TABLE IF NOT EXISTS servicios (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    nombre TEXT NOT NULL,
                    descripcion TEXT NULL,
                    tipo TEXT NOT NULL DEFAULT 'recurrente',
                    precio_sugerido_usd REAL NOT NULL DEFAULT 0.00,
                    activo INTEGER NOT NULL DEFAULT 1,
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );");

                $pdo->exec("CREATE TABLE IF NOT EXISTS servicios_cliente (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cliente_id INTEGER NOT NULL,
                    servicio_id INTEGER NULL,
                    nombre_servicio TEXT NOT NULL,
                    tipo TEXT NOT NULL DEFAULT 'recurrente',
                    monto_usd REAL NOT NULL DEFAULT 0.00,
                    estatus TEXT NOT NULL DEFAULT 'activo',
                    observaciones TEXT NULL,
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );");

                $pdo->exec("CREATE TABLE IF NOT EXISTS ingresos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    empresa_id INTEGER DEFAULT 1,
                    cliente_id INTEGER NOT NULL,
                    servicio_id INTEGER NULL,
                    nombre_servicio TEXT NOT NULL,
                    tipo_ingreso TEXT NOT NULL DEFAULT 'recurrente',
                    fecha TEXT NOT NULL,
                    monto REAL NOT NULL DEFAULT 0.00,
                    moneda TEXT NOT NULL DEFAULT 'USD',
                    tasa_cambio REAL NOT NULL DEFAULT 1.0000,
                    monto_equivalente REAL NOT NULL DEFAULT 0.00,
                    monto_usd REAL NOT NULL DEFAULT 0.00,
                    cuenta_receptora TEXT NOT NULL DEFAULT 'Banco Banesco',
                    metodo_pago TEXT NOT NULL DEFAULT 'Pago Móvil',
                    referencia TEXT NULL,
                    descripcion TEXT NULL,
                    comprobante_url TEXT NULL,
                    pago_honorario_id INTEGER NULL,
                    cotizacion_id INTEGER NULL,
                    estado TEXT NOT NULL DEFAULT 'cobrado',
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );");

                $pdo->exec("CREATE TABLE IF NOT EXISTS cotizaciones (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    empresa_id INTEGER DEFAULT 1,
                    numero_cotizacion TEXT NOT NULL UNIQUE,
                    cliente_id INTEGER NULL,
                    prospecto_nombre TEXT NULL,
                    prospecto_rif TEXT NULL,
                    prospecto_email TEXT NULL,
                    prospecto_telefono TEXT NULL,
                    servicios_json TEXT NOT NULL,
                    subtotal_usd REAL NOT NULL DEFAULT 0.00,
                    descuento_usd REAL NOT NULL DEFAULT 0.00,
                    tasa_bcv REAL NOT NULL DEFAULT 1.0000,
                    total_bcv REAL NOT NULL DEFAULT 0.00,
                    fecha TEXT NOT NULL,
                    validez_dias INTEGER NOT NULL DEFAULT 7,
                    observaciones TEXT NULL,
                    estado TEXT NOT NULL DEFAULT 'Borrador',
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );");

                $pdo->exec("CREATE TABLE IF NOT EXISTS documentos_escaneados (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cliente_id INTEGER NOT NULL,
                    tipo_documento TEXT NOT NULL,
                    mes_fiscal TEXT NOT NULL,
                    nombre_original TEXT NOT NULL,
                    ruta_archivo TEXT NOT NULL,
                    tamanio_bytes INTEGER NOT NULL DEFAULT 0,
                    mime_type TEXT NOT NULL,
                    ocr_status TEXT DEFAULT 'listo',
                    ocr_confianza REAL DEFAULT 98.5,
                    ocr_datos_json TEXT NULL,
                    subido_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );");

                $pdo->exec("CREATE TABLE IF NOT EXISTS gastos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    empresa_id INTEGER DEFAULT 1,
                    categoria TEXT NOT NULL DEFAULT 'Operativo',
                    descripcion TEXT NOT NULL,
                    monto_usd REAL NOT NULL DEFAULT 0.00,
                    monto_bs REAL NOT NULL DEFAULT 0.00,
                    tasa_cambio REAL NOT NULL DEFAULT 1.0000,
                    fecha TEXT NOT NULL,
                    metodo_pago TEXT NOT NULL DEFAULT 'Transferencia Bancaria',
                    proveedor TEXT NULL,
                    comprobante_url TEXT NULL,
                    estado TEXT NOT NULL DEFAULT 'pagado',
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );");

                $pdo->exec("CREATE TABLE IF NOT EXISTS comunicaciones_cliente (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cliente_id INTEGER NOT NULL,
                    tipo TEXT NOT NULL DEFAULT 'Nota Interna',
                    asunto TEXT NOT NULL,
                    detalle TEXT NULL,
                    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );");

                $pdo->exec("CREATE TABLE IF NOT EXISTS pagos_honorarios (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cliente_id INTEGER NOT NULL,
                    mes_periodo TEXT NOT NULL,
                    monto_usd REAL NOT NULL DEFAULT 0.00,
                    monto_bs REAL NOT NULL DEFAULT 0.00,
                    tasa_bcv REAL NOT NULL DEFAULT 1.0000,
                    metodo_pago TEXT NOT NULL DEFAULT 'Pago Móvil',
                    referencia TEXT NULL,
                    fecha_pago TEXT NOT NULL,
                    observaciones TEXT NULL,
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );");

                $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    username TEXT NOT NULL UNIQUE,
                    email TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    plan_id TEXT NOT NULL DEFAULT 'PRO',
                    valid_until TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );");
            } else {
                // MySQL Table Definitions
                $pdo->exec("CREATE TABLE IF NOT EXISTS `configuracion_despacho` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `nombre_despacho` VARCHAR(150) NOT NULL DEFAULT 'Despacho Rojas & Asociados',
                    `tipo_entidad` ENUM('Independiente', 'Firma') NOT NULL DEFAULT 'Firma',
                    `cpc_numero` VARCHAR(50) NULL DEFAULT 'CPC-102948',
                    `rif` VARCHAR(20) NULL DEFAULT 'J-50123891-2',
                    `telefono` VARCHAR(30) NULL DEFAULT '+58 414 123 4567',
                    `email` VARCHAR(100) NULL DEFAULT 'contacto@kontify.app',
                    `direccion` TEXT NULL,
                    `datos_pago` TEXT NULL,
                    `mensaje_cobro` TEXT NULL,
                    `actualizado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `clientes` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `empresa_id` INT DEFAULT 1,
                    `razon_social` VARCHAR(150) NOT NULL,
                    `rif` VARCHAR(15) NOT NULL UNIQUE,
                    `tipo_contribuyente` ENUM('Ordinario', 'Especial', 'Formal') NOT NULL DEFAULT 'Ordinario',
                    `telefono` VARCHAR(25) NOT NULL,
                    `email` VARCHAR(100) NULL,
                    `direccion` TEXT NULL,
                    `honorarios_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                    `estatus` ENUM('al dia', 'pendiente', 'mora', 'auditoria', 'pausado') NOT NULL DEFAULT 'al dia',
                    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `actualizado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_rif` (`rif`),
                    INDEX `idx_estatus` (`estatus`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `servicios` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `nombre` VARCHAR(150) NOT NULL,
                    `descripcion` TEXT NULL,
                    `tipo` ENUM('recurrente', 'extraordinario') NOT NULL DEFAULT 'recurrente',
                    `precio_sugerido_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                    `activo` TINYINT(1) NOT NULL DEFAULT 1,
                    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `servicios_cliente` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `cliente_id` INT NOT NULL,
                    `servicio_id` INT NULL,
                    `nombre_servicio` VARCHAR(150) NOT NULL,
                    `tipo` ENUM('recurrente', 'extraordinario') NOT NULL DEFAULT 'recurrente',
                    `monto_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                    `estatus` ENUM('activo', 'pausado', 'finalizado') NOT NULL DEFAULT 'activo',
                    `observaciones` TEXT NULL,
                    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_sc_cliente` (`cliente_id`, `estatus`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `ingresos` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `empresa_id` INT DEFAULT 1,
                    `cliente_id` INT NOT NULL,
                    `servicio_id` INT NULL,
                    `nombre_servicio` VARCHAR(150) NOT NULL,
                    `tipo_ingreso` ENUM('recurrente', 'extraordinario') NOT NULL DEFAULT 'recurrente',
                    `fecha` DATE NOT NULL,
                    `monto` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                    `moneda` VARCHAR(10) NOT NULL DEFAULT 'USD',
                    `tasa_cambio` DECIMAL(12, 4) NOT NULL DEFAULT 1.0000,
                    `monto_equivalente` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
                    `monto_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                    `cuenta_receptora` VARCHAR(100) NOT NULL DEFAULT 'Banco Banesco',
                    `metodo_pago` VARCHAR(50) NOT NULL DEFAULT 'Pago Móvil',
                    `referencia` VARCHAR(100) NULL,
                    `descripcion` TEXT NULL,
                    `comprobante_url` VARCHAR(255) NULL,
                    `pago_honorario_id` INT NULL,
                    `cotizacion_id` INT NULL,
                    `estado` ENUM('cobrado', 'pendiente', 'anulado') NOT NULL DEFAULT 'cobrado',
                    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_ingreso_fecha` (`fecha`),
                    INDEX `idx_ingreso_cliente` (`cliente_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `cotizaciones` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `empresa_id` INT DEFAULT 1,
                    `numero_cotizacion` VARCHAR(30) NOT NULL UNIQUE,
                    `cliente_id` INT NULL,
                    `prospecto_nombre` VARCHAR(150) NULL,
                    `prospecto_rif` VARCHAR(20) NULL,
                    `prospecto_email` VARCHAR(120) NULL,
                    `prospecto_telefono` VARCHAR(40) NULL,
                    `servicios_json` LONGTEXT NOT NULL,
                    `subtotal_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                    `descuento_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                    `tasa_bcv` DECIMAL(10, 4) NOT NULL DEFAULT 1.0000,
                    `total_bcv` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
                    `fecha` DATE NOT NULL,
                    `validez_dias` INT NOT NULL DEFAULT 7,
                    `observaciones` TEXT NULL,
                    `estado` ENUM('Borrador', 'Enviado', 'Aceptado', 'Rechazado') NOT NULL DEFAULT 'Borrador',
                    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `documentos_escaneados` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `cliente_id` INT NOT NULL,
                    `tipo_documento` VARCHAR(80) NOT NULL,
                    `mes_fiscal` VARCHAR(10) NOT NULL,
                    `nombre_original` VARCHAR(255) NOT NULL,
                    `ruta_archivo` VARCHAR(255) NOT NULL,
                    `tamanio_bytes` INT NOT NULL DEFAULT 0,
                    `mime_type` VARCHAR(100) NOT NULL,
                    `ocr_status` VARCHAR(30) DEFAULT 'listo',
                    `ocr_confianza` DECIMAL(5, 2) DEFAULT 98.50,
                    `ocr_datos_json` LONGTEXT NULL,
                    `subido_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_cliente_mes` (`cliente_id`, `mes_fiscal`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `gastos` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `empresa_id` INT DEFAULT 1,
                    `categoria` VARCHAR(80) NOT NULL DEFAULT 'Operativo',
                    `descripcion` VARCHAR(255) NOT NULL,
                    `monto_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                    `monto_bs` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
                    `tasa_cambio` DECIMAL(12, 4) NOT NULL DEFAULT 1.0000,
                    `fecha` DATE NOT NULL,
                    `metodo_pago` VARCHAR(80) NOT NULL DEFAULT 'Transferencia Bancaria',
                    `proveedor` VARCHAR(120) NULL,
                    `comprobante_url` VARCHAR(255) NULL,
                    `estado` ENUM('pagado', 'pendiente', 'anulado') NOT NULL DEFAULT 'pagado',
                    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_gasto_fecha` (`fecha`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `comunicaciones_cliente` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `cliente_id` INT NOT NULL,
                    `tipo` VARCHAR(50) NOT NULL DEFAULT 'Nota Interna',
                    `asunto` VARCHAR(180) NOT NULL,
                    `detalle` TEXT NULL,
                    `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_com_cliente` (`cliente_id`, `fecha`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `pagos_honorarios` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `cliente_id` INT NOT NULL,
                    `mes_periodo` VARCHAR(10) NOT NULL,
                    `monto_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                    `monto_bs` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
                    `tasa_bcv` DECIMAL(10, 4) NOT NULL DEFAULT 1.0000,
                    `metodo_pago` VARCHAR(50) NOT NULL DEFAULT 'Pago Móvil',
                    `referencia` VARCHAR(80) NULL,
                    `fecha_pago` DATE NOT NULL,
                    `observaciones` TEXT NULL,
                    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL,
                    `username` VARCHAR(100) NOT NULL UNIQUE,
                    `email` VARCHAR(150) NOT NULL UNIQUE,
                    `password_hash` VARCHAR(255) NOT NULL,
                    `plan_id` VARCHAR(50) NOT NULL DEFAULT 'PRO',
                    `valid_until` DATETIME NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }

            // Seed Initial Configuration
            $chkCfg = $pdo->query("SELECT COUNT(*) FROM configuracion_despacho")->fetchColumn();
            if ((int)$chkCfg === 0) {
                $pdo->exec("INSERT INTO configuracion_despacho (id, nombre_despacho, tipo_entidad, cpc_numero, rif, telefono, email, direccion, datos_pago, mensaje_cobro)
                    VALUES (1, 'Despacho Rojas & Asociados', 'Firma', 'CPC-102948', 'J-50123891-2', '+58 414 123 4567', 'contacto@kontify.app', 'Torre Financiera Empresarial, Piso 14', 'Pago Móvil: Banesco 0134 - 04141234567 - J501238912 | Zelle: pagos@kontify.app', 'Estimado(a) {cliente}, le recordamos su honorario contable mensual de \${monto_usd} USD.')");
            }

            // Seed Services Catalog
            $chkServ = $pdo->query("SELECT COUNT(*) FROM servicios")->fetchColumn();
            if ((int)$chkServ === 0) {
                $pdo->exec("INSERT INTO servicios (id, nombre, descripcion, tipo, precio_sugerido_usd) VALUES
                    (1, 'Declaración de IVA & Libros Fiscales', 'Gestión quincenal/mensual SENIAT, TXT de retenciones y libros fiscales.', 'recurrente', 60.00),
                    (2, 'Contabilidad General VEN-NIF', 'Asientos de diario, balance de comprobación y libros mayores.', 'recurrente', 85.00),
                    (3, 'Gestión de Nómina & Aportes Patronales', 'Recibos de pago LOTTT, aportes IVSS Tiuna, FAOV e INCES.', 'recurrente', 50.00),
                    (4, 'Declaración Definitiva & Estimada ISLR', 'Conciliación fiscal de rentas, anticipos y cédulas de trabajo.', 'recurrente', 75.00),
                    (5, 'Estados Financieros Auditados con Notas', 'Situación financiera y flujo de efectivo con visado CPC.', 'extraordinario', 150.00),
                    (6, 'Certificación de Ingresos Visada', 'Para trámites bancarios, visas o créditos comerciales con visado.', 'extraordinario', 45.00),
                    (7, 'Auditoría Tributaria Preventiva', 'Revisión exhaustiva para prevención de reparos y sanciones fiscales.', 'extraordinario', 220.00),
                    (8, 'Constitución de Empresa & Registro Mercantil', 'Redacción de acta constitutiva, inventario de apertura y RNC.', 'extraordinario', 180.00)");
            }

            // Seed High-Profile Clients
            $chkCli = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
            if ((int)$chkCli === 0) {
                $pdo->exec("INSERT INTO clientes (id, razon_social, rif, tipo_contribuyente, telefono, email, direccion, honorarios_usd, estatus) VALUES
                    (1, 'Inversiones El Ávila, C.A.', 'J-12345678-0', 'Especial', '+58 414 123 4567', 'finanzas@elavila.com', 'Av. Francisco de Miranda, Edif. Parque Cristal', 380.00, 'al dia'),
                    (2, 'Nexus Tech Solutions, S.A.S.', 'J-29876543-2', 'Ordinario', '+58 424 987 6543', 'billing@nexustech.io', 'Centro San Ignacio, Torre Copernico', 650.00, 'al dia'),
                    (3, 'Grupo Gastronómico Alto, C.A.', 'J-31415926-5', 'Especial', '+58 416 999 8877', 'admin@grupoalto.com', 'Las Mercedes, Calle Madrid', 520.00, 'pendiente'),
                    (4, 'Logística & Transporte Continental', 'J-40582910-1', 'Especial', '+58 412 333 4455', 'operaciones@continental.com', 'Zona Industrial La Yaguara', 450.00, 'al dia'),
                    (5, 'Dra. Sofía Mendoza Consultoría', 'V-18459201-9', 'Formal', '+58 414 777 8899', 'contacto@mendozamed.com', 'Chacao, Centro Médico Caracas', 220.00, 'mora'),
                    (6, 'Constructora Metro Urbana, S.A.', 'J-50192837-4', 'Especial', '+58 424 555 1122', 'compras@metrourbana.com', 'Boleíta Norte, Calle 3', 780.00, 'al dia')");

                // Seed Client Contracted Services
                $pdo->exec("INSERT INTO servicios_cliente (cliente_id, servicio_id, nombre_servicio, tipo, monto_usd, estatus, observaciones) VALUES
                    (1, 1, 'Declaración de IVA & Libros Fiscales', 'recurrente', 120.00, 'activo', 'Contribuyente especial quincenal'),
                    (1, 2, 'Contabilidad General VEN-NIF', 'recurrente', 180.00, 'activo', 'Cierre contable mensual'),
                    (1, 3, 'Gestión de Nómina & Aportes Patronales', 'recurrente', 80.00, 'activo', 'Nómina de 14 empleados'),
                    (2, 2, 'Contabilidad General VEN-NIF', 'recurrente', 350.00, 'activo', 'Incluye facturación multimoneda'),
                    (2, 4, 'Declaración Definitiva & Estimada ISLR', 'recurrente', 300.00, 'activo', 'Anticipos mensuales de ISLR'),
                    (3, 1, 'Declaración de IVA & Libros Fiscales', 'recurrente', 200.00, 'activo', 'Quincenas 1 y 2'),
                    (3, 3, 'Gestión de Nómina & Aportes Patronales', 'recurrente', 120.00, 'activo', 'Personal de cocina y salón'),
                    (3, 2, 'Contabilidad General VEN-NIF', 'recurrente', 200.00, 'activo', 'Conciliación bancaria semanal'),
                    (4, 1, 'Declaración de IVA & Libros Fiscales', 'recurrente', 150.00, 'activo', 'Gestión de fletes e inventario'),
                    (4, 2, 'Contabilidad General VEN-NIF', 'recurrente', 300.00, 'activo', 'Auditoría continua'),
                    (5, 2, 'Contabilidad General VEN-NIF', 'recurrente', 150.00, 'activo', 'Honorarios profesionales'),
                    (5, 4, 'Declaración Definitiva & Estimada ISLR', 'recurrente', 70.00, 'activo', 'Declaración I.S.L.R. anual'),
                    (6, 1, 'Declaración de IVA & Libros Fiscales', 'recurrente', 250.00, 'activo', 'Obras y subcontratistas'),
                    (6, 2, 'Contabilidad General VEN-NIF', 'recurrente', 400.00, 'activo', 'Cuentas por cobrar en divisa'),
                    (6, 5, 'Estados Financieros Auditados con Notas', 'extraordinario', 130.00, 'activo', 'Licitación pública')");

                // Seed Incomes
                $pdo->exec("INSERT INTO ingresos (empresa_id, cliente_id, servicio_id, nombre_servicio, tipo_ingreso, fecha, monto, moneda, tasa_cambio, monto_equivalente, monto_usd, cuenta_receptora, metodo_pago, referencia, descripcion, estado) VALUES
                    (1, 1, 2, 'Contabilidad General y Declaración IVA', 'recurrente', '2026-09-02', 380.00, 'USD', 65.50, 24890.00, 380.00, 'Banco Banesco (VES)', 'Pago Móvil', 'PM-992144', 'Honorario mensual Septiembre 2026', 'cobrado'),
                    (1, 2, 2, 'Retainer Contable y Fiscal SaaS', 'recurrente', '2026-09-04', 650.00, 'USD', 1.00, 650.00, 650.00, 'Zelle Bank of America', 'Zelle', 'ZEL-881923', 'Facturación quincenal tecnología', 'cobrado'),
                    (1, 4, 1, 'Declaración IVA Quincenal & Nómina', 'recurrente', '2026-09-08', 450.00, 'USD', 65.50, 29475.00, 450.00, 'Banco Mercantil', 'Transferencia', 'TRF-774120', 'Honorario Quincena 1 Septiembre', 'cobrado'),
                    (1, 6, 2, 'Contabilidad y Estados de Flujo', 'recurrente', '2026-09-12', 780.00, 'USD', 65.50, 51090.00, 780.00, 'Banco Banesco (VES)', 'Pago Móvil', 'PM-331092', 'Pago anticipado de honorarios', 'cobrado'),
                    (1, 1, 6, 'Certificación de Ingresos Visada', 'extraordinario', '2026-09-15', 80.00, 'USD', 65.50, 5240.00, 80.00, 'Efectivo USD', 'Efectivo', 'EF-0012', 'Certificación para crédito bancario', 'cobrado'),
                    (1, 3, 1, 'Honorario Mensual Gastronomía', 'recurrente', '2026-09-20', 520.00, 'USD', 65.50, 34060.00, 520.00, 'Banco Banesco', 'Transferencia', 'PEND-001', 'Pendiente por conciliación', 'pendiente'),
                    (1, 5, 2, 'Asesoría Fiscal Médica', 'recurrente', '2026-08-28', 220.00, 'USD', 64.80, 14256.00, 220.00, 'Banco Mercantil', 'Pago Móvil', 'PM-112094', 'Honorario Agosto 2026', 'cobrado'),
                    (1, 2, 7, 'Auditoría Tributaria Preventiva', 'extraordinario', '2026-08-15', 400.00, 'USD', 1.00, 400.00, 400.00, 'Zelle Bank of America', 'Zelle', 'ZEL-440192', 'Auditoría de prevención fiscal', 'cobrado')");

                // Seed Quotes
                $servJson1 = json_encode([
                    ["nombre" => "Contabilidad General VEN-NIF", "precio" => 250, "cantidad" => 1],
                    ["nombre" => "Declaración de IVA & Libros Fiscales", "precio" => 120, "cantidad" => 1],
                    ["nombre" => "Gestión de Nómina (1-10 empleados)", "precio" => 80, "cantidad" => 1]
                ]);
                $servJson2 = json_encode([
                    ["nombre" => "Auditoría Fiscal & Revisión de Libros", "precio" => 350, "cantidad" => 1],
                    ["nombre" => "Estados Financieros Auditados con Notas", "precio" => 200, "cantidad" => 1]
                ]);
                $servJson3 = json_encode([
                    ["nombre" => "Constitución de Empresa & Registro Mercantil", "precio" => 280, "cantidad" => 1],
                    ["nombre" => "Inscripción en SENIAT, IVSS y FAOV", "precio" => 120, "cantidad" => 1]
                ]);

                $stmtCot = $pdo->prepare("INSERT INTO cotizaciones (empresa_id, numero_cotizacion, cliente_id, prospecto_nombre, prospecto_rif, prospecto_email, prospecto_telefono, servicios_json, subtotal_usd, descuento_usd, tasa_bcv, total_bcv, fecha, validez_dias, observaciones, estado) VALUES
                    (1, 'COT-2026-042', 1, 'Inversiones El Ávila, C.A.', 'J-12345678-0', 'finanzas@elavila.com', '+58 414 123 4567', ?, 450.00, 50.00, 65.50, 26200.00, '2026-09-18', 7, 'Plan Integral Pyme con descuento por pronto pago.', 'Aceptado'),
                    (1, 'COT-2026-043', 3, 'Grupo Gastronómico Alto, C.A.', 'J-31415926-5', 'admin@grupoalto.com', '+58 416 999 8877', ?, 550.00, 0.00, 65.50, 36025.00, '2026-09-21', 5, 'Propuesta de auditoría y balance general 2026.', 'Enviado'),
                    (1, 'COT-2026-044', NULL, 'Distribuidora FarmaOriente, C.A.', 'J-51203948-1', 'gerencia@farmaoriente.com', '+58 424 111 2233', ?, 400.00, 20.00, 65.50, 24890.00, '2026-09-23', 10, 'Apertura de sucursal comercial en Caracas.', 'Borrador')");
                $stmtCot->execute([$servJson1, $servJson2, $servJson3]);

                // Seed Document Vault
                $pdo->exec("INSERT INTO documentos_escaneados (cliente_id, tipo_documento, mes_fiscal, nombre_original, ruta_archivo, tamanio_bytes, mime_type) VALUES
                    (1, 'Declaración SENIAT (Certificado)', '2026-09', 'Certificado_IVA_Agosto_ElAvila.pdf', 'uploads/clientes/1/Certificado_IVA_Agosto_ElAvila.pdf', 184500, 'application/pdf'),
                    (1, 'Comprobante Retención IVA', '2026-09', 'Retencion_IVA_Avila_0049.pdf', 'uploads/clientes/1/Retencion_IVA_Avila_0049.pdf', 95200, 'application/pdf'),
                    (2, 'Estado de Cuenta Bancario', '2026-09', 'Banesco_Nexus_Agosto2026.pdf', 'uploads/clientes/2/Banesco_Nexus_Agosto2026.pdf', 312000, 'application/pdf'),
                    (2, 'Declaración SENIAT (Certificado)', '2026-08', 'Certificado_ISLR_Nexus_2026.pdf', 'uploads/clientes/2/Certificado_ISLR_Nexus_2026.pdf', 245000, 'application/pdf'),
                    (3, 'Factura de Compra', '2026-09', 'Factura_Proveedores_GrupoAlto.jpg', 'uploads/clientes/3/Factura_Proveedores_GrupoAlto.jpg', 450100, 'image/jpeg'),
                    (4, 'Planilla IVSS / FAOV / INCES', '2026-09', 'Solvencia_Laboral_Continental.pdf', 'uploads/clientes/4/Solvencia_Laboral_Continental.pdf', 120400, 'application/pdf'),
                    (6, 'Documento Legal / RIF Actualizado', '2026-09', 'RIF_Vigente_MetroUrbana_2026.pdf', 'uploads/clientes/6/RIF_Vigente_MetroUrbana_2026.pdf', 178900, 'application/pdf')");

                // Seed Client Communications & Notes
                $pdo->exec("INSERT INTO comunicaciones_cliente (cliente_id, tipo, asunto, detalle) VALUES
                    (1, 'Reunión', 'Cierre contable Q3 y planificación fiscal', 'Se acordó con el director financiero adelantar la revisión de inventarios para evitar retenciones adicionales de ISLR.'),
                    (1, 'WhatsApp', 'Confirmación de pago de honorarios', 'Comprobante recibido vía WhatsApp por $380 USD. Conciliado y cargado en el sistema.'),
                    (2, 'Nota Interna', 'Revisión de facturación multimoneda', 'El cliente comenzó a emitir notas de débito en divisa. Supervisar tipo de cambio oficial en el libro de ventas.'),
                    (3, 'Llamada', 'Aviso de vencimiento quincenal', 'Se contactó al gerente de administración para solicitar los comprobantes de retención pendientes del mes.'),
                    (5, 'Nota Interna', 'Honorarios pendientes de regularización', 'El cliente tiene pendiente el pago de Agosto. Se coordinó recordatorio automático por WhatsApp.')");
            }

            // Migración no destructiva de columnas IA OCR en documentos_escaneados
            try { $pdo->exec("ALTER TABLE documentos_escaneados ADD COLUMN ocr_status TEXT DEFAULT 'listo'"); } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE documentos_escaneados ADD COLUMN ocr_confianza REAL DEFAULT 98.5"); } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE documentos_escaneados ADD COLUMN ocr_datos_json TEXT NULL"); } catch (Exception $e) {}

            // Seed de Gastos Operativos del Despacho si está vacía
            $chkGastos = 0;
            try {
                $chkGastos = (int)$pdo->query("SELECT COUNT(*) FROM gastos")->fetchColumn();
            } catch (Exception $e) {}

            if ($chkGastos === 0) {
                try {
                    $pdo->exec("INSERT INTO gastos (empresa_id, categoria, descripcion, monto_usd, monto_bs, tasa_cambio, fecha, metodo_pago, proveedor, estado) VALUES
                        (1, 'Software & Cloud', 'Suscripción Servidor Cloud & Kontify Pro', 65.00, 4257.50, 65.50, '2026-09-05', 'Tarjeta Internacional', 'Amazon Web Services / Kontify', 'pagado'),
                        (1, 'Nómina Asistentes', 'Honorarios Asistente Contable Quincena 1', 250.00, 16375.00, 65.50, '2026-09-15', 'Pago Móvil Banesco', 'Lic. Andrea Méndez', 'pagado'),
                        (1, 'Servicios Oficina', 'Internet Fibra Óptica Empresarial 300Mbps', 55.00, 3602.50, 65.50, '2026-09-10', 'Transferencia Mercantil', 'NetUno Fibra', 'pagado'),
                        (1, 'Papelería & Envíos', 'Timbres Fiscales y Hojas Notariadas CPC', 40.00, 2620.00, 65.50, '2026-09-12', 'Efectivo USD', 'Colegio de Contadores Públicos', 'pagado')");
                } catch (Exception $e) {}
            }
        } catch (Exception $e) {
            error_log("Aviso asegurar tablas: " . $e->getMessage());
        }
    }
}

function getDB(): PDO {
    return Database::getConnection();
}

if (!function_exists('obtenerConfiguracionDespacho')) {
    function obtenerConfiguracionDespacho($db) {
        if (!$db) return [
            'nombre_despacho' => 'Despacho Rojas & Asociados',
            'tipo_entidad' => 'Firma',
            'cpc_numero' => 'CPC-102948',
            'rif' => 'J-50123891-2',
            'telefono' => '+58 414 123 4567',
            'email' => 'contacto@kontify.app',
            'direccion' => 'Torre Financiera Empresarial, Piso 14',
            'datos_pago' => 'Pago Móvil: Banesco 0134 - 04141234567 - J501238912 | Zelle: pagos@kontify.app',
            'mensaje_cobro' => 'Estimado(a) {cliente}, le recordamos su honorario contable de ${monto_usd} USD correspondiente al período.'
        ];
        try {
            $stmt = $db->query("SELECT * FROM configuracion_despacho LIMIT 1");
            $cfg = $stmt ? $stmt->fetch() : null;
            if ($cfg) return $cfg;
        } catch (Exception $e) {}
        return [
            'nombre_despacho' => 'Despacho Rojas & Asociados',
            'tipo_entidad' => 'Firma',
            'cpc_numero' => 'CPC-102948',
            'rif' => 'J-50123891-2',
            'telefono' => '+58 414 123 4567',
            'email' => 'contacto@kontify.app',
            'direccion' => 'Torre Financiera Empresarial, Piso 14',
            'datos_pago' => 'Pago Móvil: Banesco 0134 - 04141234567 - J501238912 | Zelle: pagos@kontify.app',
            'mensaje_cobro' => 'Estimado(a) {cliente}, le recordamos su honorario contable de ${monto_usd} USD correspondiente al período.'
        ];
    }
}

if (!function_exists('obtenerUsuarioActual')) {
    function obtenerUsuarioActual($db = null) {
        return [
            'id' => $_SESSION['user_id'] ?? 1,
            'name' => $_SESSION['user_nombre'] ?? 'Mauricio Rojas',
            'username' => 'mauricio',
            'email' => $_SESSION['user_email'] ?? 'mauricio@nexusgestions.online',
            'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=256&q=80',
            'rol' => $_SESSION['user_rol'] ?? 'Lead Partner / CPA',
            'plan_id' => 'ENTERPRISE'
        ];
    }
}

if (!function_exists('verificarSuscripcionActiva')) {
    function verificarSuscripcionActiva($user, $db = null) {
        return [
            'activa' => true,
            'plan' => 'Firm Operating System ($199/mes)',
            'fecha_vencimiento' => date('Y-m-d', strtotime('+1 year'))
        ];
    }
}

if (!function_exists('obtenerPlanesSaaS')) {
    function obtenerPlanesSaaS() {
        return [
            ['id' => 'SOLO', 'nombre' => 'Solo CPA', 'precio' => 49],
            ['id' => 'PRO', 'nombre' => 'Despacho Pro', 'precio' => 99],
            ['id' => 'ENTERPRISE', 'nombre' => 'Firm Enterprise', 'precio' => 199]
        ];
    }
}
?>
