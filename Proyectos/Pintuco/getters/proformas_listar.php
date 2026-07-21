<?php
// proformas_listar.php
//
// La fuente principal es insert_proyectos_contacto (todos los agendamientos),
// con LEFT JOIN a insert_proforma para traer los datos de la proforma si ya
// existe. Así aparecen en el módulo de Proforma TODOS los agendamientos desde
// fase 1, y cuando el promotor suba la proforma desde el celular el registro
// pasa automáticamente a fase 3 sin que nadie tenga que hacer nada extra.
//
// Sin ?id_agendamiento → todos los registros (el JS desduplicará si hay
//   múltiples ciclos de negociación por agendamiento con ultimosCiclos()).
// Con ?id_agendamiento=X → todos los ciclos de proforma de ese agendamiento
//   ordenados del más antiguo al más reciente (para el historial gris).
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Content-Type: application/json');
// Sin esto, el navegador (o un proxy de IIS/Azure) puede servir una
// respuesta cacheada de esta misma URL tras una acción que acaba de
// cambiar estado_proforma, mostrando datos viejos aunque el UPDATE en BD
// ya se haya aplicado correctamente.
header('Cache-Control: no-store, no-cache, must-revalidate');

include_once '../db_connect.php';

$estado          = isset($_GET['estado_proforma'])  ? $_GET['estado_proforma']      : '';
$usuario         = isset($_GET['usuario'])           ? $_GET['usuario']              : '';
$id_agendamiento = isset($_GET['id_agendamiento'])  ? (int)$_GET['id_agendamiento'] : 0;

// c.id AS agendamiento_id: siempre presente aunque no haya proforma todavía.
// foto_factura y fase_actual agregadas en producción vía ALTER TABLE
// (confirmado 2026-07-03) — ya se seleccionan como columnas reales.
// monto_total_factura/plazo_meses/estado_pago: factura pagada a plazos
// (2026-07-07), los escribe la app — acá solo se leen. Único caso donde la
// web SÍ escribe estado_pago: la acción 'cerrar_plan_pago' de
// update_proforma.php, que lo pasa a 'cerrado' (contrato confirmado con
// Android 2026-07-16, mismo valor que usa el propio cierre de la app —
// ver NOTA 2 en ese archivo). Fuera de ese caso puntual, sigue siendo la
// app la que decide el resto de los valores (pendiente/en_proceso/
// completado).
//
// ══════════════════════════════════════════════════════════════════════
// CONTRATO DE "Monto Facturado" — CONFIRMADO con el agente Android y con el
// usuario (2026-07-14), corrige dos notas anteriores que estaban mal:
// ══════════════════════════════════════════════════════════════════════
// monto_total_factura es la META cotizada FIJA — se copia una sola vez de
// monto_validado (al facturar directo o al pasar a "a plazos") y no vuelve
// a cambiar. Confirmado con datos reales 2026-07-14: 3 filas del mismo
// agendamiento a plazos traían monto_total_factura=4000.00 las tres, el
// mismo total repetido — por eso NUNCA se suma esta columna entre filas
// (sumarlas daría 12000, triplicando el total real).
//
// "Monto Facturado" en la web (ver totalFacturadoDe en factura.js) es
// HÍBRIDO según plazo_meses de la fila "factura" vigente (la de mayor id
// que ya trae foto_factura):
//   - Pago Directo (plazo_meses vacío o <= 0, una sola factura): se lee
//     directo monto_total_factura de esa fila — coincide con la Cotización
//     Inicial, es la misma factura única (ej. cotizado $235 → facturado
//     $235, sin nada que acumular).
//   - A plazos (plazo_meses > 1): se ACUMULA cada factura parcial que el
//     promotor teclea a mano (primera factura al activar "a plazos" + cada
//     cuota siguiente desde el módulo Facturas de la app) — cada una es
//     una fila propia en insert_pago_factura, con su monto_pago y su
//     numero_cuota, todas con el mismo id_proforma (la fila "factura" de
//     arriba). Ej.: cotizado $5000, van llegando facturas de $1500+$500+
//     $100 → "Monto Facturado" muestra $2100 hasta que lleguen más.
//
// No hay ningún cambio pendiente del lado Android para esto — el modelo
// que ya implementa (cotización fija + facturas parciales tecleadas una
// por una en insert_pago_factura) era el correcto; el error estaba solo en
// esta consulta del lado web.
// motivo_cierre/motivo_cierre_pago (2026-07-14): al revés que lo de
// arriba — estas SÍ son de la web (ver update_proforma.php, acciones
// 'rechazar' y 'cerrar_plan_pago'). Son columnas propias, solo
// informativas para el detalle de la web; el móvil las tiene como campo
// LOCAL (no las sincroniza) y el motivo tampoco se pisa en
// observaciones_auditoria del lado web — eso quedó a cargo del móvil en
// su propio flujo de cierre. No hay columna de fecha aparte para el
// cierre del plan de pago: esa fila (la de foto_factura) nunca pasa por
// 'guardar'/'rechazar' desde la web, así que fecha_auditoria queda libre
// ahí y se reusa para "cuándo se cerró el plan de pago" sin riesgo de
// choque.
// fecha_factura TODAVÍA NO se selecciona: pendiente ALTER TABLE
// (ver memoria del proyecto) — agregarla acá en cuanto exista en BD.
$selectBase = "SELECT
        c.id           AS agendamiento_id,
        c.codigo_pdv,
        c.pdv,
        c.contacto,
        c.empresa,
        c.direccion,
        c.latitud,
        c.longitud,
        c.telefono,
        c.usuario,
        c.estado_agenda,
        c.fecha_registro   AS contacto_fecha_registro,
        c.fecha_agendamiento,
        c.hora,
        c.tecnico,
        c.no_requiere_visita,
        p.id,
        p.id_agendamiento,
        p.fecha_proforma,
        p.estado_proforma,
        p.evidencia,
        p.foto_factura,
        p.monto_validado,
        p.observaciones_auditoria,
        p.motivo_cierre,
        p.fecha_auditoria,
        p.fase_actual,
        p.monto_total_factura,
        p.plazo_meses,
        p.estado_pago,
        p.motivo_cierre_pago,
        p.caracteristica_visita,
        p.acompanamiento_tecnico,
        p.fecha_registro   AS proforma_fecha_registro
    FROM insert_proyectos_contacto c
    LEFT JOIN insert_proforma p ON p.id_agendamiento = c.id
    WHERE c.activar = 'SI'";

