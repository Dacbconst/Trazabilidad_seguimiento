<?php
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();

$usuario = trim($_POST['usuario'] ?? '');
$clave = trim($_POST['clave'] ?? '');

// MOCK temporal, sin tabla de usuarios todavía: pasa a validar contra la base cuando exista el esquema real.
if ($usuario === '' || $clave === '') {
	header('Location: ../login.php?error=1');
	exit;
}

$_SESSION['usuario'] = $usuario;
$_SESSION['rol'] = (stripos($usuario, 'admin') !== false) ? 'admin' : 'usuario'; // MOCK: rol real vendrá de la tabla de usuarios.

$redirect = trim($_POST['redirect'] ?? '');
// Validar que sea una ruta local segura que no sea login.php y no empiece con protocolo externo
if ($redirect !== '' && !preg_match('#^(https?:)?//#i', $redirect) && stripos($redirect, 'login.php') === false) {
	header('Location: ' . $redirect);
} else {
	header('Location: ../index.php');
}
exit;
