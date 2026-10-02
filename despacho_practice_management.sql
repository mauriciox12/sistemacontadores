-- ====================================================================
-- KONTIFY PRACTICE MANAGEMENT / GESTIÓN DE DESPACHO
-- Módulo de Rentabilidad Interna, Costeo Horario y Blindaje Jurídico
-- Adaptado a Normativa Profesional Venezolana (FCCPV, VEN-NIF, NIA 210, SEC-7, C.Comercio)
-- ====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------------------
-- 1. TABLA: despacho_jerarquias
-- Matriz de Roles, Tarifas de Costo Nómina y Honorarios Facturables
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `despacho_jerarquias` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT NOT NULL DEFAULT 1,
    `rol_nombre` VARCHAR(80) NOT NULL, -- Socio Director, Gerente de Auditoría, Contador Senior, Asistente Contable, Auxiliar
    `nivel_jerarquico` TINYINT UNSIGNED NOT NULL DEFAULT 1, -- 1=Auxiliar, 5=Socio Director
    `tarifa_costo_hora_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00, -- Costo laboral base/hora (sueldo + cargas patronales)
    `tarifa_cobro_hora_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00, -- Tarifa estándar a facturar al cliente
    `descripcion` VARCHAR(255) NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_jerarquia_empresa` (`empresa_id`, `activo`),
    INDEX `idx_jerarquia_nivel` (`nivel_jerarquico`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 2. TABLA: despacho_costos_fijos
-- Estructura de Gastos Generales y Operativos del Despacho
-- Utilizada para el prorrateo de Overhead (Costo Indirecto / Hora Operativa)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `despacho_costos_fijos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT NOT NULL DEFAULT 1,
    `categoria` ENUM(
        'Alquiler de Oficina',
        'Servicios e Internet',
        'Licencias y Software',
        'Nomina Administrativa',
        'Colegiatura y Tributos Municipales',
        'Depreciacion y Mantenimiento',
        'Otros Costos Operativos'
    ) NOT NULL DEFAULT 'Alquiler de Oficina',
    `concepto` VARCHAR(180) NOT NULL,
    `monto_mensual_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `frecuencia` ENUM('Mensual', 'Trimestral', 'Semestral', 'Anual') NOT NULL DEFAULT 'Mensual',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `fecha_inicio` DATE NOT NULL,
    `observaciones` TEXT NULL,
    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cf_empresa` (`empresa_id`, `activo`),
    INDEX `idx_cf_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 3. TABLA: despacho_contratos
-- Blindaje Legal y Cartas de Encargo Normativas (NIA 210, SEC-7, VEN-NIF, Comisario)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `despacho_contratos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT NOT NULL DEFAULT 1,
    `cliente_id` INT NOT NULL,
    `tipo_plantilla` ENUM(
        'SEC_7_RECURRENTE',      -- Servicios Contables Continuos (Deslinde penal/fiscal FCCPV)
        'NIA_210_AUDITORIA',     -- Carta de Encargo de Auditoría / Revisión Limitada
        'NIIF_PYMES_SECCION23',  -- Contrato de Reconocimiento de Ingresos y Obligaciones de Desempeño
        'COMISARIO_MERCANTIL',   -- Designación y Aceptación de Comisario (Arts. 287/309 Cód. Comercio)
        'ASESORIA_EXTRAORDINARIA' -- Proyectos especiales o litigios tributarios SENIAT
    ) NOT NULL DEFAULT 'SEC_7_RECURRENTE',
    `numero_contrato` VARCHAR(50) NOT NULL,
    `titulo` VARCHAR(200) NOT NULL,
    `alcance_servicio` LONGTEXT NOT NULL,
    `honorarios_pactados_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `frecuencia_pago` ENUM('Mensual', 'Quincenal', 'Por Hito', 'Anticipo y Finiquito') NOT NULL DEFAULT 'Mensual',
    `horas_incluidas_mensuales` DECIMAL(6, 2) NOT NULL DEFAULT 0.00, -- Horas cubiertas en el fee base
    `tarifa_hora_excedente_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `clausula_rescision` TEXT NOT NULL,
    `responsabilidades_cliente` TEXT NOT NULL,
    `normativa_aplicable` VARCHAR(150) NOT NULL DEFAULT 'SEC-7 FCCPV / VEN-NIF PYMES',
    `estado` ENUM('Borrador', 'Emitido', 'Firmado', 'Vencido', 'Rescindido') NOT NULL DEFAULT 'Borrador',
    `fecha_emision` DATE NOT NULL,
    `fecha_vencimiento` DATE NULL,
    `cpc_firmante` VARCHAR(40) NULL,
    `archivo_digital_url` VARCHAR(255) NULL,
    `metadata_dinamica` JSON NULL,
    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE INDEX `idx_contrato_num` (`empresa_id`, `numero_contrato`),
    INDEX `idx_contrato_cliente` (`cliente_id`, `estado`),
    INDEX `idx_contrato_tipo` (`tipo_plantilla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 4. TABLA: despacho_horas
-- Núcleo de Costeo de Horas-Hombre, Time-Tracking y Anexos Facturables
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `despacho_horas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT NOT NULL DEFAULT 1,
    `cliente_id` INT NOT NULL,
    `usuario_id` INT NOT NULL, -- Usuario/Colaborador que ejecutó la labor (users.id)
    `jerarquia_id` INT NOT NULL, -- Rol al momento del registro
    `contrato_id` INT NULL, -- Contrato al que imputa (si aplica)
    `fecha` DATE NOT NULL,
    `horas` DECIMAL(6, 2) NOT NULL DEFAULT 1.00,
    `tipo_tarea` ENUM('Ordinaria', 'Extraordinaria') NOT NULL DEFAULT 'Ordinaria',
    `sujeta_contrato` TINYINT(1) NOT NULL DEFAULT 1, -- 1=Incluida en el fee mensual, 0=Facturable aparte
    `es_facturable` TINYINT(1) NOT NULL DEFAULT 1,   -- 1=Genera cobro, 0=No facturable (capacitación/subsanación)
    `facturado` TINYINT(1) NOT NULL DEFAULT 0,       -- 1=Ya se emitió anexo/factura
    `tarifa_costo_aplicada_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00, -- Snapshot costo hora
    `tarifa_cobro_aplicada_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00, -- Snapshot precio hora
    `costo_overhead_aplicado_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00, -- Overhead prorrateado absorbido
    `gastos_directos_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00, -- Papelería especial, traslados SENIAT/Alcaldía, aranceles
    `descripcion` TEXT NOT NULL,
    `origen_registro` ENUM('Cronometro', 'Manual', 'Importacion') NOT NULL DEFAULT 'Manual',
    `hora_inicio` TIME NULL,
    `hora_fin` TIME NULL,
    `aprobado_por` INT NULL, -- Aprobación por Gerente/Socio
    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`jerarquia_id`) REFERENCES `despacho_jerarquias`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`contrato_id`) REFERENCES `despacho_contratos`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_horas_cliente_fecha` (`cliente_id`, `fecha`),
    INDEX `idx_horas_usuario` (`usuario_id`, `fecha`),
    INDEX `idx_horas_facturacion` (`cliente_id`, `sujeta_contrato`, `es_facturable`, `facturado`),
    INDEX `idx_horas_tarea` (`tipo_tarea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 5. TABLA: despacho_aliados
