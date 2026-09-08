<?php
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$busqueda   = trim($_GET['q'] ?? '');
$trimestre  = (int) ($_GET['trimestre'] ?? 0);
$anio       = (int) ($_GET['anio'] ?? 0);
$filtroFirma = in_array($_GET['firma'] ?? '', ['firmadas', 'pendientes'], true) ? $_GET['firma'] : 'todos';
$pagina     = (int) ($_GET['pg'] ?? 1);
$usuarioId  = $_SESSION['user_id'] ?? null;
$rolUsuario = $_SESSION['rol'] ?? '';
$esSuperdev = $rolUsuario === 'superdesarrollador';
// Filtro de Canal: misma whitelist que components/historial/historial.php, sirve los refrescos AJAX igual que la carga inicial SSR.
$canal = in_array($_GET['canal'] ?? '', ['directo', 'distribuidor'], true) ? $_GET['canal'] : 'total';
$resultado  = listar_historial_acuerdos($mysqli, $busqueda, $trimestre, $anio, $filtroFirma, $pagina, $usuarioId, 10, $rolUsuario, $canal);

$filas = '';
foreach ($resultado['acuerdos'] as $a) {
	$filas .= renderFilaHistorial($a, $esSuperdev);
}
if (!$resultado['acuerdos']) {
	$filas = '<tr><td colspan="'.($esSuperdev ? 8 : 7).'" class="ac-table-empty">No se encontraron acuerdos.</td></tr>';
}

// Stats de los 3 tiles: mismo alcance que la tabla pero sin el filtro de firma, para que no cuenten solo lo ya filtrado.
$stats = obtener_stats_historial($mysqli, $busqueda, $trimestre, $anio, $usuarioId, $rolUsuario, $canal);

echo json_encode([
	'ok'            => true,
	'filas'         => $filas,
	'pagina'        => $resultado['pagina'],
	'total_paginas' => $resultado['total_paginas'],
	'total'         => $resultado['total'],
	'mostrando'     => count($resultado['acuerdos']),
	'stats'         => $stats,
]);
?>
