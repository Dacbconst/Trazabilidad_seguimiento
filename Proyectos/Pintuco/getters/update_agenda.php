<?php
// update_agenda.php — guarda la gestión del analista sobre una visita agendada
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Content-Type: application/json');

include_once '../db_connect.php';

$id     = isset($_POST['id'])     ? (int)$_POST['id'] : 0;
$accion = isset($_POST['accion']) ? $_POST['accion']  : 'guardar';

if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "Falta el id de la visita."]);
    exit;
}

// Cancelar y eliminar son las dos únicas acciones donde el analista elige el
// estado directamente; en "guardar" el estado lo decide el backend (ver más
// abajo). Cancelar es un estado de negocio real (el cliente canceló, la
// visita sigue visible en el historial); eliminar es borrado lógico vía
// "activar" para errores/duplicados que no deben aparecer en ningún lado.
if ($accion === 'cancelar') {
    $query = "UPDATE insert_proyectos_contacto SET estado_agenda = 'cancelada' WHERE id = ?";
    if ($sql = $mysqli->prepare($query)) {
        $sql->bind_param("i", $id);
        $ok = $sql->execute();
        $sql->close();
        echo json_encode(["success" => $ok, "message" => $ok ? "Visita cancelada." : $mysqli->error]);
    } else {
        echo json_encode(["success" => false, "message" => $mysqli->error]);
    }
    exit;
}

if ($accion === 'eliminar') {
    // activar es varchar(2) 'SI'/'NO' (confirmado contra la tabla real).
    $query = "UPDATE insert_proyectos_contacto SET activar = 'NO' WHERE id = ?";
    if ($sql = $mysqli->prepare($query)) {
        $sql->bind_param("i", $id);
        $ok = $sql->execute();
        $sql->close();
        echo json_encode(["success" => $ok, "message" => $ok ? "Visita eliminada." : $mysqli->error]);
    } else {
        echo json_encode(["success" => false, "message" => $mysqli->error]);
    }
    exit;
}

$fecha   = isset($_POST['fecha'])   ? $_POST['fecha']   : '';
$hora    = isset($_POST['hora'])    ? $_POST['hora']    : '';
$tecnico = isset($_POST['tecnico']) ? $_POST['tecnico'] : '';

// Estado automático: si la visita no tenía hora asignada todavía, esta
// gestión la agenda por primera vez; si ya tenía una, guardar de nuevo
// (cambie o no la fecha/hora) significa que se está reagendando.
$horaPrevia = null;
if ($sql = $mysqli->prepare("SELECT hora FROM insert_proyectos_contacto WHERE id = ?")) {
    $sql->bind_param("i", $id);
    $sql->execute();
    $sql->bind_result($horaPrevia);
    $sql->fetch();
    $sql->close();
}
$estado_agenda = ($horaPrevia === null || $horaPrevia === '') ? 'agendada' : 'reagendada';

// El título no se modifica aquí (viene de la base de datos) y el lugar se
// sincroniza con la dirección ya registrada para el contacto. La fecha solo
// se actualiza si llega un valor (nunca se deja la visita sin fecha; para
// eso está la acción "eliminar").
if ($fecha !== '') {
    $query = "UPDATE insert_proyectos_contacto
              SET fecha_agendamiento = ?, hora = ?, tecnico = ?, estado_agenda = ?, lugar = direccion
              WHERE id = ?";
    if ($sql = $mysqli->prepare($query)) {
        $sql->bind_param("ssssi", $fecha, $hora, $tecnico, $estado_agenda, $id);
        $ok = $sql->execute();
        $sql->close();
        echo json_encode(["success" => $ok, "message" => $ok ? "Actualizado." : $mysqli->error]);
    } else {
        echo json_encode(["success" => false, "message" => $mysqli->error]);
    }
} else {
    $query = "UPDATE insert_proyectos_contacto
              SET hora = ?, tecnico = ?, estado_agenda = ?, lugar = direccion
              WHERE id = ?";
    if ($sql = $mysqli->prepare($query)) {
        $sql->bind_param("sssi", $hora, $tecnico, $estado_agenda, $id);
        $ok = $sql->execute();
        $sql->close();
        echo json_encode(["success" => $ok, "message" => $ok ? "Actualizado." : $mysqli->error]);
    } else {
        echo json_encode(["success" => false, "message" => $mysqli->error]);
    }
}
?>