-- Directorio de Aliados Multidisciplinarios para Proyectos y Dictámenes
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `despacho_aliados` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT NOT NULL DEFAULT 1,
    `nombre_completo` VARCHAR(150) NOT NULL,
    `especialidad` ENUM(
        'Perito Avaluador (SOVECT)',
        'Especialista en Costos & Precios Justos',
        'Licenciado en Relaciones Industriales / RRHH',
        'Ingeniero Civil / Peritaje de Obras',
        'Corredor de Seguros Patrimoniales',
        'Gestor Tributario & Aduanas',
        'Abogado Corporativo / Mercantil',
        'Especialista en Ciberseguridad & TI'
    ) NOT NULL DEFAULT 'Perito Avaluador (SOVECT)',
    `rif_cedula` VARCHAR(20) NOT NULL,
    `colegiatura_numero` VARCHAR(50) NULL, -- Ej: CPC-XXXXX, CIV-XXXXX, INPREABOGADO
    `telefono` VARCHAR(30) NOT NULL,
    `email` VARCHAR(120) NOT NULL,
    `direccion_ciudad` VARCHAR(120) NULL DEFAULT 'Caracas, Venezuela',
    `honorarios_referenciales_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `modalidad_cobro` ENUM('Por Hora', 'Por Dictamen/Informe', 'Por Porcentaje Proyecto', 'Mixto') NOT NULL DEFAULT 'Por Dictamen/Informe',
    `calificacion_despacho` TINYINT UNSIGNED NOT NULL DEFAULT 5, -- 1 a 5 estrellas
    `proyectos_realizados` JSON NULL, -- Registro de casos anteriores y clientes atendidos
    `notas_evaluacion` TEXT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_aliado_empresa` (`empresa_id`, `activo`),
    INDEX `idx_aliado_especialidad` (`especialidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- SEED DATA ESTRATÉGICO: REALIDAD VENEZOLANA DE DESPACHO
-- ====================================================================

-- 1. Matriz de Jerarquías con Costos y Tarifas Benchmark 2026
INSERT INTO `despacho_jerarquias` (`id`, `empresa_id`, `rol_nombre`, `nivel_jerarquico`, `tarifa_costo_hora_usd`, `tarifa_cobro_hora_usd`, `descripcion`) VALUES
(1, 1, 'Auxiliar Contable', 1, 4.50, 15.00, 'Carga de asientos de diario, conciliaciones bancarias y transcripción de libros de compras y ventas.'),
(2, 1, 'Asistente Contable', 2, 8.00, 25.00, 'Armado de declaraciones quincenales de IVA, retenciones ISLR, elaboración de pre-nóminas y TXT SENIAT.'),
(3, 1, 'Contador Senior', 3, 15.00, 45.00, 'Revisión técnica de estados financieros VEN-NIF, cierre fiscal de ISLR, auditoría preventiva y atención de requerimientos.'),
(4, 1, 'Gerente de Auditoría', 4, 25.00, 70.00, 'Supervisión de encargos bajo NIA, validación de papeles de trabajo, control de calidad y mitigación de riesgos.'),
(5, 1, 'Socio Director (CPA Lead)', 5, 45.00, 120.00, 'Estrategia tributaria corporativa, dictámenes de comisario, defensa de actas de reparo SENIAT y relación con la junta directiva.')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 2. Estructura de Costos Fijos Mensuales Típicos
INSERT INTO `despacho_costos_fijos` (`id`, `empresa_id`, `categoria`, `concepto`, `monto_mensual_usd`, `frecuencia`, `fecha_inicio`, `observaciones`) VALUES
(1, 1, 'Alquiler de Oficina', 'Alquiler Centro Empresarial El Rosal', 650.00, 'Mensual', '2026-01-01', 'Oficina física con sala de juntas y puestos para 8 contadores.'),
(2, 1, 'Servicios e Internet', 'Fibra Óptica Dedicada + Respaldo Eléctrico / UPS', 120.00, 'Mensual', '2026-01-01', 'Enlace simétrico para procesamiento de portales SENIAT / IVSS.'),
(3, 1, 'Licencias y Software', 'Suite Kontify Pro + Servidor Cloud Backup + Office 365', 180.00, 'Mensual', '2026-01-01', 'Licenciamiento operativo de firmas.'),
(4, 1, 'Nomina Administrativa', 'Secretaría Ejecutiva & Recepción', 300.00, 'Mensual', '2026-01-01', 'Personal de apoyo logístico y archivo fiscal.'),
(5, 1, 'Colegiatura y Tributos Municipales', 'Aporte Colegio de Contadores del Dto. Capital / Miranda + Patente', 90.00, 'Mensual', '2026-01-01', 'Solvencias institucionales de la firma.')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 3. Directorio de Aliados Multidisciplinarios
INSERT INTO `despacho_aliados` (`id`, `empresa_id`, `nombre_completo`, `especialidad`, `rif_cedula`, `colegiatura_numero`, `telefono`, `email`, `direccion_ciudad`, `honorarios_referenciales_usd`, `modalidad_cobro`, `proyectos_realizados`, `notas_evaluacion`) VALUES
(1, 1, 'Ing. Carlos Mendoza (SOVECT)', 'Perito Avaluador (SOVECT)', 'V-11234567', 'SOVECT-412 / CIV-89210', '0414-2345678', 'carlos.mendoza.peritajes@gmail.com', 'Caracas', 350.00, 'Por Dictamen/Informe', 
    JSON_ARRAY(
        JSON_OBJECT("cliente", "Inversiones El Ávila, C.A.", "caso", "Reexpresión y Avalúo de Maquinaria para adopción VEN-NIF PYMES", "fecha", "2025-11-15")
    ), 
    'Excelente precisión técnica en informes aptos para inspecciones de aseguradoras y registros mercantiles.'),
(2, 1, 'Lic. Yelitza Rondón', 'Licenciado en Relaciones Industriales / RRHH', 'V-16789123', 'CRIL-8842', '0412-8765432', 'yrondon.laboral@gmail.com', 'Valencia / Caracas', 180.00, 'Por Proyecto', 
    JSON_ARRAY(
        JSON_OBJECT("cliente", "Farmacia La Milagrosa, C.A.", "caso", "Auditoría de Pasivos Laborales y Expedientes LOTTT", "fecha", "2026-01-20")
    ), 
    'Especialista en cálculos de prestaciones sociales, retroactivos y liquidaciones complejas.'),
(3, 1, 'Dr. Gustavo A. Alfonzo', 'Abogado Corporativo / Mercantil', 'V-09876543', 'INPREABO-54129', '0424-1122334', 'galfonzo.legal@escritorioalfonzo.com', 'Caracas', 250.00, 'Por Dictamen/Informe',
    JSON_ARRAY(
        JSON_OBJECT("cliente", "Comercializadora Los Andes, S.A.", "caso", "Aumento de Capital con Superávit por Revaluación", "fecha", "2025-10-05")
    ),
    'Homologación expedita en registros mercantiles y redacción de actas de asamblea bajo código de comercio.')
ON DUPLICATE KEY UPDATE `id`=`id`;

SET FOREIGN_KEY_CHECKS = 1;
