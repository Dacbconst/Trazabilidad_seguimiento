<?php
// Sube la foto/PDF del Acta firmada a mano; vive en Historial, siguiente paso natural del ciclo de vida del Acuerdo.
// Reemplaza cualquier subida anterior (sin versionado) y pasa a estado='firmado' automáticamente.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/azure_storage.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

// Bufferea warnings/notices de PHP para que no rompan el JSON: una foto de cámara pesada puede disparar un warning de memory_limit antes del JSON.
ob_start();
set_exception_handler(function ($e) {
	while (ob_get_level() > 0) { ob_end_clean(); }
	echo json_encode(['ok' => false, 'message' => 'No se pudo subir el archivo: '.$e->getMessage()]);
	exit;
});
// Margen extra para fotos de cámara pesadas; @ evita warning si el hosting bloquea ini_set().
@ini_set('memory_limit', '256M');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

function responder($ok, $message) {
	while (ob_get_level() > 0) { ob_end_clean(); }
	echo json_encode(['ok' => $ok, 'message' => $message]);
	exit;
}

$acuerdoId = (int) ($_POST['id'] ?? 0);
$usuarioId = $_SESSION['user_id'] ?? null;

if ($acuerdoId <= 0) {
	responder(false, 'Acuerdo inválido.');
}

// Mismo criterio de propiedad que eliminar_acuerdo.php. No se permite subir sobre un borrador, anulado, ni vencido (20 días, ver barrer_actas_vencidas()).
$stmt = $mysqli->prepare("SELECT creado_por, estado, fecha_generacion, documento_no FROM repositorio_acuerdos WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $acuerdoId);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila || (int) $fila['creado_por'] !== (int) $usuarioId) {
	responder(false, 'Acuerdo no encontrado.');
}
if (in_array($fila['estado'], ['borrador', 'anulado', 'vencido'], true)) {
	responder(false, 'No se puede subir la firma de un acuerdo en borrador, vencido o anulado.');
}
// Defensa en tiempo real: el barrido de vencidos puede no haber corrido todavía si nadie visitó Historial, así que se rechequea la fecha acá.
if (in_array($fila['estado'], ['generado', 'enviado'], true) && $fila['fecha_generacion']) {
	$vencida = (new DateTime($fila['fecha_generacion']))->modify('+20 days') < new DateTime();
	if ($vencida) {
		$upd = $mysqli->prepare("UPDATE repositorio_acuerdos SET estado = 'vencido' WHERE id = ?");
		if ($upd) { $upd->bind_param('i', $acuerdoId); $upd->execute(); $upd->close(); }
		responder(false, 'El plazo de 20 días para firmar esta Acta ya venció.');
	}
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

// Límite propio (15MB, generoso para foto de celular), independiente de upload_max_filesize/post_max_size del servidor.
$tamanoMaximo = 15 * 1024 * 1024;
if ($_FILES['archivo']['size'] > $tamanoMaximo) {
	responder(false, 'El archivo no puede superar 15MB.');
}

// Mime real del contenido (finfo), no la extensión ni el Content-Type del navegador — ambos se pueden falsear fácil.
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

// Solo se guarda la RUTA en la base (Azure Blob Storage). Nombre fijo por Acuerdo: una subida nueva reemplaza el blob anterior, sin versionado.
$extensionesPorMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
$extension = $extensionesPorMime[$mime] ?? 'bin';
$rutaAzure = azure_storage_subir('ActasFirmadas/'.$fila['documento_no'].'.'.$extension, $contenido, $mime);
if ($rutaAzure === false) {
	responder(false, 'No se pudo subir el archivo. Avisa al equipo técnico.');
}

$stmt = $mysqli->prepare(
	"UPDATE repositorio_acuerdos
	 SET acta_firmada_azure_path = ?, acta_firmada_mime = ?, acta_firmada_subido_en = NOW(), acta_firmada_subido_por = ?, estado = 'firmado'
	 WHERE id = ?"
);
if (!$stmt) {
	responder(false, 'No se pudo guardar. Avisa al equipo técnico.');
}
$stmt->bind_param('ssii', $rutaAzure, $mime, $usuarioId, $acuerdoId);
$ok = $stmt->execute();
$stmt->close();

responder((bool) $ok, $ok ? 'Acta firmada subida correctamente.' : 'No se pudo guardar el archivo.');
?>
