<?php
// Seguimiento de Equipo (2026-08-27) — resumen para superdesarrollador:
// stats globales + un array por usuario con sus 4 conteos, en JSON crudo
// (el frontend arma las 4 vistas filtradas al vuelo, ver
// assets/js/seguimiento.js). Única pantalla del proyecto que muestra Actas
// de TODOS los usuarios, no solo las propias — reforzar el chequeo de rol
// acá, no alcanza con que el módulo esté oculto del sidebar para otros roles.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$trimestre = (int) ($_GET['trimestre'] ?? 0);
$anio      = (int) ($_GET['anio'] ?? 0);

echo json_encode(['ok' => true] + resumen_seguimiento_equipo($mysqli, $trimestre, $anio));
?>
