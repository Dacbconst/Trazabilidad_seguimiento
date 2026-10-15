<?php
// Mismo bug/arreglo que Historial: antes comparaba d.canal directo contra el maestro (ambiguo con pos_id duplicados). Ahora usa el canal de origen de la precarga (sqlCanalOrigenAcuerdo()).
function condicionCanalNegociacion($canal) {
	if ($canal === "directo") return " AND ".sqlCanalOrigenAcuerdo('a')." = 'DIRECTO'";
	if ($canal === "distribuidor") return " AND ".sqlCanalOrigenAcuerdo('a')." = 'DISTRIBUIDOR'";
	return "";
}

// ---------- Módulo "Resumen de Negociación" ---------- cuenta ACUERDOS, no filas: 5 filas de cabecera cuentan 1.
function resumen_negociacion_equipo($mysqli, $trimestre = 0, $anio = 0, $canal = "total") {
	barrer_actas_vencidas($mysqli);

	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;
	$anio            = (int) $anio;

	$vacio = ['stats' => ['total' => 0, 'rebate' => 0, 'cabeceras' => 0, 'rumas' => 0, 'perchas' => 0], 'equipo' => []];

	$stmt = $mysqli->prepare(
		"SELECT u.id AS usuario_id, u.usuario AS nombre,
		        COUNT(DISTINCT a.id) AS total,
		        COUNT(DISTINCT CASE WHEN l.tipo = 'meta_compra' THEN a.id END) AS rebate,
		        COUNT(DISTINCT CASE WHEN l.tipo = 'cabecera' THEN a.id END) AS cabeceras,
		        COUNT(DISTINCT CASE WHEN l.tipo = 'ruma' THEN a.id END) AS rumas,
		        COUNT(DISTINCT CASE WHEN l.tipo = 'percha' THEN a.id END) AS perchas
		 FROM repositorio_acuerdos a
		 JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
		 LEFT JOIN repositorio_acuerdo_lineas l ON l.acuerdo_id = a.id
		 WHERE a.estado <> 'anulado' AND a.acta_firmada_azure_path IS NOT NULL
		   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		   AND (? = 0 OR a.anio = ?)
		   ".condicionCanalNegociacion($canal)."
		 GROUP BY u.id, u.usuario
		 ORDER BY total DESC"
	);
	if (!$stmt) return $vacio;
	$stmt->bind_param('iiiii', $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmt->execute();
	$equipo = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	$stats = ['total' => 0, 'rebate' => 0, 'cabeceras' => 0, 'rumas' => 0, 'perchas' => 0];
	foreach ($equipo as &$u) {
		$u['usuario_id'] = (int) $u['usuario_id'];
		$u['total']      = (int) $u['total'];
		$u['rebate']     = (int) $u['rebate'];
		$u['cabeceras']  = (int) $u['cabeceras'];
		$u['rumas']      = (int) $u['rumas'];
		$u['perchas']    = (int) $u['perchas'];
		$u['iniciales']  = inicialesUsuario($u['nombre']);
		foreach (['total', 'rebate', 'cabeceras', 'rumas', 'perchas'] as $k) $stats[$k] += $u[$k];
	}
	unset($u);

	return ['stats' => $stats, 'equipo' => $equipo];
}

// $tipo mapea al ENUM real de repositorio_acuerdo_lineas.tipo. $tipoLinea sale de whitelist fija, seguro interpolar.
function listar_actas_negociacion_usuario($mysqli, $usuarioId, $trimestre = 0, $anio = 0, $tipo = 'todas', $canal = 'total') {
	$usuarioId = (int) $usuarioId;
	if (!$usuarioId) return [];

	$mapaTipos = ['rebate' => 'meta_compra', 'cabeceras' => 'cabecera', 'rumas' => 'ruma', 'perchas' => 'percha'];
	$tipoLinea = $mapaTipos[$tipo] ?? null;

	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;
	$anio            = (int) $anio;

	$condicionTipo = $tipoLinea ? "AND EXISTS (SELECT 1 FROM repositorio_acuerdo_lineas l WHERE l.acuerdo_id = a.id AND l.tipo = '$tipoLinea')" : '';

	// Solo el nombre del Acta acá; el detalle de tablas se pide aparte, al expandir, vía obtener_negociacion_detalle_acuerdo().
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.pos_id, a.documento_no,
		        COALESCE(
		          (SELECT cc.cliente_excel FROM repositorio_cuota_cliente cc WHERE cc.acuerdo_id_generado = a.id LIMIT 1),
		          (SELECT acl.cliente_excel FROM repositorio_acuerdo_completo_linea acl WHERE acl.acuerdo_id_generado = a.id LIMIT 1)
		        ) AS cliente
		 FROM repositorio_acuerdos a
		 WHERE a.creado_por = ?
		   AND a.estado <> 'anulado' AND a.acta_firmada_azure_path IS NOT NULL
		   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		   AND (? = 0 OR a.anio = ?)
		   $condicionTipo
		   ".condicionCanalNegociacion($canal)."
		 ORDER BY a.fecha_generacion DESC"
	);
	if (!$stmt) return [];
	$stmt->bind_param('iiiiii', $usuarioId, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	rellenarClienteDeActas($mysqli, $filas, 'cliente');
	return $filas;
}

// Detalle de un Acta para el droplist de Resumen de Negociación: qué tablas tiene y sus valores, calculado al vuelo.
function obtener_negociacion_detalle_acuerdo($mysqli, $acuerdoId) {
	$acuerdoId = (int) $acuerdoId;
	if (!$acuerdoId) return null;

	$stmt = $mysqli->prepare('SELECT mes_inicio, mes_fin, pos_id FROM repositorio_acuerdos WHERE id = ?');
	if (!$stmt) return null;
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$acuerdo = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$acuerdo) return null;

	// Mismo criterio que acta_pdf.php: mes_inicio/mes_fin son 0=Ene...11=Dic.
	$mesesCortoTodos = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
	$mesesActivos    = range((int) $acuerdo['mes_inicio'], (int) $acuerdo['mes_fin']);
	$mesesCorto      = array_map(function ($m) use ($mesesCortoTodos) { return $mesesCortoTodos[$m]; }, $mesesActivos);

	// Mismo canónico de canal que el resto de la app: necesario porque "Estimado" de Rebate se calcula distinto por canal.
	$esDistribuidor = false;
	$stmtCanal = $mysqli->prepare("SELECT 1 FROM repositorio_locales_supervisores_cliente WHERE pos_id = ? AND canal = 'DISTRIBUIDOR' LIMIT 1");
	if ($stmtCanal) {
		$stmtCanal->bind_param('s', $acuerdo['pos_id']);
		$stmtCanal->execute();
		$esDistribuidor = (bool) $stmtCanal->get_result()->fetch_assoc();
		$stmtCanal->close();
	}

	$stmt = $mysqli->prepare(
		'SELECT tipo, sector, categoria, marca, rebate_pct, cantidad_max_percha, participacion_pct, valores_mensuales, valor_mensual_unico
		 FROM repositorio_acuerdo_lineas WHERE acuerdo_id = ? ORDER BY tipo, orden'
	);
	if (!$stmt) return null;
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$lineas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	// Misma etiqueta que acta_pdf.php: Rebate muestra SOLO Categoría, Cabecera/Ruma SOLO Marca, Percha Marca+Participación+Max Percha.
	$porTipo = ['meta_compra' => [], 'cabecera' => [], 'ruma' => [], 'percha' => []];
	foreach ($lineas as $l) {
		// Ruma: un solo valor tipeado que se repite en todos los meses del período, nunca JSON por mes.
		if ($l['tipo'] === 'ruma') {
			$valoresPorMes = array_fill(0, count($mesesActivos), (float) $l['valor_mensual_unico']);
		} else {
			$decoded = $l['valores_mensuales'] ? json_decode($l['valores_mensuales'], true) : [];
			$valoresPorMes = array_map(function ($m) use ($decoded) { return (float) ($decoded[(string) $m] ?? 0); }, $mesesActivos);
		}
		// "Total" es siempre la suma mensual cruda. "Estimado" (Rebate) usa la fórmula exacta de acta_pdf.php, copiada tal cual.
		$sumaMensual = array_sum($valoresPorMes);
		$rebateRaw = (float) $l['rebate_pct'];
		$estimado = $l['tipo'] === 'meta_compra' ? ($sumaMensual * $rebateRaw) : null;

		$porTipo[$l['tipo']][] = [
			'etiqueta'            => $l['tipo'] === 'meta_compra' ? $l['sector'] : $l['marca'],
			'participacion'       => $l['tipo'] === 'percha' ? ($l['participacion_pct'] !== null && $l['participacion_pct'] !== '' ? $l['participacion_pct'] : null) : null,
			'rebate_pct'          => $l['tipo'] === 'meta_compra' ? round($rebateRaw * 100, 2) : null,
			'cantidad_max_percha' => $l['tipo'] === 'percha' ? (int) $l['cantidad_max_percha'] : null,
			'valores'             => array_map(function ($v) { return round($v, 2); }, $valoresPorMes),
			'total'               => round($sumaMensual, 2),
			'estimado'            => $estimado !== null ? round($estimado, 2) : null,
		];
	}
	// Mismo criterio que $fmt en generar_acta_html(): Distribuidor mide en Cajas (sin "$"), Directo en Dólares.
	return ['meses' => $mesesCorto, 'formato' => $esDistribuidor ? 'numero' : 'moneda', 'tablas' => $porTipo];
}
