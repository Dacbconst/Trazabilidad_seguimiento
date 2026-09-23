<?php
// Persistencia de registros de actividades en insert_reporte_registro (antes era un JSON con datos de ejemplo).
require_once __DIR__.'/db.php';

// Registros más recientes primero, reconstruidos con la misma forma que consume Historial. Lista vacía si la base no responde.
function ep_registros_datos(int $limite = 1000): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	$stmt = $db->prepare('SELECT r.codigo, r.valores, r.fotos, r.comentarios, u.usuario, u.nombre FROM insert_reporte_registro r LEFT JOIN repositorio_usuarios_reporte u ON u.id = r.usuario_id WHERE r.eliminado_en IS NULL ORDER BY r.created_at DESC, r.id DESC LIMIT ?');
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
		$registro['promotor_usuario'] = $fila['usuario'] ?? ($registro['promotor_usuario'] ?? '');
		$registro['promotor'] = $fila['nombre'] ?: ucwords(str_replace('.', ' ', (string) $registro['promotor_usuario']));
		$registro['fotos'] = json_decode((string) $fila['fotos'], true) ?: [];
		$registro['comentarios'] = json_decode((string) $fila['comentarios'], true) ?: [];
		$registros[] = $registro;
	}
	$stmt->close();
	return $registros;
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
	$valores = json_encode($registro, JSON_UNESCAPED_UNICODE);
	$fotosJson = json_encode($fotos, JSON_UNESCAPED_UNICODE);
	$comentariosJson = json_encode($comentarios, JSON_UNESCAPED_UNICODE);

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
