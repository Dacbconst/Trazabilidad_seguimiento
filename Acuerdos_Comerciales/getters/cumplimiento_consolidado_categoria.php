<?php
// JSON crudo, mismos filtros que cumplimiento_listar.php — agrupado por Sector en vez de Asesor -> Cliente.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

ob_start();
set_exception_handler(function ($e) {
	while (ob_get_level() > 0) { ob_end_clean(); }
	echo json_encode(['ok' => false, 'message' => 'No se pudo cargar: '.$e->getMessage()]);
	exit;
});

$trimestre = (int) ($_GET['trimestre'] ?? 0);
$anio      = (int) ($_GET['anio'] ?? 0);
$canal     = in_array($_GET['canal'] ?? '', ['directo', 'distribuidor'], true) ? $_GET['canal'] : 'total';

$categorias = resumen_consolidado_categoria($mysqli, $trimestre, $anio, $canal);

$totalCuota = 0.0;
$totalVenta = 0.0;
$ganan = 0;
foreach ($categorias as $c) {
	$totalCuota += $c['cuota_total'];
	$totalVenta += $c['venta_total'];
	if ($c['gana'] === 'gana') $ganan++;
}
$stats = [
	'categorias'  => count($categorias),
	'ganan'       => $ganan,
	'no_ganan'    => count($categorias) - $ganan,
	'cumplimiento_consolidado' => $totalCuota > 0 ? round(($totalVenta / $totalCuota) * 100, 2) : 0.0,
];

while (ob_get_level() > 0) { ob_end_clean(); }
echo json_encode(['ok' => true, 'categorias' => $categorias, 'stats' => $stats]);
?>
