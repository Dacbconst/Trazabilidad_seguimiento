<?php
// Campanita del header, en vivo (nunca cacheada), solo Actas propias del
// usuario: "mias" (por vencer, últimos 5 días) y "precargadas" (Actas Asignadas).
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$usuarioId = $_SESSION['user_id'] ?? null;

$mias        = listar_alertas_firma_propias($mysqli, $usuarioId, 5);
$precargadas = listar_actas_precargadas_pendientes($mysqli, $usuarioId);

echo json_encode(['ok' => true, 'mias' => $mias, 'precargadas' => $precargadas]);
?>
