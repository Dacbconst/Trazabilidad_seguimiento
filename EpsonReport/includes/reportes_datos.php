<?php
// Reportes mensuales (insert_reporte_mensual): guardan solo la selección (ids de registros, calendario, programadas); el PPTX se arma al descargar.
require_once __DIR__.'/db.php';

function ep_reportes_listar(): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$res = $db->query('SELECT m.id, m.tipo, m.mes, m.titulo, m.calendario, m.programadas, m.total_registros, m.created_at, u.nombre AS creador FROM insert_reporte_mensual m LEFT JOIN repositorio_usuarios_reporte u ON u.id = m.creado_por WHERE m.eliminado_en IS NULL ORDER BY m.created_at DESC, m.id DESC LIMIT 200');
	return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function ep_reporte_obtener(int $id): ?array {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$stmt = $db->prepare('SELECT id, tipo, mes, titulo, calendario, programadas, registros, total_registros FROM insert_reporte_mensual WHERE id = ? AND eliminado_en IS NULL LIMIT 1');
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$fila) {
		return null;
	}
	$fila['ids'] = array_map('intval', json_decode((string) $fila['registros'], true) ?: []);
	return $fila;
}

function ep_reporte_crear(string $tipo, string $mes, ?string $titulo, ?string $calendario, ?int $programadas, array $ids, int $creadoPor): int {
	$db = ep_db();
	if (!$db) {
		return 0;
	}
	$idsJson = json_encode(array_values(array_map('intval', $ids)));
	$total = count($ids);
	$stmt = $db->prepare('INSERT INTO insert_reporte_mensual (tipo, mes, titulo, calendario, programadas, registros, total_registros, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
	if (!$stmt) {
		error_log('ep_reporte_crear: '.$db->error);
		return 0;
	}
	$stmt->bind_param('ssssisii', $tipo, $mes, $titulo, $calendario, $programadas, $idsJson, $total, $creadoPor);
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
