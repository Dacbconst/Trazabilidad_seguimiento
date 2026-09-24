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

// La página debe pertenecer a la cuenta con sesión abierta: si en este navegador se entró con otra cuenta, no se guarda nada.
if ((string) ($payload['usuario_ref'] ?? '') !== (string) $_SESSION['usuario_id']) {
	http_response_code(409);
	echo json_encode(['success' => false, 'error' => 'En este navegador se inició sesión con otra cuenta. Recarga la página; el registro no se guardó.']);
	exit;
}

// Cantidades de los formularios: enteros de 0 a 999.
function ep_entero($v) {
	return min(999, max(0, (int) $v));
}

$tipo = trim($payload['tipo'] ?? 'activaciones');
$actividadLabel = trim($payload['actividad_label'] ?? ucfirst($tipo));
$actividadBadge = trim($payload['actividad_badge'] ?? 'Registro de Campo');
// El punto de venta se resuelve en la base, activo y de un canal permitido para el usuario; nunca se toma tal cual del navegador.
require_once __DIR__.'/../includes/pdv_datos.php';
require_once __DIR__.'/../includes/login_datos.php';
$posId = trim($payload['pos_id'] ?? '');
$punto = $posId !== '' ? ep_pdv_obtener($posId, ep_canales_usuario()) : null;
if ($tipo !== 'colocacion-pop' && !$punto) {
	http_response_code(422);
	echo json_encode(['success' => false, 'error' => 'Elige un punto de venta de la lista.']);
	exit;
}
$puntoVenta = $punto ? $punto['nombre'] : '';
$cadena = $punto ? $punto['cadena'] : '';
$ciudad = $punto ? $punto['ciudad'] : '';
$canal = $punto ? $punto['canal'] : '';
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

// Código corto: prefijo de la actividad + usuario + número por usuario, por ejemplo RACPABLOCASTELO-001.
$id = ep_codigo_registro($tipo, (int) $_SESSION['usuario_id'], $_SESSION['usuario']);

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
	'pos_id'          => $punto ? $punto['pos_id'] : null,
	'punto_venta'     => $puntoVenta,
	'cadena'          => $cadena,
	'ciudad'          => $ciudad,
	'canal'           => $canal,
	'promotor'        => $_SESSION['nombre'] ?? ucwords(str_replace('.', ' ', $usuario)),
	'promotor_usuario'=> $usuario,
	'promotor_avatar' => $avatar,
];

// Tipo, fecha y horario de la actividad: los escribe el promotor y se validan aquí.
if (in_array($tipo, ['activaciones', 'capacitaciones', 'epson-day', 'evento-ferias', 'exhibiciones'], true)) {
	require_once __DIR__.'/../includes/actividad_datos.php';
	[$datosActividad, $errorActividad] = ep_actividad_datos($valores);
	if ($errorActividad) {
		http_response_code(422);
		echo json_encode(['success' => false, 'error' => $errorActividad]);
		exit;
	}
	$registro = array_merge($registro, $datosActividad);
	$registro['promotor_correo'] = ep_correo_usuario((int) $_SESSION['usuario_id']);
}

// Procesar según formulario
if (in_array($tipo, ['activaciones', 'epson-day', 'evento-ferias'], true)) {
	$nac = ep_entero($valores['nacional'] ?? 0);
	$cob = ep_entero($valores['coberturadas'] ?? 0);
	$vis = ep_entero($valores['visitaron'] ?? 0);
	$inte = ep_entero($valores['interactuaron'] ?? 0);
	$com = ep_entero($valores['compraron'] ?? 0);

	// Evento o Ferias no tiene cobertura.
	if ($tipo !== 'evento-ferias') {
		$registro['cobertura'] = [
			'nacional'     => $nac,
			'coberturadas' => $cob,
			'pct'          => $nac > 0 ? round(($cob / $nac) * 100, 1) : 0,
		];
	}
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
	// Solo se guardan los conteos; el total de asistentes se calcula al leer.
	$vendedores = ep_entero($valores['vendedores'] ?? 0);
	$jefeTienda = ep_entero($valores['jefe_tienda'] ?? 0);
	$asistenteJefe = ep_entero($valores['asistente_jefe'] ?? 0);

	$registro['capacitacion'] = [
		'vendedores'     => $vendedores,
		'jefe_tienda'    => $jefeTienda,
		'asistente_jefe' => $asistenteJefe,
		'interacciones'  => min(ep_entero($valores['interacciones'] ?? 0), $vendedores + $jefeTienda + $asistenteJefe),
	];
} elseif ($tipo === 'colocacion-pop') {
	$popLista = is_array($valores['pop_materiales'] ?? null) ? $valores['pop_materiales'] : [];
	$registro['pop_materiales'] = $popLista;
} elseif ($tipo === 'exhibiciones') {
	$x = [];
	foreach (['cabeceras', 'rumas', 'muebles', 'exh_regular', 'otras'] as $clave) {
		$x[$clave] = ep_entero($valores[$clave] ?? 0);
	}
	$x['total'] = array_sum($x);
	foreach (['cabeceras', 'rumas', 'muebles', 'exh_regular', 'otras'] as $clave) {
		$x[$clave.'_pct'] = $x['total'] > 0 ? round(($x[$clave] / $x['total']) * 100, 1).'%' : '0%';
	}
	$registro['exhibiciones'] = $x;
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
	if ($ruta === '' && empty($rf['opcional'])) {
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
// Las obligatorias no pueden faltar; las marcadas como opcionales pueden quedar vacías.
if ($faltantes > 0) {
	http_response_code(422);
	echo json_encode(['success' => false, 'error' => 'Faltan '.$faltantes.' foto(s) por subir. Sube las fotos obligatorias.']);
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
