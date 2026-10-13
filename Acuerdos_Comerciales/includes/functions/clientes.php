<?php
// ---------- Repositorio de Cuotas trimestrales ---------- match por nombre, desempate por canal: supervisor en Directo, empresa (tipo_distribuidor) en Distribuidor.
// Sin puntos/comas/espacios ni tildes, mismo criterio ya usado en el JS de Cuotas — tolera "S.A.S." vs "S A S" o "IÑIGUEZ" vs "INIGUEZ" sin tapar ambigüedades reales.
function repositorio_texto_comparable($texto) {
	$texto = str_replace(['.', ',', ' '], '', (string) $texto);
	return strtr(mb_strtoupper($texto, 'UTF-8'), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
}

// Misma limpieza que repositorio_texto_comparable(), aplicada del lado SQL a una columna.
function repositorio_sql_comparable($columna) {
	$expr = "UPPER($columna)";
	foreach (['.' => '', ',' => '', ' ' => '', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N'] as $de => $a) {
		$expr = "REPLACE($expr, '$de', '$a')";
	}
	return $expr;
}

// ¿La base propia ya tiene la columna `ciudad`? Cache por request: sin ella todo sigue como antes (cedi hace de ciudad), no se rompe nada si el ALTER todavía no corrió.
function propiosTieneCiudad($mysqli) {
	static $tiene = null;
	if ($tiene === null) {
		$res = $mysqli->query("SHOW COLUMNS FROM repositorio_clientes_propiosac LIKE 'ciudad'");
		$tiene = $res && $res->num_rows > 0;
	}
	return $tiene;
}

// Ciudad real de un cliente propio para mostrar y buscar rebate: `ciudad` si está llena, si no `cedi` (en Directa es el asesor, en Distribuidor ya es la ciudad). La columna `cedi` cruda se sigue usando para saber de quién es el cliente.
function sqlCiudadPropios($mysqli) {
	return propiosTieneCiudad($mysqli) ? "COALESCE(NULLIF(ciudad, ''), cedi)" : 'cedi';
}

// Cache en memoria del maestro completo, una sola vez por request — antes escaneaba 42k filas por SQL sin índice en cada cliente (~300ms c/u).
function maestroClientesEnMemoria($mysqli, $forzarRecarga = false) {
	static $filas = null;
	if ($filas !== null && !$forzarRecarga) return $filas;
	$filas = [];
	$res = $mysqli->query('SELECT id, pos_id, pos_name, canal, tipo_distribuidor, supervisor, cedi FROM repositorio_locales_supervisores_cliente');
	while ($fila = $res->fetch_assoc()) {
		$fila['pos_name_comparable'] = repositorio_texto_comparable($fila['pos_name']);
		$fila['tipo_distribuidor_comparable'] = repositorio_texto_comparable($fila['tipo_distribuidor']);
		$fila['supervisor_comparable'] = repositorio_texto_comparable($fila['supervisor']);
		$fila['es_propio'] = false;
		$filas[] = $fila;
	}
	// Clientes que Alicorp todavía no tiene en su maestro (ver resolverPosIdCliente()/crearClientePropio()) — mismo formato de fila para que el resto de funciones de este archivo los trate igual; `es_propio` hace que el match sea EXACTO, nunca por prefijo como el maestro real. Silencioso si la tabla todavía no existe (ALTER/CREATE pendiente).
	$resPropios = $mysqli->query("SELECT id, pos_id, cliente AS pos_name, UPPER(canal) AS canal, distribuidor AS tipo_distribuidor, cedi AS supervisor, ".sqlCiudadPropios($mysqli)." AS cedi, ".(propiosTieneCiudad($mysqli) ? "COALESCE(ciudad, '')" : "''")." AS ciudad_real FROM repositorio_clientes_propiosac");
	if ($resPropios) {
		while ($fila = $resPropios->fetch_assoc()) {
			$fila['pos_name_comparable'] = repositorio_texto_comparable($fila['pos_name']);
			$fila['tipo_distribuidor_comparable'] = repositorio_texto_comparable($fila['tipo_distribuidor']);
			$fila['supervisor_comparable'] = repositorio_texto_comparable($fila['supervisor']);
			$fila['ciudad_comparable'] = repositorio_texto_comparable($fila['ciudad_real']);
			$fila['es_propio'] = true;
			$filas[] = $fila;
		}
	}
	return $filas;
}

// Crea un pos_id propio (formato PDVAC0001...) para un cliente que no existe en el maestro de Alicorp — pedido explícito del cliente: su base está incompleta y seguirá pasando, así que armamos la nuestra poco a poco. Match de reuso EXACTO por nombre+canal (ver maestroClientesEnMemoria()), nunca por prefijo: un typo nuevo crea otro cliente propio en vez de mezclarse con uno ya creado.
function crearClientePropio($mysqli, $clienteExcel, $cediExcel, $canal, $distribuidorExcel = null, $creadoPor = null) {
	$canalDb = $canal === 'distribuidor' ? 'distribuidor' : 'directo';
	$cediExcel = $cediExcel !== null ? trim((string) $cediExcel) : null;
	$distribuidorExcel = $distribuidorExcel !== null ? trim((string) $distribuidorExcel) : null;

	// Ciudad real: en Distribuidor ya viene en el Excel (columna CIUDAD), en Directa se toma del maestro de Alicorp por nombre.
	$tieneCiudad = propiosTieneCiudad($mysqli);
	// La ciudad viene en la columna CEDI del Excel (en Distribuidor, en CIUDAD): se guarda en cedi. La columna `ciudad` queda para completar clientes viejos. El maestro de Alicorp ya no interviene.
	$ciudad = $canalDb === 'distribuidor' ? $cediExcel : null;
	$stmt = $mysqli->prepare(
		$tieneCiudad
			? 'INSERT INTO repositorio_clientes_propiosac (pos_id, cliente, cedi, ciudad, distribuidor, canal, creado_por) VALUES (\'\', ?, ?, ?, ?, ?, ?)'
			: 'INSERT INTO repositorio_clientes_propiosac (pos_id, cliente, cedi, distribuidor, canal, creado_por) VALUES (\'\', ?, ?, ?, ?, ?)'
	);
	if (!$stmt) return null;
	if ($tieneCiudad) $stmt->bind_param('sssssi', $clienteExcel, $cediExcel, $ciudad, $distribuidorExcel, $canalDb, $creadoPor);
	else $stmt->bind_param('ssssi', $clienteExcel, $cediExcel, $distribuidorExcel, $canalDb, $creadoPor);
	if (!$stmt->execute()) { $stmt->close(); return null; }
	$id = $stmt->insert_id;
	$stmt->close();

	$posId = 'PDVAC'.str_pad($id, 4, '0', STR_PAD_LEFT);
	$stmtUp = $mysqli->prepare('UPDATE repositorio_clientes_propiosac SET pos_id = ? WHERE id = ?');
	if ($stmtUp) { $stmtUp->bind_param('si', $posId, $id); $stmtUp->execute(); $stmtUp->close(); }
	// Sin esto, una 2da consulta en el mismo request no lo ve (cache en memoria de maestroClientesEnMemoria()) y crea un duplicado.
	maestroClientesEnMemoria($mysqli, true);
	return $posId;
}

// Ciudades que ya conocemos (las ya guardadas en clientes y las del repositorio de Rebate): sirve para distinguir una ciudad de un nombre de asesor cuando el Excel viene con el formato viejo.
function esCiudadConocida($mysqli, $texto) {
	static $conocidas = null;
	if ($conocidas === null) {
		$conocidas = [];
		foreach (['SELECT DISTINCT ciudad AS c FROM repositorio_rebate_producto WHERE eliminado_en IS NULL', 'SELECT DISTINCT ciudad AS c FROM repositorio_clientes_propiosac WHERE ciudad IS NOT NULL AND ciudad <> \'\''] as $q) {
			$res = @$mysqli->query($q);
			if (!$res) continue;
			while ($f = $res->fetch_assoc()) $conocidas[repositorio_texto_comparable($f['c'])] = true;
		}
		unset($conocidas['TODAS'], $conocidas['']);
	}
	return isset($conocidas[repositorio_texto_comparable($texto)]);
}

// Cliente de Directa ya existente sin ciudad: si el CEDI del Excel es una ciudad conocida, se la completa. Nunca pisa una ciudad ya llena.
function completarCiudadPropio($mysqli, $posId, $cediExcel) {
	$cediExcel = trim((string) $cediExcel);
	if ($cediExcel === '' || !propiosTieneCiudad($mysqli) || !esCiudadConocida($mysqli, $cediExcel)) return;
	$stmt = $mysqli->prepare("UPDATE repositorio_clientes_propiosac SET ciudad = ? WHERE pos_id = ? AND canal = 'directo' AND (ciudad IS NULL OR ciudad = '')");
	if (!$stmt) return;
	$stmt->bind_param('ss', $cediExcel, $posId);
	$stmt->execute();
	$stmt->close();
	maestroClientesEnMemoria($mysqli, true);
}

// Usado por "Pendientes de Asignar" de Cuotas/Acuerdo Completo — solo nuestra base propia, el maestro de Alicorp ya no aplica a esos 2 repos.
function posIdValido($mysqli, $posId) {
	$stmt = $mysqli->prepare('SELECT 1 FROM repositorio_clientes_propiosac WHERE pos_id = ? LIMIT 1');
	if (!$stmt) return false;
	$stmt->bind_param('s', $posId);
	$stmt->execute();
	$existe = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return (bool) $existe;
}

// $diagnostico (por referencia, opcional): si el cliente existe pero el Distribuidor/CEDI tipeado no matchea ninguno, queda el texto real registrado en nuestra base propia.
// $permitirCrear=false (previsualización): nunca escribe, aunque el cliente sería nuevo — $pendienteCrear (por referencia) queda true para que el caller lo muestre como "nuevo" sin alarmar.
// Pedido explícito del usuario (2026-10-05): Cuotas Trimestrales y Acuerdo Completo YA NO validan contra el maestro de Alicorp (desactualizado) — solo contra nuestra base propia (repositorio_clientes_propiosac), match EXACTO siempre. El maestro real sigue intacto para Registrar (acuerdo manual), que no pasa por esta función.
function resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel, $canal = 'directo', $distribuidorExcel = null, &$diagnostico = null, $creadoPor = null, $permitirCrear = true, &$pendienteCrear = null) {
	$clienteComparable = repositorio_texto_comparable($clienteExcel);
	$esDistribuidor = $canal === 'distribuidor';
	$candidatos = array_values(array_filter(maestroClientesEnMemoria($mysqli), function ($f) use ($clienteComparable, $esDistribuidor) {
		if (empty($f['es_propio']) || $f['pos_name_comparable'] !== $clienteComparable) return false;
		return $esDistribuidor ? $f['canal'] === 'DISTRIBUIDOR' : $f['canal'] !== 'DISTRIBUIDOR';
	}));

	if (count($candidatos) === 1) { if ($permitirCrear) completarCiudadPropio($mysqli, $candidatos[0]['pos_id'], $cediExcel); return $candidatos[0]['pos_id']; }
	$porParecido = false;
	if (count($candidatos) === 0) {
		// Pedido explícito del usuario (2026-10-06): nunca queda "sin identificar" — si hay algo parecido ya registrado (típico de un typo) se usa ese cliente en vez de duplicarlo.
		$candidatos = clientesPropiosSimilares($mysqli, $clienteExcel, $canal);
		$porParecido = (bool) $candidatos;
		if (!$candidatos) {
			// Nuestra base propia no tiene este cliente: lo creamos para que el próximo trimestre ya lo reconozca solo.
			if (!$permitirCrear) { $pendienteCrear = true; return null; }
			return crearClientePropio($mysqli, $clienteExcel, $cediExcel, $canal, $distribuidorExcel, $creadoPor);
		}
	}

	// Desempate por Ciudad en los 2 canales (el Distribuidor/Empresa varía con el tiempo, la Ciudad no) — $cediExcel ya trae la Ciudad en Distribuidor también.
	$cediComparable = repositorio_texto_comparable((string) $cediExcel);
	$desempatados = array_values(array_filter($candidatos, fn($f) => $f['supervisor_comparable'] === $cediComparable || ($f['ciudad_comparable'] ?? '') === $cediComparable));
	if (!$desempatados && $cediComparable !== '') {
		// Nombre matchea pero la Ciudad no: se registra igual (nunca bloquea el guardado), el diagnóstico avisa en la alerta de siempre con la Ciudad real para que el analista la corrija en el Excel.
		$diagnostico = ['campo' => 'supervisor', 'valores_reales' => array_values(array_unique(array_map(fn($f) => ($f['ciudad_real'] ?? '') !== '' ? $f['ciudad_real'] : $f['supervisor'], $candidatos)))];
	}
	if (!$desempatados) $desempatados = $candidatos;

	// Duplicado real (mismo nombre+canal+ciudad): toma el registro más reciente.
	usort($desempatados, fn($a, $b) => $b['id'] <=> $a['id']);
	// Unido por parecido, no por nombre exacto: la previsualización lo avisa como "¿Quisiste decir?" antes de guardar.
	if ($porParecido) $diagnostico = ['campo' => 'cliente_similar', 'valores_reales' => [$desempatados[0]['pos_name']]];
	if ($permitirCrear) completarCiudadPropio($mysqli, $desempatados[0]['pos_id'], $cediExcel);
	return $desempatados[0]['pos_id'];
}

// Cumplimiento de Cuota no valida contra el maestro de Alicorp: valida contra lo que YA se ingresó en los repositorios principales (Cuotas Trimestrales, Acuerdo Completo) para el mismo trimestre/año. Si no matchea ahí, el cliente queda sin identificar — no se busca en el maestro como respaldo.
function resolverPosIdDesdeRepoPrincipal($mysqli, $clienteExcel, $trimestre, $anio) {
	$clienteComparable = repositorio_texto_comparable($clienteExcel);
	$colComparable = repositorio_sql_comparable('cliente_excel');
	foreach (['repositorio_cuota_cliente', 'repositorio_acuerdo_completo_linea'] as $tabla) {
		$stmt = $mysqli->prepare(
			"SELECT DISTINCT pos_id FROM $tabla WHERE $colComparable = ? AND trimestre = ? AND anio = ? AND pos_id IS NOT NULL AND pos_id <> ''"
		);
		if (!$stmt) continue;
		$stmt->bind_param('sii', $clienteComparable, $trimestre, $anio);
		$stmt->execute();
		$posIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'pos_id');
		$stmt->close();
		if (count($posIds) === 1) return $posIds[0];
	}
	return null;
}

