<?php
// Busca el Rebate % del repositorio (Ciudad+Canal+Sector+Categoría+Marca) para
// autocompletar y bloquear el campo en Registrar. Solo lectura, nunca escribe.
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

// buscarRebateProducto() tolera plural/singular y, como último recurso,
// matchea sin Categoría — el texto del Excel de JW no siempre calza exacto
// con el catálogo real (ej. "LIQUIDOS" vs "LIQUIDO").
$rebatePct = buscarRebateProducto($mysqli, $ciudad, $canal, $sector, $categoria, $marca);

if ($rebatePct !== null) {
	echo json_encode(['ok' => true, 'encontrado' => true, 'rebate_pct' => $rebatePct]);
} else {
	echo json_encode(['ok' => true, 'encontrado' => false]);
}
?>
