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
	// `subcategoria`/`marca` (2026-08-28, columnas nuevas, opcionales — ver
	// datos/cuota_cliente_schema.sql): si el ALTER correspondiente todavía
	// no se corrió, se cae a un INSERT con menos columnas — mismo criterio
	// defensivo que el resto del proyecto, nunca tumbar toda la subida de
	// Cuotas por columnas nuevas que capaz faltan. **Sin `rebate_pct`**
	// (2026-08-30, pedido explícito del usuario: nunca pidió que Cuotas
	// tomara Rebate del Excel, se sacó — ver nota completa en
	// obtener_precarga_detalle(), includes/functions.php).
	$stmt = $mysqli->prepare(
		'INSERT INTO repositorio_cuota_cliente
		 (pos_id, cliente_excel, cedi_excel, plan, sector, subcategoria, marca, trimestre, anio, valores_mensuales, estado, actualizado_por)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
		 ON DUPLICATE KEY UPDATE
		   cliente_excel = VALUES(cliente_excel), cedi_excel = VALUES(cedi_excel), plan = VALUES(plan),
		   subcategoria = VALUES(subcategoria), marca = VALUES(marca),
		   valores_mensuales = VALUES(valores_mensuales), estado = VALUES(estado), actualizado_por = VALUES(actualizado_por),
		   updated_at = NOW()'
	);
	$conSubcategoriaMarca = (bool) $stmt;
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			'INSERT INTO repositorio_cuota_cliente
			 (pos_id, cliente_excel, cedi_excel, plan, sector, trimestre, anio, valores_mensuales, estado, actualizado_por)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE
			   cliente_excel = VALUES(cliente_excel), cedi_excel = VALUES(cedi_excel), plan = VALUES(plan),
			   valores_mensuales = VALUES(valores_mensuales), estado = VALUES(estado), actualizado_por = VALUES(actualizado_por),
			   updated_at = NOW()'
		);
	}
	if (!$stmt) throw new Exception('El Repositorio de Cuotas todavía no está disponible. Avisa al equipo técnico.');

	// mes1/mes2/mes3 son posición dentro del trimestre (ver
	// repositorio_parsear_cuotas()) — acá se convierten al índice real 0-11
	// para guardar en el MISMO formato JSON que
	// repositorio_acuerdo_lineas.valores_mensuales (ej. trimestre=2 ->
	// mesInicio=3 -> {"3": mes1, "4": mes2, "5": mes3}), así la Fase 2 puede
	// copiarlo directo a una línea de Meta de Compras sin convertir nada.
	$mesInicio = ($trimestre - 1) * 3;

	// Cache dentro de esta misma subida (2026-08-30, bug real reportado: "me
	// sale un guardando eterno") — resolverSectorReal()/resolverPosIdCliente()
	// hacen consultas contra tablas SIN índice útil para esa búsqueda
	// (repositorio_locales_supervisores_cliente tiene ~41.000 filas y ningún
	// índice más que `id` — decisión ya tomada de no tocar el esquema de esa
	// tabla externa, ver CLAUDE.md "Módulo Liquidación"). Un Excel de Cuotas
	// real trae MUCHAS filas del MISMO cliente (una por categoría) y a
	// menudo el MISMO texto de Sector repetido — sin cache, cada fila volvía
	// a hacer el escaneo completo aunque ya se hubiera resuelto ese mismo
	// cliente/sector antes en esta misma subida. El resultado es siempre el
	// mismo para el mismo texto de entrada dentro de una sola subida, así
	// que cachear es seguro, no una aproximación.
	$cacheSector = [];
	$cachePosId  = [];

	foreach ($filas as $indice => $fila) {
		$clienteExcel = repositorio_normalizar_texto($fila['cliente_excel'] ?? '');
		$cediExcel    = repositorio_normalizar_texto($fila['cedi_excel'] ?? '');
		$plan         = repositorio_normalizar_texto($fila['plan'] ?? '');
		$sector       = repositorio_normalizar_texto($fila['sector'] ?? '');
		$subcategoria = repositorio_normalizar_texto($fila['subcategoria'] ?? '');
		$marca        = repositorio_normalizar_texto($fila['marca'] ?? '');
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
			$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Los 3 montos mensuales deben ser 0 o más'];
			continue;
		}

		// "CATEGORIAS" del Excel puede no matchear un Sector real (ver
		// resolverSectorReal(), includes/functions.php) — ej. "POLVO
		// DETERGENTE" es en realidad Sector "POLVO" + Subcategoría
		// "DETERGENTE" pegados. Se corrige acá, antes de guardar, para que
		// el dato guardado sea un Sector real de verdad (Fase 2 lo va a usar
		// tal cual como `sector` de una línea de Meta de Compras).
		if (!array_key_exists($sector, $cacheSector)) {
			$cacheSector[$sector] = resolverSectorReal($mysqli, $sector);
		}
		$sectorResuelto = $cacheSector[$sector];
		if ($sectorResuelto === null) {
			// Mensaje simplificado (2026-08-30, pedido explícito: mensajes
			// simples, sin explicar el mecanismo interno de interpretación).
			$avisos[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se pudo identificar la categoría "'.$sector.'" en el catálogo. Revisar con JW.'];
		} elseif ($sectorResuelto !== $sector) {
			// Interpretación correcta y sin ambigüedad (ej. "POLVO DETERGENTE"
			// -> Sector "POLVO") — NO se agrega a $avisos (2026-08-28, pedido
			// explícito: "quítame demasiado ruido visual, esas informativas no
			// afectan") — con archivos reales esto pasa en casi todas las filas
			// de un Sector concatenado, y llenaba la lista de "revisá" de avisos
			// que en realidad no requieren ninguna acción. Sigue visible ANTES
			// de guardar, como hint chico bajo el badge de cada fila (ver
			// cuotas_verificar_estado.php/badgeEstadoPreview()) — solo se sacó
			// de la lista post-guardado.
			$sector = $sectorResuelto;
			$etiqueta = $clienteExcel.' / '.$sector;
		}

		$clavePos = $clienteExcel.'|'.$cediExcel;
		if (!array_key_exists($clavePos, $cachePosId)) {
			$cachePosId[$clavePos] = resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel);
		}
		$posId = $cachePosId[$clavePos];
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
					$avisos[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Esta categoría ya se usó en una Acta. No se modificó.'];
					continue;
				}
			}
		}

		$claveRepetido = ($posId ?: $clienteExcel).'|'.$sector;
		if (isset($clavesVistas[$claveRepetido])) {
			// 'tipo' => 'duplicado_archivo' (2026-08-30) — es una propiedad
			// del archivo en sí, no un problema de datos: re-subir el mismo
			// archivo lo va a decir de nuevo siempre. Etiquetado para que
			// assets/js/repositorios.js no lo muestre como "algo para
			// revisar" después de guardar, mismo criterio que Rebate/
			// Participación (ver getters/repositorio_guardar.php).
			$avisos[] = ['indice' => $clavesVistas[$claveRepetido], 'fila' => $etiqueta, 'motivo' => 'Cliente repetido en el archivo. Se usó el valor más reciente.', 'tipo' => 'duplicado_archivo'];
		}
		$clavesVistas[$claveRepetido] = $indice;

		if (!$posId) {
			$avisos[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se pudo identificar el cliente. Queda en Pendientes de Asignar.'];
		}

		$valoresJson = json_encode([
			(string) $mesInicio       => $mes1,
			(string) ($mesInicio + 1) => $mes2,
			(string) ($mesInicio + 2) => $mes3,
		]);

		if ($conSubcategoriaMarca) {
			$stmt->bind_param('sssssssiissi', $posId, $clienteExcel, $cediExcel, $plan, $sector, $subcategoria, $marca, $trimestre, $anio, $valoresJson, $estado, $usuarioSesion);
		} else {
			$stmt->bind_param('sssssiissi', $posId, $clienteExcel, $cediExcel, $plan, $sector, $trimestre, $anio, $valoresJson, $estado, $usuarioSesion);
		}
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
			$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se pudo guardar esta fila'];
		}
	}
	$stmt->close();

	$mysqli->commit();
} catch (Exception $e) {
	$mysqli->rollback();
	responder(false, 'No se pudo guardar: '.$e->getMessage());
}

