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
	];
	$paths = $iconos[$nombre] ?? '';
	return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'.$paths.'</svg>';
}

// Rol actual, mock temporal hasta que exista la tabla de usuarios real ('usuario' o 'admin').
function ep_rol_actual(): string {
	return $_SESSION['rol'] ?? 'usuario';
}
