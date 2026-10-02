<?php
// Reportes mensuales (insert_reporte_mensual): guardan la selección congelada (copia de los registros, calendario, programadas, comentarios); el PPTX se arma al descargar.
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';

function ep_reportes_listar(): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$res = $db->query('SELECT m.id, m.tipo, m.mes, m.titulo, m.calendario, m.programadas, m.total_registros, m.created_at, SUBSTRING(m.snapshot, 1, 60) AS snapshot_ini, u.nombre AS creador FROM insert_reporte_mensual m LEFT JOIN repositorio_usuarios_reporte u ON u.id = m.creado_por WHERE m.eliminado_en IS NULL'.(ep_es_supervisor() ? ' AND m.creado_por = '.(int) $_SESSION['usuario_id'] : '').' ORDER BY m.created_at DESC, m.id DESC LIMIT 200');
	return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function ep_reporte_obtener(int $id): ?array {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$stmt = $db->prepare('SELECT id, tipo, mes, titulo, calendario, programadas, comentarios, registros, snapshot, total_registros FROM insert_reporte_mensual WHERE id = ? AND eliminado_en IS NULL'.(ep_es_supervisor() ? ' AND creado_por = '.(int) $_SESSION['usuario_id'] : '').' LIMIT 1');
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$fila) {
		return null;
	}
	$fila['ids'] = array_map('intval', json_decode((string) $fila['registros'], true) ?: []);
	$fila['snapshot'] = json_decode((string) $fila['snapshot'], true) ?: null;
	return $fila;
}

// Ids de los registros que ya no se pueden meter en OTRO reporte: los que ya están en un reporte guardado, y los que ya cumplieron
// la fila de un calendario todavía activo (su reporte se arma solo al cerrarse, no antes de forma manual). $exceptoCalendarioId
// deja pasar las filas de ESE calendario, así puede reclamar sus propios registros al cerrarse.
function ep_registros_ocupados(?int $exceptoCalendarioId = null): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$ocupados = [];
	$res = $db->query('SELECT registros FROM insert_reporte_mensual WHERE eliminado_en IS NULL');
	foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $fila) {
		foreach (json_decode((string) $fila['registros'], true) ?: [] as $id) {
			$ocupados[(int) $id] = true;
		}
	}
	$sql = "SELECT f.registro_id FROM insert_reporte_calendario_fila f JOIN insert_reporte_calendario c ON c.id = f.calendario_id
		WHERE c.estado = 'activo' AND c.eliminado_en IS NULL AND f.estado = 'cumplido' AND f.registro_id IS NOT NULL"
		.($exceptoCalendarioId ? ' AND c.id <> '.$exceptoCalendarioId : '');
	$res2 = $db->query($sql);
	foreach ($res2 ? $res2->fetch_all(MYSQLI_ASSOC) : [] as $fila) {
		$ocupados[(int) $fila['registro_id']] = true;
	}
	return array_keys($ocupados);
}

// Reporte mensual guardado (no eliminado) que ya incluye este registro, o null si no está en ninguno.
function ep_registro_en_reporte(int $registroId): ?array {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$res = $db->query('SELECT id, titulo, tipo, registros FROM insert_reporte_mensual WHERE eliminado_en IS NULL');
	foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $fila) {
		$ids = array_map('intval', json_decode((string) $fila['registros'], true) ?: []);
		if (in_array($registroId, $ids, true)) {
			return ['id' => (int) $fila['id'], 'titulo' => trim((string) $fila['titulo']) ?: $fila['tipo']];
		}
	}
	return null;
}

function ep_reporte_crear(string $tipo, string $mes, ?string $titulo, ?string $calendario, ?int $programadas, ?string $comentarios, array $ids, string $snapshot, int $creadoPor): int {
	$db = ep_db();
	if (!$db) {
		return 0;
	}
	$idsJson = json_encode(array_values(array_map('intval', $ids)));
	$total = count($ids);
	$stmt = $db->prepare('INSERT INTO insert_reporte_mensual (tipo, mes, titulo, calendario, programadas, comentarios, registros, snapshot, total_registros, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
	if (!$stmt) {
		error_log('ep_reporte_crear: '.$db->error);
		return 0;
	}
	$stmt->bind_param('ssssisssii', $tipo, $mes, $titulo, $calendario, $programadas, $comentarios, $idsJson, $snapshot, $total, $creadoPor);
	$ok = $stmt->execute();
	$id = $ok ? (int) $db->insert_id : 0;
	if (!$ok) {
		error_log('ep_reporte_crear: '.$stmt->error);
	}
	$stmt->close();
	return $id;
}

function ep_reporte_eliminar(int $id): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$stmt = $db->prepare('UPDATE insert_reporte_mensual SET eliminado_en = NOW() WHERE id = ? AND eliminado_en IS NULL');
	$stmt->bind_param('i', $id);
	$ok = $stmt->execute();
	$stmt->close();
	return $ok;
}
