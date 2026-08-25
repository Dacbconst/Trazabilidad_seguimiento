<?php
// Paso 2 de la subida del Repositorio de Cuotas (guarda de verdad, ver
// cuotas_previsualizar_excel.php) — mismo espíritu "el sistema se defiende
// solo" que getters/repositorio_guardar.php: UPSERT fila por fila, nunca
// aborta todo por un error puntual, reporta errores/avisos con su índice.
//
// Diferencia real con Rebate/Participación: acá SÍ hay cliente, así que
// además de guardar el monto hay que resolver a qué pos_id real corresponde
// cada fila (resolverPosIdCliente(), includes/functions.php) — si no
// matchea de forma única, la fila se guarda igual pero con
// estado='pendiente_match' (pos_id NULL) para resolver a mano en
// "Pendientes de Asignar" (getters/cuotas_pendientes_asignar.php).
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/repositorio_import.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

function responder($ok, $message, $extra = []) {
	echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
	exit;
}

$body      = json_decode(file_get_contents('php://input'), true);
$filas     = is_array($body['filas'] ?? null) ? $body['filas'] : [];
$trimestre = (int) ($body['trimestre'] ?? 0);
$anio      = (int) ($body['anio'] ?? 0);

if (!$filas) {
	responder(false, 'No hay filas para guardar.');
}
if ($trimestre < 1 || $trimestre > 4) {
	responder(false, 'Trimestre inválido.');
}
$anioActual = (int) date('Y');
if ($anio < $anioActual - 1 || $anio > $anioActual + 1) {
	responder(false, 'Año inválido.');
}

$usuarioSesion = $_SESSION['user_id'] ?? null;
$guardadas = 0;
$errores   = []; // [{indice, fila, motivo}, ...] — NO se guardaron.
$avisos    = []; // [{indice, fila, motivo}, ...] — SÍ se guardaron, pero conviene revisar (ej. sin match de cliente).
$clavesVistas = []; // pos_id|sector -> índice, para avisar de repetidos DENTRO del mismo archivo (mismo criterio que Rebate/Participación).

$mysqli->begin_transaction();
try {
	$stmt = $mysqli->prepare(
		'INSERT INTO repositorio_cuota_cliente
		 (pos_id, cliente_excel, cedi_excel, plan, sector, trimestre, anio, valor_mensual, estado, actualizado_por)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
		 ON DUPLICATE KEY UPDATE
		   cliente_excel = VALUES(cliente_excel), cedi_excel = VALUES(cedi_excel), plan = VALUES(plan),
		   valor_mensual = VALUES(valor_mensual), estado = VALUES(estado), actualizado_por = VALUES(actualizado_por),
		   updated_at = NOW()'
	);
	if (!$stmt) throw new Exception('El Repositorio de Cuotas todavía no existe en la base (falta correr datos/cuota_cliente_schema.sql).');

	foreach ($filas as $indice => $fila) {
		$clienteExcel = repositorio_normalizar_texto($fila['cliente_excel'] ?? '');
		$cediExcel    = repositorio_normalizar_texto($fila['cedi_excel'] ?? '');
		$plan         = repositorio_normalizar_texto($fila['plan'] ?? '');
		$sector       = repositorio_normalizar_texto($fila['sector'] ?? '');
		$valorMensual = is_numeric($fila['valor_mensual'] ?? null) ? round((float) $fila['valor_mensual'], 2) : null;
		$etiqueta     = $clienteExcel !== '' ? $clienteExcel.' / '.$sector : '(fila vacía)';

		$faltantes = [];
		if ($clienteExcel === '') $faltantes[] = 'Cliente';
		if ($sector === '') $faltantes[] = 'Categoría';
		if ($faltantes) {
			$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Falta '.implode(', ', $faltantes)];
			continue;
		}
		if ($valorMensual === null || $valorMensual < 0) {
			$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Monto mensual inválido — debe ser un número igual o mayor a 0'];
			continue;
		}

		$posId = resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel);
		$estado = $posId ? 'pendiente_uso' : 'pendiente_match';

		$claveRepetido = ($posId ?: $clienteExcel).'|'.$sector;
		if (isset($clavesVistas[$claveRepetido])) {
			$avisos[] = ['indice' => $clavesVistas[$claveRepetido], 'fila' => $etiqueta, 'motivo' => 'Este cliente/categoría se repite más abajo en el mismo archivo — se guardó el último valor'];
		}
		$clavesVistas[$claveRepetido] = $indice;

		if (!$posId) {
			$avisos[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se encontró un cliente único con ese nombre/CEDI — queda en "Pendientes de Asignar" para resolver a mano'];
		}

		$stmt->bind_param('sssssiidsi', $posId, $clienteExcel, $cediExcel, $plan, $sector, $trimestre, $anio, $valorMensual, $estado, $usuarioSesion);
		if ($stmt->execute()) {
			$guardadas++;
		} else {
			$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Error al guardar: '.$stmt->error];
		}
	}
	$stmt->close();

	$mysqli->commit();
} catch (Exception $e) {
	$mysqli->rollback();
	responder(false, 'No se pudo guardar: '.$e->getMessage());
}

$omitidas = count($errores);
$partesMensaje = ["Se guardaron $guardadas fila(s)."];
if ($omitidas > 0) $partesMensaje[] = "$omitidas fila(s) NO se guardaron — revisá el detalle.";
if ($avisos) $partesMensaje[] = count($avisos).' fila(s) necesitan revisión — revisá el detalle.';
responder(true, implode(' ', $partesMensaje), ['guardadas' => $guardadas, 'omitidas' => $omitidas, 'errores' => $errores, 'avisos' => $avisos]);
?>
