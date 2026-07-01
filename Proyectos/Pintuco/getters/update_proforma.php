<?php
/**
 * update_proforma.php — auditoría web de proformas.
 *
 * Acciones:
 *  'guardar'     → Solo registra monto/obs. No cambia estado ni fase.
 *                  La web puede guardar múltiples veces mientras llega nueva evidencia.
 *  'negociacion' → Rechaza evidencia actual y pide nueva al promotor.
 *                  UPDATE estado='en_negociacion' + INSERT nuevo ciclo vacío.
 *  'rechazar'    → Rechazo definitivo (terminal). estado='rechazado', fase_actual=4.
 *
 * La decisión final (fase 5) la toma el promotor al subir foto_factura desde el celular.
 * La web NO tiene botón de "aprobar" ni de "finalizar" — eso es responsabilidad del móvil.
 *
 * Estados acordados con la app móvil (2026-06-30):
 *   Existentes: 'pendiente' / 'en_proceso' / 'realizado'
 *   Nuevos:     'en_negociacion' / 'rechazado'
 *   fase_actual: 4=negociación  5=completado (el móvil pone 4 al crear, 5 al subir factura)
 */
error_reporting(0);
ini_set('display_errors', '0');

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

include_once '../db_connect.php';

$ESTADOS = [
    'guardar'     => null,             // sin cambio de estado
    'negociacion' => 'en_negociacion', // pide nueva evidencia
    'rechazar'    => 'rechazado',      // terminal
];
$FASES = [
    'guardar'     => null,
    'negociacion' => 4,
    'rechazar'    => 4,
];

$id            = isset($_POST['id'])             ? (int)$_POST['id']     : 0;
$accion        = isset($_POST['accion'])         ? trim($_POST['accion']) : '';
$monto         = (isset($_POST['monto'])          && $_POST['monto']          !== '') ? $_POST['monto']          : null;
$observaciones = (isset($_POST['observaciones']) && $_POST['observaciones'] !== '') ? $_POST['observaciones'] : null;

if ($id <= 0 || !array_key_exists($accion, $ESTADOS)) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
    exit;
}

$nuevoEstado = $ESTADOS[$accion];
$nuevaFase   = $FASES[$accion];

// ── Detectar qué columnas nuevas existen ya (por si el ALTER no corrió aún) ──
function columnaExiste($mysqli, $tabla, $columna): bool {
    $r = $mysqli->query(
        "SELECT COUNT(*) AS tiene FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tabla' AND COLUMN_NAME='$columna'"
    );
    if (!$r) return false;
    $row = $r->fetch_assoc();
    $r->free();
    return (int)($row['tiene'] ?? 0) > 0;
}

$tieneMonto = columnaExiste($mysqli, 'insert_proforma', 'monto_validado');
$tieneFase  = columnaExiste($mysqli, 'insert_proforma', 'fase_actual');

// ── 1. Acción 'guardar': solo monto/obs, sin tocar estado ni fase ──────────────
if ($accion === 'guardar') {
    if ($tieneMonto) {
        $sql = $mysqli->prepare(
            "UPDATE insert_proforma
             SET monto_validado=?, observaciones_auditoria=?
             WHERE id=?"
        );
        if (!$sql) { echo json_encode(['success'=>false,'message'=>$mysqli->error]); exit; }
        $sql->bind_param('ssi', $monto, $observaciones, $id);
        $ok = $sql->execute();
        $sql->close();
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Guardado.' : $mysqli->error]);
    } else {
        echo json_encode(['success' => true, 'message' => 'Sin columnas de auditoría aún — correr ALTER TABLE.']);
    }
    exit;
}

// ── 2. UPDATE del estado del ciclo actual ──────────────────────────────────────
if ($tieneMonto && $tieneFase) {
    $sql = $mysqli->prepare(
        "UPDATE insert_proforma
         SET estado_proforma=?, monto_validado=?, observaciones_auditoria=?,
             fecha_auditoria=NOW(), fase_actual=?
         WHERE id=?"
    );
    if (!$sql) { echo json_encode(['success'=>false,'message'=>$mysqli->error]); exit; }
    $sql->bind_param('sssii', $nuevoEstado, $monto, $observaciones, $nuevaFase, $id);
} elseif ($tieneMonto) {
    $sql = $mysqli->prepare(
        "UPDATE insert_proforma
         SET estado_proforma=?, monto_validado=?, observaciones_auditoria=?, fecha_auditoria=NOW()
         WHERE id=?"
    );
    if (!$sql) { echo json_encode(['success'=>false,'message'=>$mysqli->error]); exit; }
    $sql->bind_param('sssi', $nuevoEstado, $monto, $observaciones, $id);
} else {
    $sql = $mysqli->prepare("UPDATE insert_proforma SET estado_proforma=? WHERE id=?");
    if (!$sql) { echo json_encode(['success'=>false,'message'=>$mysqli->error]); exit; }
    $sql->bind_param('si', $nuevoEstado, $id);
}

$ok = $sql->execute();
$sql->close();
if (!$ok) { echo json_encode(['success'=>false,'message'=>$mysqli->error]); exit; }

// ── 3. Si es 'negociacion': insertar nuevo ciclo para que el promotor suba ────
if ($accion === 'negociacion') {
    $orig = null;
    $copyRes = $mysqli->query(
        "SELECT id_agendamiento, codigo_pdv, usuario, evidencia,
                caracteristica_visita, acompanamiento_tecnico
         FROM insert_proforma WHERE id=$id"
    );
    if ($copyRes) {
        $orig = $copyRes->fetch_assoc();
        $copyRes->free();
    }
    if ($orig) {
        if ($tieneFase) {
            $ins = $mysqli->prepare(
                "INSERT INTO insert_proforma
                 (id_agendamiento, codigo_pdv, usuario, evidencia,
                  caracteristica_visita, acompanamiento_tecnico,
                  estado_proforma, fase_actual, pendiente_insercion, fecha_registro)
                 VALUES (?, ?, ?, ?, ?, ?, 'en_proceso', 4, 0, NOW())"
            );
        } else {
            $ins = $mysqli->prepare(
                "INSERT INTO insert_proforma
                 (id_agendamiento, codigo_pdv, usuario, evidencia,
                  caracteristica_visita, acompanamiento_tecnico,
                  estado_proforma, pendiente_insercion, fecha_registro)
                 VALUES (?, ?, ?, ?, ?, ?, 'en_proceso', 0, NOW())"
            );
        }
        if ($ins) {
            $ins->bind_param('ssssss',
                $orig['id_agendamiento'], $orig['codigo_pdv'], $orig['usuario'],
                $orig['evidencia'], $orig['caracteristica_visita'], $orig['acompanamiento_tecnico']
            );
            $ins->execute();
            $ins->close();
        }
    }
}

echo json_encode(['success' => true, 'message' => 'Guardado.']);
?>
