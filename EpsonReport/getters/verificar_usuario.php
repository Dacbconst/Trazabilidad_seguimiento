<?php
// Primer paso del registro: confirma que el usuario existe en Xplora y que aún no tiene contraseña propia.
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../includes/login_datos.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = trim($_POST['usuario'] ?? '');
if ($usuario === '') {
	ep_login_fallo('datos');
}

$db = ep_db();
if (!$db) {
	ep_login_fallo('servidor');
}
if (!ep_login_xplora($db, $usuario)) {
	ep_login_fallo('no_autorizado');
}
$perfil = ep_login_perfil($db, $usuario);
if ($perfil && $perfil['contrasena'] !== '') {
	ep_login_fallo('ya_registrado');
}
echo json_encode(['ok' => true]);
