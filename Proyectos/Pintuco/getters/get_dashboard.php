<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

include_once '../db_connect.php';

function colExisteDash($mysqli, $tabla, $col) {
    $r = $mysqli->query("SHOW COLUMNS FROM `$tabla` LIKE '$col'");
    return $r && $r->num_rows > 0;
}

$tieneFoto = colExisteDash($mysqli, 'insert_proforma', 'foto_factura');

// 1. Total PDVs activos
$q = $mysqli->query("SELECT COUNT(*) AS n FROM insert_proyectos_contacto WHERE activar = 'SI'");
$totalPdvs = $q ? (int)($q->fetch_assoc()['n'] ?? 0) : 0;

// 2. PDVs con visita confirmada (hora + tecnico asignados) → fase 2+
$q = $mysqli->query("SELECT COUNT(*) AS n FROM insert_proyectos_contacto WHERE activar = 'SI' AND hora IS NOT NULL AND hora != '' AND tecnico IS NOT NULL AND tecnico != ''");
$conAgenda = $q ? (int)($q->fetch_assoc()['n'] ?? 0) : 0;

// 3. PDVs con al menos una proforma → fase 3+
$q = $mysqli->query("SELECT COUNT(DISTINCT id_agendamiento) AS n FROM insert_proforma");
$conProforma = $q ? (int)($q->fetch_assoc()['n'] ?? 0) : 0;

// 4. Fase 3 actual: fotos recién llegadas, sin monto todavía (pendiente de revisión)
$q = $mysqli->query("SELECT COUNT(DISTINCT id_agendamiento) AS n FROM insert_proforma WHERE estado_proforma = 'en_proceso' AND (monto_validado IS NULL OR monto_validado = '')");
$fase3act = $q ? (int)($q->fetch_assoc()['n'] ?? 0) : 0;

// 5. Fase 5: facturados
if ($tieneFoto) {
    $q = $mysqli->query("SELECT COUNT(DISTINCT id_agendamiento) AS n FROM insert_proforma WHERE foto_factura IS NOT NULL AND foto_factura != ''");
} else {
    $q = $mysqli->query("SELECT COUNT(DISTINCT id_agendamiento) AS n FROM insert_proforma WHERE estado_proforma = 'aprobado'");
}
$fase5 = $q ? (int)($q->fetch_assoc()['n'] ?? 0) : 0;

// Distribución de fases por diferencia
$fase1 = max(0, $totalPdvs - $conAgenda);
$fase2 = max(0, $conAgenda - $conProforma);
$fase4 = max(0, $conProforma - $fase3act - $fase5);

// 6. Montos totales
$q = $mysqli->query("SELECT COALESCE(SUM(monto_validado+0), 0) AS tot FROM insert_proforma WHERE monto_validado IS NOT NULL AND monto_validado != ''");
$montoNeg = $q ? (float)($q->fetch_assoc()['tot'] ?? 0) : 0;

if ($tieneFoto) {
    $q = $mysqli->query("SELECT COALESCE(SUM(CASE WHEN foto_factura IS NOT NULL AND foto_factura != '' THEN monto_validado+0 ELSE 0 END), 0) AS tot FROM insert_proforma WHERE monto_validado IS NOT NULL AND monto_validado != ''");
} else {
    $q = $mysqli->query("SELECT COALESCE(SUM(CASE WHEN estado_proforma = 'aprobado' THEN monto_validado+0 ELSE 0 END), 0) AS tot FROM insert_proforma WHERE monto_validado IS NOT NULL AND monto_validado != ''");
}
$montoFact = $q ? (float)($q->fetch_assoc()['tot'] ?? 0) : 0;

$convPct = $totalPdvs > 0 ? round($fase5 / $totalPdvs * 100, 1) : 0;

// 7. Top promotores — PDVs por promotor
$q = $mysqli->query("SELECT usuario, COUNT(*) AS total FROM insert_proyectos_contacto WHERE activar = 'SI' AND usuario IS NOT NULL AND usuario != '' GROUP BY usuario ORDER BY total DESC LIMIT 10");
$promMap = [];
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $promMap[$r['usuario']] = ['usuario' => $r['usuario'], 'total' => (int)$r['total'], 'monto_facturado' => 0.0];
    }
}

// Monto facturado por promotor
if ($tieneFoto) {
    $q = $mysqli->query("SELECT usuario, COALESCE(SUM(CASE WHEN foto_factura IS NOT NULL AND foto_factura != '' THEN monto_validado+0 ELSE 0 END), 0) AS mf FROM insert_proforma WHERE monto_validado IS NOT NULL GROUP BY usuario");
} else {
    $q = $mysqli->query("SELECT usuario, COALESCE(SUM(CASE WHEN estado_proforma = 'aprobado' THEN monto_validado+0 ELSE 0 END), 0) AS mf FROM insert_proforma WHERE monto_validado IS NOT NULL GROUP BY usuario");
}
if ($q) {
    while ($r = $q->fetch_assoc()) {
        if (isset($promMap[$r['usuario']])) {
            $promMap[$r['usuario']]['monto_facturado'] = (float)$r['mf'];
        }
    }
}

$promotores = array_values($promMap);
usort($promotores, fn($a, $b) => $b['monto_facturado'] <=> $a['monto_facturado']);
$promotores = array_slice($promotores, 0, 8);

echo json_encode([
    'kpis' => [
        'total_pdvs'      => $totalPdvs,
        'monto_negociado' => $montoNeg,
        'monto_facturado' => $montoFact,
        'conversion_pct'  => $convPct,
        'fase5_count'     => $fase5,
    ],
    'fases' => [
        ['fase' => 1, 'label' => 'Contacto inicial',  'count' => $fase1],
        ['fase' => 2, 'label' => 'Agendamiento',      'count' => $fase2],
        ['fase' => 3, 'label' => 'Proforma recibida', 'count' => $fase3act],
        ['fase' => 4, 'label' => 'Negociación',       'count' => $fase4],
        ['fase' => 5, 'label' => 'Facturado',         'count' => $fase5],
    ],
    'promotores' => $promotores,
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
?>
