<?php
// Mes de Colocación de POP: Fabricio carga lo que llegó a bodega, se reparte en cadena y los promotores lo dan de baja al reportar.
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';
require_once __DIR__.'/reportes_datos.php';
require_once __DIR__.'/pop_reparto.php';
require_once __DIR__.'/pop_equipo.php';
require_once __DIR__.'/pop_catalogo.php';

// Mes abierto que manda hoy: de él sale la lista de materiales que ve el promotor.
function ep_pop_abierto(): ?array {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$row = $db->query("SELECT * FROM insert_reporte_pop WHERE estado = 'activo' AND eliminado_en IS NULL ORDER BY mes DESC, id DESC LIMIT 1")->fetch_assoc();
	if (!$row) {
		return null;
	}
	$row['filas'] = ep_pop_filas((int) $row['id']);
	return $row;
}

// Cada fila trae 'asignado': lo repartido a cada supervisor al cargar el mes ([usuario_id => cantidad]).
function ep_pop_filas(int $popId): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$res = $db->query('SELECT id, material, campana, bodega FROM insert_reporte_pop_fila WHERE pop_id = '.$popId.' ORDER BY campana, material');
	$filas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
	$reparto = ep_pop_asignaciones($popId, 1);
	foreach ($filas as &$f) {
		$f['asignado'] = $reparto[(int) $f['id']] ?? [];
	}
	unset($f);
	return $filas;
}

// Materiales que el usuario puede elegir hoy; el promotor solo los que su supervisor le asignó, con lo que le queda.
function ep_pop_materiales(): array {
	$mes = ep_pop_abierto();
	if (!$mes) {
		return [];
	}
	if (($_SESSION['rol'] ?? '') !== 'usuario') {
		return array_map(fn($f) => ['material' => $f['material'], 'campana' => $f['campana'], 'disponible' => null], $mes['filas']);
	}
	$lista = [];
	$filas = array_column($mes['filas'], null, 'id');
	foreach (ep_pop_mi_material((int) $_SESSION['usuario_id'], $mes) as $filaId => $m) {
		if ($m['disponible'] > 0 && isset($filas[$filaId])) {
			$lista[] = ['material' => $filas[$filaId]['material'], 'campana' => $filas[$filaId]['campana'], 'disponible' => $m['disponible']];
		}
	}
	return $lista;
}

// Lo entregado por los promotores, por mes y campaña + material; el canal del punto decide si suma a Retail o a Canales.
function ep_pop_entregado_todos(): array {
	static $porMes = null;
	if ($porMes !== null) {
		return $porMes;
	}
	require_once __DIR__.'/registros_datos.php';
	$porMes = [];
	// Una sola pasada por los registros: la lista de meses los consulta todos y leer por mes era una pasada por cada uno.
	foreach (ep_registros_datos(5000, [], ['Aprobado']) as $r) {
		$mes = substr((string) ($r['fecha_actividad'] ?? ''), 0, 7);
		if (($r['tipo'] ?? '') !== 'colocacion-pop' || $mes === '') {
			continue;
		}
		$esRetail = mb_strtoupper((string) ($r['canal'] ?? ''), 'UTF-8') === 'RETAIL';
		foreach ($r['pop_entregas'] ?? [] as $e) {
			$clave = ($e['campana'] ?? $r['campana'] ?? '').'|'.$e['material'];
			$porMes[$mes][$clave] ??= ['canales' => 0, 'retail' => 0];
			$porMes[$mes][$clave][$esRetail ? 'retail' : 'canales'] += (int) $e['cantidad'];
		}
	}
	return $porMes;
}

function ep_pop_entregado(string $mes): array {
	return ep_pop_entregado_todos()[$mes] ?? [];
}

// Meses de POP con sus filas y lo entregado hasta ahora; Fabricio y el admin ven todos, un supervisor solo los que le repartieron.
function ep_pop_listar(): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$res = $db->query("SELECT p.*, u.nombre AS creador FROM insert_reporte_pop p LEFT JOIN repositorio_usuarios_reporte u ON u.id = p.creado_por WHERE p.eliminado_en IS NULL ORDER BY p.mes DESC, p.id DESC");
	$yo = (int) ($_SESSION['usuario_id'] ?? 0);
	$meses = [];
	foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $row) {
		$row['filas'] = ep_pop_filas((int) $row['id']);
		if (!ep_pop_es_dueno() && !array_filter($row['filas'], fn($f) => !empty($f['asignado'][$yo]))) {
			continue;
		}
		$entregado = ep_pop_entregado($row['mes']);
		foreach ($row['filas'] as &$f) {
			$t = $entregado[$f['campana'].'|'.$f['material']] ?? ['canales' => 0, 'retail' => 0];
			$f['colocado'] = $t['canales'] + $t['retail'];
			$f['sin_repartir'] = (int) $f['bodega'] - array_sum($f['asignado']);
			$f['disponible'] = (int) $f['bodega'] - $f['colocado'];
		}
		unset($f);
		$meses[] = $row;
	}
	return $meses;
}

