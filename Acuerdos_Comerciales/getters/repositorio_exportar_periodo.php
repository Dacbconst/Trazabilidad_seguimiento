<?php
// Exporta lo cargado en Cuotas Trimestrales o Acuerdo Completo de un canal y período, con la misma estructura del formato de subida. Excluye solo lo descartado; con contar=1 responde solo el conteo.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../includes/xlsx_writer.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo 'No autorizado.';
	exit;
}

$tipo      = $_GET['tipo'] ?? '';
$canal     = $_GET['canal'] ?? '';
$trimestre = (int) ($_GET['trimestre'] ?? 0);
$anio      = (int) ($_GET['anio'] ?? 0);
$soloContar = !empty($_GET['contar']);
if (!in_array($tipo, ['cuotas', 'acuerdo_completo'], true) || !in_array($canal, ['directo', 'distribuidor'], true) || $trimestre < 1 || $trimestre > 4 || $anio < 2000) {
	http_response_code(400);
	echo 'Parámetros inválidos.';
	exit;
}

$tabla = $tipo === 'cuotas' ? 'repositorio_cuota_cliente' : 'repositorio_acuerdo_completo_linea';
$stmt = $mysqli->prepare("SELECT * FROM $tabla WHERE canal = ? AND trimestre = ? AND anio = ? AND estado <> 'descartada' ORDER BY cliente_excel, sector, id");
if (!$stmt) {
	http_response_code(500);
	echo 'No se pudo consultar.';
	exit;
}
$stmt->bind_param('sii', $canal, $trimestre, $anio);
$stmt->execute();
$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($soloContar) {
	$clientes = [];
	foreach ($filas as $f) $clientes[$f['pos_id'].'|'.$f['cliente_excel']] = true;
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['ok' => true, 'filas' => count($filas), 'clientes' => count($clientes)]);
	exit;
}
if (!$filas) {
	http_response_code(404);
	echo 'No hay datos para este período.';
	exit;
}

$nombresMes = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
$mesInicio = ($trimestre - 1) * 3;
$meses = [$nombresMes[$mesInicio], $nombresMes[$mesInicio + 1], $nombresMes[$mesInicio + 2]];
$formatoValor = $canal === 'distribuidor' ? 'numero' : 'money'; // Distribuidor mide en Cajas, sin signo $
$valoresDe = function ($json) use ($mesInicio) {
	$v = $json !== null ? json_decode($json, true) : [];
	$v = is_array($v) ? $v : [];
	$r = [];
	for ($i = 0; $i < 3; $i++) $r[] = isset($v[(string) ($mesInicio + $i)]) ? (float) $v[(string) ($mesInicio + $i)] : null;
	return $r;
};
$identidadDe = function ($f, $sector, $subcategoria, $marca) use ($canal) {
	return $canal === 'directo'
		? [$f['usuario_excel'], $f['cedi_excel'], $f['cliente_excel'], $f['plan'], $sector, $subcategoria, $marca]
		: [$f['usuario_excel'], $f['plan'], $f['cedi_excel'], $f['cliente_excel'], $f['codigo'], $f['ruc'], $sector, $subcategoria, $marca];
};
$titulosIdentidad = $canal === 'directo'
	? ['USUARIO', 'CEDI', 'CLIENTE', 'PLAN', 'CATEGORIAS', 'SUBCATEGORIA', 'MARCA']
	: ['USUARIO', 'DISTRIBUIDOR', 'CIUDAD', 'NOMBRE', 'CODIGO', 'RUC', 'CATEGORIA', 'SUBCATEGORIA', 'MARCA'];
$nIdent = count($titulosIdentidad);
$etiquetaCanal = $canal === 'directo' ? 'Directo' : 'Distribuidor';

$wb = new XlsxWriter();
$hoja = $wb->agregarHoja($tipo === 'cuotas' ? 'CUOTAS' : 'ACUERDO COMPLETO');

