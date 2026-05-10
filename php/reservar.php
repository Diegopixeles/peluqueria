<?php
// =============================================================
// DESCRIPCIÓN: Página pública de reserva de citas.
//              - Usuarios con sesión: ven su historial y datos precompletados.
//              - Usuarios sin sesión: solo pueden reservar rellenando sus datos.
//              Incluye un calendario interactivo JS, selección de horas
//              disponibles vía AJAX, y formulario de confirmación.
// =============================================================

session_start();
require_once __DIR__ . '/conexion.php';

// ---- Determinar si hay sesión iniciada ----
$haySession   = !empty($_SESSION['usuario_id']);
$nombreSesion = htmlspecialchars($_SESSION['usuario_nombre'] ?? '');
$emailSesion  = htmlspecialchars($_SESSION['usuario_email']  ?? '');

// ---- Obtener historial de citas (solo si hay sesión) ----
$historial = [];
if ($haySession) {
    // Buscar el id_cliente asociado al email de sesión
    $stmtCli = $pdo->prepare('SELECT id_cliente FROM clientes WHERE correo = :email LIMIT 1');
    $stmtCli->execute([':email' => $_SESSION['usuario_email'] ?? '']);
    $clienteRow = $stmtCli->fetch();

    if ($clienteRow) {
        $stmtHist = $pdo->prepare(
            'SELECT rw.fecha, rw.hora_inicio, rw.estado, s.nombre AS servicio
             FROM reservas_web rw
             JOIN servicios s ON s.id_servicio = rw.id_servicio
             WHERE rw.id_cliente_web = :id
             ORDER BY rw.fecha DESC, rw.hora_inicio DESC
             LIMIT 5'
        );
        $stmtHist->execute([':id' => $clienteRow['id_cliente']]);
        $historial = $stmtHist->fetchAll();
    }
}

