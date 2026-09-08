<?php
// JSON crudo, el front arma el DOM completo desde esto (sin HTML pre-armado); jerarquía ya agrupada Asesor -> Cliente -> Categoría.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

// Bufferea warnings/notices de PHP para que no se mezclen con el JSON de respuesta.
ob_start();
set_exception_handler(function ($e) {
	while (ob_get_level() > 0) { ob_end_clean(); }
	echo json_encode(['ok' => false, 'message' => 'No se pudo cargar: '.$e->getMessage()]);
	exit;
});

$trimestre = (int) ($_GET['trimestre'] ?? 0);
$anio      = (int) ($_GET['anio'] ?? 0);
$busqueda  = trim((string) ($_GET['q'] ?? ''));
$canal     = in_array($_GET['canal'] ?? '', ['directo', 'distribuidor'], true) ? $_GET['canal'] : 'total';

$filas = listar_cumplimiento_cuota($mysqli, $trimestre, $anio, $busqueda, $canal);
$stats = resumen_cumplimiento_cuota($mysqli, $trimestre, $anio, $canal);
$anios = listar_anios_disponibles_cumplimiento($mysqli);

// ---------- Agrupar Asesor -> Cliente -> Categoría ----------
$usuarios = []; // usuario_id (o 0 = "Sin asesor identificado") -> datos
$ordenUsuarios = [];

foreach ($filas as $f) {
	$uid = $f['usuario_id'] !== null ? (int) $f['usuario_id'] : 0;
	if (!isset($usuarios[$uid])) {
		$usuarios[$uid] = [
			'usuario_id' => $uid ?: null,
			'nombre'     => $uid ? $f['usuario_nombre'] : 'Sin asesor identificado',
			'clientes'   => [], // pos_id -> datos
		];
		$ordenUsuarios[] = $uid;
	}

	$posId = $f['pos_id'];
	if (!isset($usuarios[$uid]['clientes'][$posId])) {
		$usuarios[$uid]['clientes'][$posId] = [
			'pos_id'        => $posId,
			'cliente'       => $f['cliente_excel'],
			'cedi'          => $f['cedi_excel'],
			'plan'          => $f['plan_excel'],
			'canal'         => $f['canal'],
			'gana_total'    => $f['gana_total'],
			'actualizado_en' => $f['updated_at'],
			'categorias'    => [],
		];
	}

	$actualizadoAnterior = $usuarios[$uid]['clientes'][$posId]['actualizado_en'];
	if ($f['updated_at'] > $actualizadoAnterior) {
		$usuarios[$uid]['clientes'][$posId]['actualizado_en'] = $f['updated_at'];
	}

	$cambio = null;
	if ($f['gana_categoria_anterior'] !== null && $f['gana_categoria_anterior'] !== $f['gana_categoria']) {
		$cambio = $f['gana_categoria'] === 'gana' ? 'mejora' : 'empeora';
	}

	// gana_total se repite por categoría a propósito, para comparar Gana Categoría vs Gana Total lado a lado en la misma fila.
	$usuarios[$uid]['clientes'][$posId]['categorias'][] = [
		'id'               => (int) $f['id'],
		'sector'           => $f['sector'],
		'cumplimiento_pct' => round((float) $f['cumplimiento_pct'], 2),
		'venta_total'      => round((float) $f['venta_total'], 2),
		'cuota_total'      => round((float) $f['cuota_total'], 2),
		'gana_categoria'   => $f['gana_categoria'],
		'gana_total'       => $f['gana_total'],
		'rebate_real_vol'  => round((float) $f['rebate_real_vol'], 2),
		'cambio'           => $cambio,
	];
}

$usuariosLista = [];
foreach ($ordenUsuarios as $uid) {
	$u = $usuarios[$uid];
	$clientes = array_values($u['clientes']);
	$categoriasTotal = 0;
	$ganan = 0;
	foreach ($clientes as $c) {
		$categoriasTotal += count($c['categorias']);
		foreach ($c['categorias'] as $cat) {
			if ($cat['gana_categoria'] === 'gana') $ganan++;
		}
	}
	$usuariosLista[] = [
		'usuario_id'         => $u['usuario_id'],
		'nombre'             => $u['nombre'],
		// Calculado acá con inicialesUsuario(), no en JS, para no repetir el bug de iniciales distintas PHP/JS de Seguimiento de Equipo.
		'iniciales'          => $u['usuario_id'] ? inicialesUsuario($u['nombre']) : '?',
		'clientes'           => $clientes,
		'total_clientes'     => count($clientes),
		'total_categorias'   => $categoriasTotal,
		'ganan_categoria'    => $ganan,
		'no_ganan_categoria' => $categoriasTotal - $ganan,
	];
}

while (ob_get_level() > 0) { ob_end_clean(); }
echo json_encode([
	'ok'       => true,
	'usuarios' => $usuariosLista,
	'stats'    => $stats,
	'anios'    => $anios,
]);
?>
