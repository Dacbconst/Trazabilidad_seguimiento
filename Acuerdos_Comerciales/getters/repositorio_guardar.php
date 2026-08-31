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
		// eliminado_en/eliminado_por se limpian acá a propósito (2026-08-25,
		// borrado lógico — ver repositorio_eliminar.php): si el UPSERT
		// encuentra una fila que estaba borrada (misma ciudad/canal/sector/
		// categoría/marca, el UNIQUE la sigue ocupando aunque esté borrada),
		// re-subir el Excel la reactiva sola — sin esto, la fila quedaría
		// actualizada pero seguiría invisible en el listado normal.
		//
		// Clave (ciudad, canal, sector, categoria, marca) — NO segmento
		// (2026-08-27, rediseño: la primera versión con segmento nunca tuvo
		// filas reales; el Excel real de JW no tiene esa columna, pero SÍ
		// tiene Ciudad y Canal, que cambian el % del mismo producto — ver
		// datos/repositorios_schema.sql).
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
			// Rango sano: 0%-100%. Un rebate negativo o mayor al 100% de la
			// compra no tiene sentido de negocio — mejor frenarlo acá que
			// dejarlo entrar silencioso a un catálogo que después autocompleta
			// Actas reales.
			if ($rebatePct === null || $rebatePct < 0 || $rebatePct > 1) {
				$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'El Rebate debe ser un número entre 0% y 100%'];
				continue;
			}

			$clave = $ciudad.'|'.$canal.'|'.$sector.'|'.$categoria.'|'.$marca;
			if (isset($clavesVistas[$clave])) {
				// 'tipo' => 'duplicado_archivo' (2026-08-30): esto es una
				// propiedad del ARCHIVO en sí (2 filas apuntan al mismo
				// producto), no un problema de datos — re-subir el mismo
				// archivo lo va a decir de nuevo siempre, aunque nada haya
				// cambiado. Se etiqueta distinto para que el frontend no lo
				// muestre como "algo para revisar" después de guardar (ver
				// assets/js/repositorios.js) — bug real reportado: "subo el
				// mismo archivo y me sale la misma alerta, no debería haber
				// novedad". Mensaje simplificado (2026-08-30, mismo día,
				// pedido explícito: "mensajes simples, sencillos de
				// entender") — sin explicar el mecanismo interno del upsert.
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
		// eliminado_en/eliminado_por en NULL acá, mismo motivo que Rebate arriba.
		// Clave (ciudad, marca) — SIN categoría/subcategoría (2026-08-30,
		// rediseño con el Excel real que confirmó el usuario: las líneas de
		// Percha del Acta solo guardan Marca, nunca esos 2 campos, ver
		// datos/repositorios_schema.sql) y SIN canal (el Excel no lo trae,
		// aplica igual para Directo y Distribuidor).
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
				// 'tipo' => 'duplicado_archivo' — ver nota completa en la rama
				// de Rebate más arriba, mismo criterio acá. Mensaje
				// simplificado (2026-08-30, mismo pedido).
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
// "duplicado_archivo" (ver más arriba) no cuenta para el mensaje — es una
// propiedad fija del archivo (2 filas apuntan al mismo producto/marca), no
// algo que haya cambiado en esta subida puntual. Bug real reportado
// 2026-08-30: subir prácticamente el mismo archivo de nuevo seguía
// mostrando "X fila(s) con un aviso, revisá el detalle" — daba la
// impresión de que había algo nuevo que mirar cuando no lo había. Solo se
// cuentan acá los avisos genuinos (sector sin match, cuota ya usada,
// cliente sin resolver, etc.).
$avisosRelevantes = array_filter($avisos, function ($a) { return ($a['tipo'] ?? null) !== 'duplicado_archivo'; });
$partesMensaje = ["Se guardaron $guardadas fila(s)."];
if ($omitidas > 0) $partesMensaje[] = "$omitidas fila(s) no se guardaron. Revisá el detalle.";
if ($avisosRelevantes) $partesMensaje[] = count($avisosRelevantes).' fila(s) se guardaron con un aviso. Revisá el detalle.';
responder(true, implode(' ', $partesMensaje), ['guardadas' => $guardadas, 'omitidas' => $omitidas, 'errores' => $errores, 'avisos' => $avisos]);
?>
