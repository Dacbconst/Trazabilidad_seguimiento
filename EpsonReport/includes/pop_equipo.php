<?php
// Equipo de POP: cada supervisor marca qué promotor reporta qué material; los promotores de un material descuentan de un mismo saldo (lo que recibió el supervisor).
require_once __DIR__.'/db.php';
require_once __DIR__.'/pop_reparto.php';
require_once __DIR__.'/usuarios_datos.php';

// Promotores a cargo de un supervisor (en cualquiera de sus canales), activos.
function ep_pop_equipo(int $supervisorId): array {
	$db = ep_db();
	$colFoto = ep_usuarios_tiene_foto($db) ? 'foto' : 'NULL AS foto';
	$stmt = $db->prepare("SELECT id, usuario, nombre, $colFoto FROM repositorio_usuarios_reporte WHERE rol = 'promotor' AND status = 'activo' AND (supervisor_canales_id = ? OR supervisor_retail_id = ?) ORDER BY nombre");
	$stmt->bind_param('ii', $supervisorId, $supervisorId);
	$stmt->execute();
	$equipo = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return array_map(fn($f) => ['id' => (int) $f['id'], 'nombre' => $f['nombre'] ?: $f['usuario'], 'foto' => ep_usuario_foto_url($f['foto'])], $equipo);
}

// Material asignado a promotores en el mes: [usuario_id => [fila_id => supervisor_id]].
function ep_pop_equipo_elegido(int $popId): array {
	$db = ep_db();
	$res = $db ? $db->query('SELECT e.usuario_id, e.pop_fila_id, e.supervisor_id FROM insert_reporte_pop_equipo e JOIN insert_reporte_pop_fila f ON f.id = e.pop_fila_id WHERE f.pop_id = '.$popId) : false;
	if (!$res) {
		error_log('ep_pop_equipo_elegido: '.($db ? $db->error : 'sin conexión'));
		return [];
	}
	$mapa = [];
	foreach ($res->fetch_all(MYSQLI_ASSOC) as $f) {
		$mapa[(int) $f['usuario_id']][(int) $f['pop_fila_id']] = (int) $f['supervisor_id'];
	}
	return $mapa;
}

// Lo que un supervisor asignó en el mes: [usuario_id => [fila_id, ...]].
function ep_pop_equipo_de(int $popId, int $supervisorId): array {
	$mio = [];
	foreach (ep_pop_equipo_elegido($popId) as $usuarioId => $filas) {
		foreach ($filas as $filaId => $supId) {
			if ($supId === $supervisorId) {
				$mio[$usuarioId][] = $filaId;
			}
		}
	}
	return $mio;
}

// Minutos desde que cada promotor está en el equipo del supervisor en ese mes: [usuario_id => minutos] (la asignación más antigua).
function ep_pop_equipo_desde(int $popId, int $supervisorId): array {
	$db = ep_db();
	$res = $db ? $db->query('SELECT e.usuario_id, MAX(TIMESTAMPDIFF(MINUTE, e.created_at, NOW())) AS min FROM insert_reporte_pop_equipo e JOIN insert_reporte_pop_fila f ON f.id = e.pop_fila_id WHERE f.pop_id = '.$popId.' AND e.supervisor_id = '.$supervisorId.' GROUP BY e.usuario_id') : false;
	$mapa = [];
	foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $f) {
		$mapa[(int) $f['usuario_id']] = max(0, (int) $f['min']);
	}
	return $mapa;
}

// Registros de POP de un usuario en el mes (pendiente o aprobado; lo devuelto no cuenta), con sus entregas ya enlazadas a la fila del mes.
function ep_pop_entregas_usuario(int $usuarioId, array $mes): array {
	$db = ep_db();
	$filaDe = [];
	foreach ($mes['filas'] as $f) {
		$filaDe[$f['campana'].'|'.$f['material']] = (int) $f['id'];
	}
	$stmt = $db->prepare("SELECT punto_venta, canal, valores, created_at, TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS hace_min FROM insert_reporte_registro WHERE usuario_id = ? AND tipo = 'colocacion-pop' AND LEFT(fecha_actividad, 7) = ? AND eliminado_en IS NULL AND estado IN ('Pendiente', 'Aprobado') ORDER BY created_at DESC");
	$stmt->bind_param('is', $usuarioId, $mes['mes']);
	$stmt->execute();
	$entregas = [];
	foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
		$valores = json_decode((string) $r['valores'], true);
		foreach (is_array($valores['pop_entregas'] ?? null) ? $valores['pop_entregas'] : [] as $e) {
			// Los registros nuevos traen fila_id; los anteriores se enlazan por campaña y material.
			$filaId = (int) ($e['fila_id'] ?? 0) ?: ($filaDe[($e['campana'] ?? '').'|'.($e['material'] ?? '')] ?? 0);
			if ($filaId) {
				$entregas[] = ['fila_id' => $filaId, 'material' => (string) ($e['material'] ?? ''), 'cantidad' => (int) ($e['cantidad'] ?? 0), 'punto' => (string) $r['punto_venta'], 'canal' => (string) $r['canal'], 'actualizado' => (string) $r['created_at'], 'hace_min' => max(0, (int) $r['hace_min'])];
			}
		}
	}
	$stmt->close();
	return $entregas;
}

