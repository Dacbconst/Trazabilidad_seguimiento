<?php
// update_agenda.php — guarda la gestión del analista sobre una visita agendada
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Content-Type: application/json');

include_once '../db_connect.php';

$id              = isset($_POST['id'])              ? (int)$_POST['id']      : 0;
$titulo          = isset($_POST['titulo'])           ? $_POST['titulo']       : '';
$hora            = isset($_POST['hora'])             ? $_POST['hora']         : '';
$lugar           = isset($_POST['lugar'])            ? $_POST['lugar']        : '';
$tecnico         = isset($_POST['tecnico'])           ? $_POST['tecnico']      : '';
$estado_agenda   = isset($_POST['estado_agenda'])     ? $_POST['estado_agenda'] : '';

$estados_validos = ['pendiente', 'confirmado'];

if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "Falta el id de la visita."]);
    exit;
}

if ($estado_agenda !== '' && !in_array($estado_agenda, $estados_validos, true)) {
    echo json_encode(["success" => false, "message" => "estado_agenda inválido."]);
    exit;
}

$query = "UPDATE insert_proyectos_contacto
          SET titulo = ?, hora = ?, lugar = ?, tecnico = ?, estado_agenda = ?
          WHERE id = ?";

if ($sql = $mysqli->prepare($query)) {
    $sql->bind_param("sssssi", $titulo, $hora, $lugar, $tecnico, $estado_agenda, $id);
    $ok = $sql->execute();
    $sql->close();
    echo json_encode(["success" => $ok, "message" => $ok ? "Actualizado." : $mysqli->error]);
} else {
    echo json_encode(["success" => false, "message" => $mysqli->error]);
}
?>
