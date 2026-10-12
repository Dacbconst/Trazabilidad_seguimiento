<?php
// Cola de resolución manual, agrupada por cliente (todos los tipos de un mismo cliente se resuelven juntos) — mismo patrón que listar_repositorio_cuotas_pendientes_match().
function listar_repositorio_acuerdo_completo_pendientes_match($mysqli) {
	$stmt = $mysqli->prepare(
		"SELECT id, cliente_excel, cedi_excel, plan, sector, trimestre, anio, valores_mensuales
		 FROM repositorio_acuerdo_completo_linea
		 WHERE estado = 'pendiente_match'
		 ORDER BY cliente_excel, cedi_excel, plan, trimestre, anio, sector"
	);
	if (!$stmt) return [];
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	$grupos = [];
	$ordenGrupos = [];
	foreach ($filas as $fila) {
		$valores = $fila['valores_mensuales'] !== null ? json_decode($fila['valores_mensuales'], true) : [];
		$clave = $fila['cliente_excel'].'|'.$fila['cedi_excel'].'|'.$fila['plan'].'|'.$fila['trimestre'].'|'.$fila['anio'];
		if (!isset($grupos[$clave])) {
			$grupos[$clave] = [
				'ids' => [], 'cliente_excel' => $fila['cliente_excel'], 'cedi_excel' => $fila['cedi_excel'],
				'plan' => $fila['plan'], 'trimestre' => (int) $fila['trimestre'], 'anio' => (int) $fila['anio'],
				'categorias' => [], 'monto_total' => 0,
			];
			$ordenGrupos[] = $clave;
		}
		$grupos[$clave]['ids'][] = (int) $fila['id'];
		$grupos[$clave]['categorias'][] = $fila['sector'] !== '' ? $fila['sector'] : $fila['marca'];
		$grupos[$clave]['monto_total'] += is_array($valores) ? array_sum($valores) : 0;
	}

	// Solo nuestra base propia (ver resolverPosIdCliente()) — el maestro de Alicorp ya no aplica a este repositorio.
	$stmtCand = $mysqli->prepare(
		"SELECT pos_id, cliente AS pos_name, cedi, cedi AS supervisor FROM repositorio_clientes_propiosac
		 WHERE cliente LIKE CONCAT(?, '%') ORDER BY cliente LIMIT 10"
	);
	$resultado = [];
	foreach ($ordenGrupos as $clave) {
		$g = $grupos[$clave];
		$g['candidatos'] = [];
		if ($stmtCand) {
			$stmtCand->bind_param('s', $g['cliente_excel']);
			$stmtCand->execute();
			$g['candidatos'] = $stmtCand->get_result()->fetch_all(MYSQLI_ASSOC);
		}
		$resultado[] = $g;
	}
	if ($stmtCand) $stmtCand->close();
	return $resultado;
}

// Dueño real del grupo, mismo criterio que usuarioIdDeCuota() (Usuario del Excel manda, CEDI de respaldo, maestro al final).
function usuarioIdDeAcuerdoCompleto($mysqli, $posId, $trimestre, $anio) {
	$posName = null;
	$stmt = $mysqli->prepare(
		"SELECT cedi_excel, cliente_excel, usuario_excel FROM repositorio_acuerdo_completo_linea
		 WHERE pos_id = ? AND trimestre = ? AND anio = ? AND tipo = 'meta_compra' LIMIT 1"
	);
	if ($stmt) {
		$stmt->bind_param('sii', $posId, $trimestre, $anio);
		$stmt->execute();
		$fila = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		$posName = $fila['cliente_excel'] ?? null;
		$usuarioExacto = resolverUsuarioExacto($mysqli, $fila['usuario_excel'] ?? '');
		if ($usuarioExacto) return (int) $usuarioExacto['id'];
		$cedi = $fila ? trim((string) $fila['cedi_excel']) : '';
		if ($cedi !== '') {
			$stmtCedi = $mysqli->prepare(
				"SELECT id FROM repositorio_usuarios_acuerdos
				 WHERE status = 'activo'
				   AND (UPPER(TRIM(usuario)) = UPPER(TRIM(?)) OR UPPER(TRIM(supervisor)) = UPPER(TRIM(?)))
				 LIMIT 1"
			);
			if ($stmtCedi) {
				$stmtCedi->bind_param('ss', $cedi, $cedi);
				$stmtCedi->execute();
				$filaCedi = $stmtCedi->get_result()->fetch_assoc();
				$stmtCedi->close();
				if ($filaCedi) return (int) $filaCedi['id'];
			}
		}
	}
	return usuarioIdDePosId($mysqli, $posId, $posName);
}

