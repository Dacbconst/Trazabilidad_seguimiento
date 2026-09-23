<?php
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();

require_once __DIR__.'/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

const EP_MAX_INTENTOS = 5;
const EP_MINUTOS_BLOQUEO = 15;

$usuario = trim($_POST['usuario'] ?? '');
$clave = trim($_POST['clave'] ?? '');
$redirect = trim($_POST['redirect'] ?? '');
$forzar = ($_POST['forzar'] ?? '') === '1';

// Responde el motivo del fallo; el frontend decide el mensaje/ventana.
function ep_login_fallo($motivo, $redirect = '') {
	echo json_encode(['ok' => false, 'motivo' => $motivo]);
	exit;
}

// Historial de cada intento (éxito o fallo) para seguimiento; si falla el log no bloquea el ingreso.
function ep_login_log($db, $usuarioId, $intentado, $exito, $motivo) {
	$ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
	$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
	$stmt = $db->prepare('INSERT INTO insert_reporte_login (usuario_id, usuario_intentado, exito, motivo, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
	if ($stmt) {
		$stmt->bind_param('isisss', $usuarioId, $intentado, $exito, $motivo, $ip, $ua);
		$stmt->execute();
		$stmt->close();
	}
}

if ($usuario === '' || $clave === '') {
	ep_login_fallo('credenciales');
}

$db = ep_db();
if (!$db) {
	ep_login_fallo('servidor');
}

$stmt = $db->prepare('SELECT id, usuario, contrasena, nombre, rol, status, intentos_fallidos, bloqueado_hasta, sesion_token, TIMESTAMPDIFF(SECOND, ultima_actividad, NOW()) AS inactivo FROM repositorio_usuarios_reporte WHERE usuario = ? LIMIT 1');
$stmt->bind_param('s', $usuario);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila || $fila['status'] !== 'activo') {
	ep_login_log($db, $fila['id'] ?? null, $usuario, 0, $fila ? 'inactivo' : 'usuario_inexistente');
	ep_login_fallo('credenciales');
}

if ($fila['bloqueado_hasta'] && strtotime($fila['bloqueado_hasta']) > time()) {
	ep_login_log($db, (int) $fila['id'], $usuario, 0, 'bloqueado');
	ep_login_fallo('bloqueado');
}

if (!hash_equals((string) $fila['contrasena'], $clave)) {
	$intentos = (int) $fila['intentos_fallidos'] + 1;
	if ($intentos >= EP_MAX_INTENTOS) {
		$up = $db->prepare('UPDATE repositorio_usuarios_reporte SET intentos_fallidos = 0, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL '.EP_MINUTOS_BLOQUEO.' MINUTE) WHERE id = ?');
	} else {
		$up = $db->prepare('UPDATE repositorio_usuarios_reporte SET intentos_fallidos = '.$intentos.' WHERE id = ?');
	}
	$up->bind_param('i', $fila['id']);
	$up->execute();
	$up->close();
	ep_login_log($db, (int) $fila['id'], $usuario, 0, 'clave_incorrecta');
	ep_login_fallo('credenciales');
}

// Ya hay una sesión abierta en otro dispositivo: se pregunta antes de cerrarla.
// Sesión activa de verdad = token guardado Y latido reciente (ping cada 15s, mismo criterio que Acuerdos_Comerciales). Un token viejo sin latido (pestaña cerrada) ya no cuenta.
$sesionViva = !empty($fila['sesion_token']) && $fila['inactivo'] !== null && (int) $fila['inactivo'] < 180;
if (!$forzar && $sesionViva) {
	ep_login_log($db, (int) $fila['id'], $usuario, 0, 'sesion_activa');
	ep_login_fallo('sesion_activa');
}

// Login correcto: token nuevo (invalida cualquier sesión previa de esta cuenta) y datos de seguimiento.
$token = bin2hex(random_bytes(32));
$ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
$up = $db->prepare('UPDATE repositorio_usuarios_reporte SET sesion_token = ?, ultimo_login = NOW(), ultima_actividad = NOW(), ultimo_ip = ?, total_logins = total_logins + 1, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?');
$up->bind_param('ssi', $token, $ip, $fila['id']);
$up->execute();
$up->close();
ep_login_log($db, (int) $fila['id'], $usuario, 1, null);

session_regenerate_id(true);
$_SESSION['ult_interaccion'] = time();
$_SESSION['usuario_id'] = (int) $fila['id'];
$_SESSION['usuario'] = $fila['usuario'];
$_SESSION['nombre'] = $fila['nombre'] ?: $fila['usuario'];
$_SESSION['rol'] = $fila['rol'] === 'admin' ? 'admin' : 'usuario';
$_SESSION['sesion_token'] = $token;

// Solo rutas locales, nunca login.php ni URLs con protocolo.
$destino = 'index.php';
if ($redirect !== '' && !preg_match('#^(https?:)?//#i', $redirect) && stripos($redirect, 'login.php') === false) {
	$destino = $redirect;
}
echo json_encode(['ok' => true, 'redirect' => $destino]);
