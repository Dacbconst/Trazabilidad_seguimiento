<?php
// Hoja "CUOTAS POR CAT -DISTRIBUIDORES" del Excel real de JW para canal
// Distribuidor (datos/LIQUIDACION DE ACUERDO COMERCIALES DISTRIBUIDORES Q2
// 2026.xlsx) — mismo propósito que exportar_cuota_categoria.php (canal
// Directa) pero con columnas/colores propios, así que va en un archivo aparte
// para no arriesgar el código de Directa ya probado. Incluido desde
// exportar_cuota_categoria.php cuando el usuario logueado es canal
// distribuidor; $mysqli, $usuarioId, $like, $mesIdx, $wb-helpers ($cl) ya
// están definidos por el archivo que hace el include.
//
// Layout, colores y fórmulas leídos EXACTOS del archivo real vía Excel COM
// (Interior.Color/Font.Color, BGR→RGB) el 2026-08-20, confirmados con datos
// reales fila por fila (no adivinados):
// - DISTRIBUIDOR = d.tipo_distribuidor (empresa distribuidora — el mismo
//   campo que ahora se muestra como "Distribuidor" en Registrar/el PDF, ver
//   functions.php obtener_acuerdo_detalle()).
// - CIUDAD = d.cedi (confirmado: valores como SANTO DOMINGO/RIOBAMBA calzan
//   con los `cedi` reales de repositorio_locales_supervisores_cliente para
//   canal DISTRIBUIDOR).
// - NOMBRE = d.pos_name (el "Local" de la Acta — mismo campo que CLIENTE en
//   la hoja de Directa).
// - CATEGORIA = l.sector (mismo campo que CATEGORIAS en Directa).
// - **Sin columnas CODIGO / RUC** (2026-08-20, pedido explícito): el
//   archivo real SÍ las tiene (confirmado leyendo D2/E2 vía Excel COM,
//   celdas propias sin fusionar), pero el usuario decidió no incluirlas acá
//   porque no hay fuente real de esos datos en la base
//   (repositorio_locales_supervisores_cliente no tiene columnas `ruc` ni
//   `codigo`) y prefiere no mostrar 2 columnas siempre vacías.
// - Sin columna CARTERA (no existe en el archivo real de Distribuidor).
// - Sin fila TOTAL al final (el archivo real no tiene una — confirmado:
//   UsedRange termina justo en la última fila de datos).
// - Bloque CUOTA (meses+total+rebate%+rebate$+rebate max) va SIN color de
//   fondo en las filas de datos (blanco), a diferencia de Directa que sí
//   usa un verde ahí — así está en el archivo real, no es un error de copia.
// - CUMPLIMIENTO real usa `=R/K` sin IFERROR; acá se envuelve en IFERROR por
//   seguridad (mismo criterio ya aplicado en la hoja de Directa) — el valor
//   mostrado es idéntico cuando K≠0, solo evita #DIV/0! si una Acta quedara
//   con cuota en 0.

