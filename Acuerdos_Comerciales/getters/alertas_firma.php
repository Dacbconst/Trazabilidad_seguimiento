<?php
// Campanita del header (2026-08-25): alertas de Actas por vencer (20 días
// desde fecha_generacion sin firmar, ver barrer_actas_vencidas() en
// includes/functions.php). "mias" es para cualquier desarrollador/
// superdesarrollador (sus propias Actas, últimos 5 días de plazo);
// "equipo" es exclusivo de superdesarrollador — seguimiento de TODOS los
// pendientes de todos los usuarios, no solo los urgentes (pedido explícito:
// no es una alerta, es visibilidad de quién trae pendientes).
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
$esSuper   = rolPermitido(['superdesarrollador']);

$mias   = listar_alertas_firma_propias($mysqli, $usuarioId, 5);
$equipo = $esSuper ? listar_equipo_pendientes_firma($mysqli) : [];

echo json_encode(['ok' => true, 'mias' => $mias, 'equipo' => $equipo]);
?>
