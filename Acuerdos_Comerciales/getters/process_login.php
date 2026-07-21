<?php
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();

$usuario  = $_POST['usuario']  ?? '';
$password = $_POST['password'] ?? '';

if ($usuario === '' || $password === '' || !login($usuario, $password, $mysqli)) {
	header('Location: ../login.php?error=1');
	exit;
}

header('Location: ../index.php');
exit;
?>
