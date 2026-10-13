<?php
function mesCorto($mes) {
	$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
	return isset($meses[$mes]) ? $meses[$mes] : '';
}

// "Q1 (Ene-Mar)" para períodos que son un trimestre exacto (mismo texto que el filtro de Historial); un rango irregular cae al formato anterior sin "Qx".
function periodoCorto($mesInicio, $mesFin) {
	if ($mesInicio % 3 === 0 && $mesFin === $mesInicio + 2) {
		$trimestre = intdiv($mesInicio, 3) + 1;
		return 'Q'.$trimestre.' ('.mesCorto($mesInicio).'-'.mesCorto($mesFin).')';
	}
	if ($mesInicio === $mesFin) return mesCorto($mesInicio);
	return mesCorto($mesInicio).' - '.mesCorto($mesFin);
}

// Devuelve [mesInicio, mesFin] (0-11) del trimestre 1-4, o null si no es válido.
function trimestreABounds($trimestre) {
	$trimestre = (int) $trimestre;
	if ($trimestre < 1 || $trimestre > 4) return null;
	$inicio = ($trimestre - 1) * 3;
	return [$inicio, $inicio + 2];
}

// Acta YA generada (no borrador) para este Local+Período, sin importar de qué repositorio salió — aviso cruzado entre Cuotas/Acuerdo Completo, nunca bloquea la subida.
function acuerdoActivoEnPeriodo($mysqli, $posId, $trimestre, $anio) {
	$bounds = trimestreABounds($trimestre);
	if (!$bounds) return null;
	$stmt = $mysqli->prepare(
		"SELECT a.documento_no, a.created_at, u.usuario
		 FROM repositorio_acuerdos a
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
		 WHERE a.pos_id = ? AND a.anio = ? AND a.mes_inicio = ? AND a.mes_fin = ?
		   AND a.estado NOT IN ('borrador', 'anulado')
		 LIMIT 1"
	);
	if (!$stmt) return null;
	$stmt->bind_param('siii', $posId, $anio, $bounds[0], $bounds[1]);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $fila ?: null;
}

// Mismo Local+Período ya subido en el OTRO repositorio (pendiente o usado, sin importar si ya se generó Acta) — pedido explícito: evitar que el mismo cliente quede 2 veces en "Actas Asignadas" sin que nadie se entere hasta después.
function clientePendienteEnOtroRepo($mysqli, $posId, $trimestre, $anio, $repoOrigen) {
	$tablaOtra = $repoOrigen === 'completo' ? 'repositorio_cuota_cliente' : 'repositorio_acuerdo_completo_linea';
	$stmt = $mysqli->prepare(
		"SELECT 1 FROM $tablaOtra WHERE pos_id = ? AND trimestre = ? AND anio = ? AND estado IN ('pendiente_uso', 'usada') LIMIT 1"
	);
	if (!$stmt) return false;
	$stmt->bind_param('sii', $posId, $trimestre, $anio);
	$stmt->execute();
	$existe = (bool) $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $existe;
}

// 20 días hábiles = 28 días calendario si fecha_generacion cae en día de semana, 30 si cae sábado/domingo (WEEKDAY: 0=Lun..6=Dom).
function sqlFechaLimiteFirma($col) {
	return "DATE_ADD($col, INTERVAL IF(WEEKDAY($col) > 4, 30, 28) DAY)";
}

// Misma regla que sqlFechaLimiteFirma() pero en PHP, para renderFilaHistorial() (no pasa por una query).
function fechaLimiteFirmaPhp($fechaGeneracion) {
	$d = new DateTime($fechaGeneracion);
	return $d->modify('+'.((int) $d->format('N') >= 6 ? 30 : 28).' days');
}

// Convierte "días calendario hasta el límite" a días hábiles (lun-vie) con signo: positivo = quedan, negativo = pasados. Sin feriados.
function diasHabilesRestantes($diasCalendario) {
	$n = (int) $diasCalendario;
	$d = new DateTime('today');
	$cont = 0;
	for ($i = 0; $i < abs($n); $i++) {
		if ($n > 0) {
			$d->modify('+1 day');
			if ((int) $d->format('N') < 6) $cont++;
		} else {
			if ((int) $d->format('N') < 6) $cont--;
			$d->modify('-1 day');
		}
	}
	return $cont;
}

