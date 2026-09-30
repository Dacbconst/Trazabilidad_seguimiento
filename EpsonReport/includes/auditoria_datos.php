<?php
// Bitácora de acciones de admin (insert_reporte_auditoria): quién hizo qué, sobre qué y cuándo. Solo se agrega, nunca se edita ni se borra.
require_once __DIR__.'/db.php';

const EP_AUDITORIA_LIMITE = 1000;

// Etiqueta legible de cada acción, en el orden en que aparecen en el filtro.
function ep_auditoria_acciones(): array {
	return [
		'calendario_crear'       => 'Creó un calendario',
		'calendario_fila_editar' => 'Cambió una fila del calendario',
		'calendario_comentarios' => 'Cambió comentarios del calendario',
		'calendario_generar'     => 'Generó el reporte del calendario',
		'calendario_cierre_auto' => 'Calendario cerrado al vencer',
		'calendario_reactivar'   => 'Reactivó un calendario',
		'calendario_eliminar'    => 'Eliminó un calendario',
		'registro_eliminar'      => 'Eliminó un registro',
		'reporte_crear'          => 'Creó un reporte mensual',
		'reporte_eliminar'       => 'Eliminó un reporte mensual',
		'actividad_crear'        => 'Creó una actividad',
		'actividad_activar'      => 'Activó una actividad',
		'actividad_desactivar'   => 'Desactivó una actividad',
	];
}

// Registra un evento; $usuarioId null = quien tiene la sesión, 0 = el sistema. Si falla solo queda en el log, la acción no se corta.
function ep_auditar(string $accion, string $entidad, ?int $entidadId, string $resumen, array $detalle = [], ?int $usuarioId = null): void {
	$db = ep_db();
	if (!$db) {
		return;
	}
	$usuarioId = $usuarioId ?? (int) ($_SESSION['usuario_id'] ?? 0);
	$usuarioParam = $usuarioId > 0 ? $usuarioId : null;
	$nombre = $usuarioId > 0 ? (string) ($_SESSION['nombre'] ?? ($_SESSION['usuario'] ?? '')) : 'Sistema';
	$resumen = mb_substr($resumen, 0, 255);
	$json = $detalle ? json_encode(array_values($detalle), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
	$ip = $usuarioId > 0 ? substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45) : null;
	$stmt = $db->prepare('INSERT INTO insert_reporte_auditoria (usuario_id, usuario_nombre, accion, entidad, entidad_id, resumen, detalle, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
	if (!$stmt) {
		error_log('ep_auditar: '.$db->error);
		return;
	}
	$stmt->bind_param('isssisss', $usuarioParam, $nombre, $accion, $entidad, $entidadId, $resumen, $json, $ip);
	if (!$stmt->execute()) {
		error_log('ep_auditar: '.$stmt->error);
	}
	$stmt->close();
}

// Fila de detalle "antes → después"; devuelve null si no cambió, para filtrarla con array_filter.
function ep_auditoria_cambio(string $campo, $antes, $despues): ?array {
	$antes = trim((string) $antes);
	$despues = trim((string) $despues);
	return $antes === $despues ? null : ['campo' => $campo, 'antes' => $antes, 'despues' => $despues];
}

// Fila de detalle informativa (sin antes/después).
function ep_auditoria_dato(string $campo, $valor): array {
	return ['campo' => $campo, 'valor' => trim((string) $valor)];
}

// Últimos eventos, más recientes primero; los filtros los aplica la pantalla en el navegador.
function ep_auditoria_listar(int $limite = EP_AUDITORIA_LIMITE): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$stmt = $db->prepare('SELECT a.id, a.usuario_id, a.usuario_nombre, u.usuario, a.accion, a.entidad, a.entidad_id, a.resumen, a.detalle, a.ip, a.created_at FROM insert_reporte_auditoria a LEFT JOIN repositorio_usuarios_reporte u ON u.id = a.usuario_id ORDER BY a.id DESC LIMIT ?');
	if (!$stmt) {
		error_log('ep_auditoria_listar: '.$db->error);
		return [];
	}
	$stmt->bind_param('i', $limite);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	foreach ($filas as &$f) {
		$f['detalle'] = json_decode((string) $f['detalle'], true) ?: [];
	}
	return $filas;
}
