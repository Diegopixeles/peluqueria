<?php
// =============================================================
// DESCRIPCIÓN: Panel de administración de la peluquería.
//              Solo accesible tras haber iniciado sesión 
//              correctamente en login.php.
//              Muestra estadísticas y datos de la BD en tiempo real.
// =============================================================

// ---- Iniciar sesión PHP ----
session_start();

// ---- Protección de acceso ----
if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// ---- Incluir la conexión a la base de datos ----
require_once __DIR__ . '/conexion.php';

// ---- Manejar el Cierre de Sesión (Logout) ----
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

// ====================================================
// AUTO-ACTUALIZACIÓN DE CITAS PASADAS A "FINALIZADA"
// ====================================================
// En lugar de borrarlas, las citas cuya hora haya pasado se marcan como finalizadas.
$pdo->exec("
    UPDATE reservas_web rw
    JOIN servicios s ON rw.id_servicio = s.id_servicio
    SET rw.estado = 'finalizada'
    WHERE TIMESTAMP(rw.fecha, rw.hora_inicio) < DATE_SUB(NOW(), INTERVAL s.duracion_minutos MINUTE)
    AND rw.estado != 'finalizada'
");

// ====================================================
// MANEJO DE CRUD DE SERVICIOS
// ====================================================
$error_mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    try {
        if ($action === 'crear_servicio') {
            $stmt = $pdo->prepare('INSERT INTO servicios (nombre, descripcion, precio, duracion_minutos, activo) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$_POST['nombre'], $_POST['descripcion'], $_POST['precio'], $_POST['duracion_minutos']]);
        } elseif ($action === 'editar_servicio') {
            $stmt = $pdo->prepare('UPDATE servicios SET nombre = ?, descripcion = ?, precio = ?, duracion_minutos = ? WHERE id_servicio = ?');
            $stmt->execute([$_POST['nombre'], $_POST['descripcion'], $_POST['precio'], $_POST['duracion_minutos'], $_POST['id_servicio']]);
        } elseif ($action === 'borrar_servicio') {
            try {
                // Intentar borrado físico
                $stmt = $pdo->prepare('DELETE FROM servicios WHERE id_servicio = ?');
                $stmt->execute([$_POST['id_servicio']]);
            } catch (PDOException $e) {
                // 23000 = constraint violation (está siendo usado en citas)
                if ($e->getCode() == '23000') {
                    // Borrado lógico (desactivar)
                    $stmt = $pdo->prepare('UPDATE servicios SET activo = 0 WHERE id_servicio = ?');
                    $stmt->execute([$_POST['id_servicio']]);
                } else {
                    throw $e;
                }
            }
        } elseif ($action === 'borrar_mensaje') {
            $stmt = $pdo->prepare('DELETE FROM mensajes_contacto WHERE id_mensaje = ?');
            $stmt->execute([$_POST['id_mensaje']]);
        }
        // Redirigir para evitar reenvío de formulario al recargar
        header('Location: panel.php');
        exit();
    } catch (Exception $e) {
        $error_mensaje = "Error: " . $e->getMessage();
    }
}

// ====================================================
// CONSULTAS A LA BASE DE DATOS
// ====================================================

// Obtener totales
$totalServicios = $pdo->query('SELECT COUNT(*) FROM servicios WHERE activo = 1')->fetchColumn();
$totalClientes = $pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
$totalEmpleados = $pdo->query('SELECT COUNT(*) FROM empleados WHERE activo = 1')->fetchColumn();
$totalCitas = $pdo->query('SELECT COUNT(*) FROM reservas_web')->fetchColumn(); // Actualizado para contar reservas_web en vez de la tabla compleja 'citas' si se usa reservas_web

// Obtener TODOS los servicios activos
$stmtListaServicios = $pdo->query(
    'SELECT id_servicio, nombre, descripcion, precio, duracion_minutos
     FROM servicios
     WHERE activo = 1
     ORDER BY nombre'
);
$listaServicios = $stmtListaServicios->fetchAll();

// Obtener el historial de las últimas 5 citas finalizadas
$stmtHistorialCitas = $pdo->query(
    "SELECT rw.nombre AS cliente_nombre, rw.fecha, rw.hora_inicio, s.nombre AS servicio_nombre
     FROM reservas_web rw
     JOIN servicios s ON rw.id_servicio = s.id_servicio
     WHERE rw.estado = 'finalizada'
     ORDER BY rw.fecha DESC, rw.hora_inicio DESC
     LIMIT 5"
);
$historialCitas = $stmtHistorialCitas->fetchAll();

// Obtener los empleados activos
$stmtListaEmpleados = $pdo->query(
    'SELECT nombre, apellidos, telefono, correo, puesto
     FROM empleados
     WHERE activo = 1
     ORDER BY nombre'
);
$listaEmpleados = $stmtListaEmpleados->fetchAll();

// Obtener próximas citas
$stmtProximasCitas = $pdo->query(
    "SELECT r.id_reserva, r.nombre AS cliente_nombre, r.telefono, r.fecha, r.hora_inicio, s.nombre AS servicio_nombre, s.duracion_minutos
     FROM reservas_web r
     JOIN servicios s ON r.id_servicio = s.id_servicio
     WHERE TIMESTAMP(r.fecha, r.hora_inicio) >= DATE_SUB(NOW(), INTERVAL s.duracion_minutos MINUTE)
     ORDER BY r.fecha ASC, r.hora_inicio ASC
     LIMIT 15"
);
$proximasCitas = $stmtProximasCitas->fetchAll();

// Obtener mensajes de contacto
try {
    $stmtMensajes = $pdo->query(
        "SELECT id_mensaje, nombre, email, mensaje, fecha_envio
         FROM mensajes_contacto
         ORDER BY fecha_envio DESC"
    );
    $listaMensajes = $stmtMensajes->fetchAll();
} catch (PDOException $e) {
    // Si la tabla aún no existe, devuelve array vacío
    $listaMensajes = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración | Peluquería tecnológica</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        /* Utilidad para modales ocultos */
        .modal-hidden { display: none !important; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- BARRA DE NAVEGACIÓN -->
    <nav class="bg-gray-900 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <a href="panel.php" class="flex items-center space-x-2 min-w-0">
                <i data-lucide="scissors-square" class="w-7 h-7 text-amber-500 flex-shrink-0"></i>
                <span class="text-lg sm:text-xl font-bold truncate">Peluquería tecnológica</span>
                <span class="text-gray-400 text-sm ml-2 hidden sm:inline">— Panel Admin</span>
            </a>
            <div class="flex items-center space-x-4">
                <span class="text-gray-300 text-sm hidden sm:block">
                    <i data-lucide="user-circle" class="w-4 h-4 inline mr-1"></i>
                    <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>
                    <span class="ml-1 px-2 py-0.5 bg-amber-500 text-gray-900 text-xs rounded-full font-bold">
                        <?php echo htmlspecialchars(strtoupper($_SESSION['usuario_rol'])); ?>
                    </span>
                </span>
                <a href="panel.php?logout=1"
                   class="flex items-center px-3 py-2 rounded-lg bg-red-600 hover:bg-red-500 text-sm font-medium transition"
                   onclick="return confirm('¿Seguro que quieres cerrar sesión?')">
                    <i data-lucide="log-out" class="w-4 h-4 mr-1"></i>
                    Salir
                </a>
            </div>
        </div>
    </nav>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <?php if ($error_mensaje): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_mensaje); ?></span>
            </div>
        <?php endif; ?>

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900">
                ¡Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>! 👋
            </h1>
            <p class="text-gray-500 mt-1">Aquí tienes el resumen de la peluquería en tiempo real.</p>
        </div>

        <!-- TARJETAS DE ESTADÍSTICAS -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-2xl shadow p-6 flex items-center space-x-4">
                <div class="bg-blue-100 p-3 rounded-xl"><i data-lucide="users" class="w-7 h-7 text-blue-600"></i></div>
                <div>
                    <p class="text-sm text-gray-500">Clientes</p>
                    <p class="text-3xl font-extrabold text-gray-900"><?php echo $totalClientes; ?></p>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow p-6 flex items-center space-x-4">
                <div class="bg-green-100 p-3 rounded-xl"><i data-lucide="user-check" class="w-7 h-7 text-green-600"></i></div>
                <div>
                    <p class="text-sm text-gray-500">Empleados</p>
                    <p class="text-3xl font-extrabold text-gray-900"><?php echo $totalEmpleados; ?></p>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow p-6 flex items-center space-x-4">
                <div class="bg-amber-100 p-3 rounded-xl"><i data-lucide="scissors" class="w-7 h-7 text-amber-600"></i></div>
                <div>
                    <p class="text-sm text-gray-500">Servicios</p>
                    <p class="text-3xl font-extrabold text-gray-900"><?php echo $totalServicios; ?></p>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow p-6 flex items-center space-x-4">
                <div class="bg-purple-100 p-3 rounded-xl"><i data-lucide="calendar-check" class="w-7 h-7 text-purple-600"></i></div>
                <div>
                    <p class="text-sm text-gray-500">Citas Pendientes</p>
                    <p class="text-3xl font-extrabold text-gray-900"><?php echo $totalCitas; ?></p>
                </div>
            </div>
        </div>

        <!-- SECCIÓN: PRÓXIMAS CITAS -->
        <div class="bg-white rounded-2xl shadow overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center">
                <i data-lucide="clock" class="w-5 h-5 text-purple-500 mr-2"></i>
                <h2 class="text-lg font-bold text-gray-800">Próximas Citas (Se borran automáticamente al terminar)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Servicio</th>
                            <th class="px-4 py-3">Fecha y Hora</th>
                            <th class="px-4 py-3 text-right">Duración</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($proximasCitas)): ?>
                            <tr><td colspan="4" class="px-4 py-4 text-center text-gray-400">No hay citas próximas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($proximasCitas as $cita): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($cita['cliente_nombre']); ?></p>
                                        <p class="text-xs text-gray-500">📞 <?php echo htmlspecialchars($cita['telefono']); ?></p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700"><?php echo htmlspecialchars($cita['servicio_nombre']); ?></td>
                                    <td class="px-4 py-3 font-semibold text-gray-800">
                                        <?php echo htmlspecialchars($cita['fecha']); ?> - <?php echo htmlspecialchars(substr($cita['hora_inicio'], 0, 5)); ?>
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-500"><?php echo htmlspecialchars($cita['duracion_minutos']); ?> min</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECCIÓN: MENSAJES DE CONTACTO -->
        <div class="bg-white rounded-2xl shadow overflow-hidden mb-8 border border-amber-100">
            <div class="px-6 py-4 border-b border-amber-100 flex items-center bg-amber-50">
                <i data-lucide="mail" class="w-5 h-5 text-amber-500 mr-2"></i>
                <h2 class="text-lg font-bold text-gray-800">Mensajes y Dudas de Clientes</h2>
            </div>
            <div class="divide-y divide-gray-100">
                <?php if (empty($listaMensajes)): ?>
                    <p class="px-6 py-8 text-center text-gray-400">No hay mensajes nuevos.</p>
                <?php else: ?>
                    <?php foreach ($listaMensajes as $msj): ?>
                        <div class="px-6 py-4 flex flex-col sm:flex-row justify-between items-start hover:bg-gray-50 transition">
                            <div class="mb-3 sm:mb-0">
                                <div class="flex items-baseline space-x-2">
                                    <h4 class="font-bold text-gray-900"><?php echo htmlspecialchars($msj['nombre']); ?></h4>
                                    <a href="mailto:<?php echo htmlspecialchars($msj['email']); ?>" class="text-sm text-blue-600 hover:underline">
                                        <?php echo htmlspecialchars($msj['email']); ?>
                                    </a>
                                </div>
                                <p class="text-xs text-gray-400 mt-1 mb-2">Recibido: <?php echo htmlspecialchars($msj['fecha_envio']); ?></p>
                                <p class="text-gray-700 text-sm bg-gray-100 p-3 rounded-lg border border-gray-200"><?php echo nl2br(htmlspecialchars($msj['mensaje'])); ?></p>
                            </div>
                            <div class="ml-0 sm:ml-4 flex-shrink-0 mt-2 sm:mt-0">
                                <form method="POST" action="panel.php" onsubmit="return confirm('¿Borrar definitivamente este mensaje?');">
                                    <input type="hidden" name="action" value="borrar_mensaje">
                                    <input type="hidden" name="id_mensaje" value="<?php echo $msj['id_mensaje']; ?>">
                                    <button type="submit" class="flex items-center text-xs font-medium text-red-600 hover:text-red-800 px-3 py-1.5 border border-red-200 rounded-md hover:bg-red-50 transition">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5 mr-1"></i> Borrar
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- GRID INFERIOR: SERVICIOS + CLIENTES + EMPLEADOS -->
        <div class="grid lg:grid-cols-3 gap-6">

            <!-- TABLA: Servicios disponibles con CRUD -->
            <div class="bg-white rounded-2xl shadow overflow-hidden lg:col-span-2">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center">
                        <i data-lucide="list" class="w-5 h-5 text-amber-500 mr-2"></i>
                        <h2 class="text-lg font-bold text-gray-800">Servicios activos</h2>
                    </div>
                    <!-- Botón para añadir servicio -->
                    <button onclick="abrirModalServicio()" class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 rounded text-sm font-bold flex items-center transition">
                        <i data-lucide="plus" class="w-4 h-4 mr-1"></i> Añadir
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3">Servicio</th>
                                <th class="px-4 py-3 text-right">Precio</th>
                                <th class="px-4 py-3 text-right">Min</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($listaServicios)): ?>
                                <tr><td colspan="4" class="px-4 py-4 text-center text-gray-400">Sin servicios en la BD</td></tr>
                            <?php else: ?>
                                <?php foreach ($listaServicios as $servicio): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-3 font-medium text-gray-900">
                                            <?php echo htmlspecialchars($servicio['nombre']); ?>
                                            <p class="text-xs text-gray-500 font-normal"><?php echo htmlspecialchars(substr($servicio['descripcion'], 0, 50)); ?>...</p>
                                        </td>
                                        <td class="px-4 py-3 text-right text-green-700 font-semibold">
                                            <?php echo number_format($servicio['precio'], 2, ',', '.'); ?> €
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-500">
                                            <?php echo $servicio['duracion_minutos']; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <!-- Botón Editar -->
                                            <button onclick='abrirModalServicio(<?php echo json_encode($servicio); ?>)' class="text-blue-600 hover:text-blue-800 p-1 rounded transition" title="Editar">
                                                <i data-lucide="edit-2" class="w-4 h-4"></i>
                                            </button>
                                            
                                            <!-- Formulario para Borrar -->
                                            <form method="POST" action="panel.php" class="inline-block" onsubmit="return confirm('¿Seguro que deseas eliminar este servicio? Si ya tiene citas, se marcará como inactivo.');">
                                                <input type="hidden" name="action" value="borrar_servicio">
                                                <input type="hidden" name="id_servicio" value="<?php echo $servicio['id_servicio']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 p-1 rounded transition" title="Borrar">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TABLA: Historial de Citas Finalizadas -->
            <div class="bg-white rounded-2xl shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center">
                    <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-2"></i>
                    <h2 class="text-lg font-bold text-gray-800">Últimas citas finalizadas</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php if (empty($historialCitas)): ?>
                        <p class="px-6 py-4 text-center text-gray-400 text-sm">No hay historial de citas</p>
                    <?php else: ?>
                        <?php foreach ($historialCitas as $citaFin): ?>
                            <div class="px-6 py-4">
                                <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($citaFin['cliente_nombre']); ?></p>
                                <p class="text-sm text-gray-600 mt-0.5"><?php echo htmlspecialchars($citaFin['servicio_nombre']); ?></p>
                                <p class="text-xs text-gray-400 mt-1">📅 <?php echo date('d/m/Y', strtotime($citaFin['fecha'])); ?> a las <?php echo date('H:i', strtotime($citaFin['hora_inicio'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- Fin del grid -->

        <div class="mt-8 text-center">
            <a href="/" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 transition">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Ver página web pública
            </a>
        </div>

    </main>

    <!-- ============================================== -->
    <!-- MODAL PARA CREAR / EDITAR SERVICIOS            -->
    <!-- ============================================== -->
    <div id="modalServicio" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 modal-hidden backdrop-blur-sm transition-opacity duration-300">
        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-md m-4 transform scale-100 transition-transform">
            <div class="flex justify-between items-center mb-5">
                <h3 id="modalServicioTitulo" class="text-xl font-bold text-gray-900">Añadir Servicio</h3>
                <button onclick="cerrarModalServicio()" class="text-gray-400 hover:text-gray-700 transition">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <form id="formServicio" method="POST" action="panel.php">
                <input type="hidden" name="action" id="modalAction" value="crear_servicio">
                <input type="hidden" name="id_servicio" id="modalIdServicio" value="">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                        <input type="text" name="nombre" id="modalNombre" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                        <textarea name="descripcion" id="modalDescripcion" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-amber-500 focus:border-amber-500"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Precio (€)</label>
                            <input type="number" step="0.01" name="precio" id="modalPrecio" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Duración (min)</label>
                            <input type="number" name="duracion_minutos" id="modalDuracion" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="cerrarModalServicio()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition">Cancelar</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Librería de iconos Lucide -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        // Lógica del Modal de Servicios
        const modal = document.getElementById('modalServicio');
        const titulo = document.getElementById('modalServicioTitulo');
        const inputAction = document.getElementById('modalAction');
        const inputId = document.getElementById('modalIdServicio');
        const inputNombre = document.getElementById('modalNombre');
        const inputDesc = document.getElementById('modalDescripcion');
        const inputPrecio = document.getElementById('modalPrecio');
        const inputDuracion = document.getElementById('modalDuracion');

        function abrirModalServicio(servicio = null) {
            if (servicio) {
                // Modo Editar
                titulo.innerText = "Editar Servicio";
                inputAction.value = "editar_servicio";
                inputId.value = servicio.id_servicio;
                inputNombre.value = servicio.nombre;
                inputDesc.value = servicio.descripcion;
                inputPrecio.value = servicio.precio;
                inputDuracion.value = servicio.duracion_minutos;
            } else {
                // Modo Crear
                titulo.innerText = "Añadir Servicio";
                inputAction.value = "crear_servicio";
                inputId.value = "";
                document.getElementById('formServicio').reset();
            }
            modal.classList.remove('modal-hidden');
        }

        function cerrarModalServicio() {
            modal.classList.add('modal-hidden');
        }
    </script>
</body>
</html>
