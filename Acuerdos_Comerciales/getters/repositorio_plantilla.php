<?php
// Plantilla .xlsx en blanco con el formato esperado por repositorio_parsear_rebate()/_participacion().
// require config.php (no db_connect.php completo, no hace falta conexión a
// la base para esto) — sin esto, iniciar_sesion() usa la constante SECURE
// sin definir y PHP 8.2 tira un error fatal (se ve como "404 Not Found" en
// nginx, mismo patrón ya documentado en este archivo para otro incidente).
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../includes/functions.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo 'No autorizado.';
	exit;
}

$tipo = $_GET['tipo'] ?? '';
if (!in_array($tipo, ['rebate', 'participacion'], true)) {
	http_response_code(400);
	echo 'Tipo de repositorio inválido.';
	exit;
}

require_once __DIR__.'/../includes/xlsx_writer.php';

$wb = new XlsxWriter();

if ($tipo === 'rebate') {
	$hoja = $wb->agregarHoja('REBATE');
	$cols = ['CIUDAD', 'CANAL', 'CATEGORIA', 'SUBCATEGORIA', 'MARCA', 'REBATE'];
	foreach ($cols as $i => $titulo) $wb->celda($hoja, 1, $i + 1, $titulo, true);
	$wb->celda($hoja, 2, 1, 'QUITO');
	$wb->celda($hoja, 2, 2, 'DIRECTA');
	$wb->celda($hoja, 2, 3, 'CREMA');
	$wb->celda($hoja, 2, 4, 'LAVAVAJILLAS');
	$wb->celda($hoja, 2, 5, 'EJEMPLO');
	$wb->celda($hoja, 2, 6, 0.04, false, 'pct');
	$nombreBase = 'Formato_Rebate';
} else {
	$hoja = $wb->agregarHoja('PARTICIPACION PERCHA');
	$cols = ['CIUDAD', 'CATEGORIA', 'SUBCATEGORIA', 'MARCA', '%'];
	foreach ($cols as $i => $titulo) $wb->celda($hoja, 1, $i + 1, $titulo, true);
	$wb->celda($hoja, 2, 1, 'TODAS');
	$wb->celda($hoja, 2, 2, 'CREMA');
	$wb->celda($hoja, 2, 3, 'LAVAVAJILLAS');
	$wb->celda($hoja, 2, 4, 'EJEMPLO');
	$wb->celda($hoja, 2, 5, 0.5, false, 'pct');
	$nombreBase = 'Formato_Participacion_Percha';
}

$bin = $wb->generar();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$nombreBase.'.xlsx"');
header('Content-Length: '.strlen($bin));
echo $bin;