// Clientes listos para generar su Acuerdo, mismo criterio que listar_actas_precargadas_pendientes().
function listar_acuerdos_completos_pendientes($mysqli, $usuarioId) {
	if (!$usuarioId) return [];
	$stmt = $mysqli->prepare(
		"SELECT c.pos_id, c.cliente_excel, c.trimestre, c.anio, c.sector, c.valores_mensuales, c.updated_at
		 FROM repositorio_acuerdo_completo_linea c
		 LEFT JOIN repositorio_usuarios_acuerdos u_usuario
		   ON u_usuario.status = 'activo' AND UPPER(TRIM(u_usuario.usuario)) = UPPER(TRIM(c.usuario_excel)) AND c.usuario_excel <> ''
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN (SELECT pos_id, MIN(supervisor) AS supervisor FROM repositorio_locales_supervisores_cliente GROUP BY pos_id) m ON m.pos_id = c.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u_master ON u_master.supervisor = m.supervisor AND u_master.status = 'activo'
		 WHERE c.tipo = 'meta_compra' AND c.estado = 'pendiente_uso' AND COALESCE(u_usuario.id, u_cedi.id, u_master.id) = ?
		 ORDER BY c.anio DESC, c.trimestre DESC, c.cliente_excel"
	);
	if (!$stmt) return [];
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$filasCrudas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	$grupos = [];
	foreach ($filasCrudas as $f) {
		$valores = $f['valores_mensuales'] !== null ? json_decode($f['valores_mensuales'], true) : [];
		if (!is_array($valores) || array_sum($valores) <= 0) continue;
		$clave = $f['pos_id'].'|'.$f['trimestre'].'|'.$f['anio'];
		if (!isset($grupos[$clave])) {
			$grupos[$clave] = ['pos_id' => $f['pos_id'], 'cliente_excel' => $f['cliente_excel'], 'trimestre' => $f['trimestre'], 'anio' => $f['anio'], 'categorias' => 0, 'actualizado_en' => $f['updated_at']];
		}
		$grupos[$clave]['categorias']++;
		if ($f['updated_at'] > $grupos[$clave]['actualizado_en']) $grupos[$clave]['actualizado_en'] = $f['updated_at'];
	}
	return array_values($grupos);
}

