<?php
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();

require_once __DIR__.'/../includes/login_datos.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = trim($_POST['usuario'] ?? '');
$clave = trim($_POST['clave'] ?? '');
$redirect = trim($_POST['redirect'] ?? '');
$forzar = ($_POST['forzar'] ?? '') === '1';

if ($usuario === '' || $clave === '') {
	ep_login_fallo('credenciales');
}

$db = ep_db();
if (!$db) {
	ep_login_fallo('servidor');
}

$perfil = ep_login_perfil($db, $usuario);
$esAdmin = $perfil && $perfil['rol'] === 'admin';

// Mercaderista de Xplora que todavía no creó su contraseña: se le pide registrarse.
if (!$perfil || (!$esAdmin && $perfil['contrasena'] === '')) {
	$existe = ep_login_xplora($db, $usuario);
	ep_login_log($db, $perfil['id'] ?? null, $usuario, 0, $existe ? 'sin_registro' : 'usuario_inexistente');
	ep_login_fallo($existe ? 'registrar' : 'credenciales');
}

// Un promotor desactivado en Xplora ya no entra aunque conserve su perfil.
if ($perfil['status'] !== 'activo' || (!$esAdmin && !ep_login_xplora($db, $perfil['usuario']))) {
	ep_login_log($db, (int) $perfil['id'], $usuario, 0, 'inactivo');
	ep_login_fallo('credenciales');
}

if (ep_login_bloqueado($perfil)) {
	ep_login_log($db, (int) $perfil['id'], $usuario, 0, 'bloqueado');
	ep_login_fallo('bloqueado');
}

// Las claves se guardan en texto plano (decisión del proyecto), tanto la del admin como la de los promotores.
$claveValida = hash_equals((string) $perfil['contrasena'], $clave);
if (!$claveValida) {
	ep_login_sumar_fallo($db, $perfil);
	ep_login_log($db, (int) $perfil['id'], $usuario, 0, 'clave_incorrecta');
	ep_login_fallo('credenciales');
}

// Ya hay una sesión abierta en otro dispositivo: se pregunta antes de cerrarla.
// Sesión activa de verdad = token guardado Y latido reciente (ping cada 15s, mismo criterio que Acuerdos_Comerciales).
$sesionViva = !empty($perfil['sesion_token']) && $perfil['inactivo'] !== null && (int) $perfil['inactivo'] < 180;
if (!$forzar && $sesionViva) {
	ep_login_log($db, (int) $perfil['id'], $usuario, 0, 'sesion_activa');
	ep_login_fallo('sesion_activa');
}

// Login correcto: token nuevo (invalida cualquier sesión previa de esta cuenta) y datos de seguimiento.
$token = bin2hex(random_bytes(32));
// El historial de ingresos (IP, fecha) vive en insert_reporte_login; aquí solo la sesión activa.
$up = $db->prepare('UPDATE repositorio_usuarios_reporte SET sesion_token = ?, ultima_actividad = NOW(), intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?');
$up->bind_param('si', $token, $perfil['id']);
$up->execute();
$up->close();
ep_login_log($db, (int) $perfil['id'], $usuario, 1, null);

session_regenerate_id(true);
$_SESSION['ult_interaccion'] = time();
$_SESSION['usuario_id'] = (int) $perfil['id'];
$_SESSION['usuario'] = $perfil['usuario'];
$_SESSION['nombre'] = $perfil['nombre'] ?: $perfil['usuario'];
$_SESSION['rol'] = $esAdmin ? 'admin' : 'usuario';
$_SESSION['sesion_token'] = $token;

// Solo rutas locales, nunca login.php ni URLs con protocolo.
$destino = 'index.php';
if ($redirect !== '' && !preg_match('#^(https?:)?//#i', $redirect) && stripos($redirect, 'login.php') === false) {
	$destino = $redirect;
}
echo json_encode(['ok' => true, 'redirect' => $destino]);
