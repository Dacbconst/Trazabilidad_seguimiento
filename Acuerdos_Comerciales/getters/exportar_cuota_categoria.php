<?php
// Genera un .xlsx real (con fórmulas vivas, no un CSV) que replica la hoja
// "CUOTA CLIENTE - CATEGORÍA" del archivo real de JW
// (datos/LIQUIDACION ACUERDOS COMERCIALES Q2 DIRECTA 2026.xlsx) a partir de
// lo pactado en nuestras Actas — para que JW no tenga que reconstruir a mano
// lo que el ejecutivo ya tipeó. Solo Actas de canal Directa (la de
// Distribuidor tiene columnas distintas en el archivo real, "CUOTAS POR
// CAT-DISTRIBUIDORES" — no está construida, es un export aparte a futuro).
//
// Decisiones confirmadas con el usuario 2026-08-18 (ver CLAUDE.md, sección
// "Export de Cuota/Categoría" para el detalle completo):
// - REBATE A APLICAR %: se toma el `rebate_pct` YA GUARDADO en la línea de
//   la Acta (congelado al momento en que se generó esa Acta) — nunca un
//   valor "vivo" de un catálogo externo, para que un cambio de rebate
//   después no altere retroactivamente Actas ya generadas.
// - PLAN: se investigó a fondo contra la base real (todas las columnas de
//   repositorio_locales_supervisores_cliente) y el texto que usa JW
//   ("AUTOSERVICIO INDEPENDIENTE", etc.) no aparece en ningún lado — queda
//   vacío, columna para que JW la llene, no se puede derivar hoy.
// - **Una fila por línea de Meta de Compras, SIN agrupar por Sector**
//   (corregido 2026-08-18 — la primera versión sumaba todas las marcas de
//   un mismo Sector en una sola fila, asumiendo que compartían el mismo
//   rebate %. El usuario lo objetó mostrando el Acta real escaneada
//   (`datos/WhatsApp Image 2026-07-23 at 11.09.16.jpeg`): cada línea de la
//   Acta — aunque comparta Sector con otra — es un registro propio, con su
//   propio rebate % pactado. Se confirmó con un caso real: dos líneas de
//   Sector "PASTAS" con 4% y 3% de rebate distintos, que la versión vieja
//   fusionaba mal en una sola fila mostrando solo uno de los dos rebates).
//   La columna CATEGORIAS sigue mostrando el Sector (así se llama en el
//   archivo real de JW) — si dos líneas del mismo cliente comparten Sector,
//   van en 2 filas con el mismo texto de CATEGORIAS, cada una con su cuota
//   y rebate reales, nunca sumadas.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/xlsx_writer.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo 'No autorizado.';
	exit;
}

$usuarioId = $_SESSION['user_id'] ?? null;
$busqueda  = trim($_GET['q'] ?? '');
$mes       = (int) ($_GET['mes'] ?? 0);
$mesIdx    = ($mes >= 1 && $mes <= 12) ? ($mes - 1) : -1;
$like      = '%'.$busqueda.'%';

if (!$usuarioId) {
	http_response_code(403);
	echo 'Sesión inválida.';
	exit;
}

// Mismo patrón de GROUP BY a.id, l.id que el resto de exports de Historial —
// colapsa duplicados de pos_id en el maestro sin perder líneas reales.
// d.canal <> 'DISTRIBUIDOR': esta hoja es solo para Directa.
$stmt = $mysqli->prepare(
	"SELECT u.usuario AS ejecutivo, d.pos_name AS cliente, l.sector, l.rebate_pct, l.valores_mensuales
	 FROM repositorio_acuerdos a
	 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
	 JOIN repositorio_acuerdo_lineas l ON l.acuerdo_id = a.id AND l.tipo = 'meta_compra'
	 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
	 WHERE a.estado NOT IN ('borrador', 'anulado')
	   AND a.creado_por = ?
	   AND d.pos_name LIKE ?
	   AND (? = -1 OR (a.mes_inicio <= ? AND a.mes_fin >= ?))
	   AND d.canal <> 'DISTRIBUIDOR'
	 GROUP BY a.id, l.id"
);
if (!$stmt) {
	http_response_code(500);
	echo 'Error preparando la consulta.';
	exit;
}
$stmt->bind_param('isiii', $usuarioId, $like, $mesIdx, $mesIdx, $mesIdx);
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
		'sector'     => $f['sector'],
		'rebate_pct' => (float) $f['rebate_pct'], // el de ESTA línea, nunca mezclado con otra
		'valores'    => $valoresPorMes,
	];
}

ksort($mesesPresentes, SORT_NUMERIC);
$mesesCols = array_keys($mesesPresentes); // ej. [3,4,5] para Abril-Mayo-Junio
$M = count($mesesCols);

if ($M === 0) {
	// Nada que exportar (sin Actas Directa con Sector guardado que matcheen el filtro).
	$M = 1; // evita división por cero más abajo / columnas negativas; sheet sale con headers nomás.
	$mesesCols = [0];
}

usort($filasFinal, function ($a, $b) {
	$c = strcmp($a['cliente'], $b['cliente']);
	return $c !== 0 ? $c : strcmp($a['sector'], $b['sector']);
});

// ---------- Layout de columnas (dinámico según cuántos meses hay) ----------
$colCedi = 1; $colCliente = 2; $colPlan = 3; $colCategorias = 4; $colConcat = 5;
$colCuotaInicio = 6;
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
$s2 = $wb->agregarHoja('CUOTA TOTAL');

