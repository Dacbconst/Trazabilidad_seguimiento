<?php
// Exporta el repositorio completo (respetando la búsqueda activa, si hay) —
// CSV o .xlsx real, según ?formato= (2026-08-24, antes solo CSV). El .xlsx
// reusa includes/xlsx_writer.php (el mismo escritor propio que ya arma
// Descargar Excel de Historial) — acá sin fórmulas, solo celdas con formato
// de %, mucho más simple que esos exports.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo 'No autorizado.';
	exit;
}

$tipo     = $_GET['tipo'] ?? '';
$busqueda = trim($_GET['q'] ?? '');
$formato  = in_array($_GET['formato'] ?? '', ['csv', 'xlsx'], true) ? $_GET['formato'] : 'csv';
if (!in_array($tipo, ['rebate', 'participacion'], true)) {
	http_response_code(400);
	echo 'Tipo de repositorio inválido.';
	exit;
}

// porPagina alto para traer "todo" en una sola pasada — un catálogo de
// referencia no llega a tener miles de filas como para justificar paginar
// también la exportación.
$resultado = $tipo === 'rebate'
	? listar_repositorio_rebate($mysqli, $busqueda, 1, 100000)
	: listar_repositorio_participacion($mysqli, $busqueda, 1, 100000);

$nombreBase = ($tipo === 'rebate' ? 'Rebate' : 'Participacion_Percha').'_'.date('Y-m-d');

if ($formato === 'xlsx') {
	require_once __DIR__.'/../includes/xlsx_writer.php'; // escritor propio, sin librería externa (ver cabecera de ese archivo)

	$wb = new XlsxWriter();
	$hoja = $wb->agregarHoja($tipo === 'rebate' ? 'REBATE' : 'PARTICIPACION PERCHA');

	if ($tipo === 'rebate') {
		$cols = ['Ciudad', 'Canal', 'Categoría', 'Subcategoría', 'Marca', 'Rebate %', 'Actualizado por', 'Última Modificación'];
		foreach ($cols as $i => $titulo) $wb->celda($hoja, 1, $i + 1, $titulo, true);
		$fila = 2;
		foreach ($resultado['filas'] as $f) {
			$wb->celda($hoja, $fila, 1, $f['ciudad']);
			$wb->celda($hoja, $fila, 2, $f['canal']);
			$wb->celda($hoja, $fila, 3, $f['sector']);
			$wb->celda($hoja, $fila, 4, $f['categoria']);
			$wb->celda($hoja, $fila, 5, $f['marca']);
			$wb->celda($hoja, $fila, 6, (float) $f['rebate_pct'], false, 'pct'); // ya es fracción (0.025), 'pct' formatea como %.
			$wb->celda($hoja, $fila, 7, $f['actualizado_por_usuario'] ?? '');
			$wb->celda($hoja, $fila, 8, $f['updated_at']);
			$fila++;
		}
	} else {
		$cols = ['Marca', 'Participación %', 'Actualizado por', 'Última Modificación'];
		foreach ($cols as $i => $titulo) $wb->celda($hoja, 1, $i + 1, $titulo, true);
		$fila = 2;
		foreach ($resultado['filas'] as $f) {
			$wb->celda($hoja, $fila, 1, $f['marca']);
			$wb->celda($hoja, $fila, 2, ((float) $f['participacion_pct']) / 100, false, 'pct'); // acá SÍ se guarda como entero (55.00) -> se divide para que 'pct' lo muestre bien.
			$wb->celda($hoja, $fila, 3, $f['actualizado_por_usuario'] ?? '');
			$wb->celda($hoja, $fila, 4, $f['updated_at']);
			$fila++;
		}
	}

	$bin = $wb->generar();
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment; filename="'.$nombreBase.'.xlsx"');
	header('Content-Length: '.strlen($bin));
	echo $bin;
	exit;
}

// ---------- CSV (default) ----------
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$nombreBase.'.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8, para que Excel no rompa las tildes al abrir el CSV.

if ($tipo === 'rebate') {
	fputcsv($out, ['Ciudad', 'Canal', 'Categoría', 'Subcategoría', 'Marca', 'Rebate %', 'Actualizado por', 'Última Modificación']);
	foreach ($resultado['filas'] as $f) {
		fputcsv($out, [
			$f['ciudad'], $f['canal'], $f['sector'], $f['categoria'], $f['marca'],
			number_format((float) $f['rebate_pct'] * 100, 2).'%',
			$f['actualizado_por_usuario'] ?? '', $f['updated_at'],
		]);
	}
} else {
	fputcsv($out, ['Marca', 'Participación %', 'Actualizado por', 'Última Modificación']);
	foreach ($resultado['filas'] as $f) {
		fputcsv($out, [
			$f['marca'], number_format((float) $f['participacion_pct'], 2).'%',
			$f['actualizado_por_usuario'] ?? '', $f['updated_at'],
		]);
	}
}
fclose($out);
?>