// ---- Obtener servicios activos para el desplegable ----
$stmtSrv = $pdo->query('SELECT id_servicio, nombre, precio, duracion_minutos FROM servicios WHERE activo = 1 ORDER BY nombre');
$servicios = $stmtSrv->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar Cita | Peluquería tecnológica</title>
    <meta name="description" content="Reserva tu cita online en Peluquería tecnológica. Elige día, hora y servicio en segundos.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        * { font-family: 'Inter', sans-serif; }

        /* ---- Fondo animado ---- */
        body {
            background: linear-gradient(135deg, #111827 0%, #1f2937 50%, #111827 100%);
            min-height: 100vh;
        }

        /* ---- Calendario ---- */
        .cal-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.6rem;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.18s ease;
            user-select: none;
        }
        .cal-day:hover:not(.cal-disabled):not(.cal-empty) { background: #f59e0b22; color: #f59e0b; }
        .cal-day.cal-today { border: 2px solid #f59e0b55; color: #fbbf24; font-weight: 700; }
        .cal-day.cal-selected { background: #f59e0b !important; color: #111827 !important; font-weight: 800; box-shadow: 0 0 20px #f59e0b66; }
        .cal-day.cal-disabled { color: #374151; cursor: not-allowed; }
        .cal-day.cal-empty { cursor: default; }
        .cal-day.cal-past { color: #374151; cursor: not-allowed; opacity: 0.4; }

        /* ---- Slots de hora ---- */
        .slot {
            padding: 0.45rem 0.9rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.18s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .slot.libre { background: #064e3b22; border-color: #10b981; color: #34d399; }
        .slot.libre:hover { background: #10b981; color: #111827; transform: scale(1.05); }
        .slot.ocupado { background: #1f2937; color: #4b5563; border-color: #374151; cursor: not-allowed; }
        .slot.seleccionado { background: #f59e0b !important; color: #111827 !important; border-color: #f59e0b !important; box-shadow: 0 0 16px #f59e0b55; }

        /* ---- Tarjeta glassmorphism ---- */
        .glass {
            background: rgba(31, 41, 55, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(75, 85, 99, 0.4);
        }

        /* ---- Animaciones ---- */
        @keyframes fadeSlideUp {
            from { opacity:0; transform: translateY(20px); }
            to   { opacity:1; transform: translateY(0); }
        }
        .anim-up { animation: fadeSlideUp 0.4s ease both; }

        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 0px #f59e0b00; }
            50%       { box-shadow: 0 0 24px #f59e0b88; }
        }
        .pulse-amber { animation: pulse-glow 2s infinite; }

        /* Steps indicator */
        .step-dot {
            width: 2.2rem; height: 2.2rem;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        .step-line { flex: 1; height: 2px; background: #374151; transition: background 0.3s ease; }
        .step-line.active { background: #f59e0b; }
        .step-dot.active { background: #f59e0b; color: #111827; }
        .step-dot.done { background: #10b981; color: #111827; }
        .step-dot.pending { background: #374151; color: #6b7280; }

        /* Input styles */
        .inp {
            width: 100%;
            padding: 0.65rem 0.9rem;
            border-radius: 0.6rem;
            background: #111827;
            border: 1.5px solid #374151;
            color: white;
            font-size: 0.9rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .inp:focus { border-color: #f59e0b; box-shadow: 0 0 0 3px #f59e0b22; }
        .inp::placeholder { color: #6b7280; }

        /* Badge estado historial */
        .badge { padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; }
        .badge-pendiente  { background: #f59e0b22; color: #fbbf24; border: 1px solid #f59e0b55; }
        .badge-confirmada { background: #10b98122; color: #34d399; border: 1px solid #10b98155; }
        .badge-cancelada  { background: #ef444422; color: #f87171; border: 1px solid #ef444455; }
    </style>
</head>
<body class="text-white">

    <!-- ====== NAVBAR ====== -->
    <nav class="border-b border-gray-700/50 bg-gray-900/80 backdrop-blur sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex justify-between items-center">

            <a href="/" class="flex items-center gap-2 text-white hover:text-amber-400 transition">
                <i data-lucide="scissors-square" class="w-6 h-6 text-amber-500"></i>
                <span class="font-bold text-lg">Peluquería tecnológica</span>
            </a>

            <div class="flex items-center gap-3">
                <?php if ($haySession): ?>
                    <span class="text-gray-400 text-sm hidden sm:block">
                        <i data-lucide="user-circle" class="w-4 h-4 inline mr-1 text-amber-400"></i>
                        Hola, <strong class="text-white"><?= $nombreSesion ?></strong>
                    </span>
                    <a href="panel.php" class="text-xs px-3 py-1.5 rounded-lg bg-amber-500 text-gray-900 font-bold hover:bg-amber-400 transition">
                        Panel Admin
                    </a>
                <?php else: ?>
                    <a href="login.php" class="text-sm text-gray-400 hover:text-amber-400 transition flex items-center gap-1">
                        <i data-lucide="log-in" class="w-4 h-4"></i>
                        Iniciar sesión
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- ====== HERO ====== -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-12 pb-6 text-center anim-up">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-amber-500 rounded-2xl shadow-lg mb-5 transform -rotate-6 hover:rotate-0 transition-transform duration-300">
            <i data-lucide="calendar-check" class="w-8 h-8 text-gray-900"></i>
        </div>
        <h1 class="text-4xl sm:text-5xl font-extrabold mb-3">Reserva tu Cita</h1>
        <p class="text-gray-400 text-lg max-w-xl mx-auto">Elige el día y la hora que mejor te venga. En 3 sencillos pasos.</p>
    </section>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 pb-16">

        <!-- ====== HISTORIAL (solo si hay sesión) ====== -->
        <?php if ($haySession): ?>
        <div class="glass rounded-2xl p-6 mb-8 anim-up">
            <h2 class="text-lg font-bold text-amber-400 mb-4 flex items-center gap-2">
                <i data-lucide="history" class="w-5 h-5"></i>
                Tus últimas reservas
            </h2>
            <?php if (empty($historial)): ?>
                <p class="text-gray-500 text-sm">Aún no tienes ninguna reserva registrada. ¡Haz tu primera cita ahora!</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-gray-500 uppercase text-xs border-b border-gray-700">
                                <th class="pb-2 text-left">Fecha</th>
                                <th class="pb-2 text-left">Hora</th>
                                <th class="pb-2 text-left">Servicio</th>
                                <th class="pb-2 text-left">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700/50">
                            <?php foreach ($historial as $h): ?>
                            <tr>
                                <td class="py-2.5 font-medium"><?= date('d/m/Y', strtotime($h['fecha'])) ?></td>
                                <td class="py-2.5 text-gray-300"><?= htmlspecialchars(substr($h['hora_inicio'], 0, 5)) ?></td>
                                <td class="py-2.5 text-gray-300"><?= htmlspecialchars($h['servicio']) ?></td>
                                <td class="py-2.5">
                                    <span class="badge badge-<?= $h['estado'] ?>">
                                        <?= ucfirst(htmlspecialchars($h['estado'])) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ====== STEPS INDICATOR ====== -->
        <div class="flex items-center justify-center gap-0 mb-8" id="steps-indicator">
            <div class="flex flex-col items-center">
                <div class="step-dot active" id="dot-1">1</div>
                <span class="text-xs mt-1 text-amber-400 font-medium">Día</span>
            </div>
            <div class="step-line mx-1 sm:mx-2" id="line-1-2"></div>
            <div class="flex flex-col items-center">
                <div class="step-dot pending" id="dot-2">2</div>
                <span class="text-xs mt-1 text-gray-500" id="label-2">Hora</span>
            </div>
            <div class="step-line mx-1 sm:mx-2" id="line-2-3"></div>
            <div class="flex flex-col items-center">
                <div class="step-dot pending" id="dot-3">3</div>
                <span class="text-xs mt-1 text-gray-500" id="label-3">Confirmar</span>
            </div>
        </div>

        <!-- ====== GRID PRINCIPAL ====== -->
        <div class="grid lg:grid-cols-2 gap-6 items-start">

            <!-- ====== COLUMNA IZQUIERDA: Calendario + Slots ====== -->
            <div class="space-y-5">

                <!-- SELECTOR DE SERVICIO -->
                <div class="glass rounded-2xl p-5 anim-up">
                    <label class="block text-sm font-semibold text-gray-300 mb-2">
                        <i data-lucide="scissors" class="w-4 h-4 inline mr-1 text-amber-400"></i>
                        Primero, selecciona un servicio
                    </label>
                    <select id="sel-servicio" class="inp">
                        <option value="">-- Elige un servicio --</option>
                        <?php foreach ($servicios as $srv): ?>
                        <option value="<?= $srv['id_servicio'] ?>"
                                data-duracion="<?= $srv['duracion_minutos'] ?>"
                                data-precio="<?= number_format($srv['precio'], 2, ',', '.') ?>">
                            <?= htmlspecialchars($srv['nombre']) ?>
                            — <?= number_format($srv['precio'], 2, ',', '.') ?>€
                            (<?= $srv['duracion_minutos'] ?> min)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="srv-info" class="hidden mt-2 text-xs text-amber-400 flex items-center gap-1">
                        <i data-lucide="clock" class="w-3 h-3"></i>
                        <span id="srv-info-text"></span>
                    </div>
                </div>

                <!-- CALENDARIO -->
                <div class="glass rounded-2xl p-5 anim-up" id="card-calendario">
                    <div class="flex items-center justify-between mb-4">
                        <button id="btn-prev" class="p-1.5 rounded-lg hover:bg-gray-700 transition text-gray-400 hover:text-white">
                            <i data-lucide="chevron-left" class="w-5 h-5"></i>
                        </button>
                        <h3 class="font-bold text-lg" id="cal-titulo"></h3>
                        <button id="btn-next" class="p-1.5 rounded-lg hover:bg-gray-700 transition text-gray-400 hover:text-white">
                            <i data-lucide="chevron-right" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <!-- Cabecera días semana -->
                    <div class="grid grid-cols-7 gap-1 mb-1">
                        <?php foreach (['L','M','X','J','V','S','D'] as $d): ?>
                        <div class="text-center text-xs text-gray-500 font-semibold pb-1"><?= $d ?></div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Grid de días (rellenado por JS) -->
                    <div class="grid grid-cols-7 gap-1" id="cal-grid"></div>
                    <p class="text-xs text-gray-600 mt-3 text-center">Los domingos la peluquería está cerrada.</p>
                </div>

                <!-- SLOTS DE HORA -->
                <div class="glass rounded-2xl p-5 anim-up hidden" id="card-slots">
                    <h3 class="font-bold text-base mb-1 flex items-center gap-2">
                        <i data-lucide="clock" class="w-4 h-4 text-amber-400"></i>
                        Elige una hora
                        <span class="text-amber-400 text-sm font-normal" id="slots-fecha-label"></span>
                    </h3>
                    <p class="text-xs text-gray-500 mb-3">🟢 Disponible &nbsp; ⬛ Ocupado</p>
                    <div id="slots-container" class="flex flex-wrap gap-2"></div>
                    <div id="slots-loading" class="text-center py-6 hidden">
                        <div class="inline-block w-6 h-6 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
                        <p class="text-gray-400 text-sm mt-2">Cargando disponibilidad…</p>
                    </div>
                    <div id="slots-error" class="text-red-400 text-sm hidden"></div>
                </div>
            </div>

            <!-- ====== COLUMNA DERECHA: Formulario de confirmación ====== -->
            <div class="glass rounded-2xl p-6 anim-up" id="card-formulario">

                <!-- Estado VACÍO (aún no ha seleccionado día + hora) -->
                <div id="form-placeholder" class="text-center py-12">
                    <i data-lucide="calendar-clock" class="w-14 h-14 mx-auto text-gray-600 mb-4"></i>
                    <p class="text-gray-500 text-sm">Selecciona un <strong class="text-gray-400">servicio</strong>, luego un <strong class="text-gray-400">día</strong> y una <strong class="text-gray-400">hora</strong> para continuar.</p>
                </div>

                <!-- Resumen selección (oculto hasta completar pasos 1+2) -->
                <div id="form-contenido" class="hidden">

                    <!-- Resumen seleccionado -->
                    <div class="bg-gray-900/60 rounded-xl p-4 mb-5 border border-amber-500/30">
                        <p class="text-xs text-gray-500 uppercase font-semibold mb-2">Tu selección</p>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div>
                                <i data-lucide="scissors" class="w-4 h-4 mx-auto text-amber-400 mb-1"></i>
                                <p class="text-xs text-gray-400">Servicio</p>
                                <p class="text-sm font-bold text-white" id="sum-servicio">—</p>
                            </div>
                            <div>
                                <i data-lucide="calendar" class="w-4 h-4 mx-auto text-amber-400 mb-1"></i>
                                <p class="text-xs text-gray-400">Fecha</p>
                                <p class="text-sm font-bold text-white" id="sum-fecha">—</p>
                            </div>
                            <div>
                                <i data-lucide="clock" class="w-4 h-4 mx-auto text-amber-400 mb-1"></i>
                                <p class="text-xs text-gray-400">Hora</p>
                                <p class="text-sm font-bold text-white" id="sum-hora">—</p>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de datos del cliente -->
                    <form id="form-reserva" class="space-y-4" novalidate>
                        <input type="hidden" id="inp-fecha"      name="fecha">
                        <input type="hidden" id="inp-hora"       name="hora">
                        <input type="hidden" id="inp-servicio"   name="id_servicio">

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">
                                Nombre completo <span class="text-red-400">*</span>
                            </label>
                            <input type="text" id="inp-nombre" name="nombre" class="inp"
                                   placeholder="Ana García"
                                   value="<?= $haySession ? $nombreSesion : '' ?>"
                                   <?= $haySession ? '' : '' ?>>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">
                                Email <span class="text-red-400">*</span>
                            </label>
                            <input type="email" id="inp-email" name="email" class="inp"
                                   placeholder="tu@email.com"
                                   value="<?= $haySession ? $emailSesion : '' ?>">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">
                                Teléfono <span class="text-gray-500 text-xs">(opcional)</span>
                            </label>
                            <input type="tel" id="inp-telefono" name="telefono" class="inp"
                                   placeholder="+34 600 000 000">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">
                                Notas adicionales <span class="text-gray-500 text-xs">(opcional)</span>
                            </label>
                            <textarea id="inp-notas" name="notas" rows="2" class="inp resize-none"
                                      placeholder="Alergias, preferencias, …"></textarea>
                        </div>

                        <!-- Mensaje de error -->
                        <div id="form-error" class="hidden bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-3 rounded-xl text-sm flex items-start gap-2">
                            <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
                            <span id="form-error-text"></span>
                        </div>

                        <!-- Botón reservar -->
                        <button type="submit" id="btn-reservar"
                                class="w-full py-3.5 rounded-xl font-extrabold text-gray-900 bg-amber-500 hover:bg-amber-400 transition transform active:scale-95 pulse-amber flex items-center justify-center gap-2">
                            <i data-lucide="check-circle" class="w-5 h-5"></i>
                            Confirmar Reserva
                        </button>

                        <p class="text-center text-xs text-gray-600">
                            Recibirás un email de confirmación con los detalles de tu cita.
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <!-- ====== MODAL DE ÉXITO ====== -->
        <div id="modal-exito" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
            <div class="glass rounded-3xl p-8 max-w-md w-full text-center anim-up">
                <div class="w-20 h-20 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-5 shadow-lg">
                    <i data-lucide="check" class="w-10 h-10 text-white"></i>
                </div>
                <h2 class="text-2xl font-extrabold mb-2">¡Cita reservada!</h2>
                <p class="text-gray-400 text-sm mb-5" id="modal-mensaje">Hemos enviado los detalles a tu email.</p>
                <div class="bg-gray-900/60 rounded-xl p-4 text-left space-y-2 mb-6">
                    <div class="flex items-center gap-2 text-sm">
                        <i data-lucide="scissors" class="w-4 h-4 text-amber-400 flex-shrink-0"></i>
                        <span class="text-gray-400">Servicio:</span>
                        <strong id="modal-servicio" class="text-white ml-auto"></strong>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i data-lucide="calendar" class="w-4 h-4 text-amber-400 flex-shrink-0"></i>
                        <span class="text-gray-400">Fecha:</span>
                        <strong id="modal-fecha" class="text-white ml-auto"></strong>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i data-lucide="clock" class="w-4 h-4 text-amber-400 flex-shrink-0"></i>
                        <span class="text-gray-400">Hora:</span>
                        <strong id="modal-hora" class="text-white ml-auto"></strong>
                    </div>
                </div>
                <button onclick="cerrarModal()"
                        class="w-full py-3 rounded-xl font-bold text-gray-900 bg-amber-500 hover:bg-amber-400 transition">
                    Hacer otra reserva
                </button>
                <a href="/" class="block mt-3 text-sm text-gray-500 hover:text-gray-300 transition">
                    Volver a la página principal
                </a>
            </div>
        </div>

    </div><!-- /max-w-6xl -->

    <script>
    // ======================================================
    // JAVASCRIPT: Calendario interactivo + AJAX para reservas
    // ======================================================

    // ---- Variables de estado ----
    let mesActual   = new Date().getMonth();
    let añoActual   = new Date().getFullYear();
    let fechaSelec  = null;   // 'YYYY-MM-DD'
    let horaSelec   = null;   // 'HH:MM'
    let servicioId  = null;
    let servicioNombre = '';

    const hoy = new Date();
    hoy.setHours(0,0,0,0);

    const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                   'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    // ---- Elementos del DOM ----
    const calGrid        = document.getElementById('cal-grid');
    const calTitulo      = document.getElementById('cal-titulo');
    const cardSlots      = document.getElementById('card-slots');
    const slotsContainer = document.getElementById('slots-container');
    const slotsLoading   = document.getElementById('slots-loading');
    const slotsError     = document.getElementById('slots-error');
    const slotsFechaLbl  = document.getElementById('slots-fecha-label');
    const cardForm       = document.getElementById('card-formulario');
    const formPlaceholder= document.getElementById('form-placeholder');
    const formContenido  = document.getElementById('form-contenido');
    const selServicio    = document.getElementById('sel-servicio');
    const srvInfo        = document.getElementById('srv-info');
    const srvInfoTxt     = document.getElementById('srv-info-text');

    // ---- Renderizar calendario ----
    function renderCalendario() {
        calTitulo.textContent = meses[mesActual] + ' ' + añoActual;
        calGrid.innerHTML = '';

        // Primer día del mes (0=Dom, 1=Lun, …6=Sáb → ajustar a Lunes=0)
        const primerDia = new Date(añoActual, mesActual, 1);
        let offset = primerDia.getDay(); // 0=Dom
        offset = (offset === 0) ? 6 : offset - 1; // Convertir a Lunes=0

        const diasEnMes = new Date(añoActual, mesActual + 1, 0).getDate();
        const hoyStr = formatFecha(hoy);

        // Celdas vacías antes del primer día
        for (let i = 0; i < offset; i++) {
            const vacio = document.createElement('div');
            vacio.className = 'cal-day cal-empty';
            calGrid.appendChild(vacio);
        }

        // Celdas de días
        for (let dia = 1; dia <= diasEnMes; dia++) {
            const fecha = new Date(añoActual, mesActual, dia);
            const fechaStr = formatFecha(fecha);
            const diaSem = fecha.getDay(); // 0=Dom

            const cel = document.createElement('div');
            cel.className = 'cal-day';
            cel.textContent = dia;

            if (fecha < hoy) {
                cel.classList.add('cal-past');
            } else if (diaSem === 0) {
                cel.classList.add('cal-disabled');
                cel.title = 'Cerrado los domingos';
            } else {
                if (fechaStr === hoyStr) cel.classList.add('cal-today');
                if (fechaStr === fechaSelec) cel.classList.add('cal-selected');

                cel.addEventListener('click', () => seleccionarDia(fechaStr, cel));
            }

            calGrid.appendChild(cel);
        }
    }

    function formatFecha(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const dia = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${dia}`;
    }

    function formatFechaES(str) {
        const [y, m, d] = str.split('-');
        return `${d}/${m}/${y}`;
    }

    // ---- Seleccionar un día ----
    function seleccionarDia(fecha, el) {
        if (!servicioId) {
            selServicio.classList.add('ring-2', 'ring-amber-500');
            selServicio.focus();
            setTimeout(() => selServicio.classList.remove('ring-2','ring-amber-500'), 1500);
            return;
        }

        fechaSelec = fecha;
        horaSelec  = null;

        // Marcar visualmente en el calendario
        document.querySelectorAll('.cal-day.cal-selected').forEach(c => c.classList.remove('cal-selected'));
        el.classList.add('cal-selected');

        // Actualizar steps
        setStep(2);

        // Cargar horas
        cargarHoras(fecha);
    }

    // ---- Cargar franjas horarias vía AJAX ----
    function cargarHoras(fecha) {
        cardSlots.classList.remove('hidden');
        slotsContainer.innerHTML = '';
        slotsError.classList.add('hidden');
        slotsLoading.classList.remove('hidden');

        const nombreMes = meses[parseInt(fecha.split('-')[1]) - 1];
        const dia = parseInt(fecha.split('-')[2]);
        slotsFechaLbl.textContent = `— ${dia} de ${nombreMes}`;

        ocultarFormulario();

        fetch(`ajax/horas_disponibles.php?fecha=${fecha}&servicio=${servicioId}`)
            .then(r => r.json())
            .then(data => {
                slotsLoading.classList.add('hidden');
                if (data.error) {
                    slotsError.textContent = data.error;
                    slotsError.classList.remove('hidden');
                    return;
                }
                renderSlots(data.franjas);
            })
            .catch(() => {
                slotsLoading.classList.add('hidden');
                slotsError.textContent = 'Error al conectar con el servidor. Inténtalo de nuevo.';
                slotsError.classList.remove('hidden');
            });
    }

    // ---- Renderizar slots de hora ----
    function renderSlots(franjas) {
        slotsContainer.innerHTML = '';
        if (!franjas || franjas.length === 0) {
            slotsContainer.innerHTML = '<p class="text-gray-500 text-sm">No hay franjas horarias disponibles para este día.</p>';
            return;
        }
        franjas.forEach(f => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'slot ' + (f.disponible ? 'libre' : 'ocupado');
            btn.textContent = f.hora;
            btn.disabled = !f.disponible;
            if (f.disponible) {
                btn.addEventListener('click', () => seleccionarHora(f.hora, btn));
            }
            slotsContainer.appendChild(btn);
        });
    }

    // ---- Seleccionar una hora ----
    function seleccionarHora(hora, el) {
        horaSelec = hora;

        // Marcar visualmente
        document.querySelectorAll('.slot.seleccionado').forEach(s => s.classList.remove('seleccionado'));
        el.classList.add('seleccionado');

        // Actualizar steps
        setStep(3);

        // Mostrar formulario con resumen
        mostrarFormulario();
    }

    // ---- Mostrar formulario ----
    function mostrarFormulario() {
        formPlaceholder.classList.add('hidden');
        formContenido.classList.remove('hidden');

        // Rellenar resumen
        document.getElementById('sum-servicio').textContent = servicioNombre;
        document.getElementById('sum-fecha').textContent    = formatFechaES(fechaSelec);
        document.getElementById('sum-hora').textContent     = horaSelec;

        // Rellenar inputs ocultos
        document.getElementById('inp-fecha').value    = fechaSelec;
        document.getElementById('inp-hora').value     = horaSelec;
        document.getElementById('inp-servicio').value = servicioId;

        // Re-init icons por el DOM nuevo
        lucide.createIcons();
    }

    function ocultarFormulario() {
        formPlaceholder.classList.remove('hidden');
        formContenido.classList.add('hidden');
        horaSelec = null;
    }

    // ---- Steps indicator ----
    function setStep(step) {
        const dot1 = document.getElementById('dot-1');
        const dot2 = document.getElementById('dot-2');
        const dot3 = document.getElementById('dot-3');
        const line12 = document.getElementById('line-1-2');
        const line23 = document.getElementById('line-2-3');
        const lab2 = document.getElementById('label-2');
        const lab3 = document.getElementById('label-3');

        if (step >= 2) {
            dot1.className = 'step-dot done';
            dot2.className = 'step-dot active';
            line12.classList.add('active');
            lab2.classList.replace('text-gray-500', 'text-amber-400');
        }
        if (step >= 3) {
            dot2.className = 'step-dot done';
            dot3.className = 'step-dot active';
            line23.classList.add('active');
            lab3.classList.replace('text-gray-500', 'text-amber-400');
        }
        lucide.createIcons();
    }

    // ---- Cambiar mes ----
    document.getElementById('btn-prev').addEventListener('click', () => {
        const limiteMin = new Date();
        if (mesActual === limiteMin.getMonth() && añoActual === limiteMin.getFullYear()) return;
        mesActual--;
        if (mesActual < 0) { mesActual = 11; añoActual--; }
        fechaSelec = null; horaSelec = null;
        renderCalendario();
        cardSlots.classList.add('hidden');
        ocultarFormulario();
    });

    document.getElementById('btn-next').addEventListener('click', () => {
        // Máximo 12 meses vista (reservas hasta un año adelante)
        const limiteMax = new Date();
        limiteMax.setMonth(limiteMax.getMonth() + 12);
        const fechaVista = new Date(añoActual, mesActual + 1);
        if (fechaVista > limiteMax) return;
        mesActual++;
        if (mesActual > 11) { mesActual = 0; añoActual++; }
        fechaSelec = null; horaSelec = null;
        renderCalendario();
        cardSlots.classList.add('hidden');
        ocultarFormulario();
    });

    // ---- Cambio de servicio ----
    selServicio.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        servicioId = this.value || null;
        servicioNombre = opt.text.split('—')[0].trim();

        if (servicioId) {
            const dur = opt.dataset.duracion;
            const precio = opt.dataset.precio;
            srvInfoTxt.textContent = `Duración: ${dur} min · Precio: ${precio}€`;
            srvInfo.classList.remove('hidden');
            lucide.createIcons();
        } else {
            srvInfo.classList.add('hidden');
        }

        // Recargar horas si ya había un día seleccionado
        if (fechaSelec && servicioId) {
            cargarHoras(fechaSelec);
        } else {
            cardSlots.classList.add('hidden');
            ocultarFormulario();
        }

        horaSelec = null;
        document.querySelectorAll('.slot.seleccionado').forEach(s => s.classList.remove('seleccionado'));
    });

    // ---- Envío del formulario ----
    document.getElementById('form-reserva').addEventListener('submit', function(e) {
        e.preventDefault();

        const errDiv  = document.getElementById('form-error');
        const errTxt  = document.getElementById('form-error-text');
        const btnRes  = document.getElementById('btn-reservar');

        // Validar client-side básico
        const nombre = document.getElementById('inp-nombre').value.trim();
        const email  = document.getElementById('inp-email').value.trim();

        if (!nombre) { mostrarError('El nombre es obligatorio.'); return; }
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { mostrarError('Introduce un email válido.'); return; }
        if (!fechaSelec) { mostrarError('Selecciona un día en el calendario.'); return; }
        if (!horaSelec)  { mostrarError('Selecciona una hora disponible.'); return; }
        if (!servicioId) { mostrarError('Selecciona un servicio.'); return; }

        // Ocultar error previo
        errDiv.classList.add('hidden');

        // Deshabilitar botón mientras se procesa
        btnRes.disabled = true;
        btnRes.innerHTML = '<div class="inline-block w-5 h-5 border-2 border-gray-900 border-t-transparent rounded-full animate-spin mr-2"></div> Procesando…';

        const formData = new FormData(this);

        fetch('ajax/guardar_cita.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            btnRes.disabled = false;
            btnRes.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5 mr-2"></i>Confirmar Reserva';
            lucide.createIcons();

            if (data.ok) {
                mostrarModal(data);
            } else {
                mostrarError(data.error || 'Error desconocido.');
            }
        })
        .catch(() => {
            btnRes.disabled = false;
            btnRes.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5 mr-2"></i>Confirmar Reserva';
            lucide.createIcons();
            mostrarError('Error de conexión con el servidor. Por favor, inténtalo de nuevo.');
        });
    });

    function mostrarError(txt) {
        const errDiv = document.getElementById('form-error');
        document.getElementById('form-error-text').textContent = txt;
        errDiv.classList.remove('hidden');
        errDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ---- Modal de éxito ----
    function mostrarModal(data) {
        document.getElementById('modal-mensaje').textContent  = data.mensaje;
        document.getElementById('modal-servicio').textContent = data.resumen.servicio;
        document.getElementById('modal-fecha').textContent    = data.resumen.fecha;
        document.getElementById('modal-hora').textContent     = data.resumen.hora;
        document.getElementById('modal-exito').classList.remove('hidden');
        lucide.createIcons();
    }

    function cerrarModal() {
        document.getElementById('modal-exito').classList.add('hidden');
        // Resetear estado
        fechaSelec = fechaSelec; // mantener para permitir nueva reserva mismo día
        horaSelec  = null;
        document.querySelectorAll('.slot.seleccionado').forEach(s => s.classList.remove('seleccionado'));
        ocultarFormulario();
        if (fechaSelec && servicioId) cargarHoras(fechaSelec);
    }

    // Cerrar modal al hacer clic fuera
    document.getElementById('modal-exito').addEventListener('click', function(e) {
        if (e.target === this) cerrarModal();
    });

    // ---- Inicializar ----
    renderCalendario();
    lucide.createIcons();
    </script>
</body>
</html>
