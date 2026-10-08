<?php
// Reparto de POP en cadena: bodega → supervisores (nivel 1) → promotores (nivel 2); nadie reparte más de lo que recibió.
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';

// Único supervisor (además del admin) que carga el mes y lo reparte a los supervisores.
const EP_POP_DUENO = 'FABRICIO LUZARRAGA';

function ep_pop_es_dueno(): bool {
	return ep_es_admin() || mb_strtoupper((string) ($_SESSION['usuario'] ?? ''), 'UTF-8') === EP_POP_DUENO;
}

function ep_pop_nombre_propio(string $texto): string {
	return mb_convert_case(mb_strtolower($texto, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

// Supervisores activos de la plataforma: son las columnas del reparto de Fabricio.
function ep_pop_supervisores(): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$res = $db->query("SELECT id, usuario FROM repositorio_usuarios_reporte WHERE rol = 'supervisor' AND status = 'activo' ORDER BY usuario = '".EP_POP_DUENO."' DESC, usuario");
	return array_map(fn($f) => ['id' => (int) $f['id'], 'nombre' => ep_pop_nombre_propio($f['usuario'])], $res ? $res->fetch_all(MYSQLI_ASSOC) : []);
}

// Lo repartido en un nivel: [fila_id => [usuario_id => cantidad]]; en el nivel 2 se puede filtrar por quien reparte.
function ep_pop_asignaciones(int $popId, int $nivel, ?int $porId = null): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$sql = 'SELECT a.pop_fila_id, a.usuario_id, a.cantidad FROM insert_reporte_pop_asignacion a JOIN insert_reporte_pop_fila f ON f.id = a.pop_fila_id WHERE f.pop_id = '.$popId.' AND a.nivel = '.$nivel.($porId !== null ? ' AND a.asignado_por = '.$porId : '');
	$res = $db->query($sql);
	$mapa = [];
	foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $f) {
		$mapa[(int) $f['pop_fila_id']][(int) $f['usuario_id']] = (int) $f['cantidad'];
	}
	return $mapa;
}

// Reemplaza el reparto a supervisores de las filas dadas ([fila_id => [supervisor_id => cantidad]]); va dentro de la transacción que guarda el mes.
function ep_pop_reparto_guardar(array $porFila, int $porId): bool {
	$db = ep_db();
	if (!$porFila) {
		return true;
	}
	$db->query('DELETE FROM insert_reporte_pop_asignacion WHERE nivel = 1 AND pop_fila_id IN ('.implode(',', array_map('intval', array_keys($porFila))).')');
	$stmt = $db->prepare('INSERT INTO insert_reporte_pop_asignacion (pop_fila_id, usuario_id, asignado_por, nivel, cantidad) VALUES (?, ?, ?, 1, ?)');
	foreach ($porFila as $filaId => $reparto) {
		foreach ($reparto as $supId => $cantidad) {
			if ($cantidad <= 0) {
				continue;
			}
			$filaId = (int) $filaId;
			$supId = (int) $supId;
			$stmt->bind_param('iiii', $filaId, $supId, $porId, $cantidad);
			if (!$stmt->execute()) {
				error_log('ep_pop_reparto_guardar: '.$stmt->error);
				$stmt->close();
				return false;
			}
		}
	}
	$stmt->close();
	return true;
}

// Al corregir el mes, un supervisor no puede quedar con menos de lo que ya repartió a sus promotores; devuelve el motivo o null.
function ep_pop_reparto_conflicto(int $popId, array $filasNuevas): ?string {
	$nuevo = [];
	foreach ($filasNuevas as $f) {
		if (!empty($f['id'])) {
			$nuevo[(int) $f['id']] = $f['reparto'];
		}
	}
	$db = ep_db();
	$res = $db->query('SELECT a.pop_fila_id, f.material, a.asignado_por, SUM(a.cantidad) AS repartido, u.usuario FROM insert_reporte_pop_asignacion a JOIN insert_reporte_pop_fila f ON f.id = a.pop_fila_id LEFT JOIN repositorio_usuarios_reporte u ON u.id = a.asignado_por WHERE f.pop_id = '.$popId.' AND a.nivel = 2 GROUP BY a.pop_fila_id, f.material, a.asignado_por, u.usuario');
	foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $f) {
		if (!isset($nuevo[(int) $f['pop_fila_id']])) {
			return 'No puedes quitar «'.$f['material'].'»: ya se repartió a los promotores.';
		}
		$recibe = (int) ($nuevo[(int) $f['pop_fila_id']][(int) $f['asignado_por']] ?? 0);
		if ((int) $f['repartido'] > $recibe) {
			return 'No puedes dejar a '.ep_pop_nombre_propio((string) $f['usuario']).' con '.$recibe.' de «'.$f['material'].'»: ya repartió '.(int) $f['repartido'].' a sus promotores.';
		}
	}
	return null;
}

