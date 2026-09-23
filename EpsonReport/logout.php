<?php
require_once __DIR__.'/config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/includes/db.php';

// Libera el token solo si coincide con el de ESTA sesión (aunque la sesión ya esté vencida), así el próximo login no pregunta por una sesión que ya no existe.
if (isset($_SESSION['usuario_id'], $_SESSION['sesion_token'])) {
	$db = ep_db();
	$stmt = $db ? $db->prepare('UPDATE repositorio_usuarios_reporte SET sesion_token = NULL WHERE id = ? AND sesion_token = ?') : null;
	if ($stmt) {
		$stmt->bind_param('is', $_SESSION['usuario_id'], $_SESSION['sesion_token']);
		$stmt->execute();
		$stmt->close();
	}
}

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
