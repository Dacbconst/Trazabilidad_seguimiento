<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

include_once '../db_connect.php';

// Fuente cruda (un registro por agendamiento×ciclo de proforma, igual patrón
// que proformas_listar.php): el agregado (KPIs, embudo, top promotores) se
// calcula en el cliente para que los filtros de Promotor y Período (mismos
// que Proforma) recalculen todo sin ida y vuelta al servidor — mismo
// criterio que proforma.js / contactados.js, que ya filtran así.
// foto_factura confirmada en producción vía ALTER TABLE (2026-07-03).
$q = $mysqli->query("
    SELECT
        c.id               AS agendamiento_id,
        c.usuario,
        c.hora, c.tecnico,
        c.fecha_registro   AS contacto_fecha_registro,
        p.id               AS proforma_id,
        p.estado_proforma,
        p.foto_factura,
        p.monto_validado,
        p.fecha_registro   AS proforma_fecha_registro
    FROM insert_proyectos_contacto c
    LEFT JOIN insert_proforma p ON p.id_agendamiento = c.id
    WHERE c.activar = 'SI'
    ORDER BY p.id ASC
");
$registros = [];
if ($q) {
    while ($r = $q->fetch_assoc()) { $registros[] = $r; }
}

// Pagos/cuotas reales — mecánica de facturación a plazos confirmada con la
// app móvil (2026-07-03). El monto negociado (monto_validado) NO equivale
// al monto facturado: ver comentario de ultimaProformaDe() en
// estado-flujo.js — cada "Guardar" cierra la ronda vigente con su monto y
// abre una ronda nueva vacía esperando la próxima foto, así que ambos casi
// nunca coinciden en la misma fila de insert_proforma.
$q = $mysqli->query("SELECT id_agendamiento, usuario, monto_pago FROM insert_pago_factura");
$pagos = [];
if ($q) {
    while ($r = $q->fetch_assoc()) { $pagos[] = $r; }
}

echo json_encode([
    'registros' => $registros,
    'pagos'     => $pagos,
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
?>
