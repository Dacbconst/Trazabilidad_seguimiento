<?php
// Todo el manejo de sesión y roles vive en un solo archivo a propósito:
// este proyecto es independiente del login de Xplora y no necesita la
// separación Session.php/Auth.php que usan Pintuco/Unilever.

function iniciar_sesion() {
	if (session_status() === PHP_SESSION_NONE) {
		session_set_cookie_params([
			'httponly' => true,
			'secure'   => SECURE,
			'samesite' => 'Lax',
		]);
		session_start();
	}
}

// Decisión explícita: login simple, sin password_hash/password_verify.
// El prepared statement solo protege la consulta contra inyección SQL,
// la contraseña se compara tal cual está guardada en la tabla.
function login($usuario, $password, $mysqli) {
	$stmt = $mysqli->prepare(
		"SELECT id, usuario, rol, supervisor FROM repositorio_usuarios_acuerdos
		 WHERE usuario = ? AND contrasena = ? AND status = 'activo' LIMIT 1"
	);
	// Si todavía no se corrió el ALTER que agrega `supervisor` (ver
	// getters/alter_usuarios_supervisor.sql), prepare() devuelve false acá
	// (columna inexistente) — sin este fallback, el siguiente bind_param()
	// explota con un fatal error de PHP. Mientras la columna no exista, el
	// login sigue funcionando igual, solo que `$_SESSION['supervisor']` queda
	// null (canalDeSupervisor() ya maneja ese caso como 'directo' por defecto).
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT id, usuario, rol FROM repositorio_usuarios_acuerdos
			 WHERE usuario = ? AND contrasena = ? AND status = 'activo' LIMIT 1"
		);
	}
	$stmt->bind_param('ss', $usuario, $password);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$row) {
		return false;
	}

	session_regenerate_id();
	$_SESSION['user_id']    = $row['id'];
	$_SESSION['username']   = $row['usuario'];
	$_SESSION['rol']        = $row['rol'];
	$_SESSION['supervisor'] = $row['supervisor'] ?? null;
	return true;
}

function login_check() {
	return isset($_SESSION['user_id'], $_SESSION['rol']);
}

// El acceso por módulo NO es una jerarquía (admin tiene menos acceso que
// desarrollador: no ve Historial). Cada sección define su lista explícita de
// roles permitidos en includes/secciones.php y se valida por pertenencia simple.
function rolPermitido(array $rolesPermitidos) {
	return isset($_SESSION['rol']) && in_array($_SESSION['rol'], $rolesPermitidos, true);
}

function rolEtiqueta($rol) {
	$etiquetas = [
		'desarrollador'      => 'Desarrollador',
		'superdesarrollador' => 'Superdesarrollador',
	];
	return isset($etiquetas[$rol]) ? $etiquetas[$rol] : $rol;
}

// ---------- Canal (Directo / Distribuidor) vía supervisor ----------
// repositorio_locales_supervisores_cliente es un maestro externo de Alicorp
// (NO se modifica su esquema, solo se consulta) con una columna `canal` que
// solo puede ser DISTRIBUIDOR/COBERTURA/MAYORISTA/AUTOSERVICIO, y una columna
// `supervisor` que ES la lista real de personas que usan la plataforma
// (confirmado por el cliente en llamada, 2026-07-26). El canal de un usuario
// de Acuerdos_Comerciales NUNCA se guarda: se deriva en vivo mirando qué
// canal(es) tienen los clientes de SU supervisor, cada vez que hace falta.

function listar_supervisores_disponibles($mysqli) {
	$supervisores = [];
	$res = $mysqli->query(
		"SELECT DISTINCT supervisor FROM repositorio_locales_supervisores_cliente
		 WHERE supervisor IS NOT NULL AND supervisor <> '' ORDER BY supervisor"
	);
	// $res puede venir en false si la tabla no existe/no es accesible — no
	// asumir que siempre hay resultado (esto fue justamente lo que rompió el
	// login el 2026-07-26: un nombre de tabla mal escrito hizo que las
	// consultas a este maestro fallaran silenciosamente en cascada).
	if (!$res) return $supervisores;
	while ($row = $res->fetch_assoc()) {
		$supervisores[] = $row['supervisor'];
	}
	return $supervisores;
}

