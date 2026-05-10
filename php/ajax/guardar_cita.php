<?php
// =============================================================
// ARCHIVO: ajax/guardar_cita.php
// DESCRIPCIÓN: Endpoint AJAX (POST). Valida los datos del
//              formulario de reserva, guarda la cita en BD
//              y envía email de confirmación con PHPMailer + Gmail.
// =============================================================

header('Content-Type: application/json; charset=utf-8');
session_start();

// ---- PHPMailer (descargado en lib/PHPMailer/src/) ----
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

// =============================================================
//     CONFIGURACIÓN DE GMAIL SMTP
// =============================================================
// Lee las credenciales de las variables de entorno (Railway)
// Si no existen (ej. en local), usa las de por defecto.
define('MAIL_HOST',     getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT',     getenv('MAIL_PORT') ?: 587);
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'diegosanmiguelseco2006@gmail.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'pralgxipxpkddggy'); 
define('MAIL_FROM',     getenv('MAIL_FROM') ?: 'diegosanmiguelseco2006@gmail.com');
define('MAIL_FROM_NAME','Peluquería tecnológica');

require_once __DIR__ . '/../conexion.php';

// Solo acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

// ---- Recoger datos del formulario ----
$nombre     = trim($_POST['nombre']      ?? '');
$email      = trim($_POST['email']       ?? '');
$telefono   = trim($_POST['telefono']    ?? '');
$fecha      = trim($_POST['fecha']       ?? '');
$hora       = trim($_POST['hora']        ?? '');
$idServicio = intval($_POST['id_servicio'] ?? 0);
$notas      = trim($_POST['notas']       ?? '');

// ---- Validaciones ----
$errores = [];

if (empty($nombre)) $errores[] = 'El nombre es obligatorio.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'El email no es válido.';
if (empty($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $errores[] = 'La fecha no es válida.';
if ($fecha < date('Y-m-d')) $errores[] = 'No se pueden reservar fechas pasadas.';
if (empty($hora) || !preg_match('/^\d{2}:\d{2}$/', $hora)) $errores[] = 'La hora no es válida.';
if ($idServicio <= 0) $errores[] = 'Debes seleccionar un servicio.';

if (!empty($errores)) {
    echo json_encode(['ok' => false, 'error' => implode(' ', $errores)]);
    exit;
}

// ---- Verificar que el servicio existe ----
$stmtSrv = $pdo->prepare(
    'SELECT id_servicio, nombre, duracion_minutos FROM servicios WHERE id_servicio = :id AND activo = 1 LIMIT 1'
);
$stmtSrv->execute([':id' => $idServicio]);
$servicio = $stmtSrv->fetch();

if (!$servicio) {
    echo json_encode(['ok' => false, 'error' => 'El servicio seleccionado no existe o no está disponible.']);
    exit;
}

$duracion      = (int) $servicio['duracion_minutos'];
$nombreServicio = $servicio['nombre'];

// ---- Verificar disponibilidad (evitar race condition) ----
// Calculamos el bloque de tiempo del nuevo slot
list($hh, $mm) = explode(':', $hora);
$nuevoInicio = (int)$hh * 60 + (int)$mm;
$nuevoFin    = $nuevoInicio + $duracion;

// Buscar solapamientos en reservas_web
$stmtChkRW = $pdo->prepare(
    'SELECT rw.hora_inicio, s.duracion_minutos
     FROM reservas_web rw
     JOIN servicios s ON s.id_servicio = rw.id_servicio
     WHERE rw.fecha = :fecha AND rw.estado != "cancelada"'
);
$stmtChkRW->execute([':fecha' => $fecha]);
foreach ($stmtChkRW->fetchAll() as $r) {
    list($rh, $rm) = explode(':', $r['hora_inicio']);
    $rIni = (int)$rh * 60 + (int)$rm;
    $rFin = $rIni + (int)$r['duracion_minutos'];
    if ($nuevoInicio < $rFin && $nuevoFin > $rIni) {
        echo json_encode(['ok' => false, 'error' => 'Lo sentimos, esa hora acaba de ser reservada. Por favor, elige otra.']);
        exit;
    }
}

// Buscar solapamientos en citas
$stmtChkC = $pdo->prepare(
    'SELECT hora_inicio, duracion_minutos
     FROM citas
     WHERE fecha = :fecha AND estado NOT IN ("cancelada", "finalizada")'
);
$stmtChkC->execute([':fecha' => $fecha]);
foreach ($stmtChkC->fetchAll() as $c) {
    list($ch, $cm) = explode(':', $c['hora_inicio']);
    $cIni = (int)$ch * 60 + (int)$cm;
    $cFin = $cIni + (int)$c['duracion_minutos'];
    if ($nuevoInicio < $cFin && $nuevoFin > $cIni) {
        echo json_encode(['ok' => false, 'error' => 'Lo sentimos, esa hora acaba de ser reservada. Por favor, elige otra.']);
        exit;
    }
}

// ---- Determinar si el usuario tiene sesión ----
$idClienteWeb = null;
if (!empty($_SESSION['usuario_id'])) {
    // Intentar encontrar el cliente asociado al usuario de sesión por email
    // La sesión guarda usuario_email
    $stmtCli = $pdo->prepare('SELECT id_cliente FROM clientes WHERE correo = :email LIMIT 1');
    $stmtCli->execute([':email' => $_SESSION['usuario_email'] ?? '']);
    $cli = $stmtCli->fetch();
    if ($cli) {
        $idClienteWeb = (int) $cli['id_cliente'];
    }
}

// ---- Guardar la reserva en BD ----
$stmt = $pdo->prepare(
    'INSERT INTO reservas_web (nombre, email, telefono, fecha, hora_inicio, id_servicio, id_cliente_web, estado, notas)
     VALUES (:nombre, :email, :telefono, :fecha, :hora, :id_servicio, :id_cliente_web, "pendiente", :notas)'
);
$stmt->execute([
    ':nombre'        => $nombre,
    ':email'         => $email,
    ':telefono'      => $telefono ?: null,
    ':fecha'         => $fecha,
    ':hora'          => $hora,
    ':id_servicio'   => $idServicio,
    ':id_cliente_web'=> $idClienteWeb,
    ':notas'         => $notas ?: null,
]);

// ---- Enviar email de confirmación con PHPMailer + Gmail SMTP ----
$fechaFormateada = date('d/m/Y', strtotime($fecha));

// Construir cuerpo HTML del email
$cuerpoHTML = "
<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; background:#f9f9f9; padding:0; margin:0;'>
  <div style='max-width:520px; margin:30px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.1);'>
    <div style='background:#111827; padding:28px 32px; text-align:center;'>
      <h1 style='color:#f59e0b; margin:0; font-size:22px;'>✂️ Peluquería tecnológica</h1>
      <p style='color:#9ca3af; margin:6px 0 0; font-size:13px;'>Confirmación de cita</p>
    </div>
    <div style='padding:32px;'>
      <p style='color:#374151; font-size:16px; margin:0 0 20px;'>Hola <strong>{$nombre}</strong>,</p>
      <p style='color:#374151; font-size:15px; margin:0 0 24px;'>¡Tu cita ha sido <strong style='color:#10b981;'>confirmada con éxito!</strong> Aquí tienes el resumen:</p>
      <div style='background:#f3f4f6; border-radius:10px; padding:20px; margin-bottom:24px;'>
        <table style='width:100%; border-collapse:collapse;'>
          <tr>
            <td style='padding:8px 0; color:#6b7280; font-size:13px; width:40%;'>📅 Fecha</td>
            <td style='padding:8px 0; color:#111827; font-weight:700; font-size:15px;'>{$fechaFormateada}</td>
          </tr>
          <tr>
            <td style='padding:8px 0; color:#6b7280; font-size:13px;'>🕐 Hora</td>
            <td style='padding:8px 0; color:#111827; font-weight:700; font-size:15px;'>{$hora}</td>
          </tr>
          <tr>
            <td style='padding:8px 0; color:#6b7280; font-size:13px;'>✂️ Servicio</td>
            <td style='padding:8px 0; color:#111827; font-weight:700; font-size:15px;'>{$nombreServicio}</td>
          </tr>
        </table>
      </div>
      <p style='color:#6b7280; font-size:13px; margin:0 0 6px;'>¿Necesitas cancelar o modificar tu cita?</p>
      <p style='color:#374151; font-size:13px; margin:0;'>📞 <a href='tel:+34123456789' style='color:#f59e0b;'>+34 123 456 789</a></p>
      <p style='color:#374151; font-size:13px; margin:4px 0 0;'>✉️ <a href='mailto:peluqueriatecnologica@gmail.com' style='color:#f59e0b;'>peluqueriatecnologica@gmail.com</a></p>
    </div>
    <div style='background:#f9fafb; border-top:1px solid #e5e7eb; padding:16px 32px; text-align:center;'>
      <p style='color:#9ca3af; font-size:12px; margin:0;'>¡Te esperamos! — Peluquería tecnológica<br>C/ Eusebio Rubalcaba, 123, 45600 Talavera de la Reina</p>
    </div>
  </div>
</body>
</html>";

$emailEnviado = false;
$emailError   = '';

try {
    $mail = new PHPMailer(true);

    // Configuración del servidor SMTP
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_PORT;
    $mail->CharSet    = 'UTF-8';

    // Remitente y destinatario
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress($email, $nombre);
    $mail->addReplyTo('peluqueriatecnologica@gmail.com', 'Peluquería tecnológica');

    // Contenido del email
    $mail->isHTML(true);
    $mail->Subject = '✂️ Confirmación de tu cita - Peluquería tecnológica';
    $mail->Body    = $cuerpoHTML;
    $mail->AltBody = "Hola {$nombre},\n\nTu cita ha sido confirmada.\nFecha: {$fechaFormateada}\nHora: {$hora}\nServicio: {$nombreServicio}\n\n¡Te esperamos!\n— Peluquería tecnológica";

    $mail->send();
    $emailEnviado = true;

} catch (MailException $e) {
    // El email falló pero la reserva ya está guardada en BD
    $emailError = $mail->ErrorInfo;
}

// ---- Respuesta de éxito ----
echo json_encode([
    'ok'           => true,
    'mensaje'      => $emailEnviado
        ? "¡Reserva confirmada! Hemos enviado los detalles a {$email}."
        : "¡Reserva confirmada! (No se pudo enviar el email: configura las credenciales de Gmail en guardar_cita.php)",
    'email_enviado'=> $emailEnviado,
    'resumen'      => [
        'nombre'   => $nombre,
        'fecha'    => $fechaFormateada,
        'hora'     => $hora,
        'servicio' => $nombreServicio,
    ],
]);