$stmtD = $mysqli->prepare(
	"SELECT d.tipo_distribuidor AS distribuidor, d.cedi AS ciudad, d.pos_name AS cliente, l.sector, l.rebate_pct, l.valores_mensuales
	 FROM repositorio_acuerdos a
	 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
	 JOIN repositorio_acuerdo_lineas l ON l.acuerdo_id = a.id AND l.tipo = 'meta_compra'
	 WHERE a.estado NOT IN ('borrador', 'anulado')
	   AND a.creado_por = ?
	   AND d.pos_name LIKE ?
	   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
	   AND (? = 0 OR a.anio = ?)
	   AND d.canal = 'DISTRIBUIDOR'
	 GROUP BY a.id, l.id"
);
if (!$stmtD) {
	http_response_code(500);
	echo 'Error preparando la consulta.';
	exit;
}
$stmtD->bind_param('isiiiii', $usuarioId, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
$stmtD->execute();
$filasD = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtD->close();

$mesesLargos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

$filasFinalD = [];
$mesesPresentesD = [];
foreach ($filasD as $f) {
	if ($f['sector'] === null || trim($f['sector']) === '') continue;
	$valores = json_decode($f['valores_mensuales'] ?? '{}', true) ?: [];
	$valoresPorMes = [];
	foreach ($valores as $mesIdxFila => $valor) {
		$mi = (int) $mesIdxFila;
		$mesesPresentesD[$mi] = true;
		$valoresPorMes[$mi] = (float) $valor;
	}
	$filasFinalD[] = [
		'distribuidor' => $f['distribuidor'] ?? '',
		'ciudad'       => $f['ciudad'] ?? '',
		'cliente'      => $f['cliente'],
		'sector'       => $f['sector'],
		'rebate_pct'   => (float) $f['rebate_pct'],
		'valores'      => $valoresPorMes,
	];
}

ksort($mesesPresentesD, SORT_NUMERIC);
$mesesColsD = array_keys($mesesPresentesD);
$MD = count($mesesColsD);
if ($MD === 0) { $MD = 1; $mesesColsD = [0]; }

usort($filasFinalD, function ($a, $b) {
	$c = strcmp($a['distribuidor'], $b['distribuidor']);
	if ($c !== 0) return $c;
	$c = strcmp($a['cliente'], $b['cliente']);
	return $c !== 0 ? $c : strcmp($a['sector'], $b['sector']);
});

// ---------- Layout de columnas ----------
$colDistribuidor = 1; $colCiudad = 2; $colNombre = 3; $colCategoria = 4; $colConcat = 5;
$colCuotaInicio = 6;
$colCuotaTotal = $colCuotaInicio + $MD;
$colRebatePct = $colCuotaTotal + 1;
$colRebateDolar = $colCuotaTotal + 2;
$colRebateMax110 = $colCuotaTotal + 3;
$colVentaInicio = $colCuotaTotal + 4;
$colVentaTotal = $colVentaInicio + $MD;
$colCumplimiento = $colVentaTotal + 1;
$colGanaCategoria = $colCumplimiento + 1;
$colGanaTotal = $colGanaCategoria + 1;
$colPreRebate = $colGanaTotal + 1;
$colRebateRealVol = $colPreRebate + 1;
$colNovedades = $colRebateRealVol + 1;

$wbD = new XlsxWriter();
$sD1 = $wbD->agregarHoja('CUOTAS POR CAT -DISTRIBUIDORES');
$sD2 = $wbD->agregarHoja('CUOTA TOTAL');

$bgEncD = '747474'; $fontEncD = 'FFFFFF'; $bgVentaD = '000000'; $fontVentaD = 'FFFFFF'; $bgClienteD = 'F2CEEF';

$trimestreD = intdiv($mesesColsD[0], 3) + 1;
$tituloCuotaGrupo = 'CUOTAS Q'.$trimestreD;
$tituloCuotaCol = 'CUOTA Q'.$trimestreD;
$tituloVentaGrupo = 'VENTA Q'.$trimestreD;
$tituloVentaCol = 'TOTAL VENTA Q'.$trimestreD;

// ---------- Encabezados fila 2 ----------
$filaEncD = 2;
$wbD->celda($sD1, $filaEncD, $colDistribuidor, 'DISTRIBUIDOR', true, null, $bgEncD, $fontEncD);
$wbD->celda($sD1, $filaEncD, $colCiudad, 'CIUDAD', true, null, $bgEncD, $fontEncD);
$wbD->celda($sD1, $filaEncD, $colNombre, 'NOMBRE', true, null, $bgEncD, $fontEncD);
$wbD->celda($sD1, $filaEncD, $colCategoria, 'CATEGORIA', true, null, $bgEncD, $fontEncD);
$wbD->celda($sD1, $filaEncD, $colConcat, 'CONCAT 1', true, null, $bgEncD, $fontEncD);
foreach ($mesesColsD as $i => $mi) {
	$wbD->celda($sD1, $filaEncD, $colCuotaInicio + $i, mb_strtoupper($mesesLargos[$mi]), true, null, $bgEncD, $fontEncD);
}
$wbD->celda($sD1, $filaEncD, $colCuotaTotal, $tituloCuotaCol, true, null, $bgEncD, $fontEncD);
$wbD->celda($sD1, $filaEncD, $colRebatePct, 'REBATE', true, null, $bgEncD, $fontEncD);
$wbD->celda($sD1, $filaEncD, $colRebateDolar, 'REBATE $', true, null, $bgEncD, $fontEncD);
$wbD->celda($sD1, $filaEncD, $colRebateMax110, 'REBATE MAXIMO 110%', true, null, $bgEncD, $fontEncD);
foreach ($mesesColsD as $i => $mi) {
	$wbD->celda($sD1, $filaEncD, $colVentaInicio + $i, mb_strtoupper($mesesLargos[$mi]), true, null, $bgVentaD, $fontVentaD);
}
$wbD->celda($sD1, $filaEncD, $colVentaTotal, $tituloVentaCol, true, null, $bgVentaD, $fontVentaD);
$wbD->celda($sD1, $filaEncD, $colCumplimiento, 'CUMPLIMIENTO', true, null, $bgVentaD, $fontVentaD);
$wbD->celda($sD1, $filaEncD, $colGanaCategoria, 'GANA POR CATEGORÍA', true, null, $bgVentaD, $fontVentaD);
$wbD->celda($sD1, $filaEncD, $colGanaTotal, 'GANA TOTAL Q', true, null, $bgVentaD, $fontVentaD);
$wbD->celda($sD1, $filaEncD, $colPreRebate, 'PRE REBATE', true, null, $bgVentaD, $fontVentaD);
$wbD->celda($sD1, $filaEncD, $colRebateRealVol, 'REBATE REAL VOL', true, null, $bgVentaD, $fontVentaD);
$wbD->celda($sD1, $filaEncD, $colNovedades, 'NOVEDADES', true, null, $bgVentaD, $fontVentaD);

// ---------- Fila 1: títulos de grupo fusionados ----------
$wbD->celda($sD1, 1, $colCuotaInicio, $tituloCuotaGrupo, true, null, $bgEncD, $fontEncD);
if ($MD > 1) {
	$wbD->combinarCeldas($sD1, XlsxWriter::colLetra($colCuotaInicio).'1:'.XlsxWriter::colLetra($colCuotaInicio + $MD - 1).'1');
}
// El merge real (O1:R1) cubre los meses de venta Y la columna "TOTAL VENTA",
// a diferencia del bloque CUOTA de arriba (que NO incluye su columna total).
$wbD->celda($sD1, 1, $colVentaInicio, $tituloVentaGrupo, true, null, $bgVentaD, $fontVentaD);
$wbD->combinarCeldas($sD1, XlsxWriter::colLetra($colVentaInicio).'1:'.XlsxWriter::colLetra($colVentaTotal).'1');

// Mismo bug ya encontrado y corregido en la hoja de Directa (ver
// exportar_cuota_categoria.php, 2026-08-20): las columnas de fila 1 que no
// caen dentro de ninguna fusión no tienen NINGUNA celda — quedan sin pintar.
foreach ([$colDistribuidor, $colCiudad, $colNombre, $colCategoria, $colConcat, $colCuotaTotal, $colRebatePct, $colRebateDolar, $colRebateMax110] as $c) {
	$wbD->celda($sD1, 1, $c, '', false, null, $bgEncD, $fontEncD);
}
foreach ([$colCumplimiento, $colGanaCategoria, $colGanaTotal, $colPreRebate, $colRebateRealVol, $colNovedades] as $c) {
	$wbD->celda($sD1, 1, $c, '', false, null, $bgVentaD, $fontVentaD);
}

// ---------- Filas de datos ----------
$primeraFilaDatosD = $filaEncD + 1;
$filaD = $primeraFilaDatosD;
$clientesVistosD = [];
foreach ($filasFinalD as $g) {
	$wbD->celda($sD1, $filaD, $colDistribuidor, $g['distribuidor']);
	$wbD->celda($sD1, $filaD, $colCiudad, $g['ciudad']);
	$wbD->celda($sD1, $filaD, $colNombre, $g['cliente'], false, null, $bgClienteD, '000000');
	$wbD->celda($sD1, $filaD, $colCategoria, $g['sector']);
	$wbD->formula($sD1, $filaD, $colConcat, 'CONCATENATE('.XlsxWriter::colLetra($colNombre).$filaD.','.XlsxWriter::colLetra($colCategoria).$filaD.')');
	foreach ($mesesColsD as $i => $mi) {
		$wbD->celda($sD1, $filaD, $colCuotaInicio + $i, round($g['valores'][$mi] ?? 0, 2), false, 'money');
	}
	$rangoCuotaD = XlsxWriter::colLetra($colCuotaInicio).$filaD.':'.XlsxWriter::colLetra($colCuotaInicio + $MD - 1).$filaD;
	$wbD->formula($sD1, $filaD, $colCuotaTotal, 'SUM('.$rangoCuotaD.')', false, 'money');
	$wbD->celda($sD1, $filaD, $colRebatePct, round($g['rebate_pct'], 4), false, 'pct');
	$wbD->formula($sD1, $filaD, $colRebateDolar, XlsxWriter::colLetra($colCuotaTotal).$filaD.'*'.XlsxWriter::colLetra($colRebatePct).$filaD, false, 'money');
	$wbD->formula($sD1, $filaD, $colRebateMax110, '('.XlsxWriter::colLetra($colCuotaTotal).$filaD.'*1.1)*'.XlsxWriter::colLetra($colRebatePct).$filaD, false, 'money');
	for ($i = 0; $i < $MD; $i++) {
		$wbD->celda($sD1, $filaD, $colVentaInicio + $i, '');
	}
	$rangoVentaD = XlsxWriter::colLetra($colVentaInicio).$filaD.':'.XlsxWriter::colLetra($colVentaInicio + $MD - 1).$filaD;
	$wbD->formula($sD1, $filaD, $colVentaTotal, 'SUM('.$rangoVentaD.')', false, 'money');
	$wbD->formula($sD1, $filaD, $colCumplimiento, 'IFERROR('.XlsxWriter::colLetra($colVentaTotal).$filaD.'/'.XlsxWriter::colLetra($colCuotaTotal).$filaD.',0)', false, 'pct');
	$wbD->formula($sD1, $filaD, $colGanaCategoria, 'IF('.XlsxWriter::colLetra($colCumplimiento).$filaD.'>=80%,"GANA","NO GANA")');
	$wbD->celda($sD1, $filaD, $colNovedades, '');

	$claveCliente = $g['cliente'];
	if (!isset($clientesVistosD[$claveCliente])) {
		$clientesVistosD[$claveCliente] = ['distribuidor' => $g['distribuidor'], 'cliente' => $g['cliente']];
	}
	$filaD++;
}
$ultimaFilaDatosD = $filaD - 1;

// ---------- Hoja "CUOTA TOTAL" (un renglón por NOMBRE único) ----------
$filaEncD2 = 3;
$wbD->celda($sD2, $filaEncD2, 1, 'DISTRIBUIDOR', true);
$wbD->celda($sD2, $filaEncD2, 2, 'NOMBRE', true);
$wbD->celda($sD2, $filaEncD2, 3, 'Suma de '.$tituloCuotaCol, true);
$wbD->celda($sD2, $filaEncD2, 4, 'Suma de '.$tituloVentaCol, true);
$wbD->celda($sD2, $filaEncD2, 5, 'Cumplimiento', true);
$wbD->celda($sD2, $filaEncD2, 6, 'Gana', true);

$filaD2 = $filaEncD2 + 1;
$primeraFilaD2 = $filaD2;
if ($ultimaFilaDatosD >= $primeraFilaDatosD) {
	foreach ($clientesVistosD as $cv) {
		$wbD->celda($sD2, $filaD2, 1, $cv['distribuidor']);
		$wbD->celda($sD2, $filaD2, 2, $cv['cliente']);
		$rangoNombreS1 = "'CUOTAS POR CAT -DISTRIBUIDORES'!\$".XlsxWriter::colLetra($colNombre).'$'.$primeraFilaDatosD.':$'.XlsxWriter::colLetra($colNombre).'$'.$ultimaFilaDatosD;
		$rangoCuotaTotalS1 = "'CUOTAS POR CAT -DISTRIBUIDORES'!\$".XlsxWriter::colLetra($colCuotaTotal).'$'.$primeraFilaDatosD.':$'.XlsxWriter::colLetra($colCuotaTotal).'$'.$ultimaFilaDatosD;
		$rangoVentaTotalS1 = "'CUOTAS POR CAT -DISTRIBUIDORES'!\$".XlsxWriter::colLetra($colVentaTotal).'$'.$primeraFilaDatosD.':$'.XlsxWriter::colLetra($colVentaTotal).'$'.$ultimaFilaDatosD;
		$wbD->formula($sD2, $filaD2, 3, 'SUMIF('.$rangoNombreS1.',B'.$filaD2.','.$rangoCuotaTotalS1.')', false, 'money');
		$wbD->formula($sD2, $filaD2, 4, 'SUMIF('.$rangoNombreS1.',B'.$filaD2.','.$rangoVentaTotalS1.')', false, 'money');
		$wbD->formula($sD2, $filaD2, 5, 'IFERROR(D'.$filaD2.'/C'.$filaD2.',0)', false, 'pct');
		$wbD->formula($sD2, $filaD2, 6, 'IF(E'.$filaD2.'>=99.99%,"GANA","NO GANA")');
		$filaD2++;
	}
}
$ultimaFilaD2 = $filaD2 - 1;

// ---------- GANA TOTAL / PRE REBATE / REBATE REAL VOL (necesitan CUOTA TOTAL ya armada) ----------
if ($ultimaFilaD2 >= $primeraFilaD2) {
	$rangoBuscarvD = "'CUOTA TOTAL'!\$B\$".$primeraFilaD2.':$F$'.$ultimaFilaD2;
	for ($filaD = $primeraFilaDatosD; $filaD <= $ultimaFilaDatosD; $filaD++) {
		$wbD->formula($sD1, $filaD, $colGanaTotal, 'VLOOKUP('.XlsxWriter::colLetra($colNombre).$filaD.','.$rangoBuscarvD.',5,FALSE)');
		$wbD->formula($sD1, $filaD, $colPreRebate,
			'IF(AND('.XlsxWriter::colLetra($colGanaTotal).$filaD.'="GANA",'.XlsxWriter::colLetra($colGanaCategoria).$filaD.'="GANA"),'.
			XlsxWriter::colLetra($colVentaTotal).$filaD.'*'.XlsxWriter::colLetra($colRebatePct).$filaD.',0)', false, 'money');
		$wbD->formula($sD1, $filaD, $colRebateRealVol,
			'IF('.XlsxWriter::colLetra($colPreRebate).$filaD.'<='.XlsxWriter::colLetra($colRebateMax110).$filaD.','.XlsxWriter::colLetra($colPreRebate).$filaD.','.XlsxWriter::colLetra($colRebateMax110).$filaD.')',
			false, 'money');
	}
}

$binD = $wbD->generar();
$nombreArchivoD = 'CuotaCategoria_Distribuidor_'.date('Y-m-d').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$nombreArchivoD.'"');
header('Content-Length: '.strlen($binD));
echo $binD;
exit;
?>