$omitidas = count($errores);
// "duplicado_archivo" no cuenta para el mensaje — ver nota completa en
// getters/repositorio_guardar.php, mismo criterio acá.
$avisosRelevantes = array_filter($avisos, function ($a) { return ($a['tipo'] ?? null) !== 'duplicado_archivo'; });
$partesDetalle = [];
if ($nuevas > 0) $partesDetalle[] = "$nuevas fila(s) nueva(s)";
if ($actualizadas > 0) $partesDetalle[] = "$actualizadas actualizada(s)";
if ($sinCambios > 0) $partesDetalle[] = "$sinCambios sin cambios (ya existían igual)";
$partesMensaje = [$partesDetalle ? 'Se guardaron '.implode(', ', $partesDetalle).'.' : 'No se guardó ninguna fila nueva.'];
if ($omitidas > 0) $partesMensaje[] = "$omitidas fila(s) no se guardaron. Revisá el detalle.";
if ($avisosRelevantes) $partesMensaje[] = count($avisosRelevantes).' fila(s) necesitan revisión. Revisá el detalle.';
responder(true, implode(' ', $partesMensaje), [
	'guardadas' => $guardadas, 'nuevas' => $nuevas, 'actualizadas' => $actualizadas, 'sin_cambios' => $sinCambios,
	'omitidas' => $omitidas, 'errores' => $errores, 'avisos' => $avisos,
]);
?>
