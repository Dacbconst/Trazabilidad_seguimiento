<?php
// Vista previa del Acta SIN guardar nada en la base (2026-08-18, pedido
// explícito: la versión anterior guardaba un borrador en silencio detrás de
// escena, el usuario pidió sacar eso). Arma el $detalle directo desde lo que
// hay en pantalla ahora mismo (recolectarLineas() del JS, mandado por POST) y
// llama al MISMO renderizador (generar_acta_pdf_binario) que usa todo el
// resto del sistema — no hay una segunda plantilla de PDF que mantener
// sincronizada, solo se duplica acá el armado/normalización de las líneas
// (mucho más chico y menos riesgoso que duplicar el render de Dompdf).
//
// A propósito NO se valida pos_id contra repositorio_locales_supervisores_cliente
// como sí hace guardar_acuerdo.php — nada de esto se persiste, es solo una
// previsualización visual para el usuario que la pide, no tiene consecuencia
// real si el nombre mostrado no calzara exactamente.
// config.php (NO db_connect.php — a propósito, este endpoint nunca abre
// conexión a la base) define la constante SECURE que iniciar_sesion()
// necesita — sin esto sería un Error fatal en PHP 8, no un warning (ver
// includes/functions.php y el gotcha ya documentado en CLAUDE.md/memoria).
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/acta_pdf.php';
require_once __DIR__.'/../vendor/autoload.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo 'No autorizado.';
	exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
	http_response_code(400);
	echo 'Cuerpo de la petición inválido.';
	exit;
}

$posId        = trim($body['pos_id'] ?? '');
$distribuidor = trim($body['distribuidor_nombre'] ?? '');
$localidad    = trim($body['localidad'] ?? '');
$anio         = (int) ($body['anio'] ?? 0);
$mesInicio    = (int) ($body['mes_inicio'] ?? -1);
$mesFin       = (int) ($body['mes_fin'] ?? -1);
$lineas       = is_array($body['lineas'] ?? null) ? $body['lineas'] : [];

if ($posId === '' || $mesInicio < 0 || $mesInicio > 11 || $mesFin < $mesInicio || $mesFin > 11) {
	http_response_code(400);
	echo 'Faltan datos para armar la vista previa (Distribuidor/Periodo).';
	exit;
}

$cantidadMeses = $mesFin - $mesInicio + 1;

// Modo permisivo (sin abortar por validaciones que ya corrió registrar.js del
// lado del cliente antes de llegar acá) — esto es solo para PREVISUALIZAR,
// nada se persiste, así que no hace falta responder(false,...) como en
// guardar_acuerdo.php.
function pv_normalizar_valores(array $valores, $cantidadMeses, $mesInicio) {
	$out = [];
	for ($i = 0; $i < $cantidadMeses; $i++) {
		$out[(string) ($mesInicio + $i)] = round(max(0, (float) ($valores[$i] ?? 0)), 2);
	}
	return $out;
}

$lineasNormalizadas = ['meta_compra' => [], 'cabecera' => [], 'ruma' => [], 'percha' => []];

foreach (['meta_compra', 'cabecera'] as $tipo) {
	foreach (($lineas[$tipo] ?? []) as $fila) {
		$segmento = trim($fila['segmento'] ?? '');
		$categoria = trim($fila['categoria'] ?? '');
		$marca = trim($fila['marca'] ?? '');
		if ($segmento === '' || $categoria === '' || $marca === '') continue;
		$lineasNormalizadas[$tipo][] = [
			'segmento'            => $segmento,
			'categoria'           => $categoria,
			'marca'               => $marca,
			'rebate_pct'          => $tipo === 'meta_compra' ? max(0, (float) ($fila['rebate_pct'] ?? 0)) : 0,
			'cantidad_max_percha' => 0,
			'participacion'       => '',
			'precio_percha'       => 0,
			'valores_mensuales'   => pv_normalizar_valores(is_array($fila['valores'] ?? null) ? $fila['valores'] : [], $cantidadMeses, $mesInicio),
			'valor_mensual_unico' => 0,
		];
	}
}

foreach (($lineas['ruma'] ?? []) as $fila) {
	$segmento = trim($fila['segmento'] ?? '');
	$categoria = trim($fila['categoria'] ?? '');
	$marca = trim($fila['marca'] ?? '');
	if ($segmento === '' || $categoria === '' || $marca === '') continue;
	$lineasNormalizadas['ruma'][] = [
		'segmento'            => $segmento,
		'categoria'           => $categoria,
		'marca'               => $marca,
		'rebate_pct'          => 0,
		'cantidad_max_percha' => 0,
		'participacion'       => '',
		'precio_percha'       => 0,
		'valores_mensuales'   => [],
		'valor_mensual_unico' => round(max(0, (float) ($fila['valor_mensual_unico'] ?? 0)), 2),
	];
}

foreach (($lineas['percha'] ?? []) as $fila) {
	$marca = trim($fila['marca'] ?? '');
	if ($marca === '') continue;
	$lineasNormalizadas['percha'][] = [
		'segmento'            => '',
		'categoria'           => '',
		'marca'               => $marca,
		'rebate_pct'          => 0,
		'cantidad_max_percha' => max(0, min(5, (int) ($fila['cantidad_max_percha'] ?? 0))),
		'participacion'       => trim($fila['participacion'] ?? ''),
		'precio_percha'       => round((float) ($fila['precio_percha'] ?? 40), 2),
		'valores_mensuales'   => pv_normalizar_valores(is_array($fila['valores'] ?? null) ? $fila['valores'] : [], $cantidadMeses, $mesInicio),
		'valor_mensual_unico' => 0,
	];
}

$detalle = [
	'id'                  => 0,
	'documento_no'        => 'VISTA PREVIA',
	'pos_id'              => $posId,
	'anio'                => $anio,
	'mes_inicio'          => $mesInicio,
	'mes_fin'             => $mesFin,
	'estado'              => 'borrador',
	'fecha_generacion'    => date('Y-m-d'),
	'creado_por'          => $_SESSION['user_id'] ?? null,
	'distribuidor'        => $distribuidor !== '' ? $distribuidor : '—',
	'localidad'           => $localidad !== '' ? $localidad : '—',
	'ejecutivo_comercial' => $_SESSION['username'] ?? '',
	// Mandado por el cliente (catalogoDistribuidor.canal, ya cargado al inicio
	// de Registrar) — este endpoint nunca abre conexión a la base, así que no
	// puede derivarlo solo desde pos_id como sí hace obtener_acuerdo_detalle().
	'es_distribuidor'    => !empty($body['es_distribuidor']),
	'lineas'              => $lineasNormalizadas,
];

$pdfBinario = generar_acta_pdf_binario($detalle);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Vista_Previa_Acta.pdf"');
header('Content-Length: '.strlen($pdfBinario));
header('Cache-Control: no-cache, no-store, must-revalidate');
echo $pdfBinario;
?>
