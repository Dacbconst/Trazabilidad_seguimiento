<?php
/**
 * update_proforma.php — auditoría web de proformas.
 *
 * Modelo de ciclos (2026-07-01): cada ronda de negociación es una fila propia
 * en insert_proforma (mismo id_agendamiento, id distinto). El historial de
 * fecha+monto por ronda se lee directamente de esas filas — no hace falta
 * una tabla de historial aparte.
 *
 * Acciones:
 *  'rechazar_calidad'   → Fase 3: la foto que llegó no sirve (borrosa, no es
 *                         la proforma, etc.). NO borra la foto — la deja
 *                         intacta en la fila y solo marca
 *                         estado_proforma='correccion_solicitada', que el
 *                         móvil debe leer para avisarle al promotor que
 *                         reenvíe. Reversible con 'cancelar_correccion'.
 *                         No toca monto/fecha_auditoria, no crea ciclo
 *                         nuevo, no es una ronda de negociación.
 *  'cancelar_correccion' → Deshace 'rechazar_calidad': vuelve el estado a
 *                         'en_proceso'. Como la foto nunca se borró, vuelve
 *                         a verse tal cual estaba; la alerta del móvil
 *                         desaparece en cuanto deja de ver
 *                         'correccion_solicitada'.
 *  'guardar'            → Fase 4: sella monto+fecha en la fila activa
 *                         (UPDATE por id, nunca INSERT). Confirmado con la
 *                         app móvil (2026-07-03): ya NO se crea un ciclo
 *                         nuevo vacío acá — esa fila vacía era el origen de
 *                         las "proformas fantasma"; ahora el promotor
 *                         registra la siguiente proforma desde la app en
 *                         cuanto ve el monto puesto.
 *  'rechazar'            → Rechazo definitivo (terminal). estado='rechazado'.
 *
 * La decisión final (fase 5) la toma el promotor al subir foto_factura desde
 * el celular. La web NO tiene botón de "aprobar"/"finalizar".
 */
error_reporting(0);
ini_set('display_errors', '0');

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

include_once '../db_connect.php';

$id            = isset($_POST['id'])             ? (int)$_POST['id']      : 0;
$accion        = isset($_POST['accion'])         ? trim($_POST['accion']) : '';
$monto         = (isset($_POST['monto'])          && $_POST['monto']          !== '') ? $_POST['monto']          : null;
$observaciones = (isset($_POST['observaciones']) && $_POST['observaciones'] !== '') ? $_POST['observaciones'] : null;

if ($id <= 0 || !in_array($accion, ['rechazar_calidad', 'cancelar_correccion', 'guardar', 'rechazar'], true)) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
    exit;
}

// ── 1. 'rechazar_calidad': la foto no sirve, se pide reenvío. Reversible —
//      NO se borra nada, solo se marca el estado; ver 'cancelar_correccion'.
if ($accion === 'rechazar_calidad') {
    $sql = $mysqli->prepare(
        "UPDATE insert_proforma SET estado_proforma = 'correccion_solicitada' WHERE id = ?"
    );
    if (!$sql) { echo json_encode(['success' => false, 'message' => $mysqli->error]); exit; }
    $sql->bind_param('i', $id);
    $ok = $sql->execute();
    $sql->close();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Se pidió una corrección.' : $mysqli->error]);
    exit;
}

// ── 1b. 'cancelar_correccion': deshace el paso anterior ──────────────────────
if ($accion === 'cancelar_correccion') {
    $sql = $mysqli->prepare(
        "UPDATE insert_proforma SET estado_proforma = 'en_proceso' WHERE id = ?"
    );
    if (!$sql) { echo json_encode(['success' => false, 'message' => $mysqli->error]); exit; }
    $sql->bind_param('i', $id);
    $ok = $sql->execute();
    $sql->close();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Corrección cancelada.' : $mysqli->error]);
    exit;
}

// ── 2. 'rechazar': terminal, no abre ciclo nuevo ─────────────────────────────
if ($accion === 'rechazar') {
    $sql = $mysqli->prepare(
        "UPDATE insert_proforma
         SET estado_proforma = 'rechazado', monto_validado = ?, observaciones_auditoria = ?,
             fecha_auditoria = NOW(), fase_actual = 4
         WHERE id = ?"
    );
    if (!$sql) { echo json_encode(['success' => false, 'message' => $mysqli->error]); exit; }
    $sql->bind_param('ssi', $monto, $observaciones, $id);
    $ok = $sql->execute();
    $sql->close();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Rechazada.' : $mysqli->error]);
    exit;
}

// ── 3. 'guardar': monto es obligatorio en esta acción — sella la ronda
//     actual (UPDATE por id, nunca INSERT de un ciclo nuevo) ────────────────
if ($monto === null || $monto === '') {
    echo json_encode(['success' => false, 'message' => 'El monto cotizado es obligatorio.']);
    exit;
}

$sql = $mysqli->prepare(
    "UPDATE insert_proforma
     SET estado_proforma = 'en_negociacion', monto_validado = ?, observaciones_auditoria = ?,
         fecha_auditoria = NOW(), fase_actual = 4
     WHERE id = ?"
);
if (!$sql) { echo json_encode(['success' => false, 'message' => $mysqli->error]); exit; }
$sql->bind_param('ssi', $monto, $observaciones, $id);
$ok = $sql->execute();
$sql->close();

// Ya NO se inserta un ciclo nuevo vacío acá (confirmado con la app móvil,
// 2026-07-03): esa fila vacía era el origen de las "proformas fantasma" que
// aparecían solas. Ahora el promotor registra la siguiente proforma desde
// la propia app en cuanto ve el monto puesto — el backend solo actualiza la
// fila existente por su id, nunca inserta como parte de esta acción.
echo json_encode(['success' => $ok, 'message' => $ok ? 'Guardado.' : $mysqli->error]);
?>
