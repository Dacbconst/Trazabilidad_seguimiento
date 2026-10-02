<?php
// Guarda los cambios de un calendario activo (solo admin): comentarios y filas editables.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'supervisor'], true)) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/calendario_datos.php';

$calendarioId = (int) ($_POST['id'] ?? 0);
if (!ep_calendario_permitido($calendarioId)) {
	echo json_encode(['ok' => false, 'message' => 'Ese calendario no es tuyo.']);
	exit;
}
$filasJson = json_decode($_POST['filas'] ?? '[]', true);
$cal = ep_calendario_obtener($calendarioId);
if (!$cal || $cal['estado'] !== 'activo') {
	echo json_encode(['ok' => false, 'message' => 'El calendario no existe o ya está cerrado.']);
	exit;
}
// Los comentarios solo viajan si cambiaron; las filas pueden venir vacías si solo cambiaron los comentarios.
$cambiaComentarios = array_key_exists('comentarios', $_POST);
if (!is_array($filasJson) || (empty($filasJson) && !$cambiaComentarios)) {
	echo json_encode(['ok' => false, 'message' => 'No hay cambios para guardar.']);
	exit;
}

// Primero se valida todo; si una fila no pasa, no se guarda ninguna.
$cambios = [];
$vistas = [];
foreach ($filasJson as $f) {
	$filaId = (int) ($f['fila_id'] ?? 0);
	if (!ep_calendario_fila_es_editable($calendarioId, $filaId)) {
		echo json_encode(['ok' => false, 'message' => 'Una de las filas ya no se puede editar (está cumplida o su fecha ya pasó). Recarga la página.']);
		exit;
	}
	$real = ep_calendario_fila_validar($cal['canal'], (int) ($f['promotor_id'] ?? 0), (string) ($f['pos_id'] ?? ''));
	if (!$real) {
		echo json_encode(['ok' => false, 'message' => 'Una de las filas tiene un punto de venta o promotor inválidos.']);
		exit;
	}
	$antes = ep_calendario_fila_obtener($filaId);
	$fechaFila = (string) ($antes['fecha'] ?? '');
	$llave = (int) $f['promotor_id'].'|'.$f['pos_id'].'|'.$fechaFila;
	$previo = ep_calendario_fila_repetida((int) $f['promotor_id'], (string) $f['pos_id'], $fechaFila, $filaId);
	if ($previo !== null || isset($vistas[$llave])) {
		echo json_encode(['ok' => false, 'message' => ep_calendario_fila_repetida_mensaje($real['promotor_nombre'], $fechaFila, $previo ?? ep_calendario_nombre($cal))]);
		exit;
	}
	$vistas[$llave] = true;
	$cambios[] = [$filaId, (string) $f['pos_id'], (int) $f['promotor_id'], $real, ep_calendario_fila_obtener($filaId)];
}

require_once __DIR__.'/../includes/auditoria_datos.php';
$nombreCal = ep_calendario_nombre($cal);

// En este servidor (nginx + PHP 8.2) un error fatal sale como "404"; se atrapa para devolver el motivo real.
$db = ep_db();
try {
	$db->begin_transaction();
	foreach ($cambios as [$filaId, $posId, $promotorId, $real, $antes]) {
		if (!ep_calendario_fila_editar($filaId, $posId, $real['punto_venta'], $real['ciudad'], $promotorId, $real['promotor_nombre'], $real['supervisor'], (int) $_SESSION['usuario_id'])) {
			$db->rollback();
			echo json_encode(['ok' => false, 'message' => 'No se pudieron guardar los cambios.']);
			exit;
		}
		// La auditoría va en la misma transacción: si el cambio se deshace, su rastro también.
		$detalle = array_filter([
			ep_auditoria_cambio('Ciudad', $antes['ciudad'] ?? '', $real['ciudad']),
			ep_auditoria_cambio('Punto de venta', $antes['punto_venta'] ?? '', $real['punto_venta']),
			ep_auditoria_cambio('Promotor', $antes['promotor_nombre'] ?? '', $real['promotor_nombre']),
			ep_auditoria_cambio('Supervisor', $antes['supervisor_nombre'] ?? '', $real['supervisor']),
		]);
		if ($detalle) {
			$fecha = date('d/m/Y', strtotime($antes['fecha'] ?? 'now'));
			ep_auditar('calendario_fila_editar', 'calendario', $calendarioId, 'Cambió la fila del '.$fecha.' en «'.$nombreCal.'»', $detalle);
		}
	}
	if ($cambiaComentarios) {
		$comentarios = ep_calendario_limpiar_comentarios((string) $_POST['comentarios']);
		if (!ep_calendario_comentarios_guardar($calendarioId, $comentarios)) {
			$db->rollback();
			echo json_encode(['ok' => false, 'message' => 'No se pudieron guardar los comentarios.']);
			exit;
		}
		$detalle = array_filter([ep_auditoria_cambio('Comentarios', $cal['comentarios'] ?? '', $comentarios ?? '')]);
		if ($detalle) {
			ep_auditar('calendario_comentarios', 'calendario', $calendarioId, 'Cambió los comentarios de «'.$nombreCal.'»', $detalle);
		}
	}
	$db->commit();
} catch (Throwable $e) {
	$db->rollback();
	error_log('calendario_editar: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
echo json_encode(['ok' => true]);
