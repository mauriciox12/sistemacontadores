-- =========================================================================
-- KONTIFY / NEXUS FINANZAS - MIGRACIÓN PARA SISTEMA DE SUSCRIPCIONES SAAS
-- =========================================================================

-- 1. Alterar la tabla 'users' para almacenar el plan y la fecha de vencimiento
ALTER TABLE `users` 
ADD COLUMN `plan_id` VARCHAR(50) NOT NULL DEFAULT 'FREE',
ADD COLUMN `valid_until` DATETIME NULL;

-- 2. Asignar 14 días de prueba por defecto a las cuentas existentes desde su creación o desde ahora
UPDATE `users` 
SET `valid_until` = DATE_ADD(COALESCE(`created_at`, NOW()), INTERVAL 14 DAY)
WHERE `valid_until` IS NULL;

-- 3. Crear la tabla 'reported_payments' para comprobantes de pago manuales
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
