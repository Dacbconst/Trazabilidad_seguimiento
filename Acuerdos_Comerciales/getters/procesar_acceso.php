<?php
// Nombre elegido a propósito (no "process_login.php"): ese patrón queda bloqueado por reglas de WAF del hosting que filtran nombres típicos de bots.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();

$usuario  = $_POST['usuario']  ?? '';
$password = $_POST['password'] ?? '';

if ($usuario === '' || $password === '') {
	header('Location: ../login.php?error=1');
	exit;
}

$resultado = login($usuario, $password, $mysqli);

if ($resultado === true) {
	header('Location: ../index.php');
	exit;
}

// 'bloqueado' (demasiados intentos fallidos, ver login() en functions.php)
// se distingue del error genérico para mostrar un mensaje específico.
header('Location: ../login.php?error='.($resultado === 'bloqueado' ? 'bloqueado' : '1'));
exit;
?>