// Un supervisor = la persona real que usa la cuenta, así que no tiene sentido
// que dos logins compartan el mismo supervisor (ver canalDeSupervisor()) —
// esto arma el mapa [supervisor => usuario] de quién ya lo tiene, para que
// gestion-usuarios.php pueda ocultarlo del combo "Nuevo Usuario" y
// crear_usuario.php/actualizar_usuario.php lo puedan rechazar si igual llega
// por API directa. Solo cuenta usuarios 'activo': si se desactiva una cuenta,
// su supervisor queda libre para reasignar. $excluirId permite que, al editar
// un usuario, su propio supervisor actual no cuente como "ya tomado por otro".
function supervisores_asignados_activos($mysqli, $excluirId = 0) {
	$asignados = [];
	$stmt = $mysqli->prepare(
		"SELECT usuario, supervisor FROM repositorio_usuarios_acuerdos
		 WHERE supervisor IS NOT NULL AND supervisor <> '' AND status = 'activo' AND id <> ?"
	);
	if (!$stmt) return $asignados;
	$stmt->bind_param('i', $excluirId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	foreach ($filas as $f) {
		$asignados[$f['supervisor']] = $f['usuario'];
	}
	return $asignados;
}

// Ningún supervisor real mezcla DISTRIBUIDOR con COBERTURA/MAYORISTA (ver
// investigación de datos) — por eso alcanza con mirar si DISTRIBUIDOR está
// presente. Caso borde sin resolver: un supervisor exclusivamente MAYORISTA
// (no existe hoy en la data) caería como 'directo' por este orden de checks.
//
// Esta función se llama SIN CONDICIÓN en cada carga de Registrar (el tab por
// defecto tras el login) — por eso nunca debe poder tirar un fatal error acá,
// pase lo que pase con la tabla externa: devuelve null (-> 'directo' por
// defecto donde se usa) en vez de romper el login para todo el mundo.
function canalDeSupervisor($mysqli, $supervisor) {
	if (!$supervisor) return null;
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT canal FROM repositorio_locales_supervisores_cliente WHERE supervisor = ?"
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $supervisor);
	$stmt->execute();
	$canales = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'canal');
	$stmt->close();

	if (in_array('DISTRIBUIDOR', $canales, true)) return 'distribuidor';
	if ($canales) return 'directo';
	return null;
}

// ---------- Gestión de Usuarios (repositorio_usuarios_acuerdos) ----------
// Centralizado aquí porque tanto la carga inicial (gestion-usuarios.php) como
// el refresco por AJAX (getters/tabla_usuarios.php) necesitan la misma consulta
// y el mismo render de fila, para no duplicar el SQL ni el HTML.

function listar_usuarios_acuerdos($mysqli, $busqueda = '', $pagina = 1, $porPagina = 8) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';

	$stmtTotal = $mysqli->prepare(
		"SELECT COUNT(*) AS total FROM repositorio_usuarios_acuerdos WHERE usuario LIKE ?"
	);
	$stmtTotal->bind_param('s', $like);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) {
		$pagina = $totalPaginas;
		$offset = ($pagina - 1) * $porPagina;
	}

	$stmt = $mysqli->prepare(
		"SELECT id, usuario, rol, supervisor, status, created_at FROM repositorio_usuarios_acuerdos
		 WHERE usuario LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
	);
	// Mismo fallback que login() — si `supervisor` todavía no existe en la
	// base, no reventar Gestión de Usuarios con un fatal error.
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT id, usuario, rol, NULL AS supervisor, status, created_at FROM repositorio_usuarios_acuerdos
			 WHERE usuario LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
		);
	}
	$stmt->bind_param('sii', $like, $porPagina, $offset);
	$stmt->execute();
	$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return [
		'usuarios'      => $usuarios,
		'total'         => $total,
		'pagina'        => $pagina,
		'total_paginas' => $totalPaginas,
	];
}

function inicialesUsuario($usuario) {
	$partes = preg_split('/[._\s-]+/', $usuario, -1, PREG_SPLIT_NO_EMPTY);
	if (count($partes) >= 2) {
		return strtoupper(substr($partes[0], 0, 1).substr($partes[1], 0, 1));
	}
	return strtoupper(substr($usuario, 0, 2));
}

