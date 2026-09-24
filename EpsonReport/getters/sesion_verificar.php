<?php
// Ping liviano para sesion-watch.js: ep_login_check() ya vacía la sesión si otro login pisó el token; acá solo se avisa al frontend.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
// El ping siempre es latido; solo cuenta como interacción del usuario si el navegador avisa (?activo=1).
$ok = ep_login_check(($_GET['activo'] ?? '') === '1');
// La página manda su cuenta (?u=): si la sesión del navegador ya es de otra, se avisa de inmediato.
if ($ok && isset($_GET['u']) && (string) $_GET['u'] !== (string) $_SESSION['usuario_id']) {
	echo json_encode(['ok' => false, 'motivo' => 'cuenta_cambiada']);
	exit;
}
echo json_encode(['ok' => $ok, 'motivo' => $ok ? null : ($GLOBALS['ep_motivo_cierre'] ?? 'otro_dispositivo')]);
