<?php
// Flujo de un mes de POP: de lo que recibió cada supervisor a lo que cada promotor colocó en cada punto de venta.
require_once __DIR__.'/db.php';
require_once __DIR__.'/pop_datos.php';
require_once __DIR__.'/pop_equipo.php';
require_once __DIR__.'/pop_reparto.php';

// Filas planas para el PPT: material, campaña, supervisor, lo que recibió, promotor, punto de venta, ciudad y cantidad colocada.
function ep_pop_flujo(array $pop, array $registros): array {
	$db = ep_db();
	$supervisor = array_column(ep_pop_supervisores(), 'nombre', 'id');
	$recibido = ep_pop_asignaciones((int) $pop['id'], 1);
	$equipo = ep_pop_equipo_elegido((int) $pop['id']);
	$idDe = [];
	$res = $db ? $db->query('SELECT id, usuario FROM repositorio_usuarios_reporte') : false;
	foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $u) {
		$idDe[$u['usuario']] = (int) $u['id'];
	}
	$filaDe = [];
	foreach ($pop['filas'] as $f) {
		$filaDe[$f['campana'].'|'.$f['material']] = (int) $f['id'];
	}

	// Lo colocado, por fila del mes y supervisor que lo recibió: cada entrega con su promotor y punto.
	$colocado = [];
	foreach ($registros as $r) {
		$usuarioId = $idDe[$r['promotor_usuario'] ?? ''] ?? 0;
		foreach ($r['pop_entregas'] ?? [] as $e) {
			$filaId = (int) ($e['fila_id'] ?? 0) ?: ($filaDe[($e['campana'] ?? $r['campana'] ?? '').'|'.($e['material'] ?? '')] ?? 0);
			$supId = $equipo[$usuarioId][$filaId] ?? 0;
			$colocado[$filaId][$supId][] = ['promotor' => (string) $r['promotor'], 'pdv' => (string) ($e['punto_venta'] ?? $r['punto_venta'] ?? ''), 'ciudad' => (string) ($e['ciudad'] ?? $r['ciudad'] ?? ''), 'cantidad' => (int) $e['cantidad']];
		}
	}

	$filas = [];
	foreach ($pop['filas'] as $f) {
		$filaId = (int) $f['id'];
		$supIds = array_unique(array_merge(array_keys(array_filter($recibido[$filaId] ?? [])), array_keys($colocado[$filaId] ?? [])));
		foreach ($supIds as $supId) {
			$entregas = $colocado[$filaId][$supId] ?? [];
			$base = ['material' => $f['material'], 'campana' => $f['campana'], 'supervisor' => $supervisor[$supId] ?? 'Sin supervisor', 'recibido' => (int) ($recibido[$filaId][$supId] ?? 0)];
			foreach ($entregas ?: [['promotor' => '', 'pdv' => '', 'ciudad' => '', 'cantidad' => 0]] as $e) {
				$filas[] = $base + $e;
			}
		}
	}
	return $filas;
}
