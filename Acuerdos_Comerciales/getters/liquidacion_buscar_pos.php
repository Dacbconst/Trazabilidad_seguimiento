<?php
// Búsqueda libre de pos_id por nombre (contiene, no solo prefijo) — para la
// pantalla de "Pendientes de Asignar" cuando una fila quedó en sin_match
// (cero candidatos automáticos) y el superdesarrollador tiene que buscar a
// mano el cliente correcto.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 3) {
	echo json_encode(['ok' => true, 'resultados' => []]);
	exit;
}

$like = '%'.$q.'%';
$stmt = $mysqli->prepare(
	"SELECT DISTINCT pos_id, pos_name, cedi, supervisor, tipo_distribuidor
	 FROM repositorio_locales_supervisores_cliente
	 WHERE pos_name LIKE ? LIMIT 20"
);
$stmt->bind_param('s', $like);
$stmt->execute();
$resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['ok' => true, 'resultados' => $resultados]);
?>
