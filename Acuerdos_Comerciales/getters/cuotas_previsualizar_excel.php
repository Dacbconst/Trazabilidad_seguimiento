<?php
// Paso 1 de la subida del Repositorio de Cuotas (2026-08-25, ver CLAUDE.md
// "Repositorio de Cuotas trimestrales + Actas precargadas") — mismo espíritu
// que getters/repositorio_previsualizar_excel.php: SOLO parsea el Excel y
// devuelve las filas leídas, no toca la base para nada. El trimestre se
// infiere del propio archivo (ver repositorio_parsear_cuotas()); el año NO
// viene en el Excel, así que lo elige el superdesarrollador en pantalla y se
// manda aparte para confirmarlo junto con el resto en el paso 2.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/repositorio_import.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

function responder($ok, $message, $extra = []) {
	echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
	exit;
}

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
	$codigo = $_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE;
	$mensajesError = [
		UPLOAD_ERR_INI_SIZE   => 'El archivo supera el tamaño máximo permitido por el servidor.',
		UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el tamaño máximo permitido (10 MB).',
		UPLOAD_ERR_PARTIAL    => 'El archivo se subió incompleto — probá de nuevo, puede haber sido un corte de conexión.',
		UPLOAD_ERR_NO_FILE    => 'No se eligió ningún archivo.',
		UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene dónde guardar archivos temporales — avisar al equipo técnico.',
		UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo en disco — avisar al equipo técnico.',
		UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP del servidor bloqueó la subida — avisar al equipo técnico.',
	];
	responder(false, $mensajesError[$codigo] ?? 'No se pudo recibir el archivo (error desconocido de subida).');
}
if (!xlsx_disponible()) {
	responder(false, 'El servidor no tiene la extensión "zip" de PHP habilitada — no se puede leer el Excel. Avisar al equipo técnico.');
}

$rutaTmp = $_FILES['archivo']['tmp_name'];
$nombreArchivo = basename($_FILES['archivo']['name']);

if (strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION)) !== 'xlsx') {
	responder(false, 'El archivo tiene que ser .xlsx (Excel) — "'.$nombreArchivo.'" no lo es.');
}

$resultado = repositorio_parsear_cuotas($rutaTmp);
if (isset($resultado['error'])) {
	responder(false, $resultado['error']);
}

responder(true, 'Archivo leído correctamente.', [
	'nombre_archivo' => $nombreArchivo,
	'filas'          => $resultado['filas'],
	'avisos'         => $resultado['avisos'],
	'trimestre'      => $resultado['trimestre'],
]);
?>
