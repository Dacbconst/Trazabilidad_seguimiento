<?php
include_once __DIR__.'/config.php';
// Desde PHP 8.1, mysqli tira excepciones (mysqli_sql_exception) en errores de
// prepare()/execute() en vez de devolver false — rompe TODO el código de este
// proyecto que asume el comportamiento clásico (if (!$stmt), $stmt->errno,
// etc., ver getters/guardar_acuerdo.php y includes/functions.php). Esto
// restaura ese comportamiento clásico para toda la app.
mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = new mysqli(HOST, USER, PASS, DB);

if ($mysqli->connect_errno) {
	die('Error de conexión a la base de datos: '.$mysqli->connect_error);
}
?>
