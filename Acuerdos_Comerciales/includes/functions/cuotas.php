<?php
// ---------- Actas Precargadas (Repositorio de Cuotas) ---------- resolución en vivo. Agrupa por (pos_id, trimestre, anio): varias filas son UNA sola Acta.
function listar_actas_precargadas_pendientes($mysqli, $usuarioId) {
	if (!$usuarioId) return [];
	// c.usuario_excel (2026-09-28) manda primero, igual que resolverNombreAsignadoCuota()/usuarioIdDeCuota() — sin esto, una fila con USUARIO bien tipeado igual no aparecía acá (bug real reportado). Subquery en vez de JOIN directo: pos_id no es único en el maestro, duplicaría la Acta si hay 2+ filas.
	$stmt = $mysqli->prepare(
		"SELECT c.pos_id, c.cliente_excel, c.trimestre, c.anio, c.sector, c.valores_mensuales, c.updated_at
		 FROM repositorio_cuota_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u_usuario
		   ON u_usuario.status = 'activo' AND UPPER(TRIM(u_usuario.usuario)) = UPPER(TRIM(c.usuario_excel)) AND c.usuario_excel <> ''
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN (SELECT pos_id, MIN(supervisor) AS supervisor FROM repositorio_locales_supervisores_cliente GROUP BY pos_id) m ON m.pos_id = c.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u_master ON u_master.supervisor = m.supervisor AND u_master.status = 'activo'
		 WHERE c.estado = 'pendiente_uso' AND COALESCE(u_usuario.id, u_cedi.id, u_master.id) = ?
		 ORDER BY c.anio DESC, c.trimestre DESC, c.cliente_excel"
	);
	if (!$stmt) $stmt = $mysqli->prepare(
		"SELECT c.pos_id, c.cliente_excel, c.trimestre, c.anio, c.sector, c.valores_mensuales, c.updated_at
		 FROM repositorio_cuota_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN (SELECT pos_id, MIN(supervisor) AS supervisor FROM repositorio_locales_supervisores_cliente GROUP BY pos_id) m ON m.pos_id = c.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u_master ON u_master.supervisor = m.supervisor AND u_master.status = 'activo'
		 WHERE c.estado = 'pendiente_uso' AND COALESCE(u_cedi.id, u_master.id) = ?
		 ORDER BY c.anio DESC, c.trimestre DESC, c.cliente_excel"
	);
	if (!$stmt) return [];
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$filasCrudas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	// Se cuenta en PHP para coincidir con lo que ve el asesor. `actualizado_en` cambia si el cliente se resube/reasigna.
	$grupos = [];
	foreach ($filasCrudas as $f) {
		$valores = $f['valores_mensuales'] !== null ? json_decode($f['valores_mensuales'], true) : [];
		if (!is_array($valores) || array_sum($valores) <= 0) continue;
		$clave = $f['pos_id'].'|'.$f['trimestre'].'|'.$f['anio'];
		if (!isset($grupos[$clave])) {
			$grupos[$clave] = ['pos_id' => $f['pos_id'], 'cliente_excel' => $f['cliente_excel'], 'trimestre' => $f['trimestre'], 'anio' => $f['anio'], 'categorias' => 0, 'actualizado_en' => $f['updated_at'], 'origen' => 'cuotas'];
		}
		$grupos[$clave]['categorias']++;
		if ($f['updated_at'] > $grupos[$clave]['actualizado_en']) $grupos[$clave]['actualizado_en'] = $f['updated_at'];
	}

	// Fusión con "Acuerdo Completo" (2026-10-03) — misma lista de "Actas Asignadas" en la campanita, sin distinguir origen en la UI; 'origen' queda solo puertas adentro para saber a qué endpoint pegarle al hacer click.
	foreach (listar_acuerdos_completos_pendientes($mysqli, $usuarioId) as $g) {
		$g['origen'] = 'completo';
		$grupos[$g['pos_id'].'|'.$g['trimestre'].'|'.$g['anio'].'|completo'] = $g;
	}

	$resultado = array_values($grupos);
	usort($resultado, function ($a, $b) {
		return $b['anio'] <=> $a['anio'] ?: $b['trimestre'] <=> $a['trimestre'] ?: strcmp($a['cliente_excel'], $b['cliente_excel']);
	});
	return $resultado;
}

