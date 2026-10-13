<?php
// ---------- Módulo Repositorios ---------- catálogos self-service (Rebate, Participación) que autocompletan y bloquean esos campos.
function listar_repositorio_rebate($mysqli, $busqueda = '', $pagina = 1, $porPagina = 10, $canal = 'total') {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';
	// Filtro de Canal: viene del propio Excel (columna CANAL), no hay tabla separada por canal.
	$condicionCanal = '';
	if ($canal === 'directo') $condicionCanal = " AND UPPER(canal) <> 'DISTRIBUIDOR'";
	elseif ($canal === 'distribuidor') $condicionCanal = " AND UPPER(canal) = 'DISTRIBUIDOR'";

	// Distribuidor y Cliente también se buscan, solo si esas columnas ya existen.
	$extraBusca = ''; $tiposBusca = 'sssss'; $valoresBusca = [$like, $like, $like, $like, $like];
	if (rebateTieneColumnaDistribuidor($mysqli)) { $extraBusca .= ' OR r.distribuidor LIKE ?'; $tiposBusca .= 's'; $valoresBusca[] = $like; }
	if (rebateTieneColumnaCliente($mysqli)) { $extraBusca .= ' OR r.cliente LIKE ?'; $tiposBusca .= 's'; $valoresBusca[] = $like; }

	// eliminado_en IS NULL: el listado normal nunca muestra filas borradas, viven en "Eliminados".
	$stmtTotal = $mysqli->prepare(
		"SELECT COUNT(*) AS total FROM repositorio_rebate_producto r
		 WHERE r.eliminado_en IS NULL AND (r.ciudad LIKE ? OR r.canal LIKE ? OR r.sector LIKE ? OR r.categoria LIKE ? OR r.marca LIKE ?$extraBusca) $condicionCanal"
	);
	if (!$stmtTotal) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmtTotal->bind_param($tiposBusca, ...$valoresBusca);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) { $pagina = $totalPaginas; $offset = ($pagina - 1) * $porPagina; }

	$stmt = $mysqli->prepare(
		"SELECT r.id, r.ciudad, r.canal, r.sector, r.categoria, r.marca, r.rebate_pct, ".(rebateTieneColumnaDistribuidor($mysqli) ? 'r.distribuidor' : "'' AS distribuidor").', '.(rebateTieneColumnaCliente($mysqli) ? 'r.cliente' : "'' AS cliente").", r.updated_at, u.usuario AS actualizado_por_usuario
		 FROM repositorio_rebate_producto r
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = r.actualizado_por
		 WHERE r.eliminado_en IS NULL AND (r.ciudad LIKE ? OR r.canal LIKE ? OR r.sector LIKE ? OR r.categoria LIKE ? OR r.marca LIKE ?$extraBusca) $condicionCanal
		 ORDER BY r.ciudad, r.canal, r.sector, r.categoria, r.marca
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$valoresPag = array_merge($valoresBusca, [$porPagina, $offset]);
	$stmt->bind_param($tiposBusca.'ii', ...$valoresPag);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'total_paginas' => $totalPaginas];
}

// Jerarquía de Supervisores: mapea el nombre "de campo" del maestro (sin cuenta propia) al supervisor real que recibe sus Actas.
// Pestaña "Base" de Repositorios: nuestra base propia de clientes, solo consulta (cliente + CEDI), mismo formato de respuesta que el resto de listar_repositorio_*().
function listar_repositorio_base($mysqli, $busqueda = '', $pagina = 1, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';
	// Ciudad real del cliente (columna nueva): si el ALTER todavía no corrió, se muestra vacía y todo sigue igual.
	$tieneCiudad = propiosTieneCiudad($mysqli);
	$colCiudad = $tieneCiudad ? 'ciudad' : "'' AS ciudad";
	$buscaCiudad = $tieneCiudad ? ' OR ciudad LIKE ?' : '';

	$stmtTotal = $mysqli->prepare('SELECT COUNT(*) AS total FROM repositorio_clientes_propiosac WHERE cliente LIKE ? OR cedi LIKE ?'.$buscaCiudad);
	if (!$stmtTotal) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	if ($tieneCiudad) $stmtTotal->bind_param('sss', $like, $like, $like); else $stmtTotal->bind_param('ss', $like, $like);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) { $pagina = $totalPaginas; $offset = ($pagina - 1) * $porPagina; }

	$stmt = $mysqli->prepare(
		'SELECT id, cliente, cedi, '.$colCiudad.' FROM repositorio_clientes_propiosac
		 WHERE cliente LIKE ? OR cedi LIKE ?'.$buscaCiudad.'
		 ORDER BY cliente, cedi
		 LIMIT ? OFFSET ?'
	);
	if (!$stmt) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	if ($tieneCiudad) $stmt->bind_param('sssii', $like, $like, $like, $porPagina, $offset); else $stmt->bind_param('ssii', $like, $like, $porPagina, $offset);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'total_paginas' => $totalPaginas];
}

