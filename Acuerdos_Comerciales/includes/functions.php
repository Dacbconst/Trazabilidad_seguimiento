<?php
// Sesión y roles en un solo archivo a propósito, proyecto independiente de Xplora.

function iniciar_sesion() {
	if (session_status() === PHP_SESSION_NONE) {
		// 8 horas: el gc_maxlifetime del hosting es más corto que una jornada de uso normal.
		$vidaSegundos = 8 * 60 * 60;
		ini_set('session.gc_maxlifetime', $vidaSegundos);
		session_set_cookie_params([
			'lifetime'  => $vidaSegundos,
			'httponly' => true,
			'secure'   => SECURE,
			'samesite' => 'Lax',
		]);
		session_start();
	}
}

// Sesión única por usuario: un login nuevo invalida cualquier sesión previa de esa cuenta en otro dispositivo.
function registrarSesionUnica($mysqli, $userId) {
	$token = bin2hex(random_bytes(32));
	$_SESSION['sesion_token'] = $token;
	$stmt = $mysqli->prepare('UPDATE repositorio_usuarios_acuerdos SET sesion_token = ?, sesion_ultima_actividad = NOW() WHERE id = ?');
	if (!$stmt) $stmt = $mysqli->prepare('UPDATE repositorio_usuarios_acuerdos SET sesion_token = ? WHERE id = ?');
	if ($stmt) {
		$stmt->bind_param('si', $token, $userId);
		$stmt->execute();
		$stmt->close();
	}
}

// Sesión activa de verdad = token guardado Y actividad reciente (ping de sesion-watch.js cada 15s).
function sesionAnteriorSigueActiva($row) {
	if (empty($row['sesion_token'])) return false;
	if (!array_key_exists('sesion_ultima_actividad', $row)) return true;
	if ($row['sesion_ultima_actividad'] === null) return false;
	return (time() - strtotime($row['sesion_ultima_actividad'])) < 180;
}

// Login sin password_hash (decisión del cliente). Devuelve true/false/'bloqueado'/'sesion_activa' (5 intentos bloquean 15 min).
function login($usuario, $password, $mysqli, $forzar = false) {
	$stmt = $mysqli->prepare(
		"SELECT id, usuario, rol, supervisor, contrasena, intentos_fallidos, bloqueado_hasta, sesion_token, sesion_ultima_actividad
		 FROM repositorio_usuarios_acuerdos WHERE usuario = ? AND status = 'activo' LIMIT 1"
	);
	if ($stmt) {
		$stmt->bind_param('s', $usuario);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$stmt->close();

		if (!$row) {
			return cuentaInactivaConClaveCorrecta($mysqli, $usuario, $password) ? 'inactivo' : false;
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

		// Sesión activa en otro dispositivo: pide confirmación antes de cerrarla, salvo $forzar.
		if (!$forzar && sesionAnteriorSigueActiva($row)) {
			return 'sesion_activa';
		}

		session_regenerate_id();
		$_SESSION['user_id']    = $row['id'];
		$_SESSION['username']   = $row['usuario'];
		$_SESSION['rol']        = $row['rol'];
		$_SESSION['supervisor'] = $row['supervisor'] ?? null;
		registrarSesionUnica($mysqli, $row['id']);
		return true;
	}

	// ---------- Fallback: columnas de fuerza bruta todavía no existen ---------- login de siempre sin bloqueo.
	$stmt = $mysqli->prepare(
		"SELECT id, usuario, rol, supervisor, sesion_token, sesion_ultima_actividad FROM repositorio_usuarios_acuerdos
		 WHERE usuario = ? AND contrasena = ? AND status = 'activo' LIMIT 1"
	);
	if (!$stmt) $stmt = $mysqli->prepare(
		"SELECT id, usuario, rol, supervisor, sesion_token FROM repositorio_usuarios_acuerdos
		 WHERE usuario = ? AND contrasena = ? AND status = 'activo' LIMIT 1"
	);
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT id, usuario, rol, sesion_token FROM repositorio_usuarios_acuerdos
			 WHERE usuario = ? AND contrasena = ? AND status = 'activo' LIMIT 1"
		);
	}
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
		return cuentaInactivaConClaveCorrecta($mysqli, $usuario, $password) ? 'inactivo' : false;
	}

	if (!$forzar && sesionAnteriorSigueActiva($row)) {
		return 'sesion_activa';
	}

	session_regenerate_id();
	$_SESSION['user_id']    = $row['id'];
	$_SESSION['username']   = $row['usuario'];
	$_SESSION['rol']        = $row['rol'];
	$_SESSION['supervisor'] = $row['supervisor'] ?? null;
	registrarSesionUnica($mysqli, $row['id']);
	return true;
}

// Distingue "usuario/clave incorrectos" de "cuenta inactiva" — requiere clave correcta para no revelar status a quien no la tiene.
function cuentaInactivaConClaveCorrecta($mysqli, $usuario, $password) {
	$stmt = $mysqli->prepare(
		"SELECT 1 FROM repositorio_usuarios_acuerdos WHERE usuario = ? AND contrasena = ? AND status <> 'activo' LIMIT 1"
	);
	if (!$stmt) return false;
	$stmt->bind_param('ss', $usuario, $password);
	$stmt->execute();
	$existe = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return (bool) $existe;
}

