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
function buscarRebateProducto($mysqli, $ciudad, $canal, $sector, $categoria, $marca) {
	$stmtBase = $mysqli->prepare(
		"SELECT rebate_pct FROM repositorio_rebate_producto
		 WHERE eliminado_en IS NULL
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
		 WHERE eliminado_en IS NULL
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
