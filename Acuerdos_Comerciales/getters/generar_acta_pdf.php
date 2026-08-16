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

$cabecera = null;
if ($acuerdoId > 0) {
	$stmt = $mysqli->prepare(
		'SELECT documento_no, creado_por, pdf_documento FROM repositorio_acuerdos WHERE id = ? LIMIT 1'
	);
	if ($stmt) {
		$stmt->bind_param('i', $acuerdoId);
		$stmt->execute();
		$cabecera = $stmt->get_result()->fetch_assoc();
		$stmt->close();
	}
}

// Mismo criterio de scoping que Historial y "Mis Borradores" (creado_por):
// nadie puede ver el Acta de un acuerdo ajeno adivinando el id por la URL.
// 404 (no 403) a propósito en ambos casos, para no confirmar si el id existe.
$usuarioSesion = $_SESSION['user_id'] ?? null;
if (!$cabecera || (int) $cabecera['creado_por'] !== (int) $usuarioSesion) {
	http_response_code(404);
	echo 'Acuerdo no encontrado.';
	exit;
}

// Snapshot guardado en guardar_acuerdo.php al momento de "Generar Acta" — es
// el caso normal, y evita re-renderizar con Dompdf en cada vista. Solo cae al
// render en vivo si todavía no hay snapshot (acuerdos generados antes de que
// existiera esto), y de paso lo deja guardado para la próxima vez.
if ($cabecera['pdf_documento'] !== null) {
	$pdfBinario = $cabecera['pdf_documento'];
} else {
	$detalle = obtener_acuerdo_detalle($mysqli, $acuerdoId);
	if (!$detalle) {
		http_response_code(404);
		echo 'Acuerdo no encontrado.';
		exit;
	}
	$pdfBinario = generar_acta_pdf_binario($detalle);

	$tamano = strlen($pdfBinario);
	$stmtPdf = $mysqli->prepare(
		'UPDATE repositorio_acuerdos SET pdf_documento = ?, pdf_generado_en = NOW(), pdf_tamano_bytes = ? WHERE id = ?'
	);
	if ($stmtPdf) {
		$stmtPdf->bind_param('sii', $pdfBinario, $tamano, $acuerdoId);
		$stmtPdf->execute();
		$stmtPdf->close();
	}
}

// El acuerdo puede cambiar (regenerar acta) sin que cambie ?id=X, así que el
// navegador no debe reusar una versión vieja del PDF con esa misma URL.
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Acta_'.$cabecera['documento_no'].'.pdf"');
header('Content-Length: '.strlen($pdfBinario));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo $pdfBinario;
?>
