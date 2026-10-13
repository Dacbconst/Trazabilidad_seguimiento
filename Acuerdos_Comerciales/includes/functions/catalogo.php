<?php
// Corrige "CATEGORIAS" del Excel de Cuotas contra el catálogo real: Sector directo, o "Sector Subcategoría" pegados. Sin match, null.
function resolverSectorReal($mysqli, $sectorCrudo) {
	$stmt = $mysqli->prepare(
		"SELECT 1 FROM repositorio_productos WHERE fabricante = 'JABONERIA WILSON' AND sector = ? AND activar = 'SI' LIMIT 1"
	);
	if ($stmt) {
		$stmt->bind_param('s', $sectorCrudo);
		$stmt->execute();
		$existe = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if ($existe) return $sectorCrudo;
	}

	$stmt = $mysqli->prepare(
		"SELECT DISTINCT sector FROM repositorio_productos
		 WHERE fabricante = 'JABONERIA WILSON' AND activar = 'SI' AND CONCAT(sector, ' ', categoria) = ?"
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $sectorCrudo);
	$stmt->execute();
	$sectores = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'sector');
	$stmt->close();

	return count($sectores) === 1 ? $sectores[0] : null;
}

// Parche visual: BARRA+EL MACHO es "ROPA" en repositorio_productos; se muestra "BARRA" como lo nombra JW en Cuotas y Rebate (sin tocar el esquema).
function aplicarParcheCategoriaVisual($sector, $marca, $categoria) {
	$parches = [
		['BARRA', 'EL MACHO', 'BARRA'],
	];
	foreach ($parches as $parche) {
		if (strtoupper(trim($sector)) === $parche[0] && strtoupper(trim($marca)) === $parche[1]) return $parche[2];
	}
	return $categoria;
}

// Resuelve Segmento/Categoría/Marca desde SUBCATEGORIA/MARCA del Excel de Cuotas, tolerando plural/singular. Solo si el match es único.
function resolverProductoCuota($mysqli, $sector, $subcategoriaCruda, $marcaCruda) {
	if ($subcategoriaCruda === '' || $marcaCruda === '') return null;

	$stmt = $mysqli->prepare(
		"SELECT segmento, categoria, marca FROM repositorio_productos
		 WHERE fabricante = 'JABONERIA WILSON' AND activar = 'SI'
		   AND UPPER(TRIM(sector)) = UPPER(TRIM(?))
		   AND UPPER(TRIM(categoria)) = UPPER(TRIM(?))
		   AND UPPER(TRIM(marca)) = UPPER(TRIM(?))
		 LIMIT 1"
	);
	if (!$stmt) return null;

	$variantes = function ($texto) {
		$v = [$texto];
		if (substr($texto, -1) === 'S') $v[] = substr($texto, 0, -1);
		else $v[] = $texto.'S';
		return $v;
	};

	foreach ($variantes($sector) as $sectorProbar) {
		foreach ($variantes($subcategoriaCruda) as $categoriaProbar) {
			$stmt->bind_param('sss', $sectorProbar, $categoriaProbar, $marcaCruda);
			$stmt->execute();
			$fila = $stmt->get_result()->fetch_assoc();
			if ($fila) {
				$stmt->close();
				$fila['categoria'] = aplicarParcheCategoriaVisual($sector, $fila['marca'], $fila['categoria']);
				return $fila;
			}
		}
	}
	$stmt->close();

	// Respaldo: Sector + Marca como palabra completa ("MACHO" → "EL MACHO"), ignorando Subcategoría; solo si el producto es único.
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT segmento, categoria, marca FROM repositorio_productos
		 WHERE fabricante = 'JABONERIA WILSON' AND activar = 'SI'
		   AND UPPER(TRIM(sector)) = UPPER(TRIM(?))
		   AND CONCAT(' ', UPPER(TRIM(marca)), ' ') LIKE CONCAT('% ', UPPER(TRIM(?)), ' %')"
	);
	if (!$stmt) return null;
	foreach ($variantes($sector) as $sectorProbar) {
		$stmt->bind_param('ss', $sectorProbar, $marcaCruda);
		$stmt->execute();
		$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
		if (count($filas) === 1) {
			$stmt->close();
			$filas[0]['categoria'] = aplicarParcheCategoriaVisual($sector, $filas[0]['marca'], $filas[0]['categoria']);
			return $filas[0];
		}
	}
	$stmt->close();
	return null;
}

