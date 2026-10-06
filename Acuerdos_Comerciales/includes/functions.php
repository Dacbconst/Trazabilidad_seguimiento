<?php
// Sesión y roles en un solo archivo a propósito, proyecto independiente de Xplora.

// En este hosting (nginx) un fatal error de PHP se ve en el navegador como "404 Not Found", sin pista real — esto deja la causa real en logs/errores_fatales.log.
register_shutdown_function(function () {
	$error = error_get_last();
	if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
		$linea = '['.date('Y-m-d H:i:s').'] '.($_SERVER['REQUEST_URI'] ?? 'cli').' -> '.$error['message'].' en '.$error['file'].':'.$error['line'].PHP_EOL;
		@file_put_contents(__DIR__.'/../logs/errores_fatales.log', $linea, FILE_APPEND);
	}
});

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
	return login_check_motivo() === 'ok';
}

// Distingue "nunca hubo sesión / se perdió por otra razón" (motivo neutro) de "otro login pisó el token" (motivo real de "otro dispositivo") — antes ambos casos mostraban el mismo mensaje de "otro dispositivo", confundiendo al usuario cuando la sesión se cae sola.
function login_check_motivo() {
	if (!isset($_SESSION['user_id'], $_SESSION['rol'])) return 'sesion_perdida';
	static $motivo = null;
	if ($motivo !== null) return $motivo;
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
				$motivo = 'otro_dispositivo';
				return $motivo;
			}
		}
	}
	$motivo = 'ok';
	return $motivo;
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
	$resPropios = $mysqli->query("SELECT id, pos_id, cliente AS pos_name, UPPER(canal) AS canal, distribuidor AS tipo_distribuidor, cedi AS supervisor, cedi FROM repositorio_clientes_propiosac");
	if ($resPropios) {
		while ($fila = $resPropios->fetch_assoc()) {
			$fila['pos_name_comparable'] = repositorio_texto_comparable($fila['pos_name']);
			$fila['tipo_distribuidor_comparable'] = repositorio_texto_comparable($fila['tipo_distribuidor']);
			$fila['supervisor_comparable'] = repositorio_texto_comparable($fila['supervisor']);
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

	$stmt = $mysqli->prepare(
		'INSERT INTO repositorio_clientes_propiosac (pos_id, cliente, cedi, distribuidor, canal, creado_por)
		 VALUES (\'\', ?, ?, ?, ?, ?)'
	);
	if (!$stmt) return null;
	$stmt->bind_param('ssssi', $clienteExcel, $cediExcel, $distribuidorExcel, $canalDb, $creadoPor);
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

	if (count($candidatos) === 1) return $candidatos[0]['pos_id'];
	if (count($candidatos) === 0) {
		// Si hay algo parecido ya registrado (típico de un typo), nunca crear un propio nuevo — que quede "sin identificar" con la misma sugerencia de siempre, para que corrijan el Excel en vez de duplicar al cliente.
		if (sugerirClienteSimilar($mysqli, $clienteExcel, $canal)) return null;
		// Ni el maestro de Alicorp ni nuestra base propia tienen este cliente: lo creamos nosotros (pedido explícito, base de Alicorp incompleta) para que el próximo trimestre ya lo reconozca solo.
		if (!$permitirCrear) { $pendienteCrear = true; return null; }
		return crearClientePropio($mysqli, $clienteExcel, $cediExcel, $canal, $distribuidorExcel, $creadoPor);
	}

	// Desempate por Ciudad en los 2 canales (el Distribuidor/Empresa varía con el tiempo, la Ciudad no) — $cediExcel ya trae la Ciudad en Distribuidor también.
	if (!$cediExcel) return null;
	$cediComparable = repositorio_texto_comparable($cediExcel);
	$desempatados = array_values(array_filter($candidatos, fn($f) => $f['supervisor_comparable'] === $cediComparable));
	if (!$desempatados) {
		// Nombre matchea pero la Ciudad no: se registra igual (nunca bloquea el guardado), el diagnóstico avisa en la alerta de siempre con la Ciudad real para que el analista la corrija en el Excel.
		$diagnostico = ['campo' => 'supervisor', 'valores_reales' => array_values(array_unique(array_column($candidatos, 'supervisor')))];
		$desempatados = $candidatos;
	}

	if (count($desempatados) === 1) return $desempatados[0]['pos_id'];
	// Duplicado real (mismo nombre+canal+ciudad): toma el registro más reciente.
	usort($desempatados, fn($a, $b) => $b['id'] <=> $a['id']);
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
	$clienteComparable = repositorio_texto_comparable($clienteExcel);
	if (strlen($clienteComparable) < 6) return [];
	$esDistribuidor = $canal === 'distribuidor';
	$minLargo = 6;
	$vistos = [];
	$sugerencias = [];
	foreach (maestroClientesEnMemoria($mysqli) as $f) {
		if (empty($f['es_propio'])) continue;
		if ($esDistribuidor ? $f['canal'] !== 'DISTRIBUIDOR' : $f['canal'] === 'DISTRIBUIDOR') continue;
		$masCorto = strlen($f['pos_name_comparable']) < strlen($clienteComparable) ? $f['pos_name_comparable'] : $clienteComparable;
		if (strlen($masCorto) < $minLargo) continue;
		$coincide = strncmp($f['pos_name_comparable'], $clienteComparable, strlen($masCorto)) === 0;
		if (!$coincide || isset($vistos[$f['pos_name']])) continue;
		$vistos[$f['pos_name']] = true;
		$sugerencias[] = $f['pos_name'];
		if (count($sugerencias) >= 5) break;
	}
	return $sugerencias;
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

// Dado un pos_id resuelto, encuentra el usuario responsable vía su supervisor real. Null si no hay cuenta activa. $posName (opcional) desempata cuando el pos_id está duplicado en el maestro entre clientes distintos (~1,110 casos confirmados) — sin él, cae en el criterio viejo (primera fila que encuentre).
function usuarioIdDePosId($mysqli, $posId, $posName = null) {
	$nombreComparable = $posName !== null ? repositorio_texto_comparable($posName) : null;
	$condicionNombre = $nombreComparable !== null ? ' AND '.repositorio_sql_comparable('c.pos_name').' = ?' : '';

	$stmt = $mysqli->prepare(
		"SELECT u.id FROM repositorio_locales_supervisores_cliente c
		 JOIN repositorio_usuarios_acuerdos u ON u.supervisor = c.supervisor AND u.status = 'activo'
		 WHERE c.pos_id = ?$condicionNombre LIMIT 1"
	);
	if ($stmt) {
		if ($nombreComparable !== null) $stmt->bind_param('ss', $posId, $nombreComparable);
		else $stmt->bind_param('s', $posId);
		$stmt->execute();
		$fila = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if ($fila) return (int) $fila['id'];
	}

	$stmtSup = $mysqli->prepare("SELECT supervisor FROM repositorio_locales_supervisores_cliente WHERE pos_id = ?$condicionNombre LIMIT 1");
	if (!$stmtSup) return null;
	if ($nombreComparable !== null) $stmtSup->bind_param('ss', $posId, $nombreComparable);
	else $stmtSup->bind_param('s', $posId);
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

// Columna USUARIO del Excel de Cuotas (2026-09-28, pedido explícito): el Excel manda directo, sin adivinar por CEDI/supervisor. Exacto salvo mayúsculas/espacios — un usuario mal tipeado NO cae a otro criterio, se reporta como error (ver resolverNombreAsignadoCuota()).
function resolverUsuarioExacto($mysqli, $usuarioExcel) {
	$usuarioExcel = trim((string) $usuarioExcel);
	if ($usuarioExcel === '') return null;
	$stmt = $mysqli->prepare(
		"SELECT id, usuario FROM repositorio_usuarios_acuerdos WHERE UPPER(TRIM(usuario)) = UPPER(TRIM(?)) AND status = 'activo' LIMIT 1"
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $usuarioExcel);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $fila ?: null;
}

// Dueño real de una fila de Cuotas: la columna USUARIO del Excel manda si vino tipeada; si no, CEDI del Excel; el maestro de Alicorp es el último respaldo.
function usuarioIdDeCuota($mysqli, $posId, $trimestre, $anio) {
	$posName = null;
	$stmt = $mysqli->prepare(
		"SELECT cedi_excel, cliente_excel, usuario_excel FROM repositorio_cuota_cliente WHERE pos_id = ? AND trimestre = ? AND anio = ? LIMIT 1"
	);
	if (!$stmt) $stmt = $mysqli->prepare(
		"SELECT cedi_excel, cliente_excel FROM repositorio_cuota_cliente WHERE pos_id = ? AND trimestre = ? AND anio = ? LIMIT 1"
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
function resolverNombreAsignadoCuota($mysqli, $posId, $cediExcel, $posName = null, $usuarioExcel = null) {
	// Columna USUARIO manda directo — si vino tipeada pero no matchea ninguna cuenta activa, ESO es el error a mostrar (no cae a CEDI/maestro, sería tapar el typo).
	if ($usuarioExcel !== null && trim((string) $usuarioExcel) !== '') {
		$usuarioExacto = resolverUsuarioExacto($mysqli, $usuarioExcel);
		if ($usuarioExacto) return ['nombre' => $usuarioExacto['usuario'], 'tiene_cuenta' => true];
		return ['nombre' => trim((string) $usuarioExcel), 'tiene_cuenta' => false];
	}
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
	// $posName (opcional) desempata cuando el pos_id está duplicado en el maestro entre clientes distintos (~1,110 casos confirmados).
	$nombreComparable = $posName !== null ? repositorio_texto_comparable($posName) : null;
	$condicionNombre = $nombreComparable !== null ? ' AND '.repositorio_sql_comparable('c.pos_name').' = ?' : '';
	$stmt = $mysqli->prepare(
		"SELECT c.supervisor, u.usuario, (u.id IS NOT NULL) AS tiene_cuenta
		 FROM repositorio_locales_supervisores_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.supervisor = c.supervisor AND u.status = 'activo'
		 WHERE c.pos_id = ?$condicionNombre LIMIT 1"
	);
	if (!$stmt) return ['nombre' => null, 'tiene_cuenta' => false];
	if ($nombreComparable !== null) $stmt->bind_param('ss', $posId, $nombreComparable);
	else $stmt->bind_param('s', $posId);
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
		// "OTRAS CATEGORIAS" se ignora acá también, para coincidir con lo que ve el asesor.
		if (strtoupper(trim($f['sector'])) === 'OTRAS CATEGORIAS') continue;
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
		if (strtoupper(trim($f['sector'])) === 'OTRAS CATEGORIAS') continue;
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
		"SELECT c.pos_id, c.cliente_excel, c.trimestre, c.anio, c.updated_at, u.usuario
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

// 20 días hábiles = 28 días calendario si fecha_generacion cae en día de semana, 30 si cae sábado/domingo (WEEKDAY: 0=Lun..6=Dom).
function sqlFechaLimiteFirma($col) {
	return "DATE_ADD($col, INTERVAL IF(WEEKDAY($col) > 4, 30, 28) DAY)";
}

// Misma regla que sqlFechaLimiteFirma() pero en PHP, para renderFilaHistorial() (no pasa por una query).
function fechaLimiteFirmaPhp($fechaGeneracion) {
	$d = new DateTime($fechaGeneracion);
	return $d->modify('+'.((int) $d->format('N') >= 6 ? 30 : 28).' days');
}

// Un Acta con 20+ días HÁBILES desde fecha_generacion pasa a 'vencido'. Sin cron: corre cada vez que se listan Actas o se calculan alertas.
function barrer_actas_vencidas($mysqli) {
	$limite = sqlFechaLimiteFirma('fecha_generacion');
	$mysqli->query(
		"UPDATE repositorio_acuerdos
		 SET estado = 'vencido'
		 WHERE estado IN ('generado', 'enviado')
		   AND fecha_generacion IS NOT NULL
		   AND $limite < CURDATE()"
	);
}

// Actas propias por vencer, alimenta "Mis Actas" de la campanita, sin firmar con $diasUmbral días o menos.
function listar_alertas_firma_propias($mysqli, $usuarioId, $diasUmbral = 5) {
	if (!$usuarioId) return [];
	barrer_actas_vencidas($mysqli);
	$limite = sqlFechaLimiteFirma('a.fecha_generacion');
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.fecha_generacion,
		        DATEDIFF($limite, CURDATE()) AS dias_restantes
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
// Canal real de un Acuerdo: lee directo la columna `canal` de repositorio_acuerdos, grabada una sola vez al crearlo (ver guardar_acuerdo.php) — nunca se vuelve a comparar contra el maestro ni ninguna otra tabla acá (pedido explícito: la única validación contra el maestro vive en Repositorios, al subir el Excel). Reusado por Historial y por los 2 export de Excel.
function sqlCanalOrigenAcuerdo($aliasAcuerdo = 'a') {
	return "UPPER($aliasAcuerdo.canal)";
}

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

	$canalOrigenSql = sqlCanalOrigenAcuerdo('a');
	$condicionCanal = '';
	if ($canal === 'directo') {
		$condicionCanal = " AND $canalOrigenSql = 'DIRECTO'";
	} elseif ($canal === 'distribuidor') {
		$condicionCanal = " AND $canalOrigenSql = 'DISTRIBUIDOR'";
	}

	$condicionFirma = '';
	if ($filtroFirma === 'firmadas') $condicionFirma = ' AND a.acta_firmada_azure_path IS NOT NULL';
	elseif ($filtroFirma === 'pendientes') $condicionFirma = ' AND a.acta_firmada_azure_path IS NULL';

	// Cliente: SIEMPRE lo que ya se guardó al subir el Excel (Cuotas/Acuerdo Completo) — nunca el maestro, ni para buscar ni para mostrar.
	$clienteOrigenSql = "COALESCE(
		(SELECT cc.cliente_excel FROM repositorio_cuota_cliente cc WHERE cc.acuerdo_id_generado = a.id LIMIT 1),
		(SELECT acl.cliente_excel FROM repositorio_acuerdo_completo_linea acl WHERE acl.acuerdo_id_generado = a.id LIMIT 1)
	)";
	// LEFT JOIN para "Generado por": un Acta huérfana (creado_por NULL) no debe desaparecer de Historial.
	$sqlBase = "FROM repositorio_acuerdos a
		LEFT JOIN repositorio_usuarios_acuerdos ug ON ug.id = a.creado_por
		WHERE a.estado NOT IN ('borrador', 'anulado', 'vencido')
		  AND (? = 1 OR a.creado_por = ?)
		  AND COALESCE($clienteOrigenSql, '') LIKE ?
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

	// Canal: columna propia de repositorio_acuerdos, grabada al crear el Acuerdo (ver guardar_acuerdo.php) — nunca el maestro.
	$canalCanonico = "UPPER(a.canal) AS canal";
	$cediOrigenSql = "COALESCE(
		(SELECT cc2.cedi_excel FROM repositorio_cuota_cliente cc2 WHERE cc2.acuerdo_id_generado = a.id LIMIT 1),
		(SELECT acl2.cedi_excel FROM repositorio_acuerdo_completo_linea acl2 WHERE acl2.acuerdo_id_generado = a.id LIMIT 1)
	)";
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.pos_id, a.documento_no, a.mes_inicio, a.mes_fin, a.fecha_generacion, a.estado, a.creado_por,
		        (a.acta_firmada_azure_path IS NOT NULL) AS tiene_firma, a.acta_firmada_mime,
		        $clienteOrigenSql AS pos_name, $cediOrigenSql AS cedi, $canalCanonico, ug.usuario AS generado_por
		 $sqlBase
		 ORDER BY a.fecha_generacion DESC, a.id DESC
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT a.id, a.pos_id, a.documento_no, a.mes_inicio, a.mes_fin, a.fecha_generacion, a.estado, a.creado_por,
			        0 AS tiene_firma, NULL AS acta_firmada_mime,
			        $clienteOrigenSql AS pos_name, $cediOrigenSql AS cedi, $canalCanonico, ug.usuario AS generado_por
			 $sqlBase
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
	// Mismo criterio "ver todo" y filtro de Canal que listar_historial_acuerdos() (sqlCanalOrigenAcuerdo()).
	$verTodos = ($rol === 'superdesarrollador') ? 1 : 0;
	$condicionCanal = '';
	if ($canal === 'directo') {
		$condicionCanal = " AND ".sqlCanalOrigenAcuerdo('a')." = 'DIRECTO'";
	} elseif ($canal === 'distribuidor') {
		$condicionCanal = " AND ".sqlCanalOrigenAcuerdo('a')." = 'DISTRIBUIDOR'";
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
			$limite = fechaLimiteFirmaPhp($a['fecha_generacion']);
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
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.pos_id, a.anio, a.mes_inicio, a.mes_fin, a.estado, a.fecha_generacion, a.creado_por, a.sin_visibilidad,
		        u.usuario AS ejecutivo_comercial
		 FROM repositorio_acuerdos a
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
		 WHERE a.id = ? LIMIT 1"
	);
	if (!$stmt) return null;
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$cabecera = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$cabecera) return null;

	// Desambiguado por nombre (pos_id solo no es único en el maestro — bug real confirmado: el mismo pos_id resolvía a veces como "Directo", a veces como "Distribuidor" según qué fila devolviera MySQL). El nombre real del cliente se recupera del repositorio de origen (Cuotas o Acuerdo Completo), si vino de una precarga.
	$clienteOrigen = null;
	$canalOrigen = null;
	$stmtOrigenCuota = $mysqli->prepare('SELECT cliente_excel, plan FROM repositorio_cuota_cliente WHERE acuerdo_id_generado = ? LIMIT 1');
	if ($stmtOrigenCuota) {
		$stmtOrigenCuota->bind_param('i', $acuerdoId);
		$stmtOrigenCuota->execute();
		$filaOrigen = $stmtOrigenCuota->get_result()->fetch_assoc();
		$stmtOrigenCuota->close();
		if ($filaOrigen) {
			$clienteOrigen = $filaOrigen['cliente_excel'];
			$canalOrigen = ($filaOrigen['plan'] ?? '') !== '' ? 'distribuidor' : 'directo';
		}
	}
	if ($clienteOrigen === null) {
		$stmtOrigenCompleto = $mysqli->prepare('SELECT cliente_excel, plan FROM repositorio_acuerdo_completo_linea WHERE acuerdo_id_generado = ? LIMIT 1');
		if ($stmtOrigenCompleto) {
			$stmtOrigenCompleto->bind_param('i', $acuerdoId);
			$stmtOrigenCompleto->execute();
			$filaOrigen = $stmtOrigenCompleto->get_result()->fetch_assoc();
			$stmtOrigenCompleto->close();
			if ($filaOrigen) {
				$clienteOrigen = $filaOrigen['cliente_excel'];
				$canalOrigen = ($filaOrigen['plan'] ?? '') !== '' ? 'distribuidor' : 'directo';
			}
		}
	}
	$d = clienteMaestroDePosId($mysqli, $cabecera['pos_id'], $clienteOrigen, $canalOrigen);
	if (!$d) return null;
	$cabecera['pos_name'] = $d['pos_name'];
	$cabecera['cedi'] = $d['cedi'];
	$cabecera['canal'] = $d['canal'];
	$cabecera['tipo_distribuidor'] = $d['tipo_distribuidor'];

	$stmt = $mysqli->prepare(
		"SELECT tipo, segmento, sector, categoria, marca, rebate_pct, cantidad_max_percha, participacion_pct, precio_percha,
		        valores_mensuales, valor_mensual_unico, orden
		 FROM repositorio_acuerdo_lineas WHERE acuerdo_id = ? ORDER BY tipo, orden"
	);
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	// Origen del Acuerdo, calculado antes de armar las líneas: Cuotas siempre bloquea Visibilidad; Acuerdo Completo marca cada línea como bloqueada en pantalla.
	$deCuotas = false;
	$stmtCuotas = $mysqli->prepare('SELECT 1 FROM repositorio_cuota_cliente WHERE acuerdo_id_generado = ? LIMIT 1');
	if ($stmtCuotas) {
		$stmtCuotas->bind_param('i', $acuerdoId);
		$stmtCuotas->execute();
		$deCuotas = (bool) $stmtCuotas->get_result()->fetch_assoc();
		$stmtCuotas->close();
	}
	$deAcuerdoCompleto = false;
	$stmtCompleto = $mysqli->prepare('SELECT 1 FROM repositorio_acuerdo_completo_linea WHERE acuerdo_id_generado = ? LIMIT 1');
	if ($stmtCompleto) {
		$stmtCompleto->bind_param('i', $acuerdoId);
		$stmtCompleto->execute();
		$deAcuerdoCompleto = (bool) $stmtCompleto->get_result()->fetch_assoc();
		$stmtCompleto->close();
	}

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
			'bloqueado'           => $deAcuerdoCompleto,
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
		'de_cuotas'         => $deCuotas,
		'de_acuerdo_completo' => $deAcuerdoCompleto,
		'empresa_distribuidora' => $cabecera['tipo_distribuidor'] ?: '',
		'ejecutivo_comercial' => $cabecera['ejecutivo_comercial'] ?: '',
		'lineas'            => $lineas,
	];
}

// Borradores propios, para "Mis Borradores", mismo scoping por creador que listar_historial_acuerdos().
function listar_borradores_usuario($mysqli, $usuarioId) {
	if (!$usuarioId) return [];
	// pos_name/cedi: SIEMPRE lo que ya se guardó al subir el Excel (Cuotas/Acuerdo Completo) — nunca el maestro.
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.anio, a.mes_inicio, a.mes_fin, a.updated_at,
		        COALESCE(
		          (SELECT cc.cliente_excel FROM repositorio_cuota_cliente cc WHERE cc.acuerdo_id_generado = a.id LIMIT 1),
		          (SELECT acl.cliente_excel FROM repositorio_acuerdo_completo_linea acl WHERE acl.acuerdo_id_generado = a.id LIMIT 1)
		        ) AS pos_name,
		        COALESCE(
		          (SELECT cc2.cedi_excel FROM repositorio_cuota_cliente cc2 WHERE cc2.acuerdo_id_generado = a.id LIMIT 1),
		          (SELECT acl2.cedi_excel FROM repositorio_acuerdo_completo_linea acl2 WHERE acl2.acuerdo_id_generado = a.id LIMIT 1)
		        ) AS cedi
		 FROM repositorio_acuerdos a
		 WHERE a.estado = 'borrador' AND a.creado_por = ?
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
		'pendientes' => $pendientes, 'usadas' => $usadas, 'pendientes_match' => $pendientesMatch, 'borradores' => 0,
		'por_usuario' => $porUsuario, 'chocan' => $chocan,
	];
}

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
	$limite = sqlFechaLimiteFirma('a.fecha_generacion');
	$stmt = $mysqli->prepare(
		"SELECT u.id AS usuario_id, u.usuario AS nombre,
		        COUNT(*) AS total,
		        COUNT(CASE WHEN a.acta_firmada_azure_path IS NOT NULL THEN 1 END) AS firmadas,
		        COUNT(CASE WHEN a.acta_firmada_azure_path IS NULL AND a.estado <> 'vencido' THEN 1 END) AS pendientes,
		        COUNT(CASE WHEN a.estado = 'vencido' THEN 1 END) AS vencidas,
		        MIN(CASE WHEN a.acta_firmada_azure_path IS NULL AND a.estado <> 'vencido'
		                 THEN DATEDIFF($limite, CURDATE()) END) AS dias_mas_proxima
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

	// pos_name: SIEMPRE lo que ya se guardó al subir el Excel (Cuotas/Acuerdo Completo) — nunca el maestro, ni como respaldo (pedido explícito).
	$limite = sqlFechaLimiteFirma('a.fecha_generacion');
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.fecha_generacion, a.estado,
		        (a.acta_firmada_azure_path IS NOT NULL) AS tiene_firma,
		        a.acta_firmada_subido_en, a.acta_firmada_mime,
		        a.firma_validada_en, a.firma_rechazada_en, a.firma_rechazada_motivo,
		        COALESCE(
		          (SELECT cc.cliente_excel FROM repositorio_cuota_cliente cc WHERE cc.acuerdo_id_generado = a.id LIMIT 1),
		          (SELECT acl.cliente_excel FROM repositorio_acuerdo_completo_linea acl WHERE acl.acuerdo_id_generado = a.id LIMIT 1)
		        ) AS pos_name,
		        DATEDIFF($limite, CURDATE()) AS dias_restantes
		 FROM repositorio_acuerdos a
		 WHERE a.creado_por = ?
		   AND $condicionEstado
		   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		   AND (? = 0 OR a.anio = ?)
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

// Mismo bug/arreglo que Historial: antes comparaba d.canal directo contra el maestro (ambiguo con pos_id duplicados). Ahora usa el canal de origen de la precarga (sqlCanalOrigenAcuerdo()).
function condicionCanalNegociacion($canal) {
	if ($canal === "directo") return " AND ".sqlCanalOrigenAcuerdo('a')." = 'DIRECTO'";
	if ($canal === "distribuidor") return " AND ".sqlCanalOrigenAcuerdo('a')." = 'DISTRIBUIDOR'";
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
		"SELECT a.id, a.documento_no,
		        COALESCE(
		          (SELECT cc.cliente_excel FROM repositorio_cuota_cliente cc WHERE cc.acuerdo_id_generado = a.id LIMIT 1),
		          (SELECT acl.cliente_excel FROM repositorio_acuerdo_completo_linea acl WHERE acl.acuerdo_id_generado = a.id LIMIT 1)
		        ) AS cliente
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
?>
