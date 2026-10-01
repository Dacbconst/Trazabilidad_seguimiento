<?php
// Sube la foto de perfil de un usuario a Azure y guarda su ruta (POST id + archivo). Solo admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

function ep_usuario_foto_responder(bool $ok, array $datos = [], int $codigo = 200): void {
	http_response_code($codigo);
	echo json_encode(array_merge(['ok' => $ok], $datos));
	exit;
}

if (($_SESSION['rol'] ?? '') !== 'admin') {
	ep_usuario_foto_responder(false, ['message' => 'No autorizado.'], 403);
}

require_once __DIR__.'/../includes/usuarios_datos.php';
require_once __DIR__.'/../includes/azure_storage.php';

try {
	$id = (int) ($_POST['id'] ?? 0);
	$usuario = ep_usuario_obtener($id);
	if (!$usuario) {
		ep_usuario_foto_responder(false, ['message' => 'El usuario no existe.'], 404);
	}
	$archivo = $_FILES['archivo'] ?? null;
	if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
		ep_usuario_foto_responder(false, ['message' => 'No se recibió la foto.'], 400);
	}
	if ($archivo['size'] > EP_FOTO_USUARIO_MAX) {
		ep_usuario_foto_responder(false, ['message' => 'La foto supera los 5 MB.'], 400);
	}
	// El tipo real se valida por contenido, no por la extensión.
	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
	$extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
	if (!isset($extensiones[$mime])) {
		ep_usuario_foto_responder(false, ['message' => 'Solo se permiten fotos JPG, PNG o WEBP.'], 400);
	}
	$contenido = file_get_contents($archivo['tmp_name']);
	$ext = $extensiones[$mime];
	// Se reduce a 400 px en JPEG: es un avatar, no hace falta más.
	if (function_exists('imagecreatefromstring')) {
		$img = @imagecreatefromstring($contenido);
		if ($img) {
			$escala = min(1, 400 / max(imagesx($img), imagesy($img)));
			$destino = imagecreatetruecolor((int) round(imagesx($img) * $escala), (int) round(imagesy($img) * $escala));
			imagecopyresampled($destino, $img, 0, 0, 0, 0, imagesx($destino), imagesy($destino), imagesx($img), imagesy($img));
			ob_start();
			imagejpeg($destino, null, 82);
			$contenido = ob_get_clean();
			imagedestroy($img);
			imagedestroy($destino);
			$mime = 'image/jpeg';
			$ext = 'jpg';
		}
	}
	// Una foto por usuario, con su nombre de usuario: subir otra reemplaza la anterior.
	$limpio = trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', strtr((string) $usuario['usuario'], ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u', 'Ü' => 'U'])), '_');
	$nombre = 'Usuarios/'.($limpio !== '' ? $limpio : 'usuario_'.$id).'.'.$ext;
	$blob = ep_azure_subir($nombre, $contenido, $mime);
	if ($blob === false) {
		ep_usuario_foto_responder(false, ['message' => 'No se pudo subir la foto. Intenta de nuevo.'], 502);
	}
	$ruta = substr($blob, strlen(EP_AZURE_PREFIX));
	if (!ep_usuario_guardar_foto($id, $ruta)) {
		ep_usuario_foto_responder(false, ['message' => 'Falta la columna foto en la base de datos.'], 500);
	}
	ep_usuario_foto_responder(true, ['url' => ep_usuario_foto_url($ruta)]);
} catch (Throwable $e) {
	error_log('usuario_foto: '.$e->getMessage());
	ep_usuario_foto_responder(false, ['message' => 'Error del servidor: '.$e->getMessage()], 500);
}
