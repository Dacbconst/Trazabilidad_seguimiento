<?php
// Firma liviana de los registros (total y último id) para saber si el Historial cambió sin pedir la lista completa.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!ep_login_check()) {
	http_response_code(401);
	echo '{}';
	exit;
}
session_write_close();

$db = ep_db();
$fila = $db ? $db->query("SELECT COUNT(*) AS total, COALESCE(MAX(id), 0) AS ultimo, COALESCE(SUM(estado = 'Pendiente'), 0) AS pend, COALESCE(SUM(estado = 'Devuelto'), 0) AS dev FROM insert_reporte_registro r WHERE eliminado_en IS NULL".(ep_es_supervisor() ? ' AND r.supervisor_id = '.(int) $_SESSION['usuario_id'] : ''))->fetch_assoc() : null;
echo json_encode(['firma' => $fila ? $fila['total'].'-'.$fila['ultimo'].'-'.$fila['pend'].'-'.$fila['dev'] : '']);