// Arma el detalle completo (4 tipos) para Registrar — Segmento se resuelve 1 vez por línea de Meta y se reusa en Cabecera/Ruma/Percha de la misma Categoría+Marca.
function obtener_acuerdo_completo_detalle($mysqli, $posId, $trimestre, $anio) {
	$stmt = $mysqli->prepare(
		"SELECT id, tipo, cliente_excel, plan, sector, categoria, marca, cantidad_max_percha, valores_mensuales, valor_mensual_unico
		 FROM repositorio_acuerdo_completo_linea
		 WHERE pos_id = ? AND trimestre = ? AND anio = ? AND estado = 'pendiente_uso'
		 ORDER BY FIELD(tipo,'meta_compra','cabecera','ruma','percha')"
	);
	if (!$stmt) return null;
	$stmt->bind_param('sii', $posId, $trimestre, $anio);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	if (!$filas) return null;

	$primeraMeta = null;
	foreach ($filas as $f) { if ($f['tipo'] === 'meta_compra') { $primeraMeta = $f; break; } }
	// Desambiguado por nombre + canal de origen (mismo bug/criterio que obtener_precarga_detalle()): este era el que faltaba, por eso la campanita abría el Acta en el canal equivocado.
	$canalOrigenCompleto = ($primeraMeta['plan'] ?? '') !== '' ? 'distribuidor' : 'directo';
	$cliente = clienteMaestroDePosId($mysqli, $posId, $primeraMeta['cliente_excel'] ?? null, $canalOrigenCompleto);
	if (!$cliente) return null;

	$esDistribuidorRebate = ($cliente['canal'] ?? null) === 'DISTRIBUIDOR';
	$ciudadRebate = $esDistribuidorRebate ? 'TODAS' : ($cliente['cedi'] ?: '');
	$canalRebate  = $esDistribuidorRebate ? 'DISTRIBUIDOR' : 'DIRECTA';

	$lineasMeta = []; $lineasCab = []; $lineasRuma = []; $lineasPercha = [];
	$segmentoPorClave = []; // "categoria|marca" -> segmento, resuelto al procesar meta_compra, reusado por los otros 3 tipos.

	foreach ($filas as $f) {
		$valores = $f['valores_mensuales'] !== null ? json_decode($f['valores_mensuales'], true) : [];
		$valores = is_array($valores) ? $valores : [];
		$claveProducto = $f['categoria'].'|'.$f['marca'];

		if ($f['tipo'] === 'meta_compra') {
			$match = resolverProductoCuota($mysqli, $f['sector'], $f['categoria'], $f['marca']);
			$segmento = $match['segmento'] ?? null;
			$categoriaFinal = $match['categoria'] ?? $f['categoria'];
			$marcaFinal = $match['marca'] ?? $f['marca'];
			$segmentoPorClave[$claveProducto] = $segmento;

			$rebatePct = 0;
			if ($categoriaFinal !== '' && $marcaFinal !== '') {
				$valorRebate = buscarRebateProducto($mysqli, $ciudadRebate, $canalRebate, $f['sector'], $categoriaFinal, $marcaFinal);
				if ($valorRebate !== null) $rebatePct = $valorRebate;
			}
			$lineasMeta[] = [
				'segmento' => $segmento, 'sector' => $f['sector'], 'categoria' => $categoriaFinal, 'marca' => $marcaFinal,
				'rebate_pct' => $rebatePct, 'valores_mensuales' => $valores, 'bloqueado' => true,
			];
		} elseif ($f['tipo'] === 'cabecera') {
			$segmento = $segmentoPorClave[$claveProducto] ?? null;
			$lineasCab[] = ['segmento' => $segmento, 'categoria' => $f['categoria'], 'marca' => $f['marca'], 'valores_mensuales' => $valores, 'bloqueado' => true];
		} elseif ($f['tipo'] === 'ruma') {
			$segmento = $segmentoPorClave[$claveProducto] ?? null;
			$lineasRuma[] = ['segmento' => $segmento, 'categoria' => $f['categoria'], 'marca' => $f['marca'], 'valor_mensual_unico' => $f['valor_mensual_unico'] !== null ? (float) $f['valor_mensual_unico'] : 0, 'bloqueado' => true];
		} elseif ($f['tipo'] === 'percha') {
			$participacionPct = 0;
			$valorPart = buscarParticipacionPercha($mysqli, $esDistribuidorRebate ? 'TODAS' : ($cliente['cedi'] ?: ''), $f['marca']);
			if ($valorPart !== null) $participacionPct = $valorPart;
			$lineasPercha[] = [
				'categoria' => $f['categoria'], 'marca' => $f['marca'],
				'cantidad_max_percha' => $f['cantidad_max_percha'] !== null ? (int) $f['cantidad_max_percha'] : 0,
				'participacion' => $participacionPct ? number_format($participacionPct, 2).'%' : '',
				'valores_mensuales' => $valores, 'bloqueado' => true,
			];
		}
	}

	$mesInicio = ($trimestre - 1) * 3;
	return [
		'pos_id'          => $posId,
		'distribuidor'    => $cliente['pos_name'],
		'localidad'       => $cliente['cedi'] ?: '—',
		'anio'            => (int) $anio,
		'mes_inicio'      => $mesInicio,
		'mes_fin'         => $mesInicio + 2,
		'es_distribuidor' => $esDistribuidorRebate,
		'empresa_distribuidora' => $cliente['tipo_distribuidor'] ?: '',
		'empresa_distribuidora_excel' => $primeraMeta['plan'] ?? '',
		'lineas'          => ['meta_compra' => $lineasMeta, 'cabecera' => $lineasCab, 'ruma' => $lineasRuma, 'percha' => $lineasPercha],
	];
}