function login_check() {
	if (!isset($_SESSION['user_id'], $_SESSION['rol'])) return false;
	// Sesión única: si otro login pisó el token, esta sesión queda inválida. static evita repetir la consulta.
	static $valida = null;
	if ($valida !== null) return $valida;
	global $mysqli;
	if (isset($_SESSION['sesion_token']) && isset($mysqli)) {
		$stmt = $mysqli->prepare('SELECT sesion_token FROM repositorio_usuarios_acuerdos WHERE id = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('i', $_SESSION['user_id']);
			$stmt->execute();
			$fila = $stmt->get_result()->fetch_assoc();
			$stmt->close();
			if ($fila && $fila['sesion_token'] !== null && $fila['sesion_token'] !== $_SESSION['sesion_token']) {
				session_unset();
				session_destroy();
				$valida = false;
				return false;
			}
		}
	}
	$valida = true;
	return true;
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

// ---------- Canal (Directo / Distribuidor) vía supervisor ---------- nunca se guarda, se deriva en vivo del maestro de Alicorp.

function listar_supervisores_disponibles($mysqli) {
	$supervisores = [];
	// Excluye supervisores de prueba del maestro ("PRUEBA X") por prefijo, no lista fija.
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

// Nunca debe tirar fatal error: devuelve null (-> 'directo' por defecto) en vez de romper el login.
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

// Canal real de la SESIÓN actual: decide qué cartera ve el usuario en Registrar. Sin supervisor (ej. "Admin") elige el canal a mano vía admin_set_canal.php, 'directo' por default.
function canalEfectivoUsuario($mysqli) {
	$supervisor = $_SESSION['supervisor'] ?? null;
	if ($supervisor) return canalDeSupervisor($mysqli, $supervisor) ?: 'directo';
	if (($_SESSION['rol'] ?? '') === 'superdesarrollador') return ($_SESSION['canal_admin'] ?? 'directo') === 'distribuidor' ? 'distribuidor' : 'directo';
	return 'directo';
}

// true solo para superdesarrollador sin supervisor real — el resto sigue viendo su cartera de siempre.
function esModoAdminSinCartera() {
	return ($_SESSION['rol'] ?? '') === 'superdesarrollador' && empty($_SESSION['supervisor']);
}

// ---------- Repositorio de Cuotas trimestrales ---------- match por nombre, desempate por canal: supervisor en Directo, empresa (tipo_distribuidor) en Distribuidor.
function resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel, $canal = 'directo', $distribuidorExcel = null) {
	$condicionCanal = $canal === 'distribuidor' ? "canal = 'DISTRIBUIDOR'" : "canal <> 'DISTRIBUIDOR'";
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT pos_id FROM repositorio_locales_supervisores_cliente
		 WHERE pos_name LIKE CONCAT(?, '%') AND $condicionCanal"
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $clienteExcel);
	$stmt->execute();
	$posIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'pos_id');
	$stmt->close();

	if (count($posIds) === 1) return $posIds[0];
	if (count($posIds) === 0) return null;

	if ($canal === 'distribuidor') {
		if (!$distribuidorExcel) return null;
		$stmt = $mysqli->prepare(
			"SELECT DISTINCT pos_id FROM repositorio_locales_supervisores_cliente
			 WHERE pos_name LIKE CONCAT(?, '%') AND canal = 'DISTRIBUIDOR' AND tipo_distribuidor = ?"
		);
		if (!$stmt) return null;
		$stmt->bind_param('ss', $clienteExcel, $distribuidorExcel);
	} else {
		if (!$cediExcel) return null;
		$stmt = $mysqli->prepare(
			"SELECT DISTINCT pos_id FROM repositorio_locales_supervisores_cliente
			 WHERE pos_name LIKE CONCAT(?, '%') AND canal <> 'DISTRIBUIDOR' AND supervisor = ?"
		);
		if (!$stmt) return null;
		$stmt->bind_param('ss', $clienteExcel, $cediExcel);
	}
	$stmt->execute();
	$desempatados = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'pos_id');
	$stmt->close();

	return count($desempatados) === 1 ? $desempatados[0] : null;
}

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

