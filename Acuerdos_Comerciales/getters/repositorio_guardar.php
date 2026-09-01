<?php
// Paso 2 de la subida (y también el guardado de una fila editada a mano): UPSERT
// sobre la clave única de cada tabla, nunca se borra el resto del repositorio.
// Reporta por fila $errores (no se guardó) y $avisos (se guardó, revisar) sin abortar todo por un error puntual.
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

$body  = json_decode(file_get_contents('php://input'), true);
$tipo  = $body['tipo'] ?? '';
$filas = is_array($body['filas'] ?? null) ? $body['filas'] : [];

if (!in_array($tipo, ['rebate', 'participacion'], true)) {
	responder(false, 'Tipo de repositorio inválido.');
}
if (!$filas) {
	responder(false, 'No hay filas para guardar.');
}

$usuarioSesion = $_SESSION['user_id'] ?? null;
$guardadas = 0;
$errores   = []; // [{indice, fila, motivo}, ...] — NO se guardaron.
$avisos    = []; // [{indice, fila, motivo}, ...] — SÍ se guardaron, pero conviene revisar.
$clavesVistas = []; // clave normalizada -> índice de la última fila que la usó, para detectar repetidos DENTRO del mismo archivo.

// Identificador legible de una fila para el mensaje — la mejor combinación
// de campos que ya tenga tipeados, aunque estén incompletos.
function repositorio_identificar_fila($tipo, $fila) {
	if ($tipo === 'rebate') {
		$partes = array_filter([$fila['marca'] ?? '', $fila['categoria'] ?? '', $fila['ciudad'] ?? '', $fila['canal'] ?? '']);
		return $partes ? implode(' / ', $partes) : '(fila vacía)';
	}
	// participacion (2026-08-30): Ciudad + Marca, mismo criterio que Rebate.
	$partes = array_filter([$fila['marca'] ?? '', $fila['ciudad'] ?? '']);
	return $partes ? implode(' / ', $partes) : '(fila vacía)';
}

