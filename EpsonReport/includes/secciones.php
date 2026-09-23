<?php
// Secciones del rail lateral, todas visibles por ahora (sin roles, pendiente definir).
// Actividades y Gestión de Actividades son UN SOLO módulo — lo que cambia es qué ve
// cada rol adentro (ep_rol_actual() decide si aparece "+ Nueva actividad"), no la sección.
function ep_secciones(): array {
	$todas = [
		'actividades' => [
			'label' => 'Actividades',
			'icon'  => 'grid',
		],
		'historial' => [
			'label' => 'Registros de Actividades',
			'icon'  => 'clock',
		],
		'reportes' => [
			'label' => 'Reportes mensuales',
			'icon'  => 'presentation',
			'solo_admin' => true,
		],
	];
	// Las secciones marcadas solo_admin no existen para el rol usuario (tampoco se puede entrar por la URL).
	return array_filter($todas, fn($s) => empty($s['solo_admin']) || ep_rol_actual() === 'admin');
}