// Parche visual: BARRA+EL MACHO es "ROPA" en repositorio_productos pero "DETERGENTE" en repositorio_rebate_producto (dato inconsistente entre tablas, sin tocar el esquema).
function aplicarParcheCategoriaVisual($sector, $marca, $categoria) {
	$parches = [
		['BARRA', 'EL MACHO', 'DETERGENTE'],
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

// Supervisor de campo sin cuenta propia que reporta a otro supervisor que sí tiene cuenta.
function supervisorRealDeJerarquia($mysqli, $supervisorCampo) {
	if (!$supervisorCampo) return null;
	$stmt = $mysqli->prepare(
		'SELECT supervisor_real FROM repositorio_jerarquia_supervisores WHERE UPPER(TRIM(supervisor_campo)) = UPPER(TRIM(?)) LIMIT 1'
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $supervisorCampo);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $fila ? $fila['supervisor_real'] : null;
}

// Dado un pos_id resuelto, encuentra el usuario responsable vía su supervisor real. Null si no hay cuenta activa.
function usuarioIdDePosId($mysqli, $posId) {
	$stmt = $mysqli->prepare(
		"SELECT u.id FROM repositorio_locales_supervisores_cliente c
		 JOIN repositorio_usuarios_acuerdos u ON u.supervisor = c.supervisor AND u.status = 'activo'
		 WHERE c.pos_id = ? LIMIT 1"
	);
	if ($stmt) {
		$stmt->bind_param('s', $posId);
		$stmt->execute();
		$fila = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if ($fila) return (int) $fila['id'];
	}

	$stmtSup = $mysqli->prepare('SELECT supervisor FROM repositorio_locales_supervisores_cliente WHERE pos_id = ? LIMIT 1');
	if (!$stmtSup) return null;
	$stmtSup->bind_param('s', $posId);
	$stmtSup->execute();
	$filaSup = $stmtSup->get_result()->fetch_assoc();
	$stmtSup->close();
	$real = $filaSup ? supervisorRealDeJerarquia($mysqli, $filaSup['supervisor']) : null;
	if (!$real) return null;

	$stmtReal = $mysqli->prepare("SELECT id FROM repositorio_usuarios_acuerdos WHERE supervisor = ? AND status = 'activo' LIMIT 1");
	if (!$stmtReal) return null;
	$stmtReal->bind_param('s', $real);
	$stmtReal->execute();
	$filaReal = $stmtReal->get_result()->fetch_assoc();
	$stmtReal->close();
	return $filaReal ? (int) $filaReal['id'] : null;
}

// Dueño real de una fila de Cuotas: el CEDI del Excel manda sobre el maestro de Alicorp, que actúa como respaldo.
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

// Mismo criterio de arriba (CEDI del Excel gana, maestro como respaldo), pero ANTES de
// guardar — usada por la previsualización de Cuotas (2026-09-17) para mostrar "Se asigna
// a" antes de confirmar. Recibe $cediExcel directo de la fila recién parseada (todavía no
// existe en repositorio_cuota_cliente, por eso no puede reusar usuarioIdDeCuota() tal
// cual) — es la MISMA fila que, una vez guardada, usuarioIdDeCuota() volvería a leer con
// este mismo cedi_excel, así que el resultado coincide con lo que pasaría de verdad.
// Devuelve ['nombre'=>string|null, 'tiene_cuenta'=>bool] — 2026-09-17, ampliado a pedido
// explícito del usuario: un cliente cuyo `pos_id` SÍ se identificó pero cuyo supervisor
// real (del maestro de Alicorp) todavía no tiene cuenta de usuario NO es lo mismo que un
// cliente que ni siquiera se pudo identificar — antes ambos casos caían en el mismo balde
// "sin identificar", perdiendo la distinción. Mismo criterio que ya usaba el modal
// "Resumen" viejo (`resumen_cuotas()`, sección "Con cuenta"/"Sin cuenta todavía").
function resolverNombreAsignadoCuota($mysqli, $posId, $cediExcel) {
	$cedi = trim((string) $cediExcel);
	if ($cedi !== '') {
		$stmt = $mysqli->prepare(
			"SELECT usuario FROM repositorio_usuarios_acuerdos
			 WHERE status = 'activo'
			   AND (UPPER(TRIM(usuario)) = UPPER(TRIM(?)) OR UPPER(TRIM(supervisor)) = UPPER(TRIM(?)))
			 LIMIT 1"
		);
		if ($stmt) {
			$stmt->bind_param('ss', $cedi, $cedi);
			$stmt->execute();
			$fila = $stmt->get_result()->fetch_assoc();
			$stmt->close();
			if ($fila) return ['nombre' => $fila['usuario'], 'tiene_cuenta' => true];
		}
	}
	// Respaldo del maestro: trae el supervisor real de ESE pos_id, con o sin cuenta activa
	// (LEFT JOIN, no JOIN — antes un JOIN normal descartaba en silencio el caso "supervisor
	// real pero sin cuenta todavía", indistinguible de "no se encontró nada").
	$stmt = $mysqli->prepare(
		"SELECT c.supervisor, u.usuario, (u.id IS NOT NULL) AS tiene_cuenta
		 FROM repositorio_locales_supervisores_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.supervisor = c.supervisor AND u.status = 'activo'
		 WHERE c.pos_id = ? LIMIT 1"
	);
	if (!$stmt) return ['nombre' => null, 'tiene_cuenta' => false];
	$stmt->bind_param('s', $posId);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$fila) return ['nombre' => null, 'tiene_cuenta' => false];
	if ($fila['tiene_cuenta']) return ['nombre' => $fila['usuario'], 'tiene_cuenta' => true];

	// Jerarquía manual: el supervisor de campo no tiene cuenta propia, pero reporta a alguien que sí.
	$real = supervisorRealDeJerarquia($mysqli, $fila['supervisor']);
	if ($real) {
		$stmtReal = $mysqli->prepare("SELECT usuario FROM repositorio_usuarios_acuerdos WHERE supervisor = ? AND status = 'activo' LIMIT 1");
		if ($stmtReal) {
			$stmtReal->bind_param('s', $real);
			$stmtReal->execute();
			$filaReal = $stmtReal->get_result()->fetch_assoc();
			$stmtReal->close();
			if ($filaReal) return ['nombre' => $filaReal['usuario'], 'tiene_cuenta' => true];
		}
	}

	// Cliente identificado, supervisor real conocido, pero sin cuenta creada todavía.
	return ['nombre' => $fila['supervisor'], 'tiene_cuenta' => false];
}

// ---------- Actas Precargadas (Repositorio de Cuotas) ---------- resolución en vivo. Agrupa por (pos_id, trimestre, anio): varias filas son UNA sola Acta.
function listar_actas_precargadas_pendientes($mysqli, $usuarioId) {
	if (!$usuarioId) return [];
	// Subquery en vez de JOIN directo: pos_id no es único en el maestro, duplicaría la Acta si hay 2+ filas.
	$stmt = $mysqli->prepare(
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
		// "OTRAS CATEGORIAS" se ignora acá también, para coincidir con lo que ve el asesor.
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

// Arma el detalle de una Acta precargada para poblar Registrar. Segmento/Categoría/Marca vienen del Excel o, si falta, del historial del cliente.
function obtener_precarga_detalle($mysqli, $posId, $trimestre, $anio) {
	// Sin rebate_pct: se busca abajo vía buscarRebateProducto().
	$stmt = $mysqli->prepare(
		"SELECT id, sector, subcategoria, marca, valores_mensuales FROM repositorio_cuota_cliente
		 WHERE pos_id = ? AND trimestre = ? AND anio = ? AND estado = 'pendiente_uso'
		 ORDER BY sector"
	);
	// Fallback si subcategoria/marca todavía no existen en la base: nunca tumbar la Acta por columnas nuevas.
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

	// Ciudad/Canal para buscar Rebate, mismo criterio que buscarYAplicarRebate() en registrar.js.
	$esDistribuidorRebate = ($cliente['canal'] ?? null) === 'DISTRIBUIDOR';
	$ciudadRebate = $esDistribuidorRebate ? 'TODAS' : ($cliente['cedi'] ?: '');
	$canalRebate  = $esDistribuidorRebate ? 'DISTRIBUIDOR' : 'DIRECTA';

	$lineasMeta = [];
	foreach ($filasCuota as $fc) {
		// "OTRAS CATEGORIAS" se ignora al pregenerar, JW dejó de usar ese cajón genérico.
		if (strtoupper(trim($fc['sector'])) === 'OTRAS CATEGORIAS') continue;

		$segmento = null; $categoria = null; $marca = null;

		// 1ra prioridad: SUBCATEGORIA/MARCA reales del Excel, más confiable que el historial.
		if (!empty($fc['subcategoria']) && !empty($fc['marca'])) {
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

// Todas las Actas precargadas pendientes, sin acotar a un usuarioId, para el panorama del superdesarrollador.
function listar_actas_precargadas_todas($mysqli) {
	$stmt = $mysqli->prepare(
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
		if (strtoupper(trim($f['sector'])) === 'OTRAS CATEGORIAS') continue;
		$valores = $f['valores_mensuales'] !== null ? json_decode($f['valores_mensuales'], true) : [];
		if (!is_array($valores) || array_sum($valores) <= 0) continue;
		$clave = $f['pos_id'].'|'.$f['trimestre'].'|'.$f['anio'];
		if (!isset($grupos[$clave])) {
			$grupos[$clave] = [
				'pos_id' => $f['pos_id'], 'cliente_excel' => $f['cliente_excel'], 'cedi_excel' => $f['cedi_excel'],
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
	$r = $mysqli->query("SELECT COUNT(DISTINCT CONCAT(c.pos_id, '|', c.trimestre, '|', c.anio)) AS n FROM repositorio_cuota_cliente c WHERE c.estado = 'usada'");
	if ($r) $usadas = (int) $r->fetch_assoc()['n'];

	$pendientesMatch = 0;
	$r = $mysqli->query("SELECT COUNT(DISTINCT c.cliente_excel, c.trimestre, c.anio) AS n FROM repositorio_cuota_cliente c WHERE c.estado = 'pendiente_match'");
	if ($r) $pendientesMatch = (int) $r->fetch_assoc()['n'];

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

	$porUsuarioMapa = [];
	$nombrePorClaveGrupo = [];
	foreach ($grupos as $g) {
		$cedi = strtoupper(trim((string) $g['cedi_excel']));
		$nombre = null; $tieneCuenta = false;
		if ($cedi !== '' && isset($porCedi[$cedi])) {
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
		'pendientes_match'  => $pendientesMatch,
		'por_usuario'       => $porUsuario,
		'chocan'            => $chocan,
	];
}

// ---------- Gestión de Usuarios ---------- centralizado acá: carga inicial y refresco AJAX comparten consulta y render de fila.

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
	// Mismo fallback que login(): si `supervisor` no existe todavía, no reventar con un fatal error.
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

	// Canal por fila en PHP, no SQL: pagina de a 8, no vale una subquery.
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
	// Canal ya resuelto desde listar_usuarios_acuerdos(); array_key_exists porque puede ser null de verdad.
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

// ---------- Historial de Acuerdos ---------- mismo patrón que arriba: carga inicial y refresco AJAX comparten consulta y render.

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

// Un Acta con 20+ días desde fecha_generacion pasa a 'vencido'. Sin cron: corre cada vez que se listan Actas o se calculan alertas.
function barrer_actas_vencidas($mysqli) {
	$mysqli->query(
		"UPDATE repositorio_acuerdos
		 SET estado = 'vencido'
		 WHERE estado IN ('generado', 'enviado')
		   AND fecha_generacion IS NOT NULL
		   AND fecha_generacion < DATE_SUB(CURDATE(), INTERVAL 20 DAY)"
	);
}

// Actas propias por vencer, alimenta "Mis Actas" de la campanita, sin firmar con $diasUmbral días o menos.
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

// $usuarioId filtra por creado_por real. $trimestre/$anio: 0="Todos". $filtroFirma: 'todos'|'firmadas'|'pendientes'.
function listar_historial_acuerdos($mysqli, $busqueda = '', $trimestre = 0, $anio = 0, $filtroFirma = 'todos', $pagina = 1, $usuarioId = null, $porPagina = 10, $rol = null, $canal = 'total') {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';
	$anio   = (int) $anio;

	$bounds           = trimestreABounds($trimestre);
	$trimestreActivo  = $bounds ? 1 : 0;
	$mesInicioFiltro  = $bounds ? $bounds[0] : -1;
	$mesFinFiltro     = $bounds ? $bounds[1] : -1;

	// Sin user_id no hay forma de saber qué acuerdos son suyos: vacío, nunca todo el mundo.
	if (!$usuarioId) {
		return ['acuerdos' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	}

	// Corre el barrido de vencimiento para que Historial nunca muestre un Acta ya vencida.
	barrer_actas_vencidas($mysqli);

	// "Ver todo": superdesarrollador ve Actas de todos. `? = 1 OR a.creado_por = ?` fija el conteo de parámetros sin bind_param variable.
	$verTodos = ($rol === 'superdesarrollador') ? 1 : 0;

	// Filtro de Canal vía EXISTS, no comparación directa: un pos_id puede tener 2+ filas de canal distinto en el maestro.
	$condicionCanal = '';
	if ($canal === 'directo') {
		$condicionCanal = " AND NOT EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.pos_id = a.pos_id AND d2.canal = 'DISTRIBUIDOR')";
	} elseif ($canal === 'distribuidor') {
		$condicionCanal = " AND EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.pos_id = a.pos_id AND d2.canal = 'DISTRIBUIDOR')";
	}

	// JOIN solo para pos_name/cedi/canal; GROUP BY a.id evita duplicar el Acuerdo por pos_id repetidos en el maestro.
	$condicionFirma = '';
	if ($filtroFirma === 'firmadas') $condicionFirma = ' AND a.acta_firmada_azure_path IS NOT NULL';
	elseif ($filtroFirma === 'pendientes') $condicionFirma = ' AND a.acta_firmada_azure_path IS NULL';

	// LEFT JOIN para "Generado por": un Acta huérfana (creado_por NULL) no debe desaparecer de Historial.
	$sqlBase = "FROM repositorio_acuerdos a
		JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		LEFT JOIN repositorio_usuarios_acuerdos ug ON ug.id = a.creado_por
		WHERE a.estado NOT IN ('borrador', 'anulado', 'vencido')
		  AND (? = 1 OR a.creado_por = ?)
		  AND d.pos_name LIKE ?
		  AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		  AND (? = 0 OR a.anio = ?)
		  $condicionFirma
		  $condicionCanal";

	// Se renderiza siempre en cada login: nunca debe tirar fatal error si el JOIN externo falla.
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

	// Canal canónico: `d.canal` crudo es ambiguo con pos_id duplicados, usa el mismo EXISTS que decide la pastilla.
	$canalCanonico = "(CASE WHEN EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.pos_id = a.pos_id AND d2.canal = 'DISTRIBUIDOR') THEN 'DISTRIBUIDOR' ELSE 'OTRO' END) AS canal";
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.mes_inicio, a.mes_fin, a.fecha_generacion, a.estado, a.creado_por,
		        (a.acta_firmada_azure_path IS NOT NULL) AS tiene_firma, a.acta_firmada_mime,
		        d.pos_name, d.cedi, $canalCanonico, ug.usuario AS generado_por
		 $sqlBase
		 GROUP BY a.id
		 ORDER BY a.fecha_generacion DESC, a.id DESC
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT a.id, a.documento_no, a.mes_inicio, a.mes_fin, a.fecha_generacion, a.estado, a.creado_por,
			        0 AS tiene_firma, NULL AS acta_firmada_mime,
			        d.pos_name, d.cedi, $canalCanonico, ug.usuario AS generado_por
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

// Stat tiles de Historial: respetan búsqueda/trimestre/año pero NO el filtro de firma.
function obtener_stats_historial($mysqli, $busqueda, $trimestre, $anio, $usuarioId, $rol = null, $canal = 'total') {
	$vacio = ['total' => 0, 'firmadas' => 0, 'pendientes' => 0, 'pendiente_mas_antigua' => null];
	if (!$usuarioId) return $vacio;

	$like = '%'.$busqueda.'%';
	$anio = (int) $anio;
	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;
	// Mismo criterio "ver todo" y filtro de Canal que listar_historial_acuerdos().
	$verTodos = ($rol === 'superdesarrollador') ? 1 : 0;
	$condicionCanal = '';
	if ($canal === 'directo') {
		$condicionCanal = " AND NOT EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.pos_id = a.pos_id AND d2.canal = 'DISTRIBUIDOR')";
	} elseif ($canal === 'distribuidor') {
		$condicionCanal = " AND EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.pos_id = a.pos_id AND d2.canal = 'DISTRIBUIDOR')";
	}

	$stmt = $mysqli->prepare(
		"SELECT COUNT(DISTINCT a.id) AS total,
		        COUNT(DISTINCT CASE WHEN a.acta_firmada_azure_path IS NOT NULL THEN a.id END) AS firmadas,
		        MIN(CASE WHEN a.acta_firmada_azure_path IS NULL THEN a.fecha_generacion END) AS pendiente_mas_antigua
		 FROM repositorio_acuerdos a
		 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		 WHERE a.estado NOT IN ('borrador', 'anulado', 'vencido')
		   AND (? = 1 OR a.creado_por = ?)
		   AND d.pos_name LIKE ?
		   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		   AND (? = 0 OR a.anio = ?)
		   $condicionCanal"
	);
	if (!$stmt) return $vacio; // acta_firmada_azure_path todavía no existe, ver CLAUDE.md (migración a Azure Blob Storage).
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

// Años con al menos un Acuerdo real de este usuario, para poblar el filtro "Año" sin inventar un rango fijo.
function listar_anios_disponibles($mysqli, $usuarioId, $rol = null) {
	if (!$usuarioId) return [];
	// "Ver todo": superdesarrollador ve años de todos los Acuerdos, sin filtrar por canal a propósito.
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

// $mostrarCanal agrega una celda de Canal entre Localidad y Periodo, solo para quien ve los 2 canales mezclados.
function renderFilaHistorial(array $a, $mostrarCanal = false) {
	$fecha = $a['fecha_generacion'] ? date('d/m/Y', strtotime($a['fecha_generacion'])) : '—';
	$celdaCanal = '';
	$celdaGenerador = '';
	if ($mostrarCanal) {
		$esDistribuidor = ($a['canal'] ?? '') === 'DISTRIBUIDOR';
		$celdaCanal = '<td><span class="ac-badge ac-badge-canal-'.($esDistribuidor ? 'distribuidor' : 'directo').'">'.($esDistribuidor ? 'Distribuidor' : 'Directo').'</span></td>';
		// "Generado por" (solo superdesarrollador): antes había que abrir cada Acta para saber a quién se le asignó.
		$celdaGenerador = '<td>'.htmlspecialchars($a['generado_por'] ?: '—').'</td>';
	}

	// Un solo botón por fila que cambia de ícono/acción según el estado: subir si falta la firma, ver el archivo si ya está.
	$tieneFirma = !empty($a['tiene_firma']);
	// Badge "Pendiente" pasa a cuenta regresiva con 5 días o menos. $filaUrgencia marca el <tr> para la franja lateral.
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
	// Subir/Ver Firma y Eliminar quedan bloqueados para una Acta ajena; Ver Detalles y Descargar PDF quedan libres.
	$esPropio = (int) ($a['creado_por'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0);
	$disabledAjeno = $esPropio ? '' : ' disabled';
	$tituloAjeno = ' title="Esta Acta la generó otro asesor — solo esa cuenta puede subir la firma."';

	// .ac-row-actions-primary: en mobile es el botón más importante, necesita texto visible y buen tamaño táctil.
	$firmaBtn = $tieneFirma
		? '<button type="button" class="ac-btn-outline ac-btn-inline ac-btn-outline-success ac-row-actions-primary hist-btn-firma" data-id="'.(int) $a['id'].'" data-doc="'.htmlspecialchars($a['documento_no']).'" data-tiene-firma="1" data-mime="'.htmlspecialchars($a['acta_firmada_mime'] ?? '').'"'.$disabledAjeno.($esPropio ? ' title="Ver Acta Firmada"' : $tituloAjeno).'><span class="material-symbols-outlined">task_alt</span><span class="ac-row-actions-primary-label">Ver Firma</span></button>'
		: '<button type="button" class="ac-btn-outline ac-btn-inline ac-row-actions-primary hist-btn-firma" data-id="'.(int) $a['id'].'" data-doc="'.htmlspecialchars($a['documento_no']).'" data-tiene-firma="0"'.$disabledAjeno.($esPropio ? ' title="Subir Acta Firmada"' : $tituloAjeno).'><span class="material-symbols-outlined">upload_file</span><span class="ac-row-actions-primary-label">Subir Firma</span></button>';

	return '
	<tr data-id="'.(int) $a['id'].'" class="hist-fila'.$filaUrgencia.($mostrarCanal ? ' hist-fila-con-canal' : '').'">
		<td><button type="button" class="ac-link-id hist-btn-ver" data-id="'.(int) $a['id'].'">#'.htmlspecialchars($a['documento_no']).'</button></td>
		<td class="ac-hist-distribuidor">'.htmlspecialchars($a['pos_name']).'</td>
		<td>'.htmlspecialchars($a['cedi'] ?: '—').'</td>
		'.$celdaCanal.$celdaGenerador.'
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

// Cabecera + 4 tablas de líneas de un Acuerdo puntual, para el detalle/Acta imprimible.
function obtener_acuerdo_detalle($mysqli, $acuerdoId) {
	// LIMIT 1 alcanza pese a pos_id duplicados en el maestro. d.canal decide el formato; d.tipo_distribuidor es la Empresa Distribuidora.
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

// Borradores propios, para "Mis Borradores", mismo scoping por creador que listar_historial_acuerdos().
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

// ---------- Módulo Repositorios ---------- catálogos self-service (Rebate, Participación) que autocompletan y bloquean esos campos.
function listar_repositorio_rebate($mysqli, $busqueda = '', $pagina = 1, $porPagina = 10, $canal = 'total') {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';
	// Filtro de Canal: viene del propio Excel (columna CANAL), no hay tabla separada por canal.
	$condicionCanal = '';
	if ($canal === 'directo') $condicionCanal = " AND UPPER(canal) <> 'DISTRIBUIDOR'";
	elseif ($canal === 'distribuidor') $condicionCanal = " AND UPPER(canal) = 'DISTRIBUIDOR'";

	// eliminado_en IS NULL: el listado normal nunca muestra filas borradas, viven en "Eliminados".
	$stmtTotal = $mysqli->prepare(
		"SELECT COUNT(*) AS total FROM repositorio_rebate_producto
		 WHERE eliminado_en IS NULL AND (ciudad LIKE ? OR canal LIKE ? OR sector LIKE ? OR categoria LIKE ? OR marca LIKE ?) $condicionCanal"
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
		 WHERE r.eliminado_en IS NULL AND (r.ciudad LIKE ? OR r.canal LIKE ? OR r.sector LIKE ? OR r.categoria LIKE ? OR r.marca LIKE ?) $condicionCanal
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

// Jerarquía de Supervisores: mapea el nombre "de campo" del maestro (sin cuenta propia) al supervisor real que recibe sus Actas.
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
	$stmt = $mysqli->prepare(
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

// Cola de resolución manual: filas sin match único, con candidatos para elegir a mano (igual que liquidacion_pendientes.php).
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

// ---------- Seguimiento de Equipo ---------- maestro-detalle con filtro de estado. Reforzar el chequeo de rol acá.

// Años con al menos un Acuerdo real de cualquier usuario, a nivel de todo el equipo.
function listar_anios_disponibles_equipo($mysqli) {
	$anios = [];
	$r = $mysqli->query(
		"SELECT DISTINCT anio FROM repositorio_acuerdos
		 WHERE estado NOT IN ('borrador', 'anulado') ORDER BY anio DESC"
	);
	if ($r) $anios = array_map('intval', array_column($r->fetch_all(MYSQLI_ASSOC), 'anio'));
	return $anios;
}

// Stats globales + array por usuario: el frontend deriva las 4 vistas filtradas sin pedir más al servidor.
function resumen_seguimiento_equipo($mysqli, $trimestre = 0, $anio = 0) {
	barrer_actas_vencidas($mysqli);

	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;
	$anio            = (int) $anio;

	$vacio = ['stats' => ['total' => 0, 'firmadas' => 0, 'pendientes' => 0, 'vencidas' => 0], 'equipo' => []];

		// Pendientes: cualquier Acta sin firma real y sin vencer. Depender de si HAY un archivo real, no del texto del estado.
	$stmt = $mysqli->prepare(
		"SELECT u.id AS usuario_id, u.usuario AS nombre,
		        COUNT(*) AS total,
		        COUNT(CASE WHEN a.acta_firmada_azure_path IS NOT NULL THEN 1 END) AS firmadas,
		        COUNT(CASE WHEN a.acta_firmada_azure_path IS NULL AND a.estado <> 'vencido' THEN 1 END) AS pendientes,
		        COUNT(CASE WHEN a.estado = 'vencido' THEN 1 END) AS vencidas,
		        MIN(CASE WHEN a.acta_firmada_azure_path IS NULL AND a.estado <> 'vencido'
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
		// Calculado acá para que el frontend nunca tenga su propia versión divergente.
		$u['iniciales']        = inicialesUsuario($u['nombre']);
		$stats['total']      += $u['total'];
		$stats['firmadas']   += $u['firmadas'];
		$stats['pendientes'] += $u['pendientes'];
		$stats['vencidas']   += $u['vencidas'];
	}
	unset($u);

	return ['stats' => $stats, 'equipo' => $equipo];
}

// $tipo validado con whitelist en el getter. 'pendientes' ordena por urgencia, el resto por fecha de generación.
function listar_actas_equipo_usuario($mysqli, $usuarioId, $trimestre = 0, $anio = 0, $tipo = 'todas') {
	$usuarioId = (int) $usuarioId;
	if (!$usuarioId) return [];

	// Sin esto, un Acta vencida hace rato pero sin visita a Historial seguía como 'generado' con días negativos.
	barrer_actas_vencidas($mysqli);

	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;
	$anio            = (int) $anio;

	switch ($tipo) {
		case 'firmadas':   $condicionEstado = "a.acta_firmada_azure_path IS NOT NULL"; $orden = 'a.fecha_generacion DESC'; break;
		// Sin firma real y sin vencer, sin importar el estado exacto. Excluye borrador/anulado: un borrador no es "pendiente" real.
		case 'pendientes': $condicionEstado = "a.estado NOT IN ('vencido', 'borrador', 'anulado') AND a.acta_firmada_azure_path IS NULL"; $orden = 'dias_restantes ASC'; break;
		case 'vencidas':   $condicionEstado = "a.estado = 'vencido'"; $orden = 'a.fecha_generacion DESC'; break;
		default:           $condicionEstado = "a.estado NOT IN ('borrador', 'anulado')"; $orden = 'a.fecha_generacion DESC';
	}

	// LEFT JOIN a propósito: si el pos_id ya no matchea el maestro, un JOIN normal la haría desaparecer del detalle.
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.fecha_generacion, a.estado,
		        (a.acta_firmada_azure_path IS NOT NULL) AS tiene_firma,
		        a.acta_firmada_subido_en, a.acta_firmada_mime,
		        a.firma_validada_en, a.firma_rechazada_en, a.firma_rechazada_motivo,
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

function condicionCanalNegociacion($canal) {
	if ($canal === "directo") return " AND NOT EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.pos_id = a.pos_id AND d2.canal = 'DISTRIBUIDOR')";
	if ($canal === "distribuidor") return " AND EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d2 WHERE d2.pos_id = a.pos_id AND d2.canal = 'DISTRIBUIDOR')";
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
		"SELECT a.id, a.documento_no, (SELECT MIN(m.pos_name) FROM repositorio_locales_supervisores_cliente m WHERE m.pos_id = a.pos_id) AS cliente
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
		$estimado = $l['tipo'] === 'meta_compra' ? ($esDistribuidor ? ($sumaMensual * $rebateRaw) : ($sumaMensual * (1 + $rebateRaw))) : null;

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

// ---------- Módulo "Cumplimiento de Cuota" ---------- CEDI del Excel gana sobre el maestro. $canal filtra por el SUPERVISOR ya resuelto.
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

	// `canal`: badge solo cuando Vista="Total", derivado del SUPERVISOR. La subquery MIN(supervisor) evita duplicar filas por pos_id repetido.
	$stmt = $mysqli->prepare(
		"SELECT c.id, c.pos_id, c.cliente_excel, c.cedi_excel, c.plan_excel, c.sector,
		        c.cuota_total, c.venta_total, c.cumplimiento_pct,
		        c.gana_categoria, c.gana_categoria_anterior, c.gana_total,
		        c.rebate_real_vol, c.updated_at,
		        COALESCE(u_cedi.id, u_master.id) AS usuario_id,
		        COALESCE(u_cedi.usuario, u_master.usuario) AS usuario_nombre,
		        (CASE WHEN EXISTS (SELECT 1 FROM repositorio_locales_supervisores_cliente d3 WHERE d3.supervisor = COALESCE(u_cedi.supervisor, u_master.supervisor) AND d3.canal = 'DISTRIBUIDOR') THEN 'distribuidor' ELSE 'directo' END) AS canal,
		        (CASE WHEN EXISTS (SELECT 1 FROM repositorio_productos p WHERE p.fabricante = 'JABONERIA WILSON' AND p.sector = c.sector AND p.activar = 'SI') THEN 1 ELSE 0 END) AS categoria_valida
		 FROM repositorio_cumplimiento_cuota c
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN (SELECT pos_id, MIN(supervisor) AS supervisor FROM repositorio_locales_supervisores_cliente GROUP BY pos_id) mst ON mst.pos_id = c.pos_id
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
	// Mismo criterio que listar_cumplimiento_cuota(): canal por el SUPERVISOR del dueño real, con los mismos 2 LEFT JOIN.
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
		 LEFT JOIN (SELECT pos_id, MIN(supervisor) AS supervisor FROM repositorio_locales_supervisores_cliente GROUP BY pos_id) mst ON mst.pos_id = c.pos_id
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