// Usuario que registró este cliente en el repo principal (Cuotas/Acuerdo Completo), para avisar si el Excel de Cumplimiento trae otro nombre.
function usuarioEsperadoDesdeRepoPrincipal($mysqli, $posId, $trimestre, $anio) {
	foreach (['repositorio_cuota_cliente', 'repositorio_acuerdo_completo_linea'] as $tabla) {
		$stmt = $mysqli->prepare(
			"SELECT DISTINCT usuario_excel FROM $tabla WHERE pos_id = ? AND trimestre = ? AND anio = ? AND usuario_excel IS NOT NULL AND usuario_excel <> ''"
		);
		if (!$stmt) continue;
		$stmt->bind_param('sii', $posId, $trimestre, $anio);
		$stmt->execute();
		$usuarios = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'usuario_excel');
		$stmt->close();
		if (count($usuarios) === 1) return $usuarios[0];
	}
	return null;
}

// Sugerencias de "¿quisiste decir?" cuando el cliente no matcheó nada (solo para mostrar, nunca para resolver pos_id solo). Solo contra nuestra base propia (ver resolverPosIdCliente()) — el maestro de Alicorp ya no aplica acá. Prefijo en cualquier dirección, con mínimo de letras para no traer basura corta.
function sugerirClienteSimilar($mysqli, $clienteExcel, $canal = 'directo') {
	return array_slice(array_values(array_unique(array_column(clientesPropiosSimilares($mysqli, $clienteExcel, $canal), 'pos_name'))), 0, 5);
}