// Lo que un usuario ya reportó en el mes (pendiente o aprobado), por fila del mes; lo devuelto no cuenta.
function ep_pop_reportado_usuario(int $usuarioId, array $mes): array {
	$db = ep_db();
	$filaDe = [];
	foreach ($mes['filas'] as $f) {
		$filaDe[$f['campana'].'|'.$f['material']] = (int) $f['id'];
	}
	$stmt = $db->prepare("SELECT valores FROM insert_reporte_registro WHERE usuario_id = ? AND tipo = 'colocacion-pop' AND LEFT(fecha_actividad, 7) = ? AND eliminado_en IS NULL AND estado IN ('Pendiente', 'Aprobado')");
	$stmt->bind_param('is', $usuarioId, $mes['mes']);
	$stmt->execute();
	$reportado = [];
	foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
		$valores = json_decode((string) $r['valores'], true);
		foreach (is_array($valores['pop_entregas'] ?? null) ? $valores['pop_entregas'] : [] as $e) {
			// Los registros nuevos traen fila_id; los anteriores se enlazan por campaña y material.
			$filaId = (int) ($e['fila_id'] ?? 0) ?: ($filaDe[($e['campana'] ?? '').'|'.($e['material'] ?? '')] ?? 0);
			if ($filaId) {
				$reportado[$filaId] = ($reportado[$filaId] ?? 0) + (int) ($e['cantidad'] ?? 0);
			}
		}
	}
	$stmt->close();
	return $reportado;
}

// Lo que le toca a un promotor este mes: [fila_id => [asignado, reportado, disponible]].
function ep_pop_mi_material(int $usuarioId, array $mes): array {
	$recibido = [];
	foreach (ep_pop_asignaciones((int) $mes['id'], 2) as $filaId => $porPromotor) {
		if (isset($porPromotor[$usuarioId])) {
			$recibido[$filaId] = $porPromotor[$usuarioId];
		}
	}
	$reportado = $recibido ? ep_pop_reportado_usuario($usuarioId, $mes) : [];
	$mio = [];
	foreach ($recibido as $filaId => $asignado) {
		$hecho = $reportado[$filaId] ?? 0;
		$mio[$filaId] = ['asignado' => $asignado, 'reportado' => $hecho, 'disponible' => max(0, $asignado - $hecho)];
	}
	return $mio;
}

// Promotores a cargo de un supervisor (en cualquiera de sus canales), activos.
function ep_pop_equipo(int $supervisorId): array {
	$db = ep_db();
	$stmt = $db->prepare("SELECT id, usuario, nombre FROM repositorio_usuarios_reporte WHERE rol = 'promotor' AND status = 'activo' AND (supervisor_canales_id = ? OR supervisor_retail_id = ?) ORDER BY nombre");
	$stmt->bind_param('ii', $supervisorId, $supervisorId);
	$stmt->execute();
	$equipo = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return array_map(fn($f) => ['id' => (int) $f['id'], 'nombre' => $f['nombre'] ?: $f['usuario']], $equipo);
}

// La parte de un supervisor en el mes: por fila lo recibido, lo repartido y a quién.
function ep_pop_parte_supervisor(int $popId, int $supervisorId): array {
	$parte = [];
	$reparto = ep_pop_asignaciones($popId, 2, $supervisorId);
	foreach (ep_pop_asignaciones($popId, 1) as $filaId => $porSupervisor) {
		if (empty($porSupervisor[$supervisorId])) {
			continue;
		}
		$aPromotores = $reparto[$filaId] ?? [];
		$parte[$filaId] = ['recibido' => $porSupervisor[$supervisorId], 'repartido' => array_sum($aPromotores), 'reparto' => $aPromotores];
	}
	return $parte;
}

