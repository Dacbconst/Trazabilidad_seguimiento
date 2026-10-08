<?php
// Aprobación de registros: el promotor envía, el supervisor de su categoría aprueba o devuelve con motivo (el admin ve todo).
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';
require_once __DIR__.'/auditoria_datos.php';

const EP_MOTIVO_MIN = 8;
const EP_MOTIVO_MAX = 300;

// Mientras no existan las columnas nuevas, el flujo anterior sigue igual: todo registro nace aprobado.
function ep_aprobacion_activa($db = null): bool {
	static $activa = null;
	if ($activa === null) {
		$db = $db ?? ep_db();
		$res = $db ? $db->query("SHOW COLUMNS FROM insert_reporte_registro LIKE 'supervisor_id'") : false;
		$activa = $res && $res->num_rows > 0;
	}
	return $activa;
}

// Columnas de ruta en el usuario: quién es su supervisor de canales y su supervisor de retail.
function ep_usuarios_tiene_ruta($db): bool {
	static $tiene = null;
	if ($tiene === null) {
		$res = $db->query("SHOW COLUMNS FROM repositorio_usuarios_reporte LIKE 'supervisor_retail_id'");
		$tiene = $res && $res->num_rows > 0;
	}
	return $tiene;
}

// Un supervisor solo ve los registros que le tocan; el admin ve todos.
function ep_aprobacion_filtro_alcance($db): string {
	if (!ep_es_supervisor() || !ep_aprobacion_activa($db)) {
		return '';
	}
	$id = (int) ($_SESSION['usuario_id'] ?? 0);
	// Competencia con puntos de venta de más de un canal puede sumar un supervisor extra dentro del JSON (ver guardar_registro.php);
	// el resto de los tipos nunca tiene esa clave, así que esto no les cambia nada.
	return ' AND (r.supervisor_id = '.$id.' OR (JSON_VALID(r.valores) AND JSON_CONTAINS(r.valores, CAST('.$id.' AS JSON), \'$.supervisores_extra\')))';
}

// Supervisor de un registro según la categoría del punto de venta: retail va a su supervisor de retail, lo demás al de canales.
function ep_supervisor_asignado(int $usuarioId, ?string $canal): ?int {
	$db = ep_db();
	if (!$db || !ep_usuarios_tiene_ruta($db)) {
		return null;
	}
	$stmt = $db->prepare('SELECT categorias, supervisor_canales_id, supervisor_retail_id FROM repositorio_usuarios_reporte WHERE id = ? LIMIT 1');
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$fila) {
		return null;
	}
	return ep_supervisor_de_fila($fila, (string) $canal);
}

// Supervisor que corresponde a un promotor (fila de usuarios) según el canal del punto de venta.
function ep_supervisor_de_fila(array $fila, string $canal): ?int {
	$canal = strtoupper($canal);
	if ($canal === 'RETAIL') {
		$id = $fila['supervisor_retail_id'];
	} elseif ($canal === 'CANALES') {
		$id = $fila['supervisor_canales_id'];
	} else {
		// Oficina, eventos, ferias, bodega: según la categoría del promotor; si reporta a dos, va al de canales.
		$id = $fila['categorias'] === 'retail' ? $fila['supervisor_retail_id'] : ($fila['supervisor_canales_id'] ?: $fila['supervisor_retail_id']);
	}
	return $id ? (int) $id : null;
}

