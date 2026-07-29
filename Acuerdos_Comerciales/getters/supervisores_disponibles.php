<?php
// Refresca el estado de los combos de Supervisor en Gestión de Usuarios
// (quién ya tomó cuál) después de crear/editar/activar-desactivar un usuario,
// sin necesidad de recargar la página. Ver supervisores_asignados_activos()
// en includes/functions.php para la regla de negocio (1 supervisor = 1 cuenta).
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$supervisores = listar_supervisores_disponibles($mysqli);
$asignados    = supervisores_asignados_activos($mysqli);

$resultado = [];
foreach ($supervisores as $s) {
	$resultado[] = ['nombre' => $s, 'tomado_por' => $asignados[$s] ?? null];
}

echo json_encode(['ok' => true, 'supervisores' => $resultado]);
?>
