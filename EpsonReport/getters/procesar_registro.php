<?php
// Primer ingreso de un mercaderista de Xplora: comprueba su cédula una vez y guarda la contraseña que elija (en texto plano, como la del admin).
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../includes/login_datos.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = trim($_POST['usuario'] ?? '');
$cedula = trim($_POST['cedula'] ?? '');
$clave = trim($_POST['clave'] ?? '');
$clave2 = trim($_POST['clave2'] ?? '');
$correo = strtolower(trim($_POST['correo'] ?? ''));

if ($usuario === '' || $cedula === '' || $clave === '') {
	ep_login_fallo('datos');
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150) {
	ep_login_fallo('correo');
}
if ($clave !== $clave2) {
	ep_login_fallo('no_coinciden');
}
if (strlen($clave) < EP_CLAVE_MINIMA) {
	ep_login_fallo('clave_corta');
}

$db = ep_db();
if (!$db) {
	ep_login_fallo('servidor');
}

$xplora = ep_login_xplora($db, $usuario);
if (!$xplora) {
	ep_login_fallo('credenciales');
}

$perfil = ep_login_perfil($db, $xplora['user']) ?? ep_login_crear_perfil($db, $xplora);
if (!$perfil) {
	ep_login_fallo('servidor');
}
if ($perfil['contrasena'] !== '') {
	ep_login_fallo('ya_registrado');
}
if (ep_login_bloqueado($perfil)) {
	ep_login_fallo('bloqueado');
}

// Sin cédula en Xplora no hay forma de comprobar quién es; el acceso lo habilita el administrador.
$cedulaXplora = trim((string) $xplora['cedula']);
if ($cedulaXplora === '') {
	ep_login_log($db, (int) $perfil['id'], $usuario, 0, 'registro_sin_cedula');
	ep_login_fallo('sin_cedula');
}
if (!hash_equals($cedulaXplora, $cedula)) {
	ep_login_sumar_fallo($db, $perfil);
	ep_login_log($db, (int) $perfil['id'], $usuario, 0, 'registro_cedula_incorrecta');
	ep_login_fallo('cedula');
}

// Si la columna correo todavía no existe se guarda solo la contraseña.
$up = $db->prepare('UPDATE repositorio_usuarios_reporte SET contrasena = ?, correo = ?, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?');
if ($up) {
	$up->bind_param('ssi', $clave, $correo, $perfil['id']);
} else {
	$up = $db->prepare('UPDATE repositorio_usuarios_reporte SET contrasena = ?, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?');
	$up->bind_param('si', $clave, $perfil['id']);
}
$up->execute();
$up->close();
ep_login_log($db, (int) $perfil['id'], $usuario, 1, 'registro');
echo json_encode(['ok' => true]);
