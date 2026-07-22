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

$busqueda  = trim($_GET['q'] ?? '');
$mes       = (int) ($_GET['mes'] ?? 0);
$pagina    = (int) ($_GET['pg'] ?? 1);
$resultado = listar_historial_acuerdos($mysqli, $busqueda, $mes, $pagina);

$filas = '';
foreach ($resultado['acuerdos'] as $a) {
	$filas .= renderFilaHistorial($a);
}
if (!$resultado['acuerdos']) {
	$filas = '<tr><td colspan="6" class="ac-table-empty">No se encontraron acuerdos.</td></tr>';
}

echo json_encode([
	'ok'            => true,
	'filas'         => $filas,
	'pagina'        => $resultado['pagina'],
	'total_paginas' => $resultado['total_paginas'],
	'total'         => $resultado['total'],
	'mostrando'     => count($resultado['acuerdos']),
]);
?>
