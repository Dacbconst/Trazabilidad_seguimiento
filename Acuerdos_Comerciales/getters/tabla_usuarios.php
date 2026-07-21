<?php
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$busqueda  = trim($_GET['q'] ?? '');
$pagina    = (int) ($_GET['pg'] ?? 1);
$resultado = listar_usuarios_acuerdos($mysqli, $busqueda, $pagina);

$filas = '';
foreach ($resultado['usuarios'] as $u) {
	$filas .= renderFilaUsuario($u, $_SESSION['user_id']);
}
if (!$resultado['usuarios']) {
	$filas = '<tr><td colspan="5" class="ac-table-empty">No se encontraron usuarios.</td></tr>';
}

echo json_encode([
	'ok'            => true,
	'filas'         => $filas,
	'pagina'        => $resultado['pagina'],
	'total_paginas' => $resultado['total_paginas'],
	'total'         => $resultado['total'],
]);
?>
