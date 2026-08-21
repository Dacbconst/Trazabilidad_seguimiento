<?php
// Sube la foto/PDF del Acta ya firmada a mano (el papel físico vuelve firmado
// y alguien lo escanea/fotografía) — vive en Historial, no es un módulo
// aparte: es el siguiente paso natural del ciclo de vida de un Acuerdo ya
// generado (ver CLAUDE.md, decisión 2026-08-20/21). Al subir, el archivo
// reemplaza cualquier subida anterior (no hay versionado) y el Acuerdo pasa
// a `estado='firmado'` automáticamente — aprovecha el ENUM que ya existía
// en el schema pero nunca se conectó a nada.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

function responder($ok, $message) {
	echo json_encode(['ok' => $ok, 'message' => $message]);
	exit;
}

$acuerdoId = (int) ($_POST['id'] ?? 0);
$usuarioId = $_SESSION['user_id'] ?? null;

if ($acuerdoId <= 0) {
	responder(false, 'Acuerdo inválido.');
}

// Mismo criterio de propiedad que eliminar_acuerdo.php/generar_acta_pdf.php:
// nadie sube la firma de un acuerdo ajeno adivinando el id. No se permite
// subir sobre un borrador (todavía no es un Acta real) ni uno anulado.
$stmt = $mysqli->prepare("SELECT creado_por, estado FROM repositorio_acuerdos WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $acuerdoId);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila || (int) $fila['creado_por'] !== (int) $usuarioId) {
	responder(false, 'Acuerdo no encontrado.');
}
if (in_array($fila['estado'], ['borrador', 'anulado'], true)) {
	responder(false, 'No se puede subir la firma de un acuerdo en borrador o anulado.');
}

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
	$errores = [
		UPLOAD_ERR_INI_SIZE => 'El archivo supera el tamaño máximo permitido por el servidor.',
		UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido.',
		UPLOAD_ERR_PARTIAL => 'La subida se interrumpió. Intenta de nuevo.',
		UPLOAD_ERR_NO_FILE => 'Selecciona una foto o PDF del Acta firmada.',
	];
	$codigo = $_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE;
	responder(false, $errores[$codigo] ?? 'No se pudo subir el archivo.');
}

// Límite propio (15MB, generoso para una foto de celular) — independiente de
// upload_max_filesize/post_max_size del servidor, que también aplican antes
// de llegar acá.
$tamanoMaximo = 15 * 1024 * 1024;
if ($_FILES['archivo']['size'] > $tamanoMaximo) {
	responder(false, 'El archivo no puede superar 15MB.');
}

// Mime real del contenido (finfo), no la extensión ni el Content-Type que
// manda el navegador — ambos se pueden falsear fácil.
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['archivo']['tmp_name']);
$mimesPermitidos = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
if (!in_array($mime, $mimesPermitidos, true)) {
	responder(false, 'Solo se aceptan fotos (JPG/PNG/WEBP) o PDF.');
}

$contenido = file_get_contents($_FILES['archivo']['tmp_name']);
if ($contenido === false) {
	responder(false, 'No se pudo leer el archivo subido.');
}

$stmt = $mysqli->prepare(
	"UPDATE repositorio_acuerdos
	 SET acta_firmada_archivo = ?, acta_firmada_mime = ?, acta_firmada_subido_en = NOW(), acta_firmada_subido_por = ?, estado = 'firmado'
	 WHERE id = ?"
);
if (!$stmt) {
	responder(false, 'No se pudo guardar (falta correr el ALTER TABLE de acta_firmada_archivo, ver CLAUDE.md).');
}
// 's' alcanza para el LONGBLOB: mysqli es binary-safe con bind_param, igual
// que ya hace guardar_acuerdo.php con pdf_documento.
$stmt->bind_param('ssii', $contenido, $mime, $usuarioId, $acuerdoId);
$ok = $stmt->execute();
$stmt->close();

responder((bool) $ok, $ok ? 'Acta firmada subida correctamente.' : 'No se pudo guardar el archivo.');
?>
