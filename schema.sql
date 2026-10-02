-- ==========================================================
-- KONTIFY APP - Base de Datos MySQL
-- Esquema para Gestión Administrativa y Fiscal de Contadores
-- ==========================================================

-- 1. Tabla: clientes
CREATE TABLE IF NOT EXISTS `clientes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `razon_social` VARCHAR(150) NOT NULL,
    `rif` VARCHAR(15) NOT NULL UNIQUE,
    `tipo_contribuyente` ENUM('Ordinario', 'Especial', 'Formal') NOT NULL DEFAULT 'Ordinario',
    `telefono` VARCHAR(25) NOT NULL,
    `email` VARCHAR(100) NULL,
    `direccion` TEXT NULL,
    `honorarios_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `estatus` ENUM('al dia', 'pendiente', 'mora') NOT NULL DEFAULT 'al dia',
    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_rif` (`rif`),
    INDEX `idx_estatus` (`estatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla: cotizaciones
CREATE TABLE IF NOT EXISTS `cotizaciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `numero_cotizacion` VARCHAR(20) NOT NULL UNIQUE,
    `cliente_id` INT NULL,
    `prospecto_nombre` VARCHAR(150) NULL,
    `prospecto_rif` VARCHAR(15) NULL,
    `servicios_json` JSON NOT NULL,
    `subtotal_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `tasa_bcv` DECIMAL(10, 4) NOT NULL DEFAULT 1.0000,
    `total_bcv` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `fecha` DATE NOT NULL,
    `validez_dias` INT NOT NULL DEFAULT 7,
    `observaciones` TEXT NULL,
    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla: documentos_escaneados
