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
 * Guardas de carrera (2026-07-16, hallazgo del consejo de revisión): 'rechazar',
 * 'guardar' y 'cerrar_plan_pago' condicionan su UPDATE al estado esperado y
 * revisan affected_rows — si otra sesión (otro analista, u otro flujo de la
 * app móvil) ya cambió el registro entre que se abrió el panel y que se
 * confirmó la acción, el UPDATE no aplica y se devuelve
 * {success:false, stale:true, message:'...'} en vez de pisar en silencio un
 * cierre/avance ajeno. El frontend usa 'stale' para recargar en vez de solo
 * reintentar contra un estado que ya sabe que es viejo.
 *
 * ══════════════════════════════════════════════════════════════════════
 * NOTA PARA EL EQUIPO MÓVIL/ANDROID (2026-07-16) — contrato compartido,
 * confirmado con lo que YA hace la app (Cierre Proforma / Cierre Factura):
 * ══════════════════════════════════════════════════════════════════════
 * El móvil tiene sus propios flujos de cierre. Del lado de la web, el
 * único campo que de verdad sincroniza en cada uno es el de estado —
 * motivo_cierre/motivo_cierre_pago son columnas propias de la web,
 * informativas solamente (el móvil las tiene como campo LOCAL, no las
 * sincroniza, y del lado web tampoco se escribe el motivo en
 * observaciones_auditoria — eso quedó a cargo del móvil, corregido en su
 * propio flujo).
 *
 * Cierre Proforma (acción 'rechazar' de este archivo) — ronda de
 * negociación que se queda en pura cotización y nunca llega a factura:
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
 *  - Ambos lados leen/escriben la misma tabla en tiempo real — no hace
 *    falta ningún mecanismo de sync/notificación aparte.
 *
 * NOTA 2 (2026-07-16) — cierre de plan de pago a plazos (Cierre Factura):
 * Acción 'cerrar_plan_pago': para cuando el cliente deja de pagar cuotas y
 * el plan nunca se va a completar.
 *   UPDATE insert_proforma
 *   SET estado_pago = 'cerrado', motivo_cierre_pago = '<motivo>',
 *       fecha_auditoria = NOW()
 *   WHERE id = <id de la fila "factura">
 * - motivo_cierre_pago: columna propia de la web (el móvil la tiene local,
 *   no sincroniza — mismo caso que motivo_cierre arriba).
 * - estado_pago: CAMBIO DE CRITERIO respecto a la nota anterior — hasta
 *   ahora esta acción nunca la tocaba porque se consideraba de un solo
 *   dueño (la app). Confirmado 2026-07-16 con el equipo Android: la app
 *   YA usa 'cerrado' como su propio valor para este mismo caso (plan
 *   truncado), así que la web escribe el mismo valor en vez de inventar
 *   un canal paralelo — evita que un lado muestre "cerrado" y el otro
 *   siga mostrando 'pendiente'/'en_proceso' para la misma fila.
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
    // Guarda de carrera (2026-07-16, mismo motivo que 'rechazar'/'guardar'
    // más abajo): el botón "Rechazar / Pedir nueva foto" del frontend ya
    // se oculta si el proceso está cerrado, pero ese es solo control de UI
    // — sin este WHERE, una pantalla desactualizada podía reabrir a
    // 'correccion_solicitada' un ciclo que ya estaba 'rechazado' (cerrado).
    $sql = $mysqli->prepare(
        "UPDATE insert_proforma SET estado_proforma = 'correccion_solicitada' WHERE id = ? AND estado_proforma <> 'rechazado'"
    );
    if (!$sql) { echo json_encode(['success' => false, 'message' => $mysqli->error]); exit; }
    $sql->bind_param('i', $id);
    $ok = $sql->execute();
    $afectadas = $ok ? $sql->affected_rows : 0;
    $sql->close();
    if ($ok && $afectadas === 0) {
        echo json_encode(['success' => false, 'stale' => true, 'message' => 'El proceso ya está cerrado — recarga la página.']);
        exit;
    }
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
    // monto es opcional acá (se puede cerrar sin haber llegado nunca a
    // cotizar), pero si viene tiene que ser numérico — sin esto, un valor
    // no numérico se trunca silenciosamente al guardar en la columna
    // DECIMAL (error_reporting está apagado arriba, no se vería el warning).
    if ($monto !== null && !is_numeric($monto)) {
        echo json_encode(['success' => false, 'message' => 'El monto no es válido.']);
        exit;
    }
    // motivo_cierre es su propia columna (no observaciones_auditoria, que
    // ya es de 'guardar' — notas de validación, concepto distinto). Así
    // ambos quedan intactos y consultables por separado.
    // Guarda de carrera (hallazgo del consejo 2026-07-16): antes este UPDATE
    // no tenía condición de estado ni chequeo de affected_rows, a diferencia
    // de 'cerrar_plan_pago' que sí lo tenía. Sin esto, un "Cerrar proceso"
    // con la pantalla desactualizada podía revertir un registro que el
    // móvil ya había avanzado a fase 5 (foto_factura subida) mientras el
    // panel seguía abierto — acá no hay polling, el panel no se refresca
    // solo. El WHERE bloquea esa reversión en vez de solo confiar en la UI.
    $sql = $mysqli->prepare(
        "UPDATE insert_proforma
         SET estado_proforma = 'rechazado', monto_validado = ?, motivo_cierre = ?,
             fecha_auditoria = NOW(), fase_actual = 4
         WHERE id = ?
           AND estado_proforma NOT IN ('rechazado', 'aprobado')
           AND (foto_factura IS NULL OR foto_factura = '')"
    );
    if (!$sql) { echo json_encode(['success' => false, 'message' => $mysqli->error]); exit; }
    $sql->bind_param('ssi', $monto, $motivo_cierre, $id);
    $ok = $sql->execute();
    $afectadas = $ok ? $sql->affected_rows : 0;
    $sql->close();
    if ($ok && $afectadas === 0) {
        echo json_encode(['success' => false, 'stale' => true, 'message' => 'El registro ya cambió de estado (cerrado o facturado) en otra sesión — recarga la página.']);
        exit;
    }
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Proceso cerrado.' : $mysqli->error]);
    exit;
}

