<?php
// =============================================================
// DESCRIPCIÓN: Página principal de la web de la peluquería.
//              Debe ser un archivo .php (no .html) para que
//              el servidor ejecute el código PHP embebido.
//              Carga los servicios desde la base de datos y
//              procesa el formulario de contacto.
// =============================================================

// ---- Incluir la conexión a la BD ----
// Crea la variable $pdo con la conexión activa.
require_once __DIR__ . '/../php/conexion.php';

// ---- Incluir funciones y lógica del formulario ----
// Este archivo define: $servicios, generarTarjetasServicios(),
// obtenerServiciosDB() y $mensaje_formulario.
require_once __DIR__ . '/../php/funciones.php';

// ---- Obtener los servicios desde la base de datos ----
// Si la BD tiene datos, los usa. Si falla, usa el array de respaldo $servicios.
$serviciosParaMostrar = obtenerServiciosDB($pdo);
if (empty($serviciosParaMostrar)) {
    // Si la BD no devuelve nada, usamos el array definido en funciones.php
    $serviciosParaMostrar = $servicios;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peluquería tecnologica | Peluquería y Estilismo</title>
    <!-- Framework CSS Tailwind (via CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Fuente Inter de Google Fonts para tipografía moderna */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { font-family: 'Inter', sans-serif; }

        /* Clase personalizada para la sección hero con imagen de fondo */
        .bg-hero {
            /* Imagen de fondo de la fachada (generada con IA) */
            background-image: url('./Imagenes/Gemini_Generated_Fachada.png');
            background-size: cover;      /* Cubre toda la sección */
            background-position: center; /* Centrada */
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Librería de iconos Lucide (se inicializa al final del body) -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- ====================================== -->
    <!-- CABECERA Y NAVEGACIÓN (Sticky al top)  -->
    <!-- ====================================== -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">

            <!-- Logo: icono + nombre del negocio -->
            <a href="#inicio" class="text-2xl sm:text-3xl font-extrabold text-gray-900 flex items-center">
                <i data-lucide="scissors-square" class="w-7 h-7 text-amber-500 mr-2"></i>
                <span class="hidden sm:inline">Peluquería tecnológica</span>
                <span class="sm:hidden">Pelutería Tec.</span>
            </a>

            <!-- Menú de navegación (solo visible en pantallas medianas y grandes) -->
            <nav class="hidden md:flex space-x-8">
                <a href="#inicio"    class="text-gray-600 hover:text-amber-600 transition duration-150">Inicio</a>
                <a href="#servicios" class="text-gray-600 hover:text-amber-600 transition duration-150">Servicios</a>
                <a href="#galeria"   class="text-gray-600 hover:text-amber-600 transition duration-150">Galería</a>
                <a href="#contacto"  class="text-gray-600 hover:text-amber-600 transition duration-150">Contacto</a>
                <!-- Enlace a la página de reservas en línea -->
                <a href="/php/reservar.php" class="text-amber-600 hover:text-amber-700 font-semibold transition duration-150">Reservar</a>
                <!-- Enlace al panel de login -->
                <a href="/php/login.php" class="text-gray-600 hover:text-amber-600 transition duration-150">Login</a>
            </nav>

            <!-- Botón de reserva online (escritorio) -->
            <a href="/php/reservar.php"
               class="hidden md:inline-flex items-center px-4 py-2 text-sm font-medium rounded-full text-white bg-amber-500 hover:bg-amber-600 shadow-md transition duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Reservar Online
            </a>

            <!-- Botón hamburguesa (solo móvil) -->
            <button id="btn-menu-movil" class="md:hidden text-gray-600 hover:text-amber-600 p-2 rounded-lg" aria-label="Menú">
                <i data-lucide="menu" class="w-6 h-6" id="icon-menu"></i>
            </button>
        </div>

        <!-- Menú desplegable móvil -->
        <div id="menu-movil" class="hidden md:hidden border-t border-gray-100 bg-white">
            <div class="px-4 py-3 space-y-1">
                <a href="#inicio"    class="block py-2 px-3 rounded-lg text-gray-600 hover:bg-amber-50 hover:text-amber-600 transition" onclick="cerrarMenu()">Inicio</a>
                <a href="#servicios" class="block py-2 px-3 rounded-lg text-gray-600 hover:bg-amber-50 hover:text-amber-600 transition" onclick="cerrarMenu()">Servicios</a>
                <a href="#galeria"   class="block py-2 px-3 rounded-lg text-gray-600 hover:bg-amber-50 hover:text-amber-600 transition" onclick="cerrarMenu()">Galería</a>
                <a href="#contacto"  class="block py-2 px-3 rounded-lg text-gray-600 hover:bg-amber-50 hover:text-amber-600 transition" onclick="cerrarMenu()">Contacto</a>
                <a href="/php/login.php" class="block py-2 px-3 rounded-lg text-gray-600 hover:bg-amber-50 hover:text-amber-600 transition">Login</a>
                <a href="/php/reservar.php" class="block py-2.5 px-3 rounded-lg text-center font-bold text-white bg-amber-500 hover:bg-amber-600 transition mt-2">Reservar Online</a>
            </div>
        </div>
    </header>

    <script>
        // Toggle menu hamburguesa
        const btnMenu = document.getElementById('btn-menu-movil');
        const menuMovil = document.getElementById('menu-movil');
        const iconMenu = document.getElementById('icon-menu');
        let menuAbierto = false;

        btnMenu.addEventListener('click', () => {
            menuAbierto = !menuAbierto;
            menuMovil.classList.toggle('hidden', !menuAbierto);
            iconMenu.setAttribute('data-lucide', menuAbierto ? 'x' : 'menu');
            lucide.createIcons();
        });

        function cerrarMenu() {
            menuAbierto = false;
            menuMovil.classList.add('hidden');
            iconMenu.setAttribute('data-lucide', 'menu');
            lucide.createIcons();
        }
    </script>

    <!-- =============================== -->
    <!-- SECCIÓN HERO (Imagen principal) -->
    <!-- =============================== -->
    <section id="inicio" class="bg-hero h-[70vh] sm:h-[80vh] flex items-center relative rounded-b-3xl overflow-hidden shadow-inner">
        <!-- Overlay oscuro semitransparente sobre la imagen de fondo -->
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10 w-full">
            <h1 class="text-4xl sm:text-5xl lg:text-7xl font-extrabold text-white mb-4 leading-tight">
                El Arte de la Belleza Capilar
            </h1>
            <p class="text-base sm:text-xl text-gray-100 mb-8 max-w-xl mx-auto">
                Donde el estilo se encuentra con la perfección. Descubre tu mejor versión.
            </p>
            <!-- Botones del hero -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/php/reservar.php"
                   class="inline-flex items-center px-8 py-3 text-lg font-bold rounded-full text-gray-900 bg-amber-400 hover:bg-amber-300 transition duration-300 shadow-xl transform hover:scale-105">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Reservar Cita Online
                </a>
                <a href="#servicios"
                   class="inline-flex px-8 py-3 text-lg font-bold rounded-full text-white bg-white/20 hover:bg-white/30 border border-white/40 transition duration-300 shadow-xl transform hover:scale-105 backdrop-blur-sm">
                    Ver Servicios
                </a>
            </div>
        </div>
    </section>

    <!-- ============================================== -->
    <!-- SECCIÓN DE SERVICIOS (datos cargados desde BD) -->
    <!-- ============================================== -->
    <section id="servicios" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-extrabold text-gray-900 mb-4">Nuestra Experiencia</h2>
            <p class="text-lg text-gray-600 mb-12">
                Ofrecemos un catálogo completo de servicios para mimar tu cabello y definir tu imagen.
            </p>

            <!-- Grid de tarjetas de servicios (4 columnas en pantalla grande) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <?php
                    // Llama a la función que genera el HTML de las tarjetas
                    // usando los datos obtenidos de la BD (o el array de respaldo)
                    echo generarTarjetasServicios($serviciosParaMostrar);
                ?>
            </div>
        </div>
    </section>

    <!-- ========================== -->
    <!-- SECCIÓN GALERÍA (mockup)   -->
    <!-- ========================== -->
    <section id="galeria" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-extrabold text-gray-900 mb-4">Momentos Capturados</h2>
            <p class="text-lg text-gray-600 mb-12">Inspírate con nuestros últimos trabajos en corte y coloración.</p>

            <!-- Grid de 6 imágenes de galería (placeholder de colores) -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 rounded-xl overflow-hidden">
                <div class="aspect-square overflow-hidden rounded-xl shadow-lg hover:opacity-75 transition duration-300 relative group">
                    <img src="../peinados/corte femenino.png" alt="Corte Femenino" class="object-cover w-full h-full">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                        <span class="text-white font-bold text-lg opacity-0 group-hover:opacity-100 transition drop-shadow-md">Corte Femenino</span>
                    </div>
                </div>
                <div class="aspect-square overflow-hidden rounded-xl shadow-lg hover:opacity-75 transition duration-300 relative group">
                    <img src="../peinados/corte de barba.png" alt="Barbería Clásica" class="object-cover w-full h-full">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                        <span class="text-white font-bold text-lg opacity-0 group-hover:opacity-100 transition drop-shadow-md">Barbería Clásica</span>
                    </div>
                </div>
                <div class="aspect-square overflow-hidden rounded-xl shadow-lg hover:opacity-75 transition duration-300 relative group">
                    <img src="../peinados/decoloración.png" alt="Decoloraciones" class="object-cover w-full h-full">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                        <span class="text-white font-bold text-lg opacity-0 group-hover:opacity-100 transition drop-shadow-md">Decoloraciones</span>
                    </div>
                </div>
                <div class="aspect-square overflow-hidden rounded-xl shadow-lg hover:opacity-75 transition duration-300 relative group">
                    <img src="../peinados/de evento.png" alt="Peinado de Evento" class="object-cover w-full h-full">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                        <span class="text-white font-bold text-lg opacity-0 group-hover:opacity-100 transition drop-shadow-md">Peinado de Evento</span>
                    </div>
                </div>
                <div class="aspect-square overflow-hidden rounded-xl shadow-lg hover:opacity-75 transition duration-300 relative group">
                    <img src="../peinados/corte masculino.png" alt="Corte Masculino" class="object-cover w-full h-full">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                        <span class="text-white font-bold text-lg opacity-0 group-hover:opacity-100 transition drop-shadow-md">Corte Masculino</span>
                    </div>
                </div>
                <div class="aspect-square overflow-hidden rounded-xl shadow-lg hover:opacity-75 transition duration-300 relative group">
                    <img src="../peinados/trenzas.png" alt="Trenzas" class="object-cover w-full h-full">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                        <span class="text-white font-bold text-lg opacity-0 group-hover:opacity-100 transition drop-shadow-md">Trenzas</span>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- ================================== -->
    <!-- SECCIÓN DE CONTACTO Y FORMULARIO   -->
    <!-- ================================== -->
    <section id="contacto" class="py-20 bg-gray-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid md:grid-cols-2 gap-12 items-start">

            <!-- Información de contacto del negocio -->
            <div>
                <h2 class="text-4xl font-extrabold text-amber-500 mb-4">¡Pide tu Cita Hoy!</h2>
                <p class="text-lg text-gray-300 mb-8">
                    Estamos listos para transformar tu estilo. Contáctanos por teléfono o visítanos.
                </p>

                <div class="space-y-4">
                    <!-- Dirección -->
                    <p class="flex items-center text-lg">
                        <i data-lucide="map-pin" class="w-6 h-6 text-amber-500 mr-3"></i>
                        C/ Eusebio Rubalcaba, 123, 45600 Talavera de la Reina, España
                    </p>
                    <!-- Teléfono (enlace clickable en móvil) -->
                    <p class="flex items-center text-lg">
                        <i data-lucide="phone" class="w-6 h-6 text-amber-500 mr-3"></i>
                        <a href="tel:+34123456789" class="hover:underline">+34 123 456 789</a>
                    </p>
                    <!-- Correo electrónico -->
                    <p class="flex items-center text-lg">
                        <i data-lucide="mail" class="w-6 h-6 text-amber-500 mr-3"></i>
                        <a href="mailto:peluqueriatecnologica@gmail.com" class="hover:underline">
                            peluqueriatecnologica@gmail.com
                        </a>
                    </p>
                    <!-- Horario -->
                    <p class="flex items-center text-lg">
                        <i data-lucide="clock" class="w-6 h-6 text-amber-500 mr-3"></i>
                        Lun - Sáb: 10:00h - 20:00h
                    </p>
                </div>
            </div>

            <!-- Formulario de contacto (procesado por PHP al enviar) -->
            <div class="bg-gray-800 p-8 rounded-xl shadow-2xl">
                <h3 class="text-2xl font-bold text-white mb-6">Envíanos un Mensaje</h3>

                <!-- Mensaje de respuesta generado por PHP tras el envío del formulario -->
                <?php echo $mensaje_formulario; ?>

                <!-- El formulario se envía a esta misma página (index.php#contacto) -->
                <form action="#contacto" method="POST" class="space-y-4">
                    <!-- Campo Nombre -->
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-300 mb-1">Nombre</label>
                        <input type="text" id="nombre" name="nombre" required
                               class="w-full px-4 py-2 rounded-lg border border-gray-600 focus:ring-amber-500 focus:border-amber-500 bg-gray-700 text-white"
                               value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                    </div>
                    <!-- Campo Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email</label>
                        <input type="email" id="email" name="email" required
                               class="w-full px-4 py-2 rounded-lg border border-gray-600 focus:ring-amber-500 focus:border-amber-500 bg-gray-700 text-white"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <!-- Campo Mensaje -->
                    <div>
                        <label for="mensaje" class="block text-sm font-medium text-gray-300 mb-1">Mensaje</label>
                        <textarea id="mensaje" name="mensaje" rows="4" required
                                  class="w-full px-4 py-2 rounded-lg border border-gray-600 focus:ring-amber-500 focus:border-amber-500 bg-gray-700 text-white"
                        ><?php echo htmlspecialchars($_POST['mensaje'] ?? ''); ?></textarea>
                    </div>
                    <!-- Botón de envío -->
                    <button type="submit"
                            class="w-full px-6 py-3 font-bold rounded-lg text-gray-900 bg-amber-500 hover:bg-amber-400 transition duration-300 shadow-md transform hover:scale-[1.01]">
                        Enviar Mensaje
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- ========================= -->
    <!-- SECCIÓN CTA RESERVA        -->
    <!-- ========================= -->
    <section class="py-16 bg-amber-500">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center">
            <h2 class="text-4xl font-extrabold text-gray-900 mb-3">¿Listo para tu cambio de look?</h2>
            <p class="text-gray-800 text-lg mb-8">Reserva tu cita online en menos de un minuto. Sin esperas, sin llamadas.</p>
            <a href="/php/reservar.php"
               class="inline-flex items-center px-10 py-4 text-lg font-extrabold rounded-full text-amber-500 bg-gray-900 hover:bg-gray-800 transition duration-300 shadow-2xl transform hover:scale-105">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Reservar mi Cita Ahora
            </a>
        </div>
    </section>

    <!-- ========================= -->
    <!-- PIE DE PÁGINA (Footer)    -->
    <!-- ========================= -->
    <footer class="bg-gray-950 py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-400 text-sm">
            <!-- Año actual generado dinámicamente por PHP con date("Y") -->
            <p>&copy; <?php echo date("Y"); ?> Peluquería tecnológica. Todos los derechos reservados.</p>
            <div class="mt-2 space-x-4">
                <a href="#" class="hover:text-amber-500 transition duration-150">Política de Privacidad</a>
                <a href="#" class="hover:text-amber-500 transition duration-150">Aviso Legal</a>
            </div>
        </div>
    </footer>

    <!-- Inicializar iconos Lucide al final del body (para que el DOM esté completo) -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