function listar_repositorio_jerarquia($mysqli, $busqueda = '', $pagina = 1, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';

	$stmtTotal = $mysqli->prepare('SELECT COUNT(*) AS total FROM repositorio_jerarquia_supervisores WHERE eliminado_en IS NULL AND (supervisor_campo LIKE ? OR supervisor_real LIKE ?)');
	if (!$stmtTotal) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmtTotal->bind_param('ss', $like, $like);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) { $pagina = $totalPaginas; $offset = ($pagina - 1) * $porPagina; }

	$stmt = $mysqli->prepare(
		"SELECT j.id, j.supervisor_campo, j.supervisor_real, j.updated_at, u.usuario AS actualizado_por_usuario
		 FROM repositorio_jerarquia_supervisores j
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = j.actualizado_por
		 WHERE j.eliminado_en IS NULL AND (j.supervisor_campo LIKE ? OR j.supervisor_real LIKE ?)
		 ORDER BY j.supervisor_real, j.supervisor_campo
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmt->bind_param('ssii', $like, $like, $porPagina, $offset);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'total_paginas' => $totalPaginas];
}

function listar_repositorio_participacion($mysqli, $busqueda = '', $pagina = 1, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';

	// eliminado_en IS NULL, mismo criterio que listar_repositorio_rebate(). Busca/ordena también por Ciudad.
	$stmtTotal = $mysqli->prepare('SELECT COUNT(*) AS total FROM repositorio_participacion_percha WHERE eliminado_en IS NULL AND (ciudad LIKE ? OR marca LIKE ?)');
	if (!$stmtTotal) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmtTotal->bind_param('ss', $like, $like);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) { $pagina = $totalPaginas; $offset = ($pagina - 1) * $porPagina; }

	$stmt = $mysqli->prepare(
		"SELECT p.id, p.ciudad, p.marca, p.participacion_pct, p.updated_at, u.usuario AS actualizado_por_usuario
		 FROM repositorio_participacion_percha p
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = p.actualizado_por
		 WHERE p.eliminado_en IS NULL AND (p.ciudad LIKE ? OR p.marca LIKE ?)
		 ORDER BY p.ciudad, p.marca
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmt->bind_param('ssii', $like, $like, $porPagina, $offset);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'total_paginas' => $totalPaginas];
}

