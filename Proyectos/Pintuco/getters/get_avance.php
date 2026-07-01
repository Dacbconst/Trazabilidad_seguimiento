<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include_once '../db_connect.php';

// Resumen global
$resGlobal = $mysqli->query("
    SELECT
        COUNT(DISTINCT c.usuario)                                                        AS total_mercaderistas,
        COUNT(c.id)                                                                      AS total_pdvs,
        SUM(CASE WHEN c.estado_agenda = 'completada' THEN 1 ELSE 0 END)                 AS completados,
        SUM(CASE WHEN c.estado_agenda IN ('pendiente','confirmado','reagendada') THEN 1 ELSE 0 END) AS en_curso,
        SUM(CASE WHEN c.estado_agenda = 'vencida' THEN 1 ELSE 0 END)                    AS vencidos,
        COUNT(DISTINCT p.id)                                                             AS con_proforma
    FROM insert_proyectos_contacto c
    LEFT JOIN insert_proforma p ON p.id_agendamiento = c.id
    WHERE c.activar = 'SI'
      AND c.fecha_agendamiento IS NOT NULL
      AND c.fecha_agendamiento != '0000-00-00'
");

$global = ['total_mercaderistas'=>0,'total_pdvs'=>0,'completados'=>0,'en_curso'=>0,'vencidos'=>0,'con_proforma'=>0];
if ($resGlobal) {
    $row = $resGlobal->fetch_assoc();
    if ($row) $global = $row;
}

// Rendimiento por mercaderista
$resMerc = $mysqli->query("
    SELECT
        c.usuario                                                                         AS mercaderista,
        COUNT(c.id)                                                                       AS total_pdvs,
        SUM(CASE WHEN c.estado_agenda = 'completada' THEN 1 ELSE 0 END)                  AS completados,
        SUM(CASE WHEN c.estado_agenda IN ('pendiente','confirmado','reagendada') THEN 1 ELSE 0 END) AS en_curso,
        SUM(CASE WHEN c.estado_agenda = 'vencida' THEN 1 ELSE 0 END)                     AS vencidos,
        SUM(CASE WHEN c.estado_agenda = 'cancelada' THEN 1 ELSE 0 END)                   AS cancelados,
        COUNT(DISTINCT p.id)                                                              AS con_proforma,
        MAX(c.fecha_agendamiento)                                                         AS ultima_visita
    FROM insert_proyectos_contacto c
    LEFT JOIN insert_proforma p ON p.id_agendamiento = c.id
    WHERE c.activar = 'SI'
      AND c.fecha_agendamiento IS NOT NULL
      AND c.fecha_agendamiento != '0000-00-00'
    GROUP BY c.usuario
    ORDER BY (SUM(CASE WHEN c.estado_agenda = 'completada' THEN 1 ELSE 0 END) / COUNT(c.id)) DESC,
             COUNT(c.id) DESC
");

$mercaderistas = [];
if ($resMerc) {
    while ($f = $resMerc->fetch_assoc()) {
        $total = (int)$f['total_pdvs'];
        $comp  = (int)$f['completados'];
        $f['pct_avance'] = $total > 0 ? round($comp / $total * 100) : 0;
        $mercaderistas[] = $f;
    }
}

echo json_encode([
    'global'        => $global,
    'mercaderistas' => $mercaderistas,
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
?>
