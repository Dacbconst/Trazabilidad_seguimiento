<?php
// Genera el .xlsx "CUOTA CLIENTE - CATEGORÍA" de JW, canal Directa (Distribuidor
// delega en exportar_cuota_categoria_distribuidor.php). Rebate % congelado, una fila por línea, nunca agrupada.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/xlsx_writer.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();

// Solo superdesarrollador; un desarrollador normal ya no ve el botón en Historial.
if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo 'No autorizado.';
	exit;
}

$usuarioId = $_SESSION['user_id'] ?? null;
$busqueda  = trim($_GET['q'] ?? '');
$trimestre = (int) ($_GET['trimestre'] ?? 0);
$anio      = (int) ($_GET['anio'] ?? 0);
$like      = '%'.$busqueda.'%';

// Mismo filtro exacto que listar_historial_acuerdos() (trimestreABounds()).
$bounds          = trimestreABounds($trimestre);
$trimestreActivo = $bounds ? 1 : 0;
$mesInicioFiltro = $bounds ? $bounds[0] : -1;
$mesFinFiltro    = $bounds ? $bounds[1] : -1;

if (!$usuarioId) {
	http_response_code(403);
	echo 'Sesión inválida.';
	exit;
}

// Exige trimestre Y año puntuales: esta hoja replica un archivo de un solo
// trimestre, "Todos los períodos"/"Todos los años" mezclaría líneas de Actas distintas.
if (!$trimestreActivo || !$anio) {
	http_response_code(400);
	echo 'Elige un trimestre y un año específicos en el filtro de período antes de descargar el Excel.';
	exit;
}

// Formato elegido explícito en el picker de Historial; sin valor reconocido cae a Directo.
$canalExport = ($_GET['canal'] ?? '') === 'distribuidor' ? 'distribuidor' : 'directo';
if ($canalExport === 'distribuidor') {
	require __DIR__.'/exportar_cuota_categoria_distribuidor.php';
	exit;
}

