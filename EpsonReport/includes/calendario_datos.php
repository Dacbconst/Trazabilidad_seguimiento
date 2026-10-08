<?php
// Calendario de Activaciones: se arma en filas (fecha + punto de venta + promotor) y el sistema cruza solo lo real. Sin borrador.
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';
require_once __DIR__.'/reportes_datos.php';

const EP_CAL_RUTERO_CACHE_TTL = 300;

// Canales con puntos activos en repositorio_locales_dtt2 (no solo Retail y Canales).
function ep_calendario_canales(): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$canales = [];
	$res = $db->query("SELECT DISTINCT channel FROM repositorio_locales_dtt2 WHERE activar = 'SI' AND channel <> '-' ORDER BY channel");
	while ($row = $res->fetch_assoc()) {
		$canales[] = $row['channel'];
	}
	// Un supervisor solo programa en los canales donde tiene promotores a su cargo.
	if (ep_es_supervisor()) {
		require_once __DIR__.'/aprobacion_datos.php';
		$miId = (int) $_SESSION['usuario_id'];
		$canales = array_values(array_filter(array_intersect($canales, ep_supervisor_canales_gestion($miId)), fn($c) => ep_promotores_de_supervisor($miId, $c)));
	}
	return $canales;
}

// Rutero directo a las tablas (lvi_rutero es lenta); incluye promotores sin cuenta en la app. Caché 5 min.
function ep_calendario_rutero(string $canal): array {
	$cacheFile = __DIR__.'/../data/cache/rutero_'.$canal.'.json';
	if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < EP_CAL_RUTERO_CACHE_TTL) {
		$cacheado = json_decode(file_get_contents($cacheFile), true);
		if (is_array($cacheado)) {
			return $cacheado;
		}
	}
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$stmt = $db->prepare("SELECT DISTINCT pdvs.city AS ciudad, usu.id AS promotor_id, usu.user AS promotor_nombre, pdvs.pos_id, pdvs.pos_name AS punto_venta, sup.supervisor
		FROM rutero_pdv rutero
		JOIN repositorio_locales_dtt2 pdvs ON pdvs.id = rutero.id_pdv AND pdvs.channel = ? AND pdvs.activar = 'SI'
		JOIN repositorio_usuarios usu ON usu.id = rutero.id_usuario
		LEFT JOIN repositorio_supervisores sup ON sup.id = rutero.id_supervisor
		WHERE rutero.status = 1 AND rutero.habilitado = 1
		ORDER BY pdvs.city, usu.user, pdvs.pos_name");
	$stmt->bind_param('s', $canal);
	$stmt->execute();
	$filas = [];
	foreach ($stmt->get_result() as $f) {
		$filas[] = [
			'ciudad' => $f['ciudad'],
			'promotor_id' => (int) $f['promotor_id'],
			'promotor_nombre' => $f['promotor_nombre'],
			'pos_id' => $f['pos_id'],
			'punto_venta' => $f['punto_venta'],
			'supervisor' => $f['supervisor'] ?? '',
		];
	}
	if (!is_dir(dirname($cacheFile))) {
		mkdir(dirname($cacheFile), 0755, true);
	}
	file_put_contents($cacheFile, json_encode($filas));
	return $filas;
}

// Supervisor que la app asigna a cada promotor en ese canal (usuario en mayúsculas => supervisor); es quien aprueba, no el del rutero de Xplora.
function ep_calendario_supervisores_app(string $canal): array {
	static $cache = [];
	if (isset($cache[$canal])) {
		return $cache[$canal];
	}
	$cache[$canal] = [];
	$db = ep_db();
	require_once __DIR__.'/aprobacion_datos.php';
	if (!$db || !ep_usuarios_tiene_ruta($db)) {
		return $cache[$canal];
	}
	$nombres = [];
	foreach ($db->query("SELECT id, usuario FROM repositorio_usuarios_reporte WHERE rol <> 'promotor'") ?: [] as $s) {
		$nombres[(int) $s['id']] = $s['usuario'];
	}
	foreach ($db->query('SELECT usuario, categorias, supervisor_canales_id, supervisor_retail_id FROM repositorio_usuarios_reporte') ?: [] as $f) {
		$id = ep_supervisor_de_fila($f, $canal);
		if ($id && isset($nombres[$id])) {
			$cache[$canal][mb_strtoupper($f['usuario'], 'UTF-8')] = $nombres[$id];
		}
	}
	return $cache[$canal];
}

