<?php
// Genera un .xlsx real (con fórmulas vivas, no un CSV) que replica la hoja
// "CUOTA CLIENTE - CATEGORÍA" del archivo real de JW
// (datos/LIQUIDACION ACUERDOS COMERCIALES Q2 DIRECTA 2026.xlsx) a partir de
// lo pactado en nuestras Actas — para que JW no tenga que reconstruir a mano
// lo que el ejecutivo ya tipeó. Todo lo de abajo es SOLO para canal Directa
// (d.canal <> 'DISTRIBUIDOR' en la consulta) — para canal Distribuidor este
// archivo delega en exportar_cuota_categoria_distribuidor.php (2026-08-20,
// ver el branch por canalDeSupervisor() más abajo), que replica la hoja
// "CUOTAS POR CAT -DISTRIBUIDORES" del archivo real de Distribuidor.
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
$trimestre = (int) ($_GET['trimestre'] ?? 0);
$anio      = (int) ($_GET['anio'] ?? 0);
$like      = '%'.$busqueda.'%';

// Mismo filtro de trimestre exacto que listar_historial_acuerdos() (ver
// functions.php, trimestreABounds()) — el Período del Acuerdo es siempre un
// trimestre fijo, así que comparar mes_inicio/mes_fin exactos alcanza.
$bounds          = trimestreABounds($trimestre);
$trimestreActivo = $bounds ? 1 : 0;
$mesInicioFiltro = $bounds ? $bounds[0] : -1;
$mesFinFiltro    = $bounds ? $bounds[1] : -1;

if (!$usuarioId) {
	http_response_code(403);
	echo 'Sesión inválida.';
	exit;
}