// Guarda el reparto de un supervisor a sus promotores; filas = [{fila_id, reparto: {promotor_id: cantidad}}]. Devuelve true o el texto del error.
function ep_pop_repartir(array $mes, int $supervisorId, array $filas): bool|string {
	if ($mes['estado'] !== 'activo') {
		return 'Ese mes ya está cerrado.';
	}
	$parte = ep_pop_parte_supervisor((int) $mes['id'], $supervisorId);
	$nombreDe = array_column($mes['filas'], 'material', 'id');
	$equipo = array_column(ep_pop_equipo($supervisorId), 'nombre', 'id');
	$nuevo = [];
	foreach ($filas as $f) {
		$filaId = (int) ($f['fila_id'] ?? 0);
		if (!isset($parte[$filaId])) {
			return 'No tienes «'.($nombreDe[$filaId] ?? 'ese material').'» para repartir este mes.';
		}
		$total = 0;
		foreach (is_array($f['reparto'] ?? null) ? $f['reparto'] : [] as $promotorId => $cantidad) {
			$promotorId = (int) $promotorId;
			$cantidad = min(999999, max(0, (int) $cantidad));
			if ($cantidad <= 0) {
				continue;
			}
			if (!isset($equipo[$promotorId])) {
				return 'Ese promotor no es de tu equipo.';
			}
			$nuevo[$filaId][$promotorId] = $cantidad;
			$total += $cantidad;
		}
		if ($total > $parte[$filaId]['recibido']) {
			return 'En «'.$nombreDe[$filaId].'» repartes '.$total.' y recibiste '.$parte[$filaId]['recibido'].'.';
		}
	}
	// Un promotor no puede quedar con menos de lo que ya reportó (sumando lo que le dan otros supervisores).
	foreach ($equipo as $promotorId => $nombre) {
		foreach (ep_pop_reportado_usuario($promotorId, $mes) as $filaId => $hecho) {
			$total = ($nuevo[$filaId][$promotorId] ?? 0) + ep_pop_recibido_de_otros($promotorId, $filaId, $supervisorId);
			if ($total < $hecho) {
				return $nombre.' ya reportó '.$hecho.' de «'.($nombreDe[$filaId] ?? 'ese material').'»; no puedes dejarle menos.';
			}
		}
	}
	$db = ep_db();
	$db->begin_transaction();
	$db->query('DELETE a FROM insert_reporte_pop_asignacion a JOIN insert_reporte_pop_fila f ON f.id = a.pop_fila_id WHERE f.pop_id = '.(int) $mes['id'].' AND a.nivel = 2 AND a.asignado_por = '.$supervisorId);
	$stmt = $db->prepare('INSERT INTO insert_reporte_pop_asignacion (pop_fila_id, usuario_id, asignado_por, nivel, cantidad) VALUES (?, ?, ?, 2, ?)');
	foreach ($nuevo as $filaId => $porPromotor) {
		foreach ($porPromotor as $promotorId => $cantidad) {
			$stmt->bind_param('iiii', $filaId, $promotorId, $supervisorId, $cantidad);
			if (!$stmt->execute()) {
				error_log('ep_pop_repartir: '.$stmt->error);
				$db->rollback();
				return 'No se pudo guardar el reparto.';
			}
		}
	}
	$stmt->close();
	$db->commit();
	return true;
}

// Lo que un promotor recibe de supervisores distintos al que está repartiendo.
function ep_pop_recibido_de_otros(int $promotorId, int $filaId, int $supervisorId): int {
	$db = ep_db();
	$stmt = $db->prepare('SELECT COALESCE(SUM(cantidad), 0) AS t FROM insert_reporte_pop_asignacion WHERE pop_fila_id = ? AND nivel = 2 AND usuario_id = ? AND asignado_por <> ?');
	$stmt->bind_param('iii', $filaId, $promotorId, $supervisorId);
	$stmt->execute();
	$total = (int) $stmt->get_result()->fetch_assoc()['t'];
	$stmt->close();
	return $total;
}
