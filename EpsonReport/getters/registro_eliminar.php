<?php
// Quita un registro del Historial (borrado lógico, POST codigo). Solo admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/registros_datos.php';
require_once __DIR__.'/../includes/reportes_datos.php';
header('Content-Type: application/json; charset=utf-8');

if (!ep_login_check() || !ep_es_gestor()) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No tienes permiso para esto.']);
	exit;
}

// En este servidor (nginx + PHP 8.2) un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	$codigo = trim((string) ($_POST['codigo'] ?? ''));
	$registro = ep_registro_por_codigo($codigo);
	// Ya guardado en un reporte mensual: no se borra en silencio y se deja un reporte con datos fantasma, hay que quitarlo de ahí primero.
	$reporte = $registro ? ep_registro_en_reporte((int) $registro['db_id']) : null;
	if ($reporte) {
		echo json_encode(['ok' => false, 'message' => 'Este registro ya está en el reporte mensual «'.$reporte['titulo'].'». Elimina ese reporte primero si de verdad quieres borrar el registro.']);
		exit;
	}
	// El supervisor solo elimina lo que le toca: fuera de su alcance el registro no aparece.
	$ok = $registro ? ep_registro_eliminar($codigo) : false;
	if ($ok && $registro) {
		require_once __DIR__.'/../includes/auditoria_datos.php';
		$fecha = $registro['fecha_actividad'] ?? ($registro['fecha_iso'] ?? '');
		ep_auditar('registro_eliminar', 'registro', (int) $registro['db_id'], 'Eliminó el registro '.$codigo, [
			ep_auditoria_dato('Actividad', $registro['actividad_label'] ?? ($registro['tipo'] ?? '')),
			ep_auditoria_dato('Promotor', $registro['promotor'] ?? ''),
			ep_auditoria_dato('Punto de venta', $registro['punto_venta'] ?? ''),
			ep_auditoria_dato('Fecha de la actividad', $fecha !== '' ? date('d/m/Y', strtotime($fecha)) : '—'),
		]);
	}
} catch (Throwable $e) {
	error_log('registro_eliminar: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'message' => 'No se encontró el registro o ya estaba eliminado.']);