$mysqli->begin_transaction();
try {
	if ($tipo === 'rebate') {
		// eliminado_en/eliminado_por se limpian acá: re-subir revive una fila borrada lógicamente.
		// Clave (ciudad, canal, sector, categoria, marca), sin segmento: el Excel real de JW no lo tiene.
		$stmt = $mysqli->prepare(
			'INSERT INTO repositorio_rebate_producto (ciudad, canal, sector, categoria, marca, rebate_pct, actualizado_por)
			 VALUES (?, ?, ?, ?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE rebate_pct = VALUES(rebate_pct), actualizado_por = VALUES(actualizado_por), updated_at = NOW(), eliminado_en = NULL, eliminado_por = NULL'
		);
		if (!$stmt) throw new Exception('El repositorio de Rebate todavía no está disponible. Avisa al equipo técnico.');

		foreach ($filas as $indice => $fila) {
			$ciudad    = repositorio_normalizar_texto($fila['ciudad'] ?? '');
			$canal     = repositorio_normalizar_texto($fila['canal'] ?? '');
			$sector    = repositorio_normalizar_texto($fila['sector'] ?? '');
			$categoria = repositorio_normalizar_texto($fila['categoria'] ?? '');
			$marca     = repositorio_normalizar_texto($fila['marca'] ?? '');
			$rebatePct = is_numeric($fila['rebate_pct'] ?? null) ? (float) $fila['rebate_pct'] : null;
			$etiqueta  = repositorio_identificar_fila($tipo, $fila);

			$faltantes = [];
			if ($ciudad === '') $faltantes[] = 'Ciudad';
			if ($canal === '') $faltantes[] = 'Canal';
			if ($sector === '') $faltantes[] = 'Categoría'; // columna interna "sector" = "Categoría" en pantalla, ver CONFIG.rebate en repositorios.js
			if ($categoria === '') $faltantes[] = 'Subcategoría'; // columna interna "categoria" = "Subcategoría" en pantalla
			if ($marca === '') $faltantes[] = 'Marca';
			if ($faltantes) {
				$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Falta '.implode(', ', $faltantes)];
				continue;
			}
			// Rango sano 0%-100%: frena acá antes de que autocomplete Actas reales.
			if ($rebatePct === null || $rebatePct < 0 || $rebatePct > 1) {
				$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'El Rebate debe ser un número entre 0% y 100%'];
				continue;
			}

			$clave = $ciudad.'|'.$canal.'|'.$sector.'|'.$categoria.'|'.$marca;
			if (isset($clavesVistas[$clave])) {
				// 'duplicado_archivo': propiedad del archivo en sí, no se muestra como aviso post-guardado.
				$avisos[] = ['indice' => $clavesVistas[$clave], 'fila' => $etiqueta, 'motivo' => 'Producto repetido en el archivo. Se usó el valor más reciente.', 'tipo' => 'duplicado_archivo'];
			}
			$clavesVistas[$clave] = $indice;

			$stmt->bind_param('sssssdi', $ciudad, $canal, $sector, $categoria, $marca, $rebatePct, $usuarioSesion);
			if ($stmt->execute()) {
				$guardadas++;
			} else {
				$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se pudo guardar esta fila'];
			}
		}
		$stmt->close();
	} else {
		// eliminado_en/eliminado_por en NULL, mismo motivo que Rebate.
		// Clave (ciudad, marca): sin categoría/subcategoría (Percha del Acta solo guarda Marca) ni canal.
		$stmt = $mysqli->prepare(
			'INSERT INTO repositorio_participacion_percha (ciudad, marca, participacion_pct, actualizado_por)
			 VALUES (?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE participacion_pct = VALUES(participacion_pct), actualizado_por = VALUES(actualizado_por), updated_at = NOW(), eliminado_en = NULL, eliminado_por = NULL'
		);
		if (!$stmt) throw new Exception('El repositorio de Participación todavía no está disponible. Avisa al equipo técnico.');

		foreach ($filas as $indice => $fila) {
			$ciudad = repositorio_normalizar_texto($fila['ciudad'] ?? '');
			$marca  = repositorio_normalizar_texto($fila['marca'] ?? '');
			$pct    = is_numeric($fila['participacion_pct'] ?? null) ? (float) $fila['participacion_pct'] : null;
			$etiqueta = repositorio_identificar_fila($tipo, $fila);

			$faltantes = [];
			if ($ciudad === '') $faltantes[] = 'Ciudad';
			if ($marca === '') $faltantes[] = 'Marca';
			if ($faltantes) {
				$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Falta '.implode(', ', $faltantes)];
				continue;
			}
			if ($pct === null || $pct < 0 || $pct > 100) {
				$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'La Participación debe ser un número entre 0% y 100%'];
				continue;
			}

			$clave = $ciudad.'|'.$marca;
			if (isset($clavesVistas[$clave])) {
				// 'duplicado_archivo', mismo criterio que la rama de Rebate arriba.
				$avisos[] = ['indice' => $clavesVistas[$clave], 'fila' => $etiqueta, 'motivo' => 'Marca repetida en el archivo. Se usó el valor más reciente.', 'tipo' => 'duplicado_archivo'];
			}
			$clavesVistas[$clave] = $indice;

			$stmt->bind_param('ssdi', $ciudad, $marca, $pct, $usuarioSesion);
			if ($stmt->execute()) {
				$guardadas++;
			} else {
				$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se pudo guardar esta fila'];
			}
		}
		$stmt->close();
	}

	$mysqli->commit();
} catch (Exception $e) {
	$mysqli->rollback();
	responder(false, 'No se pudo guardar: '.$e->getMessage());
}

$omitidas = count($errores);
// "duplicado_archivo" no cuenta para el mensaje: es propiedad fija del archivo, no algo nuevo en esta subida.
$avisosRelevantes = array_filter($avisos, function ($a) { return ($a['tipo'] ?? null) !== 'duplicado_archivo'; });
$partesMensaje = ["Se guardaron $guardadas fila(s)."];
if ($omitidas > 0) $partesMensaje[] = "$omitidas fila(s) no se guardaron. Revisá el detalle.";
if ($avisosRelevantes) $partesMensaje[] = count($avisosRelevantes).' fila(s) se guardaron con un aviso. Revisá el detalle.';
responder(true, implode(' ', $partesMensaje), ['guardadas' => $guardadas, 'omitidas' => $omitidas, 'errores' => $errores, 'avisos' => $avisos]);
?>
