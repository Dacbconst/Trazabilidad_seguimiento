<?php
// Crea un botón de actividad nuevo copiando la lógica (plantilla, campos y fotos) de uno existente. Solo admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
// La acción deja desactualizada una caché de sesión (ver ep_cache_sesion).
unset($_SESSION['ep_cache']['act_visibles']);
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'admin') {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/actividades_datos.php';

$nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 80);
$plantilla = (string) ($_POST['logica'] ?? '');

if ($nombre === '') {
	echo json_encode(['ok' => false, 'message' => 'Ponle un nombre a la actividad.']);
	exit;
}

// La lógica se elige entre las base (ep_logicas), así una lógica nueva se puede usar aunque ningún botón la tenga todavía.
$logica = ep_logicas()[$plantilla] ?? null;
if (!$logica) {
	echo json_encode(['ok' => false, 'message' => 'Elige qué lógica replicar.']);
	exit;
}

$error = ep_actividad_crear($nombre, $plantilla, (int) $_SESSION['usuario_id']);
if ($error === null) {
	require_once __DIR__.'/../includes/auditoria_datos.php';
	ep_auditar('actividad_crear', 'actividad', null, 'Creó la actividad «'.$nombre.'»', [ep_auditoria_dato('Copia la lógica de', $logica['label'])]);
}
echo json_encode($error === null ? ['ok' => true] : ['ok' => false, 'message' => $error]);
