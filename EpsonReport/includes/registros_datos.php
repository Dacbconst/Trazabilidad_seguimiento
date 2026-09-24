<?php
// Persistencia de registros de actividades en insert_reporte_registro (antes era un JSON con datos de ejemplo).
require_once __DIR__.'/db.php';
require_once __DIR__.'/fotos_datos.php';
require_once __DIR__.'/registros_hijos.php';

// Las fotos se guardan solo como ruta relativa (id => "Activaciones/23092026184602ADMINCALENDARIO.jpg"), como en las demás tablas de Epson; la URL pública y la etiqueta se arman al leer.
const EP_FOTOS_URL_BASE = 'https://luckyecuadorweb.blob.core.windows.net/app/';

// Registros más recientes primero, reconstruidos con la misma forma que consume Historial. Lista vacía si la base no responde.
// $ids: si se pasa, solo trae esos registros (ids de la base).
function ep_registros_datos(int $limite = 1000, array $ids = []): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$filtroIds = '';
	if (!empty($ids)) {
		$filtroIds = ' AND r.id IN ('.implode(',', array_map('intval', $ids)).')';
	}
	$stmt = $db->prepare('SELECT r.id AS db_id, r.codigo, r.tipo_actividad, r.fecha_actividad, r.hora_inicio, r.hora_fin, r.tiendas_nacional, r.tiendas_coberturadas, r.visitaron, r.interactuaron, r.compraron, r.valores, u.usuario, u.nombre FROM insert_reporte_registro r LEFT JOIN repositorio_usuarios_reporte u ON u.id = r.usuario_id WHERE r.eliminado_en IS NULL'.$filtroIds.' ORDER BY r.created_at DESC, r.id DESC LIMIT ?');
	if (!$stmt) {
		return [];
	}
	$stmt->bind_param('i', $limite);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	$hijos = ep_hijos_cargar($db, array_column($filas, 'db_id'));
	return array_map(fn($fila) => ep_registro_armar($fila, $hijos[(int) $fila['db_id']]), $filas);
}

// Reconstruye un registro con la forma que consumen Historial y el PPT: columnas propias + filas hijas + el JSON con el resto.
function ep_registro_armar(array $fila, array $hijos): array {
	$registro = json_decode((string) $fila['valores'], true);
	if (!is_array($registro)) {
		$registro = [];
	}
	$registro['id'] = $fila['codigo'];
	$registro['db_id'] = (int) $fila['db_id'];
	$registro['promotor_usuario'] = $fila['usuario'] ?? ($registro['promotor_usuario'] ?? '');
	$registro['promotor'] = $fila['nombre'] ?: ucwords(str_replace('.', ' ', (string) $registro['promotor_usuario']));

	if ($fila['tipo_actividad'] !== null) {
		$registro['tipo_actividad'] = $fila['tipo_actividad'];
		$registro['fecha_actividad'] = $fila['fecha_actividad'];
		$registro['hora_inicio'] = substr((string) $fila['hora_inicio'], 0, 5);
		$registro['hora_fin'] = substr((string) $fila['hora_fin'], 0, 5);
	}
	// Los porcentajes no se guardan: se calculan aquí a partir de los conteos.
	if ($fila['tiendas_nacional'] !== null) {
		$nac = (int) $fila['tiendas_nacional'];
		$cob = (int) $fila['tiendas_coberturadas'];
		$registro['cobertura'] = ['nacional' => $nac, 'coberturadas' => $cob, 'pct' => $nac > 0 ? round(($cob / $nac) * 100, 1) : 0];
	}
	if ($fila['visitaron'] !== null) {
		$vis = (int) $fila['visitaron'];
		$inte = (int) $fila['interactuaron'];
		$com = (int) $fila['compraron'];
		$registro['embudo'] = [
			'visitaron' => $vis,
			'interactuaron' => $inte,
			'compraron' => $com,
			'tasa_interaccion_pct' => $vis > 0 ? round(($inte / $vis) * 100, 1) : 0,
			'tasa_conversion_pct' => $inte > 0 ? round(($com / $inte) * 100, 1) : 0,
			'conversion_global_pct' => $vis > 0 ? round(($com / $vis) * 100, 1) : 0,
		];
	}
	if (!empty($hijos['modelos'])) {
		$total = array_sum(array_column($hijos['modelos'], 'cantidad'));
		$registro['modelos'] = array_map(fn($m) => $m + ['pct' => $total > 0 ? round(($m['cantidad'] / $total) * 100, 1).'%' : '0%'], $hijos['modelos']);
	}
	$mapaFotos = json_encode($hijos['fotos'] ?? []);
	$registro['fotos'] = ep_fotos_desde_json($mapaFotos, (string) ($registro['tipo'] ?? ''), (string) ($registro['hora'] ?? ''));
	$registro['comentarios'] = $hijos['comentarios'] ?? [];
	return $registro;
}

// Convierte el JSON guardado (id => ruta) en la lista que usa Historial y el PPT; acepta también el formato anterior (lista completa).
function ep_fotos_desde_json(string $json, string $tipo, string $hora): array {
	$datos = json_decode($json, true);
	if (!is_array($datos)) {
		return [];
	}
	if (isset($datos[0]) && is_array($datos[0])) {
		return $datos;
	}
	$lista = [];
	foreach (ep_fotos_requeridas($tipo) as $req) {
		$ruta = (string) ($datos[$req['id']] ?? '');
		$lista[] = ['id' => $req['id'], 'label' => $req['label'], 'hora' => $hora, 'estado' => 'Verificada', 'ruta' => $ruta, 'url' => $ruta !== '' ? EP_FOTOS_URL_BASE . (strpos($ruta, 'AppEpson/') === 0 ? '' : 'AppEpson/EpsonReport/') . $ruta : ''];
	}
	return $lista;
}

