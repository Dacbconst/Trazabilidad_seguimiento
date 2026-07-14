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
 *                         Botón "Cerrar proceso" en la UI (2026-07-14): para
 *                         negociaciones que se quedan en pura cotización y
 *                         nunca llegan a factura. Motivo obligatorio, en su
 *                         propia columna motivo_cierre — separada a propósito
 *                         de observaciones_auditoria (esa es de 'guardar',
 *                         notas de validación; son conceptos distintos).
 *
 * La decisión final (fase 5) la toma el promotor al subir foto_factura desde
 * el celular. La web NO tiene botón de "aprobar"/"finalizar".
 *
 * ══════════════════════════════════════════════════════════════════════
 * NOTA PARA EL EQUIPO MÓVIL/ANDROID (2026-07-14) — contrato compartido:
 * ══════════════════════════════════════════════════════════════════════
 * Se agregó la posibilidad de cerrar definitivamente una ronda de
 * negociación que se queda en pura cotización y nunca llega a factura
 * (cliente no continuó, se fue con la competencia, etc.).
 *
 * PENDIENTE DE SCHEMA: falta agregar en insert_proforma la columna
 *   motivo_cierre VARCHAR(500) NULL DEFAULT NULL
 * (el usuario la agrega manualmente — mediador de cambios de esquema
 * compartidos; no correr ALTER TABLE sin coordinar con él).
 *
 * Lo que escribe la web al cerrar (acción 'rechazar' de este archivo):
 *   UPDATE insert_proforma
 *   SET estado_proforma = 'rechazado', motivo_cierre = '<motivo>',
 *       fecha_auditoria = NOW()
 *   WHERE id = <id del ciclo activo>
 *
 * IMPORTANTE — motivo_cierre es una columna PROPIA, distinta de
 * observaciones_auditoria (esa es de la acción 'guardar': notas internas
 * de validación del analista). Son dos conceptos separados a propósito —
 * no reusar una para la otra.
 *
 * Lado móvil:
 *  - LECTURA: si estado_proforma = 'rechazado', tratar esa ronda como
 *    TERMINAL — dejar de esperar más fotos/montos para esa fila, y
 *    mostrarla como cerrada (motivo_cierre trae la razón, si se muestra).
 *    No confundir con 'correccion_solicitada' (esa SÍ es reversible, solo
 *    pide reenviar la foto).
 *  - ESCRITURA (si el promotor también puede cerrar desde la app): mismos
 *    3 campos de arriba, motivo_cierre obligatorio en su formulario.
 *  - Ambos lados leen/escriben la misma tabla en tiempo real — no hace
 *    falta ningún mecanismo de sync/notificación aparte.
 *
 * NOTA 2 (2026-07-14) — cierre de plan de pago a plazos:
 * Acción 'cerrar_plan_pago': para cuando el cliente deja de pagar cuotas y
 * el plan nunca se va a completar. Columna nueva, SOLO de la web:
 *   motivo_cierre_pago VARCHAR(500) NULL
 * (la fecha de cierre reusa fecha_auditoria — esa fila, la que trae
 * foto_factura, nunca pasa por 'guardar'/'rechazar', así que ese campo
 * queda libre ahí; no hace falta una columna de fecha aparte).
 * IMPORTANTE: esta acción NUNCA toca estado_pago/monto_total_factura/
 * plazo_meses — esos siguen siendo de UN SOLO DUEÑO (la app). Si el móvil
 * lee motivo_cierre_pago con valor, es solo informativo (la web decidió
 * cerrar el caso); no implica que deban cambiar su propio estado_pago.
 * ══════════════════════════════════════════════════════════════════════
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
// Columna propia (no reusa observaciones_auditoria, que ya es de "guardar")
// — evita mezclar dos conceptos distintos en un solo campo de texto libre.
$motivo_cierre = (isset($_POST['motivo_cierre']) && $_POST['motivo_cierre'] !== '') ? trim($_POST['motivo_cierre']) : null;
// Mismo criterio, para el cierre de un plan de pago a plazos que se quedó
// a medias (ver acción 'cerrar_plan_pago' más abajo).
$motivo_cierre_pago = (isset($_POST['motivo_cierre_pago']) && $_POST['motivo_cierre_pago'] !== '') ? trim($_POST['motivo_cierre_pago']) : null;

