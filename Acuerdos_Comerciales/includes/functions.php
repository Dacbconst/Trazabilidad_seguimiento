<?php
// Todo el manejo de sesión y roles vive en un solo archivo a propósito:
// este proyecto es independiente del login de Xplora y no necesita la
// separación Session.php/Auth.php que usan Pintuco/Unilever.

function iniciar_sesion() {
	if (session_status() === PHP_SESSION_NONE) {
		session_set_cookie_params([
			'httponly' => true,
			'secure'   => SECURE,
			'samesite' => 'Lax',
		]);
		session_start();
	}
}

// Login simple sin password_hash — la contraseña se compara tal cual está guardada (decisión explícita del cliente).
// Devuelve true/false/'bloqueado' (5 intentos fallidos bloquean 15 min); sin el ALTER de intentos_fallidos, cae al login de siempre sin bloqueo.
function login($usuario, $password, $mysqli) {
	$stmt = $mysqli->prepare(
		"SELECT id, usuario, rol, supervisor, contrasena, intentos_fallidos, bloqueado_hasta
		 FROM repositorio_usuarios_acuerdos WHERE usuario = ? AND status = 'activo' LIMIT 1"
	);
	if ($stmt) {
		$stmt->bind_param('s', $usuario);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$stmt->close();

		if (!$row) {
			return false;
		}

		if ($row['bloqueado_hasta'] !== null && strtotime($row['bloqueado_hasta']) > time()) {
			return 'bloqueado';
		}

		if ($row['contrasena'] !== $password) {
			$intentos = (int) $row['intentos_fallidos'] + 1;
			if ($intentos >= 5) {
				$stmtUpd = $mysqli->prepare(
					'UPDATE repositorio_usuarios_acuerdos SET intentos_fallidos = 0, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?'
				);
				$stmtUpd->bind_param('i', $row['id']);
				$stmtUpd->execute();
				$stmtUpd->close();
				return 'bloqueado';
			}
			$stmtUpd = $mysqli->prepare('UPDATE repositorio_usuarios_acuerdos SET intentos_fallidos = ? WHERE id = ?');
			$stmtUpd->bind_param('ii', $intentos, $row['id']);
			$stmtUpd->execute();
			$stmtUpd->close();
			return false;
		}

		// Login exitoso: resetea el contador de intentos fallidos si tenía alguno.
		if ((int) $row['intentos_fallidos'] > 0) {
			$stmtReset = $mysqli->prepare('UPDATE repositorio_usuarios_acuerdos SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?');
			$stmtReset->bind_param('i', $row['id']);
			$stmtReset->execute();
			$stmtReset->close();
		}

		session_regenerate_id();
		$_SESSION['user_id']    = $row['id'];
		$_SESSION['username']   = $row['usuario'];
		$_SESSION['rol']        = $row['rol'];
		$_SESSION['supervisor'] = $row['supervisor'] ?? null;
		return true;
	}

	// ---------- Fallback: columnas de fuerza bruta todavía no existen ----------
	// Mismo login de siempre sin bloqueo (con fallback anidado si tampoco existe `supervisor`).
	$stmt = $mysqli->prepare(
		"SELECT id, usuario, rol, supervisor FROM repositorio_usuarios_acuerdos
		 WHERE usuario = ? AND contrasena = ? AND status = 'activo' LIMIT 1"
	);
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT id, usuario, rol FROM repositorio_usuarios_acuerdos
			 WHERE usuario = ? AND contrasena = ? AND status = 'activo' LIMIT 1"
		);
	}
	$stmt->bind_param('ss', $usuario, $password);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$row) {
		return false;
	}

	session_regenerate_id();
	$_SESSION['user_id']    = $row['id'];
	$_SESSION['username']   = $row['usuario'];
	$_SESSION['rol']        = $row['rol'];
	$_SESSION['supervisor'] = $row['supervisor'] ?? null;
	return true;
}

function login_check() {
	return isset($_SESSION['user_id'], $_SESSION['rol']);
}

// El acceso por módulo NO es jerárquico — cada sección define su propia lista de roles permitidos en includes/secciones.php.
function rolPermitido(array $rolesPermitidos) {
	return isset($_SESSION['rol']) && in_array($_SESSION['rol'], $rolesPermitidos, true);
}

// Etiqueta visible solo — el valor real de columna/ENUM sigue siendo 'desarrollador'/'superdesarrollador' en toda la app.
function rolEtiqueta($rol) {
	$etiquetas = [
		'desarrollador'      => 'Usuario',
		'superdesarrollador' => 'Administrador',
	];
	return isset($etiquetas[$rol]) ? $etiquetas[$rol] : $rol;
}

// ---------- Canal (Directo / Distribuidor) vía supervisor ----------
// repositorio_locales_supervisores_cliente es un maestro externo de Alicorp (nunca se modifica su esquema).
// El canal NUNCA se guarda: se deriva en vivo mirando el canal de los clientes del supervisor.

function listar_supervisores_disponibles($mysqli) {
	$supervisores = [];
	// Excluye los supervisores de prueba del maestro de Alicorp ("PRUEBA X") — filtro por prefijo, no una lista fija.
	$res = $mysqli->query(
		"SELECT DISTINCT supervisor FROM repositorio_locales_supervisores_cliente
		 WHERE supervisor IS NOT NULL AND supervisor <> '' AND supervisor NOT LIKE 'PRUEBA %'
		 ORDER BY supervisor"
	);
	// $res puede venir en false si la tabla no existe/no es accesible — no asumir que siempre hay resultado.
	if (!$res) return $supervisores;
	while ($row = $res->fetch_assoc()) {
		$supervisores[] = $row['supervisor'];
	}
	return $supervisores;
}

// Arma el mapa [supervisor => usuario] de quién ya lo tiene (1 supervisor = 1 cuenta activa).
// $excluirId permite que, al editar un usuario, su propio supervisor no cuente como "ya tomado".
function supervisores_asignados_activos($mysqli, $excluirId = 0) {
	$asignados = [];
	$stmt = $mysqli->prepare(
		"SELECT usuario, supervisor FROM repositorio_usuarios_acuerdos
		 WHERE supervisor IS NOT NULL AND supervisor <> '' AND status = 'activo' AND id <> ?"
	);
	if (!$stmt) return $asignados;
	$stmt->bind_param('i', $excluirId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	foreach ($filas as $f) {
		$asignados[$f['supervisor']] = $f['usuario'];
	}
	return $asignados;
}

// Ningún supervisor real mezcla DISTRIBUIDOR con COBERTURA/MAYORISTA.
// Caso borde sin resolver: un supervisor exclusivamente MAYORISTA caería como 'directo'.
//
// Se llama sin condición en cada carga de Registrar — nunca debe tirar fatal error;
// devuelve null (-> 'directo' por defecto) en vez de romper el login para todos.
function canalDeSupervisor($mysqli, $supervisor) {
	if (!$supervisor) return null;
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT canal FROM repositorio_locales_supervisores_cliente WHERE supervisor = ?"
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $supervisor);
	$stmt->execute();
	$canales = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'canal');
	$stmt->close();

	if (in_array('DISTRIBUIDOR', $canales, true)) return 'distribuidor';
	if ($canales) return 'directo';
	return null;
}

// ---------- Repositorio de Cuotas trimestrales ----------
// Match por nombre (pos_name LIKE), desempate por CEDI=supervisor (canal directa).
// A diferencia de Liquidación, acá se necesita UN solo pos_id; 0 o más de 1 candidato tras el desempate cae a "sin match".
function resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel) {
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT pos_id FROM repositorio_locales_supervisores_cliente
		 WHERE pos_name LIKE CONCAT(?, '%')"
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $clienteExcel);
	$stmt->execute();
	$posIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'pos_id');
	$stmt->close();

	if (count($posIds) === 1) return $posIds[0];
	if (count($posIds) === 0 || !$cediExcel) return null;

	$stmt = $mysqli->prepare(
		"SELECT DISTINCT pos_id FROM repositorio_locales_supervisores_cliente
		 WHERE pos_name LIKE CONCAT(?, '%') AND supervisor = ?"
	);
	if (!$stmt) return null;
	$stmt->bind_param('ss', $clienteExcel, $cediExcel);
	$stmt->execute();
	$desempatados = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'pos_id');
	$stmt->close();

	return count($desempatados) === 1 ? $desempatados[0] : null;
}

// Corrige "CATEGORIAS" del Excel de Cuotas contra el catálogo real: puede ser un Sector directo, o "Sector Subcategoría" pegados en el mismo texto.
// Si no matchea ninguno de los 2 (genuinamente ambiguo), devuelve null — nunca inventa un Sector.
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

// Resuelve Segmento/Categoría/Marca reales desde SUBCATEGORIA/MARCA del Excel de Cuotas (opcionales), tolerando plural/singular.
// Solo devuelve algo si el match es único; si no, null y el llamador cae al historial del cliente.
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
			if ($fila) { $stmt->close(); return $fila; }
		}
	}
	$stmt->close();
	return null;
}