if ($tipo === 'cuotas') {
	$cols = array_merge($titulosIdentidad, $meses);
	foreach ($cols as $i => $t) $wb->celda($hoja, 1, $i + 1, $t, true);
	foreach ($filas as $r => $f) {
		$vals = array_merge($identidadDe($f, $f['sector'], $f['subcategoria'] ?? '', $f['marca'] ?? ''), $valoresDe($f['valores_mensuales']));
		foreach ($vals as $c => $v) {
			$esMes = $c >= $nIdent;
			$wb->celda($hoja, 2 + $r, $c + 1, ($v === null || $v === '') ? '-' : $v, false, ($v === null || $v === '') ? null : ($esMes ? $formatoValor : null)); // campo vacío = guion
		}
	}
	$nombreBase = 'Cuotas_'.$etiquetaCanal;
} else {
	$colMeta = $nIdent + 1;
	$colCab = $colMeta + 3;
	$colRuma = $colCab + 3;
	$colCant = $colRuma + 1;
	$colPercha = $colCant + 1;
	foreach ($titulosIdentidad as $i => $t) $wb->celda($hoja, 2, $i + 1, $t, true);
	foreach ($meses as $i => $m) { $wb->celda($hoja, 2, $colMeta + $i, $m, true); $wb->celda($hoja, 2, $colCab + $i, $m, true); $wb->celda($hoja, 2, $colPercha + $i, $m, true); }
	$wb->celda($hoja, 2, $colRuma, 'RUMA', true);
	$wb->celda($hoja, 2, $colCant, 'CANTIDAD', true);
	$wb->celda($hoja, 1, $colMeta, 'META DE COMPRAS', true);
	$wb->celda($hoja, 1, $colCab, 'CABECERA', true);
	$wb->celda($hoja, 1, $colRuma, 'RUMA', true);
	$wb->celda($hoja, 1, $colCant, 'PERCHA', true);
	$wb->combinarCeldas($hoja, XlsxWriter::colLetra($colMeta).'1:'.XlsxWriter::colLetra($colMeta + 2).'1');
	$wb->combinarCeldas($hoja, XlsxWriter::colLetra($colCab).'1:'.XlsxWriter::colLetra($colCab + 2).'1');
	$wb->combinarCeldas($hoja, XlsxWriter::colLetra($colCant).'1:'.XlsxWriter::colLetra($colPercha + 2).'1');

	// Una fila por cliente + categoría + marca: la Meta trae el sector real; Cabecera/Ruma/Percha se guardan con sector vacío y se unen por categoría + marca.
	$grupos = [];
	foreach ($filas as $f) {
		$clave = $f['pos_id'].'|'.$f['cliente_excel'].'|'.$f['categoria'].'|'.$f['marca'];
		if (!isset($grupos[$clave])) $grupos[$clave] = ['base' => $f, 'sector' => '', 'meta' => null, 'cab' => null, 'ruma' => null, 'percha' => null];
		if ($f['tipo'] === 'meta_compra') { $grupos[$clave]['sector'] = $f['sector']; $grupos[$clave]['meta'] = $f; $grupos[$clave]['base'] = $f; }
		elseif ($f['tipo'] === 'cabecera') $grupos[$clave]['cab'] = $f;
		elseif ($f['tipo'] === 'ruma') $grupos[$clave]['ruma'] = $f;
		else $grupos[$clave]['percha'] = $f;
	}
	$r = 0;
	foreach ($grupos as $g) {
		$f = $g['base'];
		$sector = $g['sector'] !== '' ? $g['sector'] : ($f['categoria'] === 'MIXTA' ? 'MIXTA' : '');
		$celdas = [];
		foreach ($identidadDe($f, $sector, $f['categoria'], $f['marca']) as $i => $v) $celdas[$i + 1] = [$v, null];
		if ($g['meta']) foreach ($valoresDe($g['meta']['valores_mensuales']) as $i => $v) $celdas[$colMeta + $i] = [$v, $formatoValor];
		if ($g['cab']) foreach ($valoresDe($g['cab']['valores_mensuales']) as $i => $v) $celdas[$colCab + $i] = [$v, $formatoValor];
		if ($g['ruma'] && $g['ruma']['valor_mensual_unico'] !== null) $celdas[$colRuma] = [(float) $g['ruma']['valor_mensual_unico'], $formatoValor];
		if ($g['percha']) {
			if ($g['percha']['cantidad_max_percha'] !== null) $celdas[$colCant] = [(int) $g['percha']['cantidad_max_percha'], null];
			foreach ($valoresDe($g['percha']['valores_mensuales']) as $i => $v) $celdas[$colPercha + $i] = [$v, $formatoValor];
		}
		for ($col = 1; $col <= $colPercha + 2; $col++) {
			$par = $celdas[$col] ?? [null, null];
			$vacio = $par[0] === null || $par[0] === '';
			$wb->celda($hoja, 3 + $r, $col, $vacio ? '-' : $par[0], false, $vacio ? null : $par[1]); // campo vacío = guion
		}
		$r++;
	}
	$nombreBase = 'AcuerdoCompleto_'.$etiquetaCanal;
}

$bin = $wb->generar();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$nombreBase.'_Q'.$trimestre.'_'.$anio.'.xlsx"');
header('Content-Length: '.strlen($bin));
echo $bin;
?>