// Lo que un usuario ya reportó en el mes, por fila del mes.
function ep_pop_reportado_usuario(int $usuarioId, array $mes): array {
	$reportado = [];
	foreach (ep_pop_entregas_usuario($usuarioId, $mes) as $e) {
		$reportado[$e['fila_id']] = ($reportado[$e['fila_id']] ?? 0) + $e['cantidad'];
	}
	return $reportado;
}

// Lo reportado por los promotores de un supervisor en los materiales que él les asignó, por fila del mes.
function ep_pop_reportado_equipo(array $mes, int $supervisorId): array {
	$total = [];
	foreach (ep_pop_equipo_de((int) $mes['id'], $supervisorId) as $usuarioId => $filas) {
		$hecho = ep_pop_reportado_usuario($usuarioId, $mes);
		foreach ($filas as $filaId) {
			$total[$filaId] = ($total[$filaId] ?? 0) + ($hecho[$filaId] ?? 0);
		}
	}
	return $total;
}

// La parte de un supervisor en el mes: por fila lo recibido, lo que su equipo ya reportó y lo que queda.
function ep_pop_parte_supervisor(array $mes, int $supervisorId): array {
	$parte = [];
	$reportado = null;
	foreach (ep_pop_asignaciones((int) $mes['id'], 1) as $filaId => $porSupervisor) {
		if (empty($porSupervisor[$supervisorId])) {
			continue;
		}
		$reportado ??= ep_pop_reportado_equipo($mes, $supervisorId);
		$hecho = $reportado[$filaId] ?? 0;
		$parte[$filaId] = ['recibido' => $porSupervisor[$supervisorId], 'reportado' => $hecho, 'disponible' => max(0, $porSupervisor[$supervisorId] - $hecho)];
	}
	return $parte;
}

// Meses en los que el supervisor tiene POP asignado: el abierto primero y luego los más recientes.
function ep_pop_meses_del_supervisor(int $supervisorId): array {
	$db = ep_db();
	$res = $db ? $db->query("SELECT id FROM insert_reporte_pop WHERE eliminado_en IS NULL ORDER BY estado = 'activo' DESC, mes DESC, id DESC") : false;
	$meses = [];
	foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $r) {
		$mes = ep_pop_obtener((int) $r['id']);
		if ($mes && ep_pop_parte_supervisor($mes, $supervisorId)) {
			$meses[] = $mes;
		}
	}
	return $meses;
}

// Mes principal del supervisor: el abierto si lo hay, si no el más reciente.
function ep_pop_mes_del_supervisor(int $supervisorId): ?array {
	return ep_pop_meses_del_supervisor($supervisorId)[0] ?? null;
}

// Lo que puede reportar un promotor: por cada material que le marcaron, el saldo de su supervisor (compartido con los demás); [fila_id => [asignado, reportado, disponible]].
function ep_pop_mi_material(int $usuarioId, array $mes): array {
	$partes = [];
	$mio = [];
	foreach (ep_pop_equipo_elegido((int) $mes['id'])[$usuarioId] ?? [] as $filaId => $supId) {
		$partes[$supId] ??= ep_pop_parte_supervisor($mes, $supId);
		if ($p = $partes[$supId][$filaId] ?? null) {
			$mio[$filaId] = ['asignado' => $p['recibido'], 'reportado' => $p['reportado'], 'disponible' => $p['disponible']];
		}
	}
	return $mio;
}