// Un Acta con 20+ días HÁBILES desde fecha_generacion pasa a 'vencido'. Sin cron: corre cada vez que se listan Actas o se calculan alertas.
function barrer_actas_vencidas($mysqli) {
	$limite = sqlFechaLimiteFirma('fecha_generacion');
	$mysqli->query(
		"UPDATE repositorio_acuerdos
		 SET estado = 'vencido'
		 WHERE estado IN ('generado', 'enviado')
		   AND fecha_generacion IS NOT NULL
		   AND $limite < CURDATE()"
	);
}

// Actas propias por vencer, alimenta "Mis Actas" de la campanita, sin firmar con $diasUmbral días o menos.
function listar_alertas_firma_propias($mysqli, $usuarioId, $diasUmbral = 5) {
	if (!$usuarioId) return [];
	barrer_actas_vencidas($mysqli);
	$limite = sqlFechaLimiteFirma('a.fecha_generacion');
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.fecha_generacion,
		        DATEDIFF($limite, CURDATE()) AS dias_restantes
		 FROM repositorio_acuerdos a
		 WHERE a.creado_por = ?
		   AND a.estado IN ('generado', 'enviado')
		   AND a.fecha_generacion IS NOT NULL
		 HAVING dias_restantes BETWEEN 0 AND ?
		 ORDER BY dias_restantes ASC"
	);
	if (!$stmt) return [];
	// Umbral en días hábiles: la query trae el doble en calendario (cubre fines de semana) y el filtro real se hace abajo.
	$umbralCalendario = $diasUmbral * 2;
	$stmt->bind_param('ii', $usuarioId, $umbralCalendario);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	$resultado = [];
	foreach ($filas as $f) {
		$f['dias_restantes'] = diasHabilesRestantes($f['dias_restantes']);
		if ($f['dias_restantes'] >= 0 && $f['dias_restantes'] <= $diasUmbral) $resultado[] = $f;
	}
	return $resultado;
}

// $usuarioId filtra por creado_por real. $trimestre/$anio: 0="Todos". $filtroFirma: 'todos'|'firmadas'|'pendientes'.
// Canal real de un Acuerdo: lee directo la columna `canal` de repositorio_acuerdos, grabada una sola vez al crearlo (ver guardar_acuerdo.php) — nunca se vuelve a comparar contra el maestro ni ninguna otra tabla acá (pedido explícito: la única validación contra el maestro vive en Repositorios, al subir el Excel). Reusado por Historial y por los 2 export de Excel.
// Actas de clientes propios (PDVAC) no están en el maestro de Alicorp: las consultas traen ambos lados por separado (sin COALESCE en SQL, evita choques de collation) y acá se completa lo que falte.
function fusionarClienteMaestroPropio(array $filas) {
	foreach ($filas as &$f) {
		foreach (['cliente', 'canal', 'distribuidor', 'ciudad'] as $campo) {
			if (!array_key_exists($campo.'_propio', $f)) continue;
			if (($f[$campo] ?? null) === null) $f[$campo] = $f[$campo.'_propio'];
			unset($f[$campo.'_propio']);
		}
	}
	unset($f);
	return $filas;
}

function sqlCanalOrigenAcuerdo($aliasAcuerdo = 'a') {
	return "UPPER($aliasAcuerdo.canal)";
}

