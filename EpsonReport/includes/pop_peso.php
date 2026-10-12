<?php
// Peso por cadena de un mes de POP: cuánto de lo colocado fue a cada cadena y cuánto de sus puntos habilitados se cubrió.
require_once __DIR__.'/db.php';

// La cadena es el texto antes de " - " en el nombre del punto ("COMPUTRON - KENNEDY" => COMPUTRON), igual que en la base de puntos.
function ep_pop_cadena(string $puntoVenta): string {
	return mb_strtoupper(trim(explode(' - ', $puntoVenta)[0]), 'UTF-8');
}

// Filas por cadena, de más a menos unidades: puntos habilitados en la base, puntos con POP, cobertura y peso sobre lo colocado.
function ep_pop_peso(array $registros): array {
	$cadenas = [];
	foreach ($registros as $r) {
		foreach ($r['pop_entregas'] ?? [] as $e) {
			$punto = (string) ($e['punto_venta'] ?? $r['punto_venta'] ?? '');
			$cadena = ep_pop_cadena($punto);
			if ($cadena === '') {
				continue;
			}
			$cadenas[$cadena]['unidades'] = ($cadenas[$cadena]['unidades'] ?? 0) + (int) $e['cantidad'];
			$pos = (string) ($e['pos_id'] ?? $r['pos_id'] ?? $punto);
			$cadenas[$cadena]['puntos'][$pos] = true;
		}
	}
	if (!$cadenas) {
		return [];
	}
	// Puntos habilitados (activar = SI) de cada cadena en la base.
	$habilitados = [];
	$canales = [];
	$db = ep_db();
	$marcas = implode(',', array_fill(0, count($cadenas), '?'));
	$stmt = $db ? $db->prepare("SELECT UPPER(TRIM(SUBSTRING_INDEX(pos_name, ' - ', 1))) AS cadena, SUM(activar = 'SI') AS n, MAX(CASE WHEN channel IN ('', '-') THEN NULL ELSE channel END) AS canal FROM repositorio_locales_dtt2 WHERE UPPER(TRIM(SUBSTRING_INDEX(pos_name, ' - ', 1))) IN ($marcas) GROUP BY cadena") : false;
	if ($stmt) {
		$nombres = array_keys($cadenas);
		$stmt->bind_param(str_repeat('s', count($nombres)), ...$nombres);
		$stmt->execute();
		foreach ($stmt->get_result() as $f) {
			$habilitados[$f['cadena']] = (int) $f['n'];
			$canales[$f['cadena']] = mb_strtoupper((string) $f['canal'], 'UTF-8');
		}
		$stmt->close();
	}
	$total = array_sum(array_column($cadenas, 'unidades'));
	$filas = [];
	foreach ($cadenas as $nombre => $c) {
		$con = count($c['puntos']);
		$hab = $habilitados[$nombre] ?? 0;
		$filas[] = ['cadena' => $nombre, 'canal' => $canales[$nombre] ?? '', 'habilitados' => $hab, 'puntos' => $con, 'cobertura' => $hab > 0 ? round($con / $hab * 100, 1) : null, 'unidades' => $c['unidades'], 'peso' => $total > 0 ? round($c['unidades'] / $total * 100, 1) : 0];
	}
	usort($filas, fn($a, $b) => $b['unidades'] <=> $a['unidades'] ?: strcmp($a['cadena'], $b['cadena']));
	return $filas;
}
