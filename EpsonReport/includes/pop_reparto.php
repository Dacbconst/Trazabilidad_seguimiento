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
