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
$titulo = trim((string) ($_POST['titulo'] ?? ''));
$programadas = ($_POST['programadas'] ?? '') !== '' ? min(999, max(0, (int) $_POST['programadas'])) : null;
$ids = json_decode((string) ($_POST['registros'] ?? '[]'), true);
if ($tipo === '' || !preg_match('/^\d{4}-\d{2}$/', $mes) || !is_array($ids) || count($ids) === 0) {
	ep_rep_responder(false, ['error' => 'Elige el tipo, el mes y al menos un registro.'], 400);
}
$ids = array_values(array_unique(array_map('intval', $ids)));

// Solo cuentan los registros que existen y son de ese tipo
$validos = [];
$mesesEncontrados = [];
foreach (ep_registros_datos(5000, $ids) as $r) {
	if (($r['tipo'] ?? '') === $tipo) {
		$fIso = (string) ($r['fecha_iso'] ?? '');
		$fMes = substr($fIso, 0, 7);
		if ($fMes !== '') {
			$mesesEncontrados[$fMes] = ($mesesEncontrados[$fMes] ?? 0) + 1;
		}
		if ($mes !== '' && strpos($fIso, $mes) === 0) {
			$validos[] = $r['db_id'];
		}
	}
}
// Si el mes especificado no coincidió pero hay registros del tipo elegido, usamos el mes predominante de los registros
if (count($validos) === 0 && !empty($mesesEncontrados)) {
	arsort($mesesEncontrados);
	$mes = (string) array_key_first($mesesEncontrados);
	foreach (ep_registros_datos(5000, $ids) as $r) {
		if (($r['tipo'] ?? '') === $tipo && strpos($r['fecha_iso'] ?? '', $mes) === 0) {
			$validos[] = $r['db_id'];
		}
	}
}
if (count($validos) === 0) {
	ep_rep_responder(false, ['error' => 'Los registros elegidos no corresponden a la actividad seleccionada.'], 400);
}

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
	$nombre = 'Reportes/'.date('dmYHis').'CALENDARIO'.strtoupper(preg_replace('/[^a-z]/', '', $tipo)).'.'.$extensiones[$mime];
	if (ep_azure_subir($nombre, file_get_contents($archivo['tmp_name']), $mime) === false) {
		ep_rep_responder(false, ['error' => 'No se pudo subir la imagen del calendario. Intenta de nuevo.'], 502);
	}
	$rutaCalendario = $nombre;
}

$id = ep_reporte_crear($tipo, $mes, $titulo !== '' ? $titulo : null, $rutaCalendario, $programadas, $validos, (int) $_SESSION['usuario_id']);
if ($id === 0) {
	ep_rep_responder(false, ['error' => 'No se pudo guardar el reporte. Intenta de nuevo.'], 500);
}
ep_rep_responder(true, ['id' => $id, 'total' => count($validos)]);
