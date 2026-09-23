<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/db_connect.php';
iniciar_sesion();

// Sin esto, sesion_token quedaba "vivo" en la base para siempre tras cerrar sesión normal, y el próximo login (mismo usuario, cualquier dispositivo) disparaba la alerta de "sesión activa" sin que hubiera ninguna (bug real, 2026-09-24). Solo limpia si coincide con el token de ESTA sesión, no cualquiera.
if (isset($_SESSION['user_id'], $_SESSION['sesion_token'])) {
	$stmt = $mysqli->prepare('UPDATE repositorio_usuarios_acuerdos SET sesion_token = NULL WHERE id = ? AND sesion_token = ?');
	if ($stmt) {
		$stmt->bind_param('is', $_SESSION['user_id'], $_SESSION['sesion_token']);
		$stmt->execute();
		$stmt->close();
	}
}

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
?>
