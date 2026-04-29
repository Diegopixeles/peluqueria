-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 03-02-2026 a las 10:55:01
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

-- Crear la base de datos si no existe y seleccionarla
CREATE DATABASE IF NOT EXISTS `peluqueria`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_general_ci;

USE `peluqueria`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `peluqueria`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas`
--

CREATE TABLE `citas` (
  `id_cita` bigint(20) UNSIGNED NOT NULL,
  `id_cliente` bigint(20) UNSIGNED NOT NULL,
  `id_empleado` bigint(20) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `duracion_minutos` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `hora_fin` time GENERATED ALWAYS AS (addtime(`hora_inicio`,sec_to_time(`duracion_minutos` * 60))) STORED,
  `estado` enum('pendiente','confirmada','en curso','finalizada','cancelada') NOT NULL DEFAULT 'pendiente',
  `notas` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cita_servicio`
--

CREATE TABLE `cita_servicio` (
  `id_cita_servicio` bigint(20) UNSIGNED NOT NULL,
  `id_cita` bigint(20) UNSIGNED NOT NULL,
  `id_servicio` bigint(20) UNSIGNED NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `duracion_minutos` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `cita_servicio`
--
DELIMITER $$
CREATE TRIGGER `trg_actualizar_duracion_cita` AFTER INSERT ON `cita_servicio` FOR EACH ROW BEGIN
  UPDATE citas c
  SET c.duracion_minutos = (
    SELECT IFNULL(SUM(cs.duracion_minutos),0) FROM cita_servicio cs WHERE cs.id_cita = c.id_cita
  )
  WHERE c.id_cita = NEW.id_cita;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cita_servicio_insertar` BEFORE INSERT ON `cita_servicio` FOR EACH ROW BEGIN
  DECLARE v_precio DECIMAL(10,2);
  DECLARE v_duracion INT;
  SELECT precio, duracion_minutos INTO v_precio, v_duracion
  FROM servicios WHERE id_servicio = NEW.id_servicio AND activo=1;

  IF v_precio IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Servicio no válido o inactivo';
  END IF;

  SET NEW.precio = v_precio;
  SET NEW.duracion_minutos = v_duracion;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `apellidos` varchar(160) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `correo` varchar(160) DEFAULT NULL,
  `fecha_alta` date NOT NULL DEFAULT curdate(),
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id_cliente`, `nombre`, `apellidos`, `telefono`, `correo`, `fecha_alta`, `observaciones`) VALUES
(1, 'Ana', 'García López', '600000001', 'ana@gmail.com', '2026-02-03', NULL),
(2, 'Luis', 'Pérez Díaz', '600000002', 'luis@gmail.com', '2026-02-03', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empleados`
--

CREATE TABLE `empleados` (
  `id_empleado` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `apellidos` varchar(160) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `correo` varchar(160) DEFAULT NULL,
  `puesto` enum('peluquero','barbero','esteticista','recepcion','gerencia') NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empleados`
--

INSERT INTO `empleados` (`id_empleado`, `nombre`, `apellidos`, `telefono`, `correo`, `puesto`, `activo`) VALUES
(1, 'Marta', 'Ruiz', '611111111', 'marta@peluqueria.es', 'peluquero', 1),
(2, 'Guillermo', 'Diaz', '622222222', 'guillermo@peluqueria.es', 'barbero', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios`
--

CREATE TABLE `horarios` (
  `id_horario` bigint(20) UNSIGNED NOT NULL,
  `id_empleado` bigint(20) UNSIGNED NOT NULL,
  `dia_semana` tinyint(3) UNSIGNED NOT NULL CHECK (`dia_semana` between 1 and 7),
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id_producto` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `marca` varchar(120) DEFAULT NULL,
  `codigo_sku` varchar(80) DEFAULT NULL,
  `precio_venta` decimal(10,2) NOT NULL CHECK (`precio_venta` >= 0),
  `stock_actual` int(11) NOT NULL DEFAULT 0 CHECK (`stock_actual` >= 0),
  `stock_minimo` int(11) NOT NULL DEFAULT 0 CHECK (`stock_minimo` >= 0),
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id_producto`, `nombre`, `marca`, `codigo_sku`, `precio_venta`, `stock_actual`, `stock_minimo`, `activo`) VALUES
(1, 'Champú nutritivo 500ml', 'MarcaX', 'SKU-CH-500', 9.90, 20, 5, 1),
(2, 'Mascarilla hidratante 250ml', 'MarcaX', 'SKU-MA-250', 12.50, 15, 5, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

CREATE TABLE `servicios` (
  `id_servicio` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL CHECK (`precio` >= 0),
  `duracion_minutos` smallint(5) UNSIGNED NOT NULL CHECK (`duracion_minutos` > 0),
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `servicios`
--

INSERT INTO `servicios` (`id_servicio`, `nombre`, `descripcion`, `precio`, `duracion_minutos`, `activo`) VALUES
(1, 'Corte mujer', 'Corte y peinado', 18.00, 45, 1),
(2, 'Corte hombre', 'Corte clásico o moderno', 12.00, 30, 1),
(3, 'Tinte', 'Tinte de cabello', 25.00, 60, 1),
(4, 'Barba', 'Arreglo y contorno', 8.00, 20, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `valoraciones`
--

CREATE TABLE `valoraciones` (
  `id_valoracion` bigint(20) UNSIGNED NOT NULL,
  `id_cliente` bigint(20) UNSIGNED NOT NULL,
  `id_empleado` bigint(20) UNSIGNED DEFAULT NULL,
  `id_servicio` bigint(20) UNSIGNED DEFAULT NULL,
  `id_cita` bigint(20) UNSIGNED DEFAULT NULL,
  `puntuacion` tinyint(3) UNSIGNED NOT NULL CHECK (`puntuacion` between 1 and 5),
  `comentario` varchar(500) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_citas_resumen`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_citas_resumen` (
`id_cita` bigint(20) unsigned
,`fecha` date
,`hora_inicio` time
,`hora_fin` time
,`estado` enum('pendiente','confirmada','en curso','finalizada','cancelada')
,`cliente` varchar(120)
,`empleado` varchar(281)
,`total` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_citas_resumen`
--
DROP TABLE IF EXISTS `vista_citas_resumen`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_citas_resumen`  AS SELECT `c`.`id_cita` AS `id_cita`, `c`.`fecha` AS `fecha`, `c`.`hora_inicio` AS `hora_inicio`, `c`.`hora_fin` AS `hora_fin`, `c`.`estado` AS `estado`, `cli`.`nombre` AS `cliente`, concat(`emp`.`nombre`,' ',`emp`.`apellidos`) AS `empleado`, (select ifnull(sum(`cs`.`precio`),0) from `cita_servicio` `cs` where `cs`.`id_cita` = `c`.`id_cita`) AS `total` FROM ((`citas` `c` join `clientes` `cli` on(`cli`.`id_cliente` = `c`.`id_cliente`)) join `empleados` `emp` on(`emp`.`id_empleado` = `c`.`id_empleado`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`id_cita`),
  ADD KEY `idx_empleado_fecha` (`id_empleado`,`fecha`,`hora_inicio`),
  ADD KEY `idx_cliente_fecha` (`id_cliente`,`fecha`);

--
-- Indices de la tabla `cita_servicio`
--
ALTER TABLE `cita_servicio`
  ADD PRIMARY KEY (`id_cita_servicio`),
  ADD UNIQUE KEY `id_cita` (`id_cita`,`id_servicio`),
  ADD KEY `id_servicio` (`id_servicio`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`),
  ADD UNIQUE KEY `telefono` (`telefono`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- Indices de la tabla `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`id_empleado`),
  ADD UNIQUE KEY `telefono` (`telefono`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- Indices de la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD PRIMARY KEY (`id_horario`),
  ADD UNIQUE KEY `uq_horarios_unico` (`id_empleado`,`dia_semana`,`hora_inicio`,`hora_fin`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id_producto`),
  ADD UNIQUE KEY `codigo_sku` (`codigo_sku`),
  ADD KEY `idx_nombre` (`nombre`);

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id_servicio`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `valoraciones`
--
ALTER TABLE `valoraciones`
  ADD PRIMARY KEY (`id_valoracion`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_empleado` (`id_empleado`),
  ADD KEY `id_servicio` (`id_servicio`),
  ADD KEY `id_cita` (`id_cita`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `citas`
--
ALTER TABLE `citas`
  MODIFY `id_cita` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cita_servicio`
--
ALTER TABLE `cita_servicio`
  MODIFY `id_cita_servicio` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `empleados`
--
ALTER TABLE `empleados`
  MODIFY `id_empleado` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `horarios`
--
ALTER TABLE `horarios`
  MODIFY `id_horario` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id_producto` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id_servicio` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `valoraciones`
--
ALTER TABLE `valoraciones`
  MODIFY `id_valoracion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `citas`
--
ALTER TABLE `citas`
  ADD CONSTRAINT `citas_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  ADD CONSTRAINT `citas_ibfk_2` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`);

--
-- Filtros para la tabla `cita_servicio`
--
ALTER TABLE `cita_servicio`
  ADD CONSTRAINT `cita_servicio_ibfk_1` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`) ON DELETE CASCADE,
  ADD CONSTRAINT `cita_servicio_ibfk_2` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`);

--
-- Filtros para la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD CONSTRAINT `fk_horarios_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`) ON DELETE CASCADE;

--
-- Filtros para la tabla `valoraciones`
--
ALTER TABLE `valoraciones`
  ADD CONSTRAINT `valoraciones_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE CASCADE,
  ADD CONSTRAINT `valoraciones_ibfk_2` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`) ON DELETE SET NULL,
  ADD CONSTRAINT `valoraciones_ibfk_3` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON DELETE SET NULL,
  ADD CONSTRAINT `valoraciones_ibfk_4` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