// Cuotas resueltas (pos_id encontrado); 'pendiente_match' vive en listar_repositorio_cuotas_pendientes_match().
function listar_repositorio_cuotas($mysqli, $busqueda = '', $pagina = 1, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';

	// Búsqueda cubre todas las columnas visibles. 2 niveles de prepare() (con/sin Subcategoría+Marca) por si ese ALTER no se corrió.
	$stmtTotal = $mysqli->prepare(
		"SELECT COUNT(*) AS total FROM repositorio_cuota_cliente
		 WHERE estado <> 'pendiente_match' AND (cedi_excel LIKE ? OR cliente_excel LIKE ? OR pos_id LIKE ? OR plan LIKE ? OR sector LIKE ? OR subcategoria LIKE ? OR marca LIKE ?)"
	);
	$conSubMarcaBusqueda = (bool) $stmtTotal;
	if (!$stmtTotal) {
		$stmtTotal = $mysqli->prepare(
			"SELECT COUNT(*) AS total FROM repositorio_cuota_cliente
			 WHERE estado <> 'pendiente_match' AND (cedi_excel LIKE ? OR cliente_excel LIKE ? OR pos_id LIKE ? OR plan LIKE ? OR sector LIKE ?)"
		);
	}
	if (!$stmtTotal) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	if ($conSubMarcaBusqueda) {
		$stmtTotal->bind_param('sssssss', $like, $like, $like, $like, $like, $like, $like);
	} else {
		$stmtTotal->bind_param('sssss', $like, $like, $like, $like, $like);
	}
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) { $pagina = $totalPaginas; $offset = ($pagina - 1) * $porPagina; }

	// c.subcategoria/c.marca: sin esto la tabla no mostraba lo que el Excel trajo. Mismo fallback de 2 niveles que la búsqueda de arriba.
	$whereConSub = "c.estado <> 'pendiente_match' AND (c.cedi_excel LIKE ? OR c.cliente_excel LIKE ? OR c.pos_id LIKE ? OR c.plan LIKE ? OR c.sector LIKE ? OR c.subcategoria LIKE ? OR c.marca LIKE ?)";
	$whereSinSub = "c.estado <> 'pendiente_match' AND (c.cedi_excel LIKE ? OR c.cliente_excel LIKE ? OR c.pos_id LIKE ? OR c.plan LIKE ? OR c.sector LIKE ?)";
	// c.usuario_excel (2026-09-28): tercer nivel de fallback arriba de los 2 de siempre, si ese ALTER tampoco se corrió.
	$stmt = $mysqli->prepare(
		"SELECT c.id, c.pos_id, c.cliente_excel, c.cedi_excel, c.usuario_excel, c.plan, c.sector, c.subcategoria, c.marca, c.trimestre, c.anio, c.valores_mensuales, c.estado, c.updated_at, u.usuario AS actualizado_por_usuario
		 FROM repositorio_cuota_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = c.actualizado_por
		 WHERE $whereConSub
		 ORDER BY c.anio DESC, c.trimestre DESC, c.cliente_excel, c.sector
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) $stmt = $mysqli->prepare(
		"SELECT c.id, c.pos_id, c.cliente_excel, c.cedi_excel, c.plan, c.sector, c.subcategoria, c.marca, c.trimestre, c.anio, c.valores_mensuales, c.estado, c.updated_at, u.usuario AS actualizado_por_usuario
		 FROM repositorio_cuota_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = c.actualizado_por
		 WHERE $whereConSub
		 ORDER BY c.anio DESC, c.trimestre DESC, c.cliente_excel, c.sector
		 LIMIT ? OFFSET ?"
	);
	$conSubMarcaResultado = (bool) $stmt;
	// Fallback si subcategoria/marca no existieran, mismo criterio defensivo que el resto del proyecto.
	if (!$stmt) {
		$conSubMarcaResultado = false;
		$stmt = $mysqli->prepare(
			"SELECT c.id, c.pos_id, c.cliente_excel, c.cedi_excel, c.plan, c.sector, NULL AS subcategoria, NULL AS marca, c.trimestre, c.anio, c.valores_mensuales, c.estado, c.updated_at, u.usuario AS actualizado_por_usuario
			 FROM repositorio_cuota_cliente c
			 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = c.actualizado_por
			 WHERE $whereSinSub
			 ORDER BY c.anio DESC, c.trimestre DESC, c.cliente_excel, c.sector
			 LIMIT ? OFFSET ?"
		);
	}
	if (!$stmt) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	if ($conSubMarcaResultado) {
		$stmt->bind_param('sssssssii', $like, $like, $like, $like, $like, $like, $like, $porPagina, $offset);
	} else {
		$stmt->bind_param('sssssii', $like, $like, $like, $like, $like, $porPagina, $offset);
	}
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	// mysqli no decodifica JSON solo: sin esto, json_encode() manda un string escapado en vez de objeto.
	foreach ($filas as &$fila) {
		$fila['valores_mensuales'] = $fila['valores_mensuales'] !== null ? json_decode($fila['valores_mensuales'], true) : [];
	}
	unset($fila);

	return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'total_paginas' => $totalPaginas];
}

// ---------- Repositorio "Acuerdo Completo" ---------- paralelo a Cuotas, tabla propia repositorio_acuerdo_completo_linea.

function listar_repositorio_acuerdo_completo($mysqli, $busqueda = '', $pagina = 1, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';

	$stmtTotal = $mysqli->prepare(
		"SELECT COUNT(*) AS total FROM repositorio_acuerdo_completo_linea
		 WHERE estado <> 'pendiente_match' AND (cedi_excel LIKE ? OR cliente_excel LIKE ? OR pos_id LIKE ? OR plan LIKE ? OR sector LIKE ? OR categoria LIKE ? OR marca LIKE ?)"
	);
	if (!$stmtTotal) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmtTotal->bind_param('sssssss', $like, $like, $like, $like, $like, $like, $like);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) { $pagina = $totalPaginas; $offset = ($pagina - 1) * $porPagina; }

	$stmt = $mysqli->prepare(
		"SELECT id, pos_id, cliente_excel, cedi_excel, plan, tipo, sector, categoria, marca, cantidad_max_percha, valores_mensuales, valor_mensual_unico, trimestre, anio, estado
		 FROM repositorio_acuerdo_completo_linea
		 WHERE estado <> 'pendiente_match' AND (cedi_excel LIKE ? OR cliente_excel LIKE ? OR pos_id LIKE ? OR plan LIKE ? OR sector LIKE ? OR categoria LIKE ? OR marca LIKE ?)
		 ORDER BY anio DESC, trimestre DESC, cliente_excel, pos_id, FIELD(tipo,'meta_compra','cabecera','ruma','percha'), sector, categoria, marca
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmt->bind_param('sssssssii', $like, $like, $like, $like, $like, $like, $like, $porPagina, $offset);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	foreach ($filas as &$fila) {
		$fila['valores_mensuales'] = $fila['valores_mensuales'] !== null ? json_decode($fila['valores_mensuales'], true) : [];
	}
	unset($fila);
	return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'total_paginas' => $totalPaginas];
}
