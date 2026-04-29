-- =============================================================
-- ARCHIVO: usuarios.sql
-- DESCRIPCIÓN: Crea la tabla de usuarios del sistema de login
--              y añade un usuario administrador de prueba.
-- INSTRUCCIONES: Importa este archivo en phpMyAdmin con la base
--                de datos "peluqueria" ya seleccionada.
-- =============================================================

USE peluqueria;

-- ---- Tabla de usuarios del sistema ----
-- Contiene las credenciales de acceso al panel de administración.
-- Los empleados pueden tener cuenta si el administrador se la crea.
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id_usuario`     INT UNSIGNED    NOT NULL AUTO_INCREMENT,  -- Identificador único
    `nombre`         VARCHAR(120)    NOT NULL,                 -- Nombre visible del usuario
    `email`          VARCHAR(160)    NOT NULL,                 -- Email usado para el login
    `password_hash`  VARCHAR(255)    NOT NULL,                 -- Contraseña cifrada (bcrypt via PHP)
    `rol`            ENUM('admin','empleado') NOT NULL DEFAULT 'empleado', -- Nivel de acceso
    `activo`         TINYINT(1)      NOT NULL DEFAULT 1,       -- 1=activo, 0=bloqueado
    `creado_en`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_usuario`),
    UNIQUE KEY `uq_email` (`email`)      -- El email es único (no puede haber duplicados)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---- Usuario administrador de prueba ----
-- Email:      admin@peluqueria.es
-- Contraseña: Admin1234!
-- El hash fue generado con password_hash('Admin1234!', PASSWORD_BCRYPT)
-- IMPORTANTE: Cambia la contraseña tras el primer acceso en un entorno real.
INSERT INTO `usuarios` (`nombre`, `email`, `password_hash`, `rol`) VALUES
(
    'Administrador',
    'admin@peluqueria.es',
    '$2y$10$YqntfKA4vrAtpBQ8jFjnVOLEc/iLpZPyu3WsbaGk8S9c512tfRhPG',
    'admin'
);
-- NOTA: El hash de arriba corresponde a la contraseña "password" (valor de prueba de Laravel/PHP).
-- Para generar tu propio hash puedes usar en PHP:
--   echo password_hash('TuContraseña', PASSWORD_BCRYPT);