// Ciudades del canal en repositorio_locales_dtt2, haya o no rutero en ellas.
function ep_calendario_ciudades(string $canal): array {
	$cacheFile = __DIR__.'/../data/cache/ciudades_'.$canal.'.json';
	if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < EP_CAL_RUTERO_CACHE_TTL) {
		$cacheado = json_decode(file_get_contents($cacheFile), true);
		if (is_array($cacheado)) {
			return $cacheado;
		}
	}
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$stmt = $db->prepare("SELECT DISTINCT city FROM repositorio_locales_dtt2 WHERE channel = ? AND activar = 'SI' ORDER BY city");
	$stmt->bind_param('s', $canal);
	$stmt->execute();
	$ciudades = [];
	foreach ($stmt->get_result() as $f) {
		$ciudades[] = $f['city'];
	}
	if (!is_dir(dirname($cacheFile))) {
		mkdir(dirname($cacheFile), 0755, true);
	}
	file_put_contents($cacheFile, json_encode($ciudades));
	return $ciudades;
}

// PDV activos del canal: misma fuente que el selector de Actividades, sin cruzar con el rutero.
function ep_calendario_pdv(string $canal): array {
	$cacheFile = __DIR__.'/../data/cache/pdv_'.$canal.'.json';
	if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < EP_CAL_RUTERO_CACHE_TTL) {
		$cacheado = json_decode(file_get_contents($cacheFile), true);
		if (is_array($cacheado)) {
			return $cacheado;
		}
	}
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$stmt = $db->prepare("SELECT pos_id, pos_name, city FROM repositorio_locales_dtt2 WHERE channel = ? AND activar = 'SI' ORDER BY pos_name");
	$stmt->bind_param('s', $canal);
	$stmt->execute();
	$puntos = [];
	foreach ($stmt->get_result() as $f) {
		$puntos[] = ['pos_id' => $f['pos_id'], 'punto_venta' => $f['pos_name'], 'ciudad' => $f['city']];
	}
	if (!is_dir(dirname($cacheFile))) {
		mkdir(dirname($cacheFile), 0755, true);
	}
	file_put_contents($cacheFile, json_encode($puntos));
	return $puntos;
}

// Revalida la fila: PDV activo del canal y promotor de Xplora; el supervisor sale de su rutero.
// El admin gestiona cualquier calendario; el supervisor solo los que él creó.
function ep_calendario_permitido(int $calendarioId): bool {
	if (ep_es_admin()) {
		return true;
	}
	$db = ep_db();
	if (!ep_es_supervisor() || !$db) {
		return false;
	}
	$res = $db->query('SELECT creado_por FROM insert_reporte_calendario WHERE eliminado_en IS NULL AND id = '.$calendarioId);
	$fila = $res ? $res->fetch_assoc() : null;
	return $fila && (int) $fila['creado_por'] === (int) ($_SESSION['usuario_id'] ?? 0);
}

function ep_calendario_fila_validar(string $canal, int $promotorId, string $posId): ?array {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$stmt = $db->prepare("SELECT pos_name, city FROM repositorio_locales_dtt2 WHERE pos_id = ? AND channel = ? AND activar = 'SI'");
	$stmt->bind_param('ss', $posId, $canal);
	$stmt->execute();
	$pdv = $stmt->get_result()->fetch_assoc();
	if (!$pdv) {
		return null;
	}

	$stmt2 = $db->prepare('SELECT user FROM repositorio_usuarios WHERE id = ?');
	$stmt2->bind_param('i', $promotorId);
	$stmt2->execute();
	$promotor = $stmt2->get_result()->fetch_assoc();
	if (!$promotor) {
		return null;
	}
	// Un supervisor solo programa a los promotores de su equipo.
	if (ep_es_supervisor()) {
		require_once __DIR__.'/aprobacion_datos.php';
		if (!in_array(mb_strtoupper($promotor['user'], 'UTF-8'), ep_promotores_de_supervisor((int) $_SESSION['usuario_id'], $canal), true)) {
			return null;
		}
	}

	$supervisor = '';
	$deLaApp = ep_calendario_supervisores_app($canal)[mb_strtoupper($promotor['user'], 'UTF-8')] ?? '';
	foreach (ep_calendario_rutero($canal) as $f) {
		if ($f['promotor_id'] === $promotorId && $f['supervisor']) {
			$supervisor = $f['supervisor'];
			break;
		}
	}

	// Manda el supervisor de la app (quien aprueba); el del rutero solo si el promotor no tiene uno asignado.
	$supervisor = $deLaApp ?: $supervisor;

	return [
		'punto_venta' => $pdv['pos_name'],
		'ciudad' => $pdv['city'],
		'promotor_nombre' => $promotor['user'],
		'supervisor' => $supervisor,
	];
}

