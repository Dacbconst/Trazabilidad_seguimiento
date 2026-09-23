<?php
// Persistencia de registros de actividades en insert_reporte_registro (antes era un JSON con datos de ejemplo).
require_once __DIR__.'/db.php';
require_once __DIR__.'/fotos_datos.php';

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
	$stmt = $db->prepare('SELECT r.id AS db_id, r.codigo, r.valores, r.fotos, r.comentarios, u.usuario, u.nombre FROM insert_reporte_registro r LEFT JOIN repositorio_usuarios_reporte u ON u.id = r.usuario_id WHERE r.eliminado_en IS NULL'.$filtroIds.' ORDER BY r.created_at DESC, r.id DESC LIMIT ?');
	if (!$stmt) {
		return [];
	}
	$stmt->bind_param('i', $limite);
	$stmt->execute();
	$res = $stmt->get_result();
	$registros = [];
	while ($fila = $res->fetch_assoc()) {
		$registro = json_decode((string) $fila['valores'], true);
		if (!is_array($registro)) {
			$registro = [];
		}
		$registro['id'] = $fila['codigo'];
		$registro['db_id'] = (int) $fila['db_id'];
		$registro['promotor_usuario'] = $fila['usuario'] ?? ($registro['promotor_usuario'] ?? '');
		$registro['promotor'] = $fila['nombre'] ?: ucwords(str_replace('.', ' ', (string) $registro['promotor_usuario']));
		$registro['fotos'] = ep_fotos_desde_json((string) $fila['fotos'], (string) ($registro['tipo'] ?? ''), (string) ($registro['hora'] ?? ''));
		$registro['comentarios'] = json_decode((string) $fila['comentarios'], true) ?: [];
		$registros[] = $registro;
	}
	$stmt->close();
	return $registros;
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

// Guarda un registro ya armado: columnas para filtrar/contar, y el detalle completo en JSON para Historial.
function ep_guardar_nuevo_registro(array $registro, int $usuarioId): bool {
	$db = ep_db();
	if (!$db) {
		return false;
	}
	$fotos = $registro['fotos'] ?? [];
	$comentarios = $registro['comentarios'] ?? [];
	$totalFotos = count(array_filter($fotos, fn($f) => !empty($f['ruta'])));
	unset($registro['fotos'], $registro['comentarios']);

	$codigo = $registro['id'];
	$tipo = $registro['tipo'];
	$posId = $registro['pos_id'] ?? null;
	$puntoVenta = $registro['punto_venta'] ?? null;
	$ciudad = $registro['ciudad'] ?? null;
	$canal = $registro['canal'] ?? null;
	$fecha = $registro['fecha_iso'];
	$hora = ($registro['hora'] ?? '00:00').':00';
	$estado = $registro['estado'] ?? 'Aprobado';
	$jsonLimpio = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
	$mapaFotos = [];
	foreach ($fotos as $f) {
		if (!empty($f['ruta'])) {
			$mapaFotos[$f['id']] = $f['ruta'];
		}
	}
	$valores = json_encode($registro, $jsonLimpio);
	$fotosJson = json_encode($mapaFotos, $jsonLimpio);
	$comentariosJson = json_encode($comentarios, $jsonLimpio);

	$stmt = $db->prepare('INSERT INTO insert_reporte_registro (codigo, tipo, usuario_id, pos_id, punto_venta, ciudad, canal, fecha, hora, estado, valores, fotos, total_fotos, comentarios) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
	if (!$stmt) {
		error_log('ep_guardar_nuevo_registro: '.$db->error);
		return false;
	}
	$stmt->bind_param('ssisssssssssis', $codigo, $tipo, $usuarioId, $posId, $puntoVenta, $ciudad, $canal, $fecha, $hora, $estado, $valores, $fotosJson, $totalFotos, $comentariosJson);
	$ok = $stmt->execute();
	if (!$ok) {
		error_log('ep_guardar_nuevo_registro: '.$stmt->error);
	}
	$stmt->close();
	return $ok;
}
