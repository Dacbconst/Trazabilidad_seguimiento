<?php
// Lista las filas borradas lógicamente de un repositorio (Rebate o
// Participación de Percha) — ver "Eliminar" en repositorio_eliminar.php y la
// nota de borrado lógico en datos/repositorios_schema.sql. Filtrable por
// rango de fecha de borrado (`desde`/`hasta`, YYYY-MM-DD) — el caso real que
// motivó esto: "me dicen que por error borraron algo, quiero filtrar rápido
// el día y reactivarlo" (2026-08-25, pedido explícito). Solo superdesarrollador,
// mismo criterio que el resto de Repositorios.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$tipo  = $_GET['tipo'] ?? '';
$desde = trim($_GET['desde'] ?? ''); // YYYY-MM-DD, vacío = sin piso
$hasta = trim($_GET['hasta'] ?? ''); // YYYY-MM-DD, vacío = sin techo

if (!in_array($tipo, ['rebate', 'participacion'], true)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'message' => 'Tipo de repositorio inválido.']);
	exit;
}

// (? = '' OR DATE(eliminado_en) >= ?) — mismo patrón "0 = sin filtrar" que
// ya usa el resto del proyecto para filtros opcionales (ver trimestreABounds()),
// adaptado a texto vacío porque acá el filtro es una fecha, no un entero.
if ($tipo === 'rebate') {
	$stmt = $mysqli->prepare(
		"SELECT r.id, r.ciudad, r.canal, r.sector, r.categoria, r.marca, r.rebate_pct, r.eliminado_en, u.usuario AS eliminado_por_usuario
		 FROM repositorio_rebate_producto r
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = r.eliminado_por
		 WHERE r.eliminado_en IS NOT NULL
		   AND (? = '' OR DATE(r.eliminado_en) >= ?)
		   AND (? = '' OR DATE(r.eliminado_en) <= ?)
		 ORDER BY r.eliminado_en DESC"
	);
} else {
	$stmt = $mysqli->prepare(
		"SELECT p.id, p.marca, p.participacion_pct, p.eliminado_en, u.usuario AS eliminado_por_usuario
		 FROM repositorio_participacion_percha p
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = p.eliminado_por
		 WHERE p.eliminado_en IS NOT NULL
		   AND (? = '' OR DATE(p.eliminado_en) >= ?)
		   AND (? = '' OR DATE(p.eliminado_en) <= ?)
		 ORDER BY p.eliminado_en DESC"
	);
}
if (!$stmt) {
	echo json_encode(['ok' => false, 'message' => 'El repositorio todavía no existe en la base, o falta correr la migración de borrado lógico (ver datos/repositorios_schema.sql).']);
	exit;
}
$stmt->bind_param('ssss', $desde, $desde, $hasta, $hasta);
$stmt->execute();
$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['ok' => true, 'filas' => $filas, 'total' => count($filas)]);
?>