// GROUP BY a.id, l.id colapsa duplicados de pos_id sin perder líneas reales.
// Sin filtro de creado_por: exporta Actas de todos los asesores del canal (u.usuario identifica cada línea).
$stmt = $mysqli->prepare(
	"SELECT u.usuario AS ejecutivo, d.pos_name AS cliente, d.canal, l.sector, l.categoria, l.marca, l.rebate_pct, l.valores_mensuales
	 FROM repositorio_acuerdos a
	 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
	 JOIN repositorio_acuerdo_lineas l ON l.acuerdo_id = a.id AND l.tipo = 'meta_compra'
	 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
	 WHERE a.estado NOT IN ('borrador', 'anulado')
	   AND a.acta_firmada_archivo IS NOT NULL
	   AND d.pos_name LIKE ?
	   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
	   AND (? = 0 OR a.anio = ?)
	   AND d.canal <> 'DISTRIBUIDOR'
	 GROUP BY a.id, l.id"
);
if (!$stmt) {
	http_response_code(500);
	echo 'Error preparando la consulta.';
	exit;
}
$stmt->bind_param('siiiii', $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
$stmt->execute();
$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$mesesLargos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

// ---------- Una fila por línea real (sin agrupar/sumar, ver nota arriba) ----------
$filasFinal = [];
$mesesPresentes = [];
foreach ($filas as $f) {
	if ($f['sector'] === null || trim($f['sector']) === '') continue; // sin Sector no se puede armar la fila (Actas viejas antes del cambio)
	$valores = json_decode($f['valores_mensuales'] ?? '{}', true) ?: [];
	$valoresPorMes = [];
	foreach ($valores as $mesIdxFila => $valor) {
		$mi = (int) $mesIdxFila;
		$mesesPresentes[$mi] = true;
		$valoresPorMes[$mi] = (float) $valor;
	}
	$filasFinal[] = [
		'ejecutivo'  => $f['ejecutivo'] ?? '',
		'cliente'    => $f['cliente'],
		'plan'       => $f['canal'] ?? '',
		'sector'     => $f['sector'],
		// Categoría/Marca de la cascada (etiquetadas Subcategoría/Marca en Registrar); pueden venir vacías.
		'categoria'  => $f['categoria'] ?? '',
		'marca'      => $f['marca'] ?? '',
		'rebate_pct' => (float) $f['rebate_pct'], // el de ESTA línea, nunca mezclado con otra
		'valores'    => $valoresPorMes,
	];
}

ksort($mesesPresentes, SORT_NUMERIC);
$mesesCols = array_keys($mesesPresentes); // ej. [3,4,5] para Abril-Mayo-Junio
$M = count($mesesCols);

if ($M === 0) {
	$M = 1; // nada que exportar, evita división por cero; sheet sale con headers nomás.
	$mesesCols = [0];
}

usort($filasFinal, function ($a, $b) {
	$c = strcmp($a['cliente'], $b['cliente']);
	return $c !== 0 ? $c : strcmp($a['sector'], $b['sector']);
});

// ---------- Layout de columnas (dinámico según cuántos meses hay) ----------
// SUBCATEGORIA/MARCA van a la derecha de CATEGORIAS/PLAN, antes de CONCAT.
$colCedi = 1; $colCliente = 2; $colPlan = 3; $colCategorias = 4;
$colSubcategoria = 5; $colMarca = 6; $colConcat = 7;
$colCuotaInicio = 8;
$colTotalQ2 = $colCuotaInicio + $M;
$colRebatePct = $colTotalQ2 + 1;
$colRebateDolar = $colTotalQ2 + 2;
$colRebateMax110 = $colTotalQ2 + 3;
$colVentaInicio = $colTotalQ2 + 4;
$colCartera = $colVentaInicio + $M;
$colVentaTotal = $colCartera + 1;
$colCumplimiento = $colVentaTotal + 1;
$colGanaCategoria = $colCumplimiento + 1;
$colGanaTotal = $colGanaCategoria + 1;
$colPreRebate = $colGanaTotal + 1;
$colRebateRealVol = $colPreRebate + 1;

$L = ['XlsxWriter', 'colLetra']; // atajo para XlsxWriter::colLetra()
$cl = function ($n) use ($L) { return call_user_func($L, $n); };

$wb = new XlsxWriter();
$s1 = $wb->agregarHoja('CUOTA CLIENTE - CATEGORÍA');
// CONCAT es fórmula: el autofit no puede medir su resultado real, se fuerza un ancho mínimo.
$wb->anchoMinimo($s1, $colConcat, 40);
$s2 = $wb->agregarHoja('CUOTA TOTAL');

// ---------- Hoja 1: encabezados (fila 2, como el archivo real) ----------
// Colores exactos leídos del archivo real vía Excel COM (BGR->RGB).
$bgEncabezado = 'C0E6F5'; $bgVenta = '61CBF3'; $fontVenta = 'FF0000';
$bgCartera = 'FFC000'; $bgResultado = 'B5E6A2'; $bgRebateReal = 'FFFF00';

// "Qx" dinámico según el primer mes real de las Actas incluidas, nunca fijo en "Q2".
$trimestre = intdiv($mesesCols[0], 3) + 1;
$tituloCuota = 'TOTAL Q'.$trimestre;
$tituloVenta = 'VENTA Q'.$trimestre;

$filaEnc = 2;
$wb->celda($s1, $filaEnc, $colCedi, 'CEDI', true, null, $bgEncabezado, '000000');
$wb->celda($s1, $filaEnc, $colCliente, 'CLIENTE', true, null, $bgEncabezado, '000000');
$wb->celda($s1, $filaEnc, $colPlan, 'PLAN', true, null, $bgEncabezado, '000000');
$wb->celda($s1, $filaEnc, $colCategorias, 'CATEGORIAS', true, null, $bgEncabezado, '000000');
$wb->celda($s1, $filaEnc, $colSubcategoria, 'SUBCATEGORIA', true, null, $bgEncabezado, '000000');
$wb->celda($s1, $filaEnc, $colMarca, 'MARCA', true, null, $bgEncabezado, '000000');
$wb->celda($s1, $filaEnc, $colConcat, 'CONCAT', true, null, $bgEncabezado, '000000');
foreach ($mesesCols as $i => $mi) {
	$wb->celda($s1, $filaEnc, $colCuotaInicio + $i, mb_strtoupper($mesesLargos[$mi]), true, null, $bgEncabezado, '000000');
}
$wb->celda($s1, $filaEnc, $colTotalQ2, $tituloCuota, true, null, $bgEncabezado, '000000');
$wb->celda($s1, $filaEnc, $colRebatePct, 'REBATE A APLICAR %', true, null, $bgEncabezado, '000000');
$wb->celda($s1, $filaEnc, $colRebateDolar, 'REBATE $', true, null, $bgEncabezado, '000000');
$wb->celda($s1, $filaEnc, $colRebateMax110, 'REBATE MAXIMO 110%', true, null, $bgEncabezado, '000000');

$wb->celda($s1, 1, $colVentaInicio, $tituloVenta, true, null, $bgVenta, $fontVenta);
if ($M > 1) {
	$wb->combinarCeldas($s1, $cl($colVentaInicio).'1:'.$cl($colVentaInicio + $M - 1).'1');
}
foreach ($mesesCols as $i => $mi) {
	$wb->celda($s1, $filaEnc, $colVentaInicio + $i, mb_strtoupper($mesesLargos[$mi]), true, null, $bgVenta, $fontVenta);
}
$wb->celda($s1, $filaEnc, $colCartera, 'CARTERA', false, null, $bgCartera, '000000');
$wb->celda($s1, $filaEnc, $colVentaTotal, $tituloVenta, true, null, $bgResultado, '000000');
$wb->celda($s1, $filaEnc, $colCumplimiento, 'CUMPLIMIENTO', true, null, $bgResultado, '000000');
$wb->celda($s1, $filaEnc, $colGanaCategoria, 'GANA POR CATEGORÍA', true, null, $bgResultado, '000000');
$wb->celda($s1, $filaEnc, $colGanaTotal, 'GANA TOTAL', true, null, $bgResultado, '000000');
$wb->celda($s1, $filaEnc, $colPreRebate, 'PRE REBATE', true, null, $bgResultado, '000000');
$wb->celda($s1, $filaEnc, $colRebateRealVol, 'REBATE REAL VOL', true, null, $bgRebateReal, '000000');

// Columnas de fila 1 fuera de la fusión de VENTA necesitan celda propia (vacía,
// mismo color que la fila 2 de abajo) o quedan sin pintar/sin borde.
$wb->celda($s1, 1, $colCedi, '', false, null, $bgEncabezado, '000000');
$wb->celda($s1, 1, $colCliente, '', false, null, $bgEncabezado, '000000');
$wb->celda($s1, 1, $colPlan, '', false, null, $bgEncabezado, '000000');
$wb->celda($s1, 1, $colCategorias, '', false, null, $bgEncabezado, '000000');
$wb->celda($s1, 1, $colSubcategoria, '', false, null, $bgEncabezado, '000000');
$wb->celda($s1, 1, $colMarca, '', false, null, $bgEncabezado, '000000');
$wb->celda($s1, 1, $colConcat, '', false, null, $bgEncabezado, '000000');
foreach ($mesesCols as $i => $mi) {
	$wb->celda($s1, 1, $colCuotaInicio + $i, '', false, null, $bgEncabezado, '000000');
}
$wb->celda($s1, 1, $colTotalQ2, '', false, null, $bgEncabezado, '000000');
$wb->celda($s1, 1, $colRebatePct, '', false, null, $bgEncabezado, '000000');
$wb->celda($s1, 1, $colRebateDolar, '', false, null, $bgEncabezado, '000000');
$wb->celda($s1, 1, $colRebateMax110, '', false, null, $bgEncabezado, '000000');
$wb->celda($s1, 1, $colCartera, '', false, null, $bgCartera, '000000');
$wb->celda($s1, 1, $colVentaTotal, '', false, null, $bgResultado, '000000');
$wb->celda($s1, 1, $colCumplimiento, '', false, null, $bgResultado, '000000');
$wb->celda($s1, 1, $colGanaCategoria, '', false, null, $bgResultado, '000000');
$wb->celda($s1, 1, $colGanaTotal, '', false, null, $bgResultado, '000000');
$wb->celda($s1, 1, $colPreRebate, '', false, null, $bgResultado, '000000');
$wb->celda($s1, 1, $colRebateRealVol, '', false, null, $bgRebateReal, '000000');

// ---------- Hoja 1: filas de datos ----------
// Colores de datos leídos del archivo real: CLIENTE rosa, bloque Cuota+Total+Rebate% verde.
$bgClienteDato = 'F2CEEF'; $bgCuotaDato = '92D050';

$primeraFilaDatos = $filaEnc + 1;
$fila = $primeraFilaDatos;
$clientesVistos = []; // para armar la hoja CUOTA TOTAL (únicos), en orden de aparición
foreach ($filasFinal as $g) {
	$wb->celda($s1, $fila, $colCedi, $g['ejecutivo']);
	$wb->celda($s1, $fila, $colCliente, $g['cliente'], false, null, $bgClienteDato, '000000');
	// PLAN = canal del cliente en el maestro (COBERTURA/MAYORISTA/AUTOSERVICIO),
	// ver nota arriba — confirmado con el usuario 2026-08-28.
	$wb->celda($s1, $fila, $colPlan, $g['plan']);
	$wb->celda($s1, $fila, $colCategorias, $g['sector']);
	$wb->celda($s1, $fila, $colSubcategoria, $g['categoria']);
	$wb->celda($s1, $fila, $colMarca, $g['marca']);
	$wb->formula($s1, $fila, $colConcat, 'CONCAT('.$cl($colCliente).$fila.','.$cl($colCategorias).$fila.')');
	foreach ($mesesCols as $i => $mi) {
		$wb->celda($s1, $fila, $colCuotaInicio + $i, round($g['valores'][$mi] ?? 0, 2), false, 'money', $bgCuotaDato, '000000');
	}
	$rangoCuota = $cl($colCuotaInicio).$fila.':'.$cl($colCuotaInicio + $M - 1).$fila;
	$wb->formula($s1, $fila, $colTotalQ2, 'SUM('.$rangoCuota.')', false, 'money', $bgCuotaDato, '000000');
	$wb->celda($s1, $fila, $colRebatePct, round($g['rebate_pct'], 4), false, 'pct', $bgCuotaDato, '000000');
	$wb->formula($s1, $fila, $colRebateDolar, $cl($colTotalQ2).$fila.'*'.$cl($colRebatePct).$fila, false, 'money');
	$wb->formula($s1, $fila, $colRebateMax110, '('.$cl($colTotalQ2).$fila.'*1.1)*'.$cl($colRebatePct).$fila, false, 'money');
	// Venta real: columnas vacías, las llena JW mes a mes cuando cierra el período.
	for ($i = 0; $i < $M; $i++) {
		$wb->celda($s1, $fila, $colVentaInicio + $i, '');
	}
	// CARTERA: vacío, lo llena JW (ver CLAUDE.md, pendiente sin definir dónde vive esto en la base).
	$wb->celda($s1, $fila, $colCartera, '');
	$rangoVenta = $cl($colVentaInicio).$fila.':'.$cl($colVentaInicio + $M - 1).$fila;
	$wb->formula($s1, $fila, $colVentaTotal, 'SUM('.$rangoVenta.')', false, 'money');
	$wb->formula($s1, $fila, $colCumplimiento, 'IFERROR('.$cl($colVentaTotal).$fila.'/'.$cl($colTotalQ2).$fila.',0)', false, 'pct');
	$wb->formula($s1, $fila, $colGanaCategoria, 'IF('.$cl($colCumplimiento).$fila.'>=80%,"GANA","NO GANA")');

	$clienteClave = $g['cliente'];
	if (!isset($clientesVistos[$clienteClave])) {
		$clientesVistos[$clienteClave] = ['ejecutivo' => $g['ejecutivo'], 'cliente' => $g['cliente']];
	}
	$fila++;
}
$ultimaFilaDatos = $fila - 1;

// ---------- Hoja 2: CUOTA TOTAL (un renglón por cliente único) ----------
$filaEnc2 = 3;
$wb->celda($s2, $filaEnc2, 1, 'CEDI', true);
$wb->celda($s2, $filaEnc2, 2, 'CLIENTE', true);
$wb->celda($s2, $filaEnc2, 3, 'Suma de '.$tituloCuota, true);
$wb->celda($s2, $filaEnc2, 4, 'Suma de '.$tituloVenta, true);
$wb->celda($s2, $filaEnc2, 5, 'Cumplimiento', true);
$wb->celda($s2, $filaEnc2, 6, 'Gana', true);

$fila2 = $filaEnc2 + 1;
$primeraFila2 = $fila2;
if ($ultimaFilaDatos >= $primeraFilaDatos) {
	foreach ($clientesVistos as $cv) {
		$wb->celda($s2, $fila2, 1, $cv['ejecutivo']);
		$wb->celda($s2, $fila2, 2, $cv['cliente']);
		$rangoClienteS1 = "'CUOTA CLIENTE - CATEGORÍA'!\$".$cl($colCliente).'$'.$primeraFilaDatos.':$'.$cl($colCliente).'$'.$ultimaFilaDatos;
		$rangoTotalQ2S1 = "'CUOTA CLIENTE - CATEGORÍA'!\$".$cl($colTotalQ2).'$'.$primeraFilaDatos.':$'.$cl($colTotalQ2).'$'.$ultimaFilaDatos;
		$rangoVentaS1 = "'CUOTA CLIENTE - CATEGORÍA'!\$".$cl($colVentaTotal).'$'.$primeraFilaDatos.':$'.$cl($colVentaTotal).'$'.$ultimaFilaDatos;
		$wb->formula($s2, $fila2, 3, 'SUMIF('.$rangoClienteS1.',B'.$fila2.','.$rangoTotalQ2S1.')', false, 'money');
		$wb->formula($s2, $fila2, 4, 'SUMIF('.$rangoClienteS1.',B'.$fila2.','.$rangoVentaS1.')', false, 'money');
		$wb->formula($s2, $fila2, 5, 'IFERROR(D'.$fila2.'/C'.$fila2.',0)', false, 'pct');
		$wb->formula($s2, $fila2, 6, 'IF(E'.$fila2.'>=99.99%,"GANA","NO GANA")');
		$fila2++;
	}
}
$ultimaFila2 = $fila2 - 1;

// ---------- Hoja 1: GANA TOTAL / PRE REBATE / REBATE REAL VOL (necesitan CUOTA TOTAL ya armada) ----------
if ($ultimaFila2 >= $primeraFila2) {
	$rangoBuscarv = "'CUOTA TOTAL'!\$B\$".$primeraFila2.':$F$'.$ultimaFila2;
	for ($fila = $primeraFilaDatos; $fila <= $ultimaFilaDatos; $fila++) {
		$wb->formula($s1, $fila, $colGanaTotal, 'VLOOKUP('.$cl($colCliente).$fila.','.$rangoBuscarv.',5,FALSE)');
		$wb->formula($s1, $fila, $colPreRebate,
			'IF(AND('.$cl($colGanaTotal).$fila.'="GANA",'.$cl($colGanaCategoria).$fila.'="GANA"),'.
			$cl($colVentaTotal).$fila.'*'.$cl($colRebatePct).$fila.',0)', false, 'money');
		$wb->formula($s1, $fila, $colRebateRealVol,
			'IF('.$cl($colPreRebate).$fila.'<='.$cl($colRebateMax110).$fila.','.$cl($colPreRebate).$fila.','.$cl($colRebateMax110).$fila.')',
			false, 'money');
	}
}

// ---------- Hoja 1: fila de TOTAL (SUBTOTAL, igual que el archivo real) ----------
if ($ultimaFilaDatos >= $primeraFilaDatos) {
	$filaTotal = $ultimaFilaDatos + 1;
	$wb->celda($s1, $filaTotal, $colCedi, 'TOTAL', true);
	// Mismas columnas SUBTOTAL(9,...) que la fila TOTAL real, incluye REBATE % tal cual la plantilla.
	foreach ([$colCuotaInicio, $colTotalQ2, $colRebatePct, $colRebateDolar, $colRebateMax110, $colVentaInicio, $colVentaTotal, $colPreRebate, $colRebateRealVol] as $colBase) {
		// Para los bloques de M columnas (cuota, venta) hay que recorrer cada una; para el resto, una sola columna.
		$colsAExpandir = in_array($colBase, [$colCuotaInicio, $colVentaInicio], true) ? range($colBase, $colBase + $M - 1) : [$colBase];
		foreach ($colsAExpandir as $c) {
			$rango = $cl($c).$primeraFilaDatos.':'.$cl($c).$ultimaFilaDatos;
			$wb->formula($s1, $filaTotal, $c, 'SUBTOTAL(9,'.$rango.')', true, 'money');
		}
	}
	$wb->formula($s1, $filaTotal, $colCumplimiento, 'IFERROR('.$cl($colVentaTotal).$filaTotal.'/'.$cl($colTotalQ2).$filaTotal.',0)', true, 'pct');
	// Estas columnas no se totalizan, pero necesitan celda vacía igual para que el borde pinte parejo.
	foreach ([$colCliente, $colPlan, $colCategorias, $colConcat, $colCartera, $colGanaCategoria, $colGanaTotal] as $col) {
		$wb->celda($s1, $filaTotal, $col, '', true);
	}
}

// ==================== Hoja "VISIBILIDAD" ====================
// cabecera->CABECERA, ruma->ISLA, percha->PERCHA; cuenta si el TOTAL de la línea es > 0.
// "MARCA" muestra Categoría (Percha no tiene ese campo, sigue con Marca); VALIDACIÓN se autocompleta.
$stmtVis = $mysqli->prepare(
	"SELECT u.usuario AS ejecutivo, d.pos_name AS cliente, d.canal, l.tipo, l.marca, l.categoria,
	        l.valores_mensuales, l.valor_mensual_unico, a.mes_inicio, a.mes_fin
	 FROM repositorio_acuerdos a
	 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
	 JOIN repositorio_acuerdo_lineas l ON l.acuerdo_id = a.id AND l.tipo IN ('cabecera', 'ruma', 'percha')
	 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
	 WHERE a.estado NOT IN ('borrador', 'anulado')
	   AND a.acta_firmada_archivo IS NOT NULL
	   AND d.pos_name LIKE ?
	   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
	   AND (? = 0 OR a.anio = ?)
	   AND d.canal <> 'DISTRIBUIDOR'
	 GROUP BY a.id, l.id"
);
$filasVis = [];
if ($stmtVis) {
	// Sin filtro de creado_por acá tampoco (2026-08-31, "ver todo" — ver nota
	// completa en la 1ra query de este archivo).
	$stmtVis->bind_param('siiiii', $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmtVis->execute();
	$filasVis = $stmtVis->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmtVis->close();
}

$tipoColuna = ['cabecera' => 'CABECERA', 'ruma' => 'ISLA', 'percha' => 'PERCHA'];
$porClienteVis = []; // cliente => ['ejecutivo'=>..., 'tipos'=>['CABECERA'=>['cantidad'=>,'pago'=>,'textos'=>[]], ...]]

foreach ($filasVis as $f) {
	if ($f['marca'] === '' || $f['marca'] === null) continue;
	$colTipo = $tipoColuna[$f['tipo']];
	$mesesActivos = range((int) $f['mes_inicio'], (int) $f['mes_fin']);

	if ($f['tipo'] === 'ruma') {
		// Ruma: un valor mensual único aplicado a todos los meses; Pago Total = valor x meses del período.
		$valorUnico = (float) $f['valor_mensual_unico'];
		$pagoLinea = $valorUnico * count($mesesActivos);
	} else {
		$valores = json_decode($f['valores_mensuales'] ?? '{}', true) ?: [];
		$pagoLinea = 0.0;
		foreach ($mesesActivos as $m) $pagoLinea += (float) ($valores[(string) $m] ?? 0);
	}
	if ($pagoLinea <= 0) continue; // sin data real en la línea: no cuenta ni suma, ver nota arriba.

	// "MARCA" del archivo real en realidad muestra la categoría (cabecera/
	// ruma la tienen guardada); percha no tiene ese campo, se usa marca.
	$textoColumna = ($f['tipo'] === 'percha') ? $f['marca'] : ($f['categoria'] !== '' && $f['categoria'] !== null ? $f['categoria'] : $f['marca']);

	$cliente = $f['cliente'];
	if (!isset($porClienteVis[$cliente])) {
		$porClienteVis[$cliente] = [
			'ejecutivo' => $f['ejecutivo'] ?? '',
			'plan'      => $f['canal'] ?? '',
			'tipos' => [
				'CABECERA' => ['cantidad' => 0, 'pago' => 0.0, 'textos' => []],
				'ISLA'     => ['cantidad' => 0, 'pago' => 0.0, 'textos' => []],
				'PERCHA'   => ['cantidad' => 0, 'pago' => 0.0, 'textos' => []],
			],
		];
	}
	$porClienteVis[$cliente]['tipos'][$colTipo]['cantidad']++;
	$porClienteVis[$cliente]['tipos'][$colTipo]['pago'] += $pagoLinea;
	$porClienteVis[$cliente]['tipos'][$colTipo]['textos'][] = $textoColumna;
}
ksort($porClienteVis);

// Espacio final a propósito: liquidacion_import.php busca la hoja por ese nombre exacto al reimportar.
$s3 = $wb->agregarHoja('VISIBILIDAD ');

// ---------- Hoja "VISIBILIDAD": encabezados (2 filas, igual que el archivo real) ----------
// Color de tema resuelto a mano desde el XML crudo (Excel COM daba rosa incorrecto);
// reusa $bgEncabezado/$bgClienteDato de la hoja Cuota/Categoría, mismos azul/rosa.
$bgEncVis = $bgEncabezado; $bgClienteVis = $bgClienteDato;
// Columnas (sin "KP", fórmula rota #REF! del archivo real sin uso):
// 1 CEDI, 2 Nombres, 3 PLAN, 4-6 CANTIDAD, 7-9 PAGO, 10 PAGO TOTAL, 11-13 MARCA,
// 14-16 VALIDACIÓN, 17-19 validado, 20 TOTAL, 21 OBSERVACION (todas Cab/Isla/Percha).
$vCedi = 1; $vNombres = 2; $vPlan = 3;
$vCantCab = 4; $vCantIsla = 5; $vCantPercha = 6;
$vPagoCab = 7; $vPagoIsla = 8; $vPagoPercha = 9; $vPagoTotal = 10;
$vMarcaCab = 11; $vMarcaIsla = 12; $vMarcaPercha = 13;
$vValidCab = 14; $vValidIsla = 15; $vValidPercha = 16;
$vRCab = 17; $vRIsla = 18; $vRPercha = 19; $vTotal = 20; $vObs = 21;

// Títulos de grupo y sub-encabezados centrados horizontal y vertical.
$wb->celda($s3, 1, $vCantCab, 'CANTIDAD', true, null, $bgEncVis, '000000', true);
$wb->combinarCeldas($s3, $cl($vCantCab).'1:'.$cl($vCantPercha).'1');
$wb->celda($s3, 1, $vPagoCab, 'PAGO', true, null, $bgEncVis, '000000', true);
$wb->combinarCeldas($s3, $cl($vPagoCab).'1:'.$cl($vPagoPercha).'1');
$wb->celda($s3, 1, $vMarcaCab, 'MARCA', true, null, $bgEncVis, '000000', true);
$wb->combinarCeldas($s3, $cl($vMarcaCab).'1:'.$cl($vMarcaPercha).'1');
$wb->celda($s3, 1, $vValidCab, 'VALIDACIÓN', true, null, $bgEncVis, '000000', true);
$wb->combinarCeldas($s3, $cl($vValidCab).'1:'.$cl($vValidPercha).'1');

$filaEncVis = 2;
foreach ([$vCedi => 'CEDI', $vNombres => 'NOMBRES', $vPlan => 'PLAN'] as $col => $texto) {
	$wb->celda($s3, $filaEncVis, $col, $texto, true, null, $bgEncVis, '000000', true);
}
foreach ([$vCantCab, $vPagoCab, $vMarcaCab, $vValidCab, $vRCab] as $base) {
	$wb->celda($s3, $filaEncVis, $base, 'CABECERA', true, null, $bgEncVis, '000000', true);
	$wb->celda($s3, $filaEncVis, $base + 1, 'ISLA', true, null, $bgEncVis, '000000', true);
	$wb->celda($s3, $filaEncVis, $base + 2, 'PERCHA', true, null, $bgEncVis, '000000', true);
}
$wb->celda($s3, $filaEncVis, $vPagoTotal, 'PAGO TOTAL', true, null, $bgEncVis, '000000', true);
$wb->celda($s3, $filaEncVis, $vTotal, 'TOTAL', true, null, $bgEncVis, '000000', true);
$wb->celda($s3, $filaEncVis, $vObs, 'OBSERVACION', true, null, $bgEncVis, '000000', true);

// Columnas fuera de las 4 fusiones de arriba necesitan celda propia (vacía, mismo estilo) o quedan sin pintar.
foreach ([$vCedi, $vNombres, $vPlan, $vRCab, $vRIsla, $vRPercha, $vPagoTotal, $vTotal, $vObs] as $col) {
	$wb->celda($s3, 1, $col, '', false, null, $bgEncVis, '000000', true);
}

// ---------- Hoja "VISIBILIDAD": filas de datos ----------
$filaVisDatos = $filaEncVis + 1;
$primeraFilaVis = $filaVisDatos;
foreach ($porClienteVis as $cliente => $datosCliente) {
	$wb->celda($s3, $filaVisDatos, $vCedi, $datosCliente['ejecutivo']);
	$wb->celda($s3, $filaVisDatos, $vNombres, $cliente, false, null, $bgClienteVis, '000000');
	// PLAN = canal del cliente en el maestro.
	$wb->celda($s3, $filaVisDatos, $vPlan, $datosCliente['plan']);

	$colCantidad = [$vCantCab, $vCantIsla, $vCantPercha];
	$colPago = [$vPagoCab, $vPagoIsla, $vPagoPercha];
	$colMarca = [$vMarcaCab, $vMarcaIsla, $vMarcaPercha];
	foreach (['CABECERA', 'ISLA', 'PERCHA'] as $i => $tipoNombre) {
		$t = $datosCliente['tipos'][$tipoNombre];
		$wb->celda($s3, $filaVisDatos, $colCantidad[$i], $t['cantidad']);
		// PAGO: valor calculado directo (no fórmula), ver nota arriba.
		$wb->celda($s3, $filaVisDatos, $colPago[$i], round($t['pago'], 2), false, 'money');
		$wb->celda($s3, $filaVisDatos, $colMarca[$i], implode(' - ', $t['textos']));
	}

	$wb->formula($s3, $filaVisDatos, $vPagoTotal,
		$cl($vPagoCab).$filaVisDatos.'+'.$cl($vPagoIsla).$filaVisDatos.'+'.$cl($vPagoPercha).$filaVisDatos, false, 'money');

	// VALIDACIÓN: fórmula real según CANTIDAD (CUMPLE si > 0), se recalcula sola si JW corrige a mano.
	$colValid = [$vValidCab, $vValidIsla, $vValidPercha];
	foreach ($colCantidad as $i => $colCant) {
		$wb->formula($s3, $filaVisDatos, $colValid[$i], 'IF('.$cl($colCant).$filaVisDatos.'>0,"CUMPLE","NO CUMPLE")');
	}

	// R/S/T: IF(VALIDACIÓN="CUMPLE", PAGO, 0) — 0 hasta que JW valide.
	$wb->formula($s3, $filaVisDatos, $vRCab, 'IF('.$cl($vValidCab).$filaVisDatos.'="CUMPLE",'.$cl($vPagoCab).$filaVisDatos.',0)', false, 'money');
	$wb->formula($s3, $filaVisDatos, $vRIsla, 'IF('.$cl($vValidIsla).$filaVisDatos.'="CUMPLE",'.$cl($vPagoIsla).$filaVisDatos.',0)', false, 'money');
	$wb->formula($s3, $filaVisDatos, $vRPercha, 'IF('.$cl($vValidPercha).$filaVisDatos.'="CUMPLE",'.$cl($vPagoPercha).$filaVisDatos.',0)', false, 'money');
	$wb->formula($s3, $filaVisDatos, $vTotal, 'SUM('.$cl($vRCab).$filaVisDatos.':'.$cl($vRPercha).$filaVisDatos.')', false, 'money');
	$wb->celda($s3, $filaVisDatos, $vObs, '');

	$filaVisDatos++;
}
$ultimaFilaVis = $filaVisDatos - 1;

// ---------- Hoja "VISIBILIDAD": fila TOTAL ----------
// Fórmulas leídas exactas del archivo real: CANTIDAD/PAGO usan SUM (no SUBTOTAL),
// MARCA/VALIDACIÓN/R-S-T quedan en blanco, solo TOTAL usa SUBTOTAL(9,...).
if ($ultimaFilaVis >= $primeraFilaVis) {
	$filaTotalVis = $ultimaFilaVis + 1;
	$wb->celda($s3, $filaTotalVis, $vNombres, 'TOTAL', true);
	foreach ([$vCantCab, $vCantIsla, $vCantPercha, $vPagoCab, $vPagoIsla, $vPagoPercha] as $col) {
		$rango = $cl($col).$primeraFilaVis.':'.$cl($col).$ultimaFilaVis;
		$numFmt = in_array($col, [$vPagoCab, $vPagoIsla, $vPagoPercha], true) ? 'money' : null;
		$wb->formula($s3, $filaTotalVis, $col, 'SUM('.$rango.')', true, $numFmt);
	}
	$wb->formula($s3, $filaTotalVis, $vPagoTotal,
		$cl($vPagoCab).$filaTotalVis.'+'.$cl($vPagoIsla).$filaTotalVis.'+'.$cl($vPagoPercha).$filaTotalVis, true, 'money');
	$rangoTotalVis = $cl($vTotal).$primeraFilaVis.':'.$cl($vTotal).$ultimaFilaVis;
	$wb->formula($s3, $filaTotalVis, $vTotal, 'SUBTOTAL(9,'.$rangoTotalVis.')', true, 'money');
	// Columnas que no totalizan igual necesitan celda vacía (negrita) para que el borde pinte.
	foreach ([$vCedi, $vPlan, $vMarcaCab, $vMarcaIsla, $vMarcaPercha, $vValidCab, $vValidIsla, $vValidPercha, $vRCab, $vRIsla, $vRPercha, $vObs] as $col) {
		$wb->celda($s3, $filaTotalVis, $col, '', true);
	}
}

// ==================== Hoja "RESUMEN DE PAGOS" ====================
// Un renglón por cliente, sin los subtotales intercalados del archivo real, con fórmulas reales (mismo patrón que "CUOTA TOTAL").
$sResumen = $wb->agregarHoja('RESUMEN DE PAGOS');
$rCedi = 1; $rCliente = 2; $rVolumen = 3; $rVisibilidad = 4; $rTotalPago = 5;
$wb->celda($sResumen, 1, $rCedi, 'CEDI', true, null, $bgEncabezado, '000000');
$wb->celda($sResumen, 1, $rCliente, 'CLIENTE', true, null, $bgEncabezado, '000000');
$wb->celda($sResumen, 1, $rVolumen, 'VOLUMEN', true, null, $bgEncabezado, '000000');
$wb->celda($sResumen, 1, $rVisibilidad, 'VISIBILIDAD', true, null, $bgEncabezado, '000000');
$wb->celda($sResumen, 1, $rTotalPago, 'TOTAL', true, null, $bgEncabezado, '000000');

// Unión de clientes vistos en Cuota y en Visibilidad; un cliente puede tener solo uno de los dos.
$clientesResumen = [];
foreach ($clientesVistos as $cli => $cv) $clientesResumen[$cli] = $cv['ejecutivo'];
foreach ($porClienteVis as $cli => $dv) {
	if (!isset($clientesResumen[$cli])) $clientesResumen[$cli] = $dv['ejecutivo'];
}
ksort($clientesResumen);

$filaResumen = 2;
$primeraFilaResumen = $filaResumen;
foreach ($clientesResumen as $cliente => $ejecutivo) {
	$wb->celda($sResumen, $filaResumen, $rCedi, $ejecutivo);
	$wb->celda($sResumen, $filaResumen, $rCliente, $cliente);
	$refCliente = $cl($rCliente).$filaResumen;
	if ($ultimaFilaDatos >= $primeraFilaDatos) {
		$rangoClienteCuota = "'CUOTA CLIENTE - CATEGORÍA'!\$".$cl($colCliente).'$'.$primeraFilaDatos.':$'.$cl($colCliente).'$'.$ultimaFilaDatos;
		$rangoRebateRealVol = "'CUOTA CLIENTE - CATEGORÍA'!\$".$cl($colRebateRealVol).'$'.$primeraFilaDatos.':$'.$cl($colRebateRealVol).'$'.$ultimaFilaDatos;
		$wb->formula($sResumen, $filaResumen, $rVolumen, 'SUMIF('.$rangoClienteCuota.','.$refCliente.','.$rangoRebateRealVol.')', false, 'money');
	} else {
		$wb->celda($sResumen, $filaResumen, $rVolumen, 0, false, 'money');
	}
	if ($ultimaFilaVis >= $primeraFilaVis) {
		// OJO: la hoja se creó como 'VISIBILIDAD ' con espacio final; sin él la referencia queda rota (IFERROR la disfraza de "$0").
		$rangoVisLookup = "'VISIBILIDAD '!\$".$cl($vNombres).'$'.$primeraFilaVis.':$'.$cl($vTotal).'$'.$ultimaFilaVis;
		$offsetVisTotal = $vTotal - $vNombres + 1;
		// IFERROR por si el cliente no tiene fila en Visibilidad (solo Meta de Compras).
		$wb->formula($sResumen, $filaResumen, $rVisibilidad,
			'IFERROR(VLOOKUP('.$refCliente.','.$rangoVisLookup.','.$offsetVisTotal.',FALSE),0)', false, 'money');
	} else {
		$wb->celda($sResumen, $filaResumen, $rVisibilidad, 0, false, 'money');
	}
	$wb->formula($sResumen, $filaResumen, $rTotalPago,
		$cl($rVolumen).$filaResumen.'+'.$cl($rVisibilidad).$filaResumen, false, 'money');
	$filaResumen++;
}
$ultimaFilaResumen = $filaResumen - 1;
if ($ultimaFilaResumen >= $primeraFilaResumen) {
	// CEDI nunca se totaliza, pero necesita celda vacía igual para pintar el borde.
	$wb->celda($sResumen, $filaResumen, $rCedi, '', true);
	$wb->celda($sResumen, $filaResumen, $rCliente, 'TOTAL', true);
	foreach ([$rVolumen, $rVisibilidad, $rTotalPago] as $col) {
		$rango = $cl($col).$primeraFilaResumen.':'.$cl($col).$ultimaFilaResumen;
		$wb->formula($sResumen, $filaResumen, $col, 'SUM('.$rango.')', true, 'money');
	}
}

$bin = $wb->generar();

$nombreArchivo = 'CuotaCategoria_Directa_'.date('Y-m-d').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$nombreArchivo.'"');
header('Content-Length: '.strlen($bin));
echo $bin;
exit;
?>
