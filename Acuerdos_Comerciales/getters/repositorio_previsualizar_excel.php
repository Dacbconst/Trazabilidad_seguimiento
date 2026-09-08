<?php
// Paso 1: solo parsea el Excel y devuelve las filas, no toca la base. Confirmado en pantalla, recién ahí se llama a repositorio_guardar.php.
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

$tipo = $_POST['tipo'] ?? '';
if (!in_array($tipo, ['rebate', 'participacion'], true)) {
	responder(false, 'Tipo de repositorio inválido.');
}

// Mensaje específico por código de error de $_FILES, no un genérico "falló la subida" — que quede claro por qué (archivo pesado, corte de conexión, etc).
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
	$codigo = $_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE;
	$mensajesError = [
		UPLOAD_ERR_INI_SIZE   => 'El archivo supera el tamaño máximo permitido por el servidor.',
		UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el tamaño máximo permitido (10 MB).',
		UPLOAD_ERR_PARTIAL    => 'El archivo se subió incompleto. Probá de nuevo, puede haber sido un corte de conexión.',
		UPLOAD_ERR_NO_FILE    => 'No se eligió ningún archivo.',
		UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene dónde guardar archivos temporales. Avisá al equipo técnico.',
		UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo en disco. Avisá al equipo técnico.',
		UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP del servidor bloqueó la subida. Avisá al equipo técnico.',
	];
	responder(false, $mensajesError[$codigo] ?? 'No se pudo recibir el archivo (error desconocido de subida).');
}
if (!xlsx_disponible()) {
	responder(false, 'No se pudo leer el archivo. Avisa al equipo técnico.');
}

// Sin límite propio a propósito: solo se muestra una barra de carga mientras procesa. Sigue aplicando el límite real del servidor (upload_max_filesize/post_max_size).

$rutaTmp = $_FILES['archivo']['tmp_name'];
$nombreArchivo = basename($_FILES['archivo']['name']);

if (strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION)) !== 'xlsx') {
	responder(false, 'El archivo tiene que ser .xlsx (Excel). "'.$nombreArchivo.'" no lo es.');
}

$resultado = $tipo === 'rebate'
	? repositorio_parsear_rebate($rutaTmp)
	: repositorio_parsear_participacion($rutaTmp);

if (isset($resultado['error'])) {
	responder(false, $resultado['error']);
}
if (!$resultado['filas']) {
	responder(false, 'El archivo no tiene filas de datos reconocibles.');
}

// $resultado['aviso'] (solo Rebate): archivo leído pero falta la columna Segmento, se muestra como aviso no bloqueante en la previsualización.
responder(true, 'Archivo leído correctamente.', [
	'nombre_archivo' => $nombreArchivo,
	'filas' => $resultado['filas'],
	'avisos' => !empty($resultado['aviso']) ? [$resultado['aviso']] : [],
]);
?>