CREATE TABLE IF NOT EXISTS `documentos_escaneados` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT NOT NULL,
    `tipo_documento` VARCHAR(80) NOT NULL,
    `mes_fiscal` VARCHAR(10) NOT NULL, -- Formato: YYYY-MM
    `nombre_original` VARCHAR(255) NOT NULL,
    `ruta_archivo` VARCHAR(255) NOT NULL,
    `tamanio_bytes` INT NOT NULL DEFAULT 0,
    `mime_type` VARCHAR(100) NOT NULL,
    `subido_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_cliente_mes` (`cliente_id`, `mes_fiscal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabla: configuracion_despacho
CREATE TABLE IF NOT EXISTS `configuracion_despacho` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre_despacho` VARCHAR(150) NOT NULL DEFAULT 'Mi Despacho Contable',
    `tipo_entidad` ENUM('Independiente', 'Firma') NOT NULL DEFAULT 'Independiente',
    `cpc_numero` VARCHAR(50) NULL DEFAULT '',
    `rif` VARCHAR(20) NULL DEFAULT '',
    `telefono` VARCHAR(30) NULL DEFAULT '',
    `email` VARCHAR(100) NULL DEFAULT '',
    `direccion` TEXT NULL,
    `datos_pago` TEXT NULL,
    `mensaje_cobro` TEXT NULL,
    `actualizado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla: pagos_honorarios
CREATE TABLE IF NOT EXISTS `pagos_honorarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT NOT NULL,
    `mes_periodo` VARCHAR(10) NOT NULL, -- Formato: YYYY-MM
    `monto_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `monto_bs` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `tasa_bcv` DECIMAL(10, 4) NOT NULL DEFAULT 1.0000,
    `metodo_pago` VARCHAR(50) NOT NULL DEFAULT 'Pago Móvil',
    `referencia` VARCHAR(80) NULL,
    `fecha_pago` DATE NOT NULL,
    `observaciones` TEXT NULL,
    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_pago_cliente` (`cliente_id`, `fecha_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabla: tareas_cliente
CREATE TABLE IF NOT EXISTS `tareas_cliente` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT NOT NULL,
    `titulo` VARCHAR(180) NOT NULL,
    `descripcion` TEXT NULL,
    `prioridad` ENUM('Baja', 'Media', 'Alta') NOT NULL DEFAULT 'Media',
    `fecha_vencimiento` DATE NULL,
    `estado` ENUM('Pendiente', 'En proceso', 'Completada') NOT NULL DEFAULT 'Pendiente',
    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_tarea_cliente` (`cliente_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabla: gestiones_cobro
CREATE TABLE IF NOT EXISTS `gestiones_cobro` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT NOT NULL,
    `tipo` VARCHAR(50) NOT NULL DEFAULT 'WhatsApp',
    `motivo` VARCHAR(150) NOT NULL DEFAULT 'Recordatorio de Cobro',
    `resultado` VARCHAR(80) NOT NULL DEFAULT 'Enviado',
    `monto_cobrado_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `observacion` TEXT NULL,
    `fecha_gestion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_gestion_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tabla: users (SaaS & Suscripciones)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `plan_id` VARCHAR(50) NOT NULL DEFAULT 'FREE',
    `valid_until` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Tabla: reported_payments (Reportes de pago manuales en Venezuela)
CREATE TABLE IF NOT EXISTS `reported_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `plan_requested` VARCHAR(50) NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `reference` VARCHAR(100) NOT NULL,
    `receipt_data` LONGTEXT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Tabla: servicios (Catálogo general de servicios del despacho)
CREATE TABLE IF NOT EXISTS `servicios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `tipo` ENUM('recurrente', 'extraordinario') NOT NULL DEFAULT 'recurrente',
    `precio_sugerido_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Tabla: servicios_cliente (Servicios contratados/activos por cliente)
CREATE TABLE IF NOT EXISTS `servicios_cliente` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT NOT NULL,
    `servicio_id` INT NULL,
    `nombre_servicio` VARCHAR(150) NOT NULL,
    `tipo` ENUM('recurrente', 'extraordinario') NOT NULL DEFAULT 'recurrente',
    `monto_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `estatus` ENUM('activo', 'pausado', 'finalizado') NOT NULL DEFAULT 'activo',
    `observaciones` TEXT NULL,
    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_sc_cliente` (`cliente_id`, `estatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Tabla: ingresos (Registro unificado financiero y comercial)
CREATE TABLE IF NOT EXISTS `ingresos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
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
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_ingreso_fecha` (`fecha`),
    INDEX `idx_ingreso_cliente` (`cliente_id`),
    INDEX `idx_ingreso_tipo` (`tipo_ingreso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Tabla: comunicaciones_cliente (Comunicaciones y notas con el cliente)
CREATE TABLE IF NOT EXISTS `comunicaciones_cliente` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT NOT NULL,
    `tipo` ENUM('WhatsApp', 'Llamada', 'Correo', 'Reunión', 'Nota Interna') NOT NULL DEFAULT 'WhatsApp',
    `asunto` VARCHAR(180) NOT NULL,
    `detalle` TEXT NULL,
    `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_com_cliente` (`cliente_id`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Datos de prueba iniciales para arrancar de inmediato
-- ----------------------------------------------------------
INSERT INTO `clientes` (`razon_social`, `rif`, `tipo_contribuyente`, `telefono`, `email`, `honorarios_usd`, `estatus`)
VALUES 
('Inversiones El Ávila, C.A.', 'J-12345678-0', 'Especial', '04141234567', 'avila@ejemplo.com', 120.00, 'al dia'),
('Comercializadora Los Andes, S.A.', 'J-29876543-2', 'Ordinario', '04249876543', 'losandes@ejemplo.com', 80.00, 'pendiente'),
('Pedro Pérez Consultores, F.P.', 'V-14567890-4', 'Ordinario', '04125556677', 'pedroperez@ejemplo.com', 50.00, 'mora'),
('Farmacia La Milagrosa, C.A.', 'J-31415926-5', 'Especial', '04169998877', 'farmacia@ejemplo.com', 150.00, 'al dia')
ON DUPLICATE KEY UPDATE id=id;

INSERT INTO `configuracion_despacho` (`id`, `nombre_despacho`, `tipo_entidad`, `cpc_numero`, `rif`, `telefono`, `email`, `direccion`, `datos_pago`, `mensaje_cobro`)
VALUES (1, 'Mi Despacho Contable', 'Independiente', 'CPC-123456', 'V-12345678-0', '04141234567', 'contacto@midespacho.com', 'Caracas, Venezuela', 'Pago Móvil: Banco Banesco (0134) - 04141234567 - V-12345678', 'Estimado(a) {cliente}, le recordamos su honorario contable mensual de ${monto_usd} USD (≈ Bs. {monto_bs} a tasa BCV {tasa_bcv}). Agradecemos su pago oportuno.')
ON DUPLICATE KEY UPDATE id=id;

INSERT INTO `users` (`id`, `name`, `username`, `email`, `password_hash`, `plan_id`, `valid_until`)
VALUES (1, 'Mauricio Rojas', 'mauricio', 'mauricio@nexusgestions.online', '$2y$10$wKqK6L8iX3lCq4ZkR.q7be5F5oBf4M2E4G3j.N6u8R1H7m5P4k.e', 'PRO', DATE_ADD(NOW(), INTERVAL 1 YEAR))
ON DUPLICATE KEY UPDATE id=1;

-- Catálogo de servicios fiscales y contables venezolanos
INSERT INTO `servicios` (`id`, `nombre`, `descripcion`, `tipo`, `precio_sugerido_usd`) VALUES
(1, 'Declaración de IVA y Libros de Compras/Ventas', 'Gestión quincenal o mensual SENIAT, TXT de retenciones y libros fiscales.', 'recurrente', 40.00),
(2, 'Contabilidad General y Libros Principales', 'Asientos de diario, mayor e inventario conforme a VEN-NIF.', 'recurrente', 50.00),
(3, 'Gestión de Nómina y Obligaciones Laborales', 'Recibos LOTTT, aportes IVSS Tiuna, FAOV Banavih e INCES.', 'recurrente', 35.00),
(4, 'Declaración Estimada y Definitiva de ISLR', 'Conciliación fiscal de rentas y anticipos de ISLR.', 'recurrente', 45.00),
(5, 'Elaboración de Balances y Estados Financieros', 'Estado de situación financiera y resultados con notas VEN-NIF.', 'extraordinario', 80.00),
(6, 'Certificación de Ingresos Visada', 'Para trámites bancarios, visas o créditos comerciales con visado CPC.', 'extraordinario', 30.00),
(7, 'Auditoría Fiscal & Revisión de Libros', 'Revisión exhaustiva para prevención de sanciones y multas SENIAT.', 'extraordinario', 120.00),
(8, 'Trámites de Registro Mercantil / RNC', 'Actas de asamblea, constitución de empresas y actualización RNC.', 'extraordinario', 100.00)
ON DUPLICATE KEY UPDATE id=id;