function ep_pop_obtener(int $popId): ?array {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$stmt = $db->prepare('SELECT * FROM insert_reporte_pop WHERE id = ? AND eliminado_en IS NULL');
	$stmt->bind_param('i', $popId);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc() ?: null;
	if ($row) {
		$row['filas'] = ep_pop_filas($popId);
	}
	return $row;
}

// Cargar, corregir, cerrar o eliminar el mes es de Fabricio o del admin.
function ep_pop_permitido(int $popId): bool {
	return ep_pop_es_dueno() && ep_pop_obtener($popId) !== null;
}

// Solo un mes de POP abierto a la vez: dos tablas vivas dejarían al promotor con materiales de dos inventarios.
function ep_pop_mes_ocupado(string $mes): ?string {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$stmt = $db->prepare("SELECT mes FROM insert_reporte_pop WHERE eliminado_en IS NULL AND (mes = ? OR estado = 'activo') LIMIT 1");
	$stmt->bind_param('s', $mes);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	return $row ? $row['mes'] : null;
}

function ep_pop_crear(string $mes, ?string $comentarios, array $filas, int $creadoPor): ?int {
	$db = ep_db();
	if (!$db || empty($filas)) {
		return null;
	}
	$db->begin_transaction();
	$stmt = $db->prepare('INSERT INTO insert_reporte_pop (mes, comentarios, creado_por) VALUES (?, ?, ?)');
	$stmt->bind_param('ssi', $mes, $comentarios, $creadoPor);
	if (!$stmt->execute()) {
		error_log('ep_pop_crear: '.$stmt->error);
		$db->rollback();
		return null;
	}
	$popId = (int) $db->insert_id;
	$stmt->close();
	if (!ep_pop_filas_guardar($popId, $filas, $creadoPor)) {
		$db->rollback();
		return null;
	}
	$db->commit();
	return $popId;
}

// Guarda las filas del mes y su reparto a supervisores; las que ya existían se actualizan (conservan su id) y las que faltan se quitan.
function ep_pop_filas_guardar(int $popId, array $filas, int $porId): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$existentes = array_map('intval', array_column($db->query('SELECT id FROM insert_reporte_pop_fila WHERE pop_id = '.$popId)->fetch_all(MYSQLI_ASSOC), 'id'));
	$alta = $db->prepare('INSERT INTO insert_reporte_pop_fila (pop_id, material, campana, bodega) VALUES (?, ?, ?, ?)');
	$cambio = $db->prepare('UPDATE insert_reporte_pop_fila SET material = ?, campana = ?, bodega = ?, editado_en = NOW() WHERE id = ? AND pop_id = ?');
	$conservar = [];
	$reparto = [];
	foreach ($filas as $f) {
		$id = (int) ($f['id'] ?? 0);
		if ($id && in_array($id, $existentes, true)) {
			$cambio->bind_param('ssiii', $f['material'], $f['campana'], $f['bodega'], $id, $popId);
			$ok = $cambio->execute();
		} else {
			$alta->bind_param('issi', $popId, $f['material'], $f['campana'], $f['bodega']);
			$ok = $alta->execute();
			$id = (int) $db->insert_id;
		}
		if (!$ok) {
			error_log('ep_pop_filas_guardar: '.$db->error);
			return false;
		}
		$conservar[] = $id;
		$reparto[$id] = $f['reparto'];
	}
	$alta->close();
	$cambio->close();
	$quitar = array_diff($existentes, $conservar);
	if ($quitar) {
		$lista = implode(',', $quitar);
		$db->query('DELETE FROM insert_reporte_pop_asignacion WHERE pop_fila_id IN ('.$lista.')');
		$db->query('DELETE FROM insert_reporte_pop_fila WHERE id IN ('.$lista.') AND pop_id = '.$popId);
	}
	return ep_pop_reparto_guardar($reparto, $porId);
}

function ep_pop_editar(int $popId, array $filas, ?string $comentarios, int $porId): bool {
	$db = ep_db();
	if (!$db || empty($filas)) {
		return false;
	}
	$db->begin_transaction();
	$stmt = $db->prepare('UPDATE insert_reporte_pop SET comentarios = ? WHERE id = ?');
	$stmt->bind_param('si', $comentarios, $popId);
	if (!$stmt->execute() || !ep_pop_filas_guardar($popId, $filas, $porId)) {
		$db->rollback();
		return false;
	}
	$db->commit();
	return true;
}

function ep_pop_eliminar(int $popId): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$stmt = $db->prepare('UPDATE insert_reporte_pop SET eliminado_en = NOW() WHERE id = ? AND eliminado_en IS NULL');
	$stmt->bind_param('i', $popId);
	return $stmt->execute() && $stmt->affected_rows > 0;
}