// Arma el detalle de una Acta precargada para poblar Registrar. Segmento/Categoría/Marca vienen del Excel o, si falta, del historial del cliente.
function obtener_precarga_detalle($mysqli, $posId, $trimestre, $anio) {
	// Sin rebate_pct: se busca abajo vía buscarRebateProducto().
	$stmt = $mysqli->prepare(
		"SELECT id, cliente_excel, plan, sector, subcategoria, marca, valores_mensuales FROM repositorio_cuota_cliente
		 WHERE pos_id = ? AND trimestre = ? AND anio = ? AND estado = 'pendiente_uso'
		 ORDER BY sector"
	);
	// Fallback si subcategoria/marca todavía no existen en la base: nunca tumbar la Acta por columnas nuevas.
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT id, cliente_excel, plan, sector, NULL AS subcategoria, NULL AS marca, valores_mensuales FROM repositorio_cuota_cliente
			 WHERE pos_id = ? AND trimestre = ? AND anio = ? AND estado = 'pendiente_uso'
			 ORDER BY sector"
		);
	}
	if (!$stmt) return null;
	$stmt->bind_param('sii', $posId, $trimestre, $anio);
	$stmt->execute();
	$filasCuota = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	if (!$filasCuota) return null;

	// Desambiguado por nombre + canal de origen (bug real: el mismo pos_name puede existir bajo 2 canales distintos en el maestro, ver clienteMaestroDePosId()).
	$canalOrigenCuota = ($filasCuota[0]['plan'] ?? '') !== '' ? 'distribuidor' : 'directo';
	$cliente = clienteMaestroDePosId($mysqli, $posId, $filasCuota[0]['cliente_excel'] ?? null, $canalOrigenCuota);
	if (!$cliente) return null;

	$stmtHistorial = $mysqli->prepare(
		"SELECT l.segmento, l.categoria, l.marca
		 FROM repositorio_acuerdo_lineas l
		 JOIN repositorio_acuerdos a ON a.id = l.acuerdo_id
		 WHERE a.pos_id = ? AND l.tipo = 'meta_compra' AND l.sector = ?
		 ORDER BY a.created_at DESC LIMIT 1"
	);
	$stmtSegmentoPorSector = $mysqli->prepare(
		"SELECT DISTINCT segmento FROM repositorio_productos
		 WHERE fabricante = 'JABONERIA WILSON' AND sector = ? AND activar = 'SI'"
	);

	// Ciudad/Canal para buscar Rebate, mismo criterio que buscarYAplicarRebate() en registrar.js.
	$esDistribuidorRebate = ($cliente['canal'] ?? null) === 'DISTRIBUIDOR';
	$ciudadRebate = $esDistribuidorRebate ? 'TODAS' : ($cliente['cedi'] ?: '');
	$canalRebate  = $esDistribuidorRebate ? 'DISTRIBUIDOR' : 'DIRECTA';
	$distribuidorRebate = $esDistribuidorRebate ? ($cliente['tipo_distribuidor'] ?? '') : '';

	$lineasMeta = [];
	foreach ($filasCuota as $fc) {
		$segmento = null; $categoria = null; $marca = null;
		// "Otras categorías": sin subcategoría ni marca ni rebate; se completa con su propio nombre para que el Acta la acepte.
		$esOtras = strtoupper(trim($fc['sector'])) === 'OTRAS CATEGORIAS';
		if ($esOtras) { $segmento = $categoria = $marca = 'OTRAS CATEGORIAS'; }

		// 1ra prioridad: SUBCATEGORIA/MARCA reales del Excel, más confiable que el historial.
		if (!$esOtras && !empty($fc['subcategoria']) && !empty($fc['marca'])) {
			$match = resolverProductoCuota($mysqli, $fc['sector'], $fc['subcategoria'], $fc['marca']);
			if ($match) {
				$segmento = $match['segmento'];
				$categoria = $match['categoria'];
				$marca = $match['marca'];
			}
		}

		// 2da prioridad: historial del cliente para lo que la 1ra no resolvió.
		if ($categoria === null && $stmtHistorial) {
			$stmtHistorial->bind_param('ss', $posId, $fc['sector']);
			$stmtHistorial->execute();
			$prev = $stmtHistorial->get_result()->fetch_assoc();
			if ($prev) {
				if ($segmento === null) $segmento = $prev['segmento'];
				$categoria = $prev['categoria'];
				$marca = $prev['marca'];
			}
		}
		if ($segmento === null && $stmtSegmentoPorSector) {
			$stmtSegmentoPorSector->bind_param('s', $fc['sector']);
			$stmtSegmentoPorSector->execute();
			$segmentos = array_column($stmtSegmentoPorSector->get_result()->fetch_all(MYSQLI_ASSOC), 'segmento');
			if (count($segmentos) === 1) $segmento = $segmentos[0];
		}
		$valores = $fc['valores_mensuales'] !== null ? json_decode($fc['valores_mensuales'], true) : [];
		$valores = is_array($valores) ? $valores : [];
		// Categoría con $0 en los 3 meses se descarta acá, si no quedaría atrapada sin poder eliminarse.
		if (array_sum($valores) <= 0) continue;

		// Rebate % se busca en repositorio_rebate_producto solo si Categoría+Marca se resolvieron.
		$rebatePct = 0;
		if (!$esOtras && $categoria !== null && $marca !== null) {
			$valorRebate = buscarRebateProducto($mysqli, $ciudadRebate, $canalRebate, $fc['sector'], $categoria, $marca, $distribuidorRebate, $cliente['pos_name'] ?? '');
			if ($valorRebate !== null) $rebatePct = $valorRebate;
		}

		$lineasMeta[] = [
			'cuota_id'          => (int) $fc['id'],
			'segmento'          => $segmento,
			'sector'            => $fc['sector'],
			'categoria'         => $categoria,
			'marca'             => $marca,
			'rebate_pct'        => $rebatePct,
			'valores_mensuales' => $valores,
			'bloqueado'         => true,
		];
	}
	if ($stmtHistorial) $stmtHistorial->close();
	if ($stmtSegmentoPorSector) $stmtSegmentoPorSector->close();

	$mesInicio = ($trimestre - 1) * 3;

	return [
		'pos_id'          => $posId,
		'distribuidor'    => $cliente['pos_name'],
		'localidad'       => $cliente['cedi'] ?: '—',
		'anio'            => (int) $anio,
		'mes_inicio'      => $mesInicio,
		'mes_fin'         => $mesInicio + 2,
		'es_distribuidor' => ($cliente['canal'] ?? null) === 'DISTRIBUIDOR',
		'empresa_distribuidora' => $cliente['tipo_distribuidor'] ?: '',
		// Texto literal del Excel (columna DISTRIBUIDOR/plan), para mostrar lo que se subió aunque no coincida exacto con el maestro.
		'empresa_distribuidora_excel' => $filasCuota[0]['plan'] ?? '',
		'lineas'          => ['meta_compra' => $lineasMeta, 'cabecera' => [], 'ruma' => [], 'percha' => []],
	];
}

