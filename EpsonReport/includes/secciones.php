<?php
// Secciones del rail lateral, todas visibles por ahora (sin roles, pendiente definir).
// Actividades y Gestión de Actividades son UN SOLO módulo — lo que cambia es qué ve
// cada rol adentro (ep_rol_actual() decide si aparece "+ Nueva actividad"), no la sección.
function ep_secciones(): array {
	return [
		'actividades' => [
			'label' => 'Actividades',
			'icon'  => 'grid',
		],
		'historial' => [
			'label' => 'Registros de Actividades',
			'icon'  => 'clock',
		],
	];
}
