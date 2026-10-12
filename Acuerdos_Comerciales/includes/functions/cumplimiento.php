<?php
// ---------- Módulo "Cumplimiento de Cuota" ---------- CEDI del Excel gana sobre el maestro. $canal filtra por el SUPERVISOR ya resuelto.
// Lee directo c.canal (grabado al subir el Excel, ver cumplimiento_guardar.php) — nunca vuelve a comparar contra el maestro. COALESCE a 'directo' para filas de antes de que existiera esta columna (mismo criterio que el SELECT de canal, para que filtro y badge mostrado siempre coincidan).
function condicionCanalCumplimiento($canal) {
	if ($canal === 'directo') return "COALESCE(c.canal, 'directo') = 'directo'";
	if ($canal === 'distribuidor') return "COALESCE(c.canal, 'directo') = 'distribuidor'";
	return '';
}

function listar_cumplimiento_cuota($mysqli, $trimestre, $anio, $busqueda, $canal = 'total') {
	$condiciones = ['c.eliminado_en IS NULL'];
	$params = [];
	$tipos = '';
	if ($trimestre > 0) { $condiciones[] = 'c.trimestre = ?'; $params[] = $trimestre; $tipos .= 'i'; }
	if ($anio > 0) { $condiciones[] = 'c.anio = ?'; $params[] = $anio; $tipos .= 'i'; }
	$busqueda = trim((string) $busqueda);
	if ($busqueda !== '') {
		$condiciones[] = "(c.cliente_excel LIKE CONCAT('%', ?, '%') OR COALESCE(u_usuario.usuario, u_cedi.usuario, u_master.usuario) LIKE CONCAT('%', ?, '%'))";
		$params[] = $busqueda;
		$params[] = $busqueda;
		$tipos .= 'ss';
	}
	$condicionCanal = condicionCanalCumplimiento($canal, 'COALESCE(u_usuario.supervisor, u_cedi.supervisor, u_subio.supervisor, u_master.supervisor)');
	if ($condicionCanal !== '') $condiciones[] = $condicionCanal;
	$where = implode(' AND ', $condiciones);

	// USUARIO de Cuotas Trimestrales (2026-09-28) manda primero — mismo cliente/trimestre, ver usuario_excel en repositorio_cuota_cliente. Sin esto, Cumplimiento le asignaba el cliente a quien diga el maestro aunque Cuotas Trimestrales ya lo tuviera bien asignado a otra persona (bug real reportado).
	$stmt = $mysqli->prepare(
		"SELECT c.id, c.pos_id, c.cliente_excel, c.cedi_excel, c.plan_excel, c.sector,
		        c.cuota_total, c.venta_total, c.cumplimiento_pct,
		        c.gana_categoria, c.gana_categoria_anterior, c.gana_total,
		        c.rebate_real_vol, c.updated_at,
		        COALESCE(u_usuario.id, u_cedi.id, u_subio.id, u_master.id) AS usuario_id,
		        COALESCE(u_usuario.usuario, u_cedi.usuario, u_subio.usuario, u_master.usuario) AS usuario_nombre,
		        COALESCE(c.canal, 'directo') AS canal,
		        (CASE WHEN EXISTS (SELECT 1 FROM repositorio_productos p WHERE p.fabricante = 'JABONERIA WILSON' AND p.sector = c.sector AND p.activar = 'SI') THEN 1 ELSE 0 END) AS categoria_valida
		 FROM repositorio_cumplimiento_cuota c
		 LEFT JOIN (SELECT pos_id, trimestre, anio, MAX(usuario_excel) AS usuario_excel FROM repositorio_cuota_cliente WHERE usuario_excel IS NOT NULL AND usuario_excel <> '' GROUP BY pos_id, trimestre, anio) rc
		   ON rc.pos_id = c.pos_id AND rc.trimestre = c.trimestre AND rc.anio = c.anio
		 LEFT JOIN repositorio_usuarios_acuerdos u_usuario
		   ON u_usuario.status = 'activo' AND UPPER(TRIM(u_usuario.usuario)) = UPPER(TRIM(rc.usuario_excel))
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN repositorio_usuarios_acuerdos u_subio
		   ON u_subio.id = c.actualizado_por AND u_subio.status = 'activo'
		 LEFT JOIN repositorio_usuarios_acuerdos u_master
		   ON u_master.status = 'activo'
		  AND u_master.supervisor = (SELECT d4.supervisor FROM repositorio_locales_supervisores_cliente d4
		                              WHERE d4.pos_id = c.pos_id
		                                AND (CASE WHEN c.plan_excel IS NOT NULL AND c.plan_excel <> '' THEN d4.canal = 'DISTRIBUIDOR' ELSE d4.canal <> 'DISTRIBUIDOR' END)
		                              ORDER BY (UPPER(TRIM(d4.pos_name)) = UPPER(TRIM(c.cliente_excel))) DESC, d4.id DESC LIMIT 1)
		 WHERE $where
		 ORDER BY usuario_nombre IS NULL, usuario_nombre, c.cliente_excel, c.sector"
	);
	if (!$stmt) return [];
	if ($params) $stmt->bind_param($tipos, ...$params);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return $filas;
}

