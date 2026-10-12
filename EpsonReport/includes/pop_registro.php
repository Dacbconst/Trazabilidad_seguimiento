<?php
// Colocación de POP: un solo registro por promotor y mes; cada envío nuevo suma un punto de venta al registro del mes.
require_once __DIR__.'/db.php';
require_once __DIR__.'/registros_datos.php';
require_once __DIR__.'/aprobacion_datos.php';

// Fecha del registro dentro del mes de POP abierto (hoy si es de ese mes; si no, el último día del mes).
function ep_pop_fecha_registro(string $mes): string {
	return substr(date('Y-m-d'), 0, 7) === $mes ? date('Y-m-d') : $mes.'-'.date('t', strtotime($mes.'-01'));
}

// Registro de POP del promotor en ese mes que acepta más puntos (pendiente o aprobado, ya con la forma por puntos); null si no hay.
function ep_pop_registro_del_mes(int $usuarioId, string $mes): ?array {
	$db = ep_db();
	$stmt = $db ? $db->prepare("SELECT id, codigo, valores FROM insert_reporte_registro WHERE usuario_id = ? AND tipo = 'colocacion-pop' AND LEFT(fecha_actividad, 7) = ? AND eliminado_en IS NULL AND estado IN ('Pendiente', 'Aprobado') ORDER BY id LIMIT 1") : false;
	if (!$stmt) {
		return null;
	}
	$stmt->bind_param('is', $usuarioId, $mes);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	$valores = $fila ? json_decode((string) $fila['valores'], true) : null;
	return is_array($valores) && !empty($valores['puntos']) ? ['id' => (int) $fila['id'], 'codigo' => $fila['codigo'], 'valores' => $valores] : null;
}

// Supervisores de los puntos: el primero queda en la columna y el resto en el JSON, igual que Competencia.
function ep_pop_supervisores_de_puntos(array $puntos, int $usuarioId): array {
	$ids = [];
	foreach ($puntos as $p) {
		$sup = ep_supervisor_asignado($usuarioId, (string) ($p['canal'] ?? ''));
		if ($sup) {
			$ids[$sup] = true;
		}
	}
	$ids = array_keys($ids);
	return [$ids ? array_shift($ids) : null, $ids];
}

// Guarda el envío: si el promotor ya tiene registro de POP este mes le suma el punto (vuelve a Pendiente); si no, crea el registro. Devuelve el código o null.
function ep_pop_guardar_envio(array $registro, array $punto, int $usuarioId, string $mes): ?array {
	$db = ep_db();
	$previo = $db ? ep_pop_registro_del_mes($usuarioId, $mes) : null;
	$conAprobacion = ep_aprobacion_activa($db);
	if (!$previo) {
		$registro['puntos'] = [$punto];
		$registro['pop_entregas'] = $punto['pop_entregas'];
		[$principal, $extra] = ep_pop_supervisores_de_puntos([$punto], $usuarioId);
		$registro['supervisor_id'] = $conAprobacion ? $principal : null;
		$registro['supervisores_extra'] = $extra;
		$registro['fotos'] = [];
		return ep_guardar_nuevo_registro($registro, $usuarioId) ? ['codigo' => $registro['id'], 'fusionado' => false] : null;
	}
	$valores = $previo['valores'];
	$valores['puntos'][] = $punto;
	$valores['pop_entregas'] = array_merge($valores['pop_entregas'] ?? [], $punto['pop_entregas']);
	[$principal, $extra] = ep_pop_supervisores_de_puntos($valores['puntos'], $usuarioId);
	$valores['supervisores_extra'] = $extra;
	$json = json_encode($valores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$id = $previo['id'];
	// Con aprobación activa el registro ampliado vuelve a Pendiente para que el supervisor revise lo nuevo.
	$sql = $conAprobacion
		? "UPDATE insert_reporte_registro SET valores = ?, estado = 'Pendiente', supervisor_id = ?, motivo_devolucion = NULL, revisado_por = NULL, revisado_en = NULL WHERE id = ?"
		: 'UPDATE insert_reporte_registro SET valores = ? WHERE id = ?';
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		error_log('ep_pop_guardar_envio: '.$db->error);
		return null;
	}
	if ($conAprobacion) {
		$stmt->bind_param('sii', $json, $principal, $id);
	} else {
		$stmt->bind_param('si', $json, $id);
	}
	$ok = $stmt->execute();
	$stmt->close();
	if (!$ok) {
		return null;
	}
	ep_pop_sumar_comentarios($db, $id, $registro['comentarios'] ?? []);
	return ['codigo' => $previo['codigo'], 'fusionado' => true];
}

// Los comentarios del envío nuevo se agregan a los que ya tenía el registro.
function ep_pop_sumar_comentarios($db, int $registroId, array $comentarios): void {
	if (!$comentarios) {
		return;
	}
	$res = $db->query('SELECT COALESCE(MAX(orden), 0) AS n FROM insert_reporte_registro_comentario WHERE registro_id = '.$registroId);
	$orden = (int) ($res ? $res->fetch_assoc()['n'] : 0);
	$stmt = $db->prepare('INSERT INTO insert_reporte_registro_comentario (registro_id, orden, texto) VALUES (?, ?, ?)');
	foreach ($comentarios as $texto) {
		$orden++;
		$stmt->bind_param('iis', $registroId, $orden, $texto);
		$stmt->execute();
	}
	$stmt->close();
}
