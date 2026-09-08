<?php
// Genera el PDF con Dompdf en servidor: a diferencia de window.print(), no depende del header/footer de Chrome ni de que el usuario lo desactive.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/acta_pdf.php';
require_once __DIR__.'/../includes/azure_storage.php';
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../vendor/autoload.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo 'No autorizado.';
	exit;
}

$acuerdoId = (int) ($_GET['id'] ?? 0);

$cabecera = null;
if ($acuerdoId > 0) {
	$stmt = $mysqli->prepare(
		'SELECT documento_no, creado_por, pdf_azure_path FROM repositorio_acuerdos WHERE id = ? LIMIT 1'
	);
	if ($stmt) {
		$stmt->bind_param('i', $acuerdoId);
		$stmt->execute();
		$cabecera = $stmt->get_result()->fetch_assoc();
		$stmt->close();
	}
}

// Scoping por creado_por, igual que Historial: 404 (no 403) a propósito para no confirmar si el id existe.
// Excepción: superdesarrollador puede ver/descargar el PDF de cualquier Acta (ve ambos canales combinados en Historial).
$usuarioSesion = $_SESSION['user_id'] ?? null;
$puedeVerCualquiera = ($_SESSION['rol'] ?? '') === 'superdesarrollador';
if (!$cabecera || (!$puedeVerCualquiera && (int) $cabecera['creado_por'] !== (int) $usuarioSesion)) {
	http_response_code(404);
	echo 'Acuerdo no encontrado.';
	exit;
}

// Caso normal: baja el snapshot ya generado de Azure Blob Storage en vez de re-renderizar con Dompdf.
// Cae al render en vivo solo si no hay snapshot todavía (y de paso lo deja guardado en Azure para la próxima).
$pdfBinario = $cabecera['pdf_azure_path'] ? azure_storage_descargar($cabecera['pdf_azure_path']) : false;
if ($pdfBinario === false) {
	$detalle = obtener_acuerdo_detalle($mysqli, $acuerdoId);
	if (!$detalle) {
		http_response_code(404);
		echo 'Acuerdo no encontrado.';
		exit;
	}
	$pdfBinario = generar_acta_pdf_binario($detalle);

	$tamano = strlen($pdfBinario);
	$rutaAzure = azure_storage_subir('Actas/'.$cabecera['documento_no'].'.pdf', $pdfBinario, 'application/pdf');
	if ($rutaAzure !== false) {
		$stmtPdf = $mysqli->prepare(
			'UPDATE repositorio_acuerdos SET pdf_azure_path = ?, pdf_generado_en = NOW(), pdf_tamano_bytes = ? WHERE id = ?'
		);
		if ($stmtPdf) {
			$stmtPdf->bind_param('sii', $rutaAzure, $tamano, $acuerdoId);
			$stmtPdf->execute();
			$stmtPdf->close();
		}
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