// Guarda lo que un supervisor marca para el mes: [usuario_id => [fila_id, ...]]. Devuelve true o el texto del error.
function ep_pop_equipo_guardar(array $mes, int $supervisorId, array $asignaciones): bool|string {
	if ($mes['estado'] !== 'activo') {
		return 'Ese mes ya está cerrado.';
	}
	$parte = ep_pop_parte_supervisor($mes, $supervisorId);
	if (!$parte) {
		return 'No tienes POP asignado este mes.';
	}
	$equipo = array_column(ep_pop_equipo($supervisorId), 'nombre', 'id');
	$nombreDe = array_column($mes['filas'], 'material', 'id');
	$elegido = ep_pop_equipo_elegido((int) $mes['id']);
	$nuevo = [];
	foreach ($asignaciones as $usuarioId => $filas) {
		$usuarioId = (int) $usuarioId;
		if (!isset($equipo[$usuarioId])) {
			return 'Ese promotor no es de tu equipo.';
		}
		foreach (is_array($filas) ? array_unique(array_map('intval', $filas)) : [] as $filaId) {
			if (!isset($parte[$filaId])) {
				return 'No tienes «'.($nombreDe[$filaId] ?? 'ese material').'» asignado este mes.';
			}
			if (isset($elegido[$usuarioId][$filaId]) && $elegido[$usuarioId][$filaId] !== $supervisorId) {
				return $equipo[$usuarioId].' ya tiene «'.$nombreDe[$filaId].'» con otro supervisor.';
			}
			$nuevo[$usuarioId][$filaId] = true;
		}
	}
	// Quien ya reportó un material no se puede quitar de él: su consumo dejaría de descontar de tu saldo.
	foreach (ep_pop_equipo_de((int) $mes['id'], $supervisorId) as $usuarioId => $filas) {
		$hecho = ep_pop_reportado_usuario($usuarioId, $mes);
		foreach ($filas as $filaId) {
			if (!isset($nuevo[$usuarioId][$filaId]) && ($hecho[$filaId] ?? 0) > 0) {
				return ($equipo[$usuarioId] ?? 'Ese promotor').' ya reportó «'.($nombreDe[$filaId] ?? 'ese material').'»; no puedes quitárselo.';
			}
		}
	}
	$db = ep_db();
	$db->begin_transaction();
	$filasMes = implode(',', array_map('intval', array_column($mes['filas'], 'id')));
	$quitar = $db->prepare('DELETE FROM insert_reporte_pop_equipo WHERE supervisor_id = ? AND usuario_id = ? AND pop_fila_id = ?');
	foreach (ep_pop_equipo_de((int) $mes['id'], $supervisorId) as $usuarioId => $filas) {
		foreach ($filas as $filaId) {
			if (!isset($nuevo[$usuarioId][$filaId])) {
				$quitar->bind_param('iii', $supervisorId, $usuarioId, $filaId);
				$quitar->execute();
			}
		}
	}
	$quitar->close();
	$ya = ep_pop_equipo_de((int) $mes['id'], $supervisorId);
	$stmt = $db->prepare('INSERT INTO insert_reporte_pop_equipo (pop_fila_id, supervisor_id, usuario_id) VALUES (?, ?, ?)');
	foreach ($nuevo as $usuarioId => $filas) {
		foreach (array_keys($filas) as $filaId) {
			if (in_array($filaId, $ya[$usuarioId] ?? [], true)) {
				continue;
			}
			$stmt->bind_param('iii', $filaId, $supervisorId, $usuarioId);
			if (!$stmt->execute()) {
				error_log('ep_pop_equipo_guardar: ' . $stmt->error);
				$db->rollback();
				return 'No se pudo guardar el equipo.';
			}
		}
	}
	$stmt->close();
	$db->commit();
	return true;
}

// Al corregir el mes, un supervisor no puede quedar con menos de lo que su equipo ya reportó; devuelve el motivo o null.
function ep_pop_reparto_conflicto(int $popId, array $filasNuevas): ?string {
	$mes = ep_pop_obtener($popId);
	if (!$mes) {
		return null;
	}
	$nuevo = [];
	foreach ($filasNuevas as $f) {
		if (!empty($f['id'])) {
			$nuevo[(int) $f['id']] = $f['reparto'];
		}
	}
	$nombreDe = array_column($mes['filas'], 'material', 'id');
	$supervisores = array_column(ep_pop_supervisores(), 'nombre', 'id');
	$conEquipo = [];
	foreach (ep_pop_equipo_elegido($popId) as $filas) {
		foreach ($filas as $supId) {
			$conEquipo[$supId] = true;
		}
	}
	foreach (array_keys($conEquipo) as $supId) {
		foreach (ep_pop_reportado_equipo($mes, $supId) as $filaId => $hecho) {
			if (!isset($nuevo[$filaId])) {
				return 'No puedes quitar «'.($nombreDe[$filaId] ?? 'ese material').'»: ya hay promotores que lo reportaron.';
			}
			$recibe = (int) ($nuevo[$filaId][$supId] ?? 0);
			if ($hecho > $recibe) {
				return 'No puedes dejar a '.($supervisores[$supId] ?? 'ese supervisor').' con '.$recibe.' de «'.($nombreDe[$filaId] ?? 'ese material').'»: su equipo ya reportó '.$hecho.'.';
			}
		}
	}
	return null;
}
