<?php
// Paso 2: UPSERT de las filas planas en repositorio_acuerdo_completo_linea, nunca toca repositorio_cuota_cliente. Mismo criterio que cuotas_guardar.php.
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

$body      = json_decode(file_get_contents('php://input'), true);
$filas     = is_array($body['filas'] ?? null) ? $body['filas'] : [];
$trimestre = (int) ($body['trimestre'] ?? 0);
$anio      = (int) ($body['anio'] ?? 0);
$canal     = ($body['canal'] ?? '') === 'distribuidor' ? 'distribuidor' : 'directo';

if (!$filas) responder(false, 'No hay filas para guardar.');
if ($trimestre < 1 || $trimestre > 4) responder(false, 'Trimestre inválido.');
$anioActual = (int) date('Y');
if ($anio < $anioActual - 1 || $anio > $anioActual + 1) responder(false, 'Año inválido.');

// null si la celda vino vacía (ese bloque no aplica a esta línea); número si vino algo, sin importar el signo (se valida aparte).
function acuerdo_completo_leer_opcional($v) {
	if ($v === null || $v === '') return null;
	return is_numeric($v) ? (float) $v : (float) str_replace(['$', ',', ' '], '', (string) $v);
}

$usuarioSesion = $_SESSION['user_id'] ?? null;
$mesInicio = ($trimestre - 1) * 3;
$guardadas = 0; $nuevas = 0; $actualizadas = 0; $sinCambios = 0; $descartadas = 0;
$errores = []; $avisos = [];
$clavesVistas = []; // posId_o_cliente|tipo|sector|categoria|marca -> índice, duplicado dentro del mismo archivo