function resumen_cumplimiento_cuota($mysqli, $trimestre, $anio, $canal = 'total') {
	$condiciones = ['c.eliminado_en IS NULL'];
	$params = [];
	$tipos = '';
	if ($trimestre > 0) { $condiciones[] = 'c.trimestre = ?'; $params[] = $trimestre; $tipos .= 'i'; }
	if ($anio > 0) { $condiciones[] = 'c.anio = ?'; $params[] = $anio; $tipos .= 'i'; }
	// Mismo criterio que listar_cumplimiento_cuota(): USUARIO de Cuotas Trimestrales manda primero, luego CEDI, luego maestro.
	$condicionCanal = condicionCanalCumplimiento($canal, 'COALESCE(u_usuario.supervisor, u_cedi.supervisor, u_subio.supervisor, u_master.supervisor)');
	if ($condicionCanal !== '') $condiciones[] = $condicionCanal;
	$where = implode(' AND ', $condiciones);

	$stmt = $mysqli->prepare(
		"SELECT
		    COUNT(DISTINCT c.pos_id) AS clientes,
		    COUNT(*) AS categorias,
		    SUM(c.gana_categoria = 'gana') AS ganan_categoria,
		    SUM(c.gana_categoria = 'no_gana') AS no_ganan_categoria,
		    AVG(c.cumplimiento_pct) AS cumplimiento_promedio,
		    COUNT(DISTINCT CASE WHEN c.gana_total = 'gana' THEN c.pos_id END) AS clientes_ganan_total
		 FROM repositorio_cumplimiento_cuota c
		 LEFT JOIN (SELECT pos_id, trimestre, anio, MAX(usuario_excel) AS usuario_excel FROM repositorio_cuota_cliente WHERE usuario_excel IS NOT NULL AND usuario_excel <> '' GROUP BY pos_id, trimestre, anio) rc
		   ON rc.pos_id = c.pos_id AND rc.trimestre = c.trimestre AND rc.anio = c.anio
		 LEFT JOIN repositorio_usuarios_acuerdos u_usuario
		   ON u_usuario.status = 'activo' AND UPPER(TRIM(u_usuario.usuario)) = UPPER(TRIM(rc.usuario_excel))
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN repositorio_usuarios_acuerdos u_subio
		   ON u_subio.id = c.actualizado_por AND u_subio.status = 'activo'
		 LEFT JOIN repositorio_usuarios_acuerdos u_master
		   ON u_master.status = 'activo'
		  AND u_master.supervisor = (SELECT d4.supervisor FROM repositorio_locales_supervisores_cliente d4
		                              WHERE d4.pos_id = c.pos_id
		                                AND (CASE WHEN c.plan_excel IS NOT NULL AND c.plan_excel <> '' THEN d4.canal = 'DISTRIBUIDOR' ELSE d4.canal <> 'DISTRIBUIDOR' END)
		                              ORDER BY (UPPER(TRIM(d4.pos_name)) = UPPER(TRIM(c.cliente_excel))) DESC, d4.id DESC LIMIT 1)
		 WHERE $where"
	);
	$vacio = ['clientes' => 0, 'categorias' => 0, 'ganan_categoria' => 0, 'no_ganan_categoria' => 0, 'cumplimiento_promedio' => 0.0, 'clientes_ganan_total' => 0];
	if (!$stmt) return $vacio;
	if ($params) $stmt->bind_param($tipos, ...$params);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$fila) return $vacio;
	return [
		'clientes'              => (int) $fila['clientes'],
		'categorias'            => (int) $fila['categorias'],
		'ganan_categoria'       => (int) $fila['ganan_categoria'],
		'no_ganan_categoria'    => (int) $fila['no_ganan_categoria'],
		'cumplimiento_promedio' => round((float) $fila['cumplimiento_promedio'], 1),
		'clientes_ganan_total'  => (int) $fila['clientes_ganan_total'],
	];
}

