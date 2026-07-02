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
 *  'guardar'            → Fase 4: cierra la ronda actual (sella monto+fecha
 *                         en la fila activa) y abre automáticamente un
 *                         ciclo nuevo vacío para la siguiente foto — igual
 *                         patrón que ya usaba 'negociacion' antes.
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

// ── 3. 'guardar': monto es obligatorio en esta acción — sella la ronda actual
//     y abre el siguiente ciclo vacío esperando la próxima foto ──────────────
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
if (!$ok) { echo json_encode(['success' => false, 'message' => $mysqli->error]); exit; }

// Ciclo nuevo, sin evidencia/reporte (a propósito: si se copiara la foto
// vieja, el analista la vería como si ya hubiera llegado la nueva).
$orig = null;
$copyRes = $mysqli->query(
    "SELECT id_agendamiento, codigo_pdv, usuario FROM insert_proforma WHERE id=$id"
);
if ($copyRes) {
    $orig = $copyRes->fetch_assoc();
    $copyRes->free();
}
if ($orig) {
    $ins = $mysqli->prepare(
        "INSERT INTO insert_proforma
         (id_agendamiento, codigo_pdv, usuario,
          estado_proforma, fase_actual, pendiente_insercion, fecha_registro)
         VALUES (?, ?, ?, 'en_proceso', 4, 0, NOW())"
    );
    if ($ins) {
        $ins->bind_param('sss', $orig['id_agendamiento'], $orig['codigo_pdv'], $orig['usuario']);
        $ins->execute();
        $ins->close();
    }
}

echo json_encode(['success' => true, 'message' => 'Guardado.']);
?>
