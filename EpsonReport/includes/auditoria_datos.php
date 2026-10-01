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
		'calendario_cierre_completo' => 'Calendario cerrado al completarse',
		'calendario_reactivar'   => 'Reactivó un calendario',
		'calendario_eliminar'    => 'Eliminó un calendario',
		'registro_eliminar'      => 'Eliminó un registro',
		'registro_duplicado'     => 'Intentó enviar un registro duplicado',
		'reporte_crear'          => 'Creó un reporte mensual',
		'reporte_eliminar'       => 'Eliminó un reporte mensual',
		'actividad_crear'        => 'Creó una actividad',
		'actividad_activar'      => 'Activó una actividad',
		'actividad_desactivar'   => 'Desactivó una actividad',
		'usuario_crear'          => 'Creó un usuario',
		'usuario_editar'         => 'Cambió correo o rol de un usuario',
		'usuario_clave'          => 'Cambió la clave de un usuario',
		'usuario_foto'           => 'Cambió la foto de un usuario',
		'usuario_ruta'           => 'Cambió categorías o supervisores de un usuario',
		'registro_aprobar'       => 'Aprobó un registro',
		'registro_devolver'      => 'Devolvió un registro',
		'usuario_activar'        => 'Reactivó un usuario',
		'usuario_desactivar'     => 'Desactivó un usuario',
	];
}

// Línea de identidad de un calendario para la bitácora: nombre, número, canal y fechas, así dos con el mismo nombre no se confunden.
function ep_auditoria_calendario($db, int $calendarioId): ?array {
	$stmt = $db->prepare('SELECT nombre, canal, desde, hasta FROM insert_reporte_calendario WHERE id = ?');
	if (!$stmt) {
		return null;
	}
	$stmt->bind_param('i', $calendarioId);
	$stmt->execute();
	$c = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$c) {
		return null;
	}
	$nombre = trim((string) $c['nombre']) ?: 'Activaciones '.$c['canal'];
	$fechas = $c['desde'] && $c['hasta'] ? date('d/m', strtotime($c['desde'])).' al '.date('d/m/Y', strtotime($c['hasta'])) : '';
	return ep_auditoria_dato('Calendario', $nombre.' · #'.$calendarioId.' · '.$c['canal'].($fechas !== '' ? ' · '.$fechas : ''));
}

// IP del cliente: detrás del balanceador de Azure REMOTE_ADDR es una dirección interna, la real viene en X-Forwarded-For.
function ep_ip_cliente(): string {
	foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_CLIENT_IP', 'REMOTE_ADDR'] as $clave) {
		foreach (explode(',', (string) ($_SERVER[$clave] ?? '')) as $candidata) {
			$ip = preg_replace('/:\d+$/', '', trim($candidata));
			if (filter_var($ip, FILTER_VALIDATE_IP) && !str_starts_with($ip, '169.254.') && !str_starts_with($ip, '127.')) {
				return $ip;
			}
		}
	}
	return '';
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
	if ($entidad === 'calendario' && $entidadId) {
		$identidad = ep_auditoria_calendario($db, $entidadId);
		if ($identidad) {
			array_unshift($detalle, $identidad);
		}
	}
	$json = $detalle ? json_encode(array_values($detalle), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
	$ip = $usuarioId > 0 ? substr(ep_ip_cliente(), 0, 45) : null;
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

// Pone en cada movimiento el nombre de lo afectado (usuario, registro, reporte, actividad) en vez de un número suelto.
function ep_auditoria_afectados($db, array &$filas): void {
	$consultas = [
		'usuario' => "SELECT id, CONCAT(COALESCE(NULLIF(nombre, ''), usuario), IF(nombre <> '' AND nombre <> usuario, CONCAT(' (', usuario, ')'), '')) AS txt FROM repositorio_usuarios_reporte WHERE id IN (%s)",
		'registro' => 'SELECT id, codigo AS txt FROM insert_reporte_registro WHERE id IN (%s)',
		'reporte' => "SELECT id, CONCAT(COALESCE(NULLIF(titulo, ''), 'Reporte'), ' (#', id, ')') AS txt FROM insert_reporte_mensual WHERE id IN (%s)",
		'actividad' => 'SELECT id, nombre AS txt FROM insert_reporte_actividad WHERE id IN (%s)',
	];
	$nombres = [];
	foreach ($consultas as $entidad => $sql) {
		$ids = array_unique(array_map('intval', array_column(array_filter($filas, fn($f) => $f['entidad'] === $entidad && $f['entidad_id']), 'entidad_id')));
		if (!$ids) {
			continue;
		}
		$res = $db->query(sprintf($sql, implode(',', $ids)));
		foreach ($res ?: [] as $r) {
			$nombres[$entidad][(int) $r['id']] = $r['txt'];
		}
	}
	foreach ($filas as &$f) {
		$f['afectado'] = (string) ($nombres[$f['entidad']][(int) $f['entidad_id']] ?? '');
	}
	unset($f);
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
	unset($f);
	// Los movimientos guardados antes de que se anotara la identidad del calendario la reciben al mostrarse.
	foreach ($filas as &$f) {
		$sinIdentidad = !in_array('Calendario', array_column($f['detalle'], 'campo'), true);
		if ($f['entidad'] === 'calendario' && $f['entidad_id'] && $sinIdentidad && ($identidad = ep_auditoria_calendario($db, (int) $f['entidad_id']))) {
			array_unshift($f['detalle'], $identidad);
		}
	}
	unset($f);
	ep_auditoria_afectados($db, $filas);
	return $filas;
}
