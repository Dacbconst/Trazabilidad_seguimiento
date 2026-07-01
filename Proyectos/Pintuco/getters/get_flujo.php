<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Content-Type: application/json');

include_once '../db_connect.php';

// Si hay múltiples proformas por agendamiento, toma la más reciente.
$res = $mysqli->query("
    SELECT
        c.id, c.codigo_pdv, c.pdv, c.usuario, c.empresa, c.contacto,
        c.fecha_registro, c.fecha_agendamiento, c.estado_agenda, c.tecnico,
        p.id             AS id_proforma,
        p.estado_proforma,
        p.fecha_proforma,
        p.foto_factura
    FROM insert_proyectos_contacto c
    LEFT JOIN (
        SELECT ip.id_agendamiento, ip.id, ip.estado_proforma, ip.fecha_proforma
        FROM insert_proforma ip
        INNER JOIN (
            SELECT id_agendamiento, MAX(id) AS max_id
            FROM insert_proforma
            GROUP BY id_agendamiento
        ) sub ON sub.id_agendamiento = ip.id_agendamiento AND sub.max_id = ip.id
    ) p ON p.id_agendamiento = c.id
    WHERE c.activar = 'SI'
    ORDER BY c.fecha_agendamiento DESC, c.fecha_registro DESC
");

function etapa($ea, $ep) {
    if ($ep === 'venta_finalizada') return 'venta_finalizada';
    if ($ep === 'aprobado')         return 'aprobado';
    if ($ep === 'rechazado')        return 'rechazado';
    if ($ep === 'en_negociacion')   return 'en_negociacion';
    if (!empty($ep))                return 'proforma';
    if ($ea === 'completada')     return 'visita_ok';
    if ($ea === 'cancelada')      return 'cancelada';
    if ($ea === 'vencida')        return 'vencida';
    if (in_array($ea, ['pendiente','confirmado','reagendada'])) return 'agendado';
    return 'contactado';
}

$registros = [];
$hoy = new DateTime();

if ($res) {
    while ($f = $res->fetch_assoc()) {
        $e = etapa($f['estado_agenda'], $f['estado_proforma']);
        $f['etapa'] = $e;

        $ref = $f['fecha_proforma'] ?: ($f['fecha_agendamiento'] ?: $f['fecha_registro']);
        $f['dias_flujo'] = null;
        if ($ref) {
            try {
                $f['dias_flujo'] = (int)(new DateTime($ref))->diff($hoy)->days;
            } catch (Exception $ex) {}
        }
        $registros[] = $f;
    }
}

echo json_encode(['data' => $registros], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
?>