$mysqli->begin_transaction();
try {
	$stmt = $mysqli->prepare(
		'INSERT INTO repositorio_acuerdo_completo_linea
		 (pos_id, cliente_excel, cedi_excel, usuario_excel, plan, tipo, sector, categoria, marca, codigo, ruc, cantidad_max_percha, valores_mensuales, valor_mensual_unico, trimestre, anio, estado, actualizado_por)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
		 ON DUPLICATE KEY UPDATE
		   cliente_excel = VALUES(cliente_excel), cedi_excel = VALUES(cedi_excel), usuario_excel = VALUES(usuario_excel), plan = VALUES(plan),
		   codigo = VALUES(codigo), ruc = VALUES(ruc), cantidad_max_percha = VALUES(cantidad_max_percha),
		   valores_mensuales = VALUES(valores_mensuales), valor_mensual_unico = VALUES(valor_mensual_unico),
		   estado = VALUES(estado), actualizado_por = VALUES(actualizado_por), updated_at = NOW()'
	);
	if (!$stmt) throw new Exception('El Repositorio de Acuerdo Completo todavía no está disponible. Avisa al equipo técnico.');

	$stmtCheckGrupo = $mysqli->prepare(
		"SELECT a.documento_no, a.created_at, u.usuario
		 FROM repositorio_acuerdo_completo_linea c
		 LEFT JOIN repositorio_acuerdos a ON a.id = c.acuerdo_id_generado
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
		 WHERE c.pos_id = ? AND c.trimestre = ? AND c.anio = ? AND c.estado = 'usada' LIMIT 1"
	);

	// Si el Excel ya no trae Cabecera/Ruma/Percha para una línea que antes sí la tenía, esa tabla vieja se descarta (nunca se toca si ya está 'usada').
	$stmtDescartarTabla = $mysqli->prepare(
		"UPDATE repositorio_acuerdo_completo_linea
		 SET estado = 'descartada', actualizado_por = ?, updated_at = NOW()
		 WHERE pos_id = ? AND tipo = ? AND sector = '' AND categoria = ? AND marca = ? AND trimestre = ? AND anio = ?
		   AND estado NOT IN ('usada', 'descartada')"
	);

	$cacheSector = [];
	$cachePosId  = [];
	$cacheUsada  = []; // pos_id -> datos de la Acta existente, o false si no está usada

	// Canal: siempre el elegido/detectado al subir el archivo (nunca inferido del contenido de otra columna) — UPDATE aparte, silencioso si el ALTER de esta columna todavía no corrió.
	$stmtCanal = $mysqli->prepare(
		'UPDATE repositorio_acuerdo_completo_linea SET canal = ? WHERE pos_id = ? AND tipo = ? AND sector = ? AND categoria = ? AND marca = ? AND trimestre = ? AND anio = ? LIMIT 1'
	);

	// Inserta/actualiza UNA línea y devuelve 'nueva'/'actualizada'/'sin_cambios'/null (error).
	$insertarLinea = function ($identidad, $tipo, $sector, $categoria, $marca, $cantidad, $valoresMensuales, $valorUnico) use ($stmt, $usuarioSesion, $stmtCanal, $canal) {
		$valoresJson = $valoresMensuales !== null ? json_encode($valoresMensuales) : null;
		$estadoLinea = $identidad['pos_id'] ? 'pendiente_uso' : 'pendiente_match';
		$stmt->bind_param(
			'sssssssssssisdiisi',
			$identidad['pos_id'], $identidad['cliente_excel'], $identidad['cedi_excel'], $identidad['usuario_excel'], $identidad['plan'],
			$tipo, $sector, $categoria, $marca, $identidad['codigo'], $identidad['ruc'],
			$cantidad, $valoresJson, $valorUnico, $identidad['trimestre'], $identidad['anio'], $estadoLinea, $usuarioSesion
		);
		if (!$stmt->execute()) return null;
		if ($identidad['pos_id'] && $stmtCanal) {
			$stmtCanal->bind_param('ssssssii', $canal, $identidad['pos_id'], $tipo, $sector, $categoria, $marca, $identidad['trimestre'], $identidad['anio']);
			$stmtCanal->execute();
		}
		if ($stmt->affected_rows === 1) return 'nueva';
		if ($stmt->affected_rows === 2) return 'actualizada';
		return 'sin_cambios';
	};

	foreach ($filas as $indice => $fila) {
		$clienteExcel = repositorio_normalizar_texto($fila['cliente_excel'] ?? '');
		$cediExcel    = repositorio_normalizar_texto($fila['cedi_excel'] ?? '');
		$usuarioExcel = trim((string) ($fila['usuario_excel'] ?? ''));
		$plan         = repositorio_normalizar_texto($fila['plan'] ?? '');
		$sector       = repositorio_normalizar_texto($fila['sector'] ?? '');
		$subcategoria = repositorio_normalizar_texto($fila['subcategoria'] ?? '');
		$marca        = repositorio_normalizar_texto($fila['marca'] ?? '');
		$codigo       = trim((string) ($fila['codigo'] ?? ''));
		$ruc          = trim((string) ($fila['ruc'] ?? ''));
		$mes1 = is_numeric($fila['mes1'] ?? null) ? round((float) $fila['mes1'], 2) : null;
		$mes2 = is_numeric($fila['mes2'] ?? null) ? round((float) $fila['mes2'], 2) : null;
		$mes3 = is_numeric($fila['mes3'] ?? null) ? round((float) $fila['mes3'], 2) : null;
		$etiqueta = $clienteExcel !== '' ? $clienteExcel.' / '.$sector.' / '.$marca : '(fila vacía)';

		$faltantes = [];
		if ($clienteExcel === '') $faltantes[] = 'Cliente';
		if ($sector === '') $faltantes[] = 'Categoría';
		if ($marca === '') $faltantes[] = 'Marca';
		if ($faltantes) { $errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Falta '.implode(', ', $faltantes)]; continue; }
		if ($sector === 'OTRAS CATEGORIAS') continue;
		if ($mes1 === null || $mes1 < 0 || $mes2 === null || $mes2 < 0 || $mes3 === null || $mes3 < 0) {
			$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Los 3 montos de Meta de Compras deben ser 0 o más']; continue;
		}

		$cabMes1 = acuerdo_completo_leer_opcional($fila['cab_mes1'] ?? null);
		$cabMes2 = acuerdo_completo_leer_opcional($fila['cab_mes2'] ?? null);
		$cabMes3 = acuerdo_completo_leer_opcional($fila['cab_mes3'] ?? null);
		$tieneCabecera = $cabMes1 !== null || $cabMes2 !== null || $cabMes3 !== null;
		$rumaValor = acuerdo_completo_leer_opcional($fila['ruma_valor'] ?? null);
		$perchaCantidad = acuerdo_completo_leer_opcional($fila['percha_cantidad'] ?? null);
		$perchaMes1 = acuerdo_completo_leer_opcional($fila['percha_mes1'] ?? null);
		$perchaMes2 = acuerdo_completo_leer_opcional($fila['percha_mes2'] ?? null);
		$perchaMes3 = acuerdo_completo_leer_opcional($fila['percha_mes3'] ?? null);
		$tienePercha = $perchaCantidad !== null || $perchaMes1 !== null || $perchaMes2 !== null || $perchaMes3 !== null;

		if (($tieneCabecera && (($cabMes1 ?? 0) < 0 || ($cabMes2 ?? 0) < 0 || ($cabMes3 ?? 0) < 0))
			|| ($rumaValor !== null && $rumaValor < 0)
			|| ($tienePercha && (($perchaMes1 ?? 0) < 0 || ($perchaMes2 ?? 0) < 0 || ($perchaMes3 ?? 0) < 0 || ($perchaCantidad !== null && ($perchaCantidad < 0 || $perchaCantidad > 5))))
		) {
			$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Los valores de Cabecera/Ruma/Percha deben ser 0 o más (Percha: Cantidad entre 0 y 5)'];
			continue;
		}

		if (!array_key_exists($sector, $cacheSector)) $cacheSector[$sector] = resolverSectorReal($mysqli, $sector);
		$sectorResuelto = $cacheSector[$sector];
		if ($sectorResuelto === null) {
			$avisos[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se pudo identificar la categoría "'.$sector.'" en el catálogo. Revisar con JW.'];
		} elseif ($sectorResuelto !== $sector) {
			$sector = $sectorResuelto;
			$etiqueta = $clienteExcel.' / '.$sector.' / '.$marca;
		}

		$clavePos = $clienteExcel.'|'.$cediExcel;
		if (!array_key_exists($clavePos, $cachePosId)) {
			$cachePosId[$clavePos] = resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel, $canal, $plan, $diagnosticoNoUsado, $usuarioSesion);
		}
		$posId = $cachePosId[$clavePos];

		if ($posId) {
			if (!array_key_exists($posId, $cacheUsada)) {
				$cacheUsada[$posId] = false;
				if ($stmtCheckGrupo) {
					$stmtCheckGrupo->bind_param('sii', $posId, $trimestre, $anio);
					$stmtCheckGrupo->execute();
					$cacheUsada[$posId] = $stmtCheckGrupo->get_result()->fetch_assoc() ?: false;
				}
			}
			if ($cacheUsada[$posId]) {
				$existente = $cacheUsada[$posId];
				$avisos[] = [
					'indice' => $indice, 'fila' => $etiqueta,
					'motivo' => 'Este cliente ya generó su Acuerdo para este período. No se modificó.', 'tipo' => 'ya_usada',
					'existente_documento_no' => $existente['documento_no'], 'existente_usuario' => $existente['usuario'], 'existente_fecha' => $existente['created_at'],
				];
				continue;
			}
		}
		if (!$posId) {
			$avisos[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se pudo identificar el cliente. Queda en Pendientes de Asignar.'];
		}

		$identidad = [
			'pos_id' => $posId, 'cliente_excel' => $clienteExcel, 'cedi_excel' => $cediExcel, 'usuario_excel' => $usuarioExcel,
			'plan' => $plan, 'codigo' => $codigo, 'ruc' => $ruc, 'trimestre' => $trimestre, 'anio' => $anio,
		];

		$lineasAGuardar = [['meta_compra', $sector, $subcategoria, $marca, null, [(string) $mesInicio => $mes1, (string) ($mesInicio + 1) => $mes2, (string) ($mesInicio + 2) => $mes3], null]];
		if ($tieneCabecera) $lineasAGuardar[] = ['cabecera', '', $subcategoria, $marca, null, [(string) $mesInicio => $cabMes1 ?? 0, (string) ($mesInicio + 1) => $cabMes2 ?? 0, (string) ($mesInicio + 2) => $cabMes3 ?? 0], null];
		if ($rumaValor !== null) $lineasAGuardar[] = ['ruma', '', $subcategoria, $marca, null, null, round($rumaValor, 2)];
		if ($tienePercha) $lineasAGuardar[] = ['percha', '', $subcategoria, $marca, $perchaCantidad !== null ? (int) $perchaCantidad : null, [(string) $mesInicio => $perchaMes1 ?? 0, (string) ($mesInicio + 1) => $perchaMes2 ?? 0, (string) ($mesInicio + 2) => $perchaMes3 ?? 0], null];

		// Bloque que antes tenía datos y ahora el Excel lo manda vacío: se descarta la tabla vieja (solo con pos_id resuelto, si no no hay forma confiable de ubicarla).
		if ($posId && $stmtDescartarTabla) {
			$tiposOpcionales = ['cabecera' => $tieneCabecera, 'ruma' => $rumaValor !== null, 'percha' => $tienePercha];
			foreach ($tiposOpcionales as $tipoOpc => $presente) {
				if ($presente) continue;
				$stmtDescartarTabla->bind_param('issssii', $usuarioSesion, $posId, $tipoOpc, $subcategoria, $marca, $trimestre, $anio);
				$stmtDescartarTabla->execute();
				if ($stmtDescartarTabla->affected_rows > 0) $descartadas++;
			}
		}

		$algoFallo = false;
		foreach ($lineasAGuardar as $l) {
			list($tipoLinea, $sectorLinea, $categoriaLinea, $marcaLinea, $cantidadLinea, $valoresLinea, $valorUnicoLinea) = $l;
			$claveDup = ($posId ?: $clienteExcel).'|'.$tipoLinea.'|'.$sectorLinea.'|'.$categoriaLinea.'|'.$marcaLinea;
			if (isset($clavesVistas[$claveDup])) {
				$avisos[] = ['indice' => $clavesVistas[$claveDup], 'fila' => $etiqueta, 'motivo' => 'Línea repetida en el archivo. Se usó el valor más reciente.', 'tipo' => 'duplicado_archivo'];
			}
			$clavesVistas[$claveDup] = $indice;

			$resultado = $insertarLinea($identidad, $tipoLinea, $sectorLinea, $categoriaLinea, $marcaLinea, $cantidadLinea, $valoresLinea, $valorUnicoLinea);
			if ($resultado === null) { $algoFallo = true; continue; }
			$guardadas++;
			if ($resultado === 'nueva') $nuevas++;
			elseif ($resultado === 'actualizada') $actualizadas++;
			else $sinCambios++;
		}
		if ($algoFallo) $errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se pudo guardar alguna de las tablas de esta línea'];
	}
	$stmt->close();
	if ($stmtCheckGrupo) $stmtCheckGrupo->close();
	if ($stmtDescartarTabla) $stmtDescartarTabla->close();
	if ($stmtCanal) $stmtCanal->close();

	$mysqli->commit();
} catch (Exception $e) {
	$mysqli->rollback();
	responder(false, 'No se pudo guardar: '.$e->getMessage());
}

$omitidas = count($errores);
$avisosRelevantes = array_filter($avisos, function ($a) { return ($a['tipo'] ?? null) !== 'duplicado_archivo'; });
$partesDetalle = [];
if ($nuevas > 0) $partesDetalle[] = "$nuevas línea(s) nueva(s)";
if ($actualizadas > 0) $partesDetalle[] = "$actualizadas actualizada(s)";
if ($sinCambios > 0) $partesDetalle[] = "$sinCambios sin cambios (ya existían igual)";
$partesMensaje = [$partesDetalle ? 'Se guardaron '.implode(', ', $partesDetalle).'.' : 'No se guardó ninguna línea nueva.'];
if ($descartadas > 0) $partesMensaje[] = "$descartadas tabla(s) se quitaron por ya no venir en el archivo.";
if ($omitidas > 0) $partesMensaje[] = "$omitidas fila(s) no se guardaron. Revisá el detalle.";
if ($avisosRelevantes) $partesMensaje[] = count($avisosRelevantes).' fila(s) necesitan revisión. Revisá el detalle.';
responder(true, implode(' ', $partesMensaje), [
	'guardadas' => $guardadas, 'nuevas' => $nuevas, 'actualizadas' => $actualizadas, 'sin_cambios' => $sinCambios,
	'descartadas' => $descartadas, 'omitidas' => $omitidas, 'errores' => $errores, 'avisos' => $avisos,
]);
?>
