<?php
// Gestión de usuarios (solo admin): lista con ciudad y canal del rutero, edición de correo, rol, foto, clave y estado.
require_once __DIR__.'/db.php';
require_once __DIR__.'/login_datos.php';
require_once __DIR__.'/auditoria_datos.php';

const EP_ROLES_USUARIO = ['admin' => 'Administrador', 'supervisor' => 'Supervisor', 'promotor' => 'Promotor'];
// Qué categorías de punto de venta ve un promotor; vacío = se deduce de su ruta en Xplora (como antes).
const EP_CATEGORIAS_PDV = ['' => 'Por su ruta en Xplora', 'todas' => 'Todas las categorías', 'retail' => 'Retail y las demás, menos canales', 'canales' => 'Canales y las demás, menos retail'];
const EP_CANAL_ETIQUETA = ['RETAIL' => 'Retail', 'CANALES' => 'Canales'];
const EP_FOTO_USUARIO_MAX = 5 * 1024 * 1024;

// La columna foto se agrega aparte (ALTER a mano); mientras no exista todo lo demás sigue funcionando.
function ep_usuarios_tiene_foto($db): bool {
	static $tiene = null;
	if ($tiene === null) {
		$res = $db->query("SHOW COLUMNS FROM repositorio_usuarios_reporte LIKE 'foto'");
		$tiene = $res && $res->num_rows > 0;
	}
	return $tiene;
}

// Mientras la columna rol no acepte "supervisor" (falta el ALTER), no se puede crear ni cambiar a ese rol.
function ep_usuarios_acepta_supervisor($db): bool {
	static $acepta = null;
	if ($acepta === null) {
		$res = $db->query("SHOW COLUMNS FROM repositorio_usuarios_reporte LIKE 'rol'");
		$fila = $res ? $res->fetch_assoc() : null;
		$acepta = $fila && strpos((string) $fila['Type'], 'supervisor') !== false;
	}
	return $acepta;
}

function ep_usuario_foto_url(?string $ruta): string {
	if (!$ruta) {
		return '';
	}
	require_once __DIR__.'/azure_storage.php';
	return ep_azure_url(EP_AZURE_PREFIX.$ruta);
}

// Ciudad y canal de cada usuario según su rutero activo (solo informativo, tablas de Xplora en solo lectura).
function ep_usuarios_rutero($db): array {
	$stmt = $db->prepare("SELECT usu.user AS usuario, d.city, d.channel, COUNT(DISTINCT d.pos_id) AS n FROM rutero_pdv rp JOIN repositorio_usuarios usu ON usu.id = rp.id_usuario JOIN repositorio_locales_dtt2 d ON d.id = rp.id_pdv AND d.activar = 'SI' WHERE rp.status = 1 AND rp.habilitado = 1 AND d.channel IN ('RETAIL', 'CANALES') GROUP BY usu.user, d.city, d.channel");
	if (!$stmt) {
		return [];
	}
	$stmt->execute();
	$ciudades = [];
	$canales = [];
	foreach ($stmt->get_result() as $f) {
		$ciudades[$f['usuario']][$f['city']] = ($ciudades[$f['usuario']][$f['city']] ?? 0) + (int) $f['n'];
		$canales[$f['usuario']][$f['channel']] = ($canales[$f['usuario']][$f['channel']] ?? 0) + (int) $f['n'];
	}
	$stmt->close();
	$salida = [];
	foreach ($canales as $usuario => $conteo) {
		arsort($conteo);
		$total = array_sum($conteo);
		$canal = EP_CANAL_ETIQUETA[array_key_first($conteo)];
		if (count($conteo) === 2 && (min($conteo) / $total) >= EP_PDV_MINORIA_MIXTO) {
			$canal = 'Retail y Canales';
		}
		arsort($ciudades[$usuario]);
		$salida[$usuario] = ['ciudad' => (string) array_key_first($ciudades[$usuario]), 'canal' => $canal];
	}
	return $salida;
}

