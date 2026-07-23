<?php
// Genera el PDF del Acta con Dompdf (servidor) — a diferencia de window.print()
// en el navegador, no depende del encabezado/pie que agrega Chrome ni de que
// el usuario lo desactive, y el @page CSS de includes/acta_pdf.php controla
// el tamaño/margen de la hoja de forma exacta.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/acta_pdf.php';
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../vendor/autoload.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['admin', 'desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo 'No autorizado.';
	exit;
}

$acuerdoId = (int) ($_GET['id'] ?? 0);
$detalle   = $acuerdoId > 0 ? obtener_acuerdo_detalle($mysqli, $acuerdoId) : null;

if (!$detalle) {
	http_response_code(404);
	echo 'Acuerdo no encontrado.';
	exit;
}

// Un solo medidor de texto para todos los intentos de escala (no depende de
// $escala, es puro font-metrics, recrearlo en cada vuelta sería desperdicio).
$medirTexto = crear_medidor_texto();

function renderizar_dompdf($detalle, $escala, $medirTexto) {
	$options = new \Dompdf\Options();
	$options->set('isRemoteEnabled', false);
	$dompdf = new \Dompdf\Dompdf($options);
	$dompdf->loadHtml(generar_acta_html($detalle, $escala, $medirTexto));
	$dompdf->setPaper('A4', 'portrait');
	$dompdf->render();
	return $dompdf;
}

// Prueba a tamaño normal (escala 1) y solo si no entra en 1 hoja va reduciendo
// —igual que imprimirActa() en registrar.js, nunca achica más de lo necesario.
$escala = 1.0;
$dompdf = renderizar_dompdf($detalle, $escala, $medirTexto);
while ($dompdf->getCanvas()->get_page_count() > 1 && $escala > 0.4) {
	$escala -= 0.08;
	$dompdf = renderizar_dompdf($detalle, $escala, $medirTexto);
}

// El acuerdo puede cambiar (regenerar acta) sin que cambie ?id=X, así que el
// navegador no debe reusar una versión vieja del PDF con esa misma URL.
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
$dompdf->stream('Acta_'.$detalle['documento_no'].'.pdf', ['Attachment' => false]);
?>
