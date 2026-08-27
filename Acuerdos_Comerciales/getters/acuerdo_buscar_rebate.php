<?php
// Busca el Rebate % ya cargado en el repositorio (repositorio_rebate_producto)
// para una combinación de Ciudad+Canal+Sector+Categoría+Marca — usado por
// Registrar Acuerdo PDV para autocompletar y bloquear el campo Rebate % de
// Meta de Compras (2026-08-27, "conectar Rebate a Registrar", objetivo final
// documentado desde la reunión JW 2026-08-18). Solo lectura, nunca escribe.
//
// 2026-08-27, 3ra vuelta: ya se resuelve el match completo, incluyendo
// Ciudad/Canal (antes quedaba "sin match" siempre que un producto tuviera
// más de un valor por Ciudad/Canal, que es el caso real de casi todo
// `datos/RABATE.xlsx`). El frontend (assets/js/registrar.js,
// buscarYAplicarRebate()) resuelve Canal desde el canal real del usuario
// (DISTRIBUIDOR/DIRECTA, mismo criterio que `es_distribuidor` en el resto
// del proyecto) y Ciudad desde la Localidad (CEDI) del cliente elegido —
// **excepto para Distribuidor, donde el repositorio siempre usa "TODAS"
// sin importar la ciudad real del distribuidor** (confirmado con datos
// reales: las 11 filas de canal DISTRIBUIDOR en el Excel dicen Ciudad
// "TODAS", nunca una ciudad puntual). Con los 5 campos completos, el match
// es exacto sobre la clave única de la tabla — ya no hace falta degradar a
// "ambiguo" como en la versión anterior.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$ciudad    = trim($_GET['ciudad'] ?? '');
$canal     = trim($_GET['canal'] ?? '');
$sector    = trim($_GET['sector'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');
$marca     = trim($_GET['marca'] ?? '');

if ($ciudad === '' || $canal === '' || $sector === '' || $categoria === '' || $marca === '') {
	echo json_encode(['ok' => true, 'encontrado' => false]);
	exit;
}

// buscarRebateProducto() (includes/functions.php, 2026-08-27) — no solo
// UPPER(TRIM(...)) exacto: prueba variantes de plural/singular de Sector y
// Categoría, y como último recurso Ciudad+Canal+Sector+Marca sin Categoría.
// Hace falta porque el Excel real de JW y el cascade real de Registrar
// (repositorio_productos) no siempre usan el mismo texto exacto (ej.
// "LIQUIDOS" vs "LIQUIDO", "DETERGENTE" vs "ROPA" para EL MACHO) — sin esto,
// la mayoría de las filas reales del repositorio nunca hacían match.
$rebatePct = buscarRebateProducto($mysqli, $ciudad, $canal, $sector, $categoria, $marca);

if ($rebatePct !== null) {
	echo json_encode(['ok' => true, 'encontrado' => true, 'rebate_pct' => $rebatePct]);
} else {
	echo json_encode(['ok' => true, 'encontrado' => false]);
}
?>