// Nombre del calendario activo que ya le pide a ese promotor ese punto ese día (sin contar $excluirFilaId); null si está libre.
function ep_calendario_fila_repetida(int $promotorId, string $posId, string $fecha, int $excluirFilaId = 0): ?string {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$stmt = $db->prepare("SELECT c.nombre, c.canal FROM insert_reporte_calendario_fila f JOIN insert_reporte_calendario c ON c.id = f.calendario_id WHERE c.estado = 'activo' AND c.eliminado_en IS NULL AND f.promotor_usuario_id = ? AND f.pos_id = ? AND f.fecha = ? AND f.id <> ? LIMIT 1");
	$stmt->bind_param('issi', $promotorId, $posId, $fecha, $excluirFilaId);
	$stmt->execute();
	$cal = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $cal ? ep_calendario_nombre($cal) : null;
}

function ep_calendario_fila_repetida_mensaje(string $promotor, string $fecha, string $calendario): string {
	return $promotor.' ya tiene ese punto de venta pedido para el '.date('d/m/Y', strtotime($fecha)).' en «'.$calendario.'».';
}

// Lista de calendarios con sus filas, más recientes primero. $soloActivos filtra los ya cerrados.
function ep_calendario_listar(bool $soloActivos = false): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$where = 'WHERE c.eliminado_en IS NULL'.($soloActivos ? " AND c.estado = 'activo'" : '').(ep_es_supervisor() ? ' AND c.creado_por = '.(int) $_SESSION['usuario_id'] : '');
	$cabeceras = [];
	$res = $db->query("SELECT c.* FROM insert_reporte_calendario c $where ORDER BY c.created_at DESC");
	while ($row = $res->fetch_assoc()) {
		$row['filas'] = [];
		$cabeceras[(int) $row['id']] = $row;
	}
	if (empty($cabeceras)) {
		return [];
	}
	$ids = implode(',', array_keys($cabeceras));
	$res = $db->query("SELECT * FROM insert_reporte_calendario_fila WHERE calendario_id IN ($ids) ORDER BY fecha, promotor_nombre");
	while ($fila = $res->fetch_assoc()) {
		$cabeceras[(int) $fila['calendario_id']]['filas'][] = $fila;
	}
	return array_values($cabeceras);
}

// Comentarios del reporte: máximo 5 líneas de 200 caracteres, como en el reporte manual.
function ep_calendario_limpiar_comentarios(string $texto): ?string {
	$lineas = array_slice(array_filter(array_map(fn($l) => mb_substr(trim($l), 0, 200), preg_split('/\R/', $texto))), 0, 5);
	return $lineas ? implode("\n", $lineas) : null;
}

