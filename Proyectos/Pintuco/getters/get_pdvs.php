<?php
// get_pdvs.php — lista de PDV para el selector del modal "Nueva visita" (Web).
// El celular (PintucoAPP/getPdvsContacto) filtra esta misma tabla por la
// región del promotor logueado, porque ahí un solo usuario representa a un
// promotor. Acá el analista gestiona contactos de TODOS los promotores a la
// vez, así que se listan todos los PDV del canal sin filtrar por región —
// el filtro por ciudad del promotor elegido se aplica en el cliente
// (agenda-crear.js) usando la columna "city" agregada acá (2026-07-10).
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, OPTIONS');

include_once '../db_connect.php';

$query = "SELECT DISTINCT pos_id, pos_name, city
          FROM lvi_rutero
          WHERE subchannel LIKE '%COMERCIAL KYWI S.A.%'
            AND activar = 'SI'
          ORDER BY pos_name ASC";

$registros = [];
if ($resultado = $mysqli->query($query)) {
    while ($fila = $resultado->fetch_assoc()) {
        $registros[] = $fila;
    }
}

echo json_encode(["data" => $registros]);
?>
