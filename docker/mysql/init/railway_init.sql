-- railway_init.sql
-- Esquema de la base de datos 'peluqueria' para Railway.
-- La base de datos ya está creada por el plugin MySQL de Railway;
-- este script solo crea las tablas, triggers, vistas y datos de ejemplo.
SET NAMES utf8mb4;


-- Crear y usar la base de datos
  DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_general_ci;

-- TABLAS

-- Clientes del negocio
CREATE TABLE `clientes` (
  `id_cliente`    BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(120) NOT NULL,
  `apellidos`     VARCHAR(160) NOT NULL,
  `telefono`      VARCHAR(20)  NOT NULL,
  `correo`        VARCHAR(160) DEFAULT NULL,
  `fecha_alta`    DATE NOT NULL DEFAULT curdate(),
  `observaciones` TEXT DEFAULT NULL,
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `uq_telefono` (`telefono`),
  UNIQUE KEY `uq_correo`   (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Empleados de la peluquería
CREATE TABLE `empleados` (
  `id_empleado` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(120) NOT NULL,
  `apellidos`   VARCHAR(160) NOT NULL,
  `telefono`    VARCHAR(20)  NOT NULL,
  `correo`      VARCHAR(160) DEFAULT NULL,
  `puesto`      ENUM('peluquero','barbero','esteticista','recepcion','gerencia') NOT NULL,
  `activo`      TINYINT(1)   NOT NULL DEFAULT 1,  -- 1=activo, 0=baja
  PRIMARY KEY (`id_empleado`),
  UNIQUE KEY `uq_telefono` (`telefono`),
  UNIQUE KEY `uq_correo`   (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Catálogo de servicios ofrecidos
CREATE TABLE `servicios` (
  `id_servicio`      BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`           VARCHAR(120) NOT NULL,
  `descripcion`      TEXT DEFAULT NULL,
  `precio`           DECIMAL(10,2) NOT NULL CHECK (`precio` >= 0),
  `duracion_minutos` SMALLINT(5) UNSIGNED NOT NULL CHECK (`duracion_minutos` > 0),
  `activo`           TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_servicio`),
  UNIQUE KEY `uq_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Productos a la venta (champús, mascarillas, etc.)
CREATE TABLE `productos` (
  `id_producto`  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`       VARCHAR(160) NOT NULL,
  `marca`        VARCHAR(120) DEFAULT NULL,
  `codigo_sku`   VARCHAR(80)  DEFAULT NULL,       -- Código interno de referencia
  `precio_venta` DECIMAL(10,2) NOT NULL CHECK (`precio_venta` >= 0),
  `stock_actual` INT(11) NOT NULL DEFAULT 0 CHECK (`stock_actual` >= 0),
  `stock_minimo` INT(11) NOT NULL DEFAULT 0 CHECK (`stock_minimo` >= 0),
  `activo`       TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_producto`),
  UNIQUE KEY `uq_sku`  (`codigo_sku`),
  KEY `idx_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Turnos de trabajo de cada empleado (1=Lunes … 7=Domingo)
CREATE TABLE `horarios` (
  `id_horario`  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_empleado` BIGINT(20) UNSIGNED NOT NULL,
  `dia_semana`  TINYINT(3) UNSIGNED NOT NULL CHECK (`dia_semana` BETWEEN 1 AND 7),
  `hora_inicio` TIME NOT NULL,
  `hora_fin`    TIME NOT NULL,
  PRIMARY KEY (`id_horario`),
  UNIQUE KEY `uq_horarios_unico` (`id_empleado`, `dia_semana`, `hora_inicio`, `hora_fin`),
  CONSTRAINT `fk_horarios_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Citas reservadas. hora_fin se calcula automáticamente a partir de hora_inicio + duración
CREATE TABLE `citas` (
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
  CONSTRAINT `fk_citas_cliente`  FOREIGN KEY (`id_cliente`)  REFERENCES `clientes`  (`id_cliente`)  ON DELETE RESTRICT,
  CONSTRAINT `fk_citas_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Relación N:M entre citas y servicios (una cita puede incluir varios servicios)
CREATE TABLE `cita_servicio` (
  `id_cita_servicio` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_cita`          BIGINT(20) UNSIGNED NOT NULL,
  `id_servicio`      BIGINT(20) UNSIGNED NOT NULL,
  `precio`           DECIMAL(10,2) NOT NULL,         -- Copiado automáticamente por trigger
  `duracion_minutos` INT(10) UNSIGNED NOT NULL,       -- Copiado automáticamente por trigger
  PRIMARY KEY (`id_cita_servicio`),
  UNIQUE KEY `uq_cita_servicio` (`id_cita`, `id_servicio`),
  KEY `idx_servicio` (`id_servicio`),
  CONSTRAINT `fk_cs_cita`     FOREIGN KEY (`id_cita`)     REFERENCES `citas`     (`id_cita`)     ON DELETE CASCADE,
  CONSTRAINT `fk_cs_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Valoraciones de clientes sobre empleados y/o servicios (1 a 5 estrellas)
CREATE TABLE `valoraciones` (
  `id_valoracion`  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_cliente`     BIGINT(20) UNSIGNED NOT NULL,
  `id_empleado`    BIGINT(20) UNSIGNED DEFAULT NULL, -- NULL si valora el servicio en general
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

-- Reservas llegadas desde la web pública (pueden ser de clientes anónimos)
CREATE TABLE `reservas_web` (
  `id_reserva`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`         VARCHAR(160) NOT NULL,
  `email`          VARCHAR(160) NOT NULL,
  `telefono`       VARCHAR(20)  DEFAULT NULL,
  `fecha`          DATE NOT NULL,
  `hora_inicio`    TIME NOT NULL,
  `id_servicio`    BIGINT UNSIGNED NOT NULL,
  `id_cliente_web` BIGINT UNSIGNED DEFAULT NULL,  -- NULL si el cliente no está registrado
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

-- Usuarios con acceso al panel de administración (login mediante PHP + bcrypt)
CREATE TABLE `usuarios` (
  `id_usuario`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(120) NOT NULL,
  `email`         VARCHAR(160) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `rol`           ENUM('admin','empleado') NOT NULL DEFAULT 'empleado',
  `activo`        TINYINT(1) NOT NULL DEFAULT 1,
  `creado_en`     DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- TRIGGERS

DELIMITER $$

-- Al añadir un servicio a una cita, copia automáticamente su precio y duración
-- evitando que cambios futuros en el catálogo afecten a citas ya registradas
CREATE TRIGGER `trg_cita_servicio_insertar`
BEFORE INSERT ON `cita_servicio`
FOR EACH ROW
BEGIN
  DECLARE v_precio   DECIMAL(10,2);
  DECLARE v_duracion INT;
  SELECT precio, duracion_minutos INTO v_precio, v_duracion
    FROM servicios WHERE id_servicio = NEW.id_servicio AND activo = 1;

  IF v_precio IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Servicio no válido o inactivo';
  END IF;

  SET NEW.precio           = v_precio;
  SET NEW.duracion_minutos = v_duracion;
END$$

-- Tras insertar un servicio en una cita, recalcula la duración total de esa cita
CREATE TRIGGER `trg_actualizar_duracion_cita`
AFTER INSERT ON `cita_servicio`
FOR EACH ROW
BEGIN
  UPDATE citas c
     SET c.duracion_minutos = (
           SELECT IFNULL(SUM(cs.duracion_minutos), 0)
             FROM cita_servicio cs WHERE cs.id_cita = c.id_cita
         )
   WHERE c.id_cita = NEW.id_cita;
END$$

DELIMITER ;


-- VISTA: resumen de citas con datos del cliente y empleado
CREATE OR REPLACE VIEW `vista_citas_resumen` AS
SELECT
  c.id_cita, c.fecha, c.hora_inicio, c.hora_fin, c.estado,
  cli.nombre                             AS cliente,
  CONCAT(emp.nombre, ' ', emp.apellidos) AS empleado,
  (SELECT IFNULL(SUM(cs.precio), 0)
     FROM cita_servicio cs WHERE cs.id_cita = c.id_cita) AS total
FROM citas c
JOIN clientes  cli ON cli.id_cliente  = c.id_cliente
JOIN empleados emp ON emp.id_empleado = c.id_empleado;

-- DATOS DE EJEMPLO

INSERT INTO `clientes` (`nombre`, `apellidos`, `telefono`, `correo`) VALUES
('Ana',  'García López', '600000001', 'ana@gmail.com'),
('Luis', 'Pérez Díaz',   '600000002', 'luis@gmail.com');

INSERT INTO `empleados` (`nombre`, `apellidos`, `telefono`, `correo`, `puesto`) VALUES
('Marta',     'Ruiz', '611111111', 'marta@peluqueria.es',     'peluquero'),
('Guillermo', 'Diaz', '622222222', 'guillermo@peluqueria.es', 'barbero');

INSERT INTO `servicios` (`nombre`, `descripcion`, `precio`, `duracion_minutos`) VALUES
('Corte mujer',  'Corte y peinado',         18.00, 45),
('Corte hombre', 'Corte clásico o moderno', 12.00, 30),
('Tinte',        'Tinte de cabello',         25.00, 60),
('Barba',        'Arreglo y contorno',        8.00, 20);

INSERT INTO `productos` (`nombre`, `marca`, `codigo_sku`, `precio_venta`, `stock_actual`, `stock_minimo`) VALUES
('Champú nutritivo 500ml',      'MarcaX', 'SKU-CH-500',  9.90, 20, 5),
('Mascarilla hidratante 250ml', 'MarcaX', 'SKU-MA-250', 12.50, 15, 5);

-- Credenciales: admin@peluqueria.es / Admin1234!
INSERT INTO `usuarios` (`nombre`, `email`, `password_hash`, `rol`) VALUES
('Administrador', 'admin@peluqueria.es',
 '$2y$10$YqntfKA4vrAtpBQ8jFjnVOLEc/iLpZPyu3WsbaGk8S9c512tfRhPG',
 'admin');

COMMIT;
