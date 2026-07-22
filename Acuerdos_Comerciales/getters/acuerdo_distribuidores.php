<?php
// Lista de Distribuidores (pos_id) para el formulario de Acuerdo PDV.
// "Localidad" nunca se guarda (regla de negocio #8 en CLAUDE.md) — se manda
// province/city aquí mismo para que el front derive la Localidad en el
// momento de elegir el Distribuidor, sin otra consulta.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['admin', 'desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$distribuidores = [];
$res = $mysqli->query(
	"SELECT pos_id, pos_name, province, city
	 FROM repositorio_locales_dtt2
	 WHERE activar = 'SI'
	   AND status = '1'
	   AND pos_id IS NOT NULL AND pos_id <> ''
	 ORDER BY pos_name"
);
while ($row = $res->fetch_assoc()) {
	$distribuidores[] = [
		'pos_id'   => $row['pos_id'],
		'pos_name' => $row['pos_name'],
		'province' => $row['province'],
		'city'     => $row['city'],
	];
}

echo json_encode(['ok' => true, 'distribuidores' => $distribuidores]);
?>
