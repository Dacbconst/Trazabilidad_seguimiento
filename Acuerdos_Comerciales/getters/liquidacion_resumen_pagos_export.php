<?php
// Export a Excel (CSV con BOM UTF-8, Excel lo abre directo) del Resumen de
// Pagos de una importación — misma data y mismo cálculo que
// liquidacion_resumen_pagos.php, ver liquidacion_calcular_resumen_pagos() en
// includes/liquidacion_import.php. Se usa CSV en vez de un .xlsx real por la
// misma razón que includes/xlsx_reader.php es propio: sin Composer instalado
// en la máquina de desarrollo, y una dependencia pesada complicaría el
// deploy manual por FTP (ver CLAUDE.md).
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/liquidacion_import.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo 'No autorizado.';
	exit;
}

$importacionId = (int) ($_GET['importacion_id'] ?? 0);
$stmt = $mysqli->prepare('SELECT canal, anio, mes_inicio, mes_fin FROM repositorio_liquidacion_importaciones WHERE id = ?');
$stmt->bind_param('i', $importacionId);
$stmt->execute();
$importacion = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$importacion) {
	http_response_code(404);
	echo 'Importación no encontrada.';
	exit;
}

$filas = liquidacion_calcular_resumen_pagos($mysqli, $importacionId);

$nombreArchivo = 'ResumenDePagos_'.$importacion['canal'].'_'.$importacion['anio'].'_'.($importacion['mes_inicio'] + 1).'-'.($importacion['mes_fin'] + 1).'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$nombreArchivo.'"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['CEDI / Distribuidor', 'Cliente', 'Acta', 'Volumen', 'Visibilidad', 'Total', 'Estado'], ';');
foreach ($filas as $f) {
	fputcsv($out, [
		$f['cedi_o_distribuidor'],
		$f['cliente_o_nombre'],
		$f['documento_no'] ?? 'Sin vincular',
		number_format($f['volumen'], 2, '.', ''),
		number_format($f['visibilidad'], 2, '.', ''),
		number_format($f['total'], 2, '.', ''),
		$f['estado'] === 'ok' ? 'OK' : 'Revisar pendientes',
	], ';');
}
fclose($out);
exit;
?>
