<?php
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../includes/functions.php';

if (!ep_login_check()) {
	http_response_code(401);
	echo json_encode(['success' => false, 'error' => 'Tu sesión se cerró porque se inició sesión con esta cuenta en otro dispositivo, o expiró.', 'redirect' => 'login.php?error=sesion']);
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

// Cantidades de los formularios: enteros de 0 a 999.
function ep_entero($v) {
	return min(999, max(0, (int) $v));
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

$id = 'REG-' . date('Ymd-His', $ts) . '-' . mt_rand(100, 999);

$registro = [
	'id'              => $id,
	'tipo'            => $tipo,
	'actividad_label' => $actividadLabel,
	'actividad_badge' => $actividadBadge,
	'fecha_iso'       => $fechaIso,
	'fecha_texto'     => $fechaTexto,
	'hora'            => $hora,
	'duracion'        => '',
	'grupo_dia'       => $grupoDia,
	'estado'          => 'Aprobado',
	'estado_tipo'     => 'ok',
	'punto_venta'     => $puntoVenta,
	'cadena'          => $cadena,
	'ciudad'          => $ciudad,
	'canal'           => $canal,
	'promotor'        => $_SESSION['nombre'] ?? ucwords(str_replace('.', ' ', $usuario)),
	'promotor_usuario'=> $usuario,
	'promotor_avatar' => $avatar,
];

// Procesar según formulario
if ($tipo === 'activaciones' || $tipo === 'epson-day') {
	$nac = ep_entero($valores['nacional'] ?? 0);
	$cob = ep_entero($valores['coberturadas'] ?? 0);
	$vis = ep_entero($valores['visitaron'] ?? 0);
	$inte = ep_entero($valores['interactuaron'] ?? 0);
	$com = ep_entero($valores['compraron'] ?? 0);

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
			$totMods += ep_entero($m['cantidad'] ?? 0);
		}
		$mods = [];
		foreach ($valores['modelos'] as $m) {
			$nombreModelo = trim((string) ($m['modelo'] ?? ''));
			if ($nombreModelo === '') {
				continue;
			}
			$cant = ep_entero($m['cantidad'] ?? 0);
			$pct = $totMods > 0 ? round(($cant / $totMods) * 100, 1) . '%' : '0%';
			$mods[] = [
				'modelo'   => $nombreModelo,
				'cantidad' => $cant,
				'pct'      => $pct,
			];
		}
		$registro['modelos'] = $mods;
	} else {
		$registro['modelos'] = [];
	}

} elseif ($tipo === 'capacitaciones') {
	$asis = ep_entero($valores['asistentes'] ?? 0);
	$apro = ep_entero($valores['aprobados'] ?? 0);
	$horas = ep_entero($valores['horas'] ?? 0);
	$temas = trim($valores['temas'] ?? '');

	$registro['capacitacion'] = [
		'asistentes'     => $asis,
		'aprobados'      => $apro,
		'pct_aprobacion' => $asis > 0 ? round(($apro / $asis) * 100, 1) : 0,
		'horas'          => $horas,
		'temas'          => $temas,
	];
} elseif ($tipo === 'colocacion-pop') {
	$popLista = is_array($valores['pop_materiales'] ?? null) ? $valores['pop_materiales'] : [];
	$registro['pop_materiales'] = $popLista;
} elseif ($tipo === 'exhibiciones') {
	$muebles = ep_entero($valores['muebles'] ?? 0);
	$rumas = ep_entero($valores['rumas'] ?? 0);
	$cabeceras = ep_entero($valores['cabeceras'] ?? 0);
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
	$vis = ep_entero($valores['visitaron'] ?? 0);
	$inte = ep_entero($valores['interactuaron'] ?? 0);
	$com = ep_entero($valores['compraron'] ?? 0);

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
		$registro['modelos'] = [];
	}
}

// Evidencias fotográficas requeridas según tipo de actividad
$reqFotos = ep_fotos_requeridas($tipo);
$fotosFinal = [];
$fotosSubidas = is_array($payload['fotos'] ?? null) ? $payload['fotos'] : [];
$sinSimbolos = function ($v) { return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $v)); };
$usuarioLimpio = $sinSimbolos($_SESSION['usuario']);
$faltantes = 0;
foreach ($reqFotos as $rf) {
	$ruta = (string) ($fotosSubidas[$rf['id']] ?? '');
	// Solo vale una ruta relativa "Carpeta/ddmmaaaaHHMMSS+USUARIO+FOTO.ext" que haya subido este mismo usuario para esta casilla.
	$esperado = $usuarioLimpio . $sinSimbolos($rf['id']);
	if ($ruta !== '' && !preg_match('#^[A-Za-z]+/\d{14}' . preg_quote($esperado, '#') . '\.(jpg|png|webp)$#', $ruta)) {
		$ruta = '';
	}
	if ($ruta === '') {
		$faltantes++;
	}
	$fotosFinal[] = [
		'id'     => $rf['id'],
		'label'  => $rf['label'],
		'hora'   => $hora,
		'estado' => 'Verificada',
		'ruta'   => $ruta,
		'url'    => $ruta !== '' ? 'https://luckyecuadorweb.blob.core.windows.net/app/AppEpson/EpsonReport/'.$ruta : '',
	];
}
// Todas las fotos son obligatorias.
if ($faltantes > 0) {
	http_response_code(422);
	echo json_encode(['success' => false, 'error' => 'Faltan '.$faltantes.' foto(s) por subir. Todas las fotos son obligatorias.']);
	exit;
}
$registro['fotos'] = $fotosFinal;

// Comentarios
$comentarioTexto = trim($valores['comentarios'] ?? '');
if ($comentarioTexto !== '') {
	$lineas = array_filter(array_map('trim', explode("\n", $comentarioTexto)));
	$registro['comentarios'] = !empty($lineas) ? array_values($lineas) : [$comentarioTexto];
} else {
	$registro['comentarios'] = [];
}

$ok = ep_guardar_nuevo_registro($registro, (int) $_SESSION['usuario_id']);

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
		'error'   => 'No se pudo guardar el registro. Intenta de nuevo.',
	]);
}
