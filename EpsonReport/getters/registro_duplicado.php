<?php
// Avisa antes de subir fotos si el promotor ya envió una Activación de ese punto y día (GET tipo, pos_id, fecha_actividad).
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!ep_login_check()) {
	http_response_code(401);
	echo json_encode(['duplicado' => false]);
	exit;
}
$usuarioId = (int) $_SESSION['usuario_id'];
session_write_close();

require_once __DIR__.'/../includes/registros_datos.php';
$codigo = ep_registro_duplicado($usuarioId, (string) ($_GET['tipo'] ?? ''), (string) ($_GET['pos_id'] ?? ''), (string) ($_GET['fecha_actividad'] ?? ''));
// Queda anotado en Auditoría para que el admin sepa por qué el promotor pidió eliminar un registro.
if ($codigo) {
	require_once __DIR__.'/../includes/auditoria_datos.php';
	$fecha = (string) ($_GET['fecha_actividad'] ?? '');
	ep_auditar('registro_duplicado', 'registro', null, 'Intentó enviar de nuevo una Activación del '.date('d/m/Y', strtotime($fecha)).' que ya tenía enviada', [
		ep_auditoria_dato('Registro existente', $codigo),
		ep_auditoria_dato('Día de la actividad', date('d/m/Y', strtotime($fecha))),
	]);
}
echo json_encode($codigo ? ['duplicado' => true, 'codigo' => $codigo, 'mensaje' => ep_registro_duplicado_mensaje($codigo, (string) $_GET['fecha_actividad'])] : ['duplicado' => false]);