if ($id <= 0 || !in_array($accion, ['rechazar_calidad', 'cancelar_correccion', 'guardar', 'rechazar', 'cerrar_plan_pago'], true)) {
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
// 2026-07-14 — pedido explícito del usuario: ahora también se usa como
// "Cerrar proceso" desde la UI (proceso que se quedó en pura cotización y
// nunca llegó a factura). El motivo es obligatorio en ese flujo.
if ($accion === 'rechazar') {
    if ($motivo_cierre === null || $motivo_cierre === '') {
        echo json_encode(['success' => false, 'message' => 'El motivo del cierre es obligatorio.']);
        exit;
    }
    // motivo_cierre es su propia columna (no observaciones_auditoria, que
    // ya es de 'guardar' — notas de validación, concepto distinto). Así
    // ambos quedan intactos y consultables por separado.
    $sql = $mysqli->prepare(
        "UPDATE insert_proforma
         SET estado_proforma = 'rechazado', monto_validado = ?, motivo_cierre = ?,
             fecha_auditoria = NOW(), fase_actual = 4
         WHERE id = ?"
    );
    if (!$sql) { echo json_encode(['success' => false, 'message' => $mysqli->error]); exit; }
    $sql->bind_param('ssi', $monto, $motivo_cierre, $id);
    $ok = $sql->execute();
    $sql->close();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Rechazada.' : $mysqli->error]);
    exit;
}

// ── 2b. 'cerrar_plan_pago': plan de pago a plazos que se quedó a medias
//        (dejó de pagar cuotas y nunca va a terminar) — 2026-07-14, pedido
//        explícito del usuario. NO toca estado_pago/monto_total_factura/
//        plazo_meses: esos son de UN SOLO DUEÑO (la app), ver comentario en
//        proformas_listar.php. Esto es solo una anotación propia de la web
//        en una columna separada — el móvil sigue viendo su estado_pago tal
//        cual lo dejó, esto no lo pisa ni lo reemplaza.
//        fecha_auditoria: se reusa (no hay columna de fecha aparte) — la
//        fila que trae foto_factura nunca pasa por 'guardar'/'rechazar'
//        desde la web (esas acciones solo llegan hasta Fase 4), así que
//        acá siempre está libre y sirve como "fecha de cierre del plan".
if ($accion === 'cerrar_plan_pago') {
    if ($motivo_cierre_pago === null || $motivo_cierre_pago === '') {
        echo json_encode(['success' => false, 'message' => 'El motivo del cierre es obligatorio.']);
        exit;
    }
    // Las mismas reglas que ya filtran el botón en factura.js (puedeCerrarPlan)
    // se repiten acá del lado servidor — ese endpoint es alcanzable directo,
    // sin pasar por esa UI, así que las reglas de negocio no pueden vivir
    // solo en el frontend.
    $chk = $mysqli->prepare(
        "SELECT id_agendamiento, plazo_meses, estado_pago, motivo_cierre_pago FROM insert_proforma WHERE id = ?"
    );
    if (!$chk) { echo json_encode(['success' => false, 'message' => $mysqli->error]); exit; }
    $chk->bind_param('i', $id);
    $chk->execute();
    $fila = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$fila) {
        echo json_encode(['success' => false, 'message' => 'Registro no encontrado.']);
        exit;
    }
    // plazo_meses no siempre vive en esta fila puntual (misma inconsistencia
    // que corrige plazoMesesDe en factura.js: el celular a veces la deja
    // NULL en la fila que trae foto_factura) — si viene vacía, se usa el
    // mayor plazo_meses entre los demás ciclos del mismo agendamiento antes
    // de concluir que es Pago Directo.
    $plazoMeses = (int)$fila['plazo_meses'];
    if ($plazoMeses <= 0) {
        $maxPlazo = $mysqli->prepare(
            "SELECT MAX(plazo_meses) AS max_plazo FROM insert_proforma WHERE id_agendamiento = ?"
        );
        if ($maxPlazo) {
            $maxPlazo->bind_param('i', $fila['id_agendamiento']);
            $maxPlazo->execute();
            $filaMax = $maxPlazo->get_result()->fetch_assoc();
            $maxPlazo->close();
            $plazoMeses = (int)($filaMax['max_plazo'] ?? 0);
        }
    }
    if ($plazoMeses <= 0) {
        echo json_encode(['success' => false, 'message' => 'No aplica: es un Pago Directo.']);
        exit;
    }
    if ($fila['estado_pago'] === 'completado') {
        echo json_encode(['success' => false, 'message' => 'El plan ya está completado.']);
        exit;
    }
    if ($fila['motivo_cierre_pago'] !== null && $fila['motivo_cierre_pago'] !== '') {
        echo json_encode(['success' => false, 'message' => 'El plan ya fue cerrado.']);
        exit;
    }
    $sql = $mysqli->prepare(
        "UPDATE insert_proforma
         SET motivo_cierre_pago = ?, fecha_auditoria = NOW()
         WHERE id = ? AND (motivo_cierre_pago IS NULL OR motivo_cierre_pago = '')"
    );
    if (!$sql) { echo json_encode(['success' => false, 'message' => $mysqli->error]); exit; }
    $sql->bind_param('si', $motivo_cierre_pago, $id);
    $ok = $sql->execute();
    // execute() devuelve true aunque el WHERE no matchee ninguna fila (ej.
    // otra petición casi simultánea ya cerró este mismo plan entre el SELECT
    // de arriba y este UPDATE) — sin este chequeo, esa segunda petición
    // reportaría éxito falso y el frontend pisaría su estado local con datos
    // que nunca quedaron en BD.
    $afectadas = $ok ? $sql->affected_rows : 0;
    $sql->close();
    if ($ok && $afectadas === 0) {
        echo json_encode(['success' => false, 'message' => 'El plan ya fue cerrado en otra sesión.']);
        exit;
    }
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Plan de pago cerrado.' : $mysqli->error]);
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
