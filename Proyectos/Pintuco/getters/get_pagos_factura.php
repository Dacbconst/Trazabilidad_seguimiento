<?php
// get_pagos_factura.php — historial de pagos/cuotas de facturas a plazos.
//
// Sin ?id_proforma → TODOS los pagos (de todos los agendamientos). El panel
//   de Estado de Flujo pide esto una sola vez y suma monto_pago por
//   id_agendamiento en el JS, igual patrón que proformas_listar.php.
// Con ?id_proforma=X → solo los pagos de esa cotización puntual, ordenados
//   por numero_cuota (para el detalle de un agendamiento específico).
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

include_once '../db_connect.php';

$id_proforma = isset($_GET['id_proforma']) ? (int)$_GET['id_proforma'] : 0;

$selectBase = "SELECT id, id_proforma, id_agendamiento, codigo_pdv, usuario,
        numero_cuota, monto_pago, foto_pago, fecha_pago, observacion, fecha_registro
    FROM insert_pago_factura";

$registros = [];

if ($id_proforma > 0) {
    $sql = $mysqli->prepare($selectBase . " WHERE id_proforma = ? ORDER BY numero_cuota ASC");
    if ($sql) {
        $sql->bind_param('i', $id_proforma);
        if ($sql->execute()) {
            $res = $sql->get_result();
            if ($res) {
                while ($fila = $res->fetch_assoc()) { $registros[] = $fila; }
            }
        }
        $sql->close();
    }
} else {
    $res = $mysqli->query($selectBase . " ORDER BY id_agendamiento, numero_cuota ASC");
    if ($res) {
        while ($fila = $res->fetch_assoc()) { $registros[] = $fila; }
    }
}

echo json_encode(["data" => $registros], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
?>
