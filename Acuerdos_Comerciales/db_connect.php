<?php
include_once __DIR__.'/config.php';
// Desde PHP 8.1, mysqli tira excepciones en vez de devolver false, rompiendo el patrón if (!$stmt) usado en toda la app. Esto restaura ese comportamiento.
mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = new mysqli(HOST, USER, PASS, DB);

if ($mysqli->connect_errno) {
	die('Error de conexión a la base de datos: '.$mysqli->connect_error);
}
?>