// Canal Distribuidor tiene su propia hoja ("CUOTAS POR CAT -DISTRIBUIDORES",
// columnas/colores distintos del archivo real) — vive en un archivo aparte
// para no arriesgar el código de Directa ya probado (ver 2026-08-20).
$canalUsuarioExport = canalDeSupervisor($mysqli, $_SESSION['supervisor'] ?? null) ?: 'directo';
if ($canalUsuarioExport === 'distribuidor') {
	require __DIR__.'/exportar_cuota_categoria_distribuidor.php';
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
$stmt->bind_param('isiiiii', $usuarioId, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
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

// La fila 1 solo tenía celda en el título fusionado de VENTA (arriba) — el
// resto de columnas del encabezado (CEDI...REBATE MAXIMO 110%, CARTERA,
// VENTA TOTAL...REBATE REAL VOL) no tenían NINGUNA celda en fila 1, así que
// quedaban sin pintar/sin borde ahí (bug real encontrado 2026-08-20, el
// usuario lo vio en el Excel real: "hay celdas que quedan sin pintar").
// XlsxWriter solo escribe `<c>` para celdas donde se llamó celda() — una
// celda nunca escrita no tiene estilo, aunque `borderId` esté fijo para
// TODAS las que sí se llaman. Se rellenan acá con string vacío y el MISMO
// color de fondo/letra que su celda de fila 2 justo debajo, para que las 2
// filas del encabezado se vean como un solo bloque continuo sin huecos.
$wb->celda($s1, 1, $colCedi, '', false, null, $bgEncabezado, '000000');
$wb->celda($s1, 1, $colCliente, '', false, null, $bgEncabezado, '000000');
$wb->celda($s1, 1, $colPlan, '', false, null, $bgEncabezado, '000000');
$wb->celda($s1, 1, $colCategorias, '', false, null, $bgEncabezado, '000000');
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

// ==================== Hoja "VISIBILIDAD" (2026-08-19/20) ====================
// Replica la hoja VISIBILIDAD del archivo real de JW (mismo archivo
// datos/LIQUIDACION ACUERDOS COMERCIALES Q2 DIRECTA 2026.xlsx) a partir de
// las líneas cabecera/ruma/percha de la Acta — cabecera→CABECERA,
// ruma→ISLA, percha→PERCHA (confirmado con el usuario). No toca $s1/$s2 de arriba.
//
// Decisiones confirmadas con el usuario:
// - CEDI = mismo "ejecutivo" (usuario logueado que creó el Acta) que la
//   hoja de Cuota/Categoría — no es el CEDI/zona geográfica.
// - PLAN: investigado también contra `repositorio_locales_dtt2` (tabla
//   deprecada) a pedido del usuario — tiene un canal "AUTOSERVICIO" pero
//   solo para cadenas modernas (SUPERMAXI/TÍA/AKI), nada de "SUPER ALIANZA"
//   ni "AUTOSERVICIO INDEPENDIENTE". Sigue sin poder derivarse, vacío.
// - **Corregido 2026-08-20**: regla de "línea completa" para CANTIDAD/PAGO
//   relajada — antes exigía que TODOS los meses tuvieran valor > 0 (una
//   línea con un solo mes en 0 quedaba totalmente descartada); el usuario
//   la objetó mostrando percha con datos reales, así que ahora una línea
//   cuenta/suma si su TOTAL (suma de los meses, o el valor único en ruma)
//   es > 0 — basta con que haya llegado data real a la línea, no que todos
//   los meses individuales estén llenos.
// - **Corregido 2026-08-20**: el grupo de columnas etiquetado "MARCA" (así
//   se llama en el archivo real, no se cambia el título) ahora muestra la
//   CATEGORÍA de la línea (cabecera/ruma sí tienen `categoria` guardada),
//   no la marca — pedido explícito del usuario. `percha` no tiene columna
//   `categoria` en el esquema (solo `marca`, ver guardar_acuerdo.php), así
//   que para PERCHA se sigue mostrando `marca` a falta de otra cosa.
// - **Corregido 2026-08-20**: PAGO CABECERA/ISLA/PERCHA vuelven a ser
//   VALORES calculados directo en PHP (no fórmulas `SUMIF`) — la hoja
//   auxiliar "VISIBILIDAD DETALLE" que se había agregado para sostener esas
//   fórmulas se ELIMINÓ (el usuario la objetó: no existe nada parecido en
//   el archivo real, no había que inventar hojas nuevas). PAGO TOTAL sigue
//   siendo una fórmula real (`=G+H+I`, solo referencia celdas de la misma
//   fila) porque así es como funciona en el archivo real también.
// - VALIDACIÓN (CABECERA/ISLA/PERCHA) va vacía siempre — la llena JW a mano
//   después de verificar en campo, no es un dato que tengamos.
// - R/S/T (columnas sin cabecera de grupo) e IF(...)/TOTAL: fórmulas
//   idénticas a las del archivo real, traducidas a inglés/coma (ver aviso
//   en xlsx_writer.php) — quedan en 0 hasta que JW llene VALIDACIÓN a mano.
// - Colores: leídos del archivo real vía Excel COM (`Interior.Color` Y
//   `DisplayFormat.Interior.Color`, dan exactamente lo mismo, sin tabla de
//   Excel de por medio) — encabezado `#F5E6F5`, columna Nombres `#EFCEEF`.
//   El usuario dijo que no son los colores reales — **pendiente de
//   confirmar con él cuáles son los correctos**, ver CLAUDE.md.
$stmtVis = $mysqli->prepare(
	"SELECT u.usuario AS ejecutivo, d.pos_name AS cliente, l.tipo, l.marca, l.categoria,
	        l.valores_mensuales, l.valor_mensual_unico, a.mes_inicio, a.mes_fin
	 FROM repositorio_acuerdos a
	 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
	 JOIN repositorio_acuerdo_lineas l ON l.acuerdo_id = a.id AND l.tipo IN ('cabecera', 'ruma', 'percha')
	 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
	 WHERE a.estado NOT IN ('borrador', 'anulado')
	   AND a.creado_por = ?
	   AND d.pos_name LIKE ?
	   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
	   AND (? = 0 OR a.anio = ?)
	   AND d.canal <> 'DISTRIBUIDOR'
	 GROUP BY a.id, l.id"
);
$filasVis = [];
if ($stmtVis) {
	$stmtVis->bind_param('isiiiii', $usuarioId, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
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
		// Ruma: un solo valor mensual aplicado a todos los meses (ver
		// valor_mensual_unico en guardar_acuerdo.php) — el "Pago Total" de
		// la línea es ese valor × la cantidad de meses del período (mismo
		// cálculo que tabla_marca_html() en includes/acta_pdf.php).
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

$s3 = $wb->agregarHoja('VISIBILIDAD');

// ---------- Hoja "VISIBILIDAD": encabezados (2 filas, igual que el archivo real) ----------
// Corregido 2026-08-20: la lectura por COM (Interior.Color/DisplayFormat)
// daba F5E6F5/EFCEEF (rosa) — el usuario lo objetó con una captura real
// mostrando encabezado AZUL. Se verificó contra la fuente de verdad (XML
// crudo del .xlsx + xl/theme/theme1.xml, resolviendo el color de tema
// "theme=4 tint=0.8" y "theme=8 tint=0.8" a mano con la fórmula de tint de
// OOXML) — el encabezado real es theme accent1 con tint 0.8 (≈ C1E5F5,
// básicamente el mismo azul `$bgEncabezado` de la hoja Cuota/Categoría) y
// Nombres es theme accent5 con tint 0.8 (≈ F2CFEE, básicamente el mismo
// rosa `$bgClienteDato` de esa misma hoja) — se reusan esas 2 variables ya
// definidas arriba en vez de declarar valores nuevos, para que ambas hojas
// queden con el mismo azul/rosa exactos.
$bgEncVis = $bgEncabezado; $bgClienteVis = $bgClienteDato;
// Columnas (sin "KP", que en el archivo real es una fórmula rota #REF! sin uso real):
// 1 CEDI, 2 Nombres, 3 PLAN, 4-6 CANTIDAD(Cab/Isla/Percha), 7-9 PAGO(Cab/Isla/Percha),
// 10 PAGO TOTAL, 11-13 MARCA(Cab/Isla/Percha), 14-16 VALIDACIÓN(Cab/Isla/Percha),
// 17-19 Cab/Isla/Percha "validado" (sin cabecera de grupo), 20 TOTAL, 21 OBSERVACION.
$vCedi = 1; $vNombres = 2; $vPlan = 3;
$vCantCab = 4; $vCantIsla = 5; $vCantPercha = 6;
$vPagoCab = 7; $vPagoIsla = 8; $vPagoPercha = 9; $vPagoTotal = 10;
$vMarcaCab = 11; $vMarcaIsla = 12; $vMarcaPercha = 13;
$vValidCab = 14; $vValidIsla = 15; $vValidPercha = 16;
$vRCab = 17; $vRIsla = 18; $vRPercha = 19; $vTotal = 20; $vObs = 21;

// Centrado (pedido explícito del usuario): los títulos de grupo combinados
// (fila 1) y los sub-encabezados CABECERA/ISLA/PERCHA repetidos (fila 2)
// quedaban pegados a la izquierda por default de OOXML en una celda
// fusionada — se centran horizontal y vertical.
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

// Mismo bug que la hoja Cuota/Categoría (ver más arriba, 2026-08-20): las
// columnas que NO están dentro de ninguna de las 4 fusiones de arriba
// (CEDI/NOMBRES/PLAN, PAGO TOTAL, TOTAL, OBSERVACION, y las 3 "validado"
// sin cabecera de grupo) no tenían NINGUNA celda en fila 1 — quedaban sin
// pintar/sin borde. Se rellenan con string vacío y el mismo color/centrado
// que su celda de fila 2 debajo.
foreach ([$vCedi, $vNombres, $vPlan, $vRCab, $vRIsla, $vRPercha, $vPagoTotal, $vTotal, $vObs] as $col) {
	$wb->celda($s3, 1, $col, '', false, null, $bgEncVis, '000000', true);
}

// ---------- Hoja "VISIBILIDAD": filas de datos ----------
$filaVisDatos = $filaEncVis + 1;
$primeraFilaVis = $filaVisDatos;
foreach ($porClienteVis as $cliente => $datosCliente) {
	$wb->celda($s3, $filaVisDatos, $vCedi, $datosCliente['ejecutivo']);
	$wb->celda($s3, $filaVisDatos, $vNombres, $cliente, false, null, $bgClienteVis, '000000');
	// PLAN vacío a propósito, ver nota arriba.
	$wb->celda($s3, $filaVisDatos, $vPlan, '');

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

	// VALIDACIÓN: vacía, la llena JW a mano.
	foreach ([$vValidCab, $vValidIsla, $vValidPercha] as $col) $wb->celda($s3, $filaVisDatos, $col, '');

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
// Fórmulas leídas EXACTAS del archivo real (fila 46 de
// datos/LIQUIDACION ACUERDOS COMERCIALES Q2 DIRECTA 2026.xlsx, vía Excel
// COM) — a propósito NO son todas iguales: CANTIDAD/PAGO usan SUM() normal
// (no SUBTOTAL como en la otra hoja), PAGO TOTAL repite el mismo patrón
// "=+G+H+I" de las filas de datos (no una suma de rango), MARCA/VALIDACIÓN/
// R-S-T quedan en blanco (así está en el original, no se totalizan), y solo
// la columna TOTAL final usa SUBTOTAL(9,...) — se replica tal cual aunque
// la mezcla de SUM/SUBTOTAL no sea consistente, porque así está en la
// plantilla real que usa JW.
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
}

$bin = $wb->generar();

$nombreArchivo = 'CuotaCategoria_Directa_'.date('Y-m-d').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$nombreArchivo.'"');
header('Content-Length: '.strlen($bin));
echo $bin;
exit;
?>