// Clientes propios del mismo canal con nombre parecido: uno es prefijo del otro (mín. 6 letras) o comparten casi todas las palabras (ver nombresPorPalabrasSimilares()).
function clientesPropiosSimilares($mysqli, $clienteExcel, $canal = 'directo') {
	$clienteComparable = repositorio_texto_comparable($clienteExcel);
	if (strlen($clienteComparable) < 6) return [];
	$palabrasExcel = palabrasNombreCliente($clienteExcel);
	$esDistribuidor = $canal === 'distribuidor';
	$similares = [];
	foreach (maestroClientesEnMemoria($mysqli) as $f) {
		if (empty($f['es_propio'])) continue;
		if ($esDistribuidor ? $f['canal'] !== 'DISTRIBUIDOR' : $f['canal'] === 'DISTRIBUIDOR') continue;
		if (nombresClienteParecidos($clienteComparable, $palabrasExcel, $f['pos_name_comparable'], palabrasNombreCliente($f['pos_name']))) $similares[] = $f;
	}
	return $similares;
}

// Mismo criterio de parecido para 2 nombres cualquiera: prefijo (mín. 6 letras) o por palabras.
function nombresClienteParecidos($comparableA, array $palabrasA, $comparableB, array $palabrasB) {
	$masCorto = strlen($comparableA) < strlen($comparableB) ? $comparableA : $comparableB;
	if (strlen($masCorto) >= 6 && strncmp($comparableA, $comparableB, strlen($masCorto)) === 0) return true;
	return nombresPorPalabrasSimilares($palabrasA, $palabrasB);
}

