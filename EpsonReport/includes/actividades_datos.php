<?php
// Actividades registradas — mock temporal hasta que exista tabla real (mismo criterio que ep_rol_actual()).
function ep_actividades(): array {
	return [
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
		// Sin formulario propio todavía, ver plantillas/pendiente.php.
		[
			'id' => 5,
			'label' => 'Exhibiciones que Inspiran',
			'badge' => null,
			'plantilla' => 'pendiente',
			'campos' => [['label' => 'Pendiente de definir', 'tipo' => 'manual']],
		],
		[
			'id' => 6,
			'label' => 'Colocación de POP',
			'badge' => null,
			'plantilla' => 'pendiente',
			'campos' => [['label' => 'Pendiente de definir', 'tipo' => 'manual']],
		],
	];
}