// Todas las Actas precargadas pendientes, sin acotar a un usuarioId, para el panorama del superdesarrollador.
function listar_actas_precargadas_todas($mysqli) {
	// usuario_excel (2026-09-28) con fallback si ese ALTER no se corrió.
	$stmt = $mysqli->prepare(
		"SELECT pos_id, cliente_excel, cedi_excel, usuario_excel, trimestre, anio, sector, valores_mensuales, updated_at
		 FROM repositorio_cuota_cliente WHERE estado = 'pendiente_uso'
		 ORDER BY anio DESC, trimestre DESC, cliente_excel"
	);
	if (!$stmt) $stmt = $mysqli->prepare(
		"SELECT pos_id, cliente_excel, cedi_excel, trimestre, anio, sector, valores_mensuales, updated_at
		 FROM repositorio_cuota_cliente WHERE estado = 'pendiente_uso'
		 ORDER BY anio DESC, trimestre DESC, cliente_excel"
	);
	if (!$stmt) return [];
	$stmt->execute();
	$filasCrudas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	$grupos = [];
	foreach ($filasCrudas as $f) {
		$valores = $f['valores_mensuales'] !== null ? json_decode($f['valores_mensuales'], true) : [];
		if (!is_array($valores) || array_sum($valores) <= 0) continue;
		$clave = $f['pos_id'].'|'.$f['trimestre'].'|'.$f['anio'];
		if (!isset($grupos[$clave])) {
			$grupos[$clave] = [
				'pos_id' => $f['pos_id'], 'cliente_excel' => $f['cliente_excel'], 'cedi_excel' => $f['cedi_excel'],
				'usuario_excel' => $f['usuario_excel'] ?? '',
				'trimestre' => $f['trimestre'], 'anio' => $f['anio'], 'categorias' => 0, 'actualizado_en' => $f['updated_at'],
			];
		}
		$grupos[$clave]['categorias']++;
		if ($f['updated_at'] > $grupos[$clave]['actualizado_en']) $grupos[$clave]['actualizado_en'] = $f['updated_at'];
	}
	return array_values($grupos);
}

