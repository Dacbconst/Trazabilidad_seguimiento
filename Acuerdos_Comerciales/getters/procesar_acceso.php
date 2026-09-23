<?php
// Nombre elegido a propósito (no "process_login.php"): ese patrón queda bloqueado por reglas de WAF del hosting que filtran nombres típicos de bots.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

$usuario  = $_POST['usuario']  ?? '';
$password = $_POST['password'] ?? '';
$forzar   = ($_POST['forzar'] ?? '') === '1';

if ($usuario === '' || $password === '') {
	echo json_encode(['ok' => false, 'motivo' => 'credenciales']);
	exit;
}

$resultado = login($usuario, $password, $mysqli, $forzar);

if ($resultado === true) {
	echo json_encode(['ok' => true]);
	exit;
}

// 'bloqueado', 'sesion_activa' e 'inactivo' tienen mensaje propio en el frontend; cualquier otro caso es credenciales inválidas.
$motivo = in_array($resultado, ['bloqueado', 'sesion_activa', 'inactivo'], true) ? $resultado : 'credenciales';
echo json_encode(['ok' => false, 'motivo' => $motivo]);
?>
