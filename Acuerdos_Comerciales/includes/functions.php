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
?>
