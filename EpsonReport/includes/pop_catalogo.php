<?php
// Catálogo de Colocación de POP: listas de materiales y de campañas, independientes entre sí, que alimentan el modal de carga del mes.
require_once __DIR__.'/db.php';

const EP_POP_CATALOGO_LARGO = ['material' => 60, 'campana' => 40];

// Mismo formato que se guarda en cada fila del mes: mayúsculas, espacios simples y largo máximo.
function ep_pop_catalogo_normalizar(string $tipo, string $texto): string {
	$limpio = trim(preg_replace('/\s+/u', ' ', mb_strtoupper($texto, 'UTF-8')));
	return mb_substr($limpio, 0, EP_POP_CATALOGO_LARGO[$tipo] ?? 40, 'UTF-8');
}

// Lista activa de cada repositorio, ordenada por nombre.
function ep_pop_catalogo(): array {
	$db = ep_db();
	$catalogo = ['materiales' => [], 'campanas' => []];
	if (!$db) {
		return $catalogo;
	}
	foreach (['material' => 'materiales', 'campana' => 'campanas'] as $tipo => $clave) {
		$sql = "SELECT id, nombre FROM insert_reporte_pop_catalogo WHERE tipo = '$tipo' AND eliminado_en IS NULL ORDER BY nombre";
		$res = $db->query($sql);
		if (!$res) {
			error_log('ep_pop_catalogo: '.$db->error);
		}
		foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $r) {
			$catalogo[$clave][] = ['id' => (int) $r['id'], 'nombre' => $r['nombre']];
		}
	}
	return $catalogo;
}

// Agrega nombres a una lista; repetidos se ignoran y uno quitado antes vuelve a la vida. Devuelve cuántos quedaron nuevos.
function ep_pop_catalogo_agregar(string $tipo, array $nombres, int $usuarioId): int {
	$db = ep_db();
	if (!$db || !isset(EP_POP_CATALOGO_LARGO[$tipo])) {
		return 0;
	}
	$nuevos = 0;
	// Si la tabla o una columna no existe, prepare() devuelve false: se avisa con el motivo real de la base.
	$preparar = function (string $sql) use ($db) {
		$stmt = $db->prepare($sql);
		if (!$stmt) {
			throw new RuntimeException('Repositorio de POP: '.$db->error);
		}
		return $stmt;
	};
	$busca = $preparar('SELECT id, eliminado_en FROM insert_reporte_pop_catalogo WHERE tipo = ? AND nombre = ?');
	$alta = $preparar('INSERT INTO insert_reporte_pop_catalogo (tipo, nombre, creado_por) VALUES (?, ?, ?)');
	$revive = $preparar('UPDATE insert_reporte_pop_catalogo SET eliminado_en = NULL WHERE id = ?');
	foreach (array_unique(array_filter(array_map(fn($n) => ep_pop_catalogo_normalizar($tipo, (string) $n), $nombres))) as $nombre) {
		$busca->bind_param('ss', $tipo, $nombre);
		$busca->execute();
		$fila = $busca->get_result()->fetch_assoc();
		if (!$fila) {
			$alta->bind_param('ssi', $tipo, $nombre, $usuarioId);
			$nuevos += $alta->execute() ? 1 : 0;
		} elseif ($fila['eliminado_en'] !== null) {
			$revive->bind_param('i', $fila['id']);
			$nuevos += $revive->execute() ? 1 : 0;
		}
	}
	return $nuevos;
}

// Cambia el nombre de un ítem; devuelve el error o null. Los meses ya cargados conservan el nombre anterior.
function ep_pop_catalogo_renombrar(int $id, string $nombre): ?string {
	$db = ep_db();
	if (!$db) {
		return 'No hay conexión con la base.';
	}
	$stmt = $db->prepare('SELECT tipo, nombre FROM insert_reporte_pop_catalogo WHERE id = ? AND eliminado_en IS NULL');
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$actual = $stmt->get_result()->fetch_assoc();
	if (!$actual) {
		return 'Ese nombre no existe o ya fue quitado.';
	}
	$nuevo = ep_pop_catalogo_normalizar($actual['tipo'], $nombre);
	if ($nuevo === '') {
		return 'Escribe el nombre.';
	}
	if ($nuevo === $actual['nombre']) {
		return null;
	}
	$dup = $db->prepare('SELECT id FROM insert_reporte_pop_catalogo WHERE tipo = ? AND nombre = ? AND id <> ?');
	$dup->bind_param('ssi', $actual['tipo'], $nuevo, $id);
	$dup->execute();
	if ($dup->get_result()->fetch_assoc()) {
		return '«'.$nuevo.'» ya existe en la lista.';
	}
	$cambio = $db->prepare('UPDATE insert_reporte_pop_catalogo SET nombre = ? WHERE id = ?');
	$cambio->bind_param('si', $nuevo, $id);
	return $cambio->execute() ? null : 'No se pudo guardar el cambio.';
}

// Borrado lógico: los meses ya cargados guardan el texto, así que no se rompen.
function ep_pop_catalogo_quitar(int $id): ?array {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$stmt = $db->prepare('SELECT tipo, nombre FROM insert_reporte_pop_catalogo WHERE id = ? AND eliminado_en IS NULL');
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	if (!$fila) {
		return null;
	}
	$baja = $db->prepare('UPDATE insert_reporte_pop_catalogo SET eliminado_en = NOW() WHERE id = ?');
	$baja->bind_param('i', $id);
	return $baja->execute() ? $fila : null;
}

// Al cargar o corregir un mes: lo que no esté en el catálogo (ni ya estuviera en ese mes) se rechaza. Devuelve el error o null.
function ep_pop_catalogo_validar(array $filas, array $yaEnElMes = []): ?string {
	$catalogo = ep_pop_catalogo();
	$validos = [
		'material' => array_merge(array_column($catalogo['materiales'], 'nombre'), array_column($yaEnElMes, 'material')),
		'campana' => array_merge(array_column($catalogo['campanas'], 'nombre'), array_column($yaEnElMes, 'campana')),
	];
	foreach ($filas as $f) {
		foreach (['material' => 'El material', 'campana' => 'La campaña'] as $tipo => $texto) {
			if (!in_array($f[$tipo], $validos[$tipo], true)) {
				return $texto.' «'.$f[$tipo].'» no está en el catálogo. Agrégalo primero en Catálogo.';
			}
		}
	}
	return null;
}
