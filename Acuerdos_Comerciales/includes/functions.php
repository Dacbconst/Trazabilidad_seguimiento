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
		"SELECT id, usuario, rol FROM repositorio_usuarios_acuerdos
		 WHERE usuario = ? AND contrasena = ? AND status = 'activo' LIMIT 1"
	);
	$stmt->bind_param('ss', $usuario, $password);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$row) {
		return false;
	}

	session_regenerate_id();
	$_SESSION['user_id']  = $row['id'];
	$_SESSION['username'] = $row['usuario'];
	$_SESSION['rol']      = $row['rol'];
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
		'admin'              => 'Administrador',
		'superdesarrollador' => 'Superdesarrollador',
	];
	return isset($etiquetas[$rol]) ? $etiquetas[$rol] : $rol;
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
		"SELECT id, usuario, rol, status, created_at FROM repositorio_usuarios_acuerdos
		 WHERE usuario LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
	);
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

	return '
	<tr data-id="'.(int) $u['id'].'" class="'.$claseFila.'">
		<td>
			<div class="ac-user-cell">
				<div class="ac-avatar-initials">'.htmlspecialchars($iniciales).'</div>
				<p class="ac-user-name">'.htmlspecialchars($u['usuario']).'</p>
			</div>
		</td>
		<td><span class="ac-badge '.$rolClase.'">'.htmlspecialchars($rolLabel).'</span></td>
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
				<button type="button" class="ac-icon-btn ac-btn-editar" data-id="'.(int) $u['id'].'" data-usuario="'.$usuarioAttr.'" data-rol="'.$rolAttr.'" title="Editar Perfil">
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

// Misma fórmula que formatLocalidad() en registrar.js — se usa solo para el
// detalle del Acta (obtener_acuerdo_detalle) para que el documento impreso
// desde Historial se vea igual que el que genera Registrar. La tabla/listado
// de Historial en sí usa solo `city`, como pide el mockup del usuario.
function formatLocalidadTexto($province, $city) {
	$partes = array_filter([$province, $city], function ($p) { return $p !== null && $p !== ''; });
	return $partes ? implode(' - ', $partes) : '—';
}

function listar_historial_acuerdos($mysqli, $busqueda = '', $mes = 0, $pagina = 1, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';
	// -1 nunca calza con mes_inicio/mes_fin (0-11): con "Todos los meses" el
	// lado izquierdo del OR siempre gana y el filtro de rango queda anulado,
	// sin necesidad de armar el SQL con número variable de placeholders.
	$mesIdx = ($mes >= 1 && $mes <= 12) ? ($mes - 1) : -1;

	$sqlBase = "FROM repositorio_acuerdos a
		JOIN repositorio_locales_dtt2 d ON d.pos_id = a.pos_id
		WHERE a.estado <> 'borrador'
		  AND d.pos_name LIKE ?
		  AND (? = -1 OR (a.mes_inicio <= ? AND a.mes_fin >= ?))";

	$stmtTotal = $mysqli->prepare("SELECT COUNT(*) AS total $sqlBase");
	$stmtTotal->bind_param('siii', $like, $mesIdx, $mesIdx, $mesIdx);
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
		        d.pos_name, d.city
		 $sqlBase
		 ORDER BY a.fecha_generacion DESC, a.id DESC
		 LIMIT ? OFFSET ?"
	);
	$stmt->bind_param('siiiii', $like, $mesIdx, $mesIdx, $mesIdx, $porPagina, $offset);
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

function renderFilaHistorial(array $a) {
	$fecha = $a['fecha_generacion'] ? date('d/m/Y', strtotime($a['fecha_generacion'])) : '—';

	return '
	<tr data-id="'.(int) $a['id'].'">
		<td><button type="button" class="ac-link-id hist-btn-ver" data-id="'.(int) $a['id'].'">#'.htmlspecialchars($a['documento_no']).'</button></td>
		<td class="ac-hist-distribuidor">'.htmlspecialchars($a['pos_name']).'</td>
		<td>'.htmlspecialchars($a['city'] ?: '—').'</td>
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
			</div>
		</td>
	</tr>';
}

// Cabecera + las 4 tablas de líneas de un acuerdo puntual, para el detalle/
// Acta imprimible que se abre desde Historial (Ver Detalles / Descargar PDF).
function obtener_acuerdo_detalle($mysqli, $acuerdoId) {
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.anio, a.mes_inicio, a.mes_fin, a.estado, a.fecha_generacion,
		        d.pos_name, d.province, d.city
		 FROM repositorio_acuerdos a
		 JOIN repositorio_locales_dtt2 d ON d.pos_id = a.pos_id
		 WHERE a.id = ? LIMIT 1"
	);
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$cabecera = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$cabecera) return null;

	$stmt = $mysqli->prepare(
		"SELECT tipo, segmento, categoria, marca, rebate_pct, cantidad_max_percha, participacion_pct, precio_percha,
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
		'anio'              => (int) $cabecera['anio'],
		'mes_inicio'        => (int) $cabecera['mes_inicio'],
		'mes_fin'           => (int) $cabecera['mes_fin'],
		'estado'            => $cabecera['estado'],
		'fecha_generacion'  => $cabecera['fecha_generacion'],
		'distribuidor'      => $cabecera['pos_name'],
		'localidad'         => formatLocalidadTexto($cabecera['province'], $cabecera['city']),
		'lineas'            => $lineas,
	];
}
?>