// Busca Rebate% tolerando desajustes de nombre entre el Excel de JW y el catálogo real (ej. plural/singular).
// ¿Ya existe esa columna ('distribuidor' o 'cliente') en el repositorio de Rebate? Cache por request, para no romper nada si el ALTER todavía no se corrió.
function rebateTieneColumna($mysqli, $col) {
	static $cache = [];
	if (!isset($cache[$col])) {
		$res = $mysqli->query("SHOW COLUMNS FROM repositorio_rebate_producto LIKE '".$mysqli->real_escape_string($col)."'");
		$cache[$col] = $res && $res->num_rows > 0;
	}
	return $cache[$col];
}
function rebateTieneColumnaDistribuidor($mysqli) { return rebateTieneColumna($mysqli, 'distribuidor'); }
function rebateTieneColumnaCliente($mysqli) { return rebateTieneColumna($mysqli, 'cliente'); }

// Rebate especial de un cliente o de un distribuidor puntual ($columna = 'cliente' | 'distribuidor'): el nombre se compara sin puntos, comas, espacios ni tildes. Categoría/Marca en 'TODAS' valen para cualquiera. La Subcategoría tolera plural/singular y diferencias de nombre con el catálogo (ej. LAVAVAJILLA/LAVAVAJILLAS): gana la que coincide; si ninguna coincide y hay varias distintas, no se adivina. Null si no tiene tarifa propia.
function buscarRebateEspecial($mysqli, $columna, $valor, $sector, $categoria, $marca) {
	$valor = trim((string) $valor);
	if ($valor === '' || !in_array($columna, ['cliente', 'distribuidor'], true) || !rebateTieneColumna($mysqli, $columna)) return null;
	// Una fila con CLIENTE es solo de ese cliente: al buscar por distribuidor se saltan las filas que además traen cliente.
	$soloDistribuidor = ($columna === 'distribuidor' && rebateTieneColumnaCliente($mysqli)) ? " AND (cliente = '' OR cliente IS NULL)" : '';
	$stmt = $mysqli->prepare(
		"SELECT UPPER(TRIM(categoria)) AS cat, UPPER(TRIM(marca)) AS mar, rebate_pct FROM repositorio_rebate_producto
		 WHERE eliminado_en IS NULL $soloDistribuidor AND ".repositorio_sql_comparable($columna)." = ?
		   AND (UPPER(TRIM(sector)) IN (?, ?) OR UPPER(TRIM(sector)) LIKE CONCAT(?, ' %'))
		   AND (UPPER(TRIM(marca)) = UPPER(TRIM(?)) OR UPPER(TRIM(marca)) = 'TODAS')"
	);
	if (!$stmt) return null;
	$comparable = repositorio_texto_comparable($valor);
	$s1 = mb_strtoupper(trim($sector), 'UTF-8');
	$s2 = substr($s1, -1) === 'S' ? substr($s1, 0, -1) : $s1.'S';
	$stmt->bind_param('sssss', $comparable, $s1, $s2, $s1, $marca);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	if (!$filas) return null;

	$cat1 = mb_strtoupper(trim((string) $categoria), 'UTF-8');
	$cat2 = substr($cat1, -1) === 'S' ? substr($cat1, 0, -1) : $cat1.'S';
	$marcaUp = mb_strtoupper(trim((string) $marca), 'UTF-8');
	$mejor = null; $mejorRango = null; $empatadas = [];
	foreach ($filas as $f) {
		$rangoCat = ($f['cat'] === $cat1 || $f['cat'] === $cat2) ? 0 : ($f['cat'] === 'TODAS' ? 1 : 2);
		$rangoMarca = $f['mar'] === $marcaUp ? 0 : 1;
		$rango = $rangoCat * 10 + $rangoMarca;
		if ($mejorRango === null || $rango < $mejorRango) { $mejorRango = $rango; $mejor = $f; $empatadas = [$f['rebate_pct']]; }
		elseif ($rango === $mejorRango) $empatadas[] = $f['rebate_pct'];
	}
	// Ninguna subcategoría coincide y hay varias con distinto %: ambiguo, no se adivina.
	if ($mejorRango >= 20 && count(array_unique($empatadas)) > 1) return null;
	return (float) $mejor['rebate_pct'];
}
function buscarRebateProducto($mysqli, $ciudad, $canal, $sector, $categoria, $marca, $distribuidor = '', $cliente = '') {
	// Orden: rebate propio del cliente, luego del distribuidor, y si no hay, el rebate normal de siempre.
	$especial = buscarRebateEspecial($mysqli, 'cliente', $cliente, $sector, $categoria, $marca);
	if ($especial === null) $especial = buscarRebateEspecial($mysqli, 'distribuidor', $distribuidor, $sector, $categoria, $marca);
	if ($especial !== null) return $especial;
	$soloGeneral = '';
	if (rebateTieneColumnaDistribuidor($mysqli)) $soloGeneral .= " AND (distribuidor = '' OR distribuidor IS NULL)";
	if (rebateTieneColumnaCliente($mysqli)) $soloGeneral .= " AND (cliente = '' OR cliente IS NULL)";
	$stmtBase = $mysqli->prepare(
		"SELECT rebate_pct FROM repositorio_rebate_producto
		 WHERE eliminado_en IS NULL $soloGeneral
		   AND UPPER(TRIM(ciudad)) = UPPER(TRIM(?)) AND UPPER(TRIM(canal)) = UPPER(TRIM(?))
		   AND UPPER(TRIM(sector)) = UPPER(TRIM(?)) AND UPPER(TRIM(categoria)) = UPPER(TRIM(?))
		   AND UPPER(TRIM(marca)) = UPPER(TRIM(?))
		 LIMIT 1"
	);
	if (!$stmtBase) return null;

	$intentar = function ($sectorProbar, $categoriaProbar) use ($mysqli, $stmtBase, $ciudad, $canal, $marca) {
		$stmtBase->bind_param('sssss', $ciudad, $canal, $sectorProbar, $categoriaProbar, $marca);
		$stmtBase->execute();
		$fila = $stmtBase->get_result()->fetch_assoc();
		return $fila ? (float) $fila['rebate_pct'] : null;
	};

	// Variantes de plural/singular, mismo criterio que resolverSectorReal(), sobre Sector Y Categoría.
	$variantesTexto = function ($texto) {
		$variantes = [$texto];
		if (substr($texto, -1) === 'S') $variantes[] = substr($texto, 0, -1);
		else $variantes[] = $texto.'S';
		return $variantes;
	};

	foreach ($variantesTexto($sector) as $sectorProbar) {
		foreach ($variantesTexto($categoria) as $categoriaProbar) {
			$valor = $intentar($sectorProbar, $categoriaProbar);
			if ($valor !== null) { $stmtBase->close(); return $valor; }
		}
	}
	$stmtBase->close();

	// Último recurso: Ciudad+Canal+Sector+Marca sin Categoría, solo si da una única fila.
	$stmt = $mysqli->prepare(
		"SELECT rebate_pct FROM repositorio_rebate_producto
		 WHERE eliminado_en IS NULL $soloGeneral
		   AND UPPER(TRIM(ciudad)) = UPPER(TRIM(?)) AND UPPER(TRIM(canal)) = UPPER(TRIM(?))
		   AND UPPER(TRIM(sector)) = UPPER(TRIM(?)) AND UPPER(TRIM(marca)) = UPPER(TRIM(?))"
	);
	if (!$stmt) return null;
	$stmt->bind_param('ssss', $ciudad, $canal, $sector, $marca);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return count($filas) === 1 ? (float) $filas[0]['rebate_pct'] : null;
}

// Busca % de Participación por Ciudad+Marca. Fallback: Ciudad exacta -> "TODAS" -> "RESTO CIUDADES".
function buscarParticipacionPercha($mysqli, $ciudad, $marca) {
	$stmt = $mysqli->prepare(
		"SELECT participacion_pct FROM repositorio_participacion_percha
		 WHERE eliminado_en IS NULL
		   AND UPPER(TRIM(ciudad)) = UPPER(TRIM(?)) AND UPPER(TRIM(marca)) = UPPER(TRIM(?))
		 LIMIT 1"
	);
	if (!$stmt) return null;

	foreach ([$ciudad, 'TODAS', 'RESTO CIUDADES'] as $ciudadProbar) {
		$stmt->bind_param('ss', $ciudadProbar, $marca);
		$stmt->execute();
		$fila = $stmt->get_result()->fetch_assoc();
		if ($fila) { $stmt->close(); return (float) $fila['participacion_pct']; }
	}
	$stmt->close();
	return null;
}
