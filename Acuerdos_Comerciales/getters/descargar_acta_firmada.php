<?php
// Sirve el archivo del Acta firmada ya subido (foto o PDF) — mismo criterio
// de propiedad que el resto de Historial, `inline` para que el navegador lo
// muestre directo (imagen o PDF) en vez de forzar descarga.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo 'No autorizado.';
	exit;
}

$acuerdoId = (int) ($_GET['id'] ?? 0);
$usuarioId = $_SESSION['user_id'] ?? null;

if ($acuerdoId <= 0) {
	http_response_code(400);
	echo 'Acuerdo inválido.';
	exit;
}

$stmt = $mysqli->prepare(
	"SELECT documento_no, creado_por, acta_firmada_archivo, acta_firmada_mime
	 FROM repositorio_acuerdos WHERE id = ? LIMIT 1"
);
if (!$stmt) {
	http_response_code(500);
	echo 'Falta correr el ALTER TABLE de acta_firmada_archivo (ver CLAUDE.md).';
	exit;
}
$stmt->bind_param('i', $acuerdoId);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila || (int) $fila['creado_por'] !== (int) $usuarioId) {
	http_response_code(404);
	echo 'Acuerdo no encontrado.';
	exit;
}
if ($fila['acta_firmada_archivo'] === null) {
	http_response_code(404);
	echo 'Este acuerdo todavía no tiene un Acta firmada subida.';
	exit;
}

$extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
$ext = $extensiones[$fila['acta_firmada_mime']] ?? 'bin';
$nombreArchivo = 'Acta_Firmada_'.$fila['documento_no'].'.'.$ext;

header('Content-Type: '.$fila['acta_firmada_mime']);
header('Content-Disposition: inline; filename="'.$nombreArchivo.'"');
header('Content-Length: '.strlen($fila['acta_firmada_archivo']));
echo $fila['acta_firmada_archivo'];
?>