// Busca Rebate% tolerando desajustes de nombre entre el Excel de JW y el catálogo real (ej. "LIQUIDOS" plural vs "LIQUIDO").
// Las variantes se prueban en el momento de buscar, sin tocar los datos ya guardados.
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

	// Variantes de plural/singular (agregar o quitar una "S" final) — mismo
	// criterio que resolverSectorReal(), esta vez sobre Sector Y Categoría.
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

	// Último recurso: Ciudad+Canal+Sector+Marca (sin Categoría, el campo que más varía de nombre) — solo si da una única fila.
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

// Busca % de Participación por Ciudad+Marca (Percha no guarda Categoría/Subcategoría, a diferencia de Rebate).
// Fallback: Ciudad exacta -> "TODAS" -> "RESTO CIUDADES"; devuelve el primer match, nunca mezcla.
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

// Dado un pos_id resuelto, encuentra el usuario responsable vía su supervisor real.
// Null si no tiene supervisor asignado o ese supervisor aún no tiene cuenta activa.
function usuarioIdDePosId($mysqli, $posId) {
	$stmt = $mysqli->prepare(
		"SELECT u.id FROM repositorio_locales_supervisores_cliente c
		 JOIN repositorio_usuarios_acuerdos u ON u.supervisor = c.supervisor AND u.status = 'activo'
		 WHERE c.pos_id = ? LIMIT 1"
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $posId);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $fila ? (int) $fila['id'] : null;
}

// Dueño real de una fila de Cuotas — el CEDI del Excel manda siempre sobre el maestro de Alicorp (pueden diverger entre sí).
// Alcance acotado a Actas Precargadas de Cuotas; si el CEDI no matchea ningún usuario activo, cae al maestro como respaldo.
function usuarioIdDeCuota($mysqli, $posId, $trimestre, $anio) {
	$stmt = $mysqli->prepare(
		"SELECT cedi_excel FROM repositorio_cuota_cliente WHERE pos_id = ? AND trimestre = ? AND anio = ? LIMIT 1"
	);
	if ($stmt) {
		$stmt->bind_param('sii', $posId, $trimestre, $anio);
		$stmt->execute();
		$fila = $stmt->get_result()->fetch_assoc();
		$stmt->close();
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
	return usuarioIdDePosId($mysqli, $posId);
}

// ---------- Actas Precargadas (Repositorio de Cuotas) ----------
// Resolución en vivo, nunca guardada. Agrupa por (pos_id, trimestre, anio) — varias filas de sector de un cliente son UNA sola Acta.
// Mismo criterio "CEDI del Excel gana" que usuarioIdDeCuota(), acá como JOIN para todas las filas pendientes a la vez.
function listar_actas_precargadas_pendientes($mysqli, $usuarioId) {
	if (!$usuarioId) return [];
	$stmt = $mysqli->prepare(
		"SELECT c.pos_id, c.cliente_excel, c.trimestre, c.anio, c.sector, c.valores_mensuales, c.updated_at
		 FROM repositorio_cuota_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN repositorio_locales_supervisores_cliente m ON m.pos_id = c.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u_master ON u_master.supervisor = m.supervisor AND u_master.status = 'activo'
		 WHERE c.estado = 'pendiente_uso' AND COALESCE(u_cedi.id, u_master.id) = ?
		 ORDER BY c.anio DESC, c.trimestre DESC, c.cliente_excel"
	);
	if (!$stmt) return [];
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$filasCrudas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	// Se cuenta en PHP, no en SQL, para coincidir exacto con lo que ve el asesor (categorías en $0 se descartan en obtener_precarga_detalle()).
	// `actualizado_en` = updated_at más reciente del grupo — así la clave de "visto" de la campanita cambia si el cliente se resube/reasigna.
	$grupos = [];
	foreach ($filasCrudas as $f) {
		// "OTRAS CATEGORIAS" se ignora acá también (2026-08-31, ver
		// obtener_precarga_detalle()) — el contador tiene que seguir
		// coincidiendo exacto con lo que el asesor va a ver de verdad.
		if (strtoupper(trim($f['sector'])) === 'OTRAS CATEGORIAS') continue;
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

// Arma el detalle de una Acta precargada para poblar Registrar (aún no existe el Acuerdo real, se crea al guardar).
// Segmento/Categoría/Marca se resuelven del Excel o, si falta, de la línea más reciente de ese pos_id+sector en Actas anteriores.
function obtener_precarga_detalle($mysqli, $posId, $trimestre, $anio) {
	// Sin `rebate_pct` — Cuotas nunca debió tomar Rebate del Excel (se busca abajo vía buscarRebateProducto()).
	$stmt = $mysqli->prepare(
		"SELECT id, sector, subcategoria, marca, valores_mensuales FROM repositorio_cuota_cliente
		 WHERE pos_id = ? AND trimestre = ? AND anio = ? AND estado = 'pendiente_uso'
		 ORDER BY sector"
	);
	// Fallback si `subcategoria`/`marca` todavía no existen en la base (falta
	// correr el ALTER correspondiente, ver CLAUDE.md) — mismo criterio
	// defensivo que login() con `supervisor`: nunca tumbar toda la Acta
	// Precargada por columnas nuevas que capaz no se corrieron todavía.
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT id, sector, NULL AS subcategoria, NULL AS marca, valores_mensuales FROM repositorio_cuota_cliente
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

	$stmtCliente = $mysqli->prepare(
		"SELECT pos_name, cedi, canal, tipo_distribuidor FROM repositorio_locales_supervisores_cliente WHERE pos_id = ? LIMIT 1"
	);
	$stmtCliente->bind_param('s', $posId);
	$stmtCliente->execute();
	$cliente = $stmtCliente->get_result()->fetch_assoc();
	$stmtCliente->close();
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

	// Ciudad/Canal para buscar Rebate — mismo criterio que buscarYAplicarRebate() en registrar.js.
	$esDistribuidorRebate = ($cliente['canal'] ?? null) === 'DISTRIBUIDOR';
	$ciudadRebate = $esDistribuidorRebate ? 'TODAS' : ($cliente['cedi'] ?: '');
	$canalRebate  = $esDistribuidorRebate ? 'DISTRIBUIDOR' : 'DIRECTA';

	$lineasMeta = [];
	foreach ($filasCuota as $fc) {
		// "OTRAS CATEGORIAS" se ignora al pregenerar (JW dejó de usar ese cajón genérico), aunque traiga monto.
		if (strtoupper(trim($fc['sector'])) === 'OTRAS CATEGORIAS') continue;

		$segmento = null; $categoria = null; $marca = null;

		// 1ra prioridad: si el Excel trajo SUBCATEGORIA/MARCA reales, resolverlas contra el catálogo es más confiable que el historial.
		if (!empty($fc['subcategoria']) && !empty($fc['marca'])) {
			$match = resolverProductoCuota($mysqli, $fc['sector'], $fc['subcategoria'], $fc['marca']);
			if ($match) {
				$segmento = $match['segmento'];
				$categoria = $match['categoria'];
				$marca = $match['marca'];
			}
		}

		// 2da prioridad: historial del cliente (línea más reciente de ese pos_id+sector) para lo que la 1ra no resolvió.
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
		// Categoría con $0 en los 3 meses se descarta acá — Meta de Compras no deja eliminar filas de una precarga, quedaría atrapada.
		if (array_sum($valores) <= 0) continue;

		// Rebate % real se busca en repositorio_rebate_producto (mismo criterio que la búsqueda en vivo de Registrar), solo si Categoría+Marca se resolvieron.
		// Sin match, sigue en 0 y editable.
		$rebatePct = 0;
		if ($categoria !== null && $marca !== null) {
			$valorRebate = buscarRebateProducto($mysqli, $ciudadRebate, $canalRebate, $fc['sector'], $categoria, $marca);
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
		'lineas'          => ['meta_compra' => $lineasMeta, 'cabecera' => [], 'ruma' => [], 'percha' => []],
	];
}

// Resumen para el superdesarrollador: 4 números de panorama + desglose por usuario.
// "Actas" = grupo (pos_id, trimestre, anio), no fila de sector, mismo criterio de listar_actas_precargadas_pendientes().
function resumen_cuotas($mysqli) {
	$agrupador = "CONCAT(c.pos_id, '|', c.trimestre, '|', c.anio)";

	$pendientes = 0;
	$r = $mysqli->query("SELECT COUNT(DISTINCT $agrupador) AS n FROM repositorio_cuota_cliente c WHERE c.estado = 'pendiente_uso'");
	if ($r) $pendientes = (int) $r->fetch_assoc()['n'];

	$usadas = 0;
	$r = $mysqli->query("SELECT COUNT(DISTINCT $agrupador) AS n FROM repositorio_cuota_cliente c WHERE c.estado = 'usada'");
	if ($r) $usadas = (int) $r->fetch_assoc()['n'];

	$pendientesMatch = 0;
	$r = $mysqli->query("SELECT COUNT(DISTINCT c.cliente_excel, c.trimestre, c.anio) AS n FROM repositorio_cuota_cliente c WHERE c.estado = 'pendiente_match'");
	if ($r) $pendientesMatch = (int) $r->fetch_assoc()['n'];

	// Lista única: usuarios con cuenta activa + supervisores del maestro sin cuenta todavía (`tiene_cuenta: false`).
	// Mismo criterio "CEDI del Excel gana" que usuarioIdDeCuota() — coincide con a quién le llega la Acta por la campanita.
	$stmt = $mysqli->prepare(
		"SELECT COALESCE(u_cedi.usuario, u_master.usuario) AS nombre,
		        COUNT(DISTINCT $agrupador) AS actas_pendientes,
		        (COALESCE(u_cedi.id, u_master.id) IS NOT NULL) AS tiene_cuenta
		 FROM repositorio_cuota_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN repositorio_locales_supervisores_cliente m ON m.pos_id = c.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u_master ON u_master.supervisor = m.supervisor AND u_master.status = 'activo'
		 WHERE c.estado = 'pendiente_uso' AND COALESCE(u_cedi.id, u_master.id) IS NOT NULL
		 GROUP BY COALESCE(u_cedi.id, u_master.id), nombre
		 UNION ALL
		 SELECT COALESCE(m.supervisor, c.cedi_excel) AS nombre, COUNT(DISTINCT $agrupador) AS actas_pendientes, 0 AS tiene_cuenta
		 FROM repositorio_cuota_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN repositorio_locales_supervisores_cliente m ON m.pos_id = c.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u_master ON u_master.supervisor = m.supervisor AND u_master.status = 'activo'
		 WHERE c.estado = 'pendiente_uso' AND u_cedi.id IS NULL AND u_master.id IS NULL
		 GROUP BY COALESCE(m.supervisor, c.cedi_excel)
		 ORDER BY actas_pendientes DESC"
	);
	$porUsuario = [];
	if ($stmt) {
		$stmt->execute();
		$porUsuario = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
		$stmt->close();
		foreach ($porUsuario as &$fila) { $fila['tiene_cuenta'] = (bool) $fila['tiene_cuenta']; }
		unset($fila);
	}

	// Actas precargadas que ya no se van a poder generar (el Local ya tiene un Acuerdo activo en el mismo Período).
	// Se detecta antes de que el asesor intente generar y el guardado se rechace en silencio.
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT c.pos_id, c.trimestre, c.anio FROM repositorio_cuota_cliente c WHERE c.estado = 'pendiente_uso'"
	);
	$grupos = [];
	if ($stmt) {
		$stmt->execute();
		$grupos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
		$stmt->close();
	}
	$chocan = [];
	if ($grupos) {
		$stmtExistente = $mysqli->prepare(
			"SELECT a.documento_no, a.created_at, u.usuario
			 FROM repositorio_acuerdos a
			 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
			 WHERE a.pos_id = ? AND a.anio = ? AND a.mes_inicio = ? AND a.mes_fin = ?
			   AND a.estado NOT IN ('borrador', 'anulado')
			 LIMIT 1"
		);
		$stmtCliente = $mysqli->prepare(
			"SELECT pos_name FROM repositorio_locales_supervisores_cliente WHERE pos_id = ? LIMIT 1"
		);
		foreach ($grupos as $g) {
			$mesInicio = ($g['trimestre'] - 1) * 3;
			$mesFin = $mesInicio + 2;
			$stmtExistente->bind_param('siii', $g['pos_id'], $g['anio'], $mesInicio, $mesFin);
			$stmtExistente->execute();
			$existente = $stmtExistente->get_result()->fetch_assoc();
			if (!$existente) continue;

			$posName = $g['pos_id'];
			if ($stmtCliente) {
				$stmtCliente->bind_param('s', $g['pos_id']);
				$stmtCliente->execute();
				$fc = $stmtCliente->get_result()->fetch_assoc();
				if ($fc && $fc['pos_name']) $posName = $fc['pos_name'];
			}
			$duenoId = usuarioIdDeCuota($mysqli, $g['pos_id'], $g['trimestre'], $g['anio']);
			$duenoNombre = null;
			if ($duenoId) {
				$ru = $mysqli->query('SELECT usuario FROM repositorio_usuarios_acuerdos WHERE id = '.(int) $duenoId);
				$fu = $ru ? $ru->fetch_assoc() : null;
				$duenoNombre = $fu ? $fu['usuario'] : null;
			}

			$chocan[] = [
				'pos_id'             => $g['pos_id'],
				'local'              => $posName,
				'trimestre'          => (int) $g['trimestre'],
				'anio'               => (int) $g['anio'],
				'asignado_a'         => $duenoNombre,
				'existente_documento_no' => $existente['documento_no'],
				'existente_usuario'  => $existente['usuario'],
				'existente_fecha'    => $existente['created_at'],
			];
		}
		$stmtExistente->close();
		if ($stmtCliente) $stmtCliente->close();
	}

	return [
		'pendientes'        => $pendientes,
		'usadas'            => $usadas,
		'pendientes_match'  => $pendientesMatch,
		'por_usuario'       => $porUsuario,
		'chocan'            => $chocan,
	];
}

// ---------- Gestión de Usuarios (repositorio_usuarios_acuerdos) ----------
// Centralizado aquí porque tanto la carga inicial (gestion-usuarios.php) como
// el refresco por AJAX (getters/tabla_usuarios.php) necesitan la misma consulta
// y el mismo render de fila, para no duplicar el SQL ni el HTML.

function listar_usuarios_acuerdos($mysqli, $busqueda = '', $pagina = 1, $porPagina = 8) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';

	$stmtTotal = $mysqli->prepare(
		"SELECT COUNT(*) AS total FROM repositorio_usuarios_acuerdos WHERE usuario LIKE ?"
	);
	$stmtTotal->bind_param('s', $like);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) {
		$pagina = $totalPaginas;
		$offset = ($pagina - 1) * $porPagina;
	}

	$stmt = $mysqli->prepare(
		"SELECT id, usuario, rol, supervisor, status, created_at FROM repositorio_usuarios_acuerdos
		 WHERE usuario LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
	);
	// Mismo fallback que login() — si `supervisor` todavía no existe en la
	// base, no reventar Gestión de Usuarios con un fatal error.
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT id, usuario, rol, NULL AS supervisor, status, created_at FROM repositorio_usuarios_acuerdos
			 WHERE usuario LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
		);
	}
	$stmt->bind_param('sii', $like, $porPagina, $offset);
	$stmt->execute();
	$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	// Canal por fila, misma resolución en vivo que canalDeSupervisor() — en PHP, no SQL (pagina de a 8, no vale una subquery).
	foreach ($usuarios as &$u) {
		$u['canal'] = canalDeSupervisor($mysqli, $u['supervisor'] ?? null);
	}
	unset($u);

	return [
		'usuarios'      => $usuarios,
		'total'         => $total,
		'pagina'        => $pagina,
		'total_paginas' => $totalPaginas,
	];
}

