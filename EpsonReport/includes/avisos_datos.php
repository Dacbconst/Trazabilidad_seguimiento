<?php
// Avisos del promotor: activaciones que le programaron en calendarios activos y aún no cumplió; se calculan al abrir, no se guardan.
require_once __DIR__.'/db.php';

// Columna donde se guarda hasta cuándo vio sus avisos; mientras no exista, no hay "nuevos" y todo lo demás funciona igual.
function ep_avisos_tiene_visto($db): bool {
	static $tiene = null;
	if ($tiene === null) {
		$res = $db->query("SHOW COLUMNS FROM repositorio_usuarios_reporte LIKE 'avisos_visto_en'");
		$tiene = $res && $res->num_rows > 0;
	}
	return $tiene;
}

function ep_avisos_vacio(): array {
	return ['calendarios' => [], 'devueltos' => [], 'total' => 0, 'nuevos' => 0, 'urgentes' => 0];
}

// Texto del último día: hoy, mañana o la fecha corta.
function ep_avisos_ultimo_dia(int $dias, string $fecha): string {
	$meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
	$corta = (int) date('j', strtotime($fecha)).' '.$meses[(int) date('n', strtotime($fecha))];
	if ($dias <= 0) {
		return 'Último día: hoy';
	}
	return $dias === 1 ? 'Último día: mañana' : 'Último día: '.$corta;
}

// Una entrada por calendario activo con las filas pendientes del usuario en sesión; el admin no recibe avisos.
function ep_avisos_promotor(): array {
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}
	$cache = ep_avisos_vacio();
	if (in_array($_SESSION['rol'] ?? '', ['admin', 'supervisor'], true) || empty($_SESSION['usuario_id'])) {
		return $cache;
	}
	$db = ep_db();
	if (!$db) {
		return $cache;
	}
	$visto = null;
	$conVisto = ep_avisos_tiene_visto($db);
	if ($conVisto) {
		$stmt = $db->prepare('SELECT avisos_visto_en FROM repositorio_usuarios_reporte WHERE id = ? LIMIT 1');
		$stmt->bind_param('i', $_SESSION['usuario_id']);
		$stmt->execute();
		$visto = $stmt->get_result()->fetch_assoc()['avisos_visto_en'] ?? null;
		$stmt->close();
	}
	// Registros que el supervisor devolvió: cuentan como aviso y como nuevos hasta que abra la campana.
	require_once __DIR__.'/aprobacion_datos.php';
	foreach (ep_devueltos_usuario((int) $_SESSION['usuario_id']) as $d) {
		$nuevo = $conVisto && ($visto === null || ($d['revisado_en'] !== null && $d['revisado_en'] > $visto));
		$cache['devueltos'][] = ['codigo' => $d['codigo'], 'punto' => $d['punto_venta'], 'fecha' => $d['fecha_actividad'], 'motivo' => (string) $d['motivo_devolucion'], 'revisor' => (string) $d['revisor'], 'nuevo' => $nuevo];
		$cache['total']++;
		$cache['nuevos'] += $nuevo ? 1 : 0;
	}
	$cache['devueltos_nuevos'] = count(array_filter($cache['devueltos'], fn($x) => $x['nuevo']));
	// La fila guarda el id de Xplora; se traduce por el nombre de usuario, igual que el cruce del calendario.
	$stmt = $db->prepare("SELECT c.id AS cal_id, c.nombre, c.canal, c.vence_en, f.fecha, f.punto_venta, f.ciudad, GREATEST(f.created_at, COALESCE(f.editado_en, f.created_at)) AS cambio FROM insert_reporte_calendario_fila f JOIN insert_reporte_calendario c ON c.id = f.calendario_id JOIN repositorio_usuarios xu ON xu.id = f.promotor_usuario_id WHERE xu.user = ? AND f.estado = 'pendiente' AND c.estado = 'activo' AND c.eliminado_en IS NULL AND c.vence_en > NOW() ORDER BY c.vence_en, f.fecha, f.id");
	if (!$stmt) {
		error_log('ep_avisos_promotor: '.$db->error);
		return $cache;
	}
	$stmt->bind_param('s', $_SESSION['usuario']);
	$stmt->execute();
	$hoy = strtotime(date('Y-m-d'));
	$grupos = [];
	foreach ($stmt->get_result() as $f) {
		$id = (int) $f['cal_id'];
		if (!isset($grupos[$id])) {
			$dias = (int) round((strtotime(substr($f['vence_en'], 0, 10)) - $hoy) / 86400);
			$grupos[$id] = ['id' => $id, 'nombre' => trim((string) $f['nombre']) ?: 'Activaciones '.$f['canal'], 'dias' => $dias, 'ultimo_dia' => ep_avisos_ultimo_dia($dias, $f['vence_en']), 'urgente' => $dias <= 1, 'nuevos' => 0, 'filas' => []];
		}
		$nueva = $conVisto && ($visto === null || $f['cambio'] > $visto);
		$grupos[$id]['filas'][] = ['fecha' => $f['fecha'], 'punto' => $f['punto_venta'], 'ciudad' => $f['ciudad'], 'nueva' => $nueva];
		$grupos[$id]['nuevos'] += $nueva ? 1 : 0;
		$cache['total']++;
		$cache['nuevos'] += $nueva ? 1 : 0;
	}
	$stmt->close();
	$cache['calendarios'] = array_values($grupos);
	$cache['urgentes'] = count(array_filter($cache['calendarios'], fn($c) => $c['urgente'])) + ($cache['devueltos'] ? 1 : 0);
	// Lo urgente primero, luego por último día más cercano.
	usort($cache['calendarios'], fn($a, $b) => $a['dias'] <=> $b['dias']);
	return $cache;
}

// Marca que el promotor ya vio sus avisos hasta este momento.
function ep_avisos_marcar_visto(int $usuarioId): bool {
	$db = ep_db();
	if (!$db || !ep_avisos_tiene_visto($db)) {
		return false;
	}
	$stmt = $db->prepare('UPDATE repositorio_usuarios_reporte SET avisos_visto_en = NOW() WHERE id = ?');
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$stmt->close();
	return true;
}