// Previsualización: un cliente nuevo parecido a otro nuevo del MISMO archivo se agrupa con él y se avisa ya, no recién en la próxima subida (al guardar, el 2do se une al 1ro).
function agruparClienteNuevoEnArchivo(array &$nuevosEnArchivo, $clienteExcel, $clavePos) {
	$comparable = repositorio_texto_comparable($clienteExcel);
	$palabras = palabrasNombreCliente($clienteExcel);
	foreach ($nuevosEnArchivo as $n) {
		if ($n['comparable'] === $comparable) return ['clave' => $n['clave'], 'similar' => null];
		if (nombresClienteParecidos($comparable, $palabras, $n['comparable'], $n['palabras'])) return ['clave' => $n['clave'], 'similar' => $n['nombre']];
	}
	$nuevosEnArchivo[] = ['clave' => $clavePos, 'nombre' => $clienteExcel, 'comparable' => $comparable, 'palabras' => $palabras];
	return ['clave' => $clavePos, 'similar' => null];
}

// Palabras significativas de un nombre (mayúsculas, sin tildes, sin letras sueltas ni conectores/razón social) — "ANCHUNDIA MIRELLA L MIRELLA LUCIA" -> ANCHUNDIA, MIRELLA, LUCIA.
function palabrasNombreCliente($texto) {
	$texto = strtr(mb_strtoupper((string) $texto, 'UTF-8'), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
	$ignorar = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'EL', 'Y', 'SA', 'CIA', 'LTDA', 'SAS'];
	$palabras = preg_split('/[^A-Z0-9]+/', str_replace('.', '', $texto), -1, PREG_SPLIT_NO_EMPTY);
	return array_values(array_unique(array_filter($palabras, fn($p) => strlen($p) >= 2 && !in_array($p, $ignorar, true))));
}