function inicialesUsuario($usuario) {
	$partes = preg_split('/[._\s-]+/', $usuario, -1, PREG_SPLIT_NO_EMPTY);
	if (count($partes) >= 2) {
		return strtoupper(substr($partes[0], 0, 1).substr($partes[1], 0, 1));
	}
	return strtoupper(substr($usuario, 0, 2));
}

function renderFilaUsuario(array $u, $sessionUserId) {
	$iniciales   = inicialesUsuario($u['usuario']);
	$rolClase    = 'ac-badge-'.$u['rol'];
	$rolLabel    = rolEtiqueta($u['rol']);
	$fecha       = date('Y-m-d', strtotime($u['created_at']));
	$checked     = $u['status'] === 'activo' ? 'checked' : '';
	$esActual    = ((int) $u['id'] === (int) $sessionUserId);
	$disabled    = $esActual ? 'disabled title="No puedes desactivar tu propia cuenta"' : '';
	$claseFila   = $u['status'] === 'inactivo' ? 'ac-row-inactivo' : '';
	$usuarioAttr = htmlspecialchars($u['usuario'], ENT_QUOTES);
	$rolAttr     = htmlspecialchars($u['rol'], ENT_QUOTES);
	$supervisorAttr = htmlspecialchars($u['supervisor'] ?? '', ENT_QUOTES);
	// Canal ya viene resuelto desde listar_usuarios_acuerdos() (array_key_exists porque puede ser null de verdad, no solo faltante).
	$canalTexto  = array_key_exists('canal', $u)
		? ($u['canal'] === 'distribuidor' ? 'Distribuidor' : ($u['canal'] === 'directo' ? 'Directo' : '—'))
		: '—';

	return '
	<tr data-id="'.(int) $u['id'].'" class="'.$claseFila.'">
		<td>
			<div class="ac-user-cell">
				<div class="ac-avatar-initials">'.htmlspecialchars($iniciales).'</div>
				<p class="ac-user-name">'.htmlspecialchars($u['usuario']).'</p>
			</div>
		</td>
		<td><span class="ac-badge '.$rolClase.'">'.htmlspecialchars($rolLabel).'</span></td>
		<td>'.htmlspecialchars($u['supervisor'] ?: '—').'</td>
		<td>'.htmlspecialchars($canalTexto).'</td>
		<td class="ac-mono">'.htmlspecialchars($fecha).'</td>
		<td>
			<label class="ac-switch">
				<input type="checkbox" class="ac-toggle-estado" data-id="'.(int) $u['id'].'" '.$checked.' '.$disabled.'>
				<span class="ac-slider"></span>
			</label>
		</td>
		<td class="ac-text-right">
			<div class="ac-row-actions">
				<button type="button" class="ac-icon-btn ac-btn-clave" data-id="'.(int) $u['id'].'" data-usuario="'.$usuarioAttr.'" title="Modificar Clave">
					<span class="material-symbols-outlined">key</span>
				</button>
				<button type="button" class="ac-icon-btn ac-btn-editar" data-id="'.(int) $u['id'].'" data-usuario="'.$usuarioAttr.'" data-rol="'.$rolAttr.'" data-supervisor="'.$supervisorAttr.'" title="Editar Perfil">
					<span class="material-symbols-outlined">edit</span>
				</button>
			</div>
		</td>
	</tr>';
}

