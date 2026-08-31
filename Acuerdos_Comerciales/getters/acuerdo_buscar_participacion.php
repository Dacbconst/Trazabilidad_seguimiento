<?php
// Busca el % de Participación ya cargado en el repositorio
// (repositorio_participacion_percha) para una Ciudad+Marca — usado por
// Registrar Acuerdo PDV para autocompletar y bloquear el campo Participación
// de la tabla de Perchas (2026-08-30, "conectar Participación a Registrar",
// mismo patrón que Rebate — ver acuerdo_buscar_rebate.php). Solo lectura,
// nunca escribe.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$ciudad = trim($_GET['ciudad'] ?? '');
$marca  = trim($_GET['marca'] ?? '');

if ($ciudad === '' || $marca === '') {
	echo json_encode(['ok' => true, 'encontrado' => false]);
	exit;
}

$participacionPct = buscarParticipacionPercha($mysqli, $ciudad, $marca);

if ($participacionPct !== null) {
	echo json_encode(['ok' => true, 'encontrado' => true, 'participacion_pct' => $participacionPct]);
} else {
	echo json_encode(['ok' => true, 'encontrado' => false]);
}
?>
