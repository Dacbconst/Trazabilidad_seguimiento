<?php
// Guarda en sesión el canal que el superdesarrollador SIN supervisor eligió para Registrar Acuerdo PDV (ver canalEfectivoUsuario()/
// esModoAdminSinCartera() en includes/functions.php). Solo tiene efecto para esa cuenta puntual — un usuario con supervisor real
// nunca puede pisar su canal real de esta forma, el chequeo de esModoAdminSinCartera() lo bloquea.
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../includes/functions.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

if (!esModoAdminSinCartera()) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'Tu cuenta ya tiene un canal real asignado, no se puede cambiar acá.']);
	exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$canal = is_array($body) ? trim($body['canal'] ?? '') : '';
if (!in_array($canal, ['directo', 'distribuidor'], true)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'message' => 'Canal inválido.']);
	exit;
}

$_SESSION['canal_admin'] = $canal;
echo json_encode(['ok' => true, 'canal' => $canal]);
?>