// ---------- Historial de Acuerdos (repositorio_acuerdos) ----------
// Mismo patrón que arriba: la carga inicial (historial.php) y el refresco
// por AJAX (getters/listar_historial.php) comparten la misma consulta y el
// mismo render de fila para no duplicar SQL ni HTML.

function mesCorto($mes) {
	$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
	return isset($meses[$mes]) ? $meses[$mes] : '';
}

// "Q1 (Ene-Mar)" para períodos que son un trimestre exacto (mismo texto que el filtro de Historial); un rango irregular cae al formato anterior sin "Qx".
function periodoCorto($mesInicio, $mesFin) {
	if ($mesInicio % 3 === 0 && $mesFin === $mesInicio + 2) {
		$trimestre = intdiv($mesInicio, 3) + 1;
		return 'Q'.$trimestre.' ('.mesCorto($mesInicio).'-'.mesCorto($mesFin).')';
	}
	if ($mesInicio === $mesFin) return mesCorto($mesInicio);
	return mesCorto($mesInicio).' - '.mesCorto($mesFin);
}

// Devuelve [mesInicio, mesFin] (0-11) del trimestre 1-4, o null si no es válido.
function trimestreABounds($trimestre) {
	$trimestre = (int) $trimestre;
	if ($trimestre < 1 || $trimestre > 4) return null;
	$inicio = ($trimestre - 1) * 3;
	return [$inicio, $inicio + 2];
}

// Un Acta 'generado'/'enviado' con 20+ días desde fecha_generacion pasa a 'vencido' (bloquea subir firma, desaparece de Historial).
// Sin cron: corre cada vez que se listan Actas o se calculan alertas; sin el ALTER de 'vencido' en el ENUM, no hace nada.
function barrer_actas_vencidas($mysqli) {
	$mysqli->query(
		"UPDATE repositorio_acuerdos
		 SET estado = 'vencido'
		 WHERE estado IN ('generado', 'enviado')
		   AND fecha_generacion IS NOT NULL
		   AND fecha_generacion < DATE_SUB(CURDATE(), INTERVAL 20 DAY)"
	);
}

// Actas propias por vencer, alimenta "Mis Actas" de la campanita — 'generado'/'enviado' sin firmar con $diasUmbral días o menos.
function listar_alertas_firma_propias($mysqli, $usuarioId, $diasUmbral = 5) {
	if (!$usuarioId) return [];
	barrer_actas_vencidas($mysqli);
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.fecha_generacion,
		        DATEDIFF(DATE_ADD(a.fecha_generacion, INTERVAL 20 DAY), CURDATE()) AS dias_restantes
		 FROM repositorio_acuerdos a
		 WHERE a.creado_por = ?
		   AND a.estado IN ('generado', 'enviado')
		   AND a.fecha_generacion IS NOT NULL
		 HAVING dias_restantes BETWEEN 0 AND ?
		 ORDER BY dias_restantes ASC"
	);
	if (!$stmt) return [];
	$stmt->bind_param('ii', $usuarioId, $diasUmbral);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return $filas;
}

