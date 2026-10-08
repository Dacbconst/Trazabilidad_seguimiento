<?php
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
// La acción deja desactualizada una caché de sesión (ver ep_cache_sesion).
unset($_SESSION['ep_cache']['avisos']);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../includes/functions.php';

if (!ep_login_check()) {
	http_response_code(401);
	echo json_encode(['success' => false, 'error' => 'Tu sesión se cerró porque se inició sesión con esta cuenta en otro dispositivo, o expiró.', 'redirect' => 'login.php?error=sesion']);
	exit;
}

// Liberado a pedido del cliente (2026-10-03, "hasta nuevo aviso"): un supervisor sí puede enviar registros de cualquier tipo.
// Si se vuelve a restringir, el bloqueo iba aquí (ep_es_supervisor() -> 403).

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

// Competencia: un solo registro con varios puntos de venta (cada uno con sus propias fotos y descripciones), no un pos_id único.
if ($tipo === 'competencia') {
	require_once __DIR__.'/../includes/pdv_datos.php';
	require_once __DIR__.'/../includes/login_datos.php';
	require_once __DIR__.'/../includes/fotos_datos.php';
	require_once __DIR__.'/../includes/registros_datos.php';
	require_once __DIR__.'/../includes/aprobacion_datos.php';

	$valores = is_array($payload['valores'] ?? null) ? $payload['valores'] : [];
	$puntosEntrada = is_array($valores['puntos'] ?? null) ? $valores['puntos'] : [];
	if (!$puntosEntrada) {
		http_response_code(422);
		echo json_encode(['success' => false, 'error' => 'Agrega al menos un punto de venta.']);
		exit;
	}

	$conAprobacion = ep_aprobacion_activa();
	$canales = ep_canales_usuario();
	$reqFotos = ep_fotos_requeridas($tipo);
	$idsConocidos = array_column($reqFotos, 'id');
	$sinSimbolos = function ($v) { return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $v)); };
	$usuarioLimpio = $sinSimbolos($_SESSION['usuario']);
	$hora = date('H:i');

	$puntosFinal = [];
	$supervisoresVistos = [];
	foreach ($puntosEntrada as $i => $p) {
		// Igual que con un solo punto: nunca se confía en lo que manda el navegador, se revalida contra la base.
		$posIdPunto = trim((string) ($p['pos_id'] ?? ''));
		$punto = $posIdPunto !== '' ? ep_pdv_obtener($posIdPunto, $canales) : null;
		if (!$punto) {
			http_response_code(422);
			echo json_encode(['success' => false, 'error' => 'El punto de venta '.($i + 1).' no es válido.']);
			exit;
		}
		$fotosSubidas = is_array($p['fotos'] ?? null) ? $p['fotos'] : [];
		$descripcionesRecibidas = is_array($p['descripciones'] ?? null) ? $p['descripciones'] : [];
		$fotosFinal = [];
		$faltantes = 0;
		foreach ($reqFotos as $rf) {
			$ruta = (string) ($fotosSubidas[$rf['id']] ?? '');
			$esperado = $usuarioLimpio.$sinSimbolos($rf['id']);
			if ($ruta !== '' && !preg_match('#^[A-Za-z]+/\d{14}'.preg_quote($esperado, '#').'\.(jpg|png|webp)$#', $ruta)) {
				$ruta = '';
			}
			if ($ruta === '' && empty($rf['opcional'])) {
				$faltantes++;
			}
			$fotosFinal[] = ['id' => $rf['id'], 'label' => $rf['label'], 'hora' => $hora, 'estado' => 'Verificada', 'ruta' => $ruta, 'url' => $ruta !== '' ? 'https://luckyecuadorweb.blob.core.windows.net/app/AppEpson/EpsonReport/'.$ruta : ''];
		}
		if ($faltantes > 0) {
			http_response_code(422);
			echo json_encode(['success' => false, 'error' => 'Al punto de venta '.$punto['nombre'].' le falta subir una foto obligatoria.']);
			exit;
		}
		foreach ($fotosSubidas as $fotoId => $ruta) {
			if (in_array($fotoId, $idsConocidos, true) || !preg_match('/^foto-\d+$/', $fotoId)) {
				continue;
			}
			$esperado = $usuarioLimpio.$sinSimbolos($fotoId);
			if (!preg_match('#^[A-Za-z]+/\d{14}'.preg_quote($esperado, '#').'\.(jpg|png|webp)$#', (string) $ruta)) {
				continue;
			}
			$fotosFinal[] = ['id' => $fotoId, 'label' => ep_foto_extra_label($tipo), 'hora' => $hora, 'estado' => 'Verificada', 'ruta' => $ruta, 'url' => 'https://luckyecuadorweb.blob.core.windows.net/app/AppEpson/EpsonReport/'.$ruta];
		}
		$descripciones = [];
		foreach ($fotosFinal as $f) {
			if ($f['ruta'] === '') {
				continue;
			}
			$texto = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($descripcionesRecibidas[$f['id']] ?? ''))), 0, EP_FOTO_DESCRIPCION_MAX, 'UTF-8');
			if ($texto === '') {
				http_response_code(422);
				echo json_encode(['success' => false, 'error' => 'Falta la descripción de una foto en '.$punto['nombre'].'.']);
				exit;
			}
			$descripciones[$f['id']] = $texto;
		}
		$supervisorPunto = $conAprobacion ? ep_supervisor_asignado((int) $_SESSION['usuario_id'], $punto['canal']) : null;
		if ($supervisorPunto) {
			$supervisoresVistos[$supervisorPunto] = true;
		}
		$puntosFinal[] = [
			'pos_id' => $punto['pos_id'], 'punto_venta' => $punto['nombre'], 'cadena' => $punto['cadena'], 'ciudad' => $punto['ciudad'], 'canal' => $punto['canal'],
			'fotos' => $fotosFinal, 'descripciones' => $descripciones,
		];
	}

	// El primer supervisor que aparece es el "dueño" del registro (columna supervisor_id); si hay otro canal de por medio,
	// el resto queda en supervisores_extra dentro del JSON (ver ep_aprobacion_filtro_alcance) para que también lo vean.
	$supervisorIds = array_keys($supervisoresVistos);
	$supervisorPrincipal = $supervisorIds ? array_shift($supervisorIds) : null;

	$usuario = $_SESSION['usuario'];
	$id = ep_codigo_registro($tipo, (int) $_SESSION['usuario_id'], $usuario);
	$primero = $puntosFinal[0];
	$registro = [
		'id' => $id,
		'tipo' => $tipo,
		'actividad_label' => $actividadLabel,
		'actividad_badge' => $actividadBadge,
		'fecha_iso' => date('Y-m-d'),
		'hora' => $hora,
		'estado' => $conAprobacion ? 'Pendiente' : 'Aprobado',
		'supervisor_id' => $supervisorPrincipal,
		'pos_id' => $primero['pos_id'],
		'punto_venta' => $primero['punto_venta'],
		'cadena' => $primero['cadena'],
		'ciudad' => $primero['ciudad'],
		'canal' => $primero['canal'],
		'promotor' => $_SESSION['nombre'] ?? ucwords(str_replace('.', ' ', $usuario)),
		'promotor_usuario' => $usuario,
		'puntos' => $puntosFinal,
		'supervisores_extra' => $supervisorIds,
		'fotos' => [],
		'comentarios' => [],
	];

	$ok = ep_guardar_nuevo_registro($registro, (int) $_SESSION['usuario_id']);
	if ($ok) {
		echo json_encode(['success' => true, 'id' => $id, 'pendiente' => $conAprobacion, 'mensaje' => 'Registro '.$id.' guardado con '.count($puntosFinal).' punto(s) de venta.', 'redirect' => 'index.php?vista=historial&nuevo='.urlencode($id)]);
	} else {
		http_response_code(500);
		echo json_encode(['success' => false, 'error' => 'No se pudo guardar el registro. Intenta de nuevo.']);
	}
	exit;
}

