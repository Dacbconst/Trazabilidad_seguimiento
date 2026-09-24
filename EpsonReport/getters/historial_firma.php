<?php
// Firma liviana de los registros (total y último id) para saber si el Historial cambió sin pedir la lista completa.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
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
$fila = $db ? $db->query('SELECT COUNT(*) AS total, COALESCE(MAX(id), 0) AS ultimo FROM insert_reporte_registro WHERE eliminado_en IS NULL')->fetch_assoc() : null;
echo json_encode(['firma' => $fila ? $fila['total'].'-'.$fila['ultimo'] : '']);
