<?php
// =============================================================
// DESCRIPCIÓN: Define la variable $servicios (array de ejemplo),
//              la función generarTarjetasServicios() que genera
//              el HTML de las tarjetas, y la función 
//              obtenerServiciosDB() que obtiene los servicios 
//              directamente desde la base de datos.
//              También contiene la lógica del formulario de 
//              contacto en la página principal.
// =============================================================


// ---- Array de servicios de respaldo (fallback) ----
// Se usa si la consulta a la BD falla o si la BD está vacía.
// En condiciones normales, los datos vendrán de la tabla `servicios`.
$servicios = [
    [
        'titulo'      => 'Corte y Diseño',
        'descripcion' => 'Cortes modernos, afeitados clásicos y asesoramiento de estilo personalizado. Siempre a la vanguardia de las tendencias.',
        'icono'       => 'scissors'
    ],
    [
        'titulo'      => 'Coloración Balayage',
        'descripcion' => 'Técnicas de coloración avanzadas, Balayage, mechas y corrección de color. Utilizamos productos orgánicos.',
        'icono'       => 'palette'
    ],
    [
        'titulo'      => 'Tratamientos Capilares',
        'descripcion' => 'Keratina, Botox Capilar y tratamientos de hidratación profunda para restaurar la salud y brillo de tu cabello.',
        'icono'       => 'droplet'
    ],
    [
        'titulo'      => 'Bodas y Eventos',
        'descripcion' => 'Peinados de novia y servicios especiales para eventos. Nos encargamos de que luzcas espectacular en tu gran día.',
        'icono'       => 'calendar'
    ],
];


// ============================================================
// FUNCIÓN: obtenerServiciosDB($pdo)
// PROPÓSITO: Consulta la tabla `servicios` en la base de datos
//            y devuelve un array con los servicios ACTIVOS,
//            formateado igual que el array $servicios de arriba
//            para que sea compatible con generarTarjetasServicios().
// PARÁMETRO: $pdo → objeto PDO de conexión a la BD
// RETORNA: array de servicios (o array vacío si no hay datos)
// ============================================================
function obtenerServiciosDB(PDO $pdo): array
{
    // Mapeo de nombres de servicio a iconos de Lucide Icons.
    // Se busca la palabra clave en el nombre del servicio para asignar el icono.
    $mapaIconos = [
        'corte'      => 'scissors',
        'color'      => 'palette',
        'barba'      => 'user',
        'tratamient' => 'droplet',
        'boda'       => 'calendar',
        'evento'     => 'calendar',
        'peinado'    => 'star',
    ];

    try {
        // Consulta preparada: selecciona solo los servicios activos
        // ordenados por nombre de forma ascendente.
        $stmt = $pdo->prepare(
            'SELECT id_servicio, nombre, descripcion, precio, duracion_minutos
             FROM servicios
             WHERE activo = 1
             ORDER BY nombre ASC'
        );
        $stmt->execute(); // Ejecuta la consulta

        $resultado = [];

        // Recorre cada fila devuelta por la consulta
        while ($fila = $stmt->fetch()) {
            // Busca el icono más adecuado según el nombre del servicio
            $icono = 'scissors'; // Icono por defecto si no hay coincidencia
            $nombreLower = mb_strtolower($fila['nombre']); // Convierte a minúsculas para comparar

            foreach ($mapaIconos as $clave => $nombreIcono) {
                if (str_contains($nombreLower, $clave)) {
                    $icono = $nombreIcono;
                    break; // Para en el primer match encontrado
                }
            }

            // Añade el servicio al array de resultados con el formato esperado
            $resultado[] = [
                'titulo'      => $fila['nombre'],
                // La descripción incluye el precio y duración extraídos de la BD
                'descripcion' => ($fila['descripcion'] ?? 'Servicio profesional.')
                               . ' — ' . number_format($fila['precio'], 2, ',', '.') . ' €'
                               . ' (' . $fila['duracion_minutos'] . ' min)',
                'icono'       => $icono,
            ];
        }

        return $resultado; // Devuelve el array con todos los servicios

    } catch (PDOException $e) {
        // Si la consulta falla, devolvemos array vacío para que el sistema
        // use el array de respaldo $servicios definido arriba.
        return [];
    }
}


