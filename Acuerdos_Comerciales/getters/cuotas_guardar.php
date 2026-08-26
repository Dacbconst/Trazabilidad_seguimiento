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
$nuevas    = 0; // filas insertadas de cero (pos_id+sector+trimestre+anio no existía antes)
$actualizadas = 0; // filas que YA existían y cambiaron de valor
$sinCambios   = 0; // filas que ya existían con exactamente el mismo dato (resubir el mismo archivo sin tocar nada)
$errores   = []; // [{indice, fila, motivo}, ...] — NO se guardaron.
$avisos    = []; // [{indice, fila, motivo}, ...] — SÍ se guardaron, pero conviene revisar (ej. sin match de cliente).
$clavesVistas = []; // pos_id|sector -> índice, para avisar de repetidos DENTRO del mismo archivo (mismo criterio que Rebate/Participación).

$mysqli->begin_transaction();
try {
	$stmt = $mysqli->prepare(
		'INSERT INTO repositorio_cuota_cliente
		 (pos_id, cliente_excel, cedi_excel, plan, sector, trimestre, anio, valores_mensuales, estado, actualizado_por)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
		 ON DUPLICATE KEY UPDATE
		   cliente_excel = VALUES(cliente_excel), cedi_excel = VALUES(cedi_excel), plan = VALUES(plan),
		   valores_mensuales = VALUES(valores_mensuales), estado = VALUES(estado), actualizado_por = VALUES(actualizado_por),
		   updated_at = NOW()'
	);
	if (!$stmt) throw new Exception('El Repositorio de Cuotas todavía no existe en la base (falta correr datos/cuota_cliente_schema.sql).');

	// mes1/mes2/mes3 son posición dentro del trimestre (ver
	// repositorio_parsear_cuotas()) — acá se convierten al índice real 0-11
	// para guardar en el MISMO formato JSON que
	// repositorio_acuerdo_lineas.valores_mensuales (ej. trimestre=2 ->
	// mesInicio=3 -> {"3": mes1, "4": mes2, "5": mes3}), así la Fase 2 puede
	// copiarlo directo a una línea de Meta de Compras sin convertir nada.
	$mesInicio = ($trimestre - 1) * 3;

	foreach ($filas as $indice => $fila) {
		$clienteExcel = repositorio_normalizar_texto($fila['cliente_excel'] ?? '');
		$cediExcel    = repositorio_normalizar_texto($fila['cedi_excel'] ?? '');
		$plan         = repositorio_normalizar_texto($fila['plan'] ?? '');
		$sector       = repositorio_normalizar_texto($fila['sector'] ?? '');
		$mes1         = is_numeric($fila['mes1'] ?? null) ? round((float) $fila['mes1'], 2) : null;
		$mes2         = is_numeric($fila['mes2'] ?? null) ? round((float) $fila['mes2'], 2) : null;
		$mes3         = is_numeric($fila['mes3'] ?? null) ? round((float) $fila['mes3'], 2) : null;
		$etiqueta     = $clienteExcel !== '' ? $clienteExcel.' / '.$sector : '(fila vacía)';

		$faltantes = [];
		if ($clienteExcel === '') $faltantes[] = 'Cliente';
		if ($sector === '') $faltantes[] = 'Categoría';
		if ($faltantes) {
			$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Falta '.implode(', ', $faltantes)];
			continue;
		}
		if ($mes1 === null || $mes1 < 0 || $mes2 === null || $mes2 < 0 || $mes3 === null || $mes3 < 0) {
			$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Los 3 montos mensuales deben ser números iguales o mayores a 0'];
			continue;
		}

		// "CATEGORIAS" del Excel puede no matchear un Sector real (ver
		// resolverSectorReal(), includes/functions.php) — ej. "POLVO
		// DETERGENTE" es en realidad Sector "POLVO" + Subcategoría
		// "DETERGENTE" pegados. Se corrige acá, antes de guardar, para que
		// el dato guardado sea un Sector real de verdad (Fase 2 lo va a usar
		// tal cual como `sector` de una línea de Meta de Compras).
		$sectorResuelto = resolverSectorReal($mysqli, $sector);
		if ($sectorResuelto === null) {
			$avisos[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'La categoría "'.$sector.'" no coincide con ningún Sector real del catálogo (ni sola ni como Sector+Subcategoría pegados) — se guardó tal cual, revisar con JW'];
		} elseif ($sectorResuelto !== $sector) {
			$avisos[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Se interpretó "'.$sector.'" como Sector "'.$sectorResuelto.'" (Subcategoría incluida en el mismo texto)'];
			$sector = $sectorResuelto;
			$etiqueta = $clienteExcel.' / '.$sector;
		}

		$posId = resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel);
		$estado = $posId ? 'pendiente_uso' : 'pendiente_match';

		// Protege una fila ya consumida (Fase 2, repositorio_cuota_cliente.estado
		// = 'usada') — resubir el mismo trimestre no puede "revivir" una cuota
		// que ya generó una Acta real, eso rompería el enlace acuerdo_id_generado
		// y haría reaparecer algo ya hecho en la campanita del asesor. Chequeo
		// aparte (no ON DUPLICATE KEY UPDATE) para poder avisar la razón exacta.
		if ($posId) {
			$stmtCheck = $mysqli->prepare(
				'SELECT estado FROM repositorio_cuota_cliente WHERE pos_id = ? AND sector = ? AND trimestre = ? AND anio = ? LIMIT 1'
			);
			if ($stmtCheck) {
				$stmtCheck->bind_param('ssii', $posId, $sector, $trimestre, $anio);
				$stmtCheck->execute();
				$existente = $stmtCheck->get_result()->fetch_assoc();
				$stmtCheck->close();
				if ($existente && $existente['estado'] === 'usada') {
					$avisos[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Esta categoría ya generó una Acta real — no se modificó (un archivo nuevo no puede "revivir" una cuota ya usada)'];
					continue;
				}
			}
		}

		$claveRepetido = ($posId ?: $clienteExcel).'|'.$sector;
		if (isset($clavesVistas[$claveRepetido])) {
			$avisos[] = ['indice' => $clavesVistas[$claveRepetido], 'fila' => $etiqueta, 'motivo' => 'Este cliente/categoría se repite más abajo en el mismo archivo — se guardó el último valor'];
		}
		$clavesVistas[$claveRepetido] = $indice;

		if (!$posId) {
			$avisos[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se encontró un cliente único con ese nombre/CEDI — queda en "Pendientes de Asignar" para resolver a mano'];
		}

		$valoresJson = json_encode([
			(string) $mesInicio       => $mes1,
			(string) ($mesInicio + 1) => $mes2,
			(string) ($mesInicio + 2) => $mes3,
		]);

		$stmt->bind_param('sssssiissi', $posId, $clienteExcel, $cediExcel, $plan, $sector, $trimestre, $anio, $valoresJson, $estado, $usuarioSesion);
		if ($stmt->execute()) {
			$guardadas++;
			// MySQL en INSERT...ON DUPLICATE KEY UPDATE informa cuál pasó vía
			// affected_rows: 1 = insert nuevo, 2 = update real (cambió algo),
			// 0 = la fila ya existía con exactamente el mismo dato (resubir el
			// mismo archivo sin cambios) — 2026-08-25, pedido explícito del
			// usuario: "el que sube el archivo tiene que entender si está
			// cargando algo nuevo o modificando algo que ya existía", no
			// alcanza con un "se guardaron N filas" genérico.
			if ($stmt->affected_rows === 1) { $nuevas++; }
			elseif ($stmt->affected_rows === 2) { $actualizadas++; }
			else { $sinCambios++; }
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
$partesDetalle = [];
if ($nuevas > 0) $partesDetalle[] = "$nuevas fila(s) nueva(s)";
if ($actualizadas > 0) $partesDetalle[] = "$actualizadas actualizada(s)";
if ($sinCambios > 0) $partesDetalle[] = "$sinCambios sin cambios (ya existían igual)";
$partesMensaje = [$partesDetalle ? 'Se guardaron '.implode(', ', $partesDetalle).'.' : 'No se guardó ninguna fila nueva.'];
if ($omitidas > 0) $partesMensaje[] = "$omitidas fila(s) NO se guardaron — revisá el detalle.";
if ($avisos) $partesMensaje[] = count($avisos).' fila(s) necesitan revisión — revisá el detalle.';
responder(true, implode(' ', $partesMensaje), [
	'guardadas' => $guardadas, 'nuevas' => $nuevas, 'actualizadas' => $actualizadas, 'sin_cambios' => $sinCambios,
	'omitidas' => $omitidas, 'errores' => $errores, 'avisos' => $avisos,
]);
?>