// Crea el calendario y sus filas en una transacción; se activa de inmediato y desde/hasta se calculan solos.
function ep_calendario_crear(string $nombre, string $canal, int $plazoDias, ?string $comentarios, array $filas, int $creadoPor): ?int {
	$db = ep_db();
	if (!$db || empty($filas)) {
		return null;
	}
	$fechas = array_column($filas, 'fecha');
	sort($fechas);
	$desde = $fechas[0];
	$hasta = $fechas[count($fechas) - 1];
	$activadoEn = date('Y-m-d H:i:s');
	$venceEn = date('Y-m-d H:i:s', strtotime($activadoEn.' + '.$plazoDias.' days'));

	$db->begin_transaction();
	$stmt = $db->prepare('INSERT INTO insert_reporte_calendario (nombre, canal, plazo_dias, comentarios, desde, hasta, activado_en, vence_en, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
	$stmt->bind_param('ssisssssi', $nombre, $canal, $plazoDias, $comentarios, $desde, $hasta, $activadoEn, $venceEn, $creadoPor);
	if (!$stmt->execute()) {
		error_log('ep_calendario_crear: '.$stmt->error);
		$db->rollback();
		return null;
	}
	$calendarioId = (int) $db->insert_id;
	$stmt->close();

	$stmtFila = $db->prepare('INSERT INTO insert_reporte_calendario_fila (calendario_id, fecha, pos_id, punto_venta, ciudad, promotor_usuario_id, promotor_nombre, supervisor_nombre) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
	foreach ($filas as $f) {
		$fecha = $f['fecha'];
		$posId = $f['pos_id'];
		$puntoVenta = $f['punto_venta'];
		$ciudad = $f['ciudad'] ?? null;
		$promotorId = (int) $f['promotor_usuario_id'];
		$promotorNombre = $f['promotor_nombre'];
		$supervisorNombre = $f['supervisor_nombre'] ?? null;
		$stmtFila->bind_param('issssiss', $calendarioId, $fecha, $posId, $puntoVenta, $ciudad, $promotorId, $promotorNombre, $supervisorNombre);
		if (!$stmtFila->execute()) {
			error_log('ep_calendario_crear (fila): '.$stmtFila->error);
			$db->rollback();
			return null;
		}
	}
	$stmtFila->close();
	$db->commit();
	return $calendarioId;
}

// Filas de un calendario con lo que necesita la tabla del PPTX (y el id del registro que las cumplió).
function ep_calendario_filas_tabla(int $calendarioId): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$stmt = $db->prepare('SELECT fecha, ciudad, punto_venta, promotor_nombre AS promotor, supervisor_nombre AS supervisor, estado, registro_id FROM insert_reporte_calendario_fila WHERE calendario_id = ? ORDER BY fecha, ciudad, punto_venta');
	$stmt->bind_param('i', $calendarioId);
	$stmt->execute();
	return array_map(fn($f) => ['fecha' => $f['fecha'], 'ciudad' => (string) $f['ciudad'], 'punto_venta' => $f['punto_venta'], 'promotor' => $f['promotor'], 'supervisor' => (string) ($f['supervisor'] ?? ''), 'estado' => $f['estado'], 'registro_id' => $f['registro_id'] !== null ? (int) $f['registro_id'] : null], $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

// Tabla del calendario de un reporte guardado antes de congelarla en su copia.
function ep_calendario_tabla_de_reporte(int $reporteId): ?array {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$stmt = $db->prepare('SELECT id, canal FROM insert_reporte_calendario WHERE reporte_mensual_id = ? LIMIT 1');
	$stmt->bind_param('i', $reporteId);
	$stmt->execute();
	$cal = $stmt->get_result()->fetch_assoc();
	return $cal ? ['canal' => $cal['canal'], 'filas' => ep_calendario_filas_tabla((int) $cal['id'])] : null;
}

// Cierra el calendario (manual con "Generar ahora" o automático al vencer) y genera el reporte mensual con lo cumplido.
// Cierra el calendario; null si no se pudo cerrar, 0 si cerró SIN reporte (sus registros ya estaban en otro reporte activo) o el id del reporte generado.
function ep_calendario_generar_ahora(int $calendarioId, int $usuarioId): ?int {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$cal = $db->query('SELECT * FROM insert_reporte_calendario WHERE id = '.$calendarioId.' AND eliminado_en IS NULL')->fetch_assoc();
	if (!$cal || $cal['estado'] !== 'activo') {
		return null;
	}
	$filasCalendario = ep_calendario_filas_tabla($calendarioId);
	$programadas = count($filasCalendario);
	$ids = array_values(array_filter(array_map(fn($f) => $f['estado'] === 'cumplido' ? $f['registro_id'] : null, $filasCalendario)));

	// Mismas reglas que el reporte manual: solo registros libres y con su copia completa.
	require_once __DIR__.'/registros_datos.php';
	$ocupados = array_flip(ep_registros_ocupados($calendarioId));
	$validos = [];
	$copia = [];
	foreach ($ids ? ep_registros_datos(5000, $ids, ['Aprobado'], false) : [] as $r) {
		if (($r['tipo'] ?? '') !== 'activaciones' || isset($ocupados[(int) $r['db_id']])) {
			continue;
		}
		$validos[] = (int) $r['db_id'];
		$copia[] = $r;
	}

	$mes = date('Y-m', strtotime($cal['desde']));
	$titulo = $cal['nombre'] ?: ('Activaciones '.$cal['canal']);
	// Se congela la tabla para que reactivar y editar el calendario no cambie este PPTX.
	$tabla = ['canal' => $cal['canal'], 'filas' => array_map(fn($f) => array_diff_key($f, ['registro_id' => 0]), $filasCalendario)];
	$snapshot = json_encode(['desde' => $cal['desde'], 'hasta' => $cal['hasta'], 'actividad' => 'Activaciones', 'calendario' => $tabla, 'registros' => $copia], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$reporteId = $validos ? ep_reporte_crear('activaciones', $mes, $titulo, null, $programadas, $cal['comentarios'] ?? null, $validos, $snapshot, $usuarioId) : 0;

	$ahora = date('Y-m-d H:i:s');
	$stmt = $db->prepare("UPDATE insert_reporte_calendario SET estado = 'cerrado', cerrado_en = ?, reporte_mensual_id = ? WHERE id = ?");
	$reporteIdParam = $reporteId > 0 ? $reporteId : null;
	$stmt->bind_param('sii', $ahora, $reporteIdParam, $calendarioId);
	return $stmt->execute() ? $reporteId : null;
}

// Un calendario con todas sus filas cumplidas se cierra y genera su reporte ya, sin esperar el plazo (lo registra el Sistema).
function ep_calendario_cerrar_completos(): void {
	$db = ep_db();
	if (!$db) {
		return;
	}
	$res = $db->query("SELECT c.id, c.nombre, c.canal, c.creado_por FROM insert_reporte_calendario c WHERE c.estado = 'activo' AND c.eliminado_en IS NULL
		AND EXISTS (SELECT 1 FROM insert_reporte_calendario_fila f WHERE f.calendario_id = c.id)
		AND NOT EXISTS (SELECT 1 FROM insert_reporte_calendario_fila f WHERE f.calendario_id = c.id AND f.estado <> 'cumplido')");
	if (!$res) {
		return;
	}
	require_once __DIR__.'/auditoria_datos.php';
	while ($row = $res->fetch_assoc()) {
		$id = (int) $row['id'];
		$reporteId = ep_calendario_generar_ahora($id, (int) $row['creado_por']);
		if ($reporteId === null) {
			continue;
		}
		if ($reporteId > 0) {
			ep_auditar('calendario_cierre_completo', 'calendario', $id, 'Se cerró «'.ep_calendario_nombre($row).'» al completarse todas sus filas', ep_calendario_detalle_cierre($id), 0);
		} else {
			// No es un cierre normal: quedó cerrado pero sin reporte porque sus registros ya estaban en otro reporte activo.
			ep_auditar('calendario_cierre_sin_reporte', 'calendario', $id, 'Se cerró «'.ep_calendario_nombre($row).'» al completarse, pero no se generó el reporte: sus registros ya estaban en otro reporte mensual activo', ep_calendario_detalle_cierre($id), 0);
		}
	}
}

// Cruza filas pendientes con registros enviados antes de que existiera el calendario.
function ep_calendario_cruzar_pendientes(): void {
	$db = ep_db();
	if (!$db) {
		return;
	}
	$res = $db->query("SELECT f.id AS fila_id, r.id AS registro_id, r.created_at
		FROM insert_reporte_calendario_fila f
		JOIN insert_reporte_calendario c ON c.id = f.calendario_id AND c.estado = 'activo' AND c.eliminado_en IS NULL
		JOIN repositorio_usuarios x ON x.id = f.promotor_usuario_id
		JOIN repositorio_usuarios_reporte u ON u.usuario = x.user
		JOIN insert_reporte_registro r ON r.usuario_id = u.id AND r.pos_id = f.pos_id COLLATE utf8mb4_unicode_ci AND r.fecha_actividad = f.fecha AND r.tipo = 'activaciones' AND r.eliminado_en IS NULL AND r.estado = 'Aprobado'
		WHERE f.estado = 'pendiente'
			AND r.id NOT IN (SELECT f2.registro_id FROM insert_reporte_calendario_fila f2 JOIN insert_reporte_calendario c2 ON c2.id = f2.calendario_id AND c2.eliminado_en IS NULL WHERE f2.registro_id IS NOT NULL)
		ORDER BY f.id, r.id");
	if (!$res) {
		error_log('ep_calendario_cruzar_pendientes: '.$db->error);
		return;
	}
	// Un registro cumple una sola fila y cada fila toma el primer registro libre.
	$filasHechas = [];
	$registrosUsados = [];
	$upd = $db->prepare("UPDATE insert_reporte_calendario_fila SET estado = 'cumplido', registro_id = ?, cumplido_en = ? WHERE id = ? AND estado = 'pendiente'");
	while ($c = $res->fetch_assoc()) {
		$filaId = (int) $c['fila_id'];
		$registroId = (int) $c['registro_id'];
		if (isset($filasHechas[$filaId]) || isset($registrosUsados[$registroId])) {
			continue;
		}
		$cumplidoEn = $c['created_at'];
		$upd->bind_param('isi', $registroId, $cumplidoEn, $filaId);
		$upd->execute();
		$filasHechas[$filaId] = true;
		$registrosUsados[$registroId] = true;
	}
	if ($filasHechas) {
		ep_calendario_cerrar_completos();
	}
}

// Recorre los calendarios activos vencidos y los cierra solos; se llama al cargar la pantalla de Calendario (solo admin).
function ep_calendario_verificar_vencidos(int $usuarioId): void {
	$db = ep_db();
	if (!$db) {
		return;
	}
	$res = $db->query("SELECT id, nombre, canal FROM insert_reporte_calendario WHERE estado = 'activo' AND eliminado_en IS NULL AND vence_en < NOW()");
	require_once __DIR__.'/auditoria_datos.php';
	while ($row = $res->fetch_assoc()) {
		$id = (int) $row['id'];
		$reporteId = ep_calendario_generar_ahora($id, $usuarioId);
		if ($reporteId === null) {
			continue;
		}
		if ($reporteId > 0) {
			ep_auditar('calendario_cierre_auto', 'calendario', $id, 'Se cerró «'.ep_calendario_nombre($row).'» al vencer su plazo', ep_calendario_detalle_cierre($id), 0);
		} else {
			ep_auditar('calendario_cierre_sin_reporte', 'calendario', $id, 'Se cerró «'.ep_calendario_nombre($row).'» al vencer su plazo, pero no se generó el reporte: sus registros ya estaban en otro reporte mensual activo', ep_calendario_detalle_cierre($id), 0);
		}
	}
}

// Reabre un calendario cerrado con un plazo nuevo desde ahora; queda registrado quién y cuándo lo reactivó.
function ep_calendario_reactivar(int $calendarioId, int $usuarioId): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$cal = $db->query('SELECT plazo_dias FROM insert_reporte_calendario WHERE id = '.$calendarioId.' AND eliminado_en IS NULL')->fetch_assoc();
	if (!$cal) {
		return false;
	}
	$ahora = date('Y-m-d H:i:s');
	$venceEn = date('Y-m-d H:i:s', strtotime($ahora.' + '.$cal['plazo_dias'].' days'));
	$stmt = $db->prepare("UPDATE insert_reporte_calendario SET estado = 'activo', cerrado_en = NULL, comentarios_revisados_en = NULL, vence_en = ?, reactivado_por = ?, reactivado_en = ? WHERE id = ?");
	$stmt->bind_param('sisi', $venceEn, $usuarioId, $ahora, $calendarioId);
	return $stmt->execute();
}

// Un calendario cerrado con reporte espera que alguien revise sus comentarios; hasta entonces el PPT no se descarga.
function ep_calendario_por_revisar(array $cal): bool {
	return ($cal['estado'] ?? '') === 'cerrado' && !empty($cal['reporte_mensual_id']) && empty($cal['comentarios_revisados_en']);
}

// ¿Este reporte mensual viene de un calendario que aún espera la revisión de sus comentarios?
function ep_reporte_pendiente_revision(int $reporteId): bool {
	$db = ep_db();
	if (!$db || $reporteId <= 0) {
		return false;
	}
	$stmt = $db->prepare("SELECT 1 FROM insert_reporte_calendario WHERE reporte_mensual_id = ? AND estado = 'cerrado' AND comentarios_revisados_en IS NULL AND eliminado_en IS NULL LIMIT 1");
	$stmt->bind_param('i', $reporteId);
	$stmt->execute();
	$pendiente = $stmt->get_result()->num_rows > 0;
	$stmt->close();
	return $pendiente;
}

// Fija los comentarios del calendario y de su reporte, y lo deja listo para descargar; false si no estaba esperando revisión.
function ep_calendario_finalizar(int $calendarioId, ?string $comentarios): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$cal = ep_calendario_obtener($calendarioId);
	if (!$cal || !ep_calendario_por_revisar($cal)) {
		return false;
	}
	$db->begin_transaction();
	$stmt = $db->prepare("UPDATE insert_reporte_calendario SET comentarios = ?, comentarios_revisados_en = NOW() WHERE id = ? AND comentarios_revisados_en IS NULL");
	$stmt->bind_param('si', $comentarios, $calendarioId);
	$ok = $stmt->execute() && $stmt->affected_rows === 1;
	$stmt->close();
	if ($ok) {
		$reporteId = (int) $cal['reporte_mensual_id'];
		$stmt = $db->prepare('UPDATE insert_reporte_mensual SET comentarios = ? WHERE id = ?');
		$stmt->bind_param('si', $comentarios, $reporteId);
		$ok = $stmt->execute();
		$stmt->close();
	}
	$ok ? $db->commit() : $db->rollback();
	return $ok;
}

// Cabecera de un calendario no eliminado (para editar o eliminar); null si no existe.
function ep_calendario_obtener(int $calendarioId): ?array {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$stmt = $db->prepare('SELECT id, nombre, canal, estado, comentarios, comentarios_revisados_en, vence_en, cerrado_en, reporte_mensual_id FROM insert_reporte_calendario WHERE id = ? AND eliminado_en IS NULL');
	$stmt->bind_param('i', $calendarioId);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc() ?: null;
}

// Nombre con el que se muestra un calendario (el mismo título que usa su reporte).
function ep_calendario_nombre(array $cal): string {
	return trim((string) ($cal['nombre'] ?? '')) ?: 'Activaciones '.$cal['canal'];
}

// Una fila tal como está ahora, para comparar antes de editarla.
function ep_calendario_fila_obtener(int $filaId): ?array {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$stmt = $db->prepare('SELECT id, fecha, ciudad, punto_venta, promotor_nombre, supervisor_nombre FROM insert_reporte_calendario_fila WHERE id = ?');
	$stmt->bind_param('i', $filaId);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc() ?: null;
}

// Detalle de auditoría de un cierre: cuántas filas se cumplieron y qué reporte salió.
function ep_calendario_detalle_cierre(int $calendarioId): array {
	require_once __DIR__.'/auditoria_datos.php';
	$filas = ep_calendario_filas_tabla($calendarioId);
	$cumplidas = count(array_filter($filas, fn($f) => $f['estado'] === 'cumplido'));
	$cal = ep_calendario_obtener($calendarioId);
	$db = ep_db();
	$detalle = [ep_auditoria_dato('Cumplidas', $cumplidas.' de '.count($filas))];
	// El último registro que cumplió una fila: dice quién lo completó.
	$res = $db ? $db->query('SELECT r.codigo, f.promotor_nombre, f.punto_venta FROM insert_reporte_calendario_fila f JOIN insert_reporte_registro r ON r.id = f.registro_id WHERE f.calendario_id = '.$calendarioId." AND f.estado = 'cumplido' ORDER BY f.cumplido_en DESC, f.id DESC LIMIT 1") : null;
	if ($res && ($u = $res->fetch_assoc())) {
		$detalle[] = ep_auditoria_dato('Último registro', $u['codigo'].' · '.$u['promotor_nombre'].' · '.$u['punto_venta']);
	}
	$titulo = '';
	if ($db && !empty($cal['reporte_mensual_id'])) {
		$t = $db->query('SELECT titulo FROM insert_reporte_mensual WHERE id = '.(int) $cal['reporte_mensual_id'])->fetch_assoc();
		$titulo = $t ? ' · '.$t['titulo'] : '';
	}
	$detalle[] = ep_auditoria_dato('Reporte', !empty($cal['reporte_mensual_id']) ? 'Reporte mensual #'.$cal['reporte_mensual_id'].$titulo : 'No se generó (sus registros ya estaban en otro reporte o ninguno llegó)');
	return $detalle;
}

// Editable: calendario activo, fila pendiente (una cumplida queda "quemada") y fecha de hoy o después.
function ep_calendario_fila_es_editable(int $calendarioId, int $filaId): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$stmt = $db->prepare("SELECT f.fecha FROM insert_reporte_calendario_fila f JOIN insert_reporte_calendario c ON c.id = f.calendario_id WHERE f.id = ? AND f.calendario_id = ? AND f.estado = 'pendiente' AND c.estado = 'activo' AND c.eliminado_en IS NULL");
	$stmt->bind_param('ii', $filaId, $calendarioId);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	return $fila && $fila['fecha'] >= date('Y-m-d');
}

// Cambia una fila ya revalidada (nunca la fecha) y deja quién y cuándo la editó.
function ep_calendario_fila_editar(int $filaId, string $posId, string $puntoVenta, ?string $ciudad, int $promotorId, string $promotorNombre, ?string $supervisorNombre, int $editadoPor): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$ahora = date('Y-m-d H:i:s');
	$stmt = $db->prepare('UPDATE insert_reporte_calendario_fila SET editado_en = ?, editado_por = ?, pos_id = ?, punto_venta = ?, ciudad = ?, promotor_usuario_id = ?, promotor_nombre = ?, supervisor_nombre = ? WHERE id = ?');
	$stmt->bind_param('sisssissi', $ahora, $editadoPor, $posId, $puntoVenta, $ciudad, $promotorId, $promotorNombre, $supervisorNombre, $filaId);
	return $stmt->execute();
}

// Cambia los comentarios del reporte de un calendario (ya limpios); se permite mientras siga activo.
function ep_calendario_comentarios_guardar(int $calendarioId, ?string $comentarios): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$stmt = $db->prepare("UPDATE insert_reporte_calendario SET comentarios = ? WHERE id = ? AND estado = 'activo' AND eliminado_en IS NULL");
	$stmt->bind_param('si', $comentarios, $calendarioId);
	return $stmt->execute();
}

// Borrado lógico del calendario; sus filas quedan en la base y un reporte mensual ya generado no se toca.
function ep_calendario_eliminar(int $calendarioId): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$stmt = $db->prepare('UPDATE insert_reporte_calendario SET eliminado_en = NOW() WHERE id = ? AND eliminado_en IS NULL');
	$stmt->bind_param('i', $calendarioId);
	return $stmt->execute() && $stmt->affected_rows > 0;
}