// Resumen para el superdesarrollador: 4 números de panorama + desglose por usuario. "Actas" = grupo (pos_id, trimestre, anio), no fila de sector.
function resumen_cuotas($mysqli) {
	$grupos = listar_actas_precargadas_todas($mysqli);
	$pendientes = count($grupos);

	$usadas = 0;
	// Excluye Actas que siguen en 'borrador' (bug real: contaba como "generada" un Acta que todavía ni se termina de llenar).
	$r = $mysqli->query(
		"SELECT COUNT(DISTINCT CONCAT(c.pos_id, '|', c.trimestre, '|', c.anio)) AS n
		 FROM repositorio_cuota_cliente c
		 LEFT JOIN repositorio_acuerdos a ON a.id = c.acuerdo_id_generado
		 WHERE c.estado = 'usada' AND (a.estado IS NULL OR a.estado <> 'borrador')"
	);
	if ($r) $usadas = (int) $r->fetch_assoc()['n'];

	$pendientesMatch = 0;
	$r = $mysqli->query("SELECT COUNT(DISTINCT c.cliente_excel, c.trimestre, c.anio) AS n FROM repositorio_cuota_cliente c WHERE c.estado = 'pendiente_match'");
	if ($r) $pendientesMatch = (int) $r->fetch_assoc()['n'];

	// Borradores: mismo concepto que "usadas" pero el Acta vinculada no se terminó de generar. Agrupado por a.creado_por directo (ya es el usuario real, sin inferir por CEDI/supervisor).
	$gruposBorradorMapa = [];
	$rBorrador = $mysqli->query(
		"SELECT c.pos_id, c.cliente_excel, c.trimestre, c.anio, c.updated_at, u.usuario, a.id AS acuerdo_id, a.documento_no
		 FROM repositorio_cuota_cliente c
		 JOIN repositorio_acuerdos a ON a.id = c.acuerdo_id_generado
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
		 WHERE c.estado = 'usada' AND a.estado = 'borrador'"
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

	// Resolución EN LOTE por rendimiento: 3 consultas fijas en vez de hasta 162 (una por grupo).
	$usuariosActivos = [];
	$rUsuarios = $mysqli->query("SELECT usuario, supervisor FROM repositorio_usuarios_acuerdos WHERE status = 'activo'");
	if ($rUsuarios) $usuariosActivos = $rUsuarios->fetch_all(MYSQLI_ASSOC);

	// cedi_excel (usuario O supervisor de la cuenta) -> usuario activo, mismo OR que usuarioIdDeCuota().
	$porCedi = [];
	// supervisor real -> usuario activo, para el respaldo del maestro.
	$porSupervisor = [];
	foreach ($usuariosActivos as $u) {
		if (($u['usuario'] ?? '') !== '') $porCedi[strtoupper(trim($u['usuario']))] = $u['usuario'];
		if (($u['supervisor'] ?? '') !== '') {
			$porCedi[strtoupper(trim($u['supervisor']))] = $u['usuario'];
			$porSupervisor[strtoupper(trim($u['supervisor']))] = $u['usuario'];
		}
	}

	// pos_id -> supervisor real del maestro (MIN porque un pos_id puede repetirse).
	$supervisorPorPosId = [];
	$rPos = $mysqli->query("SELECT pos_id, MIN(supervisor) AS supervisor FROM repositorio_locales_supervisores_cliente GROUP BY pos_id");
	if ($rPos) { while ($f = $rPos->fetch_assoc()) $supervisorPorPosId[$f['pos_id']] = $f['supervisor']; }

	// Usuario exacto -> usuario activo (2026-09-28, manda antes que CEDI/maestro, mismo criterio que resolverNombreAsignadoCuota()).
	$porUsuarioExacto = [];
	foreach ($usuariosActivos as $u) {
		if (($u['usuario'] ?? '') !== '') $porUsuarioExacto[strtoupper(trim($u['usuario']))] = $u['usuario'];
	}

	$porUsuarioMapa = [];
	$nombrePorClaveGrupo = [];
	foreach ($grupos as $g) {
		$usuarioExcel = strtoupper(trim((string) ($g['usuario_excel'] ?? '')));
		$cedi = strtoupper(trim((string) $g['cedi_excel']));
		$nombre = null; $tieneCuenta = false;
		if ($usuarioExcel !== '' && isset($porUsuarioExacto[$usuarioExcel])) {
			$nombre = $porUsuarioExacto[$usuarioExcel]; $tieneCuenta = true;
		} elseif ($usuarioExcel !== '') {
			// USUARIO vino tipeado pero no matchea ninguna cuenta activa: es el error a mostrar, no cae a CEDI/maestro (taparía el typo).
			$nombre = trim((string) $g['usuario_excel']);
		} elseif ($cedi !== '' && isset($porCedi[$cedi])) {
			$nombre = $porCedi[$cedi]; $tieneCuenta = true;
		} else {
			$supervisorReal = $supervisorPorPosId[$g['pos_id']] ?? null;
			if (($supervisorReal ?? '') !== '') {
				$claveSup = strtoupper(trim($supervisorReal));
				if (isset($porSupervisor[$claveSup])) { $nombre = $porSupervisor[$claveSup]; $tieneCuenta = true; }
				else { $nombre = $supervisorReal; $tieneCuenta = false; }
			}
		}
		$nombreClave = $nombre ?: 'Sin identificar';
		if (!isset($porUsuarioMapa[$nombreClave])) {
			$porUsuarioMapa[$nombreClave] = ['nombre' => $nombreClave, 'actas_pendientes' => 0, 'tiene_cuenta' => $tieneCuenta, 'actas' => []];
		}
		$porUsuarioMapa[$nombreClave]['actas_pendientes']++;
		$porUsuarioMapa[$nombreClave]['actas'][] = [
			'pos_id' => $g['pos_id'], 'cliente' => $g['cliente_excel'], 'trimestre' => (int) $g['trimestre'],
			'anio' => (int) $g['anio'], 'categorias' => $g['categorias'], 'actualizado_en' => $g['actualizado_en'],
		];
		// Reusado por "chocan" más abajo, sin repetir la búsqueda por pos_id.
		$nombrePorClaveGrupo[$g['pos_id'].'|'.$g['trimestre'].'|'.$g['anio']] = $nombre;
	}
	$porUsuario = array_values($porUsuarioMapa);
	usort($porUsuario, function ($a, $b) { return $b['actas_pendientes'] <=> $a['actas_pendientes']; });

	// Actas precargadas que ya no se van a poder generar (el Local ya tiene un Acuerdo activo en el período). En lote: 2 consultas fijas.
	$chocan = [];
	if ($grupos) {
		$existentesPorClave = [];
		$rExist = $mysqli->query(
			"SELECT a.pos_id, a.anio, a.mes_inicio, a.mes_fin, a.documento_no, a.created_at, u.usuario
			 FROM repositorio_acuerdos a
			 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
			 WHERE a.estado NOT IN ('borrador', 'anulado')"
		);
		if ($rExist) {
			while ($f = $rExist->fetch_assoc()) {
				$existentesPorClave[$f['pos_id'].'|'.$f['anio'].'|'.$f['mes_inicio'].'|'.$f['mes_fin']] = $f;
			}
		}

		$posIds = array_unique(array_column($grupos, 'pos_id'));
		$nombreClientePorPosId = [];
		if ($posIds) {
			$listaEscapada = implode(',', array_map(function ($id) use ($mysqli) { return "'".$mysqli->real_escape_string($id)."'"; }, $posIds));
			$rNombres = $mysqli->query("SELECT pos_id, MIN(pos_name) AS pos_name FROM repositorio_locales_supervisores_cliente WHERE pos_id IN ($listaEscapada) GROUP BY pos_id");
			if ($rNombres) { while ($f = $rNombres->fetch_assoc()) $nombreClientePorPosId[$f['pos_id']] = $f['pos_name']; }
		}

		foreach ($grupos as $g) {
			$mesInicio = ($g['trimestre'] - 1) * 3;
			$mesFin = $mesInicio + 2;
			$clave = $g['pos_id'].'|'.$g['anio'].'|'.$mesInicio.'|'.$mesFin;
			if (!isset($existentesPorClave[$clave])) continue;
			$existente = $existentesPorClave[$clave];

			$chocan[] = [
				'pos_id'             => $g['pos_id'],
				'local'              => $nombreClientePorPosId[$g['pos_id']] ?? $g['pos_id'],
				'trimestre'          => (int) $g['trimestre'],
				'anio'               => (int) $g['anio'],
				'asignado_a'         => $nombrePorClaveGrupo[$g['pos_id'].'|'.$g['trimestre'].'|'.$g['anio']] ?? null,
				'existente_documento_no' => $existente['documento_no'],
				'existente_usuario'  => $existente['usuario'],
				'existente_fecha'    => $existente['created_at'],
			];
		}
	}

	return [
		'pendientes'        => $pendientes,
		'usadas'            => $usadas,
		'borradores'        => $borradores,
		'pendientes_match'  => $pendientesMatch,
		'por_usuario'       => $porUsuario,
		'por_usuario_borrador' => $porUsuarioBorrador,
		'chocan'            => $chocan,
	];
}

// ---------- Gestión de Usuarios ---------- centralizado acá: carga inicial y refresco AJAX comparten consulta y render de fila.

// Cola de resolución manual, agrupada por cliente (2026-09-30, pedido explícito: "esas 4 categorías son 1 solo Acta, no 4 filas sueltas") — candidatos para elegir a mano, igual que liquidacion_pendientes.php.
function listar_repositorio_cuotas_pendientes_match($mysqli) {
	$stmt = $mysqli->prepare(
		"SELECT id, cliente_excel, cedi_excel, plan, sector, trimestre, anio, valores_mensuales
		 FROM repositorio_cuota_cliente
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
		$grupos[$clave]['categorias'][] = $fila['sector'];
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

// ---------- Seguimiento de Equipo ---------- maestro-detalle con filtro de estado. Reforzar el chequeo de rol acá.
