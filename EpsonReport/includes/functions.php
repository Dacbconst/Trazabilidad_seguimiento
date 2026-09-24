<?php
// Íconos SVG inline compartidos por el rail y los componentes, sin librería externa.
function ep_icon(string $nombre, int $size = 18): string {
	$s = $size;
	$iconos = [
		'search'  => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
		'grid'    => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8M8 16h5"/>',
		'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
		'chevron' => '<path d="M6 9l6 6 6-6"/>',
		'plus'    => '<path d="M12 5v14M5 12h14"/>',
		'trash'   => '<path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-8 0l1 13a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-13"/>',
		'camera'  => '<rect x="3" y="6" width="18" height="14" rx="2"/><circle cx="12" cy="13" r="3.2"/><path d="M8 6l1.5-2h5L16 6"/>',
		'lock'    => '<rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
		'calendar'=> '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
		'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'filter'  => '<path d="M4 5h16M7 12h10M10 19h4"/>',
		'close'   => '<path d="M6 6l12 12M18 6L6 18"/>',
		'chevron-up' => '<path d="M18 15l-6-6-6 6"/>',
		'check'   => '<path d="M5 13l4 4L19 7"/>',
		'file'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
		'users'   => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/><circle cx="18" cy="7" r="3"/><path d="M22 21v-1a4 4 0 0 0-3-3.87"/>',
		'store'   => '<path d="M3 9l1-5h16l1 5"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/>',
		'arrow-left' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
		'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'printer' => '<path d="M6 9V4h12v5"/><rect x="5" y="9" width="14" height="8" rx="1"/><path d="M8 17v4h8v-4"/>',
		'bar-chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20v-3"/>',
		'eye'     => '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>',
		'eye-off' => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>',
		'chevron-left' => '<path d="M15 18l-6-6 6-6"/>',
		'chevron-right' => '<path d="M9 18l6-6 6-6"/>',
		'list'    => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
		'table'   => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>',
		'download'=> '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
		'presentation' => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="2" y1="20" x2="22" y2="20"/><line x1="12" y1="17" x2="12" y2="20"/><path d="M7 8l5 4 5-4"/>',
		'layers'  => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
		'megaphone' => '<path d="M3 11v2a1 1 0 0 0 1 1h3l7 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M18 9a4 4 0 0 1 0 6"/>',
		'graduation' => '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>',
		'tag'     => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
		'star'    => '<polygon points="12 2 15.1 8.6 22 9.3 17 14.1 18.2 21 12 17.7 5.8 21 7 14.1 2 9.3 8.9 8.6 12 2"/>',
		'shelves' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18"/><path d="M7 6.5h3M14 12.5h3M7 18.5h3"/>',
		'tent'    => '<path d="M2 21h20"/><path d="M4 21L12 4l8 17"/><path d="M9 21l3-6 3 6"/>',
	];
	$paths = $iconos[$nombre] ?? '';
	return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'.$paths.'</svg>';
}

// Nombre del icono que representa cada tipo de actividad (pestañas y filas del Historial).
function ep_icono_tipo(string $tipo): string {
	return ['activaciones' => 'megaphone', 'capacitaciones' => 'graduation', 'colocacion-pop' => 'tag', 'epson-day' => 'star', 'exhibiciones' => 'shelves', 'evento-ferias' => 'tent'][$tipo] ?? 'file';
}

// Rol actual desde la sesión real ('usuario' o 'admin').
function ep_rol_actual(): string {
	return $_SESSION['rol'] ?? 'usuario';
}

// Sesión única + inactividad, mismo esquema que Acuerdos_Comerciales:
// - "Latido": el ping de sesion-watch.js (cada 15s) refresca ultima_actividad en la base; un login nuevo solo pregunta si ese latido es de hace menos de 3 min.
// - Inactividad: 20 min sin interacción real (mouse/teclado/toque, guardada en la sesión de PHP) cierra la sesión y libera el token.
const EP_MINUTOS_INACTIVIDAD = 20;
const EP_SEGUNDOS_SESION_VIVA = 180;

// $interaccion=false para el ping automático (solo latido, no cuenta como actividad del usuario); el motivo del cierre queda en $GLOBALS['ep_motivo_cierre'].
function ep_login_check(bool $interaccion = true): bool {
	static $resultado = null;
	if ($resultado !== null) {
		return $resultado;
	}
	if (empty($_SESSION['usuario_id']) || empty($_SESSION['sesion_token'])) {
		return $resultado = false;
	}
	require_once __DIR__.'/db.php';
	$db = ep_db();
	if (!$db) {
		return $resultado = true; // base caída: no expulsar a nadie por un fallo de infraestructura
	}
	$stmt = $db->prepare('SELECT sesion_token, status FROM repositorio_usuarios_reporte WHERE id = ? LIMIT 1');
	$stmt->bind_param('i', $_SESSION['usuario_id']);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$fila || $fila['status'] !== 'activo' || !hash_equals((string) $fila['sesion_token'], (string) $_SESSION['sesion_token'])) {
		$GLOBALS['ep_motivo_cierre'] = 'otro_dispositivo';
		$_SESSION = [];
		return $resultado = false;
	}
	$ultimaInteraccion = (int) ($_SESSION['ult_interaccion'] ?? time());
	if (time() - $ultimaInteraccion > EP_MINUTOS_INACTIVIDAD * 60) {
		// Limpieza: se libera el token para que el próximo login no pregunte por una sesión que ya no existe.
		$up = $db->prepare('UPDATE repositorio_usuarios_reporte SET sesion_token = NULL WHERE id = ? AND sesion_token = ?');
		$up->bind_param('is', $_SESSION['usuario_id'], $_SESSION['sesion_token']);
		$up->execute();
		$up->close();
		$GLOBALS['ep_motivo_cierre'] = 'inactividad';
		$_SESSION = [];
		return $resultado = false;
	}
	if ($interaccion) {
		$_SESSION['ult_interaccion'] = time();
	} elseif (!isset($_SESSION['ult_interaccion'])) {
		$_SESSION['ult_interaccion'] = time();
	}
	// Latido en la base (máx. cada 10s por sesión).
	if (time() - (int) ($_SESSION['ult_actividad'] ?? 0) >= 10) {
		$up = $db->prepare('UPDATE repositorio_usuarios_reporte SET ultima_actividad = NOW() WHERE id = ?');
		$up->bind_param('i', $_SESSION['usuario_id']);
		$up->execute();
		$up->close();
		$_SESSION['ult_actividad'] = time();
	}
	return $resultado = true;
}

// Estado vacío reutilizable: icono, título y una línea de ayuda.
function ep_estado_vacio(string $icono, string $titulo, string $texto): string {
	return '<div class="ep-vacio"><span class="ep-vacio-icono">'.ep_icon($icono, 26).'</span><strong>'.htmlspecialchars($titulo).'</strong><p>'.htmlspecialchars($texto).'</p></div>';
}
