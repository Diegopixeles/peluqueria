<?php
// =============================================================
// ARCHIVO: panel.php
// DESCRIPCIÓN: Panel de administración de la peluquería.
//              Solo accesible tras haber iniciado sesión 
//              correctamente en login.php.
//              Muestra estadísticas y datos de la BD en tiempo real.
// =============================================================

// ---- Iniciar sesión PHP ----
// Necesario para poder leer $_SESSION y verificar si el usuario está logueado.
session_start();

// ---- Protección de acceso ----
// Si no hay sesión activa (usuario no logueado), redirige al login.
// Esto evita que alguien acceda directamente a esta URL sin haberse autenticado.
if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit(); // Detener ejecución tras la redirección
}

// ---- Incluir la conexión a la base de datos ----
require_once __DIR__ . '/conexion.php';

// ---- Manejar el Cierre de Sesión (Logout) ----
// Si se recibe el parámetro ?logout=1 en la URL, se cierra la sesión.
if (isset($_GET['logout'])) {
    session_unset();    // Borra todas las variables de sesión
    session_destroy();  // Destruye la sesión completamente
    header('Location: login.php'); // Redirige al login
    exit();
}


// ====================================================
// CONSULTAS A LA BASE DE DATOS para las estadísticas
// ====================================================

// Obtener el total de servicios activos
$stmtServicios = $pdo->query('SELECT COUNT(*) FROM servicios WHERE activo = 1');
$totalServicios = $stmtServicios->fetchColumn(); // fetchColumn() devuelve el valor de la primera columna

// Obtener el total de clientes registrados
$stmtClientes = $pdo->query('SELECT COUNT(*) FROM clientes');
$totalClientes = $stmtClientes->fetchColumn();

// Obtener el total de empleados activos
$stmtEmpleados = $pdo->query('SELECT COUNT(*) FROM empleados WHERE activo = 1');
$totalEmpleados = $stmtEmpleados->fetchColumn();

// Obtener el total de citas (de todos los estados)
$stmtCitas = $pdo->query('SELECT COUNT(*) FROM citas');
$totalCitas = $stmtCitas->fetchColumn();

// Obtener los servicios con su precio y duración (los 8 primeros activos)
$stmtListaServicios = $pdo->query(
    'SELECT nombre, descripcion, precio, duracion_minutos
     FROM servicios
     WHERE activo = 1
     ORDER BY nombre
     LIMIT 8'
);
$listaServicios = $stmtListaServicios->fetchAll(); // fetchAll() devuelve todas las filas

// Obtener los últimos 5 clientes registrados
$stmtListaClientes = $pdo->query(
    'SELECT nombre, apellidos, telefono, correo, fecha_alta
     FROM clientes
     ORDER BY fecha_alta DESC, id_cliente DESC
     LIMIT 5'
);
$listaClientes = $stmtListaClientes->fetchAll();