// $usuarioId filtra por repositorio_acuerdos.creado_por (dato real, no inferido por supervisor/territorio).
// $trimestre: 1-4 o 0="Todos". $anio: 0="Todos". $filtroFirma: 'todos'|'firmadas'|'pendientes' (activado desde los stat tiles, no un <select>).
function listar_historial_acuerdos($mysqli, $busqueda = '', $trimestre = 0, $anio = 0, $filtroFirma = 'todos', $pagina = 1, $usuarioId = null, $porPagina = 10, $rol = null, $canal = 'total') {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';
	$anio   = (int) $anio;

	$bounds           = trimestreABounds($trimestre);
	$trimestreActivo  = $bounds ? 1 : 0;
	$mesInicioFiltro  = $bounds ? $bounds[0] : -1;
	$mesFinFiltro     = $bounds ? $bounds[1] : -1;

	// Sin user_id no hay forma de saber qué acuerdos "son suyos" — vacío, nunca mostrar los de todo el mundo.
	if (!$usuarioId) {
		return ['acuerdos' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	}

	// Corre el barrido de vencimiento acá para que Historial nunca muestre un Acta que ya debería estar vencida.
	barrer_actas_vencidas($mysqli);

	// "Ver todo": el superdesarrollador ve Actas de TODOS los asesores, no solo las propias.
	// `? = 1 OR a.creado_por = ?` mantiene el conteo de parámetros fijo sin importar el rol (evita bind_param variable).
	$verTodos = ($rol === 'superdesarrollador') ? 1 : 0;

	// Filtro de Canal ($canal ya viene validado contra whitelist en el caller). EXISTS, no `d.canal = 'DISTRIBUIDOR'` directo sobre el JOIN:
	// un pos_id puede tener 2+ filas de canal distinto en el maestro, así que un filtro directo duplicaba el Acuerdo entre pastillas.
	$condicionCanal = '';
	if ($canal === 'directo') {
		$condicionCanal = " AND NOT EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.pos_id = a.pos_id AND d2.canal = 'DISTRIBUIDOR')";
	} elseif ($canal === 'distribuidor') {
		$condicionCanal = " AND EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.pos_id = a.pos_id AND d2.canal = 'DISTRIBUIDOR')";
	}

	// El JOIN es solo para pos_name/cedi/canal; GROUP BY a.id evita duplicar el Acuerdo por los ~1,116 pos_id repetidos en el maestro.
	// Condición de firma en texto plano (no placeholder); si acta_firmada_archivo no existiera, cae al mismo fallback sin firma de abajo.
	$condicionFirma = '';
	if ($filtroFirma === 'firmadas') $condicionFirma = ' AND a.acta_firmada_archivo IS NOT NULL';
	elseif ($filtroFirma === 'pendientes') $condicionFirma = ' AND a.acta_firmada_archivo IS NULL';

	$sqlBase = "FROM repositorio_acuerdos a
		JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		WHERE a.estado NOT IN ('borrador', 'anulado', 'vencido')
		  AND (? = 1 OR a.creado_por = ?)
		  AND d.pos_name LIKE ?
		  AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		  AND (? = 0 OR a.anio = ?)
		  $condicionFirma
		  $condicionCanal";

	// Se renderiza siempre en cada login (todas las secciones se incluyen, no solo la activa) — nunca debe tirar fatal error si el JOIN externo falla.
	$stmtTotal = $mysqli->prepare("SELECT COUNT(DISTINCT a.id) AS total $sqlBase");
	if (!$stmtTotal) {
		return ['acuerdos' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	}
	$stmtTotal->bind_param('iisiiiii', $verTodos, $usuarioId, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) {
		$pagina = $totalPaginas;
		$offset = ($pagina - 1) * $porPagina;
	}

	// Sin el ALTER de acta_firmada_archivo, prepare() da false acá — mismo fallback que login() para `supervisor`.
	// Canal canónico: el `d.canal` crudo del JOIN es ambiguo con pos_id duplicados; usa el mismo EXISTS que decide la pastilla, para que el badge nunca contradiga el filtro.
	$canalCanonico = "(CASE WHEN EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.pos_id = a.pos_id AND d2.canal = 'DISTRIBUIDOR') THEN 'DISTRIBUIDOR' ELSE 'OTRO' END) AS canal";
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.mes_inicio, a.mes_fin, a.fecha_generacion, a.estado, a.creado_por,
		        (a.acta_firmada_archivo IS NOT NULL) AS tiene_firma, a.acta_firmada_mime,
		        d.pos_name, d.cedi, $canalCanonico
		 $sqlBase
		 GROUP BY a.id
		 ORDER BY a.fecha_generacion DESC, a.id DESC
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT a.id, a.documento_no, a.mes_inicio, a.mes_fin, a.fecha_generacion, a.estado, a.creado_por,
			        0 AS tiene_firma, NULL AS acta_firmada_mime,
			        d.pos_name, d.cedi, $canalCanonico
			 $sqlBase
			 GROUP BY a.id
			 ORDER BY a.fecha_generacion DESC, a.id DESC
			 LIMIT ? OFFSET ?"
		);
	}
	if (!$stmt) {
		return ['acuerdos' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	}
	$stmt->bind_param('iisiiiiiii', $verTodos, $usuarioId, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio, $porPagina, $offset);
	$stmt->execute();
	$acuerdos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return [
		'acuerdos'      => $acuerdos,
		'total'         => $total,
		'pagina'        => $pagina,
		'total_paginas' => $totalPaginas,
	];
}

// Stat tiles de Historial — respetan búsqueda/trimestre/año pero NO el filtro de firma (esos números son lo que decide ese filtro).
function obtener_stats_historial($mysqli, $busqueda, $trimestre, $anio, $usuarioId, $rol = null, $canal = 'total') {
	$vacio = ['total' => 0, 'firmadas' => 0, 'pendientes' => 0, 'pendiente_mas_antigua' => null];
	if (!$usuarioId) return $vacio;

	$like = '%'.$busqueda.'%';
	$anio = (int) $anio;
	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;
	// Mismo criterio "ver todo" y filtro de Canal que listar_historial_acuerdos() — los stat tiles reflejan el mismo alcance que la tabla.
	$verTodos = ($rol === 'superdesarrollador') ? 1 : 0;
	$condicionCanal = '';
	if ($canal === 'directo') {
		$condicionCanal = " AND NOT EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.pos_id = a.pos_id AND d2.canal = 'DISTRIBUIDOR')";
	} elseif ($canal === 'distribuidor') {
		$condicionCanal = " AND EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.pos_id = a.pos_id AND d2.canal = 'DISTRIBUIDOR')";
	}

	$stmt = $mysqli->prepare(
		"SELECT COUNT(DISTINCT a.id) AS total,
		        COUNT(DISTINCT CASE WHEN a.acta_firmada_archivo IS NOT NULL THEN a.id END) AS firmadas,
		        MIN(CASE WHEN a.acta_firmada_archivo IS NULL THEN a.fecha_generacion END) AS pendiente_mas_antigua
		 FROM repositorio_acuerdos a
		 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		 WHERE a.estado NOT IN ('borrador', 'anulado', 'vencido')
		   AND (? = 1 OR a.creado_por = ?)
		   AND d.pos_name LIKE ?
		   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		   AND (? = 0 OR a.anio = ?)
		   $condicionCanal"
	);
	if (!$stmt) return $vacio; // acta_firmada_archivo todavía no existe, ver CLAUDE.md.
	$stmt->bind_param('iisiiiii', $verTodos, $usuarioId, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	$total    = (int) $fila['total'];
	$firmadas = (int) $fila['firmadas'];
	return [
		'total'                 => $total,
		'firmadas'              => $firmadas,
		'pendientes'            => $total - $firmadas,
		'pendiente_mas_antigua' => $fila['pendiente_mas_antigua'],
	];
}

// Años con al menos un Acuerdo real (no borrador/anulado) de este usuario —
// para poblar el filtro "Año" de Historial sin inventar un rango fijo.
function listar_anios_disponibles($mysqli, $usuarioId, $rol = null) {
	if (!$usuarioId) return [];
	// "Ver todo": el superdesarrollador ve años de TODOS los Acuerdos. Sin filtrar por canal a propósito (el selector de año no depende de la pastilla).
	$verTodos = ($rol === 'superdesarrollador') ? 1 : 0;
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT anio FROM repositorio_acuerdos
		 WHERE (? = 1 OR creado_por = ?) AND estado NOT IN ('borrador', 'anulado', 'vencido')
		 ORDER BY anio DESC"
	);
	if (!$stmt) return [];
	$stmt->bind_param('ii', $verTodos, $usuarioId);
	$stmt->execute();
	$anios = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'anio');
	$stmt->close();
	return array_map('intval', $anios);
}

