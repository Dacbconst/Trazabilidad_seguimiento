<?php
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();

header('Content-Type: application/json; charset=utf-8');

function ep_subir_foto_responder($ok, $datos = [], $codigo = 200) {
	http_response_code($codigo);
	echo json_encode(array_merge(['success' => $ok], $datos));
	exit;
}

require_once __DIR__.'/../includes/functions.php';

if (!ep_login_check()) {
	ep_subir_foto_responder(false, ['error' => 'Tu sesión se cerró porque se inició sesión con esta cuenta en otro dispositivo, o expiró.', 'redirect' => 'login.php?error=sesion'], 401);
}

require_once __DIR__.'/../includes/azure_storage.php';

$archivo = $_FILES['archivo'] ?? null;
if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
	ep_subir_foto_responder(false, ['error' => 'No se recibió la foto.'], 400);
}
if ($archivo['size'] > 15 * 1024 * 1024) {
	ep_subir_foto_responder(false, ['error' => 'La foto supera los 15 MB.'], 400);
}

// El tipo real se valida por contenido, no por extensión ni por lo que declare el navegador.
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
$extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($extensiones[$mime])) {
	ep_subir_foto_responder(false, ['error' => 'Solo se permiten fotos JPG, PNG o WEBP.'], 400);
}

$limpiar = function ($v) {
	return preg_replace('/[^a-z0-9_-]/i', '', (string) $v);
};
$tipo = $limpiar($_POST['tipo'] ?? '') ?: 'general';
$fotoId = $limpiar($_POST['foto_id'] ?? '') ?: 'foto';
$usuario = $limpiar(str_replace('.', '_', $_SESSION['usuario'])) ?: 'usuario';

$nombre = $tipo.'/'.date('Y-m').'/'.$usuario.'_'.$fotoId.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.'.$extensiones[$mime];
$contenido = file_get_contents($archivo['tmp_name']);

// Red de seguridad: si llega una foto pesada (cliente sin comprimir), se reduce aquí a 1000px JPEG para no inflar las presentaciones PPT.
if (strlen($contenido) > 300 * 1024 && function_exists('imagecreatefromstring')) {
	$img = @imagecreatefromstring($contenido);
	if ($img) {
		$ancho = imagesx($img);
		$alto = imagesy($img);
		$escala = min(1, 1000 / max($ancho, $alto));
		$destino = imagecreatetruecolor((int) round($ancho * $escala), (int) round($alto * $escala));
		imagecopyresampled($destino, $img, 0, 0, 0, 0, imagesx($destino), imagesy($destino), $ancho, $alto);
		ob_start();
		imagejpeg($destino, null, 72);
		$reducido = ob_get_clean();
		imagedestroy($img);
		imagedestroy($destino);
		if ($reducido !== '' && strlen($reducido) < strlen($contenido)) {
			$contenido = $reducido;
			$mime = 'image/jpeg';
			$nombre = preg_replace('/\.[a-z]+$/', '.jpg', $nombre);
		}
	}
}

$blobPath = ep_azure_subir($nombre, $contenido, $mime);
if ($blobPath === false) {
	ep_subir_foto_responder(false, ['error' => 'No se pudo subir la foto. Intenta de nuevo.'], 502);
}

ep_subir_foto_responder(true, ['path' => $blobPath, 'url' => ep_azure_url($blobPath)]);
