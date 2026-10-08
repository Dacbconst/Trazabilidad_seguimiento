<?php
// Seguimiento del equipo de un supervisor: saldo por material, quién reportó y quién no, con la antigüedad de cada señal y el detalle por punto de venta.
require_once __DIR__.'/pop_equipo.php';

// "hace 5 min", "hace 2 h", "hace 3 días": el texto corto que el supervisor lee de un vistazo.
function ep_pop_hace(int $minutos): string {
	if ($minutos < 1) {
		return 'ahora mismo';
	}
	if ($minutos < 60) {
		return 'hace '.$minutos.' min';
	}
	if ($minutos < 1440) {
		return 'hace '.intdiv($minutos, 60).' h';
	}
	$dias = intdiv($minutos, 1440);
	return 'hace '.$dias.($dias === 1 ? ' día' : ' días');
}

// Estructura que pinta components/pop/seguimiento_vista.php: materiales (saldo) y promotores con su estado, sus números y su detalle.
function ep_pop_seguimiento(array $mes, int $supervisorId): array {
	$parte = ep_pop_parte_supervisor($mes, $supervisorId);
	$materiales = [];
	foreach ($mes['filas'] as $f) {
		if ($p = $parte[(int) $f['id']] ?? null) {
			$materiales[] = ['id' => (int) $f['id'], 'material' => $f['material'], 'campana' => $f['campana'], 'recibido' => $p['recibido'], 'reportado' => $p['reportado'], 'disponible' => $p['disponible']];
		}
	}
	$equipo = ep_pop_equipo($supervisorId);
	$nombres = array_column($equipo, 'nombre', 'id');
	$fotos = array_column($equipo, 'foto', 'id');
	$desde = ep_pop_equipo_desde((int) $mes['id'], $supervisorId);
	$promotores = [];
	foreach (ep_pop_equipo_de((int) $mes['id'], $supervisorId) as $usuarioId => $filas) {
		$entregas = ep_pop_entregas_usuario($usuarioId, $mes);
		$hecho = [];
		foreach ($entregas as $e) {
			$hecho[$e['fila_id']] = ($hecho[$e['fila_id']] ?? 0) + $e['cantidad'];
		}
		$celdas = [];
		foreach ($materiales as $m) {
			$celdas[$m['id']] = ['asignado' => in_array($m['id'], $filas, true), 'reportado' => $hecho[$m['id']] ?? 0];
		}
		$total = array_sum($hecho);
		$promotores[] = [
			'id' => $usuarioId,
			'nombre' => $nombres[$usuarioId] ?? 'Promotor #'.$usuarioId,
			'foto' => $fotos[$usuarioId] ?? '',
			'celdas' => $celdas,
			'total' => $total,
			'estado' => $total > 0 ? 'rep' : 'sin',
			'ultimo_min' => $entregas ? min(array_column($entregas, 'hace_min')) : null,
			'desde_min' => $desde[$usuarioId] ?? 0,
			'detalle' => $entregas,
		];
	}
	usort($promotores, fn($a, $b) => strcasecmp($a['nombre'], $b['nombre']));
	return ['mes' => $mes['mes'], 'abierto' => $mes['estado'] === 'activo', 'materiales' => $materiales, 'promotores' => $promotores];
}