// ============================================================
// FUNCIÓN: generarTarjetasServicios($lista)
// PROPÓSITO: Genera el HTML de las tarjetas de servicio a partir
//            de un array de servicios (sea de la BD o el de respaldo).
// PARÁMETRO: $lista → array de servicios
// RETORNA: string con el HTML de todas las tarjetas
// ============================================================
function generarTarjetasServicios(array $lista): string
{
    // Si no hay servicios, muestra un mensaje informativo
    if (empty($lista)) {
        return '<p class="text-gray-500 col-span-4 text-center">
                    No hay servicios disponibles en este momento.
                </p>';
    }

    $html = ''; // Acumulador de HTML

    foreach ($lista as $servicio) {
        // Cada tarjeta es un div con sombra y efecto hover (clases de Tailwind)
        $html .= '<div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">';

        // Icono de Lucide (se inicializa con lucide.createIcons() al final de la página)
        $html .= '<i data-lucide="' . htmlspecialchars($servicio['icono']) . '" class="w-8 h-8 mx-auto mb-4 text-amber-500"></i>';

        // Título del servicio (escapado para evitar XSS)
        $html .= '<h4 class="text-xl font-semibold text-gray-800 mb-2">'
               . htmlspecialchars($servicio['titulo'])
               . '</h4>';

        // Descripción del servicio (escapada para evitar XSS)
        $html .= '<p class="text-gray-600">'
               . htmlspecialchars($servicio['descripcion'])
               . '</p>';

        $html .= '</div>'; // Cierre del div de la tarjeta
    }

    return $html; // Devuelve todo el HTML generado
}


// ============================================================
// LÓGICA: Formulario de contacto de la página principal
// PROPÓSITO: Procesa los datos del formulario enviado por POST.
//            Valida los campos y genera el mensaje de respuesta
//            que se muestra en la página.
// ============================================================
$mensaje_formulario = ''; // Variable que almacenará el HTML del mensaje de respuesta

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recoge y limpia los campos del formulario
    // trim() elimina espacios en blanco al principio y al final
    $nombre  = trim($_POST['nombre']  ?? '');
    $email   = trim($_POST['email']   ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');

    // Validación: comprueba que ningún campo esté vacío
    if (empty($nombre) || empty($email) || empty($mensaje)) {
        // Mensaje de error en rojo si faltan campos
        $mensaje_formulario = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                                    ⚠️ ¡Error! Por favor, rellena todos los campos.
                               </div>';
    } else {
        // Validación adicional: comprueba que el email tiene formato válido
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensaje_formulario = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
                                        ⚠️ Por favor, introduce un correo electrónico válido.
                                   </div>';
        } else {
            // Guardamos el mensaje en la BD usando la variable global $pdo
            global $pdo;
            if (isset($pdo)) {
                try {
                    $stmt = $pdo->prepare('INSERT INTO mensajes_contacto (nombre, email, mensaje) VALUES (?, ?, ?)');
                    $stmt->execute([$nombre, $email, $mensaje]);
                    $mensaje_formulario = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                                                ✅ ¡Gracias, ' . htmlspecialchars($nombre) . '! Tu mensaje ha sido recibido y guardado. Te contactaremos pronto.
                                           </div>';
                } catch (PDOException $e) {
                    $mensaje_formulario = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                                                ⚠️ Hubo un error al guardar tu mensaje. Por favor, intenta de nuevo más tarde.
                                           </div>';
                }
            } else {
                 $mensaje_formulario = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                                            ⚠️ Error de conexión. No se pudo procesar tu mensaje.
                                        </div>';
            }
        }
    }
}
?>