// Parecido por palabras: al menos 2 en común y como mínimo el 75% de las del nombre más corto; una palabra larga con 1 letra de diferencia cuenta igual (typo).
function nombresPorPalabrasSimilares(array $a, array $b) {
	if (count($a) < 2 || count($b) < 2) return false;
	$comunes = 0;
	foreach ($a as $pa) {
		foreach ($b as $pb) {
			if ($pa === $pb || (strlen($pa) >= 5 && strlen($pb) >= 5 && levenshtein($pa, $pb) <= 1)) { $comunes++; break; }
		}
	}
	return $comunes >= 2 && $comunes >= ceil(0.75 * min(count($a), count($b)));
}

// CEDI/Ciudad real del cliente ya identificado, desambiguado por nombre (mismo criterio que resolverPosIdCliente) — vía el mismo cache en memoria, pos_id solo no es único en el maestro.
function cediRealDePosId($mysqli, $posId, $clienteExcel) {
	$clienteComparable = repositorio_texto_comparable($clienteExcel);
	$candidatos = array_values(array_filter(maestroClientesEnMemoria($mysqli), fn($f) => $f['pos_id'] === $posId && $f['pos_name_comparable'] === $clienteComparable));
	if (!$candidatos) return null;
	$cedis = array_unique(array_column($candidatos, 'cedi'));
	sort($cedis);
	return $cedis[0];
}

