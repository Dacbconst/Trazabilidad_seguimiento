<?php
// Paso 1 de la subida (2026-08-24, ver CLAUDE.md "Módulo Repositorios"): SOLO
// parsea el Excel y devuelve las filas leídas — no toca la base para nada,
// mismo espíritu que getters/previsualizar_acta_pdf.php. El usuario revisa/
// corrige en pantalla y recién con eso confirmado se llama a
// repositorio_guardar.php (paso 2, el que sí escribe).
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

// Mensaje específico por código de error de subida, no un genérico "falló la
// subida" — el pedido explícito fue que el sistema "se defienda solo": si
// alguien sube un archivo de 50MB o cierra la pestaña a mitad de subida,
// tiene que quedar claro POR QUÉ, no un error mudo. Códigos de $_FILES,
// ver https://www.php.net/manual/en/features.file-upload.errors.php.
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

// 2026-08-24: se probó agregar un límite propio de 10MB (mismo patrón que
// subir_acta_firmada.php) para que coincida con lo que ya prometía la
// pantalla — el usuario pidió explícitamente NO limitar la subida acá, solo
// mostrar una barra de carga mientras procesa un archivo pesado (ver
// components/repositorios/repositorios.php y assets/js/repositorios.js).
// Sigue aplicando el límite real del servidor
// (upload_max_filesize/post_max_size), eso no se puede evitar desde acá.

$rutaTmp = $_FILES['archivo']['tmp_name'];
$nombreArchivo = basename($_FILES['archivo']['name']);

if (strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION)) !== 'xlsx') {
	responder(false, 'El archivo tiene que ser .xlsx (Excel) — "'.$nombreArchivo.'" no lo es.');
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

responder(true, 'Archivo leído correctamente.', [
	'nombre_archivo' => $nombreArchivo,
	'filas' => $resultado['filas'],
]);
?>
