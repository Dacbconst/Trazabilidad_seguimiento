<?php
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'error' => 'Sesión expirada']);
	exit;
}

require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/fotos_datos.php';
require_once __DIR__.'/../includes/registros_datos.php';

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
	$payload = $_POST;
}

$tipo = trim($payload['tipo'] ?? 'activaciones');
$actividadLabel = trim($payload['actividad_label'] ?? ucfirst($tipo));
$actividadBadge = trim($payload['actividad_badge'] ?? 'Registro de Campo');
$puntoVenta = trim($payload['punto_venta'] ?? 'SUKASA - MALL DEL SOL');
$cadena = trim($payload['cadena'] ?? 'Sukasa');
$ciudad = trim($payload['ciudad'] ?? 'GUAYAQUIL');
$canal = trim($payload['canal'] ?? 'RETAIL');
$valores = is_array($payload['valores'] ?? null) ? $payload['valores'] : [];

$usuario = $_SESSION['usuario'];
$partes = explode('.', $usuario);
$avatar = '';
foreach ($partes as $p) {
	$avatar .= strtoupper(substr($p, 0, 1));
}
if ($avatar === '') {
	$avatar = strtoupper(substr($usuario, 0, 2));
}

// Fechas y timestamps locales Ecuador (GMT-5)
$diasSemana = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
$meses = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril','May'=>'Mayo','June'=>'Junio','July'=>'Julio','August'=>'Agosto','September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];

$ts = time();
$diaNom = $diasSemana[date('l', $ts)] ?? date('l', $ts);
$mesNom = $meses[date('F', $ts)] ?? date('F', $ts);

$fechaIso = date('Y-m-d', $ts);
$fechaTexto = $diaNom . ', ' . date('j', $ts) . ' de ' . $mesNom . ' ' . date('Y', $ts);
$hora = date('H:i', $ts);
$grupoDia = 'Hoy — ' . $diaNom . ', ' . date('j', $ts) . ' de ' . $mesNom;

$id = 'REG-' . date('Y-md', $ts) . '-' . str_pad((string) mt_rand(1, 99), 2, '0', STR_PAD_LEFT);

$registro = [
	'id'              => $id,
	'tipo'            => $tipo,
	'actividad_label' => $actividadLabel,
	'actividad_badge' => $actividadBadge,
	'fecha_iso'       => $fechaIso,
	'fecha_texto'     => $fechaTexto,
	'hora'            => $hora,
	'duracion'        => '1h 30m',
	'grupo_dia'       => $grupoDia,
	'estado'          => 'Aprobado',
	'estado_tipo'     => 'ok',
	'punto_venta'     => $puntoVenta,
	'cadena'          => $cadena,
	'ciudad'          => $ciudad,
	'canal'           => $canal,
	'promotor'        => ucwords(str_replace('.', ' ', $usuario)),
	'promotor_usuario'=> $usuario,
	'promotor_avatar' => $avatar,
];