// Cierra el mes y genera su reporte: null si no se pudo cerrar, 0 si cerró sin reporte, o el id del reporte.
function ep_pop_cerrar(int $popId, int $usuarioId): ?int {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$pop = ep_pop_obtener($popId);
	if (!$pop || $pop['estado'] !== 'activo') {
		return null;
	}
	require_once __DIR__.'/registros_datos.php';
	$ocupados = array_flip(ep_registros_ocupados());
	$copia = [];
	$validos = [];
	foreach (ep_registros_datos(5000, [], ['Aprobado'], false) as $r) {
		if (($r['tipo'] ?? '') !== 'colocacion-pop' || substr((string) ($r['fecha_actividad'] ?? ''), 0, 7) !== $pop['mes'] || isset($ocupados[(int) $r['db_id']])) {
			continue;
		}
		$validos[] = (int) $r['db_id'];
		$copia[] = $r;
	}
	// La bodega se congela con el resto: reabrir o corregir el mes no cambia este PPTX.
	$bodega = [];
	foreach ($pop['filas'] as $f) {
		$bodega[$f['campana'].'|'.$f['material']] = (int) $f['bodega'];
	}
	$desde = $pop['mes'].'-01';
	$hasta = date('Y-m-t', strtotime($desde));
	$snapshot = json_encode(['desde' => $desde, 'hasta' => $hasta, 'actividad' => 'Colocación de POP', 'pop_bodega' => $bodega, 'registros' => $copia], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$titulo = 'Colocación de POP '.ep_pop_mes_texto($pop['mes']);
	$reporteId = $validos ? ep_reporte_crear('colocacion-pop', $pop['mes'], $titulo, null, null, $pop['comentarios'] ?? null, $validos, $snapshot, $usuarioId) : 0;

	$ahora = date('Y-m-d H:i:s');
	$stmt = $db->prepare("UPDATE insert_reporte_pop SET estado = 'cerrado', cerrado_en = ?, reporte_mensual_id = ? WHERE id = ?");
	$reporteIdParam = $reporteId > 0 ? $reporteId : null;
	$stmt->bind_param('sii', $ahora, $reporteIdParam, $popId);
	return $stmt->execute() ? $reporteId : null;
}

// Filas que manda el navegador, en mayúsculas y sin repetir material; devuelve el texto del error si algo no pasa.
function ep_pop_filas_desde_post(string $json): array|string {
	$limpias = [];
	$vistos = [];
	$supervisores = array_column(ep_pop_supervisores(), 'nombre', 'id');
	foreach (json_decode($json, true) ?: [] as $f) {
		$material = mb_substr(trim(preg_replace('/\s+/u', ' ', mb_strtoupper((string) ($f['material'] ?? ''), 'UTF-8'))), 0, 60, 'UTF-8');
		$campana = mb_substr(trim(preg_replace('/\s+/u', ' ', mb_strtoupper((string) ($f['campana'] ?? ''), 'UTF-8'))), 0, 40, 'UTF-8');
		$bodega = min(999999, max(0, (int) ($f['bodega'] ?? 0)));
		if ($material === '' || $campana === '') {
			continue;
		}
		// El material es la clave que elige el promotor: repetirlo dejaría dos bodegas para lo mismo.
		if (isset($vistos[$material])) {
			return 'El material «'.$material.'» está dos veces. Déjalo una sola vez con su total.';
		}
		$vistos[$material] = true;
		$reparto = [];
		foreach (is_array($f['reparto'] ?? null) ? $f['reparto'] : [] as $supId => $cantidad) {
			if (!isset($supervisores[(int) $supId])) {
				return 'Ese supervisor no existe o no está activo.';
			}
			$reparto[(int) $supId] = min(999999, max(0, (int) $cantidad));
		}
		if (array_sum($reparto) > $bodega) {
			return 'En «'.$material.'» repartes '.array_sum($reparto).' entre supervisores y en bodega hay '.$bodega.'.';
		}
		$limpias[] = ['id' => (int) ($f['id'] ?? 0), 'material' => $material, 'campana' => $campana, 'bodega' => $bodega, 'reparto' => $reparto];
	}
	return $limpias ?: 'Agrega al menos un material con su campaña.';
}

// Comentarios del reporte: máximo 5 líneas de 200 caracteres, como en el reporte manual.
function ep_pop_limpiar_comentarios(string $texto): ?string {
	$lineas = array_slice(array_filter(array_map(fn($l) => mb_substr(trim($l), 0, 200), preg_split('/\R/', $texto))), 0, 5);
	return $lineas ? implode("
", $lineas) : null;
}

function ep_pop_mes_texto(string $mes): string {
	$nombres = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
	return $nombres[(int) substr($mes, 5, 2)].' '.substr($mes, 0, 4);
}
