<?php
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../includes/functions.php';

if (!ep_login_check()) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/db.php';
$mysqli = ep_db();

$q = trim($_GET['q'] ?? '');
$like = '%'.$q.'%';
$stmt = $mysqli->prepare("SELECT sku FROM repositorio_productos WHERE marca = 'EPSON' AND activar = 'SI' AND categoria = 'IMPRESORAS' AND sku LIKE ? ORDER BY sku LIMIT 20");
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();
$productos = [];
while ($row = $res->fetch_assoc()) { $productos[] = $row['sku']; }
echo json_encode(['ok' => true, 'productos' => $productos]);