// Usuarios (en mayúsculas, como en Xplora) de este supervisor; con $canal, solo los que le reportan en ese canal.
function ep_promotores_de_supervisor(int $supervisorId, ?string $canal = null): array {
	$db = ep_db();
	if (!$db || !ep_usuarios_tiene_ruta($db)) {
		return [];
	}
	$stmt = $db->prepare('SELECT usuario, categorias, supervisor_canales_id, supervisor_retail_id FROM repositorio_usuarios_reporte WHERE supervisor_canales_id = ? OR supervisor_retail_id = ?');
	$stmt->bind_param('ii', $supervisorId, $supervisorId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	if ($canal !== null) {
		$filas = array_filter($filas, fn($f) => ep_supervisor_de_fila($f, $canal) === $supervisorId);
	}
	return array_values(array_map(fn($f) => mb_strtoupper($f['usuario'], 'UTF-8'), $filas));
}

// Categorías de punto de venta que gestiona un supervisor: el de retail, todas menos canales; el de canales, todas menos retail.
function ep_supervisor_canales_gestion(int $supervisorId): array {
	require_once __DIR__.'/pdv_datos.php';
	$todos = ep_todos_los_canales();
	$db = ep_db();
	if (!$db || !ep_usuarios_tiene_ruta($db)) {
		return $todos;
	}
	$fila = $db->query('SELECT COALESCE(SUM(supervisor_retail_id = '.$supervisorId.'), 0) AS r, COALESCE(SUM(supervisor_canales_id = '.$supervisorId.'), 0) AS c FROM repositorio_usuarios_reporte')->fetch_assoc();
	$esRetail = (int) $fila['r'] > 0;
	$esCanales = (int) $fila['c'] > 0;
	if ($esRetail && !$esCanales) {
		return array_values(array_diff($todos, ['CANALES']));
	}
	if ($esCanales && !$esRetail) {
		return array_values(array_diff($todos, ['RETAIL']));
	}
	return $todos;
}

// Cantidad de registros por estado dentro del alcance del usuario (para los contadores).
function ep_aprobaciones_contar(): array {
	static $cuentas = null;
	if ($cuentas !== null) {
		return $cuentas;
	}
	$cuentas = ['Pendiente' => 0, 'Devuelto' => 0];
	$db = ep_db();
	if (!$db || !ep_aprobacion_activa($db)) {
		return $cuentas;
	}
	$res = $db->query("SELECT r.estado, COUNT(*) AS n FROM insert_reporte_registro r WHERE r.eliminado_en IS NULL AND r.estado IN ('Pendiente', 'Devuelto')".ep_aprobacion_filtro_alcance($db).' GROUP BY r.estado');
	foreach ($res ?: [] as $f) {
		$cuentas[$f['estado']] = (int) $f['n'];
	}
	return $cuentas;
}

// Registro por código solo si el usuario puede gestionarlo (admin, o el supervisor al que le toca).
function ep_aprobacion_registro(string $codigo): ?array {
	$db = ep_db();
	if (!$db || !ep_aprobacion_activa($db) || !ep_es_gestor()) {
		return null;
	}
	$stmt = $db->prepare('SELECT r.id, r.codigo, r.estado, r.tipo, r.usuario_id, r.pos_id, r.punto_venta, r.fecha_actividad, r.supervisor_id FROM insert_reporte_registro r WHERE r.codigo = ? AND r.eliminado_en IS NULL'.ep_aprobacion_filtro_alcance($db).' LIMIT 1');
	$stmt->bind_param('s', $codigo);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $fila ?: null;
}

function ep_aprobar_registro(string $codigo): array {
	$r = ep_aprobacion_registro($codigo);
	if (!$r) {
		return ['ok' => false, 'message' => 'No encontré ese registro o no te corresponde.'];
	}
	if ($r['estado'] !== 'Pendiente') {
		return ['ok' => false, 'message' => 'Ese registro ya no está pendiente.'];
	}
	$db = ep_db();
	$yo = (int) $_SESSION['usuario_id'];
	$stmt = $db->prepare("UPDATE insert_reporte_registro SET estado = 'Aprobado', motivo_devolucion = NULL, revisado_por = ?, revisado_en = NOW() WHERE id = ? AND estado = 'Pendiente'");
	$stmt->bind_param('ii', $yo, $r['id']);
	$stmt->execute();
	$stmt->close();
	// Recién aprobado cumple su fila del Calendario; si falla, la aprobación ya quedó.
	if ($r['tipo'] === 'activaciones') {
		try {
			require_once __DIR__.'/calendario_datos.php';
			ep_calendario_cruzar_registro((int) $r['id'], (int) $r['usuario_id'], $r['pos_id'], $r['fecha_actividad']);
		} catch (Throwable $e) {
			error_log('ep_aprobar_registro (calendario): '.$e->getMessage());
		}
	}
	ep_auditar('registro_aprobar', 'registro', (int) $r['id'], 'Aprobó el registro '.$r['codigo'], [ep_auditoria_actividad((string) $r['tipo']), ep_auditoria_dato('Punto de venta', $r['punto_venta'])]);
	return ['ok' => true, 'message' => 'Registro aprobado.'];
}

function ep_devolver_registro(string $codigo, string $motivo): array {
	$motivo = trim($motivo);
	if (mb_strlen($motivo) < EP_MOTIVO_MIN) {
		return ['ok' => false, 'message' => 'Escribe qué debe corregir (mínimo '.EP_MOTIVO_MIN.' caracteres).'];
	}
	$motivo = mb_substr($motivo, 0, EP_MOTIVO_MAX);
	$r = ep_aprobacion_registro($codigo);
	if (!$r) {
		return ['ok' => false, 'message' => 'No encontré ese registro o no te corresponde.'];
	}
	if ($r['estado'] !== 'Pendiente') {
		return ['ok' => false, 'message' => 'Ese registro ya no está pendiente.'];
	}
	$db = ep_db();
	$yo = (int) $_SESSION['usuario_id'];
	$stmt = $db->prepare("UPDATE insert_reporte_registro SET estado = 'Devuelto', motivo_devolucion = ?, revisado_por = ?, revisado_en = NOW() WHERE id = ? AND estado = 'Pendiente'");
	$stmt->bind_param('sii', $motivo, $yo, $r['id']);
	$stmt->execute();
	$stmt->close();
	ep_auditar('registro_devolver', 'registro', (int) $r['id'], 'Devolvió el registro '.$r['codigo'].' al promotor', [ep_auditoria_actividad((string) $r['tipo']), ep_auditoria_dato('Punto de venta', $r['punto_venta']), ep_auditoria_dato('Motivo', $motivo)]);
	return ['ok' => true, 'message' => 'Registro devuelto al promotor.'];
}

// Registros devueltos de un promotor, con el motivo y quién los revisó (para su campana de avisos).
function ep_devueltos_usuario(int $usuarioId): array {
	$db = ep_db();
	if (!$db || !ep_aprobacion_activa($db)) {
		return [];
	}
	$stmt = $db->prepare("SELECT r.codigo, r.tipo, r.punto_venta, r.fecha_actividad, r.motivo_devolucion, r.revisado_en, u.usuario AS revisor FROM insert_reporte_registro r LEFT JOIN repositorio_usuarios_reporte u ON u.id = r.revisado_por WHERE r.usuario_id = ? AND r.estado = 'Devuelto' AND r.eliminado_en IS NULL ORDER BY r.revisado_en DESC");
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return $filas;
}
