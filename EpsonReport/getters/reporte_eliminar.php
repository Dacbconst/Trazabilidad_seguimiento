<?php
// Quita un reporte mensual del histórico (borrado lógico, POST id). Solo admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/reportes_datos.php';
header('Content-Type: application/json; charset=utf-8');

if (!ep_login_check() || !ep_es_gestor()) {
	http_response_code(403);
	echo json_encode(['success' => false, 'error' => 'No tienes permiso para esto.']);
	exit;
}
$id = (int) ($_POST['id'] ?? 0);
$reporte = ep_reporte_obtener($id);
$ok = ep_reporte_eliminar($id);
if ($ok && $reporte) {
	require_once __DIR__.'/../includes/auditoria_datos.php';
	ep_auditar('reporte_eliminar', 'reporte', $id, 'Eliminó el reporte «'.(trim((string) $reporte['titulo']) ?: $reporte['tipo']).'»', [
		ep_auditoria_dato('Mes', $reporte['mes']),
		ep_auditoria_dato('Registros liberados', $reporte['total_registros']),
	]);
}
echo json_encode(['success' => $ok, 'error' => $ok ? null : 'No se pudo quitar el reporte.']);
