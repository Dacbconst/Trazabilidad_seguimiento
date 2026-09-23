<?php
// Ping liviano para sesion-watch.js: login_check() ya destruye la sesión server-side si otro login pisó el token, acá solo se informa al frontend para que redirija.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');
$valida = login_check();
if ($valida) {
	$stmt = $mysqli->prepare('UPDATE repositorio_usuarios_acuerdos SET sesion_ultima_actividad = NOW() WHERE id = ? AND sesion_token = ?');
	if ($stmt) {
		$stmt->bind_param('is', $_SESSION['user_id'], $_SESSION['sesion_token']);
		$stmt->execute();
		$stmt->close();
	}
}
echo json_encode(['ok' => $valida]);
?>
