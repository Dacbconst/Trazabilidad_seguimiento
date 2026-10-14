<?php
function listar_supervisores_disponibles($mysqli) {
	$supervisores = [];
	// Excluye supervisores de prueba del maestro ("PRUEBA X") por prefijo, no lista fija.
	$res = $mysqli->query(
		"SELECT DISTINCT supervisor FROM repositorio_locales_supervisores_cliente
		 WHERE supervisor IS NOT NULL AND supervisor <> '' AND supervisor NOT LIKE 'PRUEBA %'
		 ORDER BY supervisor"
	);
	// $res puede venir en false si la tabla no existe/no es accesible — no asumir que siempre hay resultado.
	if (!$res) return $supervisores;
	while ($row = $res->fetch_assoc()) {
		$supervisores[] = $row['supervisor'];
	}
	return $supervisores;
}

// Arma el mapa [supervisor => usuario] de quién ya lo tiene (1 supervisor = 1 cuenta activa).
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

// Canal guardado en la cuenta (columna canal). Devuelve null si la columna todavía no existe o el usuario no tiene canal (ej. administradores).
function canalGuardadoUsuario($mysqli, $usuarioId) {
	$stmt = @$mysqli->prepare('SELECT canal FROM repositorio_usuarios_acuerdos WHERE id = ? LIMIT 1');
	if (!$stmt) return null;
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	$canal = $fila['canal'] ?? null;
	return in_array($canal, ['directo', 'distribuidor'], true) ? $canal : null;
}

// Nunca debe tirar fatal error: devuelve null (-> 'directo' por defecto) en vez de romper el login.
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

// Canal real de la SESIÓN actual: decide qué cartera ve el usuario en Registrar. Sin supervisor (ej. "Admin") elige el canal a mano vía admin_set_canal.php, 'directo' por default.
function canalEfectivoUsuario($mysqli) {
	if (!empty($_SESSION['canal'])) return $_SESSION['canal']; // canal guardado en la cuenta, manda sobre el deducido del maestro
	$supervisor = $_SESSION['supervisor'] ?? null;
	if ($supervisor) return canalDeSupervisor($mysqli, $supervisor) ?: 'directo';
	if (($_SESSION['rol'] ?? '') === 'superdesarrollador') return ($_SESSION['canal_admin'] ?? 'directo') === 'distribuidor' ? 'distribuidor' : 'directo';
	return 'directo';
}

// true solo para superdesarrollador sin supervisor real — el resto sigue viendo su cartera de siempre.
function esModoAdminSinCartera() {
	return ($_SESSION['rol'] ?? '') === 'superdesarrollador' && empty($_SESSION['supervisor']) && empty($_SESSION['canal']);
}

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
		"SELECT id, usuario, rol, supervisor, canal, status, created_at FROM repositorio_usuarios_acuerdos
		 WHERE usuario LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
	);
	if (!$stmt) $stmt = $mysqli->prepare(
		"SELECT id, usuario, rol, supervisor, status, created_at FROM repositorio_usuarios_acuerdos
		 WHERE usuario LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
	);
	// Mismo fallback que login(): si `supervisor` no existe todavía, no reventar con un fatal error.
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

	// Canal por fila en PHP, no SQL: pagina de a 8, no vale una subquery.
	foreach ($usuarios as &$u) {
		$u['canal'] = $u['canal'] ?? canalDeSupervisor($mysqli, $u['supervisor'] ?? null); // el guardado manda; si no hay, el deducido del maestro
	}
	unset($u);

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
	// Canal ya resuelto desde listar_usuarios_acuerdos(); array_key_exists porque puede ser null de verdad.
	$canalTexto  = array_key_exists('canal', $u)
		? ($u['canal'] === 'distribuidor' ? 'Distribuidor' : ($u['canal'] === 'directo' ? 'Directo' : '—'))
		: '—';

	return '
	<tr data-id="'.(int) $u['id'].'" class="'.$claseFila.'">
		<td>
			<div class="ac-user-cell">
				<div class="ac-avatar-initials">'.htmlspecialchars($iniciales).'</div>
				<p class="ac-user-name">'.htmlspecialchars($u['usuario']).'</p>
			</div>
		</td>
		<td><span class="ac-badge '.$rolClase.'">'.htmlspecialchars($rolLabel).'</span></td>
		<td>'.htmlspecialchars($canalTexto).'</td>
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
				<button type="button" class="ac-icon-btn ac-btn-editar" data-id="'.(int) $u['id'].'" data-usuario="'.$usuarioAttr.'" data-rol="'.$rolAttr.'" data-canal="'.htmlspecialchars($u['canal'] ?? '', ENT_QUOTES).'" title="Editar Perfil">
					<span class="material-symbols-outlined">edit</span>
				</button>
			</div>
		</td>
	</tr>';
}

// ---------- Historial de Acuerdos ---------- mismo patrón que arriba: carga inicial y refresco AJAX comparten consulta y render.
