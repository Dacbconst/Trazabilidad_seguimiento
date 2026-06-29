<?php
// get_contactados.php — directorio de TODOS los contactos capturados (Web),
// a diferencia de get_agenda.php que solo lista los que ya tienen
// fecha_agendamiento. Este es el lugar que cierra ese hueco: un contacto
// recién llegado del lado móvil (sin agendar todavía) sí aparece aquí.
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

include_once '../db_connect.php';

// Filtros opcionales por GET (mismo patrón que get_agenda.php)
$estado  = isset($_GET['estado_agenda']) ? $_GET['estado_agenda'] : '';
$usuario = isset($_GET['usuario'])       ? $_GET['usuario']       : '';

$condiciones = ["activar = 'SI'"];
$parametros  = [];
$tipos       = "";

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

$query = "SELECT id, codigo_pdv, pdv, usuario, contacto, empresa, mail, telefono,
                 telefono_convencional, fecha_registro, estado_agenda, fecha_agendamiento
          FROM insert_proyectos_contacto
          WHERE " . implode(" AND ", $condiciones) . "
          ORDER BY fecha_registro DESC";

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
