<?php
/**
 * update_proforma.php — resolución de la auditoría (3 acciones posibles).
 *
 * Estados CONFIRMADOS con el equipo de la app móvil (2026-06-30):
 * Constantes.java ya trae 'pendiente'/'en_proceso'/'realizado' — ninguno de
 * los 3 de abajo existía todavía del lado de la app. Se acordaron estos 3
 * literales nuevos (lowercase, con "en_" para los compuestos, igual
 * convención que 'en_proceso') y el equipo de Android va a actualizar su
 * AdapterProforma.java en paralelo para reconocerlos — mientras no lo
 * hagan, la app va a mostrar estas 3 visitas como "Pendiente" (su
 * comportamiento documentado para cualquier valor que no reconoce, no es
 * un error, así seguirá funcionando sin romperse).
 */
error_reporting(0);
ini_set('display_errors', '0');

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

include_once '../db_connect.php';

$ESTADOS_POR_ACCION = [
    'negociacion' => 'en_negociacion',
    'aprobar'     => 'aprobado',
    'rechazar'    => 'rechazado',
];

$id            = isset($_POST['id'])     ? (int) $_POST['id'] : 0;
$accion        = isset($_POST['accion']) ? $_POST['accion']   : '';
$monto         = isset($_POST['monto']) && $_POST['monto'] !== ''                 ? $_POST['monto']         : null;
$observaciones = isset($_POST['observaciones']) && $_POST['observaciones'] !== '' ? $_POST['observaciones'] : null;

if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "Falta el id de la proforma."]);
    exit;
}
if (!isset($ESTADOS_POR_ACCION[$accion])) {
    echo json_encode(["success" => false, "message" => "Acción inválida."]);
    exit;
}

$nuevoEstado = $ESTADOS_POR_ACCION[$accion];

// Detectar si las columnas de auditoría ya existen para elegir la query correcta.
// Esto evita que un prepare() fallido deje el mysqli en estado de error y rompa
// el segundo intento (comportamiento documentado en algunos PHP/MySQL en Azure).
$colCheck = $mysqli->query(
    "SELECT COUNT(*) AS tiene
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'insert_proforma'
       AND COLUMN_NAME  = 'monto_validado'"
);
$tieneColumnas = false;
if ($colCheck) {
    $row = $colCheck->fetch_assoc();
    $tieneColumnas = ($row && (int)$row['tiene'] > 0);
    $colCheck->free();
}

if ($tieneColumnas) {
    $query = "UPDATE insert_proforma
              SET estado_proforma = ?, monto_validado = ?, observaciones_auditoria = ?, fecha_auditoria = NOW()
              WHERE id = ?";
    $sql = $mysqli->prepare($query);
    if ($sql) {
        $sql->bind_param("sssi", $nuevoEstado, $monto, $observaciones, $id);
        $ok = $sql->execute();
        $msg = $ok ? "Actualizado." : $mysqli->error;
        $sql->close();
        echo json_encode(["success" => $ok, "message" => $msg]);
    } else {
        echo json_encode(["success" => false, "message" => "Error preparando query: " . $mysqli->error]);
    }
} else {
    // Columnas de auditoría aún no existen — solo actualiza el estado.
    $query = "UPDATE insert_proforma SET estado_proforma = ? WHERE id = ?";
    $sql = $mysqli->prepare($query);
    if ($sql) {
        $sql->bind_param("si", $nuevoEstado, $id);
        $ok = $sql->execute();
        $msg = $ok
            ? "Estado actualizado. Monto/observaciones NO guardados — correr alter_proforma_auditoria.sql para habilitar."
            : $mysqli->error;
        $sql->close();
        echo json_encode(["success" => $ok, "message" => $msg]);
    } else {
        echo json_encode(["success" => false, "message" => "Error preparando fallback: " . $mysqli->error]);
    }
}
?>
