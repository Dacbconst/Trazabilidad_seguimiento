<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Content-Type: application/json');

include_once '../db_connect.php';

$id_proforma = isset($_GET['id_proforma']) ? (int)$_GET['id_proforma'] : 0;
if ($id_proforma <= 0) { echo json_encode(['data' => []]); exit; }

$registros = [];
$res = $mysqli->query(
    "SELECT id, ciclo, monto, observaciones, accion, fecha_registro
     FROM insert_proforma_historial
     WHERE id_proforma = $id_proforma
     ORDER BY ciclo ASC"
);
if ($res) {
    while ($fila = $res->fetch_assoc()) { $registros[] = $fila; }
}
echo json_encode(['data' => $registros], JSON_UNESCAPED_UNICODE);
?>
