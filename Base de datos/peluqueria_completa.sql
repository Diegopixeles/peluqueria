-- =============================================================
-- ARCHIVO: peluqueria_completa.sql
-- DESCRIPCIÓN: Base de datos completa de la peluquería.
--              Contiene todas las tablas, índices, triggers,
--              vistas, datos de ejemplo y tabla de usuarios.
-- USO: Importar directamente en phpMyAdmin (sin seleccionar BD).
--      La base de datos se crea automáticamente.
-- =============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


-- =============================================================
-- 1. CREAR Y SELECCIONAR LA BASE DE DATOS
-- =============================================================

CREATE DATABASE IF NOT EXISTS `peluqueria`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_general_ci;

USE `peluqueria`;


-- =============================================================
-- 2. TABLAS PRINCIPALES
-- =============================================================

-- ------------------------------------------------------------
-- Tabla: clientes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clientes` (
  `id_cliente`    BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(120) NOT NULL,
  `apellidos`     VARCHAR(160) NOT NULL,
  `telefono`      VARCHAR(20)  NOT NULL,
  `correo`        VARCHAR(160) DEFAULT NULL,
  `fecha_alta`    DATE NOT NULL,
  `observaciones` TEXT DEFAULT NULL,
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `uq_telefono` (`telefono`),
  UNIQUE KEY `uq_correo`   (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabla: empleados
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `empleados` (
  `id_empleado` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(120) NOT NULL,
  `apellidos`   VARCHAR(160) NOT NULL,
  `telefono`    VARCHAR(20)  NOT NULL,
  `correo`      VARCHAR(160) DEFAULT NULL,
  `puesto`      ENUM('peluquero','barbero','esteticista','recepcion','gerencia') NOT NULL,
  `activo`      TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_empleado`),
  UNIQUE KEY `uq_telefono` (`telefono`),
  UNIQUE KEY `uq_correo`   (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabla: servicios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicios` (
  `id_servicio`      BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`           VARCHAR(120) NOT NULL,
  `descripcion`      TEXT DEFAULT NULL,
  `precio`           DECIMAL(10,2) NOT NULL CHECK (`precio` >= 0),
  `duracion_minutos` SMALLINT(5) UNSIGNED NOT NULL CHECK (`duracion_minutos` > 0),
  `activo`           TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_servicio`),
  UNIQUE KEY `uq_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabla: productos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `productos` (
  `id_producto`  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`       VARCHAR(160) NOT NULL,
  `marca`        VARCHAR(120) DEFAULT NULL,
  `codigo_sku`   VARCHAR(80)  DEFAULT NULL,
  `precio_venta` DECIMAL(10,2) NOT NULL CHECK (`precio_venta` >= 0),
  `stock_actual` INT(11) NOT NULL DEFAULT 0 CHECK (`stock_actual` >= 0),
  `stock_minimo` INT(11) NOT NULL DEFAULT 0 CHECK (`stock_minimo` >= 0),
  `activo`       TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_producto`),
  UNIQUE KEY `uq_sku`    (`codigo_sku`),
  KEY `idx_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabla: horarios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `horarios` (
  `id_horario`  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_empleado` BIGINT(20) UNSIGNED NOT NULL,
  `dia_semana`  TINYINT(3) UNSIGNED NOT NULL CHECK (`dia_semana` BETWEEN 1 AND 7), -- 1=Lunes, 7=Domingo
  `hora_inicio` TIME NOT NULL,
  `hora_fin`    TIME NOT NULL,
  PRIMARY KEY (`id_horario`),
  UNIQUE KEY `uq_horarios_unico` (`id_empleado`, `dia_semana`, `hora_inicio`, `hora_fin`),
  CONSTRAINT `fk_horarios_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabla: citas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `citas` (
  `id_cita`          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_cliente`       BIGINT(20) UNSIGNED NOT NULL,
  `id_empleado`      BIGINT(20) UNSIGNED NOT NULL,
  `fecha`            DATE NOT NULL,
  `hora_inicio`      TIME NOT NULL,
  `duracion_minutos` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `hora_fin`         TIME GENERATED ALWAYS AS (addtime(`hora_inicio`, sec_to_time(`duracion_minutos` * 60))) STORED,
  `estado`           ENUM('pendiente','confirmada','en curso','finalizada','cancelada') NOT NULL DEFAULT 'pendiente',
  `notas`            VARCHAR(255) DEFAULT NULL,
  `creado_en`        DATETIME NOT NULL DEFAULT current_timestamp(),
  `actualizado_en`   DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_cita`),
  KEY `idx_empleado_fecha` (`id_empleado`, `fecha`, `hora_inicio`),
  KEY `idx_cliente_fecha`  (`id_cliente`, `fecha`),
  CONSTRAINT `fk_citas_cliente`   FOREIGN KEY (`id_cliente`)  REFERENCES `clientes`  (`id_cliente`)  ON DELETE RESTRICT,
  CONSTRAINT `fk_citas_empleado`  FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabla: cita_servicio  (relación N:M entre citas y servicios)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cita_servicio` (
  `id_cita_servicio` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_cita`          BIGINT(20) UNSIGNED NOT NULL,
  `id_servicio`      BIGINT(20) UNSIGNED NOT NULL,
  `precio`           DECIMAL(10,2) NOT NULL,
  `duracion_minutos` INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id_cita_servicio`),
  UNIQUE KEY `uq_cita_servicio` (`id_cita`, `id_servicio`),
  KEY `idx_servicio` (`id_servicio`),
  CONSTRAINT `fk_cs_cita`     FOREIGN KEY (`id_cita`)     REFERENCES `citas`     (`id_cita`)     ON DELETE CASCADE,
  CONSTRAINT `fk_cs_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabla: valoraciones
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `valoraciones` (
  `id_valoracion`  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_cliente`     BIGINT(20) UNSIGNED NOT NULL,
  `id_empleado`    BIGINT(20) UNSIGNED DEFAULT NULL,
  `id_servicio`    BIGINT(20) UNSIGNED DEFAULT NULL,
  `id_cita`        BIGINT(20) UNSIGNED DEFAULT NULL,
  `puntuacion`     TINYINT(3) UNSIGNED NOT NULL CHECK (`puntuacion` BETWEEN 1 AND 5),
  `comentario`     VARCHAR(500) DEFAULT NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_valoracion`),
  KEY `idx_cliente`  (`id_cliente`),
  KEY `idx_empleado` (`id_empleado`),
  KEY `idx_servicio` (`id_servicio`),
  KEY `idx_cita`     (`id_cita`),
  CONSTRAINT `fk_val_cliente`  FOREIGN KEY (`id_cliente`)  REFERENCES `clientes`  (`id_cliente`)  ON DELETE CASCADE,
  CONSTRAINT `fk_val_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`) ON DELETE SET NULL,
  CONSTRAINT `fk_val_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON DELETE SET NULL,
  CONSTRAINT `fk_val_cita`     FOREIGN KEY (`id_cita`)     REFERENCES `citas`     (`id_cita`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabla: reservas_web  (reservas desde la web pública)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reservas_web` (
  `id_reserva`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`         VARCHAR(160) NOT NULL,
  `email`          VARCHAR(160) NOT NULL,
  `telefono`       VARCHAR(20)  DEFAULT NULL,
  `fecha`          DATE NOT NULL,
  `hora_inicio`    TIME NOT NULL,
  `id_servicio`    BIGINT UNSIGNED NOT NULL,
  `id_cliente_web` BIGINT UNSIGNED DEFAULT NULL,
  `estado`         ENUM('pendiente','confirmada','cancelada') NOT NULL DEFAULT 'pendiente',
  `notas`          VARCHAR(255) DEFAULT NULL,
  `creado_en`      DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_reserva`),
  KEY `idx_rw_fecha`   (`fecha`),
  KEY `idx_rw_email`   (`email`),
  KEY `idx_rw_cliente` (`id_cliente_web`),
  CONSTRAINT `fk_rw_servicio` FOREIGN KEY (`id_servicio`)    REFERENCES `servicios` (`id_servicio`) ON DELETE RESTRICT,
  CONSTRAINT `fk_rw_cliente`  FOREIGN KEY (`id_cliente_web`) REFERENCES `clientes`  (`id_cliente`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabla: usuarios  (acceso al panel de administración)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(120) NOT NULL,
  `email`         VARCHAR(160) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,        -- Cifrado con bcrypt (PHP)
  `rol`           ENUM('admin','empleado') NOT NULL DEFAULT 'empleado',
  `activo`        TINYINT(1) NOT NULL DEFAULT 1,
  `creado_en`     DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Tabla: mensajes_contacto (dudas enviadas desde la web)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mensajes_contacto` (
  `id_mensaje` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(160) NOT NULL,
  `email` VARCHAR(160) NOT NULL,
  `mensaje` TEXT NOT NULL,
  `fecha_envio` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_mensaje`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================
-- 3. TRIGGERS (disparadores automáticos)
-- =============================================================

DELIMITER $$

DROP TRIGGER IF EXISTS `trg_cita_servicio_insertar`$$
-- Copia el precio y duración del servicio al insertar en cita_servicio
CREATE TRIGGER `trg_cita_servicio_insertar`
BEFORE INSERT ON `cita_servicio`
FOR EACH ROW
BEGIN
  DECLARE v_precio    DECIMAL(10,2);
  DECLARE v_duracion  INT;
  SELECT precio, duracion_minutos
    INTO v_precio, v_duracion
    FROM servicios
   WHERE id_servicio = NEW.id_servicio AND activo = 1;

  IF v_precio IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Servicio no válido o inactivo';
  END IF;

  SET NEW.precio           = v_precio;
  SET NEW.duracion_minutos = v_duracion;
END$$

DROP TRIGGER IF EXISTS `trg_actualizar_duracion_cita`$$
-- Recalcula la duración total de la cita al añadir un servicio
CREATE TRIGGER `trg_actualizar_duracion_cita`
AFTER INSERT ON `cita_servicio`
FOR EACH ROW
BEGIN
  UPDATE citas c
     SET c.duracion_minutos = (
           SELECT IFNULL(SUM(cs.duracion_minutos), 0)
             FROM cita_servicio cs
            WHERE cs.id_cita = c.id_cita
         )
   WHERE c.id_cita = NEW.id_cita;
END$$

DELIMITER ;


-- =============================================================
-- 4. VISTA RESUMEN DE CITAS
-- =============================================================

CREATE OR REPLACE VIEW `vista_citas_resumen` AS
SELECT
  c.id_cita,
  c.fecha,
  c.hora_inicio,
  c.hora_fin,
  c.estado,
  cli.nombre                                    AS cliente,
  CONCAT(emp.nombre, ' ', emp.apellidos)        AS empleado,
  (SELECT IFNULL(SUM(cs.precio), 0)
     FROM cita_servicio cs
    WHERE cs.id_cita = c.id_cita)               AS total
FROM citas c
JOIN clientes  cli ON cli.id_cliente  = c.id_cliente
JOIN empleados emp ON emp.id_empleado = c.id_empleado;


-- =============================================================
-- 5. DATOS DE EJEMPLO
-- =============================================================

-- Clientes de prueba
INSERT IGNORE INTO `clientes` (`nombre`, `apellidos`, `telefono`, `correo`, `fecha_alta`) VALUES
('Ana',  'García López', '600000001', 'ana@gmail.com',  '2026-01-01'),
('Luis', 'Pérez Díaz',   '600000002', 'luis@gmail.com', '2026-01-01');

-- Empleados de prueba
INSERT IGNORE INTO `empleados` (`nombre`, `apellidos`, `telefono`, `correo`, `puesto`) VALUES
('Marta',     'Ruiz',  '611111111', 'marta@peluqueria.es',     'peluquero'),
('Guillermo', 'Diaz',  '622222222', 'guillermo@peluqueria.es', 'barbero');

-- Catálogo de servicios
INSERT IGNORE INTO `servicios` (`nombre`, `descripcion`, `precio`, `duracion_minutos`) VALUES
('Corte mujer',  'Corte y peinado',         18.00, 45),
('Corte hombre', 'Corte clásico o moderno', 12.00, 30),
('Tinte',        'Tinte de cabello',         25.00, 60),
('Barba',        'Arreglo y contorno',        8.00, 20);

-- Productos de venta
INSERT IGNORE INTO `productos` (`nombre`, `marca`, `codigo_sku`, `precio_venta`, `stock_actual`, `stock_minimo`) VALUES
('Champú nutritivo 500ml',      'MarcaX', 'SKU-CH-500',  9.90, 20, 5),
('Mascarilla hidratante 250ml', 'MarcaX', 'SKU-MA-250', 12.50, 15, 5);

-- Usuario administrador del panel
-- Email:      admin@peluqueria.es
-- Contraseña: Admin1234!
-- (hash generado con password_hash('Admin1234!', PASSWORD_BCRYPT))
INSERT IGNORE INTO `usuarios` (`nombre`, `email`, `password_hash`, `rol`) VALUES
('Administrador', 'admin@peluqueria.es',
 '$2y$10$YqntfKA4vrAtpBQ8jFjnVOLEc/iLpZPyu3WsbaGk8S9c512tfRhPG',
 'admin');


-- =============================================================

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