function listar_historial_acuerdos($mysqli, $busqueda = '', $trimestre = 0, $anio = 0, $filtroFirma = 'todos', $pagina = 1, $usuarioId = null, $porPagina = 10, $rol = null, $canal = 'total') {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';
	$anio   = (int) $anio;

	$bounds           = trimestreABounds($trimestre);
	$trimestreActivo  = $bounds ? 1 : 0;
	$mesInicioFiltro  = $bounds ? $bounds[0] : -1;
	$mesFinFiltro     = $bounds ? $bounds[1] : -1;

	// Sin user_id no hay forma de saber qué acuerdos son suyos: vacío, nunca todo el mundo.
	if (!$usuarioId) {
		return ['acuerdos' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	}

	// Corre el barrido de vencimiento para que Historial nunca muestre un Acta ya vencida.
	barrer_actas_vencidas($mysqli);

	// "Ver todo": superdesarrollador ve Actas de todos. `? = 1 OR a.creado_por = ?` fija el conteo de parámetros sin bind_param variable.
	$verTodos = ($rol === 'superdesarrollador') ? 1 : 0;

	$canalOrigenSql = sqlCanalOrigenAcuerdo('a');
	$condicionCanal = '';
	if ($canal === 'directo') {
		$condicionCanal = " AND $canalOrigenSql = 'DIRECTO'";
	} elseif ($canal === 'distribuidor') {
		$condicionCanal = " AND $canalOrigenSql = 'DISTRIBUIDOR'";
	}

	$condicionFirma = '';
	if ($filtroFirma === 'firmadas') $condicionFirma = ' AND a.acta_firmada_azure_path IS NOT NULL';
	elseif ($filtroFirma === 'pendientes') $condicionFirma = ' AND a.acta_firmada_azure_path IS NULL';

	// Cliente: SIEMPRE lo que ya se guardó al subir el Excel (Cuotas/Acuerdo Completo) — nunca el maestro, ni para buscar ni para mostrar.
	$clienteOrigenSql = "COALESCE(
		(SELECT cc.cliente_excel FROM repositorio_cuota_cliente cc WHERE cc.acuerdo_id_generado = a.id LIMIT 1),
		(SELECT acl.cliente_excel FROM repositorio_acuerdo_completo_linea acl WHERE acl.acuerdo_id_generado = a.id LIMIT 1)
	)";
	// LEFT JOIN para "Generado por": un Acta huérfana (creado_por NULL) no debe desaparecer de Historial.
	$sqlBase = "FROM repositorio_acuerdos a
		LEFT JOIN repositorio_usuarios_acuerdos ug ON ug.id = a.creado_por
		WHERE a.estado NOT IN ('borrador', 'anulado', 'vencido')
		  AND (? = 1 OR a.creado_por = ?)
		  AND COALESCE($clienteOrigenSql, '') LIKE ?
		  AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		  AND (? = 0 OR a.anio = ?)
		  $condicionFirma
		  $condicionCanal";

	// Se renderiza siempre en cada login: nunca debe tirar fatal error si el JOIN externo falla.
	$stmtTotal = $mysqli->prepare("SELECT COUNT(DISTINCT a.id) AS total $sqlBase");
	if (!$stmtTotal) {
		return ['acuerdos' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	}
	$stmtTotal->bind_param('iisiiiii', $verTodos, $usuarioId, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) {
		$pagina = $totalPaginas;
		$offset = ($pagina - 1) * $porPagina;
	}

	// Canal: columna propia de repositorio_acuerdos, grabada al crear el Acuerdo (ver guardar_acuerdo.php) — nunca el maestro.
	$canalCanonico = "UPPER(a.canal) AS canal";
	$cediOrigenSql = "COALESCE(
		(SELECT cc2.cedi_excel FROM repositorio_cuota_cliente cc2 WHERE cc2.acuerdo_id_generado = a.id LIMIT 1),
		(SELECT acl2.cedi_excel FROM repositorio_acuerdo_completo_linea acl2 WHERE acl2.acuerdo_id_generado = a.id LIMIT 1)
	)";
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.pos_id, a.documento_no, a.mes_inicio, a.mes_fin, a.fecha_generacion, a.estado, a.creado_por,
		        (a.acta_firmada_azure_path IS NOT NULL) AS tiene_firma, a.acta_firmada_mime,
		        $clienteOrigenSql AS pos_name, $cediOrigenSql AS cedi, $canalCanonico, ug.usuario AS generado_por
		 $sqlBase
		 ORDER BY a.fecha_generacion DESC, a.id DESC
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT a.id, a.pos_id, a.documento_no, a.mes_inicio, a.mes_fin, a.fecha_generacion, a.estado, a.creado_por,
			        0 AS tiene_firma, NULL AS acta_firmada_mime,
			        $clienteOrigenSql AS pos_name, $cediOrigenSql AS cedi, $canalCanonico, ug.usuario AS generado_por
			 $sqlBase
			 ORDER BY a.fecha_generacion DESC, a.id DESC
			 LIMIT ? OFFSET ?"
		);
	}
	if (!$stmt) {
		return ['acuerdos' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	}
	$stmt->bind_param('iisiiiiiii', $verTodos, $usuarioId, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio, $porPagina, $offset);
	$stmt->execute();
	$acuerdos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	rellenarClienteDeActas($mysqli, $acuerdos, 'pos_name', 'cedi', 'canal');

	return [
		'acuerdos'      => $acuerdos,
		'total'         => $total,
		'pagina'        => $pagina,
		'total_paginas' => $totalPaginas,
	];
}

