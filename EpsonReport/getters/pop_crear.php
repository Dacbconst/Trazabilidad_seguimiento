<?php
// Abre el mes de Colocación de POP con el material que llegó a bodega (solo admin/supervisor). Se activa de inmediato.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'supervisor'], true)) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/pop_datos.php';

// En este servidor (nginx + PHP 8.2) un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	$mes = (string) ($_POST['mes'] ?? '');
	if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
		echo json_encode(['ok' => false, 'message' => 'Elige el mes del reporte.']);
		exit;
	}
	$ocupado = ep_pop_mes_ocupado($mes);
	if ($ocupado !== null) {
		echo json_encode(['ok' => false, 'message' => $ocupado === $mes ? 'Ya existe la tabla de POP de ese mes.' : 'Primero cierra el mes de '.ep_pop_mes_texto($ocupado).': solo puede haber uno abierto.']);
		exit;
	}
	$filas = ep_pop_filas_desde_post($_POST['filas'] ?? '[]');
	if (is_string($filas)) {
		echo json_encode(['ok' => false, 'message' => $filas]);
		exit;
	}
	$comentarios = ep_pop_limpiar_comentarios((string) ($_POST['comentarios'] ?? ''));
	$id = ep_pop_crear($mes, $comentarios, $filas, (int) $_SESSION['usuario_id']);
	if ($id) {
		require_once __DIR__.'/../includes/auditoria_datos.php';
		ep_auditar('pop_crear', 'pop', $id, 'Cargó el material POP de '.ep_pop_mes_texto($mes), [
			ep_auditoria_dato('Materiales', count($filas)),
			ep_auditoria_dato('Total en bodega', array_sum(array_column($filas, 'bodega'))),
		]);
	}
} catch (Throwable $e) {
	error_log('pop_crear: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
echo json_encode($id ? ['ok' => true, 'id' => $id] : ['ok' => false, 'message' => 'No se pudo cargar el mes.']);
