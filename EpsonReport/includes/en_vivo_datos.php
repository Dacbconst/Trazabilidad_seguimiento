<?php
// Datos livianos para las pantallas "en vivo" del admin: una firma que cambia cuando cambia algo, sin pedir la lista completa.
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';

// Estado de cada calendario y de cada fila (pendiente/cumplido); la pantalla compara y se actualiza sola si cambió.
function ep_vivo_calendario(): array {
	$db = ep_db();
	if (!$db) {
		return ['firma' => '', 'cals' => []];
	}
	$res = $db->query('SELECT c.id, c.estado, f.id AS fila_id, f.estado AS fila_estado FROM insert_reporte_calendario c LEFT JOIN insert_reporte_calendario_fila f ON f.calendario_id = c.id WHERE c.eliminado_en IS NULL'.(ep_es_supervisor() ? ' AND c.creado_por = '.(int) $_SESSION['usuario_id'] : '').' ORDER BY c.id, f.id');
	if (!$res) {
		error_log('ep_vivo_calendario: '.$db->error);
		return ['firma' => '', 'cals' => []];
	}
	$cals = [];
	foreach ($res as $f) {
		$id = (int) $f['id'];
		$cals[$id] = $cals[$id] ?? ['id' => $id, 'estado' => $f['estado'], 'total' => 0, 'cumplidas' => 0, 'filas' => []];
		if ($f['fila_id'] !== null) {
			$cals[$id]['total']++;
			$cals[$id]['cumplidas'] += $f['fila_estado'] === 'cumplido' ? 1 : 0;
			$cals[$id]['filas'][(string) $f['fila_id']] = $f['fila_estado'];
		}
	}
	// Misma regla que la lista: activo, o cerrado completo/incompleto según si se cumplieron todas las filas.
	$salida = [];
	foreach ($cals as $c) {
		$vista = $c['estado'] === 'activo' ? 'activo' : ($c['total'] > 0 && $c['cumplidas'] === $c['total'] ? 'completo' : 'incompleto');
		$salida[] = ['id' => $c['id'], 'vista' => $vista, 'total' => $c['total'], 'filas' => (object) $c['filas']];
	}
	return ['firma' => md5(json_encode($salida)), 'cals' => $salida];
}

// Total y último id de la bitácora: basta para saber si hay movimientos nuevos.
function ep_vivo_auditoria(): array {
	$db = ep_db();
	$fila = $db ? $db->query('SELECT COUNT(*) AS total, COALESCE(MAX(id), 0) AS ultimo FROM insert_reporte_auditoria')->fetch_assoc() : null;
	return ['firma' => $fila ? $fila['total'].'-'.$fila['ultimo'] : ''];
}

// Total y último id de los reportes mensuales (alcance del supervisor igual que ep_reportes_listar).
function ep_vivo_reportes(): array {
	$db = ep_db();
	if (!$db) {
		return ['firma' => ''];
	}
	$fila = $db->query('SELECT COUNT(*) AS total, COALESCE(MAX(id), 0) AS ultimo FROM insert_reporte_mensual WHERE eliminado_en IS NULL'.(ep_es_supervisor() ? ' AND creado_por = '.(int) $_SESSION['usuario_id'] : ''))->fetch_assoc();
	return ['firma' => $fila ? $fila['total'].'-'.$fila['ultimo'] : ''];
}