// Stat tiles de Historial: respetan búsqueda/trimestre/año pero NO el filtro de firma.
function obtener_stats_historial($mysqli, $busqueda, $trimestre, $anio, $usuarioId, $rol = null, $canal = 'total') {
	$vacio = ['total' => 0, 'firmadas' => 0, 'pendientes' => 0, 'pendiente_mas_antigua' => null];
	if (!$usuarioId) return $vacio;

	$like = '%'.$busqueda.'%';
	$anio = (int) $anio;
	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;
	// Mismo criterio "ver todo" y filtro de Canal que listar_historial_acuerdos() (sqlCanalOrigenAcuerdo()).
	$verTodos = ($rol === 'superdesarrollador') ? 1 : 0;
	$condicionCanal = '';
	if ($canal === 'directo') {
		$condicionCanal = " AND ".sqlCanalOrigenAcuerdo('a')." = 'DIRECTO'";
	} elseif ($canal === 'distribuidor') {
		$condicionCanal = " AND ".sqlCanalOrigenAcuerdo('a')." = 'DISTRIBUIDOR'";
	}

	$stmt = $mysqli->prepare(
		"SELECT COUNT(DISTINCT a.id) AS total,
		        COUNT(DISTINCT CASE WHEN a.acta_firmada_azure_path IS NOT NULL THEN a.id END) AS firmadas,
		        MIN(CASE WHEN a.acta_firmada_azure_path IS NULL THEN a.fecha_generacion END) AS pendiente_mas_antigua
		 FROM repositorio_acuerdos a
		 LEFT JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		 LEFT JOIN repositorio_clientes_propiosac pc ON pc.pos_id = a.pos_id
		 WHERE a.estado NOT IN ('borrador', 'anulado', 'vencido')
		   AND (? = 1 OR a.creado_por = ?)
		   AND (d.pos_name LIKE ? OR (d.pos_id IS NULL AND pc.cliente LIKE ?))
		   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		   AND (? = 0 OR a.anio = ?)
		   $condicionCanal"
	);
	if (!$stmt) return $vacio; // acta_firmada_azure_path todavía no existe, ver CLAUDE.md (migración a Azure Blob Storage).
	$stmt->bind_param('iissiiiii', $verTodos, $usuarioId, $like, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	$total    = (int) $fila['total'];
	$firmadas = (int) $fila['firmadas'];
	return [
		'total'                 => $total,
		'firmadas'              => $firmadas,
		'pendientes'            => $total - $firmadas,
		'pendiente_mas_antigua' => $fila['pendiente_mas_antigua'],
	];
}

// Años con al menos un Acuerdo real de este usuario, para poblar el filtro "Año" sin inventar un rango fijo.
function listar_anios_disponibles($mysqli, $usuarioId, $rol = null) {
	if (!$usuarioId) return [];
	// "Ver todo": superdesarrollador ve años de todos los Acuerdos, sin filtrar por canal a propósito.
	$verTodos = ($rol === 'superdesarrollador') ? 1 : 0;
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT anio FROM repositorio_acuerdos
		 WHERE (? = 1 OR creado_por = ?) AND estado NOT IN ('borrador', 'anulado', 'vencido')
		 ORDER BY anio DESC"
	);
	if (!$stmt) return [];
	$stmt->bind_param('ii', $verTodos, $usuarioId);
	$stmt->execute();
	$anios = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'anio');
	$stmt->close();
	return array_map('intval', $anios);
}

