<?php
// Arma el detalle de una Acta precargada para registrar.js. Sin acuerdo_id todavía: la propiedad se valida resolviendo el dueño del pos_id contra la sesión.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

function responder($ok, $message, $extra = []) {
	echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
	exit;
}

$posId     = trim($_GET['pos_id'] ?? '');
$trimestre = (int) ($_GET['trimestre'] ?? 0);
$anio      = (int) ($_GET['anio'] ?? 0);
$usuarioSesion = $_SESSION['user_id'] ?? null;

if ($posId === '' || $trimestre < 1 || $trimestre > 4 || $anio <= 0) {
	responder(false, 'Parámetros inválidos.');
}

// CEDI del Excel gana sobre el maestro de Alicorp acá (usuarioIdDeCuota()): hay choques reales confirmados entre el maestro y el Excel de JW.
$usuarioDueno = usuarioIdDeCuota($mysqli, $posId, $trimestre, $anio);
if (!$usuarioDueno || (int) $usuarioDueno !== (int) $usuarioSesion) {
	http_response_code(404);
	responder(false, 'Acta precargada no encontrada.');
}

$precarga = obtener_precarga_detalle($mysqli, $posId, $trimestre, $anio);
if (!$precarga) {
	http_response_code(404);
	responder(false, 'Acta precargada no encontrada — puede que ya se haya usado.');
}

responder(true, 'ok', ['precarga' => $precarga]);
?>
