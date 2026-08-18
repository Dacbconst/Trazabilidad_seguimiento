<?php
// Lista de Excels subidos, para la pantalla principal de Liquidación.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$res = $mysqli->query(
	"SELECT id, canal, anio, mes_inicio, mes_fin, nombre_archivo, estado, total_filas, filas_pendientes, created_at
	 FROM repositorio_liquidacion_importaciones ORDER BY created_at DESC LIMIT 50"
);
$importaciones = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

echo json_encode(['ok' => true, 'importaciones' => $importaciones]);
?>