// $mostrarCanal (2026-08-31): agrega una celda de Canal (Directo/Distribuidor)
// entre Localidad y Periodo — solo tiene sentido para quien puede ver Actas de
// los 2 canales mezcladas (superdesarrollador, ver historial.php); un
// desarrollador normal siempre ve un solo canal, así que ahí la columna se
// omite y la fila queda EXACTAMENTE igual que antes de este cambio.
function renderFilaHistorial(array $a, $mostrarCanal = false) {
	$fecha = $a['fecha_generacion'] ? date('d/m/Y', strtotime($a['fecha_generacion'])) : '—';
	$celdaCanal = '';
	if ($mostrarCanal) {
		$esDistribuidor = ($a['canal'] ?? '') === 'DISTRIBUIDOR';
		$celdaCanal = '<td><span class="ac-badge ac-badge-canal-'.($esDistribuidor ? 'distribuidor' : 'directo').'">'.($esDistribuidor ? 'Distribuidor' : 'Directo').'</span></td>';
	}

	// Firma subida (2026-08-21): reusa `acta_firmada_archivo` (antes `firmas`,
	// JSON sin usar — ver CLAUDE.md) para saber si ya volvió el Acta firmada a
	// mano. Un solo botón por fila que cambia de ícono/acción según el
	// estado: subir si falta, ver el archivo si ya está.
	$tieneFirma = !empty($a['tiene_firma']);
	// Aviso visual de vencimiento (2026-08-25) — el badge "Pendiente" de
	// siempre pasa a mostrar la cuenta regresiva cuando quedan 5 días o
	// menos (mismo umbral que la campanita del header, ver
	// listar_alertas_firma_propias() más abajo), con 2 niveles de urgencia.
	// Texto rediseñado tras revisar la etiqueta contra las heurísticas de
	// Nielsen (ver concepto "Sala de Alertas", 2026-08-25, aprobado por el
	// usuario tal cual): "Vence en N días" no decía QUÉ vence — se podía
	// leer como que el ACUERDO comercial se cae, no que es la ventana para
	// subir la foto de la firma (heurística 2, coincidencia con el mundo
	// real). "Sube la firma — N días" nombra la acción pendiente en vez de
	// solo el dato (heurística 5, prevención de errores) y usa el
	// vocabulario del usuario ("sube la firma") en vez del sistema
	// ("vence"). $filaUrgencia además marca el `<tr>` para la franja de
	// color lateral (ver ac-fila-urgente/ac-fila-critica en style.css).
	$filaUrgencia = '';
	if ($tieneFirma) {
		$firmaBadge = '<span class="ac-badge ac-badge-ok">Firmada</span>';
	} else {
		$diasRestantes = null;
		if (!empty($a['fecha_generacion']) && in_array($a['estado'] ?? '', ['generado', 'enviado'], true)) {
			$limite = (new DateTime($a['fecha_generacion']))->modify('+20 days');
			$diasRestantes = (int) (new DateTime('today'))->diff($limite)->format('%r%a');
		}
		if ($diasRestantes !== null && $diasRestantes <= 5) {
			$texto = $diasRestantes <= 0 ? 'Sube la firma — hoy' : ($diasRestantes === 1 ? 'Sube la firma — 1 día' : 'Sube la firma — '.$diasRestantes.' días');
			$esCritico = $diasRestantes <= 1;
			$clase = $esCritico ? 'ac-badge-critico' : 'ac-badge-urgente';
			$filaUrgencia = $esCritico ? ' ac-fila-critica' : ' ac-fila-urgente';
			$firmaBadge = '<span class="ac-badge '.$clase.'" title="Plazo de firma: 20 días desde la generación del Acta">'.$texto.'</span>';
		} else {
			$firmaBadge = '<span class="ac-badge ac-badge-revisar">Pendiente</span>';
		}
	}
	// Ownership (2026-08-31): con el superdesarrollador viendo Actas de
	// OTROS asesores en Historial (ver listar_historial_acuerdos(), "ver
	// todo"), Subir Firma/Ver Firma y Eliminar tienen que seguir bloqueados
	// para una Acta ajena — decisión confirmada con el usuario, esos 2
	// botones no se habilitan solo porque ahora se VE la fila. Ver Detalles
	// y Descargar PDF sí quedan libres para cualquiera (ver
	// getters/generar_acta_pdf.php, ya actualizado). Para un desarrollador
	// normal `$esPropio` siempre da true (nunca ve Actas ajenas de entrada),
	// así que esto no cambia nada para ese rol.
	$esPropio = (int) ($a['creado_por'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0);
	$disabledAjeno = $esPropio ? '' : ' disabled';
	$tituloAjeno = ' title="Esta Acta la generó otro asesor — solo esa cuenta puede subir la firma."';

	// .ac-row-actions-primary + el <span> de texto (oculto en desktop, ver
	// style.css): en mobile este es el botón que más importa de toda la fila
	// — la mayoría de las subidas de Acta firmada van a pasar por celular,
	// así que necesita texto visible y buen tamaño táctil, no un ícono
	// pelado igual de chico que "Eliminar" (2026-08-25, pedido explícito).
	$firmaBtn = $tieneFirma
		? '<button type="button" class="ac-icon-btn ac-icon-btn-success ac-row-actions-primary hist-btn-firma" data-id="'.(int) $a['id'].'" data-doc="'.htmlspecialchars($a['documento_no']).'" data-tiene-firma="1" data-mime="'.htmlspecialchars($a['acta_firmada_mime'] ?? '').'"'.$disabledAjeno.($esPropio ? ' title="Ver Acta Firmada"' : $tituloAjeno).'><span class="material-symbols-outlined">task_alt</span><span class="ac-row-actions-primary-label">Ver Firma</span></button>'
		: '<button type="button" class="ac-icon-btn ac-row-actions-primary hist-btn-firma" data-id="'.(int) $a['id'].'" data-doc="'.htmlspecialchars($a['documento_no']).'" data-tiene-firma="0"'.$disabledAjeno.($esPropio ? ' title="Subir Acta Firmada"' : $tituloAjeno).'><span class="material-symbols-outlined">upload_file</span><span class="ac-row-actions-primary-label">Subir Firma</span></button>';

	return '
	<tr data-id="'.(int) $a['id'].'" class="hist-fila'.$filaUrgencia.($mostrarCanal ? ' hist-fila-con-canal' : '').'">
		<td><button type="button" class="ac-link-id hist-btn-ver" data-id="'.(int) $a['id'].'">#'.htmlspecialchars($a['documento_no']).'</button></td>
		<td class="ac-hist-distribuidor">'.htmlspecialchars($a['pos_name']).'</td>
		<td>'.htmlspecialchars($a['cedi'] ?: '—').'</td>
		'.$celdaCanal.'
		<td class="ac-text-center">'.htmlspecialchars(periodoCorto((int) $a['mes_inicio'], (int) $a['mes_fin'])).'</td>
		<td class="ac-text-center">'.$firmaBadge.'</td>
		<td class="ac-text-right ac-tabular">'.$fecha.'</td>
		<td class="ac-text-right">
			<div class="ac-row-actions">
				'.$firmaBtn.'
				<button type="button" class="ac-icon-btn hist-btn-descargar" data-id="'.(int) $a['id'].'" title="Descargar PDF">
					<span class="material-symbols-outlined">download</span>
				</button>
				<button type="button" class="ac-icon-btn hist-btn-ver" data-id="'.(int) $a['id'].'" title="Ver Detalles">
					<span class="material-symbols-outlined">visibility</span>
				</button>
				<button type="button" class="ac-icon-btn ac-icon-btn-danger hist-btn-eliminar" data-id="'.(int) $a['id'].'" data-doc="'.htmlspecialchars($a['documento_no']).'"'.$disabledAjeno.($esPropio ? ' title="Eliminar"' : ' title="Esta Acta la generó otro asesor — solo esa cuenta puede eliminarla."').'>
					<span class="material-symbols-outlined">delete</span>
				</button>
			</div>
		</td>
	</tr>';
}

// Cabecera + las 4 tablas de líneas de un acuerdo puntual, para el detalle/Acta imprimible de Historial.
function obtener_acuerdo_detalle($mysqli, $acuerdoId) {
	// LIMIT 1 alcanza pese a pos_id duplicados en el maestro (misma pos_name/cedi). LEFT JOIN a usuarios_acuerdos: acuerdos huérfanos (creado_por=NULL) no rompen el detalle.
	// d.canal decide el formato de Acta (Directo/Distribuidor); d.tipo_distribuidor es la Empresa Distribuidora, va en "Estimado(a)" separado del Local.
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.pos_id, a.anio, a.mes_inicio, a.mes_fin, a.estado, a.fecha_generacion, a.creado_por, a.sin_visibilidad,
		        d.pos_name, d.cedi, d.canal, d.tipo_distribuidor, u.usuario AS ejecutivo_comercial
		 FROM repositorio_acuerdos a
		 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
		 WHERE a.id = ? LIMIT 1"
	);
	if (!$stmt) return null;
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$cabecera = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$cabecera) return null;

	$stmt = $mysqli->prepare(
		"SELECT tipo, segmento, sector, categoria, marca, rebate_pct, cantidad_max_percha, participacion_pct, precio_percha,
		        valores_mensuales, valor_mensual_unico, orden
		 FROM repositorio_acuerdo_lineas WHERE acuerdo_id = ? ORDER BY tipo, orden"
	);
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	$lineas = ['meta_compra' => [], 'cabecera' => [], 'ruma' => [], 'percha' => []];
	foreach ($filas as $f) {
		$valores = $f['valores_mensuales'] !== null ? json_decode($f['valores_mensuales'], true) : [];
		$lineas[$f['tipo']][] = [
			'segmento'            => $f['segmento'],
			'sector'              => $f['sector'],
			'categoria'           => $f['categoria'],
			'marca'               => $f['marca'],
			'rebate_pct'          => $f['rebate_pct'] !== null ? (float) $f['rebate_pct'] : 0,
			'cantidad_max_percha' => (int) $f['cantidad_max_percha'],
			'participacion'       => $f['participacion_pct'] ?? '',
			'precio_percha'       => $f['precio_percha'] !== null ? (float) $f['precio_percha'] : 0,
			'valores_mensuales'   => is_array($valores) ? $valores : [],
			'valor_mensual_unico' => $f['valor_mensual_unico'] !== null ? (float) $f['valor_mensual_unico'] : 0,
		];
	}

	return [
		'id'                => (int) $cabecera['id'],
		'documento_no'      => $cabecera['documento_no'],
		'pos_id'            => $cabecera['pos_id'],
		'anio'              => (int) $cabecera['anio'],
		'mes_inicio'        => (int) $cabecera['mes_inicio'],
		'mes_fin'           => (int) $cabecera['mes_fin'],
		'estado'            => $cabecera['estado'],
		'fecha_generacion'  => $cabecera['fecha_generacion'],
		'creado_por'        => $cabecera['creado_por'] !== null ? (int) $cabecera['creado_por'] : null,
		'distribuidor'      => $cabecera['pos_name'],
		'localidad'         => $cabecera['cedi'] ?: '—',
		'es_distribuidor'   => ($cabecera['canal'] ?? null) === 'DISTRIBUIDOR',
		'sin_visibilidad'   => !empty($cabecera['sin_visibilidad']),
		'empresa_distribuidora' => $cabecera['tipo_distribuidor'] ?: '',
		'ejecutivo_comercial' => $cabecera['ejecutivo_comercial'] ?: '',
		'lineas'            => $lineas,
	];
}

// Borradores propios del usuario logueado, para "Mis Borradores" — mismo scoping por creador que listar_historial_acuerdos().
function listar_borradores_usuario($mysqli, $usuarioId) {
	if (!$usuarioId) return [];
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.anio, a.mes_inicio, a.mes_fin, a.updated_at,
		        d.pos_name, d.cedi
		 FROM repositorio_acuerdos a
		 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		 WHERE a.estado = 'borrador' AND a.creado_por = ?
		 GROUP BY a.id
		 ORDER BY a.updated_at DESC"
	);
	if (!$stmt) return [];
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return $filas;
}

// ---------- Módulo Repositorios ----------
// Dos catálogos self-service (Rebate, Participación de Percha) que autocompletan y bloquean esos campos en el Acta.
// $stmt puede venir null si no se corrió el schema — se devuelve vacío en vez de fatal error, mismo criterio que `supervisor`.
function listar_repositorio_rebate($mysqli, $busqueda = '', $pagina = 1, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';

	// eliminado_en IS NULL (borrado lógico) — el listado normal nunca muestra filas borradas, esas viven en "Eliminados".
	$stmtTotal = $mysqli->prepare(
		"SELECT COUNT(*) AS total FROM repositorio_rebate_producto
		 WHERE eliminado_en IS NULL AND (ciudad LIKE ? OR canal LIKE ? OR sector LIKE ? OR categoria LIKE ? OR marca LIKE ?)"
	);
	if (!$stmtTotal) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmtTotal->bind_param('sssss', $like, $like, $like, $like, $like);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) { $pagina = $totalPaginas; $offset = ($pagina - 1) * $porPagina; }

	$stmt = $mysqli->prepare(
		"SELECT r.id, r.ciudad, r.canal, r.sector, r.categoria, r.marca, r.rebate_pct, r.updated_at, u.usuario AS actualizado_por_usuario
		 FROM repositorio_rebate_producto r
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = r.actualizado_por
		 WHERE r.eliminado_en IS NULL AND (r.ciudad LIKE ? OR r.canal LIKE ? OR r.sector LIKE ? OR r.categoria LIKE ? OR r.marca LIKE ?)
		 ORDER BY r.ciudad, r.canal, r.sector, r.categoria, r.marca
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmt->bind_param('sssssii', $like, $like, $like, $like, $like, $porPagina, $offset);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'total_paginas' => $totalPaginas];
}

