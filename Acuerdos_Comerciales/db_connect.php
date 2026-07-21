<?php
include_once __DIR__.'/config.php';
$mysqli = new mysqli(HOST, USER, PASS, DB);

if ($mysqli->connect_errno) {
	die('Error de conexión a la base de datos: '.$mysqli->connect_error);
}
?>