// Consolidado por Categoría: mismas filas que listar_cumplimiento_cuota(), agrupadas por Sector en vez de por asesor/cliente.
function resumen_consolidado_categoria($mysqli, $trimestre, $anio, $canal = 'total') {
	$condiciones = ['c.eliminado_en IS NULL'];
	$params = [];
	$tipos = '';
	if ($trimestre > 0) { $condiciones[] = 'c.trimestre = ?'; $params[] = $trimestre; $tipos .= 'i'; }
	if ($anio > 0) { $condiciones[] = 'c.anio = ?'; $params[] = $anio; $tipos .= 'i'; }
	$condicionCanal = condicionCanalCumplimiento($canal, 'COALESCE(u_usuario.supervisor, u_cedi.supervisor, u_subio.supervisor, u_master.supervisor)');
	if ($condicionCanal !== '') $condiciones[] = $condicionCanal;
	$where = implode(' AND ', $condiciones);

	// Mismo criterio de resolución de dueño que listar_cumplimiento_cuota(): USUARIO de Cuotas Trimestrales > CEDI > maestro.
	$stmt = $mysqli->prepare(
		"SELECT c.id, c.pos_id, c.cliente_excel, c.cedi_excel, c.sector,
		        c.cuota_total, c.venta_total, c.cumplimiento_pct, c.gana_categoria,
		        COALESCE(u_usuario.usuario, u_cedi.usuario, u_subio.usuario, u_master.usuario) AS usuario_nombre,
		        COALESCE(c.canal, 'directo') AS canal
		 FROM repositorio_cumplimiento_cuota c
		 LEFT JOIN (SELECT pos_id, trimestre, anio, MAX(usuario_excel) AS usuario_excel FROM repositorio_cuota_cliente WHERE usuario_excel IS NOT NULL AND usuario_excel <> '' GROUP BY pos_id, trimestre, anio) rc
		   ON rc.pos_id = c.pos_id AND rc.trimestre = c.trimestre AND rc.anio = c.anio
		 LEFT JOIN repositorio_usuarios_acuerdos u_usuario
		   ON u_usuario.status = 'activo' AND UPPER(TRIM(u_usuario.usuario)) = UPPER(TRIM(rc.usuario_excel))
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN repositorio_usuarios_acuerdos u_subio
		   ON u_subio.id = c.actualizado_por AND u_subio.status = 'activo'
		 LEFT JOIN repositorio_usuarios_acuerdos u_master
		   ON u_master.status = 'activo'
		  AND u_master.supervisor = (SELECT d4.supervisor FROM repositorio_locales_supervisores_cliente d4
		                              WHERE d4.pos_id = c.pos_id
		                                AND (CASE WHEN c.plan_excel IS NOT NULL AND c.plan_excel <> '' THEN d4.canal = 'DISTRIBUIDOR' ELSE d4.canal <> 'DISTRIBUIDOR' END)
		                              ORDER BY (UPPER(TRIM(d4.pos_name)) = UPPER(TRIM(c.cliente_excel))) DESC, d4.id DESC LIMIT 1)
		 WHERE $where
		 ORDER BY c.sector, c.cliente_excel"
	);
	if (!$stmt) return [];
	if ($params) $stmt->bind_param($tipos, ...$params);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	$porSector = [];
	foreach ($filas as $f) {
		$sector = $f['sector'];
		if (!isset($porSector[$sector])) {
			$porSector[$sector] = ['sector' => $sector, 'cuota_total' => 0.0, 'venta_total' => 0.0, 'clientes' => [], 'detalle' => []];
		}
		$porSector[$sector]['cuota_total'] += (float) $f['cuota_total'];
		$porSector[$sector]['venta_total'] += (float) $f['venta_total'];
		$porSector[$sector]['clientes'][$f['pos_id']] = true;
		$porSector[$sector]['detalle'][] = [
			'cliente'   => $f['cliente_excel'],
			'cedi'      => $f['cedi_excel'],
			'usuario'   => $f['usuario_nombre'],
			'canal'     => $f['canal'],
			'cuota_total'       => (float) $f['cuota_total'],
			'venta_total'       => (float) $f['venta_total'],
			'cumplimiento_pct'  => (float) $f['cumplimiento_pct'],
			'gana_categoria'    => $f['gana_categoria'],
		];
	}

	$resultado = array_values(array_map(function ($c) {
		$pct = $c['cuota_total'] > 0 ? round(($c['venta_total'] / $c['cuota_total']) * 100, 2) : 0.0;
		return [
			'sector'           => $c['sector'],
			'clientes'         => count($c['clientes']),
			'cuota_total'      => $c['cuota_total'],
			'venta_total'      => $c['venta_total'],
			'cumplimiento_pct' => $pct,
			'gana'             => $pct >= 100 ? 'gana' : 'no_gana',
			'detalle'          => $c['detalle'],
		];
	}, $porSector));

	usort($resultado, function ($a, $b) { return strcmp($a['sector'], $b['sector']); });
	return $resultado;
}

function listar_anios_disponibles_cumplimiento($mysqli) {
	$res = $mysqli->query("SELECT DISTINCT anio FROM repositorio_cumplimiento_cuota WHERE eliminado_en IS NULL ORDER BY anio DESC");
	if (!$res) return [];
	return array_map('intval', array_column($res->fetch_all(MYSQLI_ASSOC), 'anio'));
}
