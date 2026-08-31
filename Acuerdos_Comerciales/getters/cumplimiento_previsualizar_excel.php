<?php
// Paso 1 de la subida de Cumplimiento de Cuota (2026-08-30, ver CLAUDE.md
// "Módulo Cumplimiento de Cuota") — mismo espíritu que
// getters/cuotas_previsualizar_excel.php: SOLO parsea el Excel y devuelve
// las filas leídas, no toca la base para nada. El trimestre se infiere del
// propio archivo; el año NO viene en el Excel, lo elige el
// superdesarrollador en pantalla y se manda junto con el resto en el paso 2.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/xlsx_reader.php';
require_once __DIR__.'/../includes/repositorio_import.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

// Ver nota completa en cumplimiento_guardar.php: bufferea cualquier
// warning/notice de PHP para que nunca se mezcle con el JSON de respuesta
// (rompía el fetch().then(r => r.json()) del lado del cliente, mostrando
// "Error de conexión" en vez del error real).
ob_start();

function responder($ok, $message, $extra = []) {
	while (ob_get_level() > 0) { ob_end_clean(); }
	echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
	exit;
}
set_exception_handler(function ($e) { responder(false, 'No se pudo leer el archivo: '.$e->getMessage()); });

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
	$codigo = $_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE;
	$mensajesError = [
		UPLOAD_ERR_INI_SIZE   => 'El archivo supera el tamaño máximo permitido por el servidor.',
		UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el tamaño máximo permitido.',
		UPLOAD_ERR_PARTIAL    => 'El archivo se subió incompleto. Probá de nuevo.',
		UPLOAD_ERR_NO_FILE    => 'No se eligió ningún archivo.',
		UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene dónde guardar archivos temporales. Avisá al equipo técnico.',
		UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo en disco. Avisá al equipo técnico.',
		UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP del servidor bloqueó la subida. Avisá al equipo técnico.',
	];
	responder(false, $mensajesError[$codigo] ?? 'No se pudo recibir el archivo (error desconocido de subida).');
}
if (!xlsx_disponible()) {
	responder(false, 'No se pudo leer el archivo. Avisá al equipo técnico.');
}

$rutaTmp = $_FILES['archivo']['tmp_name'];
$nombreArchivo = basename($_FILES['archivo']['name']);

if (strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION)) !== 'xlsx') {
	responder(false, 'El archivo tiene que ser .xlsx (Excel). "'.$nombreArchivo.'" no lo es.');
}

$resultado = repositorio_parsear_cumplimiento_cuota($rutaTmp);
if (isset($resultado['error'])) {
	responder(false, $resultado['error']);
}

responder(true, 'Archivo leído correctamente.', [
	'nombre_archivo' => $nombreArchivo,
	'filas'          => $resultado['filas'],
	'trimestre'      => $resultado['trimestre'],
]);
?>
