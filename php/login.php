<?php
// =============================================================
// ARCHIVO: login.php
// DESCRIPCIÓN: Página de acceso al panel de administración.
//              Autentica al usuario contra la tabla `usuarios`
//              de la base de datos usando PDO y bcrypt.
//              Si el login es correcto, inicia una sesión PHP
//              y redirige al panel de administración.
// =============================================================

// ---- Iniciar sesión PHP ----
// session_start() debe ser lo primero antes de cualquier salida HTML.
// Permite usar $_SESSION para guardar datos entre páginas.
session_start();

// ---- Incluir la conexión a la base de datos ----
// El archivo conexion.php crea la variable $pdo lista para usar.
require_once __DIR__ . '/conexion.php';

// ---- Variables de mensajes de error/éxito ----
$error_message   = '';
$success_message = '';

// ---- Procesar el formulario solo si se envió por POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recoge y limpia los campos del formulario
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';  // No se hace trim a la contraseña

    // --- Validación básica de campos vacíos ---
    if (empty($email) || empty($password)) {
        $error_message = 'Por favor, rellena todos los campos.';

    } else {
        // --- Consulta a la base de datos ---
        // Buscamos un usuario activo con ese email.
        // Usamos una consulta preparada para evitar inyección SQL.
        $stmt = $pdo->prepare(
            'SELECT id_usuario, nombre, email, password_hash, rol
             FROM usuarios
             WHERE email = :email AND activo = 1
             LIMIT 1'
        );
        // Enlazamos el parámetro :email con el valor recibido del formulario
        $stmt->execute([':email' => $email]);

        // Intentamos obtener el registro del usuario
        $usuario = $stmt->fetch();

        // --- Verificación de credenciales ---
        // password_verify() compara la contraseña en texto plano con el hash
        // almacenado en la BD. Nunca se almacena la contraseña en texto plano.
        if ($usuario && password_verify($password, $usuario['password_hash'])) {

            // LOGIN CORRECTO: guardamos los datos del usuario en la sesión
            $_SESSION['usuario_id']     = $usuario['id_usuario'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_email']  = $usuario['email'];
            $_SESSION['usuario_rol']    = $usuario['rol'];

            // Redirigimos al panel de administración
            // header('Location: ...') envía al navegador a otra página
            header('Location: panel.php');
            exit(); // Importante: detener la ejecución tras la redirección

        } else {
            // LOGIN INCORRECTO: credenciales inválidas
            // Mensaje genérico para no revelar si el email existe o no
            $error_message = 'Credenciales incorrectas. Inténtalo de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Profesional | Peluquería tecnológica</title>
    <!-- Framework CSS de utilidades para el diseño -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Hoja de estilos propia del login (fondo, círculos decorativos, etc.) -->
    <link rel="stylesheet" href="/style/login.css">
</head>
<!-- bg-login y min-h-screen: fondo oscuro a pantalla completa, centrado -->
<body class="bg-login min-h-screen flex items-center justify-center p-4">

    <!-- Círculos decorativos del fondo (definidos en login.css) -->
    <div class="circle-1"></div>
    <div class="circle-2"></div>

    <!-- Contenedor principal del formulario -->
    <div class="max-w-md w-full relative z-10">

        <!-- ---- Área del Logo / Cabecera ---- -->
        <div class="text-center mb-8">
            <!-- Icono rotado con animación hover a posición normal -->
            <div class="inline-flex items-center justify-center w-20 h-20 bg-amber-500 rounded-2xl shadow-lg mb-4 transform -rotate-6 transition-transform hover:rotate-0 duration-300">
                <i data-lucide="scissors" class="w-10 h-10 text-gray-900"></i>
            </div>
            <h1 class="text-3xl font-bold text-white tracking-tight">Peluquería tecnológica</h1>
            <p class="text-gray-400 text-sm mt-1">Panel de Administración</p>
        </div>

        <!-- ---- Tarjeta del formulario de login ---- -->
        <div class="bg-gray-800/50 backdrop-blur-xl border border-gray-700 p-8 rounded-3xl shadow-2xl">

            <!-- Mensaje de ERROR (solo se muestra si hay error) -->
            <?php if ($error_message): ?>
                <div class="mb-6 bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-3 rounded-xl text-sm flex items-center">
                    <i data-lucide="alert-circle" class="w-4 h-4 mr-2 flex-shrink-0"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Mensaje de ÉXITO (solo se muestra si el login fue correcto) -->
            <?php if ($success_message): ?>
                <div class="mb-6 bg-green-500/10 border border-green-500/50 text-green-400 px-4 py-3 rounded-xl text-sm flex items-center animate-pulse">
                    <i data-lucide="check-circle" class="w-4 h-4 mr-2 flex-shrink-0"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- ---- Formulario de Login ---- -->
            <!-- action="login.php": envía los datos a este mismo archivo -->
            <!-- method="POST": los datos no aparecen en la URL -->
            <form action="login.php" method="POST" class="space-y-5">

                <!-- Campo de Correo Electrónico -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                        Correo Electrónico
                    </label>
                    <div class="relative">
                        <!-- Icono dentro del campo -->
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                            <i data-lucide="mail" class="w-5 h-5"></i>
                        </span>
                        <!-- Mantiene el email escrito si el login falla -->
                        <input type="email" id="email" name="email" required
                               class="block w-full pl-10 pr-3 py-3 border border-gray-600 rounded-xl bg-gray-900/50 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all"
                               placeholder="tu@email.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Campo de Contraseña -->
                <div>
                    <div class="flex justify-between mb-2">
                        <label for="password" class="text-sm font-medium text-gray-300">Contraseña</label>
                        <a href="#" class="text-xs text-amber-500 hover:text-amber-400 transition">¿Olvidaste tu contraseña?</a>
                    </div>
                    <div class="relative">
                        <!-- Icono dentro del campo -->
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                            <i data-lucide="lock" class="w-5 h-5"></i>
                        </span>
                        <input type="password" id="password" name="password" required
                               class="block w-full pl-10 pr-3 py-3 border border-gray-600 rounded-xl bg-gray-900/50 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all"
                               placeholder="••••••••">
                    </div>
                </div>

                <!-- Checkbox "Recordarme" -->
                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox"
                           class="h-4 w-4 text-amber-500 focus:ring-amber-500 border-gray-600 rounded bg-gray-900">
                    <label for="remember-me" class="ml-2 block text-sm text-gray-400">
                        Recordar sesión en este equipo
                    </label>
                </div>

                <!-- Botón de enviar -->
                <button type="submit"
                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-lg text-sm font-bold text-gray-900 bg-amber-500 hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-all transform active:scale-95">
                    Entrar al Panel
                </button>
            </form>

            <!-- Enlace de registro (deshabilitado, a modo informativo) -->
            <div class="mt-8 pt-6 border-t border-gray-700 text-center">
                <p class="text-sm text-gray-400">
                    ¿No tienes una cuenta?
                    <a href="#" class="font-semibold text-amber-500 hover:text-amber-400">Solicitar acceso</a>
                </p>
            </div>
        </div>

        <!-- Enlace para volver al sitio web principal -->
        <div class="text-center mt-8">
            <a href="/" class="inline-flex items-center text-sm text-gray-500 hover:text-white transition">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                Volver a la página principal
            </a>
        </div>
    </div>

    <!-- Librería de iconos Lucide -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        // Inicializar todos los iconos data-lucide del DOM
        lucide.createIcons();
    </script>
</body>
</html>