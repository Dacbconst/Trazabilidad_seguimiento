<?php
// Mes de Colocación de POP: el gestor carga lo que llegó a bodega y los promotores lo van dando de baja al reportar.
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';
require_once __DIR__.'/reportes_datos.php';

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

function ep_pop_filas(int $popId): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$res = $db->query('SELECT id, material, campana, bodega FROM insert_reporte_pop_fila WHERE pop_id = '.$popId.' ORDER BY campana, material');
	return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// Materiales que el promotor puede elegir hoy; vacío si no hay mes abierto.
function ep_pop_materiales(): array {
	$mes = ep_pop_abierto();
	return $mes ? array_map(fn($f) => ['material' => $f['material'], 'campana' => $f['campana']], $mes['filas']) : [];
}

// Campaña que le corresponde a un material del mes abierto; null si ese material no está cargado.
function ep_pop_campana_de(string $material): ?string {
	foreach (ep_pop_materiales() as $m) {
		if ($m['material'] === $material) {
			return $m['campana'];
		}
	}
	return null;
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

// Meses de POP con sus filas y lo entregado hasta ahora; un supervisor solo ve los suyos.
function ep_pop_listar(): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$where = 'WHERE p.eliminado_en IS NULL'.(ep_es_supervisor() ? ' AND p.creado_por = '.(int) $_SESSION['usuario_id'] : '');
	$res = $db->query("SELECT p.*, u.nombre AS creador FROM insert_reporte_pop p LEFT JOIN repositorio_usuarios_reporte u ON u.id = p.creado_por $where ORDER BY p.mes DESC, p.id DESC");
	$meses = [];
	foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $row) {
		$row['filas'] = ep_pop_filas((int) $row['id']);
		$entregado = ep_pop_entregado($row['mes']);
		foreach ($row['filas'] as &$f) {
			$t = $entregado[$f['campana'].'|'.$f['material']] ?? ['canales' => 0, 'retail' => 0];
			$f['canales'] = $t['canales'];
			$f['retail'] = $t['retail'];
			$f['disponible'] = (int) $f['bodega'] - $t['canales'] - $t['retail'];
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

// Un supervisor solo toca los meses que él creó; el admin cualquiera.
function ep_pop_permitido(int $popId): bool {
	$pop = ep_pop_obtener($popId);
	if (!$pop) {
		return false;
	}
	return ep_es_admin() || (int) $pop['creado_por'] === (int) ($_SESSION['usuario_id'] ?? 0);
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
	if (!ep_pop_filas_guardar($popId, $filas)) {
		$db->rollback();
		return null;
	}
	$db->commit();
	return $popId;
}

// Reemplaza las filas del mes; se usa al crear y al editar (el mes abierto se puede corregir).
function ep_pop_filas_guardar(int $popId, array $filas): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$db->query('DELETE FROM insert_reporte_pop_fila WHERE pop_id = '.$popId);
	$stmt = $db->prepare('INSERT INTO insert_reporte_pop_fila (pop_id, material, campana, bodega) VALUES (?, ?, ?, ?)');
	foreach ($filas as $f) {
		$stmt->bind_param('issi', $popId, $f['material'], $f['campana'], $f['bodega']);
		if (!$stmt->execute()) {
			error_log('ep_pop_filas_guardar: '.$stmt->error);
			$stmt->close();
			return false;
		}
	}
	$stmt->close();
	return true;
}

function ep_pop_editar(int $popId, array $filas, ?string $comentarios): bool {
	$db = ep_db();
	if (!$db || empty($filas)) {
		return false;
	}
	$db->begin_transaction();
	$stmt = $db->prepare('UPDATE insert_reporte_pop SET comentarios = ? WHERE id = ?');
	$stmt->bind_param('si', $comentarios, $popId);
	if (!$stmt->execute() || !ep_pop_filas_guardar($popId, $filas)) {
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
		$limpias[] = ['material' => $material, 'campana' => $campana, 'bodega' => $bodega];
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
