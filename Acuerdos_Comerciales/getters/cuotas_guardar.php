<?php
// Paso 2 de la subida del Repositorio de Cuotas: UPSERT fila por fila, resuelve
// pos_id (resolverPosIdCliente()); sin match único queda 'pendiente_match' para
// resolver a mano en "Pendientes de Asignar".
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
	// subcategoria/marca son opcionales (fallback si el ALTER no se corrió). Sin
	// rebate_pct a propósito: Cuotas nunca debe tomar Rebate del Excel.
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

	// mes1/mes2/mes3 -> índice real 0-11, mismo formato JSON que valores_mensuales.
	$mesInicio = ($trimestre - 1) * 3;

	// Cache por subida: resolverSectorReal()/resolverPosIdCliente() escanean
	// tablas sin índice útil, evita repetir la misma búsqueda por fila.
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

		// "OTRAS CATEGORIAS" se ignora del todo: JW confirmó que ya no la usa.
		if ($sector === 'OTRAS CATEGORIAS') continue;
		if ($mes1 === null || $mes1 < 0 || $mes2 === null || $mes2 < 0 || $mes3 === null || $mes3 < 0) {
			$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Los 3 montos mensuales deben ser 0 o más'];
			continue;
		}

		// "CATEGORIAS" del Excel puede traer Sector+Subcategoría pegados (ver resolverSectorReal()).
		if (!array_key_exists($sector, $cacheSector)) {
			$cacheSector[$sector] = resolverSectorReal($mysqli, $sector);
		}
		$sectorResuelto = $cacheSector[$sector];
		if ($sectorResuelto === null) {
			$avisos[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se pudo identificar la categoría "'.$sector.'" en el catálogo. Revisar con JW.'];
		} elseif ($sectorResuelto !== $sector) {
			// Interpretación sin ambigüedad, no se agrega a $avisos (ya se ve como hint antes de guardar).
			$sector = $sectorResuelto;
			$etiqueta = $clienteExcel.' / '.$sector;
		}

		$clavePos = $clienteExcel.'|'.$cediExcel;
		if (!array_key_exists($clavePos, $cachePosId)) {
			$cachePosId[$clavePos] = resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel);
		}
		$posId = $cachePosId[$clavePos];
		$estado = $posId ? 'pendiente_uso' : 'pendiente_match';

		// Protege una fila ya 'usada' (generó una Acta real): chequeo aparte del
		// UPSERT para poder avisar con el Acta real (documento_no/usuario/fecha).
		if ($posId) {
			$stmtCheck = $mysqli->prepare(
				'SELECT c.estado, a.documento_no, a.created_at, u.usuario
				 FROM repositorio_cuota_cliente c
				 LEFT JOIN repositorio_acuerdos a ON a.id = c.acuerdo_id_generado
				 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
				 WHERE c.pos_id = ? AND c.sector = ? AND c.trimestre = ? AND c.anio = ? LIMIT 1'
			);
			if ($stmtCheck) {
				$stmtCheck->bind_param('ssii', $posId, $sector, $trimestre, $anio);
				$stmtCheck->execute();
				$existente = $stmtCheck->get_result()->fetch_assoc();
				$stmtCheck->close();
				if ($existente && $existente['estado'] === 'usada') {
					$avisos[] = [
						'indice' => $indice, 'fila' => $etiqueta,
						'motivo' => 'Esta categoría ya se usó en una Acta. No se modificó.',
						'tipo' => 'ya_usada',
						'existente_documento_no' => $existente['documento_no'],
						'existente_usuario' => $existente['usuario'],
						'existente_fecha' => $existente['created_at'],
					];
					continue;
				}
			}
		}

		$claveRepetido = ($posId ?: $clienteExcel).'|'.$sector;
		if (isset($clavesVistas[$claveRepetido])) {
			// 'duplicado_archivo': propiedad del archivo en sí, no se muestra como aviso post-guardado.
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
			// affected_rows: 1=insert nuevo, 2=update real, 0=ya existía igual.
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
// "duplicado_archivo" no cuenta para el mensaje, mismo criterio que repositorio_guardar.php.
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