function ep_usuarios_listar(): array {
	$db = ep_db();
	if (!$db) {
		return [];
	}
	require_once __DIR__.'/pdv_datos.php';
	$colFoto = ep_usuarios_tiene_foto($db) ? 'foto' : 'NULL AS foto';
	require_once __DIR__.'/aprobacion_datos.php';
	$colRuta = ep_usuarios_tiene_ruta($db) ? 'categorias, supervisor_canales_id, supervisor_retail_id' : "NULL AS categorias, NULL AS supervisor_canales_id, NULL AS supervisor_retail_id";
	$res = $db->query("SELECT id, usuario, nombre, correo, rol, status, $colFoto, $colRuta FROM repositorio_usuarios_reporte ORDER BY status = 'activo' DESC, nombre ASC");
	if (!$res) {
		error_log('ep_usuarios_listar: '.$db->error);
		return [];
	}
	$rutero = ep_usuarios_rutero($db);
	$lista = [];
	foreach ($res as $f) {
		$esAdmin = $f['rol'] !== 'promotor';
		$datos = $rutero[$f['usuario']] ?? null;
		$lista[] = [
			'id' => (int) $f['id'],
			'usuario' => $f['usuario'],
			'nombre' => $f['nombre'] ?: $f['usuario'],
			'correo' => (string) $f['correo'],
			'rol' => $f['rol'],
			'rol_label' => EP_ROLES_USUARIO[$f['rol']] ?? $f['rol'],
			'activo' => $f['status'] === 'activo',
			'foto' => ep_usuario_foto_url($f['foto']),
			'ciudad' => $datos['ciudad'] ?? '',
			'canal' => $esAdmin ? 'No aplica' : ($datos['canal'] ?? 'Sin rutero'),
			'propio' => (int) $f['id'] === (int) ($_SESSION['usuario_id'] ?? 0),
			'categorias' => (string) $f['categorias'],
			'sup_canales' => (int) $f['supervisor_canales_id'],
			'sup_retail' => (int) $f['supervisor_retail_id'],
			'con_ruta' => ep_usuarios_tiene_ruta($db),
		];
	}
	return $lista;
}

function ep_usuario_obtener(int $id): ?array {
	$db = ep_db();
	if (!$db) {
		return null;
	}
	$stmt = $db->prepare('SELECT id, usuario, nombre, correo, rol, status FROM repositorio_usuarios_reporte WHERE id = ? LIMIT 1');
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $fila ?: null;
}

function ep_usuario_nombre(array $u): string {
	return $u['nombre'] ?: $u['usuario'];
}

// Cambia correo y rol (el nombre y la ciudad no se editan). Devuelve ['ok' => bool, 'message' => texto].
function ep_usuario_actualizar(int $id, string $correo, string $rol): array {
	$u = ep_usuario_obtener($id);
	if (!$u) {
		return ['ok' => false, 'message' => 'El usuario no existe.'];
	}
	$correo = strtolower(trim($correo));
	if ($correo !== '' && (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150)) {
		return ['ok' => false, 'message' => 'Escribe un correo válido.'];
	}
	if (!isset(EP_ROLES_USUARIO[$rol])) {
		return ['ok' => false, 'message' => 'El rol no es válido.'];
	}
	if ($rol === 'supervisor' && !ep_usuarios_acepta_supervisor(ep_db())) {
		return ['ok' => false, 'message' => 'Falta ejecutar el SQL que agrega el rol Supervisor en la base de datos.'];
	}
	if ($id === (int) ($_SESSION['usuario_id'] ?? 0) && $rol !== $u['rol']) {
		return ['ok' => false, 'message' => 'No puedes cambiar tu propio rol.'];
	}
	$cambios = array_values(array_filter([
		ep_auditoria_cambio('Correo', $u['correo'], $correo),
		ep_auditoria_cambio('Rol', EP_ROLES_USUARIO[$u['rol']] ?? $u['rol'], EP_ROLES_USUARIO[$rol]),
	]));
	if (!$cambios) {
		return ['ok' => true, 'cambio' => false, 'message' => 'No hubo cambios.'];
	}
	$db = ep_db();
	// Si cambia de rol se cierra su sesión abierta: entra de nuevo ya con el rol nuevo.
	$stmt = $db->prepare('UPDATE repositorio_usuarios_reporte SET correo = ?, rol = ?'.($rol !== $u['rol'] ? ', sesion_token = NULL' : '').' WHERE id = ?');
	$stmt->bind_param('ssi', $correo, $rol, $id);
	$stmt->execute();
	$stmt->close();
	ep_auditar('usuario_editar', 'usuario', $id, 'Editó al usuario «'.ep_usuario_nombre($u).'»', $cambios);
	return ['ok' => true, 'cambio' => true, 'message' => 'Cambios guardados.'];
}

