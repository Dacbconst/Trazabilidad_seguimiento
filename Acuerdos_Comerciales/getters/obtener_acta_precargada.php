<?php
// Fase 2 del Repositorio de Cuotas (2026-08-25) — arma el detalle de una Acta
// precargada para que registrar.js la cargue en el formulario. A diferencia
// de obtener_borrador.php (que exige creado_por === usuario de sesión, un
// Acuerdo que YA existe), acá no hay ningún acuerdo_id todavía — la
// propiedad se valida resolviendo a quién le corresponde ese pos_id
// (usuarioIdDePosId(), includes/functions.php) y comparando contra la
// sesión, mismo criterio de "nadie ve datos ajenos adivinando el id" que el
// resto del proyecto.
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

$usuarioDueno = usuarioIdDePosId($mysqli, $posId);
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
