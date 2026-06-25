<?php
// get_agenda.php — lista las visitas agendadas para el panel del analista (Web)
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

include_once '../db_connect.php';

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

$query = "SELECT id, codigo_pdv, pdv, usuario, fecha, contacto, empresa, mail, direccion,
                 latitud, longitud, telefono, fecha_agendamiento, titulo, hora, lugar,
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
