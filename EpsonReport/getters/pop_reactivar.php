<?php
// Reactiva un mes de POP cerrado y retira su reporte (solo Fabricio o el admin); al cerrarlo de nuevo se genera otro.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'supervisor'], true)) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/pop_datos.php';

if (!ep_pop_es_dueno()) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'Solo Fabricio o un administrador manejan el material POP.']);
	exit;
}

$id = (int) ($_POST['id'] ?? 0);
if (!ep_pop_permitido($id)) {
	echo json_encode(['ok' => false, 'message' => 'Ese mes de POP no es tuyo.']);
	exit;
}
$pop = ep_pop_obtener($id);
if (ep_pop_abierto()) {
	echo json_encode(['ok' => false, 'message' => 'Ya hay un mes abierto. Ciérralo antes de reactivar otro.']);
	exit;
}
if (!ep_pop_reactivar($id)) {
	echo json_encode(['ok' => false, 'message' => 'No se pudo reactivar el mes. Recarga la página.']);
	exit;
}
require_once __DIR__.'/../includes/auditoria_datos.php';
ep_auditar('pop_reactivar', 'pop', $id, 'Reactivó el mes de POP de '.ep_pop_mes_texto($pop['mes']), []);
echo json_encode(['ok' => true]);