$registros = [];

if ($id_agendamiento > 0) {
    // Historial completo: todos los ciclos de proforma de un agendamiento,
    // ordenados del más antiguo al más reciente para el panel de historial.
    $sql = $mysqli->prepare($selectBase . " AND c.id = ? ORDER BY p.id ASC");
    if ($sql) {
        $sql->bind_param('i', $id_agendamiento);
        if ($sql->execute()) {
            $res = $sql->get_result();
            if ($res) {
                while ($fila = $res->fetch_assoc()) { $registros[] = $fila; }
            }
        }
        $sql->close();
    }
} elseif ($estado === '' && $usuario === '') {
    // Sin filtros: $mysqli->query() directo — patrón probado de get_pdvs.php.
    $res = $mysqli->query($selectBase . " ORDER BY c.fecha_registro DESC");
    if ($res) {
        while ($fila = $res->fetch_assoc()) { $registros[] = $fila; }
    }
} else {
    // Con filtros de estado_proforma y/o usuario.
    $where  = "";
    $params = [];
    $types  = "";
    if ($estado !== '') {
        $where   .= " AND p.estado_proforma = ?";
        $params[] = $estado;
        $types   .= "s";
    }
    if ($usuario !== '') {
        $where   .= " AND c.usuario = ?";
        $params[] = $usuario;
        $types   .= "s";
    }
    $sql = $mysqli->prepare($selectBase . $where . " ORDER BY c.fecha_registro DESC");
    if ($sql) {
        $bindArgs = [$types];
        foreach ($params as $k => $v) { $bindArgs[] = &$params[$k]; }
        call_user_func_array([$sql, 'bind_param'], $bindArgs);
        if ($sql->execute()) {
            $res = $sql->get_result();
            if ($res) {
                while ($fila = $res->fetch_assoc()) { $registros[] = $fila; }
            }
        }
        $sql->close();
    }
}

echo json_encode(["data" => $registros], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
?>
