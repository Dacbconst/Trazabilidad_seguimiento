<?php
// Actividades registradas — mock temporal hasta que exista tabla real (mismo criterio que ep_rol_actual()).
function ep_actividades(): array {
	$base = [
		[
			'id' => 1,
			'label' => 'Activaciones',
			'badge' => null,
			'plantilla' => 'activaciones',
			'campos' => [
				['label' => 'Tiendas a Nivel Nacional', 'tipo' => 'manual'],
				['label' => 'Tiendas Coberturadas', 'tipo' => 'manual'],
				['label' => 'Visitaron', 'tipo' => 'manual'],
				['label' => 'Interactuaron', 'tipo' => 'manual'],
				['label' => 'Compraron', 'tipo' => 'manual'],
				['label' => 'Modelos activados', 'tipo' => 'manual'],
				['label' => 'Activaciones Programadas', 'tipo' => 'manual'],
				['label' => 'Activaciones Realizadas', 'tipo' => 'manual'],
				['label' => 'Comentarios', 'tipo' => 'manual'],
			],
		],
		[
			'id' => 2,
			'label' => 'Capacitaciones',
			'badge' => null,
			'plantilla' => 'capacitaciones',
			'campos' => [
				['label' => 'Asistente de Jefe Tienda', 'tipo' => 'manual'],
				['label' => 'Jefe de Tienda', 'tipo' => 'manual'],
				['label' => 'Vendedores', 'tipo' => 'manual'],
				['label' => 'Interacciones', 'tipo' => 'manual'],
				['label' => 'Comentarios', 'tipo' => 'manual'],
			],
		],
		[
			'id' => 3,
			'label' => 'Epson Day',
			'badge' => null,
			'plantilla' => 'epson-day',
			'campos' => [
				['label' => 'Tiendas a Nivel Nacional', 'tipo' => 'manual'],
				['label' => 'Tiendas Coberturadas', 'tipo' => 'manual'],
				['label' => 'Clientes que Visitaron', 'tipo' => 'manual'],
				['label' => 'Clientes que Interactuaron', 'tipo' => 'manual'],
				['label' => 'Clientes que Compraron', 'tipo' => 'manual'],
				['label' => 'Modelos', 'tipo' => 'manual'],
				['label' => 'Comentarios', 'tipo' => 'manual'],
			],
		],
		[
			'id' => 4,
			'label' => 'Evento o Ferias',
			'badge' => null,
			'plantilla' => 'evento-ferias',
			'campos' => [
				['label' => 'Clientes que Visitaron', 'tipo' => 'manual'],
				['label' => 'Clientes que Interactuaron', 'tipo' => 'manual'],
				['label' => 'Clientes que Compraron', 'tipo' => 'manual'],
				['label' => 'Modelos', 'tipo' => 'manual'],
				['label' => 'Comentarios', 'tipo' => 'manual'],
			],
		],
		[
			'id' => 5,
			'label' => 'Exhibiciones que Inspiran',
			'badge' => null,
			'plantilla' => 'exhibiciones',
			'campos' => [
				['label' => 'Muebles', 'tipo' => 'manual'],
				['label' => 'Rumas', 'tipo' => 'manual'],
				['label' => 'Cabeceras', 'tipo' => 'manual'],
				['label' => 'Comentarios', 'tipo' => 'manual'],
			],
		],
		[
			'id' => 6,
			'label' => 'Colocación de POP',
			'badge' => null,
			'plantilla' => 'colocacion-pop',
			'sin_estadisticas' => true,
			'campos' => [
				['label' => 'POP Recibido (Bodega / Canales / Retail por material)', 'tipo' => 'manual'],
				['label' => 'Detalle de Entrega a Puntos de Venta', 'tipo' => 'manual'],
				['label' => 'Comentarios', 'tipo' => 'manual'],
			],
		],
	];
	// "Nueva actividad" (constructor, solo admin) las agrega acá — mismo criterio mock hasta que exista tabla real.
	$todas = array_merge($base, $_SESSION['ep_actividades_extra'] ?? []);
	$desactivadas = $_SESSION['ep_actividades_desactivadas'] ?? [];
	foreach ($todas as &$act) {
		if (!isset($act['activo'])) {
			$act['activo'] = !in_array($act['id'], $desactivadas, false);
		} elseif (in_array($act['id'], $desactivadas, false)) {
			$act['activo'] = false;
		}
	}
	unset($act);
	return $todas;
}

// Devuelve únicamente las actividades marcadas como activas en el sistema.
function ep_actividades_activas(): array {
	return array_values(array_filter(ep_actividades(), fn($a) => !empty($a['activo'])));
}
