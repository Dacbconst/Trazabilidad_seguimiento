<?php
// Paso 2 de la subida (guarda de verdad, ver repositorio_previsualizar_excel.php)
// y también el guardado de una fila editada a mano desde la tabla — mismo
// endpoint para los dos casos, la única diferencia es cuántas filas manda el
// cliente. UPSERT (INSERT ... ON DUPLICATE KEY UPDATE) sobre la clave única
// de cada tabla (ver datos/repositorios_schema.sql): un producto/marca que ya
// existe se ACTUALIZA, uno nuevo se agrega — nunca se borra el resto del
// repositorio al subir un archivo.
//
// "El sistema tiene que poder defenderse solo" (pedido explícito 2026-08-24):
// esto es lo que hoy detecta y reporta SIN bloquear al usuario ni romper con
// un error mudo:
//   - Campos vacíos -> se omite esa fila, se dice cuál campo falta.
//   - Rebate/Participación fuera de rango (negativo, >100%) -> se omite,
//     se dice el valor recibido — nunca se guarda un número sin sentido en
//     silencio (esto va a autocompletar Actas reales más adelante).
//   - Mismo producto repetido 2+ veces DENTRO del mismo archivo -> SÍ se
//     guarda (gana el último valor, mismo criterio que un upsert normal),
//     pero se avisa — para que no sea una sorpresa por qué el número final
//     no es el que esperaban de una fila más arriba.
//   - Texto normalizado (mayúsculas + espacios colapsados) ANTES de
//     comparar/guardar — sin esto, "Lavavajillas" y "LAVAVAJILLAS " crean 2
//     filas en vez de una, porque la clave única de la tabla es exacta.
// Reporte por fila: $errores (no se guardó) y $avisos (se guardó, pero
// revisar) van en la respuesta con su `indice` (posición dentro del array
// que mandó el cliente) para que el frontend lo pueda mostrar ubicado.
// mysqli_report(MYSQLI_REPORT_OFF) (ver db_connect.php) hace que execute()
// devuelva bool en vez de tirar excepción — así se puede seguir con la fila
// siguiente sin abortar todo el guardado por un error puntual de una sola.
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
		$partes = array_filter([$fila['marca'] ?? '', $fila['categoria'] ?? '', $fila['segmento'] ?? '']);
		return $partes ? implode(' / ', $partes) : '(fila vacía)';
	}
	return ($fila['marca'] ?? '') !== '' ? $fila['marca'] : '(fila vacía)';
}

