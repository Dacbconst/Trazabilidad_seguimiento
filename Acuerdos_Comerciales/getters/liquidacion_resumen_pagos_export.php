<?php
// Export a Excel (CSV con BOM UTF-8, Excel lo abre directo) del Resumen de
// Pagos UNIFICADO por canal (2026-08-20, antes exportaba una sola
// importación — mismo cambio que liquidacion_resumen_pagos.php, ver
// liquidacion_resumen_pagos_unificado() en includes/liquidacion_import.php).
// Se usa CSV en vez de un .xlsx real por la misma razón que
// includes/xlsx_reader.php es propio: sin Composer instalado en la máquina
// de desarrollo, y una dependencia pesada complicaría el deploy manual por
// FTP (ver CLAUDE.md).
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/liquidacion_import.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo 'No autorizado.';
	exit;
}

$canal = $_GET['canal'] ?? '';
if (!in_array($canal, ['directa', 'distribuidor'], true)) {
	http_response_code(400);
	echo 'Canal inválido.';
	exit;
}
$trimestre = (int) ($_GET['trimestre'] ?? 0);
$anio = (int) ($_GET['anio'] ?? 0);

$resultado = liquidacion_resumen_pagos_unificado($mysqli, $canal, $trimestre, $anio);
$filas = $resultado['filas'];

$mesesCorto = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
function liq_periodo_texto($mesesCorto, $mesInicio, $mesFin, $anio) {
	$rango = $mesInicio === $mesFin ? $mesesCorto[$mesInicio] : $mesesCorto[$mesInicio].'-'.$mesesCorto[$mesFin];
	return $rango.' '.$anio;
}

$nombreArchivo = 'ResumenDePagos_'.$canal.($anio ? '_'.$anio : '').'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$nombreArchivo.'"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['CEDI / Distribuidor', 'Cliente', 'Período', 'Acta', 'Volumen', 'Visibilidad', 'Total', 'Estado'], ';');
foreach ($filas as $f) {
	fputcsv($out, [
		$f['cedi_o_distribuidor'],
		$f['cliente_o_nombre'],
		liq_periodo_texto($mesesCorto, $f['mes_inicio'], $f['mes_fin'], $f['anio']),
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
