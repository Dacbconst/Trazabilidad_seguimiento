<?php
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
	echo json_encode(['ok' => false, 'message' => 'Usuario inválido.']);
	exit;
}

$campos  = [];
$tipos   = '';
$valores = [];

if (isset($_POST['rol'])) {
	$rolesValidos = ['desarrollador', 'superdesarrollador'];
	if (!in_array($_POST['rol'], $rolesValidos, true)) {
		echo json_encode(['ok' => false, 'message' => 'Rol inválido.']);
		exit;
	}
	$campos[]  = 'rol = ?';
	$tipos    .= 's';
	$valores[] = $_POST['rol'];
}

if (isset($_POST['supervisor'])) {
	$supervisor = trim($_POST['supervisor']);
	if ($supervisor !== '') {
		$tomadoPor = supervisores_asignados_activos($mysqli, $id)[$supervisor] ?? null;
		if ($tomadoPor) {
			echo json_encode(['ok' => false, 'message' => 'Ese supervisor ya está asignado al usuario "'.$tomadoPor.'".']);
			exit;
		}
	}
	$campos[]  = 'supervisor = ?';
	$tipos    .= 's';
	$valores[] = $supervisor !== '' ? $supervisor : null;
}

if (isset($_POST['status'])) {
	if ($id === (int) ($_SESSION['user_id'] ?? -1) && $_POST['status'] === 'inactivo') {
		echo json_encode(['ok' => false, 'message' => 'No puedes desactivar tu propia cuenta.']);
		exit;
	}
	if (!in_array($_POST['status'], ['activo', 'inactivo'], true)) {
		echo json_encode(['ok' => false, 'message' => 'Estado inválido.']);
		exit;
	}
	$campos[]  = 'status = ?';
	$tipos    .= 's';
	$valores[] = $_POST['status'];
}

if (isset($_POST['contrasena']) && $_POST['contrasena'] !== '') {
	$contrasena = $_POST['contrasena'];
	if (strlen($contrasena) < 4) {
		echo json_encode(['ok' => false, 'message' => 'La clave debe tener al menos 4 caracteres.']);
		exit;
	}
	$campos[]  = 'contrasena = ?';
	$tipos    .= 's';
	$valores[] = $contrasena;
}

if (!$campos) {
	echo json_encode(['ok' => false, 'message' => 'No hay cambios para guardar.']);
	exit;
}

$sql = 'UPDATE repositorio_usuarios_acuerdos SET '.implode(', ', $campos).' WHERE id = ?';
$tipos    .= 'i';
$valores[] = $id;

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
	// Probablemente falta correr getters/alter_usuarios_supervisor.sql (columna
	// "supervisor" todavía no existe) — evita un fatal error por prepare()=false.
	echo json_encode(['ok' => false, 'message' => 'No se pudo actualizar el usuario (revisar si falta la columna "supervisor" en la base).']);
	exit;
}
$stmt->bind_param($tipos, ...$valores);

if (!$stmt->execute()) {
	$stmt->close();
	echo json_encode(['ok' => false, 'message' => 'No se pudo actualizar el usuario.']);
	exit;
}
$stmt->close();

echo json_encode(['ok' => true, 'message' => 'Usuario actualizado correctamente.']);
?>