// Obtener los empleados activos
$stmtListaEmpleados = $pdo->query(
    'SELECT nombre, apellidos, telefono, correo, puesto
     FROM empleados
     WHERE activo = 1
     ORDER BY nombre'
);
$listaEmpleados = $stmtListaEmpleados->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración | Peluquería tecnológica</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Fuente Inter de Google Fonts -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- ====================================== -->
    <!-- BARRA DE NAVEGACIÓN SUPERIOR DEL PANEL -->
    <!-- ====================================== -->
    <nav class="bg-gray-900 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">

            <!-- Logo y nombre del panel -->
            <a href="panel.php" class="flex items-center space-x-2 min-w-0">
                <i data-lucide="scissors-square" class="w-7 h-7 text-amber-500 flex-shrink-0"></i>
                <span class="text-lg sm:text-xl font-bold truncate">Peluquería tecnológica</span>
                <span class="text-gray-400 text-sm ml-2 hidden sm:inline">— Panel Admin</span>
            </a>

            <!-- Información del usuario logueado y botón de logout -->
            <div class="flex items-center space-x-4">
                <!-- Muestra el nombre del usuario guardado en la sesión -->
                <span class="text-gray-300 text-sm hidden sm:block">
                    <i data-lucide="user-circle" class="w-4 h-4 inline mr-1"></i>
                    <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>
                    <span class="ml-1 px-2 py-0.5 bg-amber-500 text-gray-900 text-xs rounded-full font-bold">
                        <?php echo htmlspecialchars(strtoupper($_SESSION['usuario_rol'])); ?>
                    </span>
                </span>
                <!-- Botón de cerrar sesión: añade ?logout=1 a la URL -->
                <a href="panel.php?logout=1"
                   class="flex items-center px-3 py-2 rounded-lg bg-red-600 hover:bg-red-500 text-sm font-medium transition"
                   onclick="return confirm('¿Seguro que quieres cerrar sesión?')">
                    <i data-lucide="log-out" class="w-4 h-4 mr-1"></i>
                    Salir
                </a>
            </div>
        </div>
    </nav>

    <!-- ============================== -->
    <!-- CONTENIDO PRINCIPAL DEL PANEL  -->
    <!-- ============================== -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Mensaje de bienvenida personalizado con el nombre de la sesión -->
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900">
                ¡Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>! 👋
            </h1>
            <p class="text-gray-500 mt-1">Aquí tienes el resumen de la peluquería en tiempo real.</p>
        </div>

        <!-- ========================= -->
        <!-- TARJETAS DE ESTADÍSTICAS  -->
        <!-- ========================= -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

            <!-- Tarjeta: Total de Clientes -->
            <div class="bg-white rounded-2xl shadow p-6 flex items-center space-x-4">
                <div class="bg-blue-100 p-3 rounded-xl">
                    <i data-lucide="users" class="w-7 h-7 text-blue-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Clientes</p>
                    <!-- Muestra el valor obtenido de la consulta COUNT(*) -->
                    <p class="text-3xl font-extrabold text-gray-900"><?php echo $totalClientes; ?></p>
                </div>
            </div>

            <!-- Tarjeta: Total de Empleados activos -->
            <div class="bg-white rounded-2xl shadow p-6 flex items-center space-x-4">
                <div class="bg-green-100 p-3 rounded-xl">
                    <i data-lucide="user-check" class="w-7 h-7 text-green-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Empleados</p>
                    <p class="text-3xl font-extrabold text-gray-900"><?php echo $totalEmpleados; ?></p>
                </div>
            </div>

            <!-- Tarjeta: Total de Servicios activos -->
            <div class="bg-white rounded-2xl shadow p-6 flex items-center space-x-4">
                <div class="bg-amber-100 p-3 rounded-xl">
                    <i data-lucide="scissors" class="w-7 h-7 text-amber-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Servicios</p>
                    <p class="text-3xl font-extrabold text-gray-900"><?php echo $totalServicios; ?></p>
                </div>
            </div>

            <!-- Tarjeta: Total de Citas -->
            <div class="bg-white rounded-2xl shadow p-6 flex items-center space-x-4">
                <div class="bg-purple-100 p-3 rounded-xl">
                    <i data-lucide="calendar-check" class="w-7 h-7 text-purple-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Citas</p>
                    <p class="text-3xl font-extrabold text-gray-900"><?php echo $totalCitas; ?></p>
                </div>
            </div>
        </div>


        <!-- ================================================ -->
        <!-- GRID INFERIOR: SERVICIOS + CLIENTES + EMPLEADOS  -->
        <!-- ================================================ -->
        <div class="grid lg:grid-cols-3 gap-6">

            <!-- ---- TABLA: Servicios disponibles ---- -->
            <div class="bg-white rounded-2xl shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center">
                    <i data-lucide="list" class="w-5 h-5 text-amber-500 mr-2"></i>
                    <h2 class="text-lg font-bold text-gray-800">Servicios activos</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3">Servicio</th>
                                <th class="px-4 py-3 text-right">Precio</th>
                                <th class="px-4 py-3 text-right">Min</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($listaServicios)): ?>
                                <!-- Mensaje si no hay servicios en la BD -->
                                <tr>
                                    <td colspan="3" class="px-4 py-4 text-center text-gray-400">
                                        Sin servicios en la BD
                                    </td>
                                </tr>
                            <?php else: ?>
                                <!-- Recorre cada servicio obtenido de la BD -->
                                <?php foreach ($listaServicios as $servicio): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-3 font-medium text-gray-900">
                                            <?php echo htmlspecialchars($servicio['nombre']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-right text-green-700 font-semibold">
                                            <?php echo number_format($servicio['precio'], 2, ',', '.'); ?> €
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-500">
                                            <?php echo $servicio['duracion_minutos']; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ---- TABLA: Últimos clientes registrados ---- -->
            <div class="bg-white rounded-2xl shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center">
                    <i data-lucide="users" class="w-5 h-5 text-blue-500 mr-2"></i>
                    <h2 class="text-lg font-bold text-gray-800">Últimos clientes</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php if (empty($listaClientes)): ?>
                        <!-- Mensaje si no hay clientes en la BD -->
                        <p class="px-6 py-4 text-center text-gray-400 text-sm">Sin clientes todavía</p>
                    <?php else: ?>
                        <?php foreach ($listaClientes as $cliente): ?>
                            <div class="px-6 py-4">
                                <!-- Nombre y apellidos del cliente -->
                                <p class="font-semibold text-gray-900">
                                    <?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellidos']); ?>
                                </p>
                                <!-- Teléfono y correo -->
                                <p class="text-xs text-gray-500 mt-0.5">
                                    📞 <?php echo htmlspecialchars($cliente['telefono']); ?>
                                    <?php if ($cliente['correo']): ?>
                                        · ✉️ <?php echo htmlspecialchars($cliente['correo']); ?>
                                    <?php endif; ?>
                                </p>
                                <!-- Fecha de alta del cliente -->
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Alta: <?php echo htmlspecialchars($cliente['fecha_alta']); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ---- TABLA: Empleados activos ---- -->
            <div class="bg-white rounded-2xl shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center">
                    <i data-lucide="user-check" class="w-5 h-5 text-green-500 mr-2"></i>
                    <h2 class="text-lg font-bold text-gray-800">Empleados</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php if (empty($listaEmpleados)): ?>
                        <!-- Mensaje si no hay empleados en la BD -->
                        <p class="px-6 py-4 text-center text-gray-400 text-sm">Sin empleados registrados</p>
                    <?php else: ?>
                        <?php foreach ($listaEmpleados as $empleado): ?>
                            <div class="px-6 py-4 flex items-center space-x-3">
                                <!-- Avatar con la inicial del nombre -->
                                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                                    <span class="text-amber-600 font-bold text-sm">
                                        <?php echo mb_strtoupper(mb_substr($empleado['nombre'], 0, 1)); ?>
                                    </span>
                                </div>
                                <div>
                                    <!-- Nombre completo del empleado -->
                                    <p class="font-semibold text-gray-900 text-sm">
                                        <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellidos']); ?>
                                    </p>
                                    <!-- Puesto del empleado con la primera letra en mayúscula -->
                                    <p class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars(ucfirst($empleado['puesto'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- Fin del grid -->

        <!-- Enlace para volver a la página pública -->
        <div class="mt-8 text-center">
            <a href="/"
               class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 transition">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>
                Ver página web pública
            </a>
        </div>

    </main><!-- Fin del main -->

    <!-- Librería de iconos Lucide -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        // Inicializar todos los iconos data-lucide del DOM
        lucide.createIcons();
    </script>
</body>
</html>
