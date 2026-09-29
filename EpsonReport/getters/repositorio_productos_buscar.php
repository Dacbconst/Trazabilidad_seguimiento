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

// Catálogo completo (hoy ~20 SKU activos): se trae una sola vez y el navegador filtra en el momento, sin ida y vuelta al servidor por cada letra.
$res = $mysqli->query("SELECT sku FROM repositorio_productos WHERE marca = 'EPSON' AND activar = 'SI' AND categoria = 'IMPRESORAS' ORDER BY sku LIMIT 200");
$productos = [];
while ($row = $res->fetch_assoc()) { $productos[] = $row['sku']; }
echo json_encode(['ok' => true, 'productos' => $productos]);
