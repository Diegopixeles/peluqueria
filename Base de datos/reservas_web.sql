-- =============================================================
-- ARCHIVO: reservas_web.sql
-- DESCRIPCIÓN: Añade la tabla `reservas_web` para guardar las
--              reservas de citas hechas desde la web pública,
--              tanto por clientes registrados como anónimos.
--              Ejecutar en phpMyAdmin sobre la BD `peluqueria`.
-- =============================================================

USE peluqueria;

CREATE TABLE IF NOT EXISTS reservas_web (
  id_reserva      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  -- Datos del cliente (rellenos manualmente si no está registrado)
  nombre          VARCHAR(160) NOT NULL,
  email           VARCHAR(160) NOT NULL,
  telefono        VARCHAR(20)  NULL,
  -- Datos de la cita
  fecha           DATE NOT NULL,
  hora_inicio     TIME NOT NULL,
  id_servicio     BIGINT UNSIGNED NOT NULL,
  -- Si el cliente tenía sesión iniciada, guardamos su id_cliente (nullable para anónimos)
  id_cliente_web  BIGINT UNSIGNED NULL,
  -- Estado de la reserva
  estado          ENUM('pendiente','confirmada','cancelada') NOT NULL DEFAULT 'pendiente',
  notas           VARCHAR(255) NULL,
  -- Auditoría
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Claves foráneas
  CONSTRAINT fk_rw_servicio  FOREIGN KEY (id_servicio)    REFERENCES servicios(id_servicio) ON DELETE RESTRICT,
  CONSTRAINT fk_rw_cliente   FOREIGN KEY (id_cliente_web) REFERENCES clientes(id_cliente)   ON DELETE SET NULL,
  -- Índices para búsquedas frecuentes
  INDEX idx_rw_fecha    (fecha),
  INDEX idx_rw_email    (email),
  INDEX idx_rw_cliente  (id_cliente_web)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