function listar_repositorio_participacion($mysqli, $busqueda = '', $pagina = 1, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';

	// eliminado_en IS NULL (borrado lógico, mismo criterio que listar_repositorio_rebate()). Busca/ordena también por Ciudad.
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

// Cuotas resueltas (pos_id encontrado) — 'pendiente_match' vive aparte en listar_repositorio_cuotas_pendientes_match(), mismo concepto que "Pendientes de Asignar" de Liquidación.
function listar_repositorio_cuotas($mysqli, $busqueda = '', $pagina = 1, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';

	// Búsqueda cubre todas las columnas visibles (CEDI/Cliente/Plan/Categoría/Subcategoría/Marca) — antes solo Cliente/pos_id/Categoría, otros campos no filtraban nada.
	// 2 niveles de prepare() (con/sin Subcategoría+Marca) por si ese ALTER no se corrió en algún entorno.
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

	// c.subcategoria/c.marca: sin esto la tabla no mostraba lo que el Excel trajo en esas columnas, aunque ya estuvieran guardadas. Sin `rebate_pct` (ver obtener_precarga_detalle()).
	// Mismo fallback de 2 niveles que la búsqueda de arriba, pero para las columnas de resultado, independiente de si la búsqueda las necesita.
	$whereConSub = "c.estado <> 'pendiente_match' AND (c.cedi_excel LIKE ? OR c.cliente_excel LIKE ? OR c.pos_id LIKE ? OR c.plan LIKE ? OR c.sector LIKE ? OR c.subcategoria LIKE ? OR c.marca LIKE ?)";
	$whereSinSub = "c.estado <> 'pendiente_match' AND (c.cedi_excel LIKE ? OR c.cliente_excel LIKE ? OR c.pos_id LIKE ? OR c.plan LIKE ? OR c.sector LIKE ?)";
	$stmt = $mysqli->prepare(
		"SELECT c.id, c.pos_id, c.cliente_excel, c.cedi_excel, c.plan, c.sector, c.subcategoria, c.marca, c.trimestre, c.anio, c.valores_mensuales, c.estado, c.updated_at, u.usuario AS actualizado_por_usuario
		 FROM repositorio_cuota_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = c.actualizado_por
		 WHERE $whereConSub
		 ORDER BY c.anio DESC, c.trimestre DESC, c.cliente_excel, c.sector
		 LIMIT ? OFFSET ?"
	);
	$conSubMarcaResultado = (bool) $stmt;
	// Fallback si `subcategoria`/`marca` no existieran (entorno viejo sin el
	// ALTER corrido) — mismo criterio defensivo que el resto del proyecto.
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

	// mysqli no decodifica JSON solo — sin esto, json_encode() manda un string escapado en vez de objeto.
	foreach ($filas as &$fila) {
		$fila['valores_mensuales'] = $fila['valores_mensuales'] !== null ? json_decode($fila['valores_mensuales'], true) : [];
	}
	unset($fila);

	return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'total_paginas' => $totalPaginas];
}

// Cola de resolución manual — filas donde resolverPosIdCliente() no encontró exactamente un cliente, con candidatos para elegir a mano (igual que liquidacion_pendientes.php).
function listar_repositorio_cuotas_pendientes_match($mysqli) {
	$stmt = $mysqli->prepare(
		"SELECT id, cliente_excel, cedi_excel, plan, sector, trimestre, anio, valores_mensuales
		 FROM repositorio_cuota_cliente
		 WHERE estado = 'pendiente_match'
		 ORDER BY cliente_excel, sector"
	);
	if (!$stmt) return [];
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	foreach ($filas as &$fila) {
		$fila['valores_mensuales'] = $fila['valores_mensuales'] !== null ? json_decode($fila['valores_mensuales'], true) : [];
	}
	unset($fila);

	$stmtCand = $mysqli->prepare(
		"SELECT pos_id, pos_name, cedi, supervisor FROM repositorio_locales_supervisores_cliente
		 WHERE pos_name LIKE CONCAT(?, '%') ORDER BY pos_name LIMIT 10"
	);
	foreach ($filas as &$fila) {
		$fila['candidatos'] = [];
		if ($stmtCand) {
			$stmtCand->bind_param('s', $fila['cliente_excel']);
			$stmtCand->execute();
			$fila['candidatos'] = $stmtCand->get_result()->fetch_all(MYSQLI_ASSOC);
		}
	}
	unset($fila);
	if ($stmtCand) $stmtCand->close();

	return $filas;
}

// ---------- Seguimiento de Equipo (repositorio_acuerdos, TODOS los usuarios) ----------
// Maestro-detalle con un filtro de estado (Todas/Firmadas/Pendientes/Vencidas). Solo cuenta Actas con creado_por real, sin bucket "Sin usuario asignado".
// Los getters devuelven JSON crudo (el frontend arma el DOM) para que filtrar se sienta instantáneo. Única pantalla donde superdesarrollador ve Actas de otros — reforzar el chequeo de rol.

// Años con al menos un Acuerdo real de cualquier usuario — a diferencia de listar_anios_disponibles(), este es a nivel de todo el equipo.
function listar_anios_disponibles_equipo($mysqli) {
	$anios = [];
	$r = $mysqli->query(
		"SELECT DISTINCT anio FROM repositorio_acuerdos
		 WHERE estado NOT IN ('borrador', 'anulado') ORDER BY anio DESC"
	);
	if ($r) $anios = array_map('intval', array_column($r->fetch_all(MYSQLI_ASSOC), 'anio'));
	return $anios;
}

