<?php
// Conexión mysqli compartida (una por request). Devuelve null si la base no responde.
function ep_db() {
	static $db = false;
	if ($db !== false) {
		return $db;
	}
	require_once __DIR__.'/../config.php';
	mysqli_report(MYSQLI_REPORT_OFF);
	$conexion = @new mysqli(HOST, USER, PASS, DB);
	if ($conexion->connect_errno) {
		error_log('ep_db: '.$conexion->connect_error);
		$db = null;
		return null;
	}
	$conexion->set_charset('utf8mb4');
	$db = $conexion;
	return $db;
}