// Categorías de punto de venta y supervisores de un promotor (solo si ya existen las columnas); admin y supervisor no llevan ruta.
function ep_usuario_guardar_ruta(int $id, string $rol, string $categorias, int $supCanales, int $supRetail): array {
	$db = ep_db();
	require_once __DIR__.'/aprobacion_datos.php';
	if (!$db || $id <= 0 || !ep_usuarios_tiene_ruta($db)) {
		return ['ok' => true];
	}
	// El admin no lleva categoría; el supervisor sí (qué puntos de venta ve) pero no supervisores; el promotor lleva todo.
	if ($rol === 'admin') {
		$categorias = '';
	}
	if ($rol !== 'promotor') {
		$supCanales = $supRetail = 0;
	}
	if (!array_key_exists($categorias, EP_CATEGORIAS_PDV)) {
		return ['ok' => false, 'message' => 'La categoría de puntos de venta no es válida.'];
	}
	$nombresSup = [];
	foreach ([$supCanales, $supRetail] as $supId) {
		if ($supId <= 0) {
			continue;
		}
		$s = ep_usuario_obtener($supId);
		if (!$s || $s['rol'] !== 'supervisor') {
			return ['ok' => false, 'message' => 'El supervisor elegido no es válido.'];
		}
		$nombresSup[$supId] = ep_usuario_nombre($s);
	}
	$u = $db->query('SELECT nombre, usuario, categorias, supervisor_canales_id, supervisor_retail_id FROM repositorio_usuarios_reporte WHERE id = '.$id)->fetch_assoc();
	if (!$u) {
		return ['ok' => false, 'message' => 'El usuario no existe.'];
	}
	$nombreDe = function ($supId) use ($db, $nombresSup) {
		if (!$supId) {
			return 'Sin asignar';
		}
		return $nombresSup[(int) $supId] ?? ($db->query('SELECT nombre FROM repositorio_usuarios_reporte WHERE id = '.(int) $supId)->fetch_assoc()['nombre'] ?? '#'.$supId);
	};
	$cambios = array_values(array_filter([
		ep_auditoria_cambio('Categorías de puntos de venta', EP_CATEGORIAS_PDV[(string) $u['categorias']] ?? '', EP_CATEGORIAS_PDV[$categorias]),
		ep_auditoria_cambio('Supervisor de canales', $nombreDe($u['supervisor_canales_id']), $nombreDe($supCanales)),
		ep_auditoria_cambio('Supervisor de retail', $nombreDe($u['supervisor_retail_id']), $nombreDe($supRetail)),
	]));
	if (!$cambios) {
		return ['ok' => true, 'cambio' => false];
	}
	$cat = $categorias !== '' ? $categorias : null;
	$sc = $supCanales > 0 ? $supCanales : null;
	$sr = $supRetail > 0 ? $supRetail : null;
	$stmt = $db->prepare('UPDATE repositorio_usuarios_reporte SET categorias = ?, supervisor_canales_id = ?, supervisor_retail_id = ? WHERE id = ?');
	$stmt->bind_param('siii', $cat, $sc, $sr, $id);
	$stmt->execute();
	$stmt->close();
	ep_auditar('usuario_ruta', 'usuario', $id, 'Cambió las categorías o supervisores de «'.($u['nombre'] ?: $u['usuario']).'»', $cambios);
	return ['ok' => true, 'cambio' => true];
}

// Clave nueva en texto plano, como el resto del sistema; se cierra su sesión abierta y se libera el bloqueo.
function ep_usuario_cambiar_clave(int $id, string $clave, string $clave2): array {
	$u = ep_usuario_obtener($id);
	if (!$u) {
		return ['ok' => false, 'message' => 'El usuario no existe.'];
	}
	if ($clave !== $clave2) {
		return ['ok' => false, 'message' => 'Las claves no coinciden.'];
	}
	if (strlen($clave) < EP_CLAVE_MINIMA) {
		return ['ok' => false, 'message' => 'La clave debe tener al menos '.EP_CLAVE_MINIMA.' caracteres.'];
	}
	$db = ep_db();
	$propio = $id === (int) ($_SESSION['usuario_id'] ?? 0);
	$sql = $propio
		? 'UPDATE repositorio_usuarios_reporte SET contrasena = ?, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?'
		: 'UPDATE repositorio_usuarios_reporte SET contrasena = ?, sesion_token = NULL, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?';
	$stmt = $db->prepare($sql);
	$stmt->bind_param('si', $clave, $id);
	$stmt->execute();
	$stmt->close();
	ep_auditar('usuario_clave', 'usuario', $id, 'Cambió la clave de «'.ep_usuario_nombre($u).'»', [ep_auditoria_dato('Usuario', $u['usuario'])]);
	return ['ok' => true, 'message' => 'Clave cambiada.'];
}

function ep_usuario_cambiar_estado(int $id, bool $activo): array {
	$u = ep_usuario_obtener($id);
	if (!$u) {
		return ['ok' => false, 'message' => 'El usuario no existe.'];
	}
	if (!$activo && $id === (int) ($_SESSION['usuario_id'] ?? 0)) {
		return ['ok' => false, 'message' => 'No puedes desactivar tu propia cuenta.'];
	}
	$estado = $activo ? 'activo' : 'inactivo';
	$db = ep_db();
	$stmt = $db->prepare($activo ? 'UPDATE repositorio_usuarios_reporte SET status = ? WHERE id = ?' : 'UPDATE repositorio_usuarios_reporte SET status = ?, sesion_token = NULL WHERE id = ?');
	$stmt->bind_param('si', $estado, $id);
	$stmt->execute();
	$stmt->close();
	ep_auditar($activo ? 'usuario_activar' : 'usuario_desactivar', 'usuario', $id, ($activo ? 'Reactivó' : 'Desactivó').' al usuario «'.ep_usuario_nombre($u).'»', [ep_auditoria_dato('Usuario', $u['usuario'])]);
	return ['ok' => true, 'message' => $activo ? 'Usuario reactivado.' : 'Usuario desactivado.'];
}

