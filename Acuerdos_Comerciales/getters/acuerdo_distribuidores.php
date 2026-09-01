<?php
// Clientes/PDV del Acuerdo PDV, filtrados por el `supervisor` del usuario
// logueado (repositorio_locales_supervisores_cliente, maestro externo de
// Alicorp) — canal Distribuidor agrupa por empresa, Directo/Mayorista va plano.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$supervisor = $_SESSION['supervisor'] ?? null;
$canal      = canalDeSupervisor($mysqli, $supervisor) ?: 'directo';

$empresas  = [];
$clientes  = [];

if ($supervisor) {
	$stmt = $mysqli->prepare(
		"SELECT pos_id, pos_name, cedi, tipo_distribuidor
		 FROM repositorio_locales_supervisores_cliente
		 WHERE supervisor = ?
		   AND pos_id IS NOT NULL AND pos_id NOT IN ('', '-')
		   AND pos_name IS NOT NULL AND pos_name <> '-'
		 ORDER BY pos_name"
	);
	$filas = [];
	if ($stmt) {
		$stmt->bind_param('s', $supervisor);
		$stmt->execute();
		$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
		$stmt->close();
	}

	foreach ($filas as $f) {
		$cliente = ['pos_id' => $f['pos_id'], 'pos_name' => $f['pos_name'], 'cedi' => $f['cedi']];
		if ($canal === 'distribuidor') {
			$empresa = $f['tipo_distribuidor'] ?: 'Sin empresa asignada';
			if (!isset($empresas[$empresa])) $empresas[$empresa] = [];
			$empresas[$empresa][] = $cliente;
		} else {
			$clientes[] = $cliente;
		}
	}
}

echo json_encode([
	'ok'        => true,
	'canal'     => $canal,
	'empresas'  => $empresas,
	'clientes'  => $clientes,
]);
?>
