<?php
// Sirve el archivo del Acta firmada con `inline` para que el navegador lo muestre directo en vez de forzar descarga.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/azure_storage.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();

// Sin sesión, manda al login en vez de mostrar un texto plano (2026-09-24, pedido explícito).
if (!login_check()) {
	header('Location: ../login.php');
	exit;
}
if (!rolPermitido(['desarrollador', 'superdesarrollador'])) {
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
	"SELECT documento_no, creado_por, acta_firmada_azure_path, acta_firmada_mime
	 FROM repositorio_acuerdos WHERE id = ? LIMIT 1"
);
if (!$stmt) {
	http_response_code(500);
	echo 'Avisa al equipo técnico.';
	exit;
}
$stmt->bind_param('i', $acuerdoId);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

// El superdesarrollador puede ver la firma de cualquier Acta desde Seguimiento de Equipo — mismo criterio ya usado en generar_acta_pdf.php.
$puedeVerCualquiera = ($_SESSION['rol'] ?? '') === 'superdesarrollador';
if (!$fila || (!$puedeVerCualquiera && (int) $fila['creado_por'] !== (int) $usuarioId)) {
	http_response_code(404);
	echo 'Acuerdo no encontrado.';
	exit;
}
if ($fila['acta_firmada_azure_path'] === null) {
	http_response_code(404);
	echo 'Este acuerdo todavía no tiene un Acta firmada subida.';
	exit;
}

$extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
$ext = $extensiones[$fila['acta_firmada_mime']] ?? 'bin';
$nombreArchivo = 'Acta_Firmada_'.$fila['documento_no'].'.'.$ext;

// Por defecto sigue crudo (lo usan las vistas previas incrustadas con <img>/PDF.js). Con ?ver=1 (solo al abrir en pestaña nueva), envuelve en HTML con <title> real — antes la pestaña mostraba "descargar_acta_firmada.php" en vez del número de Acta (2026-09-24, pedido explícito).
if (($_GET['ver'] ?? '') === '1') {
	$tag = $fila['acta_firmada_mime'] === 'application/pdf'
		? '<embed src="descargar_acta_firmada.php?id='.$acuerdoId.'" type="application/pdf">'
		: '<img src="descargar_acta_firmada.php?id='.$acuerdoId.'" alt="Acta firmada">';
	header('Content-Type: text/html; charset=utf-8');
	echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.htmlspecialchars('Acta '.$fila['documento_no'], ENT_QUOTES, 'UTF-8').'</title>'
		.'<style>html,body{margin:0;height:100%;background:#525659;}embed{width:100%;height:100%;border:0;}img{display:block;max-width:100%;max-height:100vh;margin:0 auto;}</style></head>'
		.'<body>'.$tag.'</body></html>';
	exit;
}

$contenido = azure_storage_descargar($fila['acta_firmada_azure_path']);
if ($contenido === false) {
	http_response_code(500);
	echo 'No se pudo descargar el archivo. Avisa al equipo técnico.';
	exit;
}

header('Content-Type: '.$fila['acta_firmada_mime']);
header('Content-Disposition: inline; filename="'.$nombreArchivo.'"');
header('Content-Length: '.strlen($contenido));
echo $contenido;
?>