// Stats globales + array por usuario (total/firmadas/pendientes/vencidas + dias_mas_proxima) — el frontend deriva las 4 vistas filtradas sin pedir más al servidor.
function resumen_seguimiento_equipo($mysqli, $trimestre = 0, $anio = 0) {
	barrer_actas_vencidas($mysqli);

	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;
	$anio            = (int) $anio;

	$vacio = ['stats' => ['total' => 0, 'firmadas' => 0, 'pendientes' => 0, 'vencidas' => 0], 'equipo' => []];

	$stmt = $mysqli->prepare(
		"SELECT u.id AS usuario_id, u.usuario AS nombre,
		        COUNT(*) AS total,
		        COUNT(CASE WHEN a.acta_firmada_archivo IS NOT NULL THEN 1 END) AS firmadas,
		        COUNT(CASE WHEN a.acta_firmada_archivo IS NULL AND a.estado IN ('generado', 'enviado') THEN 1 END) AS pendientes,
		        COUNT(CASE WHEN a.estado = 'vencido' THEN 1 END) AS vencidas,
		        MIN(CASE WHEN a.acta_firmada_archivo IS NULL AND a.estado IN ('generado', 'enviado')
		                 THEN DATEDIFF(DATE_ADD(a.fecha_generacion, INTERVAL 20 DAY), CURDATE()) END) AS dias_mas_proxima
		 FROM repositorio_acuerdos a
		 JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
		 WHERE a.estado NOT IN ('borrador', 'anulado')
		   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		   AND (? = 0 OR a.anio = ?)
		 GROUP BY u.id, u.usuario
		 ORDER BY total DESC"
	);
	if (!$stmt) return $vacio;
	$stmt->bind_param('iiiii', $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmt->execute();
	$equipo = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	$stats = ['total' => 0, 'firmadas' => 0, 'pendientes' => 0, 'vencidas' => 0];
	foreach ($equipo as &$u) {
		$u['usuario_id']       = (int) $u['usuario_id'];
		$u['total']            = (int) $u['total'];
		$u['firmadas']         = (int) $u['firmadas'];
		$u['pendientes']       = (int) $u['pendientes'];
		$u['vencidas']         = (int) $u['vencidas'];
		$u['dias_mas_proxima'] = $u['dias_mas_proxima'] !== null ? (int) $u['dias_mas_proxima'] : null;
		// Calculado acá (misma función que Gestión de Usuarios) para que el frontend nunca tenga su propia versión divergente.
		$u['iniciales']        = inicialesUsuario($u['nombre']);
		$stats['total']      += $u['total'];
		$stats['firmadas']   += $u['firmadas'];
		$stats['pendientes'] += $u['pendientes'];
		$stats['vencidas']   += $u['vencidas'];
	}
	unset($u);

	return ['stats' => $stats, 'equipo' => $equipo];
}

// Detalle de un usuario para el filtro de estado activo — $tipo:
// 'todas' | 'firmadas' | 'pendientes' | 'vencidas' (validado con whitelist
// en el getter, acá se usa directo en el SQL). 'pendientes' ordena por
// urgencia (menos días primero); el resto por fecha de generación. Mismo
// GROUP BY a.id que listar_historial_acuerdos() por los ~1,116 pos_id
// duplicados de repositorio_locales_supervisores_cliente.
function listar_actas_equipo_usuario($mysqli, $usuarioId, $trimestre = 0, $anio = 0, $tipo = 'todas') {
	$usuarioId = (int) $usuarioId;
	if (!$usuarioId) return [];

	// Sin esto, un Acta que ya pasó los 20 días pero nadie visitó Historial/
	// este módulo desde entonces seguía apareciendo como 'generado'/
	// 'enviado' con días negativos en vez de 'vencido' — mismo criterio que
	// listar_historial_acuerdos()/resumen_seguimiento_equipo() (esta función
	// se llama sola sin pasar antes por esa, ej. si el detalle se pide
	// directo contra el getter).
	barrer_actas_vencidas($mysqli);

	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;
	$anio            = (int) $anio;

	switch ($tipo) {
		case 'firmadas':   $condicionEstado = "a.acta_firmada_archivo IS NOT NULL"; $orden = 'a.fecha_generacion DESC'; break;
		case 'pendientes': $condicionEstado = "a.estado IN ('generado', 'enviado') AND a.acta_firmada_archivo IS NULL"; $orden = 'dias_restantes ASC'; break;
		case 'vencidas':   $condicionEstado = "a.estado = 'vencido'"; $orden = 'a.fecha_generacion DESC'; break;
		default:           $condicionEstado = "a.estado NOT IN ('borrador', 'anulado')"; $orden = 'a.fecha_generacion DESC';
	}

	// LEFT JOIN (no JOIN normal) a propósito: el conteo por usuario de
	// resumen_seguimiento_equipo() no depende del maestro de clientes, así
	// que si el `pos_id` de un Acta real ya no matchea ese maestro (mismo
	// fenómeno ya documentado para las Actas huérfanas viejas — puede
	// pasarle a una Acta con usuario real también), con JOIN normal esa
	// fila desaparecía del detalle aunque SÍ contara en el total de la
	// lista de Equipo. pos_name cae a NULL -> '—' en seguimiento.js.
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.fecha_generacion, a.estado,
		        (a.acta_firmada_archivo IS NOT NULL) AS tiene_firma,
		        d.pos_name,
		        DATEDIFF(DATE_ADD(a.fecha_generacion, INTERVAL 20 DAY), CURDATE()) AS dias_restantes
		 FROM repositorio_acuerdos a
		 LEFT JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		 WHERE a.creado_por = ?
		   AND $condicionEstado
		   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		   AND (? = 0 OR a.anio = ?)
		 GROUP BY a.id
		 ORDER BY $orden"
	);
	if (!$stmt) return [];
	$stmt->bind_param('iiiiii', $usuarioId, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	foreach ($filas as &$f) {
		$f['tiene_firma']    = (bool) $f['tiene_firma'];
		$f['dias_restantes'] = $f['dias_restantes'] !== null ? (int) $f['dias_restantes'] : null;
	}
	unset($f);
	return $filas;
}

// ---------- Módulo "Cumplimiento de Cuota" (2026-08-30) ----------
// Resolución de dueño: mismo criterio "CEDI del Excel gana sobre el maestro"
// ya confirmado con el usuario para el Repositorio de Cuotas Trimestrales
// (ver usuarioIdDeCuota()/listar_actas_precargadas_pendientes() arriba) — es
// literalmente el mismo Excel/mismo significado de columna CEDI, así que se
// aplica igual acá, como LEFT JOIN + COALESCE (una sola consulta para todas
// las filas, no una por fila).
// $canal (2026-08-31): 'total'/'directo'/'distribuidor', mismo criterio
// EXISTS-based ya usado en listar_historial_acuerdos() — un pos_id puede
// tener 2+ filas en el maestro con canal distinto entre sí, así que un
// filtro directo sobre `canal` no sería mutuamente excluyente (ver el
// comentario completo en esa función, includes/functions.php).
//
// Corregido 2026-08-31 (bug real reportado por el usuario): la primera
// versión chequeaba el canal directo sobre `pos_id` contra el maestro
// (`repositorio_locales_supervisores_cliente`) — pero el DUEÑO real de la
// fila ya se resuelve con el criterio de arriba ("CEDI del Excel gana
// sobre el maestro"), y el maestro puede decir una cosa totalmente
// distinta de con quién trabaja Alicorp en la práctica (mismo fenómeno ya
// documentado para Actas Asignadas: Javier Maldonado es Directo de
// verdad, pero sus clientes en el maestro caen como MAYORISTA). Filtrar el
// canal por el `pos_id` crudo, ignorando a quién se le asignó la fila,
// hacía que un usuario Directo apareciera clasificado como Distribuidor
// (o al revés) según lo que diga el maestro, no según quién es. Ahora
// recibe la expresión SQL del SUPERVISOR ya resuelto (`COALESCE(u_cedi.
// supervisor, u_master.supervisor)`, ver los JOIN en las 2 funciones de
// abajo) y compara canal contra ESE supervisor — el mismo criterio que ya
// usa `canalDeSupervisor()` en el resto de la app, solo que expresado como
// SQL para poder filtrar/agrupar sin una consulta por fila.
function condicionCanalCumplimiento($canal, $columnaSupervisor) {
	if ($canal === 'directo') {
		return "NOT EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.supervisor = $columnaSupervisor AND d2.canal = 'DISTRIBUIDOR')";
	}
	if ($canal === 'distribuidor') {
		return "EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.supervisor = $columnaSupervisor AND d2.canal = 'DISTRIBUIDOR')";
	}
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
		$condiciones[] = "(c.cliente_excel LIKE CONCAT('%', ?, '%') OR COALESCE(u_cedi.usuario, u_master.usuario) LIKE CONCAT('%', ?, '%'))";
		$params[] = $busqueda;
		$params[] = $busqueda;
		$tipos .= 'ss';
	}
	$condicionCanal = condicionCanalCumplimiento($canal, 'COALESCE(u_cedi.supervisor, u_master.supervisor)');
	if ($condicionCanal !== '') $condiciones[] = $condicionCanal;
	$where = implode(' AND ', $condiciones);

	// `canal` acá es el mismo criterio canónico de arriba, resuelto una vez
	// por fila — el frontend lo usa para mostrar el badge de canal SOLO
	// cuando la Vista está en "Total" (con un canal puntual ya filtrado, el
	// badge sería redundante en cada fila). Se deriva del SUPERVISOR ya
	// resuelto (CEDI del Excel gana sobre el maestro), no del `pos_id`
	// crudo — ver el comentario de `condicionCanalCumplimiento()`.
	$stmt = $mysqli->prepare(
		"SELECT c.id, c.pos_id, c.cliente_excel, c.cedi_excel, c.plan_excel, c.sector,
		        c.cuota_total, c.venta_total, c.cumplimiento_pct,
		        c.gana_categoria, c.gana_categoria_anterior, c.gana_total,
		        c.rebate_real_vol, c.updated_at,
		        COALESCE(u_cedi.id, u_master.id) AS usuario_id,
		        COALESCE(u_cedi.usuario, u_master.usuario) AS usuario_nombre,
		        (CASE WHEN EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d3 WHERE d3.supervisor = COALESCE(u_cedi.supervisor, u_master.supervisor) AND d3.canal = 'DISTRIBUIDOR') THEN 'distribuidor' ELSE 'directo' END) AS canal
		 FROM repositorio_cumplimiento_cuota c
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN repositorio_locales_supervisores_cliente mst ON mst.pos_id = c.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u_master ON u_master.supervisor = mst.supervisor AND u_master.status = 'activo'
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
	// Mismo criterio que listar_cumplimiento_cuota() (2026-08-31, bug real
	// corregido) — el canal se resuelve por el SUPERVISOR del usuario
	// dueño real de la fila (CEDI del Excel gana sobre el maestro), no por
	// el `pos_id` crudo — necesita los mismos 2 LEFT JOIN de esa función
	// para poder resolver ese dueño acá también.
	$condicionCanal = condicionCanalCumplimiento($canal, 'COALESCE(u_cedi.supervisor, u_master.supervisor)');
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
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN repositorio_locales_supervisores_cliente mst ON mst.pos_id = c.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u_master ON u_master.supervisor = mst.supervisor AND u_master.status = 'activo'
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

function listar_anios_disponibles_cumplimiento($mysqli) {
	$res = $mysqli->query("SELECT DISTINCT anio FROM repositorio_cumplimiento_cuota WHERE eliminado_en IS NULL ORDER BY anio DESC");
	if (!$res) return [];
	return array_map('intval', array_column($res->fetch_all(MYSQLI_ASSOC), 'anio'));
}
?>
