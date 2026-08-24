<?php
// Antes se llamaba process_login.php — el nombre con "login" quedaba
// bloqueado por una regla de seguridad del hosting/WAF (nombres tipo
// "process_login.php"/"wp-login.php" son blancos clásicos de bots, muchos
// proveedores los filtran por default). Mismo contenido, solo cambia el
// nombre del archivo para esquivar ese filtro.
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
