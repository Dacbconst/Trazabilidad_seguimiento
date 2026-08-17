<?php
// Clientes/PDV para el formulario de Acuerdo PDV, filtrados por el
// `supervisor` del usuario logueado (ver canalDeSupervisor() en
// functions.php) — cada usuario solo ve SUS clientes, nunca la base entera
// (confirmado por el cliente en llamada, 2026-07-26).
//
// Reemplaza a repositorio_locales_dtt2 (solo tenía clientes con mercarista,
// sin segmentar por distribuidor) por repositorio_locales_supervisores_cliente
// (maestro externo de Alicorp, no se modifica su esquema, solo se consulta).
//
// Canal DISTRIBUIDOR: un supervisor puede manejar varias empresas
// distribuidoras (tipo_distribuidor) — se agrupan los clientes por empresa
// para la cascada Empresa->Cliente del formulario (mismo patrón que
// segmentos->categorías->marcas en acuerdo_catalogo.php).
// Canal Directo/Mayorista: no hay concepto de "empresa", se manda la lista
// plana de clientes.
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
