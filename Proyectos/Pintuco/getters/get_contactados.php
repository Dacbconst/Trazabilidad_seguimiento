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

// LEFT JOIN con insert_proforma para obtener datos comerciales (foto_factura,
// monto_validado, evidencia) — puede devolver N filas por contacto si tiene
// varios ciclos de proforma; la deduplicación (quedarse con el proforma_id
// mayor) se hace en JS igual que en estado-flujo.js, para no usar subqueries
// (Azure/IIS: una subquery en query() devuelve false silenciosamente).
$query = "SELECT c.id, c.codigo_pdv, c.pdv, c.direccion, c.latitud, c.longitud,
                 c.usuario, c.contacto, c.empresa, c.mail, c.telefono,
                 c.telefono_convencional, c.fecha_registro, c.estado_agenda,
                 c.fecha_agendamiento, c.hora, c.tecnico,
                 p.id          AS proforma_id,
                 p.foto_factura,
                 p.monto_validado,
                 p.evidencia
          FROM insert_proyectos_contacto c
          LEFT JOIN insert_proforma p ON p.id_agendamiento = c.id
          WHERE " . implode(" AND ", $condiciones) . "
          ORDER BY c.fecha_registro DESC, p.id ASC";

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