// $mostrarCanal agrega una celda de Canal entre Localidad y Periodo, solo para quien ve los 2 canales mezclados.
function renderFilaHistorial(array $a, $mostrarCanal = false) {
	$fecha = $a['fecha_generacion'] ? date('d/m/Y', strtotime($a['fecha_generacion'])) : '—';
	$celdaCanal = '';
	$celdaGenerador = '';
	if ($mostrarCanal) {
		$esDistribuidor = ($a['canal'] ?? '') === 'DISTRIBUIDOR';
		$celdaCanal = '<td><span class="ac-badge ac-badge-canal-'.($esDistribuidor ? 'distribuidor' : 'directo').'">'.($esDistribuidor ? 'Distribuidor' : 'Directo').'</span></td>';
		// "Generado por" (solo superdesarrollador): antes había que abrir cada Acta para saber a quién se le asignó.
		$celdaGenerador = '<td>'.htmlspecialchars($a['generado_por'] ?: '—').'</td>';
	}

	// Un solo botón por fila que cambia de ícono/acción según el estado: subir si falta la firma, ver el archivo si ya está.
	$tieneFirma = !empty($a['tiene_firma']);
	// Badge "Pendiente" pasa a cuenta regresiva con 5 días o menos. $filaUrgencia marca el <tr> para la franja lateral.
	$filaUrgencia = '';
	if ($tieneFirma) {
		$firmaBadge = '<span class="ac-badge ac-badge-ok">Firmada</span>';
	} else {
		$diasRestantes = null;
		if (!empty($a['fecha_generacion']) && in_array($a['estado'] ?? '', ['generado', 'enviado'], true)) {
			$limite = fechaLimiteFirmaPhp($a['fecha_generacion']);
			$diasRestantes = diasHabilesRestantes((new DateTime('today'))->diff($limite)->format('%r%a'));
		}
		if ($diasRestantes !== null && $diasRestantes <= 5) {
			$texto = $diasRestantes <= 0 ? 'Sube la firma — hoy' : ($diasRestantes === 1 ? 'Sube la firma — 1 día hábil' : 'Sube la firma — '.$diasRestantes.' días hábiles');
			$esCritico = $diasRestantes <= 1;
			$clase = $esCritico ? 'ac-badge-critico' : 'ac-badge-urgente';
			$filaUrgencia = $esCritico ? ' ac-fila-critica' : ' ac-fila-urgente';
			$firmaBadge = '<span class="ac-badge '.$clase.'" title="Plazo de firma: 20 días hábiles desde la generación del Acta">'.$texto.'</span>';
		} else {
			$firmaBadge = '<span class="ac-badge ac-badge-revisar">Pendiente</span>';
		}
	}
	// Subir/Ver Firma y Eliminar quedan bloqueados para una Acta ajena; Ver Detalles y Descargar PDF quedan libres.
	$esPropio = (int) ($a['creado_por'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0);
	$disabledAjeno = $esPropio ? '' : ' disabled';
	$tituloAjeno = ' title="Esta Acta la generó otro asesor — solo esa cuenta puede subir la firma."';
	// El administrador puede eliminar Actas ajenas siempre que no estén firmadas.
	$esAdmin = ($_SESSION['rol'] ?? '') === 'superdesarrollador';
	$puedeEliminar = $esPropio || ($esAdmin && !$tieneFirma);
	$disabledEliminar = $puedeEliminar ? '' : ' disabled';
	$tituloEliminar = $puedeEliminar ? 'Eliminar' : ($esAdmin ? 'Esta Acta ya está firmada y la generó otro asesor, no se puede eliminar.' : 'Esta Acta la generó otro asesor — solo esa cuenta puede eliminarla.');
	$datosEliminarAjeno = (!$esPropio && $puedeEliminar) ? ' data-ajeno="1" data-generador="'.htmlspecialchars($a['generado_por'] ?? '').'" data-cliente="'.htmlspecialchars($a['pos_name'] ?? '').'"' : '';

	// .ac-row-actions-primary: en mobile es el botón más importante, necesita texto visible y buen tamaño táctil.
	$firmaBtn = $tieneFirma
		? '<button type="button" class="ac-btn-outline ac-btn-inline ac-btn-outline-success ac-row-actions-primary hist-btn-firma" data-id="'.(int) $a['id'].'" data-doc="'.htmlspecialchars($a['documento_no']).'" data-tiene-firma="1" data-mime="'.htmlspecialchars($a['acta_firmada_mime'] ?? '').'"'.$disabledAjeno.($esPropio ? ' title="Ver Acta Firmada"' : $tituloAjeno).'><span class="material-symbols-outlined">task_alt</span><span class="ac-row-actions-primary-label">Ver Firma</span></button>'
		: '<button type="button" class="ac-btn-outline ac-btn-inline ac-row-actions-primary hist-btn-firma" data-id="'.(int) $a['id'].'" data-doc="'.htmlspecialchars($a['documento_no']).'" data-tiene-firma="0"'.$disabledAjeno.($esPropio ? ' title="Subir Acta Firmada"' : $tituloAjeno).'><span class="material-symbols-outlined">upload_file</span><span class="ac-row-actions-primary-label">Subir Firma</span></button>';

	return '
	<tr data-id="'.(int) $a['id'].'" class="hist-fila'.$filaUrgencia.($mostrarCanal ? ' hist-fila-con-canal' : '').'">
		<td><button type="button" class="ac-link-id hist-btn-ver" data-id="'.(int) $a['id'].'">#'.htmlspecialchars($a['documento_no']).'</button></td>
		<td class="ac-hist-distribuidor">'.htmlspecialchars($a['pos_name']).'</td>
		<td>'.htmlspecialchars($a['cedi'] ?: '—').'</td>
		'.$celdaCanal.$celdaGenerador.'
		<td class="ac-text-center">'.htmlspecialchars(periodoCorto((int) $a['mes_inicio'], (int) $a['mes_fin'])).'</td>
		<td class="ac-text-center">'.$firmaBadge.'</td>
		<td class="ac-text-right ac-tabular">'.$fecha.'</td>
		<td class="ac-text-right">
			<div class="ac-row-actions">
				'.$firmaBtn.'
				<button type="button" class="ac-icon-btn hist-btn-descargar" data-id="'.(int) $a['id'].'" title="Descargar PDF">
					<span class="material-symbols-outlined">download</span>
				</button>
				<button type="button" class="ac-icon-btn hist-btn-ver" data-id="'.(int) $a['id'].'" title="Ver Detalles">
					<span class="material-symbols-outlined">visibility</span>
				</button>
				<button type="button" class="ac-icon-btn ac-icon-btn-danger hist-btn-eliminar" data-id="'.(int) $a['id'].'" data-doc="'.htmlspecialchars($a['documento_no']).'"'.$datosEliminarAjeno.$disabledEliminar.' title="'.htmlspecialchars($tituloEliminar).'">
					<span class="material-symbols-outlined">delete</span>
				</button>
			</div>
		</td>
	</tr>';
}

// Cabecera + 4 tablas de líneas de un Acuerdo puntual, para el detalle/Acta imprimible.
function obtener_acuerdo_detalle($mysqli, $acuerdoId) {
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.pos_id, a.anio, a.mes_inicio, a.mes_fin, a.estado, a.fecha_generacion, a.creado_por, a.sin_visibilidad,
		        u.usuario AS ejecutivo_comercial
		 FROM repositorio_acuerdos a
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
		 WHERE a.id = ? LIMIT 1"
	);
	if (!$stmt) return null;
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$cabecera = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$cabecera) return null;

	// Desambiguado por nombre (pos_id solo no es único en el maestro — bug real confirmado: el mismo pos_id resolvía a veces como "Directo", a veces como "Distribuidor" según qué fila devolviera MySQL). El nombre real del cliente se recupera del repositorio de origen (Cuotas o Acuerdo Completo), si vino de una precarga.
	$clienteOrigen = null;
	$canalOrigen = null;
	$stmtOrigenCuota = $mysqli->prepare('SELECT cliente_excel, plan FROM repositorio_cuota_cliente WHERE acuerdo_id_generado = ? LIMIT 1');
	if ($stmtOrigenCuota) {
		$stmtOrigenCuota->bind_param('i', $acuerdoId);
		$stmtOrigenCuota->execute();
		$filaOrigen = $stmtOrigenCuota->get_result()->fetch_assoc();
		$stmtOrigenCuota->close();
		if ($filaOrigen) {
			$clienteOrigen = $filaOrigen['cliente_excel'];
			$canalOrigen = ($filaOrigen['plan'] ?? '') !== '' ? 'distribuidor' : 'directo';
		}
	}
	if ($clienteOrigen === null) {
		$stmtOrigenCompleto = $mysqli->prepare('SELECT cliente_excel, plan FROM repositorio_acuerdo_completo_linea WHERE acuerdo_id_generado = ? LIMIT 1');
		if ($stmtOrigenCompleto) {
			$stmtOrigenCompleto->bind_param('i', $acuerdoId);
			$stmtOrigenCompleto->execute();
			$filaOrigen = $stmtOrigenCompleto->get_result()->fetch_assoc();
			$stmtOrigenCompleto->close();
			if ($filaOrigen) {
				$clienteOrigen = $filaOrigen['cliente_excel'];
				$canalOrigen = ($filaOrigen['plan'] ?? '') !== '' ? 'distribuidor' : 'directo';
			}
		}
	}
	$d = clienteMaestroDePosId($mysqli, $cabecera['pos_id'], $clienteOrigen, $canalOrigen);
	if (!$d) return null;
	$cabecera['pos_name'] = $d['pos_name'];
	$cabecera['cedi'] = $d['cedi'];
	$cabecera['canal'] = $d['canal'];
	$cabecera['tipo_distribuidor'] = $d['tipo_distribuidor'];

	$stmt = $mysqli->prepare(
		"SELECT tipo, segmento, sector, categoria, marca, rebate_pct, cantidad_max_percha, participacion_pct, precio_percha,
		        valores_mensuales, valor_mensual_unico, orden
		 FROM repositorio_acuerdo_lineas WHERE acuerdo_id = ? ORDER BY tipo, orden"
	);
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	// Origen del Acuerdo, calculado antes de armar las líneas: Cuotas siempre bloquea Visibilidad; Acuerdo Completo marca cada línea como bloqueada en pantalla.
	$deCuotas = false;
	$stmtCuotas = $mysqli->prepare('SELECT 1 FROM repositorio_cuota_cliente WHERE acuerdo_id_generado = ? LIMIT 1');
	if ($stmtCuotas) {
		$stmtCuotas->bind_param('i', $acuerdoId);
		$stmtCuotas->execute();
		$deCuotas = (bool) $stmtCuotas->get_result()->fetch_assoc();
		$stmtCuotas->close();
	}
	$deAcuerdoCompleto = false;
	$stmtCompleto = $mysqli->prepare('SELECT 1 FROM repositorio_acuerdo_completo_linea WHERE acuerdo_id_generado = ? LIMIT 1');
	if ($stmtCompleto) {
		$stmtCompleto->bind_param('i', $acuerdoId);
		$stmtCompleto->execute();
		$deAcuerdoCompleto = (bool) $stmtCompleto->get_result()->fetch_assoc();
		$stmtCompleto->close();
	}

	$lineas = ['meta_compra' => [], 'cabecera' => [], 'ruma' => [], 'percha' => []];
	foreach ($filas as $f) {
		$valores = $f['valores_mensuales'] !== null ? json_decode($f['valores_mensuales'], true) : [];
		$lineas[$f['tipo']][] = [
			'segmento'            => $f['segmento'],
			'sector'              => $f['sector'],
			'categoria'           => $f['categoria'],
			'marca'               => $f['marca'],
			'rebate_pct'          => $f['rebate_pct'] !== null ? (float) $f['rebate_pct'] : 0,
			'cantidad_max_percha' => (int) $f['cantidad_max_percha'],
			'participacion'       => $f['participacion_pct'] ?? '',
			'precio_percha'       => $f['precio_percha'] !== null ? (float) $f['precio_percha'] : 0,
			'valores_mensuales'   => is_array($valores) ? $valores : [],
			'valor_mensual_unico' => $f['valor_mensual_unico'] !== null ? (float) $f['valor_mensual_unico'] : 0,
			'bloqueado'           => $deAcuerdoCompleto,
		];
	}

	return [
		'id'                => (int) $cabecera['id'],
		'documento_no'      => $cabecera['documento_no'],
		'pos_id'            => $cabecera['pos_id'],
		'anio'              => (int) $cabecera['anio'],
		'mes_inicio'        => (int) $cabecera['mes_inicio'],
		'mes_fin'           => (int) $cabecera['mes_fin'],
		'estado'            => $cabecera['estado'],
		'fecha_generacion'  => $cabecera['fecha_generacion'],
		'creado_por'        => $cabecera['creado_por'] !== null ? (int) $cabecera['creado_por'] : null,
		'distribuidor'      => $cabecera['pos_name'],
		'localidad'         => $cabecera['cedi'] ?: '—',
		'es_distribuidor'   => ($cabecera['canal'] ?? null) === 'DISTRIBUIDOR',
		'sin_visibilidad'   => !empty($cabecera['sin_visibilidad']),
		'de_cuotas'         => $deCuotas,
		'de_acuerdo_completo' => $deAcuerdoCompleto,
		'empresa_distribuidora' => $cabecera['tipo_distribuidor'] ?: '',
		'ejecutivo_comercial' => $cabecera['ejecutivo_comercial'] ?: '',
		'lineas'            => $lineas,
	];
}

