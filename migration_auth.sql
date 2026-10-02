-- ==========================================================
-- KONTIFY APP - Migración: Módulo de Autenticación Completo
-- Agrega soporte para login con email/contraseña y
-- recuperación de contraseña a la tabla 'usuarios' existente
-- ==========================================================

-- Agregar campos de autenticación local
ALTER TABLE `usuarios` 
ADD COLUMN IF NOT EXISTS `password_hash` VARCHAR(255) NULL AFTER `avatar`,
ADD COLUMN IF NOT EXISTS `reset_token` VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS `reset_token_expira` DATETIME NULL,
ADD COLUMN IF NOT EXISTS `email_verificado` TINYINT(1) NOT NULL DEFAULT 1,
ADD COLUMN IF NOT EXISTS `creado_el` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Asegurar que el email sea único para evitar duplicados
-- (solo si no existe ya el índice)
-- ALTER TABLE `usuarios` ADD UNIQUE INDEX `idx_email_unique` (`email`);