// ── 2b. 'cerrar_plan_pago': plan de pago a plazos que se quedó a medias
//        (dejó de pagar cuotas y nunca va a terminar) — 2026-07-14, pedido
//        explícito del usuario. Desde 2026-07-16 SÍ toca estado_pago
//        (pasa a 'cerrado') — confirmado con Android: la app usa el mismo
//        valor en su propio flujo "Cierre Factura", así que la web se
//        alinea en vez de mantener un estado paralelo. monto_total_factura/
//        plazo_meses siguen siendo de UN SOLO DUEÑO (la app) — esos no se
//        tocan acá. motivo_cierre_pago es columna propia de la web,
//        informativa (el móvil la tiene local, no sincroniza); el motivo
//        NO se escribe en observaciones_auditoria — eso quedó a cargo del
//        móvil en su propio flujo de cierre.
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
    // 'cerrado' puede llegar por acá (web) o por el propio flujo del móvil
    // (Cierre Factura) — cualquiera de los dos que haya cerrado primero
    // cuenta, no solo el que tenga motivo_cierre_pago propio de la web.
    if ($fila['estado_pago'] === 'cerrado' || ($fila['motivo_cierre_pago'] !== null && $fila['motivo_cierre_pago'] !== '')) {
        echo json_encode(['success' => false, 'stale' => true, 'message' => 'El plan ya fue cerrado.']);
        exit;
    }
    $sql = $mysqli->prepare(
        "UPDATE insert_proforma
         SET estado_pago = 'cerrado', motivo_cierre_pago = ?, fecha_auditoria = NOW()
         WHERE id = ? AND estado_pago NOT IN ('cerrado', 'completado')"
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
        echo json_encode(['success' => false, 'stale' => true, 'message' => 'El plan ya fue cerrado en otra sesión.']);
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
if (!is_numeric($monto)) {
    echo json_encode(['success' => false, 'message' => 'El monto no es válido.']);
    exit;
}

// Guarda de carrera (hallazgo del consejo 2026-07-16), mismo motivo que en
// 'rechazar' arriba: sin el WHERE de estado ni el chequeo de affected_rows,
// un "Guardar cambios" con la pantalla desactualizada podía reabrir a
// 'en_negociacion' un ciclo que ya se había cerrado (rechazado) o que el
// móvil ya había avanzado a fase 5 mientras el panel seguía abierto sin
// refrescarse solo.
$sql = $mysqli->prepare(
    "UPDATE insert_proforma
     SET estado_proforma = 'en_negociacion', monto_validado = ?, observaciones_auditoria = ?,
         fecha_auditoria = NOW(), fase_actual = 4
     WHERE id = ?
       AND estado_proforma <> 'rechazado'
       AND (foto_factura IS NULL OR foto_factura = '')"
);
if (!$sql) { echo json_encode(['success' => false, 'message' => $mysqli->error]); exit; }
$sql->bind_param('ssi', $monto, $observaciones, $id);
$ok = $sql->execute();
$afectadas = $ok ? $sql->affected_rows : 0;
$sql->close();
if ($ok && $afectadas === 0) {
    echo json_encode(['success' => false, 'stale' => true, 'message' => 'El registro ya cambió de estado (cerrado o facturado) en otra sesión — recarga la página.']);
    exit;
}

// Ya NO se inserta un ciclo nuevo vacío acá (confirmado con la app móvil,
// 2026-07-03): esa fila vacía era el origen de las "proformas fantasma" que
// aparecían solas. Ahora el promotor registra la siguiente proforma desde
// la propia app en cuanto ve el monto puesto — el backend solo actualiza la
// fila existente por su id, nunca inserta como parte de esta acción.
echo json_encode(['success' => $ok, 'message' => $ok ? 'Guardado.' : $mysqli->error]);
?>