// Procesar según formulario
if ($tipo === 'activaciones' || $tipo === 'epson-day') {
	$nac = max(0, (int) ($valores['nacional'] ?? 0));
	$cob = max(0, (int) ($valores['coberturadas'] ?? 0));
	$vis = max(0, (int) ($valores['visitaron'] ?? 0));
	$inte = max(0, (int) ($valores['interactuaron'] ?? 0));
	$com = max(0, (int) ($valores['compraron'] ?? 0));

	$registro['cobertura'] = [
		'nacional'     => $nac,
		'coberturadas' => $cob,
		'pct'          => $nac > 0 ? round(($cob / $nac) * 100, 1) : 0,
	];
	$registro['embudo'] = [
		'visitaron'             => $vis,
		'interactuaron'         => $inte,
		'compraron'             => $com,
		'tasa_interaccion_pct'  => $vis > 0 ? round(($inte / $vis) * 100, 1) : 0,
		'tasa_conversion_pct'   => $inte > 0 ? round(($com / $inte) * 100, 1) : 0,
		'conversion_global_pct' => $vis > 0 ? round(($com / $vis) * 100, 1) : 0,
	];

	if (!empty($valores['modelos']) && is_array($valores['modelos'])) {
		$totMods = 0;
		foreach ($valores['modelos'] as $m) {
			$totMods += (int) ($m['cantidad'] ?? 0);
		}
		$mods = [];
		foreach ($valores['modelos'] as $m) {
			$cant = (int) ($m['cantidad'] ?? 0);
			$pct = $totMods > 0 ? round(($cant / $totMods) * 100, 1) . '%' : '0%';
			$mods[] = [
				'modelo'   => $m['modelo'] ?? 'EcoTank L3250',
				'cantidad' => $cant,
				'pct'      => $pct,
			];
		}
		$registro['modelos'] = $mods;
	} else {
		$registro['modelos'] = [
			['modelo' => 'EcoTank L3250', 'cantidad' => max(1, $com), 'pct' => '100%']
		];
	}

	if ($tipo === 'activaciones') {
		$prog = max(0, (int) ($valores['programadas'] ?? 10));
		$real = max(0, (int) ($valores['realizadas'] ?? 8));
		$registro['cumplimiento'] = [
			'programadas' => $prog,
			'realizadas'  => $real,
			'pct'         => $prog > 0 ? round(($real / $prog) * 100, 1) : 0,
		];
	}
} elseif ($tipo === 'capacitaciones') {
	$asis = max(0, (int) ($valores['asistentes'] ?? 0));
	$apro = max(0, (int) ($valores['aprobados'] ?? 0));
	$horas = max(1, (int) ($valores['horas'] ?? 2));
	$temas = trim($valores['temas'] ?? 'Portafolio EcoTank Serie L y consumibles originales');

	$registro['capacitacion'] = [
		'asistentes'     => $asis,
		'aprobados'      => $apro,
		'pct_aprobacion' => $asis > 0 ? round(($apro / $asis) * 100, 1) : 0,
		'horas'          => $horas,
		'temas'          => $temas,
	];
} elseif ($tipo === 'colocacion-pop') {
	$popLista = is_array($valores['pop_materiales'] ?? null) ? $valores['pop_materiales'] : [
		['material' => 'Vibrines (Retail)', 'bodega' => 20, 'canales' => 5, 'retail' => 12, 'disponible' => 3],
		['material' => 'Banners Roll Up (1.80m)', 'bodega' => 5, 'canales' => 1, 'retail' => 3, 'disponible' => 1],
		['material' => 'Glorificadores Acrílico L3250', 'bodega' => 8, 'canales' => 2, 'retail' => 5, 'disponible' => 1],
	];
	$registro['pop_materiales'] = $popLista;
} elseif ($tipo === 'exhibiciones') {
	$muebles = max(0, (int) ($valores['muebles'] ?? 0));
	$rumas = max(0, (int) ($valores['rumas'] ?? 0));
	$cabeceras = max(0, (int) ($valores['cabeceras'] ?? 0));
	$tot = $muebles + $rumas + $cabeceras;

	$registro['exhibiciones'] = [
		'muebles'       => $muebles,
		'rumas'         => $rumas,
		'cabeceras'     => $cabeceras,
		'total'         => $tot,
		'muebles_pct'   => $tot > 0 ? round(($muebles / $tot) * 100, 1) . '%' : '0%',
		'rumas_pct'     => $tot > 0 ? round(($rumas / $tot) * 100, 1) . '%' : '0%',
		'cabeceras_pct' => $tot > 0 ? round(($cabeceras / $tot) * 100, 1) . '%' : '0%',
	];
} elseif ($tipo === 'evento-ferias') {
	$vis = max(0, (int) ($valores['visitaron'] ?? 0));
	$inte = max(0, (int) ($valores['interactuaron'] ?? 0));
	$com = max(0, (int) ($valores['compraron'] ?? 0));

	$registro['feria'] = [
		'visitaron'             => $vis,
		'interactuaron'         => $inte,
		'compraron'             => $com,
		'tasa_interaccion_pct'  => $vis > 0 ? round(($inte / $vis) * 100, 1) : 0,
		'tasa_conversion_pct'   => $inte > 0 ? round(($com / $inte) * 100, 1) : 0,
		'conversion_global_pct' => $vis > 0 ? round(($com / $vis) * 100, 1) : 0,
	];
	if (!empty($valores['modelos']) && is_array($valores['modelos'])) {
		$registro['modelos'] = $valores['modelos'];
	} else {
		$registro['modelos'] = [
			['modelo' => 'EcoTank L3250', 'cantidad' => max(1, $com), 'pct' => '100%']
		];
	}
}

// Evidencias fotográficas requeridas según tipo de actividad
$reqFotos = ep_fotos_requeridas($tipo);
$fotosFinal = [];
foreach ($reqFotos as $rf) {
	$fotosFinal[] = [
		'id'     => $rf['id'],
		'label'  => $rf['label'],
		'hora'   => $hora,
		'estado' => 'Verificada',
	];
}
$registro['fotos'] = $fotosFinal;

// Comentarios
$comentarioTexto = trim($valores['comentarios'] ?? '');
if ($comentarioTexto !== '') {
	$lineas = array_filter(array_map('trim', explode("\n", $comentarioTexto)));
	$registro['comentarios'] = !empty($lineas) ? array_values($lineas) : [$comentarioTexto];
} else {
	$registro['comentarios'] = [
		'Reporte registrado exitosamente desde el punto de venta conforme a los requerimientos operativos.'
	];
}

$ok = ep_guardar_nuevo_registro($registro);

if ($ok) {
	echo json_encode([
		'success'  => true,
		'id'       => $id,
		'mensaje'  => 'Registro '.$id.' guardado exitosamente en el sistema.',
		'redirect' => 'index.php?vista=historial',
	]);
} else {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'error'   => 'No se pudo guardar el registro en el archivo de datos.',
	]);
}