// Fila completa del maestro para un pos_id, desambiguada por nombre cuando se conoce (pos_id solo no es único). Sin nombre, o sin match exacto, cae al primero que encuentre (comportamiento de antes).
// $canalHint ('directo'/'distribuidor'/null): desempate cuando el nombre por sí solo no alcanza — bug real confirmado: el mismo pos_name existe en 2 filas del maestro con canal distinto (ej. "ACOSTA SANTAMARIA EDGAR PATRICIO" en DISTRIBUIDOR y en MAYORISTA), sin esto la elegida era arbitraria.
function clienteMaestroDePosId($mysqli, $posId, $clienteExcel = null, $canalHint = null) {
	$maestro = maestroClientesEnMemoria($mysqli);
	if ($clienteExcel) {
		$clienteComparable = repositorio_texto_comparable($clienteExcel);
		$candidatos = array_values(array_filter($maestro, fn($f) => $f['pos_id'] === $posId && $f['pos_name_comparable'] === $clienteComparable));
		if (count($candidatos) === 1) return $candidatos[0];
		if (count($candidatos) > 1 && $canalHint !== null) {
			$esDistribuidor = $canalHint === 'distribuidor';
			$porCanal = array_values(array_filter($candidatos, fn($f) => $esDistribuidor ? $f['canal'] === 'DISTRIBUIDOR' : $f['canal'] !== 'DISTRIBUIDOR'));
			if ($porCanal) return $porCanal[0];
		}
		if ($candidatos) return $candidatos[0];
	}
	foreach ($maestro as $f) {
		if ($f['pos_id'] === $posId) return $f;
	}
	return null;
}

// Actas armadas a mano en Registrar no tienen Excel de origen: completa nombre (y localidad) del cliente desde el maestro, igual que el PDF. Solo toca filas que vienen vacías.
function rellenarClienteDeActas($mysqli, array &$filas, $campoNombre = 'pos_name', $campoCedi = null, $campoCanal = null) {
	foreach ($filas as &$f) {
		if (!empty($f[$campoNombre]) || empty($f['pos_id'])) continue;
		$canalHint = $campoCanal ? (strtolower($f[$campoCanal] ?? '') ?: null) : null;
		$maestro = clienteMaestroDePosId($mysqli, $f['pos_id'], null, $canalHint);
		if (!$maestro) continue;
		$f[$campoNombre] = $maestro['pos_name'];
		if ($campoCedi && empty($f[$campoCedi])) $f[$campoCedi] = $maestro['cedi'];
	}
	unset($f);
}
