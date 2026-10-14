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

$usuario      = trim($_POST['usuario'] ?? '');
$contrasena   = $_POST['contrasena'] ?? '';
$rol          = $_POST['rol'] ?? '';
$canal        = $_POST['canal'] ?? '';
$canal        = in_array($canal, ['directo', 'distribuidor'], true) ? $canal : null;
$rolesValidos = ['desarrollador', 'superdesarrollador'];

if ($usuario === '' || strlen($usuario) > 100) {
	echo json_encode(['ok' => false, 'message' => 'El nombre de usuario es obligatorio (máx. 100 caracteres).']);
	exit;
}
if (strlen($contrasena) < 4) {
	echo json_encode(['ok' => false, 'message' => 'La clave debe tener al menos 4 caracteres.']);
	exit;
}
if (!in_array($rol, $rolesValidos, true)) {
	echo json_encode(['ok' => false, 'message' => 'Rol inválido.']);
	exit;
}
if ($rol === 'desarrollador' && $canal === null) {
	echo json_encode(['ok' => false, 'message' => 'Elige el canal del usuario.']);
	exit;
}
if ($rol === 'superdesarrollador') $canal = null; // los administradores no tienen canal fijo

$stmt = $mysqli->prepare(
	"INSERT INTO repositorio_usuarios_acuerdos (usuario, contrasena, rol, canal, status) VALUES (?, ?, ?, ?, 'activo')"
);
if (!$stmt) {
	// La columna canal todavía no existe en la base.
	echo json_encode(['ok' => false, 'message' => 'Falta agregar la columna canal en la base. Avisa al equipo técnico.']);
	exit;
}
$stmt->bind_param('ssss', $usuario, $contrasena, $rol, $canal);

if (!$stmt->execute()) {
	$duplicado = $stmt->errno === 1062;
	$stmt->close();
	echo json_encode(['ok' => false, 'message' => $duplicado ? 'Ya existe un usuario con ese nombre.' : 'No se pudo crear el usuario.']);
	exit;
}
$stmt->close();

echo json_encode(['ok' => true, 'message' => 'Usuario creado correctamente.']);
?>