// Panorama para el superdesarrollador, mismo espíritu que resumen_cuotas() pero sin el toggle "Borrador" (sin casos reales todavía).
function resumen_acuerdo_completo($mysqli) {
	$grupos = [];
	$stmt = $mysqli->prepare(
		"SELECT pos_id, cliente_excel, cedi_excel, usuario_excel, trimestre, anio, sector, valores_mensuales, updated_at
		 FROM repositorio_acuerdo_completo_linea WHERE tipo = 'meta_compra' AND estado = 'pendiente_uso'
		 ORDER BY anio DESC, trimestre DESC, cliente_excel"
	);
	if ($stmt) {
		$stmt->execute();
		$filasCrudas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
		$stmt->close();
		foreach ($filasCrudas as $f) {
			$valores = $f['valores_mensuales'] !== null ? json_decode($f['valores_mensuales'], true) : [];
			if (!is_array($valores) || array_sum($valores) <= 0) continue;
			$clave = $f['pos_id'].'|'.$f['trimestre'].'|'.$f['anio'];
			if (!isset($grupos[$clave])) {
				$grupos[$clave] = ['pos_id' => $f['pos_id'], 'cliente_excel' => $f['cliente_excel'], 'cedi_excel' => $f['cedi_excel'], 'usuario_excel' => $f['usuario_excel'] ?? '', 'trimestre' => $f['trimestre'], 'anio' => $f['anio'], 'categorias' => 0, 'actualizado_en' => $f['updated_at']];
			}
			$grupos[$clave]['categorias']++;
			if ($f['updated_at'] > $grupos[$clave]['actualizado_en']) $grupos[$clave]['actualizado_en'] = $f['updated_at'];
		}
	}
	$grupos = array_values($grupos);
	$pendientes = count($grupos);

	$usadas = 0;
	$r = $mysqli->query(
		"SELECT COUNT(DISTINCT CONCAT(pos_id, '|', trimestre, '|', anio)) AS n
		 FROM repositorio_acuerdo_completo_linea WHERE tipo = 'meta_compra' AND estado = 'usada'"
	);
	if ($r) $usadas = (int) $r->fetch_assoc()['n'];

	$pendientesMatch = 0;
	$r = $mysqli->query("SELECT COUNT(DISTINCT cliente_excel, trimestre, anio) AS n FROM repositorio_acuerdo_completo_linea WHERE estado = 'pendiente_match'");
	if ($r) $pendientesMatch = (int) $r->fetch_assoc()['n'];

	// Borradores: mismo concepto que resumen_cuotas() — la precarga ya está 'usada' pero el Acta vinculada no se terminó de generar.
	$gruposBorradorMapa = [];
	$rBorrador = $mysqli->query(
		"SELECT c.pos_id, c.cliente_excel, c.trimestre, c.anio, c.updated_at, u.usuario, a.id AS acuerdo_id, a.documento_no
		 FROM repositorio_acuerdo_completo_linea c
		 JOIN repositorio_acuerdos a ON a.id = c.acuerdo_id_generado
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
		 WHERE c.tipo = 'meta_compra' AND c.estado = 'usada' AND a.estado = 'borrador'"
	);
	if ($rBorrador) {
		while ($f = $rBorrador->fetch_assoc()) {
			$clave = $f['pos_id'].'|'.$f['trimestre'].'|'.$f['anio'];
			if (!isset($gruposBorradorMapa[$clave])) {
				$gruposBorradorMapa[$clave] = [
					'pos_id' => $f['pos_id'], 'cliente' => $f['cliente_excel'], 'trimestre' => (int) $f['trimestre'],
					'anio' => (int) $f['anio'], 'categorias' => 0, 'actualizado_en' => $f['updated_at'],
					'usuario' => $f['usuario'] ?: 'Sin identificar',
					'acuerdo_id' => (int) $f['acuerdo_id'], 'documento_no' => $f['documento_no'],
				];
			}
			$gruposBorradorMapa[$clave]['categorias']++;
			if ($f['updated_at'] > $gruposBorradorMapa[$clave]['actualizado_en']) $gruposBorradorMapa[$clave]['actualizado_en'] = $f['updated_at'];
		}
	}
	$gruposBorrador = array_values($gruposBorradorMapa);
	$borradores = count($gruposBorrador);

	$porUsuarioBorradorMapa = [];
	foreach ($gruposBorrador as $g) {
		if (!isset($porUsuarioBorradorMapa[$g['usuario']])) {
			$porUsuarioBorradorMapa[$g['usuario']] = ['nombre' => $g['usuario'], 'actas_pendientes' => 0, 'tiene_cuenta' => true, 'actas' => []];
		}
		$porUsuarioBorradorMapa[$g['usuario']]['actas_pendientes']++;
		$porUsuarioBorradorMapa[$g['usuario']]['actas'][] = [
			'pos_id' => $g['pos_id'], 'cliente' => $g['cliente'], 'trimestre' => $g['trimestre'],
			'anio' => $g['anio'], 'categorias' => $g['categorias'], 'actualizado_en' => $g['actualizado_en'],
			'acuerdo_id' => $g['acuerdo_id'], 'documento_no' => $g['documento_no'],
		];
	}
	$porUsuarioBorrador = array_values($porUsuarioBorradorMapa);
	usort($porUsuarioBorrador, function ($a, $b) { return $b['actas_pendientes'] <=> $a['actas_pendientes']; });

	$porUsuarioMapa = [];
	foreach ($grupos as $g) {
		$asignado = resolverNombreAsignadoCuota($mysqli, $g['pos_id'], $g['cedi_excel'], $g['cliente_excel'], $g['usuario_excel']);
		$nombre = $asignado['nombre'] ?: 'Sin identificar todavía';
		if (!isset($porUsuarioMapa[$nombre])) {
			$porUsuarioMapa[$nombre] = ['nombre' => $nombre, 'actas_pendientes' => 0, 'tiene_cuenta' => $asignado['tiene_cuenta'], 'actas' => []];
		}
		$porUsuarioMapa[$nombre]['actas_pendientes']++;
		$porUsuarioMapa[$nombre]['actas'][] = ['pos_id' => $g['pos_id'], 'cliente' => $g['cliente_excel'], 'trimestre' => $g['trimestre'], 'anio' => $g['anio'], 'categorias' => $g['categorias'], 'actualizado_en' => $g['actualizado_en']];
	}
	$porUsuario = array_values($porUsuarioMapa);
	usort($porUsuario, function ($a, $b) { return $b['actas_pendientes'] <=> $a['actas_pendientes']; });

	$chocan = [];
	foreach ($grupos as $g) {
		$mesInicio = ($g['trimestre'] - 1) * 3;
		$stmtChoque = $mysqli->prepare(
			"SELECT a.documento_no, a.created_at, u.usuario
			 FROM repositorio_acuerdos a LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
			 WHERE a.pos_id = ? AND a.anio = ? AND a.mes_inicio = ? AND a.mes_fin = ? AND a.estado NOT IN ('borrador','anulado') LIMIT 1"
		);
		if (!$stmtChoque) continue;
		$mesFin = $mesInicio + 2;
		$stmtChoque->bind_param('siii', $g['pos_id'], $g['anio'], $mesInicio, $mesFin);
		$stmtChoque->execute();
		$existente = $stmtChoque->get_result()->fetch_assoc();
		$stmtChoque->close();
		if (!$existente) continue;
		$asignado = resolverNombreAsignadoCuota($mysqli, $g['pos_id'], $g['cedi_excel'], $g['cliente_excel'], $g['usuario_excel']);
		$chocan[] = [
			'local' => $g['cliente_excel'], 'trimestre' => $g['trimestre'], 'anio' => $g['anio'], 'asignado_a' => $asignado['nombre'],
			'existente_documento_no' => $existente['documento_no'], 'existente_usuario' => $existente['usuario'], 'existente_fecha' => $existente['created_at'],
		];
	}

	return [
		'pendientes' => $pendientes, 'usadas' => $usadas, 'pendientes_match' => $pendientesMatch, 'borradores' => $borradores,
		'por_usuario' => $porUsuario, 'por_usuario_borrador' => $porUsuarioBorrador, 'chocan' => $chocan,
	];
}
