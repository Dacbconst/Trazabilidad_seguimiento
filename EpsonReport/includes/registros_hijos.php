<?php
// Tablas hijas de un registro: modelos vendidos, fotos y comentarios (una fila por elemento).

// Guarda las filas hijas de un registro ya insertado. Devuelve false si alguna falla (el llamador revierte la transacción).
function ep_hijos_guardar($db, int $registroId, array $modelos, array $fotos, array $comentarios): bool {
	$stmtM = $db->prepare('INSERT INTO insert_reporte_registro_modelo (registro_id, modelo, cantidad) VALUES (?, ?, ?)');
	$stmtF = $db->prepare('INSERT INTO insert_reporte_registro_foto (registro_id, casilla, ruta) VALUES (?, ?, ?)');
	$stmtC = $db->prepare('INSERT INTO insert_reporte_registro_comentario (registro_id, orden, texto) VALUES (?, ?, ?)');
	if (!$stmtM || !$stmtF || !$stmtC) {
		return false;
	}
	foreach ($modelos as $m) {
		$modelo = mb_substr(trim((string) ($m['modelo'] ?? '')), 0, 60, 'UTF-8');
		$cantidad = (int) ($m['cantidad'] ?? 0);
		if ($modelo === '' || $cantidad <= 0) {
			continue;
		}
		$stmtM->bind_param('isi', $registroId, $modelo, $cantidad);
		if (!$stmtM->execute()) {
			return false;
		}
	}
	foreach ($fotos as $casilla => $ruta) {
		$casilla = (string) $casilla;
		$ruta = (string) $ruta;
		$stmtF->bind_param('iss', $registroId, $casilla, $ruta);
		if (!$stmtF->execute()) {
			return false;
		}
	}
	$orden = 0;
	foreach ($comentarios as $texto) {
		$texto = mb_substr(trim((string) $texto), 0, 500, 'UTF-8');
		if ($texto === '') {
			continue;
		}
		$orden++;
		$stmtC->bind_param('iis', $registroId, $orden, $texto);
		if (!$stmtC->execute()) {
			return false;
		}
	}
	return true;
}

// Filas hijas de varios registros: [id => ['modelos' => [...], 'fotos' => [casilla => ruta], 'comentarios' => [...]]].
function ep_hijos_cargar($db, array $ids): array {
	$salida = [];
	foreach ($ids as $id) {
		$salida[(int) $id] = ['modelos' => [], 'fotos' => [], 'comentarios' => []];
	}
	if (empty($salida)) {
		return $salida;
	}
	$lista = implode(',', array_keys($salida));
	if ($r = $db->query("SELECT registro_id, modelo, cantidad FROM insert_reporte_registro_modelo WHERE registro_id IN ($lista) ORDER BY id")) {
		while ($f = $r->fetch_assoc()) {
			$salida[(int) $f['registro_id']]['modelos'][] = ['modelo' => $f['modelo'], 'cantidad' => (int) $f['cantidad']];
		}
	}
	if ($r = $db->query("SELECT registro_id, casilla, ruta FROM insert_reporte_registro_foto WHERE registro_id IN ($lista) ORDER BY id")) {
		while ($f = $r->fetch_assoc()) {
			$salida[(int) $f['registro_id']]['fotos'][$f['casilla']] = $f['ruta'];
		}
	}
	if ($r = $db->query("SELECT registro_id, texto FROM insert_reporte_registro_comentario WHERE registro_id IN ($lista) ORDER BY registro_id, orden")) {
		while ($f = $r->fetch_assoc()) {
			$salida[(int) $f['registro_id']]['comentarios'][] = $f['texto'];
		}
	}
	return $salida;
}