function renderFilaUsuario(array $u, $sessionUserId) {
	$iniciales   = inicialesUsuario($u['usuario']);
	$rolClase    = 'ac-badge-'.$u['rol'];
	$rolLabel    = rolEtiqueta($u['rol']);
	$fecha       = date('Y-m-d', strtotime($u['created_at']));
	$checked     = $u['status'] === 'activo' ? 'checked' : '';
	$esActual    = ((int) $u['id'] === (int) $sessionUserId);
	$disabled    = $esActual ? 'disabled title="No puedes desactivar tu propia cuenta"' : '';
	$claseFila   = $u['status'] === 'inactivo' ? 'ac-row-inactivo' : '';
	$usuarioAttr = htmlspecialchars($u['usuario'], ENT_QUOTES);
	$rolAttr     = htmlspecialchars($u['rol'], ENT_QUOTES);
	$supervisorAttr = htmlspecialchars($u['supervisor'] ?? '', ENT_QUOTES);

	return '
	<tr data-id="'.(int) $u['id'].'" class="'.$claseFila.'">
		<td>
			<div class="ac-user-cell">
				<div class="ac-avatar-initials">'.htmlspecialchars($iniciales).'</div>
				<p class="ac-user-name">'.htmlspecialchars($u['usuario']).'</p>
			</div>
		</td>
		<td><span class="ac-badge '.$rolClase.'">'.htmlspecialchars($rolLabel).'</span></td>
		<td>'.htmlspecialchars($u['supervisor'] ?: '—').'</td>
		<td class="ac-mono">'.htmlspecialchars($fecha).'</td>
		<td>
			<label class="ac-switch">
				<input type="checkbox" class="ac-toggle-estado" data-id="'.(int) $u['id'].'" '.$checked.' '.$disabled.'>
				<span class="ac-slider"></span>
			</label>
		</td>
		<td class="ac-text-right">
			<div class="ac-row-actions">
				<button type="button" class="ac-icon-btn ac-btn-clave" data-id="'.(int) $u['id'].'" data-usuario="'.$usuarioAttr.'" title="Modificar Clave">
					<span class="material-symbols-outlined">key</span>
				</button>
				<button type="button" class="ac-icon-btn ac-btn-editar" data-id="'.(int) $u['id'].'" data-usuario="'.$usuarioAttr.'" data-rol="'.$rolAttr.'" data-supervisor="'.$supervisorAttr.'" title="Editar Perfil">
					<span class="material-symbols-outlined">edit</span>
				</button>
			</div>
		</td>
	</tr>';
}

// ---------- Historial de Acuerdos (repositorio_acuerdos) ----------
// Mismo patrón que arriba: la carga inicial (historial.php) y el refresco
// por AJAX (getters/listar_historial.php) comparten la misma consulta y el
// mismo render de fila para no duplicar SQL ni HTML.

function mesCorto($mes) {
	$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
	return isset($meses[$mes]) ? $meses[$mes] : '';
}

function periodoCorto($mesInicio, $mesFin) {
	if ($mesInicio === $mesFin) return mesCorto($mesInicio);
	return mesCorto($mesInicio).' - '.mesCorto($mesFin);
}

// Devuelve [mesInicio, mesFin] (0-11) del trimestre 1-4, o null si no es un
// trimestre válido — el Período del Acuerdo es siempre un trimestre fijo
// desde el 2026-08-18 (ver CLAUDE.md), así que filtrar por trimestre exacto
// alcanza (ya no hace falta un filtro de rango "se solapa con").
function trimestreABounds($trimestre) {
	$trimestre = (int) $trimestre;
	if ($trimestre < 1 || $trimestre > 4) return null;
	$inicio = ($trimestre - 1) * 3;
	return [$inicio, $inicio + 2];
}

