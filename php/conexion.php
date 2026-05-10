<?php
// =============================================================
// DESCRIPCIÓN: Establece la conexión a la base de datos MySQL
//              usando PDO (PHP Data Objects) para mayor seguridad.
//              Este archivo debe ser incluido (require_once) en
//              cualquier script que necesite acceso a la BD.
// =============================================================

// ---- Parámetros de conexión ----
// Lee variables de entorno si están definidas (Docker), si no usa los valores de XAMPP.
define('DB_HOST',    getenv('DB_HOST')    ?: '127.0.0.1');   // En Docker viene del docker-compose.yml
define('DB_NAME',    getenv('DB_NAME')    ?: 'peluqueria');   // Nombre de la base de datos
define('DB_USER',    getenv('DB_USER')    ?: 'root');         // Usuario de MySQL
define('DB_PASS',    getenv('DB_PASS')    ?: '');             // Contraseña de MySQL
define('DB_CHARSET', 'utf8mb4');                              // Juego de caracteres (soporta emojis)

// ---- Construcción del DSN (Data Source Name) ----
// El DSN es la cadena de conexión que PDO necesita para identificar
// el tipo de base de datos y sus parámetros de acceso.
$dsn = 'mysql:host=' . DB_HOST
     . ';dbname=' . DB_NAME
     . ';charset=' . DB_CHARSET;

// ---- Opciones de configuración de PDO ----
$opciones = [
    // Lanza excepciones en caso de error → facilita detectar problemas
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

    // Los nombres de columna en los resultados serán tal como están en la BD
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // Desactiva la emulación de prepared statements para mayor seguridad
    // (evita inyección SQL)
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// ---- Creación del objeto PDO (conexión real) ----
try {
    // Se intenta crear el objeto PDO con los parámetros de arriba.
    // Si algo falla (BD apagada, credenciales erróneas, etc.) lanza PDOException.
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);

} catch (PDOException $e) {
    // Si la conexión falla, mostramos un mensaje genérico al usuario
    // y detenemos la ejecución del script.
    // NOTA: En producción nunca mostrar $e->getMessage() al usuario final.
    http_response_code(503); // 503 = Servicio no disponible
    die('<p style="font-family:sans-serif;color:red;text-align:center;">
            ⚠️ No se pudo conectar a la base de datos. 
            Comprueba que XAMPP está en ejecución y la BD "peluqueria" existe.
            <br><small>(' . htmlspecialchars($e->getMessage()) . ')</small>
         </p>');
}
// A partir de aquí, la variable $pdo contiene una conexión activa y lista para usar.

// ============================================================
// AUTO-CREACIÓN DE TABLAS ADICIONALES (Para despliegue)
// ============================================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `mensajes_contacto` (
          `id_mensaje` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
          `nombre` VARCHAR(160) NOT NULL,
          `email` VARCHAR(160) NOT NULL,
          `mensaje` TEXT NOT NULL,
          `fecha_envio` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id_mensaje`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");

    // Asegurarse de que reservas_web admite el estado 'finalizada'
    $pdo->exec("
        ALTER TABLE reservas_web 
        MODIFY estado ENUM('pendiente','confirmada','cancelada','finalizada') NOT NULL DEFAULT 'pendiente';
    ");
} catch (PDOException $e) {
    // Ignoramos errores de creación si el usuario no tiene permisos en este momento,
    // ya que normalmente la tabla se creará en el despliegue con el archivo SQL.
}
?>
