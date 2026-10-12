<?php
// Perfil del usuario en sesión: sus datos, su correo y su clave (cada quien solo toca lo suyo).
require_once __DIR__.'/usuarios_datos.php';

// Datos del perfil; ciudad y canal salen de la caché del rutero (vacíos si aún no existe).
function ep_perfil_obtener(): ?array {
	$u = ep_usuario_obtener((int) ($_SESSION['usuario_id'] ?? 0));
	$db = ep_db();
	if (!$u || !$db) {
		return null;
	}
	$rutero = ep_usuarios_rutero($db, true)[$u['usuario']] ?? [];
	return [
		'nombre' => ep_usuario_nombre($u),
		'usuario' => $u['usuario'],
		'correo' => (string) $u['correo'],
		'rol' => $u['rol'],
		'rol_label' => EP_ROLES_USUARIO[$u['rol']] ?? $u['rol'],
		'foto' => ep_usuario_foto_actual(),
		'ciudad' => (string) ($rutero['ciudad'] ?? ''),
		'canal' => $u['rol'] === 'promotor' ? (string) ($rutero['canal'] ?? '') : '',
	];
}

// Guarda el correo propio (vacío lo quita). Devuelve ['ok' => bool, 'message' => texto].
function ep_perfil_guardar_correo(string $correo): array {
	$id = (int) ($_SESSION['usuario_id'] ?? 0);
	$u = ep_usuario_obtener($id);
	$db = ep_db();
	if (!$u || !$db) {
		return ['ok' => false, 'message' => 'No se pudo leer tu usuario.'];
	}
	$correo = strtolower(trim($correo));
	if ($correo !== '' && (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150)) {
		return ['ok' => false, 'message' => 'Escribe un correo válido.'];
	}
	$cambio = ep_auditoria_cambio('Correo', $u['correo'], $correo);
	if (!$cambio) {
		return ['ok' => true, 'correo' => $correo, 'message' => 'Tu correo ya estaba así.'];
	}
	$stmt = $db->prepare('UPDATE repositorio_usuarios_reporte SET correo = ? WHERE id = ?');
	$stmt->bind_param('si', $correo, $id);
	$stmt->execute();
	$stmt->close();
	ep_auditar('usuario_editar', 'usuario', $id, 'Cambió su propio correo', [$cambio]);
	return ['ok' => true, 'correo' => $correo, 'message' => 'Correo guardado.'];
}

// Cambia la clave propia: pide la actual y reutiliza las reglas del cambio de clave de usuarios.
function ep_perfil_cambiar_clave(string $actual, string $clave, string $clave2): array {
	$id = (int) ($_SESSION['usuario_id'] ?? 0);
	$db = ep_db();
	$stmt = $db ? $db->prepare('SELECT contrasena FROM repositorio_usuarios_reporte WHERE id = ? LIMIT 1') : false;
	if (!$stmt) {
		return ['ok' => false, 'message' => 'No se pudo leer tu usuario.'];
	}
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$fila || !hash_equals((string) $fila['contrasena'], $actual)) {
		return ['ok' => false, 'message' => 'Tu clave actual no es correcta.'];
	}
	if ($clave === $actual) {
		return ['ok' => false, 'message' => 'La clave nueva debe ser distinta de la actual.'];
	}
	return ep_usuario_cambiar_clave($id, $clave, $clave2);
}