// Un promotor debe existir activo en Xplora (de ahí sale su nombre); un admin se crea con el nombre que se escriba.
function ep_usuario_crear(string $usuario, string $nombre, string $correo, string $rol, string $clave, string $clave2): array {
	$usuario = trim($usuario);
	$nombre = trim($nombre);
	$correo = strtolower(trim($correo));
	if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $usuario)) {
		return ['ok' => false, 'message' => 'El usuario debe tener de 3 a 60 caracteres (letras, números, punto, guion).'];
	}
	if ($correo !== '' && (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150)) {
		return ['ok' => false, 'message' => 'Escribe un correo válido.'];
	}
	if (!isset(EP_ROLES_USUARIO[$rol])) {
		return ['ok' => false, 'message' => 'El rol no es válido.'];
	}
	if ($rol === 'supervisor' && !ep_usuarios_acepta_supervisor(ep_db())) {
		return ['ok' => false, 'message' => 'Falta ejecutar el SQL que agrega el rol Supervisor en la base de datos.'];
	}
	if ($clave !== $clave2) {
		return ['ok' => false, 'message' => 'Las claves no coinciden.'];
	}
	if (strlen($clave) < EP_CLAVE_MINIMA) {
		return ['ok' => false, 'message' => 'La clave debe tener al menos '.EP_CLAVE_MINIMA.' caracteres.'];
	}
	$db = ep_db();
	if (ep_login_perfil($db, $usuario)) {
		return ['ok' => false, 'message' => 'Ese usuario ya existe.'];
	}
	if ($rol === 'promotor') {
		$xplora = ep_login_xplora($db, $usuario);
		if (!$xplora) {
			return ['ok' => false, 'message' => 'Ese usuario no existe o está inactivo en Xplora.'];
		}
		$nombre = (string) $xplora['mercaderista'];
	} elseif ($nombre === '') {
		return ['ok' => false, 'message' => 'Escribe el nombre completo.'];
	}
	$stmt = $db->prepare("INSERT INTO repositorio_usuarios_reporte (usuario, contrasena, nombre, correo, rol, status) VALUES (?, ?, ?, ?, ?, 'activo')");
	$stmt->bind_param('sssss', $usuario, $clave, $nombre, $correo, $rol);
	$stmt->execute();
	$id = (int) $stmt->insert_id;
	$stmt->close();
	ep_auditar('usuario_crear', 'usuario', $id, 'Creó al usuario «'.$nombre.'»', [ep_auditoria_dato('Usuario', $usuario), ep_auditoria_dato('Rol', EP_ROLES_USUARIO[$rol]), ep_auditoria_dato('Correo', $correo)]);
	return ['ok' => true, 'message' => 'Usuario creado.', 'id' => $id];
}

// Guarda la ruta relativa de la foto ya subida; devuelve false si la columna todavía no existe.
function ep_usuario_guardar_foto(int $id, string $ruta): bool {
	$db = ep_db();
	if (!$db || !ep_usuarios_tiene_foto($db)) {
		return false;
	}
	$u = ep_usuario_obtener($id);
	$stmt = $db->prepare('UPDATE repositorio_usuarios_reporte SET foto = ? WHERE id = ?');
	$stmt->bind_param('si', $ruta, $id);
	$stmt->execute();
	$stmt->close();
	if ($u) {
		ep_auditar('usuario_foto', 'usuario', $id, 'Cambió la foto de «'.ep_usuario_nombre($u).'»', [ep_auditoria_dato('Usuario', $u['usuario'])]);
	}
	return true;
}

// Foto del usuario en sesión para el avatar del menú; vacía si no tiene o la columna no existe.
function ep_usuario_foto_actual(): string {
	static $url = null;
	if ($url !== null) {
		return $url;
	}
	$url = '';
	$db = ep_db();
	$id = (int) ($_SESSION['usuario_id'] ?? 0);
	if ($db && $id && ep_usuarios_tiene_foto($db)) {
		$stmt = $db->prepare('SELECT foto FROM repositorio_usuarios_reporte WHERE id = ? LIMIT 1');
		$stmt->bind_param('i', $id);
		$stmt->execute();
		$fila = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		$url = ep_usuario_foto_url($fila['foto'] ?? null);
	}
	return $url;
}