$mysqli->begin_transaction();
try {
	if ($tipo === 'rebate') {
		// eliminado_en/eliminado_por se limpian acá a propósito (2026-08-25,
		// borrado lógico — ver repositorio_eliminar.php): si el UPSERT
		// encuentra una fila que estaba borrada (mismo segmento/sector/
		// categoría/marca, el UNIQUE la sigue ocupando aunque esté borrada),
		// re-subir el Excel la reactiva sola — sin esto, la fila quedaría
		// actualizada pero seguiría invisible en el listado normal.
		$stmt = $mysqli->prepare(
			'INSERT INTO repositorio_rebate_producto (segmento, sector, categoria, marca, rebate_pct, actualizado_por)
			 VALUES (?, ?, ?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE rebate_pct = VALUES(rebate_pct), actualizado_por = VALUES(actualizado_por), updated_at = NOW(), eliminado_en = NULL, eliminado_por = NULL'
		);
		if (!$stmt) throw new Exception('El repositorio de Rebate todavía no existe en la base (falta correr datos/repositorios_schema.sql).');

		foreach ($filas as $indice => $fila) {
			$segmento  = repositorio_normalizar_texto($fila['segmento'] ?? '');
			$sector    = repositorio_normalizar_texto($fila['sector'] ?? '');
			$categoria = repositorio_normalizar_texto($fila['categoria'] ?? '');
			$marca     = repositorio_normalizar_texto($fila['marca'] ?? '');
			$rebatePct = is_numeric($fila['rebate_pct'] ?? null) ? (float) $fila['rebate_pct'] : null;
			$etiqueta  = repositorio_identificar_fila($tipo, $fila);

			$faltantes = [];
			if ($segmento === '') $faltantes[] = 'Segmento';
			if ($sector === '') $faltantes[] = 'Sector';
			if ($categoria === '') $faltantes[] = 'Categoría';
			if ($marca === '') $faltantes[] = 'Marca';
			if ($faltantes) {
				$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Falta '.implode(', ', $faltantes)];
				continue;
			}
			// Rango sano: 0%-100%. Un rebate negativo o mayor al 100% de la
			// compra no tiene sentido de negocio — mejor frenarlo acá que
			// dejarlo entrar silencioso a un catálogo que después autocompleta
			// Actas reales.
			if ($rebatePct === null || $rebatePct < 0 || $rebatePct > 1) {
				$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Rebate inválido ('.($rebatePct === null ? 'no es un número' : number_format($rebatePct * 100, 1).'%').') — debe estar entre 0% y 100%'];
				continue;
			}

			$clave = $segmento.'|'.$sector.'|'.$categoria.'|'.$marca;
			if (isset($clavesVistas[$clave])) {
				$avisos[] = ['indice' => $clavesVistas[$clave], 'fila' => $etiqueta, 'motivo' => 'Este producto se repite más abajo en el mismo archivo — se guardó el último valor'];
			}
			$clavesVistas[$clave] = $indice;

			$stmt->bind_param('ssssdi', $segmento, $sector, $categoria, $marca, $rebatePct, $usuarioSesion);
			if ($stmt->execute()) {
				$guardadas++;
			} else {
				$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Error al guardar: '.$stmt->error];
			}
		}
		$stmt->close();
	} else {
		// eliminado_en/eliminado_por en NULL acá, mismo motivo que Rebate arriba.
		$stmt = $mysqli->prepare(
			'INSERT INTO repositorio_participacion_percha (marca, participacion_pct, actualizado_por)
			 VALUES (?, ?, ?)
			 ON DUPLICATE KEY UPDATE participacion_pct = VALUES(participacion_pct), actualizado_por = VALUES(actualizado_por), updated_at = NOW(), eliminado_en = NULL, eliminado_por = NULL'
		);
		if (!$stmt) throw new Exception('El repositorio de Participación todavía no existe en la base (falta correr datos/repositorios_schema.sql).');

		foreach ($filas as $indice => $fila) {
			$marca = repositorio_normalizar_texto($fila['marca'] ?? '');
			$pct   = is_numeric($fila['participacion_pct'] ?? null) ? (float) $fila['participacion_pct'] : null;

			if ($marca === '') {
				$errores[] = ['indice' => $indice, 'fila' => '(fila vacía)', 'motivo' => 'Falta la Marca'];
				continue;
			}
			if ($pct === null || $pct < 0 || $pct > 100) {
				$errores[] = ['indice' => $indice, 'fila' => $marca, 'motivo' => 'Participación inválida ('.($pct === null ? 'no es un número' : number_format($pct, 1).'%').') — debe estar entre 0% y 100%'];
				continue;
			}

			if (isset($clavesVistas[$marca])) {
				$avisos[] = ['indice' => $clavesVistas[$marca], 'fila' => $marca, 'motivo' => 'Esta marca se repite más abajo en el mismo archivo — se guardó el último valor'];
			}
			$clavesVistas[$marca] = $indice;

			$stmt->bind_param('sdi', $marca, $pct, $usuarioSesion);
			if ($stmt->execute()) {
				$guardadas++;
			} else {
				$errores[] = ['indice' => $indice, 'fila' => $marca, 'motivo' => 'Error al guardar: '.$stmt->error];
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
$partesMensaje = ["Se guardaron $guardadas fila(s)."];
if ($omitidas > 0) $partesMensaje[] = "$omitidas fila(s) NO se guardaron — revisá el detalle.";
if ($avisos) $partesMensaje[] = count($avisos).' fila(s) se guardaron con un aviso — revisá el detalle.';
responder(true, implode(' ', $partesMensaje), ['guardadas' => $guardadas, 'omitidas' => $omitidas, 'errores' => $errores, 'avisos' => $avisos]);
?>
