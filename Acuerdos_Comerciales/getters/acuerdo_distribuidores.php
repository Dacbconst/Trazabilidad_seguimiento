<?php
// Clientes/PDV filtrados por el `supervisor` del usuario logueado; canal Distribuidor agrupa por empresa, Directo/Mayorista va plano. Excepción: superdesarrollador sin supervisor real (ver esModoAdminSinCartera()) no tiene cartera propia — ve TODOS los clientes del canal que eligió a mano (ac-canal-admin-group en registrar.php), sin filtrar por supervisor. Mismo criterio ya usado en Historial/ Seguimiento de Equipo para ese rol ("ver todo el equipo", no solo lo propio).
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$supervisor  = $_SESSION['supervisor'] ?? null;
$modoAdmin   = esModoAdminSinCartera();
$canal       = canalEfectivoUsuario($mysqli);

$empresas  = [];
$clientes  = [];

if ($modoAdmin) {
	// Sin supervisor que filtrar — trae la cartera COMPLETA del canal elegido, de cualquier supervisor. Esta tabla externa (~41,640 filas) no tiene índice en `canal` (decisión ya tomada de no tocar el esquema de un maestro externo, ver "Módulo Liquidación" en CLAUDE.md) — un escaneo completo es aceptable acá porque es 1 sola cuenta admin, no el tráfico normal de la app.
	$condicionCanal = $canal === 'distribuidor' ? "canal = 'DISTRIBUIDOR'" : "canal <> 'DISTRIBUIDOR'";
	$filas = $mysqli->query(
		"SELECT pos_id, pos_name, cedi, tipo_distribuidor
		 FROM repositorio_locales_supervisores_cliente
		 WHERE $condicionCanal
		   AND pos_id IS NOT NULL AND pos_id NOT IN ('', '-')
		   AND pos_name IS NOT NULL AND pos_name <> '-'
		 ORDER BY pos_name"
	);
	$filas = $filas ? $filas->fetch_all(MYSQLI_ASSOC) : [];

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

	// Clientes propios (el maestro de Alicorp no los tiene, ver crearClientePropio()) del mismo canal elegido.
	$filasPropias = $mysqli->query(
		"SELECT pos_id, cliente_excel AS pos_name, cedi_excel AS cedi, distribuidor_excel AS tipo_distribuidor
		 FROM repositorio_clientes_propiosac WHERE canal = '".($canal === 'distribuidor' ? 'distribuidor' : 'directo')."'
		 ORDER BY cliente_excel"
	);
	$filasPropias = $filasPropias ? $filasPropias->fetch_all(MYSQLI_ASSOC) : [];
	foreach ($filasPropias as $f) {
		$cliente = ['pos_id' => $f['pos_id'], 'pos_name' => $f['pos_name'], 'cedi' => $f['cedi']];
		if ($canal === 'distribuidor') {
			$empresa = $f['tipo_distribuidor'] ?: 'Sin empresa asignada';
			if (!isset($empresas[$empresa])) $empresas[$empresa] = [];
			$empresas[$empresa][] = $cliente;
		} else {
			$clientes[] = $cliente;
		}
	}
} elseif ($supervisor) {
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

	// Clientes propios (el maestro de Alicorp no los tiene, ver crearClientePropio()): mismo criterio de "supervisor" ya usado en resolverPosIdCliente()/maestroClientesEnMemoria() — para Directo, cedi_excel hace de supervisor; para Distribuidor, se listan todos los del canal (el maestro tampoco filtra Distribuidor por supervisor acá).
	$stmtPropio = $canal === 'distribuidor'
		? $mysqli->prepare("SELECT pos_id, cliente_excel AS pos_name, cedi_excel AS cedi, distribuidor_excel AS tipo_distribuidor FROM repositorio_clientes_propiosac WHERE canal = 'distribuidor' ORDER BY cliente_excel")
		: $mysqli->prepare("SELECT pos_id, cliente_excel AS pos_name, cedi_excel AS cedi, distribuidor_excel AS tipo_distribuidor FROM repositorio_clientes_propiosac WHERE canal = 'directo' AND cedi_excel = ? ORDER BY cliente_excel");
	if ($stmtPropio) {
		if ($canal !== 'distribuidor') $stmtPropio->bind_param('s', $supervisor);
		$stmtPropio->execute();
		$filasPropias = $stmtPropio->get_result()->fetch_all(MYSQLI_ASSOC);
		$stmtPropio->close();
		foreach ($filasPropias as $f) {
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
}

echo json_encode([
	'ok'        => true,
	'canal'     => $canal,
	'empresas'  => $empresas,
	'clientes'  => $clientes,
]);
?>
