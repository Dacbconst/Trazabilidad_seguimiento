<?php
// Registros disponibles para armar un reporte mensual (solo admin) con filtros por fecha, promotor y canal.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/registros_datos.php';
require_once __DIR__.'/../includes/reportes_datos.php';
header('Content-Type: application/json; charset=utf-8');

if (!ep_login_check()) {
	http_response_code(401);
	echo json_encode(['success' => false, 'error' => 'Tu sesión se cerró. Inicia sesión de nuevo.', 'redirect' => 'login.php?error=sesion']);
	exit;
}
if (ep_rol_actual() !== 'admin') {
	http_response_code(403);
	echo json_encode(['success' => false, 'error' => 'No tienes permiso para esto.']);
	exit;
}

$tipo = preg_replace('/[^a-z-]/', '', (string) ($_GET['tipo'] ?? 'activaciones'));
$mes = trim((string) ($_GET['mes'] ?? ''));
$desde = trim((string) ($_GET['desde'] ?? ''));
$hasta = trim((string) ($_GET['hasta'] ?? ''));
$filtroPromotor = trim((string) ($_GET['promotor'] ?? ''));
$filtroCanal = trim((string) ($_GET['canal'] ?? ''));

// Los registros que ya están en un reporte activo no se ofrecen; al eliminar ese reporte vuelven a aparecer.
$ocupados = array_flip(ep_registros_ocupados());
$todosRegistros = array_values(array_filter(ep_registros_datos(5000), fn($r) => !isset($ocupados[(int) $r['db_id']])));
$promotoresSet = [];
$canalesSet = [];

// Recolectar listas globales de promotores y canales para el tipo seleccionado
foreach ($todosRegistros as $r) {
	if (($r['tipo'] ?? '') !== $tipo) {
		continue;
	}
	if (!empty($r['promotor'])) {
		$promotoresSet[$r['promotor']] = true;
	}
	if (!empty($r['canal'])) {
		$canalesSet[$r['canal']] = true;
	}
}

$lista = [];
foreach ($todosRegistros as $r) {
	if (($r['tipo'] ?? '') !== $tipo) {
		continue;
	}
	$fIso = substr(trim((string) ($r['fecha_actividad'] ?? ($r['fecha_iso'] ?? ''))), 0, 10);

	// Filtro por fecha única o rango
	if ($desde !== '' && $hasta !== '') {
		$minD = $desde <= $hasta ? $desde : $hasta;
		$maxD = $desde <= $hasta ? $hasta : $desde;
		if ($fIso < $minD || $fIso > $maxD) continue;
	} elseif ($desde !== '') {
		if ($fIso !== $desde) continue;
	} elseif ($hasta !== '') {
		if ($fIso !== $hasta) continue;
	} elseif ($mes !== '' && preg_match('/^\d{4}-\d{2}$/', $mes)) {
		if (strpos($fIso, $mes) !== 0) continue;
	}

	// Filtro por promotor
	if ($filtroPromotor !== '' && $filtroPromotor !== 'todos') {
		if (strcasecmp((string) ($r['promotor'] ?? ''), $filtroPromotor) !== 0 && strcasecmp((string) ($r['promotor_usuario'] ?? ''), $filtroPromotor) !== 0) {
			continue;
		}
	}

	// Filtro por canal
	if ($filtroCanal !== '' && $filtroCanal !== 'todos') {
		if (strcasecmp((string) ($r['canal'] ?? ''), $filtroCanal) !== 0) {
			continue;
		}
	}

	$fotosValidas = array_values(array_filter($r['fotos'] ?? [], fn($f) => !empty($f['ruta']) || !empty($f['url'])));
	$primeraFotoUrl = '';
	$nombreFoto = '';
	if (!empty($fotosValidas)) {
		$primeraFotoUrl = $fotosValidas[0]['url'] ?? '';
		$nombreFoto = basename($fotosValidas[0]['ruta'] ?? ($fotosValidas[0]['label'] ?? 'Foto_01.jpg'));
	}

	$lista[] = [
		'id'             => $r['db_id'],
		'codigo'         => $r['id'],
		'promotor'       => $r['promotor'] ?? '',
		'promotor_correo'=> $r['promotor_correo'] ?? '',
		'fecha'          => $fIso,
		'hora'           => $r['hora'] ?? '',
		'hora_inicio'    => $r['hora_inicio'] ?? '',
		'hora_fin'       => $r['hora_fin'] ?? '',
		'punto_venta'    => $r['punto_venta'] ?? 'Punto de venta',
		'ciudad'         => $r['ciudad'] ?? '',
		'canal'          => $r['canal'] ?? '',
		'tipo_actividad' => $r['tipo_actividad'] ?? ($r['actividad_label'] ?? 'Activación'),
		'actividad_label'=> $r['actividad_label'] ?? '',
		'estado'         => $r['estado'] ?? 'Activo',
		'tipo'           => $r['tipo'] ?? $tipo,
		'cobertura'      => $r['cobertura'] ?? null,
		'embudo'         => $r['embudo'] ?? null,
		'capacitacion'   => $r['capacitacion'] ?? null,
		'exhibiciones'   => $r['exhibiciones'] ?? null,
		'pop_materiales' => $r['pop_materiales'] ?? [],
		'modelos'        => $r['modelos'] ?? [],
		'comentarios'    => $r['comentarios'] ?? [],
		'fotos_count'    => count($fotosValidas),
		'primera_foto'   => $primeraFotoUrl,
		'nombre_foto'    => $nombreFoto ?: 'Sin foto',
		'fotos'          => $fotosValidas,
	];
}

usort($lista, fn($a, $b) => strcmp($b['fecha'].$b['hora'], $a['fecha'].$a['hora']));

$promotoresList = array_values(array_keys($promotoresSet));
sort($promotoresList, SORT_NATURAL | SORT_FLAG_CASE);

$canalesList = array_values(array_keys($canalesSet));
sort($canalesList, SORT_NATURAL | SORT_FLAG_CASE);

echo json_encode([
	'success'    => true,
	'registros'  => $lista,
	'promotores' => $promotoresList,
	'canales'    => $canalesList,
], JSON_UNESCAPED_UNICODE);
