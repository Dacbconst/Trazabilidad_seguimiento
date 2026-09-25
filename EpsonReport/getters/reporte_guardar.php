<?php
// Guarda un reporte mensual: la selección de registros + la imagen del calendario (a Azure). Solo admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/registros_datos.php';
require_once __DIR__.'/../includes/reportes_datos.php';
header('Content-Type: application/json; charset=utf-8');

function ep_rep_responder($ok, $datos = [], $codigo = 200) {
	http_response_code($codigo);
	echo json_encode(array_merge(['success' => $ok], $datos), JSON_UNESCAPED_UNICODE);
	exit;
}

if (!ep_login_check()) {
	ep_rep_responder(false, ['error' => 'Tu sesión se cerró. Inicia sesión de nuevo.', 'redirect' => 'login.php?error=sesion'], 401);
}
if (ep_rol_actual() !== 'admin') {
	ep_rep_responder(false, ['error' => 'No tienes permiso para esto.'], 403);
}

$tipo = preg_replace('/[^a-z-]/', '', (string) ($_POST['tipo'] ?? ''));
$mes = (string) ($_POST['mes'] ?? '');
$titulo = mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 150);
$nombreActividad = mb_substr(trim((string) ($_POST['nombre_actividad'] ?? '')), 0, 80);
$programadas = ($_POST['programadas'] ?? '') !== '' ? min(999, max(0, (int) $_POST['programadas'])) : null;
$comentarios = implode("\n", array_slice(array_filter(array_map(fn($l) => mb_substr(trim($l), 0, 200), preg_split('/\R/', (string) ($_POST['comentarios'] ?? '')))), 0, 5));
$ids = json_decode((string) ($_POST['registros'] ?? '[]'), true);
if ($tipo === '' || !preg_match('/^\d{4}-\d{2}$/', $mes) || !is_array($ids) || count($ids) === 0) {
	ep_rep_responder(false, ['error' => 'Elige el tipo, el mes y al menos un registro.'], 400);
}
if ($titulo === '') {
	ep_rep_responder(false, ['error' => 'Escribe el nombre del reporte.'], 400);
}
$ids = array_values(array_unique(array_map('intval', $ids)));

// Solo cuentan los registros que existen, son de esa lógica y no están en otro reporte activo.
$ocupados = array_flip(ep_registros_ocupados());
$validos = [];
$snapshot = [];
$fechas = [];
foreach (ep_registros_datos(5000, $ids) as $r) {
	if (($r['tipo'] ?? '') !== $tipo || isset($ocupados[(int) $r['db_id']])) {
		continue;
	}
	$validos[] = $r['db_id'];
	$snapshot[] = $r;
	$fechas[] = (string) ($r['fecha_actividad'] ?? ($r['fecha_iso'] ?? ''));
}
if (count($validos) === 0) {
	ep_rep_responder(false, ['error' => 'Los registros elegidos ya están en otro reporte o no corresponden a la actividad.'], 400);
}
sort($fechas);
$copia = json_encode(['desde' => $fechas[0], 'hasta' => end($fechas), 'actividad' => $nombreActividad, 'registros' => $snapshot], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Imagen del calendario (opcional): se sube a Azure, carpeta Reportes.
$rutaCalendario = null;
$archivo = $_FILES['calendario'] ?? null;
if ($archivo && $archivo['error'] === UPLOAD_ERR_OK) {
	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
	$extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
	if (!isset($extensiones[$mime])) {
		ep_rep_responder(false, ['error' => 'El calendario debe ser una imagen JPG o PNG.'], 400);
	}
	if ($archivo['size'] > 8 * 1024 * 1024) {
		ep_rep_responder(false, ['error' => 'La imagen del calendario supera los 8 MB.'], 400);
	}
	require_once __DIR__.'/../includes/azure_storage.php';
	// ddmmaaaaCALENDARIOTIPO; si ese día ya hubo otro reporte del mismo tipo (incluso eliminado) se agrega un número para no pisar su foto.
	$db = ep_db();
	$res = $db ? $db->prepare('SELECT COUNT(*) FROM insert_reporte_mensual WHERE tipo = ? AND DATE(created_at) = CURDATE()') : false;
	$previos = 0;
	if ($res) {
		$res->bind_param('s', $tipo);
		$res->execute();
		$res->bind_result($previos);
		$res->fetch();
		$res->close();
	}
	$nombre = 'Reportes/'.date('dmY').'CALENDARIO'.strtoupper(preg_replace('/[^a-z]/', '', $tipo)).($previos > 0 ? $previos + 1 : '').'.'.$extensiones[$mime];
	if (ep_azure_subir($nombre, file_get_contents($archivo['tmp_name']), $mime) === false) {
		ep_rep_responder(false, ['error' => 'No se pudo subir la imagen del calendario. Intenta de nuevo.'], 502);
	}
	$rutaCalendario = $nombre;
}

if ($tipo === 'activaciones' && $rutaCalendario === null) {
	ep_rep_responder(false, ['error' => 'Sube la foto del calendario de activaciones.'], 400);
}

$id = ep_reporte_crear($tipo, $mes, $titulo, $rutaCalendario, $programadas, $comentarios !== '' ? $comentarios : null, $validos, $copia, (int) $_SESSION['usuario_id']);
if ($id === 0) {
	ep_rep_responder(false, ['error' => 'No se pudo guardar el reporte. Intenta de nuevo.'], 500);
}
ep_rep_responder(true, ['id' => $id, 'total' => count($validos)]);
