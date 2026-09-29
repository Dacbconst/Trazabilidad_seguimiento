<?php
// Calendario de Activaciones: se arma en filas (fecha + punto de venta + promotor) y el sistema cruza solo lo real. Sin borrador.
require_once __DIR__.'/db.php';
require_once __DIR__.'/reportes_datos.php';

const EP_CAL_RUTERO_CACHE_TTL = 300;

// Cascada real de Xplora, directo a tablas base (no a la vista lvi_rutero, que agrupa por fecha y es lenta); cachea 5 min.
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
	$stmt = $db->prepare("SELECT DISTINCT pdvs.city AS ciudad, u.id AS promotor_id, u.usuario AS promotor_nombre, pdvs.pos_id, pdvs.pos_name AS punto_venta, sup.supervisor
		FROM rutero_pdv rutero
		JOIN repositorio_locales_dtt2 pdvs ON pdvs.id = rutero.id_pdv AND pdvs.channel = ? AND pdvs.activar = 'SI'
		JOIN repositorio_usuarios usu ON usu.id = rutero.id_usuario
		JOIN repositorio_usuarios_reporte u ON u.usuario = usu.user AND u.rol = 'promotor' AND u.status = 'activo'
		LEFT JOIN repositorio_supervisores sup ON sup.id = rutero.id_supervisor
		WHERE rutero.status = 1 AND rutero.habilitado = 1
		ORDER BY pdvs.city, u.usuario, pdvs.pos_name");
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

// Revalida en el servidor una combinación promotor+punto de venta; null si no existe de verdad en el rutero.
function ep_calendario_rutero_validar(string $canal, int $promotorId, string $posId): ?array {
	foreach (ep_calendario_rutero($canal) as $f) {
		if ($f['promotor_id'] === $promotorId && $f['pos_id'] === $posId) {
			return $f;
		}
	}
	return null;
}

// Lista de calendarios con sus filas, más recientes primero. $soloActivos filtra los ya cerrados.
function ep_calendario_listar(bool $soloActivos = false): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$where = $soloActivos ? "WHERE c.estado = 'activo'" : '';
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

// Crea el calendario y sus filas en una transacción; se activa de inmediato y desde/hasta se calculan solos.
function ep_calendario_crear(string $nombre, string $canal, int $plazoDias, array $filas, int $creadoPor): ?int {
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
	$stmt = $db->prepare('INSERT INTO insert_reporte_calendario (nombre, canal, plazo_dias, desde, hasta, activado_en, vence_en, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
	$stmt->bind_param('ssisssi', $nombre, $canal, $plazoDias, $desde, $hasta, $activadoEn, $venceEn, $creadoPor);
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

// Cierra el calendario (manual con "Generar ahora" o automático al vencer) y genera el reporte mensual con lo cumplido.
function ep_calendario_generar_ahora(int $calendarioId, int $usuarioId): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$cal = $db->query('SELECT * FROM insert_reporte_calendario WHERE id = '.$calendarioId)->fetch_assoc();
	if (!$cal || $cal['estado'] !== 'activo') {
		return false;
	}
	$res = $db->query('SELECT registro_id FROM insert_reporte_calendario_fila WHERE calendario_id = '.$calendarioId." AND estado = 'cumplido' AND registro_id IS NOT NULL");
	$ids = [];
	while ($row = $res->fetch_assoc()) {
		$ids[] = (int) $row['registro_id'];
	}
	$mes = date('Y-m', strtotime($cal['desde']));
	$titulo = $cal['nombre'] ?: ('Activaciones '.$cal['canal']);
	$snapshot = json_encode(['desde' => $cal['desde'], 'hasta' => $cal['hasta'], 'registros' => $ids], JSON_UNESCAPED_UNICODE);
	$reporteId = !empty($ids) ? ep_reporte_crear('activaciones', $mes, $titulo, null, null, null, $ids, $snapshot, $usuarioId) : 0;

	$ahora = date('Y-m-d H:i:s');
	$stmt = $db->prepare("UPDATE insert_reporte_calendario SET estado = 'cerrado', cerrado_en = ?, reporte_mensual_id = ? WHERE id = ?");
	$reporteIdParam = $reporteId > 0 ? $reporteId : null;
	$stmt->bind_param('sii', $ahora, $reporteIdParam, $calendarioId);
	return $stmt->execute();
}

// Recorre los calendarios activos vencidos y los cierra solos; se llama al cargar la pantalla de Calendario (solo admin).
function ep_calendario_verificar_vencidos(int $usuarioId): void {
	$db = ep_db();
	if (!$db) {
		return;
	}
	$res = $db->query("SELECT id FROM insert_reporte_calendario WHERE estado = 'activo' AND vence_en < NOW()");
	while ($row = $res->fetch_assoc()) {
		ep_calendario_generar_ahora((int) $row['id'], $usuarioId);
	}
}

// Reabre un calendario cerrado con un plazo nuevo desde ahora; queda registrado quién y cuándo lo reactivó.
function ep_calendario_reactivar(int $calendarioId, int $usuarioId): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$cal = $db->query('SELECT plazo_dias FROM insert_reporte_calendario WHERE id = '.$calendarioId)->fetch_assoc();
	if (!$cal) {
		return false;
	}
	$ahora = date('Y-m-d H:i:s');
	$venceEn = date('Y-m-d H:i:s', strtotime($ahora.' + '.$cal['plazo_dias'].' days'));
	$stmt = $db->prepare("UPDATE insert_reporte_calendario SET estado = 'activo', cerrado_en = NULL, vence_en = ?, reactivado_por = ?, reactivado_en = ? WHERE id = ?");
	$stmt->bind_param('sisi', $venceEn, $usuarioId, $ahora, $calendarioId);
	return $stmt->execute();
}

// Edita punto de venta o promotor de una fila (nunca la fecha), ya revalidados; solo si el calendario sigue activo y esa fecha no pasó.
function ep_calendario_fila_editar(int $filaId, ?string $posId, ?string $puntoVenta, ?string $ciudad, ?int $promotorId, ?string $promotorNombre, ?string $supervisorNombre, int $editadoPor): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$fila = $db->query("SELECT f.fecha, c.estado FROM insert_reporte_calendario_fila f JOIN insert_reporte_calendario c ON c.id = f.calendario_id WHERE f.id = $filaId")->fetch_assoc();
	if (!$fila || $fila['estado'] !== 'activo' || $fila['fecha'] < date('Y-m-d')) {
		return false;
	}
	$ahora = date('Y-m-d H:i:s');
	$sets = ['editado_en = ?', 'editado_por = ?', 'pos_id = ?', 'punto_venta = ?', 'ciudad = ?', 'promotor_usuario_id = ?', 'promotor_nombre = ?', 'supervisor_nombre = ?'];
	$stmt = $db->prepare('UPDATE insert_reporte_calendario_fila SET '.implode(', ', $sets).' WHERE id = ?');
	$stmt->bind_param('sissssssi', $ahora, $editadoPor, $posId, $puntoVenta, $ciudad, $promotorId, $promotorNombre, $supervisorNombre, $filaId);
	return $stmt->execute();
}

// Al guardar un registro de Activaciones: si calza con una fila pendiente de un calendario activo, la marca cumplida.
function ep_calendario_cruzar_registro(int $registroId, int $usuarioId, ?string $posId, ?string $fechaActividad): void {
	$db = ep_db();
	if (!$db || !$posId || !$fechaActividad) {
		return;
	}
	$stmt = $db->prepare("SELECT f.id FROM insert_reporte_calendario_fila f JOIN insert_reporte_calendario c ON c.id = f.calendario_id WHERE c.estado = 'activo' AND f.estado = 'pendiente' AND f.pos_id = ? AND f.promotor_usuario_id = ? AND f.fecha = ? LIMIT 1");
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
}
