<?php
// Secciones del rail lateral, todas visibles por ahora (sin roles, pendiente definir).
// Actividades y Gestión de Actividades son UN SOLO módulo — lo que cambia es qué ve
// cada rol adentro (ep_rol_actual() decide si aparece "+ Nueva actividad"), no la sección.
require_once __DIR__.'/pop_reparto.php';

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
		'aprobaciones' => [
			'label' => 'Aprobaciones',
			'icon'  => 'check',
			'solo_gestor' => true,
		],
		'reportes' => [
			'label' => 'Reportes mensuales',
			'icon'  => 'presentation',
			'solo_gestor' => true,
		],
		'calendario' => [
			'label' => 'Calendario de Activaciones',
			'icon'  => 'calendar',
			'solo_gestor' => true,
		],
		'pop' => [
			'label' => 'Colocación de POP',
			'icon'  => 'tag',
			'solo_gestor' => true,
		],
		'repositorios' => [
			'label' => 'Repositorios',
			'icon'  => 'list',
			'solo_dueno_pop' => true,
		],
		'usuarios' => [
			'label' => 'Usuarios',
			'icon'  => 'users',
			'solo_admin' => true,
		],
		'auditoria' => [
			'label' => 'Auditoría',
			'icon'  => 'shield',
			'solo_admin' => true,
		],
	];
	// Las secciones marcadas solo_admin no existen para el rol usuario (tampoco se puede entrar por la URL).
	return array_filter($todas, fn($s) => (empty($s['solo_admin']) || ep_es_admin()) && (empty($s['solo_gestor']) || ep_es_gestor()) && (empty($s['solo_dueno_pop']) || ep_pop_es_dueno()));
}
