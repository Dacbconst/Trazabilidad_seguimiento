<?php
// Datos de acceso: perfil propio (contraseña, sesión, bloqueos) y comprobación en Xplora, que solo se lee.
require_once __DIR__.'/db.php';

const EP_MAX_INTENTOS = 5;
const EP_MINUTOS_BLOQUEO = 15;
const EP_CLAVE_MINIMA = 6;

// Responde el motivo del fallo; el frontend decide el mensaje o la ventana.
function ep_login_fallo(string $motivo): void {
	echo json_encode(['ok' => false, 'motivo' => $motivo]);
	exit;
}

// Historial de cada intento para seguimiento; si falla el log no bloquea el ingreso.
function ep_login_log($db, $usuarioId, string $intentado, int $exito, ?string $motivo): void {
	$ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
	$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
	$stmt = $db->prepare('INSERT INTO insert_reporte_login (usuario_id, usuario_intentado, exito, motivo, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
	if ($stmt) {
		$stmt->bind_param('isisss', $usuarioId, $intentado, $exito, $motivo, $ip, $ua);
		$stmt->execute();
		$stmt->close();
	}
}

// Perfil propio: contraseña, rol y estado de sesión. El admin y cada promotor registrado viven aquí.
function ep_login_perfil($db, string $usuario): ?array {
	$stmt = $db->prepare('SELECT id, usuario, contrasena, nombre, rol, status, intentos_fallidos, bloqueado_hasta, sesion_token, TIMESTAMPDIFF(SECOND, ultima_actividad, NOW()) AS inactivo FROM repositorio_usuarios_reporte WHERE usuario = ? LIMIT 1');
	$stmt->bind_param('s', $usuario);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $fila ?: null;
}

// Mercaderista activo en Xplora (solo lectura, esa tabla no se toca).
function ep_login_xplora($db, string $usuario): ?array {
	$stmt = $db->prepare('SELECT user, mercaderista, cedula FROM repositorio_usuarios WHERE user = ? AND status = 1 LIMIT 1');
	$stmt->bind_param('s', $usuario);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $fila ?: null;
}

// Perfil vacío de un mercaderista de Xplora; queda sin clave hasta que se registre.
function ep_login_crear_perfil($db, array $xplora): ?array {
	$stmt = $db->prepare("INSERT INTO repositorio_usuarios_reporte (usuario, contrasena, nombre, rol, status) VALUES (?, '', ?, 'promotor', 'activo')");
	$stmt->bind_param('ss', $xplora['user'], $xplora['mercaderista']);
	$stmt->execute();
	$stmt->close();
	return ep_login_perfil($db, $xplora['user']);
}

function ep_login_bloqueado(array $perfil): bool {
	return $perfil['bloqueado_hasta'] && strtotime($perfil['bloqueado_hasta']) > time();
}

// Suma un intento fallido; al llegar al máximo bloquea la cuenta unos minutos.
function ep_login_sumar_fallo($db, array $perfil): void {
	$intentos = (int) $perfil['intentos_fallidos'] + 1;
	if ($intentos >= EP_MAX_INTENTOS) {
		$up = $db->prepare('UPDATE repositorio_usuarios_reporte SET intentos_fallidos = 0, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL '.EP_MINUTOS_BLOQUEO.' MINUTE) WHERE id = ?');
	} else {
		$up = $db->prepare('UPDATE repositorio_usuarios_reporte SET intentos_fallidos = '.$intentos.' WHERE id = ?');
	}
	$up->bind_param('i', $perfil['id']);
	$up->execute();
	$up->close();
}

// Correo del perfil; vacío si no tiene o si la columna todavía no existe en la base.
function ep_correo_usuario(int $usuarioId): string {
	$db = ep_db();
	$stmt = $db ? @$db->prepare('SELECT correo FROM repositorio_usuarios_reporte WHERE id = ? LIMIT 1') : false;
	if (!$stmt) {
		return '';
	}
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return trim((string) ($fila['correo'] ?? ''));
}