// El punto de venta se resuelve en la base, activo y de un canal permitido para el usuario; nunca se toma tal cual del navegador.
require_once __DIR__.'/../includes/pdv_datos.php';
require_once __DIR__.'/../includes/login_datos.php';
$posId = trim($payload['pos_id'] ?? '');
$punto = $posId !== '' ? ep_pdv_obtener($posId, ep_canales_usuario()) : null;
if (!$punto) {
	http_response_code(422);
	echo json_encode(['success' => false, 'error' => 'Elige un punto de venta de la lista.']);
	exit;
}
$puntoVenta = $punto ? $punto['nombre'] : '';
$cadena = $punto ? $punto['cadena'] : '';
$ciudad = $punto ? $punto['ciudad'] : '';
$canal = $punto ? $punto['canal'] : '';
$valores = is_array($payload['valores'] ?? null) ? $payload['valores'] : [];

// Con la aprobación activa el registro nace pendiente y se asigna al supervisor de su categoría.
require_once __DIR__.'/../includes/aprobacion_datos.php';
$conAprobacion = ep_aprobacion_activa();
$supervisorId = $conAprobacion ? ep_supervisor_asignado((int) $_SESSION['usuario_id'], $canal) : null;

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
	'estado'          => $conAprobacion ? 'Pendiente' : 'Aprobado',
	'estado_tipo'     => $conAprobacion ? 'warn' : 'ok',
	'supervisor_id'   => $supervisorId,
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
	// Una sola Activación por punto y día: si ya envió una, no se guarda otra hasta que el admin elimine la primera.
	require_once __DIR__.'/../includes/registros_datos.php';
	$codigoPrevio = ep_registro_duplicado((int) $_SESSION['usuario_id'], $tipo, $posId, $datosActividad['fecha_actividad']);
	if ($codigoPrevio) {
		http_response_code(409);
		echo json_encode(['success' => false, 'duplicado' => true, 'error' => ep_registro_duplicado_mensaje($codigoPrevio, $datosActividad['fecha_actividad'])]);
		exit;
	}
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
			// Precio unitario, solo Activaciones y Epson Day lo piden en el formulario; en las demás llega vacío.
			$precio = round(max(0, (float) ($m['precio'] ?? 0)), 2);
			$mods[] = [
				'modelo'   => $nombreModelo,
				'cantidad' => $cant,
				'pct'      => $pct,
				'precio'   => $precio,
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
	// Un registro = un punto de venta: el material debe estar cargado en el mes de POP abierto, y de ahí sale su campaña.
	require_once __DIR__.'/../includes/pop_datos.php';
	$mesPop = ep_pop_abierto();
	if (!$mesPop) {
		http_response_code(422);
		echo json_encode(['success' => false, 'error' => 'Todavía no hay material POP cargado para este mes. Avísale a tu supervisor.']);
		exit;
	}
	$campanaDe = [];
	$filaDe = [];
	foreach ($mesPop['filas'] as $f) {
		$campanaDe[$f['material']] = $f['campana'];
		$filaDe[$f['material']] = (int) $f['id'];
	}
	$entregas = [];
	$campana = '';
	// El promotor solo reporta lo que su supervisor le asignó y no más de lo que le queda.
	$esPromotor = ($_SESSION['rol'] ?? '') === 'usuario';
	$mio = $esPromotor ? ep_pop_mi_material((int) $_SESSION['usuario_id'], $mesPop) : [];
	$pedido = [];
	foreach (is_array($valores['pop_entregas'] ?? null) ? $valores['pop_entregas'] : [] as $e) {
		$material = mb_substr(trim(preg_replace('/\s+/u', ' ', mb_strtoupper((string) ($e['material'] ?? ''), 'UTF-8'))), 0, 60, 'UTF-8');
		$cantidad = min(99999, max(0, (int) ($e['cantidad'] ?? 0)));
		if ($material === '' || $cantidad <= 0) {
			continue;
		}
		if (!isset($campanaDe[$material])) {
			http_response_code(422);
			echo json_encode(['success' => false, 'error' => 'El material «'.$material.'» no está cargado en el mes de POP. Vuelve a elegirlo de la lista.']);
			exit;
		}
		$clave = $filaDe[$material];
		$pedido[$clave] = ($pedido[$clave] ?? 0) + $cantidad;
		if ($esPromotor && $pedido[$clave] > ($mio[$clave]['disponible'] ?? 0)) {
			http_response_code(422);
			$queda = $mio[$clave]['disponible'] ?? 0;
			if (!isset($mio[$clave])) {
				$error = 'Tu supervisor no te asignó «'.$material.'». Pídele que te lo marque en su equipo.';
			} elseif ($queda <= 0) {
				$error = 'Ya no queda «'.$material.'»: el saldo lo comparten los promotores de tu supervisor y se agotó. Avísale.';
			} else {
				$error = 'De «'.$material.'» solo quedan '.$queda.' (el saldo lo compartes con tu equipo).';
			}
			echo json_encode(['success' => false, 'error' => $error]);
			exit;
		}
		// La campaña va por material: un mes puede tener dos campañas vivas y el registro mezclar materiales de ambas.
		$entregas[] = ['material' => $material, 'cantidad' => $cantidad, 'campana' => $campanaDe[$material], 'fila_id' => $filaDe[$material]];
		$campana = $campana ?: $campanaDe[$material];
	}
	if (!$entregas) {
		http_response_code(422);
		echo json_encode(['success' => false, 'error' => 'Elige al menos un material con su cantidad.']);
		exit;
	}
	$registro['campana'] = $campana;
	$registro['pop_entregas'] = $entregas;
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
// Actividades extensibles: además de la lista fija, se validan las fotos extra que el promotor haya sumado con "+ Agregar foto".
if (ep_fotos_extensible($tipo)) {
	$idsConocidos = array_column($reqFotos, 'id');
	foreach ($fotosSubidas as $fotoId => $ruta) {
		if (in_array($fotoId, $idsConocidos, true) || !preg_match('/^foto-\d+$/', $fotoId)) {
			continue;
		}
		$esperado = $usuarioLimpio.$sinSimbolos($fotoId);
		if (!preg_match('#^[A-Za-z]+/\d{14}'.preg_quote($esperado, '#').'\.(jpg|png|webp)$#', (string) $ruta)) {
			continue;
		}
		$fotosFinal[] = [
			'id'     => $fotoId,
			'label'  => ep_foto_extra_label($tipo),
			'hora'   => $hora,
			'estado' => 'Verificada',
			'ruta'   => $ruta,
			'url'    => 'https://luckyecuadorweb.blob.core.windows.net/app/AppEpson/EpsonReport/'.$ruta,
		];
	}
}
$registro['fotos'] = $fotosFinal;

// Competencia: cada foto subida lleva su descripción (pie de la foto en el PPT); se guarda en el JSON del registro, por casilla.
if (ep_fotos_con_descripcion($tipo)) {
	$descripcionesRecibidas = is_array($valores['descripciones'] ?? null) ? $valores['descripciones'] : [];
	$descripciones = [];
	foreach ($fotosFinal as $f) {
		if ($f['ruta'] === '') {
			continue;
		}
		$texto = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($descripcionesRecibidas[$f['id']] ?? ''))), 0, EP_FOTO_DESCRIPCION_MAX, 'UTF-8');
		if ($texto === '') {
			http_response_code(422);
			echo json_encode(['success' => false, 'error' => 'Cada foto necesita su descripción. Falta en: '.$f['label'].'.']);
			exit;
		}
		$descripciones[$f['id']] = $texto;
	}
	$registro['descripciones'] = $descripciones;
}

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
		'pendiente' => $conAprobacion,
		'mensaje'  => 'Registro '.$id.' guardado exitosamente en el sistema.',
		'redirect' => 'index.php?vista=historial&nuevo='.urlencode($id),
	]);
} else {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'error'   => 'No se pudo guardar el registro. Intenta de nuevo.',
	]);
}