// Guarda un registro ya armado: columnas propias para filtrar y sumar, tablas hijas para modelos, fotos y comentarios, y el JSON solo con el resto.
function ep_guardar_nuevo_registro(array $registro, int $usuarioId): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$fotos = $registro['fotos'] ?? [];
	$comentarios = $registro['comentarios'] ?? [];
	$modelos = $registro['modelos'] ?? [];
	$cobertura = $registro['cobertura'] ?? null;
	$embudo = $registro['embudo'] ?? null;
	$tipoActividad = $registro['tipo_actividad'] ?? null;
	$fechaActividad = $registro['fecha_actividad'] ?? null;
	$horaInicio = $registro['hora_inicio'] ?? null;
	$horaFin = $registro['hora_fin'] ?? null;
	// Lo que ya vive en columnas o tablas hijas no se repite dentro del JSON.
	unset($registro['fotos'], $registro['comentarios'], $registro['modelos'], $registro['cobertura'], $registro['embudo'], $registro['tipo_actividad'], $registro['fecha_actividad'], $registro['hora_inicio'], $registro['hora_fin']);

	$codigo = $registro['id'];
	$tipo = $registro['tipo'];
	$posId = $registro['pos_id'] ?? null;
	$puntoVenta = $registro['punto_venta'] ?? null;
	$ciudad = $registro['ciudad'] ?? null;
	$canal = $registro['canal'] ?? null;
	$fecha = $registro['fecha_iso'];
	$hora = ($registro['hora'] ?? '00:00').':00';
	$estado = $registro['estado'] ?? 'Aprobado';
	$nacional = $cobertura['nacional'] ?? null;
	$coberturadas = $cobertura['coberturadas'] ?? null;
	$visitaron = $embudo['visitaron'] ?? null;
	$interactuaron = $embudo['interactuaron'] ?? null;
	$compraron = $embudo['compraron'] ?? null;
	$valores = json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$mapaFotos = [];
	foreach ($fotos as $f) {
		if (!empty($f['ruta'])) {
			$mapaFotos[$f['id']] = $f['ruta'];
		}
	}

	$stmt = $db->prepare('INSERT INTO insert_reporte_registro (codigo, tipo, usuario_id, pos_id, punto_venta, ciudad, canal, fecha, hora, estado, tipo_actividad, fecha_actividad, hora_inicio, hora_fin, tiendas_nacional, tiendas_coberturadas, visitaron, interactuaron, compraron, valores) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
	if (!$stmt) {
		error_log('ep_guardar_nuevo_registro: '.$db->error);
		return false;
	}
	$stmt->bind_param('ssisssssssssssiiiiis', $codigo, $tipo, $usuarioId, $posId, $puntoVenta, $ciudad, $canal, $fecha, $hora, $estado, $tipoActividad, $fechaActividad, $horaInicio, $horaFin, $nacional, $coberturadas, $visitaron, $interactuaron, $compraron, $valores);
	$db->begin_transaction();
	if (!$stmt->execute()) {
		error_log('ep_guardar_nuevo_registro: '.$stmt->error);
		$stmt->close();
		$db->rollback();
		return false;
	}
	$registroId = (int) $db->insert_id;
	$stmt->close();
	if (!ep_hijos_guardar($db, $registroId, $modelos, $mapaFotos, $comentarios)) {
		error_log('ep_guardar_nuevo_registro (hijos): '.$db->error);
		$db->rollback();
		return false;
	}
	return $db->commit();
}

// Un registro por su código público (por ejemplo RACPABLOCASTELO-001), con la misma forma que ep_registros_datos; null si no existe.
function ep_registro_por_codigo(string $codigo): ?array {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$stmt = $db->prepare('SELECT id FROM insert_reporte_registro WHERE codigo = ? AND eliminado_en IS NULL LIMIT 1');
	if (!$stmt) {
		return null;
	}
	$stmt->bind_param('s', $codigo);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$fila) {
		return null;
	}
	return ep_registros_datos(1, [(int) $fila['id']])[0] ?? null;
}

// Código corto del registro: prefijo del tipo de actividad + usuario + número que sube por usuario y tipo, por ejemplo RACPABLOCASTELO-001.
function ep_codigo_registro(string $tipo, int $usuarioId, string $usuario): string {
	$prefijos = ['activaciones' => 'RAC', 'capacitaciones' => 'RCAP', 'colocacion-pop' => 'RPOP', 'epson-day' => 'RDAY', 'exhibiciones' => 'REXH', 'evento-ferias' => 'RFER'];
	$prefijo = $prefijos[$tipo] ?? 'REG';
	$limpio = strtr(mb_strtoupper($usuario, 'UTF-8'), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
	$nombre = substr(preg_replace('/[^A-Z0-9]/', '', $limpio), 0, 18);
	$numero = 1;
	$db = ep_db();
	$stmt = $db ? $db->prepare('SELECT COUNT(*) AS n FROM insert_reporte_registro WHERE usuario_id = ? AND tipo = ?') : false;
	if ($stmt) {
		$stmt->bind_param('is', $usuarioId, $tipo);
		$stmt->execute();
		$numero = (int) $stmt->get_result()->fetch_assoc()['n'] + 1;
		$stmt->close();
	}
	return $prefijo.$nombre.'-'.str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
}