// $usuarioId filtra a "solo los acuerdos que ESTE usuario creó"
// (repositorio_acuerdos.creado_por, guardado por guardar_acuerdo.php al
// insertar). Antes se inferÃ­a indirectamente vÃ­a supervisor/territorio; ahora
// es el dato real, así que sigue siendo correcto aunque un supervisor cambie
// de territorio o dos usuarios lo compartan.
// $trimestre: 1-4, o 0/inválido = "Todos los períodos". $anio: 0 = "Todos los años".
function listar_historial_acuerdos($mysqli, $busqueda = '', $trimestre = 0, $anio = 0, $pagina = 1, $usuarioId = null, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';
	$anio   = (int) $anio;

	$bounds           = trimestreABounds($trimestre);
	$trimestreActivo  = $bounds ? 1 : 0;
	$mesInicioFiltro  = $bounds ? $bounds[0] : -1;
	$mesFinFiltro     = $bounds ? $bounds[1] : -1;

	// Sin user_id (no debería pasar si ya hizo login, pero por las dudas) no
	// hay forma de saber qué acuerdos "son suyos" — vacío, no mostrar los de
	// todo el mundo.
	if (!$usuarioId) {
		return ['acuerdos' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	}

	// El JOIN es solo para mostrar pos_name/cedi — repositorio_locales_
	// supervisores_cliente puede tener varias filas con el mismo pos_id bajo
	// distintos supervisores (~1,116 duplicados detectados), por eso el
	// GROUP BY a.id de abajo, para que un mismo Acuerdo no se duplique en el
	// listado por esas filas repetidas.
	$sqlBase = "FROM repositorio_acuerdos a
		JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		WHERE a.estado NOT IN ('borrador', 'anulado')
		  AND a.creado_por = ?
		  AND d.pos_name LIKE ?
		  AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		  AND (? = 0 OR a.anio = ?)";

	// Este componente se renderiza SIEMPRE (visible u oculto) en cada login de
	// desarrollador/superdesarrollador, sea cual sea la pestaña activa (ver
	// index.php: incluye el PHP de todas las secciones visibles, no solo la
	// activa) — por eso, igual que canalDeSupervisor(), esta función nunca
	// debe poder tirar un fatal error si el JOIN externo falla por lo que sea.
	$stmtTotal = $mysqli->prepare("SELECT COUNT(DISTINCT a.id) AS total $sqlBase");
	if (!$stmtTotal) {
		return ['acuerdos' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	}
	$stmtTotal->bind_param('isiiiii', $usuarioId, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) {
		$pagina = $totalPaginas;
		$offset = ($pagina - 1) * $porPagina;
	}

	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.mes_inicio, a.mes_fin, a.fecha_generacion, a.estado,
		        d.pos_name, d.cedi
		 $sqlBase
		 GROUP BY a.id
		 ORDER BY a.fecha_generacion DESC, a.id DESC
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) {
		return ['acuerdos' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	}
	$stmt->bind_param('isiiiiiii', $usuarioId, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio, $porPagina, $offset);
	$stmt->execute();
	$acuerdos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return [
		'acuerdos'      => $acuerdos,
		'total'         => $total,
		'pagina'        => $pagina,
		'total_paginas' => $totalPaginas,
	];
}

// Años con al menos un Acuerdo real (no borrador/anulado) de este usuario —
// para poblar el filtro "Año" de Historial sin inventar un rango fijo.
function listar_anios_disponibles($mysqli, $usuarioId) {
	if (!$usuarioId) return [];
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT anio FROM repositorio_acuerdos
		 WHERE creado_por = ? AND estado NOT IN ('borrador', 'anulado')
		 ORDER BY anio DESC"
	);
	if (!$stmt) return [];
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$anios = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'anio');
	$stmt->close();
	return array_map('intval', $anios);
}

function renderFilaHistorial(array $a) {
	$fecha = $a['fecha_generacion'] ? date('d/m/Y', strtotime($a['fecha_generacion'])) : '—';

	return '
	<tr data-id="'.(int) $a['id'].'">
		<td><button type="button" class="ac-link-id hist-btn-ver" data-id="'.(int) $a['id'].'">#'.htmlspecialchars($a['documento_no']).'</button></td>
		<td class="ac-hist-distribuidor">'.htmlspecialchars($a['pos_name']).'</td>
		<td>'.htmlspecialchars($a['cedi'] ?: '—').'</td>
		<td class="ac-text-center">'.htmlspecialchars(periodoCorto((int) $a['mes_inicio'], (int) $a['mes_fin'])).'</td>
		<td class="ac-text-right ac-tabular">'.$fecha.'</td>
		<td class="ac-text-right">
			<div class="ac-row-actions">
				<button type="button" class="ac-icon-btn hist-btn-descargar" data-id="'.(int) $a['id'].'" title="Descargar PDF">
					<span class="material-symbols-outlined">download</span>
				</button>
				<button type="button" class="ac-icon-btn hist-btn-ver" data-id="'.(int) $a['id'].'" title="Ver Detalles">
					<span class="material-symbols-outlined">visibility</span>
				</button>
				<button type="button" class="ac-icon-btn ac-icon-btn-danger hist-btn-eliminar" data-id="'.(int) $a['id'].'" data-doc="'.htmlspecialchars($a['documento_no']).'" title="Eliminar">
					<span class="material-symbols-outlined">delete</span>
				</button>
			</div>
		</td>
	</tr>';
}