// Al eliminar un registro, la fila que había cumplido vuelve a pendiente.
function ep_calendario_liberar_registro(int $registroId): void {
	$db = ep_db();
	if (!$db) {
		return;
	}
	$stmt = $db->prepare("UPDATE insert_reporte_calendario_fila SET estado = 'pendiente', registro_id = NULL, cumplido_en = NULL WHERE registro_id = ?");
	$stmt->bind_param('i', $registroId);
	$stmt->execute();
}

// Al guardar un registro de Activaciones: si calza con una fila pendiente de un calendario activo, la marca cumplida.
function ep_calendario_cruzar_registro(int $registroId, int $usuarioId, ?string $posId, ?string $fechaActividad): void {
	$db = ep_db();
	if (!$db || !$posId || !$fechaActividad) {
		return;
	}
	// La fila guarda el id de Xplora y la sesión el de la app: se unen por nombre de usuario.
	$stmt = $db->prepare("SELECT f.id FROM insert_reporte_calendario_fila f
		JOIN insert_reporte_calendario c ON c.id = f.calendario_id
		JOIN repositorio_usuarios x ON x.id = f.promotor_usuario_id
		JOIN repositorio_usuarios_reporte u ON u.usuario = x.user
		WHERE c.estado = 'activo' AND c.eliminado_en IS NULL AND f.estado = 'pendiente' AND f.pos_id = ? AND u.id = ? AND f.fecha = ? LIMIT 1");
	$stmt->bind_param('sis', $posId, $usuarioId, $fechaActividad);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	if (!$fila) {
		return;
	}
	$ahora = date('Y-m-d H:i:s');
	$upd = $db->prepare("UPDATE insert_reporte_calendario_fila SET estado = 'cumplido', registro_id = ?, cumplido_en = ? WHERE id = ?");
	$upd->bind_param('isi', $registroId, $ahora, $fila['id']);
	$upd->execute();
	// Si con este registro el calendario queda completo, se cierra y genera su reporte; un fallo aquí nunca debe romper el envío del promotor.
	try {
		ep_calendario_cerrar_completos();
	} catch (Throwable $e) {
		error_log('ep_calendario_cerrar_completos: '.$e->getMessage());
	}
}