// ---------- Hoja 1: encabezados (fila 2, como el archivo real) ----------
// Colores EXACTOS leídos del archivo real (Excel COM, Interior.Color/Font.Color
// convertidos de BGR a RGB) — no son "parecidos", son los mismos valores:
//   Encabezado general (CEDI..REBATE MAXIMO 110%): fondo #C0E6F5, letra negra.
//   Bloque VENTA Q2 (título combinado + meses):    fondo #61CBF3, letra roja.
//   CARTERA:                                        fondo #FFC000, letra negra, SIN negrita (así está en el original).
//   VENTA Q2 / CUMPLIMIENTO / GANA.../PRE REBATE:   fondo #B5E6A2, letra negra.
//   REBATE REAL VOL:                                fondo #FFFF00, letra negra.
$bgEncabezado = 'C0E6F5'; $bgVenta = '61CBF3'; $fontVenta = 'FF0000';
$bgCartera = 'FFC000'; $bgResultado = 'B5E6A2'; $bgRebateReal = 'FFFF00';

// "Qx" dinámico según el PRIMER mes real de las Actas incluidas (0=Enero) —
// nunca fijo en "Q2": si el período es Enero-Marzo es Q1, si es
// Julio-Septiembre es Q3, etc. Se usa tanto para "TOTAL Qx" (cuota
// pactada) como para "VENTA Qx" (título combinado + columna de venta real)
// — las dos etiquetas del archivo original que mencionan trimestre.
$trimestre = intdiv($mesesCols[0], 3) + 1;
$tituloCuota = 'TOTAL Q'.$trimestre;
$tituloVenta = 'VENTA Q'.$trimestre;

$filaEnc = 2;
$wb->celda($s1, $filaEnc, $colCedi, 'CEDI', true, null, $bgEncabezado, '000000');
$wb->celda($s1, $filaEnc, $colCliente, 'CLIENTE', true, null, $bgEncabezado, '000000');
$wb->celda($s1, $filaEnc, $colPlan, 'PLAN', true, null, $bgEncabezado, '000000');
$wb->celda($s1, $filaEnc, $colCategorias, 'CATEGORIAS', true, null, $bgEncabezado, '000000');
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

// ---------- Hoja 1: filas de datos ----------
// Colores de las filas de datos (distintos de los del encabezado) — también
// leídos EXACTOS del archivo real: columna CLIENTE en rosa/lavanda, y el
// bloque Cuota+Total Q2+Rebate% en verde (probado en varias filas reales,
// mismo valor siempre). El resto de columnas de datos van sin color (blanco
// puro en el original, visualmente igual a no pintar nada).
$bgClienteDato = 'F2CEEF'; $bgCuotaDato = '92D050';

$primeraFilaDatos = $filaEnc + 1;
$fila = $primeraFilaDatos;
$clientesVistos = []; // para armar la hoja CUOTA TOTAL (únicos), en orden de aparición
foreach ($filasFinal as $g) {
	$wb->celda($s1, $fila, $colCedi, $g['ejecutivo']);
	$wb->celda($s1, $fila, $colCliente, $g['cliente'], false, null, $bgClienteDato, '000000');
	// PLAN queda vacío a propósito, ver nota arriba.
	$wb->celda($s1, $fila, $colCategorias, $g['sector']);
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

// ---------- Hoja 1: ahora sí, GANA TOTAL / PRE REBATE / REBATE REAL VOL, que
// necesitan que la hoja CUOTA TOTAL ya exista (BUSCARV la referencia). ----------
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
	// Mismas columnas que el SUBTOTAL(9,...) de la fila TOTAL real (fila 313
	// de datos/LIQUIDACION ACUERDOS COMERCIALES Q2 DIRECTA 2026.xlsx,
	// confirmado leyendo las fórmulas reales una por una) — incluye REBATE %
	// aunque sumar porcentajes no tenga mucho sentido de negocio; se replica
	// tal cual porque así está en la plantilla real que usa JW.
	foreach ([$colCuotaInicio, $colTotalQ2, $colRebatePct, $colRebateDolar, $colRebateMax110, $colVentaInicio, $colVentaTotal, $colPreRebate, $colRebateRealVol] as $colBase) {
		// Para los bloques de M columnas (cuota, venta) hay que recorrer cada una; para el resto, una sola columna.
		$colsAExpandir = in_array($colBase, [$colCuotaInicio, $colVentaInicio], true) ? range($colBase, $colBase + $M - 1) : [$colBase];
		foreach ($colsAExpandir as $c) {
			$rango = $cl($c).$primeraFilaDatos.':'.$cl($c).$ultimaFilaDatos;
			$wb->formula($s1, $filaTotal, $c, 'SUBTOTAL(9,'.$rango.')', true, 'money');
		}
	}
	$wb->formula($s1, $filaTotal, $colCumplimiento, 'IFERROR('.$cl($colVentaTotal).$filaTotal.'/'.$cl($colTotalQ2).$filaTotal.',0)', true, 'pct');
}

$bin = $wb->generar();

$nombreArchivo = 'CuotaCategoria_Directa_'.date('Y-m-d').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$nombreArchivo.'"');
header('Content-Length: '.strlen($bin));
echo $bin;
exit;
?>
