<?php
// Chequeo antes de guardar, solo lectura. Mismo patrón que cuotas_verificar_estado.php, clave única sector+categoria+marca.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/repositorio_import.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

function responder($ok, $message, $extra = []) {
	echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
	exit;
}

$usuarioSesion = $_SESSION['user_id'] ?? null;
$body      = json_decode(file_get_contents('php://input'), true);
$filas     = is_array($body['filas'] ?? null) ? $body['filas'] : [];
$trimestre = (int) ($body['trimestre'] ?? 0);
$anio      = (int) ($body['anio'] ?? 0);
$canal     = ($body['canal'] ?? '') === 'distribuidor' ? 'distribuidor' : 'directo';

if (!$filas || $trimestre < 1 || $trimestre > 4 || $anio <= 0) {
	responder(false, 'Parámetros inválidos.');
}

$cacheSector = [];
$cachePosId  = [];
$cacheDiagnostico = [];
$stmtExistente = $mysqli->prepare(
	"SELECT estado FROM repositorio_acuerdo_completo_linea
	 WHERE pos_id = ? AND tipo = 'meta_compra' AND sector = ? AND categoria = ? AND marca = ? AND trimestre = ? AND anio = ? LIMIT 1"
);

$estados = [];
foreach ($filas as $fila) {
	$clienteExcel = repositorio_normalizar_texto($fila['cliente_excel'] ?? '');
	$cediExcel    = repositorio_normalizar_texto($fila['cedi_excel'] ?? '');
	$usuarioExcel = trim((string) ($fila['usuario_excel'] ?? ''));
	$sector       = repositorio_normalizar_texto($fila['sector'] ?? '');
	$subcategoria = repositorio_normalizar_texto($fila['subcategoria'] ?? '');
	$marca        = repositorio_normalizar_texto($fila['marca'] ?? '');
	if ($clienteExcel === '' || $sector === '') {
		$estados[] = ['estado' => 'invalido'];
		continue;
	}

	if (!array_key_exists($sector, $cacheSector)) {
		$cacheSector[$sector] = resolverSectorReal($mysqli, $sector);
	}
	$sectorCrudoMatch = $cacheSector[$sector];
	$sectorResuelto = $sectorCrudoMatch ?: $sector;
	$sectorInterpretado = $sectorCrudoMatch !== null && $sectorCrudoMatch !== $sector;
	$sectorSinResolver = $sectorCrudoMatch === null;

	$clavePos = $clienteExcel.'|'.$cediExcel;
	if (!array_key_exists($clavePos, $cachePosId)) {
		$plan = repositorio_normalizar_texto($fila['plan'] ?? '');
		$diagnostico = null;
		$cachePosId[$clavePos] = resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel, $canal, $plan, $diagnostico, $usuarioSesion);
		$cacheDiagnostico[$clavePos] = $diagnostico;
	}
	$posId = $cachePosId[$clavePos];

	if (!$posId) {
		$sugerencias = ($cacheDiagnostico[$clavePos] ?? null) === null ? sugerirClienteSimilar($mysqli, $clienteExcel, $canal) : [];
		$estados[] = [
			'estado' => 'sin_cliente', 'sector_resuelto' => $sectorResuelto,
			'sector_interpretado' => $sectorInterpretado, 'sector_sin_resolver' => $sectorSinResolver,
			'diagnostico' => $cacheDiagnostico[$clavePos] ?? null, 'sugerencias' => $sugerencias,
		];
		continue;
	}

	$existente = null;
	if ($stmtExistente) {
		$stmtExistente->bind_param('ssssii', $posId, $sectorResuelto, $subcategoria, $marca, $trimestre, $anio);
		$stmtExistente->execute();
		$existente = $stmtExistente->get_result()->fetch_assoc();
	}
	if (!$existente) $estado = 'nuevo';
	elseif ($existente['estado'] === 'usada') $estado = 'usada';
	else $estado = 'actualiza';

	$asignado = resolverNombreAsignadoCuota($mysqli, $posId, $cediExcel, $clienteExcel, $usuarioExcel);

	$estados[] = [
		'estado' => $estado, 'sector_resuelto' => $sectorResuelto,
		'sector_interpretado' => $sectorInterpretado, 'sector_sin_resolver' => $sectorSinResolver,
		'pos_id' => $posId, 'asignado_a' => $asignado['nombre'], 'tiene_cuenta' => $asignado['tiene_cuenta'],
	];
}
if ($stmtExistente) $stmtExistente->close();

responder(true, 'ok', ['estados' => $estados]);
?>
