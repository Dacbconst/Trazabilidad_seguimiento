<?php
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
		// Cookie propia: EpsonReport vive en el mismo dominio y compartían PHPSESSID, pisándose sesion_token y rol.
		session_name('ADNSESS');
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
		$_SESSION['supervisor'] = $row['supervisor'] ?? null; $_SESSION['canal'] = canalGuardadoUsuario($mysqli, $row['id']);
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
	$_SESSION['supervisor'] = $row['supervisor'] ?? null; $_SESSION['canal'] = canalGuardadoUsuario($mysqli, $row['id']);
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

// Interruptor de bloqueo de generación de Actas: poner en false para reabrirla a todos; el superdesarrollador nunca queda bloqueado.
const ACTAS_GENERACION_BLOQUEADA = true;

function actasGeneracionBloqueada() {
	return ACTAS_GENERACION_BLOQUEADA && ($_SESSION['rol'] ?? '') !== 'superdesarrollador';
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
