<?php
// insert_pago_factura.php — registra UN pago/cuota de una factura pagada a
// plazos (nueva mecánica confirmada con la app móvil 2026-07-03). Se sube un
// pago a la vez: cada cuota es su propia fila en insert_pago_factura, con
// id_proforma apuntando a la fila de insert_proforma que tiene
// monto_total_factura/plazo_meses (la cotización acordada). Un pago único
// (plazo_meses NULL o 0) simplemente sube su única fila aquí igual, con
// numero_cuota = 1.
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

include_once '../db_connect.php';

$id_proforma        = isset($_POST['id_proforma'])        ? (int)$_POST['id_proforma']        : 0;
$id_agendamiento     = isset($_POST['id_agendamiento']) && $_POST['id_agendamiento'] !== ''     ? (int)$_POST['id_agendamiento'] : null;
$codigo_pdv          = isset($_POST['codigo_pdv'])         ? $_POST['codigo_pdv']              : null;
$usuario             = isset($_POST['usuario'])            ? $_POST['usuario']                 : null;
$numero_cuota        = isset($_POST['numero_cuota'])        ? (int)$_POST['numero_cuota']       : 0;
$monto_pago          = isset($_POST['monto_pago']) && $_POST['monto_pago'] !== ''               ? $_POST['monto_pago']           : '';
$foto_pago           = isset($_POST['foto_pago'])          ? $_POST['foto_pago']                : null;
$fecha_pago          = isset($_POST['fecha_pago']) && $_POST['fecha_pago'] !== ''               ? $_POST['fecha_pago']           : null;

function error($mensaje) {
    echo json_encode(["success" => false, "message" => $mensaje]);
    exit;
}

if ($id_proforma <= 0)   error("Falta id_proforma.");
if ($numero_cuota <= 0)  error("numero_cuota debe ser un entero mayor a 0.");
if ($monto_pago === '' || !is_numeric($monto_pago)) error("monto_pago es obligatorio y debe ser numérico.");

$query = "INSERT INTO insert_pago_factura
    (id_proforma, id_agendamiento, codigo_pdv, usuario, numero_cuota, monto_pago, foto_pago, fecha_pago, pendiente_insercion, fecha_registro)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())";

if ($sql = $mysqli->prepare($query)) {
    $sql->bind_param(
        'iississs',
        $id_proforma, $id_agendamiento, $codigo_pdv, $usuario,
        $numero_cuota, $monto_pago, $foto_pago, $fecha_pago
    );
    $ok = $sql->execute();
    $nuevoId = $mysqli->insert_id;
    $sql->close();
    echo json_encode(["success" => $ok, "id" => $nuevoId, "message" => $ok ? "Pago registrado." : $mysqli->error]);
} else {
    echo json_encode(["success" => false, "message" => $mysqli->error]);
}
?>