// Borradores propios, para "Mis Borradores", mismo scoping por creador que listar_historial_acuerdos().
function listar_borradores_usuario($mysqli, $usuarioId) {
	if (!$usuarioId) return [];
	// pos_name/cedi: SIEMPRE lo que ya se guardó al subir el Excel (Cuotas/Acuerdo Completo) — nunca el maestro.
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.pos_id, a.documento_no, a.anio, a.mes_inicio, a.mes_fin, a.updated_at,
		        COALESCE(
		          (SELECT cc.cliente_excel FROM repositorio_cuota_cliente cc WHERE cc.acuerdo_id_generado = a.id LIMIT 1),
		          (SELECT acl.cliente_excel FROM repositorio_acuerdo_completo_linea acl WHERE acl.acuerdo_id_generado = a.id LIMIT 1)
		        ) AS pos_name,
		        COALESCE(
		          (SELECT cc2.cedi_excel FROM repositorio_cuota_cliente cc2 WHERE cc2.acuerdo_id_generado = a.id LIMIT 1),
		          (SELECT acl2.cedi_excel FROM repositorio_acuerdo_completo_linea acl2 WHERE acl2.acuerdo_id_generado = a.id LIMIT 1)
		        ) AS cedi
		 FROM repositorio_acuerdos a
		 WHERE a.estado = 'borrador' AND a.creado_por = ?
		 ORDER BY a.updated_at DESC"
	);
	if (!$stmt) return [];
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	rellenarClienteDeActas($mysqli, $filas, 'pos_name', 'cedi');
	return $filas;
}
