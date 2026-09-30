<?php
// Reactiva un calendario ya cerrado (solo admin), con un plazo nuevo desde ahora. Queda quién y cuándo en la fila.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'admin') {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/calendario_datos.php';

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
	echo json_encode(['ok' => false, 'message' => 'Calendario no válido.']);
	exit;
}

$antes = ep_calendario_obtener($id);
if (!$antes || !ep_calendario_reactivar($id, (int) $_SESSION['usuario_id'])) {
	echo json_encode(['ok' => false, 'message' => 'No se pudo reactivar.']);
	exit;
}
$despues = ep_calendario_obtener($id);
require_once __DIR__.'/../includes/auditoria_datos.php';
ep_auditar('calendario_reactivar', 'calendario', $id, 'Reactivó «'.ep_calendario_nombre($antes).'»', [
	ep_auditoria_dato('Estaba cerrado desde', $antes['cerrado_en'] ? date('d/m/Y H:i', strtotime($antes['cerrado_en'])) : '—'),
	ep_auditoria_dato('Nuevo vencimiento', $despues ? date('d/m/Y H:i', strtotime($despues['vence_en'])) : '—'),
]);
echo json_encode(['ok' => true]);
