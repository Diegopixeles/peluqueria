<?php
// =============================================================
// ARCHIVO: ajax/horas_disponibles.php
// DESCRIPCIÓN: Endpoint AJAX (GET). Recibe `fecha` e `id_servicio`
//              y devuelve un JSON con las franjas horarias del día,
//              indicando cuáles están disponibles y cuáles ocupadas.
// =============================================================

header('Content-Type: application/json; charset=utf-8');

// ---- Conexión a la BD ----
require_once __DIR__ . '/../conexion.php';

// ---- Recoger y validar parámetros de entrada ----
$fecha      = trim($_GET['fecha']      ?? '');
$idServicio = intval($_GET['servicio'] ?? 0);

// Validar formato de fecha (YYYY-MM-DD)
if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
    echo json_encode(['error' => 'Fecha inválida.']);
    exit;
}

// No permitir fechas pasadas
if ($fecha < date('Y-m-d')) {
    echo json_encode(['error' => 'No se pueden reservar fechas pasadas.']);
    exit;
}

// No permitir reservas con más de 365 días de antelación
if ($fecha > date('Y-m-d', strtotime('+365 days'))) {
    echo json_encode(['error' => 'Solo se puede reservar con un máximo de 1 año de antelación.']);
    exit;
}

// Validar servicio
if ($idServicio <= 0) {
    echo json_encode(['error' => 'Servicio no válido.']);
    exit;
}

// ---- Obtener la duración del servicio seleccionado ----
$stmtSrv = $pdo->prepare(
    'SELECT duracion_minutos FROM servicios WHERE id_servicio = :id AND activo = 1 LIMIT 1'
);
$stmtSrv->execute([':id' => $idServicio]);
$servicio = $stmtSrv->fetch();

if (!$servicio) {
    echo json_encode(['error' => 'Servicio no encontrado o inactivo.']);
    exit;
}

$duracion = (int) $servicio['duracion_minutos'];

// ---- Comprobar si el día es domingo (peluquería cerrada) ----
$diaSemana = (int) date('N', strtotime($fecha)); // 1=Lun, 7=Dom
if ($diaSemana === 7) {
    echo json_encode(['error' => 'La peluquería está cerrada los domingos.']);
    exit;
}

// ---- Definir el horario de apertura ----
// Lunes a Sábado: 10:00 - 20:00, franjas de 30 minutos
$horaApertura = '10:00';
$horaCierre   = '20:00';
$intMin       = 30; // intervalo en minutos

// ---- Obtener horas ya ocupadas ese día en `reservas_web` ----
$stmtRW = $pdo->prepare(
    'SELECT hora_inicio, id_servicio
     FROM reservas_web
     WHERE fecha = :fecha AND estado != "cancelada"'
);
$stmtRW->execute([':fecha' => $fecha]);
$ocupadasRW = $stmtRW->fetchAll();

// ---- Obtener horas ya ocupadas ese día en `citas` ----
$stmtCitas = $pdo->prepare(
    'SELECT hora_inicio, duracion_minutos
     FROM citas
     WHERE fecha = :fecha AND estado NOT IN ("cancelada", "finalizada")'
);
$stmtCitas->execute([':fecha' => $fecha]);
$ocupadasCitas = $stmtCitas->fetchAll();

// ---- Construir un array de bloques de tiempo ocupados ----
// Cada bloque = [inicio_en_minutos, fin_en_minutos]
$bloques = [];

// Procesar reservas_web
foreach ($ocupadasRW as $r) {
    // Obtener duración del servicio de esta reserva
    $stmtDur = $pdo->prepare('SELECT duracion_minutos FROM servicios WHERE id_servicio = :id LIMIT 1');
    $stmtDur->execute([':id' => $r['id_servicio']]);
    $durR = (int)($stmtDur->fetchColumn() ?? 30);

    list($h, $m) = explode(':', $r['hora_inicio']);
    $inicioMin = (int)$h * 60 + (int)$m;
    $bloques[] = ['inicio' => $inicioMin, 'fin' => $inicioMin + $durR];
}

// Procesar citas
foreach ($ocupadasCitas as $c) {
    list($h, $m) = explode(':', $c['hora_inicio']);
    $inicioMin = (int)$h * 60 + (int)$m;
    $bloques[] = ['inicio' => $inicioMin, 'fin' => $inicioMin + (int)$c['duracion_minutos']];
}

// ---- Generar todas las franjas horarias del día ----
$franjas = [];
$ahora = time();
$esHoy = ($fecha === date('Y-m-d'));

// Convertir hora apertura y cierre a minutos desde medianoche
list($hA, $mA) = explode(':', $horaApertura);
list($hC, $mC) = explode(':', $horaCierre);
$aperturaMin = (int)$hA * 60 + (int)$mA;
$cierreMin   = (int)$hC * 60 + (int)$mC;

$cursor = $aperturaMin;

while (($cursor + $duracion) <= $cierreMin) {
    $horaStr = sprintf('%02d:%02d', intdiv($cursor, 60), $cursor % 60);

    // ¿Está disponible este slot?
    $disponible = true;

    // Si es hoy, descartar slots pasados (con 30 min de margen)
    if ($esHoy) {
        $slotTimestamp = strtotime($fecha . ' ' . $horaStr);
        if ($slotTimestamp < ($ahora + 1800)) { // 30 min de margen mínimo
            $disponible = false;
        }
    }

    // Comprobar si el bloque de tiempo del servicio se solaparía con algo ocupado
    // El bloque nuevo va desde $cursor hasta $cursor + $duracion
    if ($disponible) {
        foreach ($bloques as $b) {
            // Hay solape si: nuevaInicio < bloqueExistenteFin Y nuevaFin > bloqueExistente Inicio
            if ($cursor < $b['fin'] && ($cursor + $duracion) > $b['inicio']) {
                $disponible = false;
                break;
            }
        }
    }

    $franjas[] = [
        'hora'       => $horaStr,
        'disponible' => $disponible,
    ];

    $cursor += $intMin;
}

// ---- Devolver el JSON ----
echo json_encode([
    'ok'     => true,
    'fecha'  => $fecha,
    'franjas' => $franjas,
]);
