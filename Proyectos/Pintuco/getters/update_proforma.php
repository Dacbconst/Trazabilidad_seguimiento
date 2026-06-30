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
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

include_once '../db_connect.php';

$ESTADOS_POR_ACCION = [
    'negociacion' => 'en_negociacion', // Enviar a Negociación (Paso 4)
    'aprobar'     => 'aprobado',       // Aprobar Proforma — cierra la auditoría, queda pendiente de factura para el Paso 5 real
    'rechazar'    => 'rechazado',      // Rechazar / Evidencia falsa — estado terminal
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

// monto_validado / observaciones_auditoria / fecha_auditoria son columnas
// NUEVAS que todavía no existen en insert_proforma (ver memoria del
// proyecto para el ALTER TABLE pendiente). Se intenta primero con ellas;
// si la columna no existe, prepare() falla y se reintenta solo con el
// estado para que la auditoría no quede totalmente bloqueada mientras
// tanto (aunque sin guardar monto/observaciones hasta correr el ALTER).
$query = "UPDATE insert_proforma
          SET estado_proforma = ?, monto_validado = ?, observaciones_auditoria = ?, fecha_auditoria = NOW()
          WHERE id = ?";

if ($sql = $mysqli->prepare($query)) {
    $sql->bind_param("sssi", $nuevoEstado, $monto, $observaciones, $id);
    $ok = $sql->execute();
    $sql->close();
    echo json_encode(["success" => $ok, "message" => $ok ? "Actualizado." : $mysqli->error]);
} else {
    $queryFallback = "UPDATE insert_proforma SET estado_proforma = ? WHERE id = ?";
    if ($sql = $mysqli->prepare($queryFallback)) {
        $sql->bind_param("si", $nuevoEstado, $id);
        $ok = $sql->execute();
        $sql->close();
        echo json_encode([
            "success" => $ok,
            "message" => $ok
                ? "Estado actualizado, pero monto/observaciones NO se guardaron — faltan columnas en insert_proforma (correr el ALTER TABLE pendiente)."
                : $mysqli->error
        ]);
    } else {
        echo json_encode(["success" => false, "message" => $mysqli->error]);
    }
}
?>