// Cabecera + las 4 tablas de líneas de un acuerdo puntual, para el detalle/
// Acta imprimible que se abre desde Historial (Ver Detalles / Descargar PDF).
function obtener_acuerdo_detalle($mysqli, $acuerdoId) {
	// LIMIT 1 alcanza aunque repositorio_locales_supervisores_cliente tenga
	// pos_id duplicados (~1,116 detectados) — cualquiera de las filas
	// duplicadas tiene el mismo pos_name/cedi para ese pos_id.
	// LEFT JOIN (no JOIN normal) a usuarios_acuerdos: acuerdos viejos con
	// creado_por=NULL (huérfanos, ver CLAUDE.md) no deben tirar todo el
	// detalle abajo — simplemente el Acta sale sin nombre de Ejecutivo
	// Comercial (vuelve a la línea en blanco de siempre, ver acta_pdf.php).
	// d.canal: mismo maestro externo de arriba, esta vez para saber si ESTE
	// pos_id puntual es DISTRIBUIDOR — decide qué formato de Acta usar (ver
	// generar_acta_html()). Se lee del cliente real, no del supervisor de la
	// sesión que lo generó (que puede cambiar con el tiempo).
	// d.tipo_distribuidor: la "Empresa Distribuidora" del cliente (columna que
	// YA existía en el maestro, usada también por acuerdo_distribuidores.php
	// para la cascada Empresa->Cliente) — en el Acta de canal Distribuidor va
	// en "Estimado(a)", separado del nombre del Local (`pos_name`), que sigue
	// yendo en la frase "Jabonería Wilson y ..." (ver acta_pdf.php, 2026-08-20).
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.pos_id, a.anio, a.mes_inicio, a.mes_fin, a.estado, a.fecha_generacion, a.creado_por,
		        d.pos_name, d.cedi, d.canal, d.tipo_distribuidor, u.usuario AS ejecutivo_comercial
		 FROM repositorio_acuerdos a
		 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
		 WHERE a.id = ? LIMIT 1"
	);
	if (!$stmt) return null;
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$cabecera = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$cabecera) return null;

	$stmt = $mysqli->prepare(
		"SELECT tipo, segmento, sector, categoria, marca, rebate_pct, cantidad_max_percha, participacion_pct, precio_percha,
		        valores_mensuales, valor_mensual_unico, orden
		 FROM repositorio_acuerdo_lineas WHERE acuerdo_id = ? ORDER BY tipo, orden"
	);
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

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
		'empresa_distribuidora' => $cabecera['tipo_distribuidor'] ?: '',
		'ejecutivo_comercial' => $cabecera['ejecutivo_comercial'] ?: '',
		'lineas'            => $lineas,
	];
}

// Borradores propios del usuario logueado (estado='borrador' AND creado_por),
// para la lista "Mis Borradores" de Registrar Acuerdo PDV — mismo criterio de
// scoping por creador que listar_historial_acuerdos(), nunca los de otro
// usuario aunque comparta supervisor/territorio.
function listar_borradores_usuario($mysqli, $usuarioId) {
	if (!$usuarioId) return [];
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.anio, a.mes_inicio, a.mes_fin, a.updated_at,
		        d.pos_name, d.cedi
		 FROM repositorio_acuerdos a
		 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		 WHERE a.estado = 'borrador' AND a.creado_por = ?
		 GROUP BY a.id
		 ORDER BY a.updated_at DESC"
	);
	if (!$stmt) return [];
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return $filas;
}
?>
