<?php
// Campanita del header (2026-08-25) — 2 módulos de notificación, ambos
// EN VIVO (nunca cacheados), ambos para cualquier desarrollador/
// superdesarrollador sobre sus propias Actas (nada de vista de equipo: se
// sacó "equipo" — seguimiento agregado de todos los usuarios, solo
// superdesarrollador — a pedido explícito del usuario, 2026-08-25):
// - "mias": Actas por vencer (20 días desde fecha_generacion sin firmar,
//   ver barrer_actas_vencidas() en includes/functions.php), últimos 5 días
//   de plazo. Pestaña "Actas Por Firmar" del panel.
// - "precargadas" (Fase 2 del Repositorio de Cuotas): Actas precargadas
//   pendientes de completar — "una Acta asignada es más urgente que un
//   Borrador propio, no la escondas detrás de un botón". Pestaña "Actas
//   Asignadas" del panel.
// Diseño del panel (tabs + activity feed + alert boxes) tomado de
// "diseños ideas/code.html" (mockup de referencia, solo esa parte — ver
// CLAUDE.md).
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
