<?php
// Lista de borradores del usuario logueado, para el modal "Mis Borradores"
// de Registrar Acuerdo PDV. Ver listar_borradores_usuario() en functions.php
// para el criterio de scoping por creado_por.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['admin', 'desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$borradores = listar_borradores_usuario($mysqli, $_SESSION['user_id'] ?? null);

echo json_encode([
	'ok'         => true,
	'borradores' => array_map(function ($b) {
		return [
			'id'           => (int) $b['id'],
			'documento_no' => $b['documento_no'],
			'distribuidor' => $b['pos_name'],
			'localidad'    => $b['cedi'] ?: '—',
			'anio'         => (int) $b['anio'],
			'periodo'      => periodoCorto((int) $b['mes_inicio'], (int) $b['mes_fin']),
			'updated_at'   => $b['updated_at'],
		];
	}, $borradores),
]);
?>
