<?php
// get_agenda.php — lista las visitas agendadas para el panel del analista (Web)
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

include_once '../db_connect.php';

// "vencida" no la elige nadie a mano: el contrato con la app móvil (que lee
// esta misma tabla directo por sync) dice que una visita pasa a 'vencida'
// en cuanto se le acaba el día sin reagendarse, sin importar si antes era
// 'pendiente', 'confirmado' o 'reagendada'. No hay un cron/job en este
// hosting, así que se corrige de forma perezosa acá: cada vez que se abre
// la agenda (web o, indirectamente, el próximo sync del celular) se ponen
// al día las filas vencidas antes de leer.
$mysqli->query(
    "UPDATE insert_proyectos_contacto
     SET estado_agenda = 'vencida'
     WHERE activar = 'SI'
       AND fecha_agendamiento IS NOT NULL
       AND fecha_agendamiento != '0000-00-00'
       AND fecha_agendamiento < CURDATE()
       AND estado_agenda NOT IN ('cancelada', 'completada', 'vencida')"
);

// "completada" tampoco la elige nadie a mano: en cuanto llega la primera foto
// de proforma (insert_proforma.evidencia, la sube el promotor desde el
// celular) para un agendamiento, se asume que la visita ya se hizo — se
// corrige perezosamente acá, igual que "vencida" arriba. Corre DESPUÉS de la
// corrección de "vencida" a propósito: si una visita venció sin reagendarse
// pero después llegó su foto de proforma, sí debe pasar a "completada" (la
// visita sí ocurrió), pisando el "vencida". Cancelada nunca se pisa: es una
// decisión manual del analista.
$mysqli->query(
    "UPDATE insert_proyectos_contacto c
     JOIN insert_proforma p ON p.id_agendamiento = c.id
     SET c.estado_agenda = 'completada'
     WHERE c.activar = 'SI'
       AND p.evidencia IS NOT NULL AND p.evidencia != ''
       AND c.estado_agenda NOT IN ('cancelada', 'completada')"
);

// Filtros opcionales por GET (todos opcionales, si no llegan no se aplican)
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin    = isset($_GET['fecha_fin'])    ? $_GET['fecha_fin']    : '';
$estado       = isset($_GET['estado_agenda'])? $_GET['estado_agenda']: '';
$usuario      = isset($_GET['usuario'])      ? $_GET['usuario']      : '';
$tecnico      = isset($_GET['tecnico'])      ? $_GET['tecnico']      : '';
$pdv          = isset($_GET['pdv'])          ? $_GET['pdv']          : '';

// fecha_agendamiento es columna DATE real (no string dd/mm/yyyy); se compara directo.
// "activar" es varchar(2) NOT NULL con valores 'SI'/'NO' (default 'SI'),
// confirmado contra la tabla real — es el borrado lógico: filas en 'NO' son
// visitas eliminadas desde el panel y no deben aparecer en la agenda.
$condiciones = [
    "fecha_agendamiento IS NOT NULL",
    "fecha_agendamiento != '0000-00-00'",
    "activar = 'SI'",
];
$parametros  = [];
$tipos       = "";

if ($fecha_inicio !== '' && $fecha_fin !== '') {
    $condiciones[] = "fecha_agendamiento BETWEEN ? AND ?";
    $parametros[] = $fecha_inicio;
    $parametros[] = $fecha_fin;
    $tipos .= "ss";
}
if ($estado !== '') {
    $condiciones[] = "estado_agenda = ?";
    $parametros[] = $estado;
    $tipos .= "s";
} else {
    // Una vez asistida (llegó su primera foto de proforma, ver UPDATE de
    // arriba) la visita ya cumplió su propósito en la agenda — desaparece
    // del calendario por defecto. El analista todavía puede revisarlas
    // filtrando manualmente por Estado = "Completada".
    $condiciones[] = "estado_agenda != 'completada'";
}
if ($usuario !== '') {
    $condiciones[] = "usuario = ?";
    $parametros[] = $usuario;
    $tipos .= "s";
}
if ($tecnico !== '') {
    $condiciones[] = "tecnico = ?";
    $parametros[] = $tecnico;
    $tipos .= "s";
}
if ($pdv !== '') {
    $condiciones[] = "pdv = ?";
    $parametros[] = $pdv;
    $tipos .= "s";
}

$query = "SELECT id, codigo_pdv, pdv, usuario, fecha, fecha_registro, contacto, empresa, mail, direccion,
                 latitud, longitud, telefono, telefono_convencional, fecha_agendamiento, titulo, hora, lugar,
                 tecnico, estado_agenda, activar
          FROM insert_proyectos_contacto
          WHERE " . implode(" AND ", $condiciones) . "
          ORDER BY fecha_agendamiento, hora";

$registros = [];

if ($sql = $mysqli->prepare($query)) {
    if (!empty($parametros)) {
        $sql->bind_param($tipos, ...$parametros);
    }
    $sql->execute();
    $resultado = $sql->get_result();
    while ($fila = $resultado->fetch_assoc()) {
        $registros[] = $fila;
    }
    $sql->close();
}

echo json_encode(["data" => $registros, "count" => count($registros)]);
?>
