<?php
// get_promotores.php — lista de promotores/mercaderistas reales del canal
// KYWI, para el selector "Promotor" del modal "Nueva visita". Antes ese
// select se llenaba con los valores de `usuario` que ya traía la agenda,
// pero esa columna no identifica al promotor individual (viene repetida
// para todos) — lvi_rutero.mercaderista sí es la persona real.
//
// Se agrega "city" (2026-07-10): el selector de PDV del mismo modal filtra
// por la ciudad del promotor elegido — mismo criterio que ya usa el celular
// (PintucoAPP/getPdvsContacto filtra por región del promotor logueado), acá
// aplicado en el cliente porque la web no tiene un promotor "logueado" fijo.
// data ahora es un array de {mercaderista, city} en vez de strings — un
// promotor puede tener varias filas (una por PDV asignado), el JS agrupa
// las ciudades por promotor.
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, OPTIONS');

include_once '../db_connect.php';

$query = "SELECT DISTINCT mercaderista, city
          FROM lvi_rutero
          WHERE subchannel LIKE '%COMERCIAL KYWI S.A.%'
            AND activar = 'SI'
            AND habilitado = '1'
          ORDER BY mercaderista ASC";

$registros = [];
if ($resultado = $mysqli->query($query)) {
    while ($fila = $resultado->fetch_assoc()) {
        $registros[] = $fila;
    }
}

echo json_encode(["data" => $registros]);
?>
