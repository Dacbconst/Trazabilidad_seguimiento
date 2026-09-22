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
		'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'printer' => '<path d="M6 9V4h12v5"/><rect x="5" y="9" width="14" height="8" rx="1"/><path d="M8 17v4h8v-4"/>',
		'bar-chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20v-3"/>',
		'eye'     => '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>',
		'eye-off' => '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a19.7 19.7 0 0 1 4.19-5.16M9.9 4.24A10.6 10.6 0 0 1 12 4c7 0 11 8 11 8a19.7 19.7 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/>',
	];
	$paths = $iconos[$nombre] ?? '';
	return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'.$paths.'</svg>';
}

// Rol actual, mock temporal hasta que exista la tabla de usuarios real ('usuario' o 'admin').
function ep_rol_actual(): string {
	return $_SESSION['rol'] ?? 'usuario';
}
