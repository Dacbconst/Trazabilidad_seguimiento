<?php
// Gestión de registros de actividades reales en puntos de venta para Epson & Lucky Ecuador.
// Conecta los datos reales capturados en los formularios con persistencia dinámica.

function ep_registros_archivo_path(): string {
	return __DIR__.'/../data/registros_guardados.json';
}

function ep_registros_datos_semilla(): array {
	return [
		[
			'id'              => 'REG-2024-1024-01',
			'tipo'            => 'activaciones',
			'actividad_label' => 'Activaciones',
			'actividad_badge' => 'Back to School',
			'fecha_iso'       => '2024-10-24',
			'fecha_texto'     => 'Jueves, 24 de Octubre 2024',
			'hora'            => '15:30',
			'duracion'        => '2h 15m',
			'grupo_dia'       => 'Hoy — Jueves, 24 de Octubre',
			'estado'          => 'Aprobado',
			'estado_tipo'     => 'ok',
			'punto_venta'     => 'SUKASA - MALL DEL SOL',
			'cadena'          => 'Sukasa',
			'ciudad'          => 'GUAYAQUIL',
			'canal'           => 'RETAIL',
			'promotor'        => 'Carlos Proaño',
			'promotor_usuario'=> 'carlos.proano',
			'promotor_avatar' => 'CP',
			'cobertura'       => [
				'nacional'     => 10,
				'coberturadas' => 8,
				'pct'          => 80.0,
			],
			'embudo'          => [
				'visitaron'             => 120,
				'interactuaron'         => 48,
				'compraron'             => 20,
				'tasa_interaccion_pct'  => 40.0,
				'tasa_conversion_pct'   => 41.7,
				'conversion_global_pct' => 16.7,
			],
			'modelos'         => [
				['modelo' => 'EcoTank L3250', 'cantidad' => 10, 'pct' => '50.0%'],
				['modelo' => 'EcoTank L4260', 'cantidad' => 6,  'pct' => '30.0%'],
				['modelo' => 'EcoTank L5590', 'cantidad' => 4,  'pct' => '20.0%'],
			],
			'cumplimiento'    => [
				'programadas' => 10,
				'realizadas'  => 8,
				'pct'         => 80.0,
			],
			'fotos'           => [
				['id' => 'calendario', 'label' => 'Calendario de Activación', 'hora' => '15:32', 'estado' => 'Verificada'],
				['id' => 'stand', 'label' => 'Promotor en su stand con todos los materiales y POP correctamente ubicados', 'hora' => '15:35', 'estado' => 'Verificada'],
				['id' => 'interaccion-1', 'label' => 'Promotor en una interacción con el cliente (1)', 'hora' => '16:10', 'estado' => 'Verificada'],
				['id' => 'interaccion-2', 'label' => 'Promotor en una interacción con el cliente (2)', 'hora' => '16:45', 'estado' => 'Verificada'],
				['id' => 'venta-1', 'label' => 'Promotor con el cliente luego de ejecutar la venta (1)', 'hora' => '17:05', 'estado' => 'Verificada'],
				['id' => 'venta-2', 'label' => 'Promotor con el cliente luego de ejecutar la venta (2)', 'hora' => '17:25', 'estado' => 'Verificada'],
				['id' => 'venta-3', 'label' => 'Promotor con el cliente luego de ejecutar la venta (3)', 'hora' => '17:40', 'estado' => 'Verificada'],
			],
			'comentarios'     => [
				'Alta afluencia de clientes interesados en la línea EcoTank para inicio de clases universitarias.',
				'Excelente acogida del combo de botellas de tinta T544 originales de regalo en tienda.'
			],
		],
		[
			'id'              => 'REG-2024-1024-02',
			'tipo'            => 'capacitaciones',
			'actividad_label' => 'Capacitaciones',
			'actividad_badge' => 'Fuerza de Ventas',
			'fecha_iso'       => '2024-10-24',
			'fecha_texto'     => 'Jueves, 24 de Octubre 2024',
			'hora'            => '10:00',
			'duracion'        => '1h 45m',
			'grupo_dia'       => 'Hoy — Jueves, 24 de Octubre',
			'estado'          => 'Aprobado',
			'estado_tipo'     => 'ok',
			'punto_venta'     => 'JUAN MARCET - RIOCENTRO NORTE',
			'cadena'          => 'Juan Marcet',
			'ciudad'          => 'GUAYAQUIL',
			'canal'           => 'RETAIL',
			'promotor'        => 'María Elena Gómez',
			'promotor_usuario'=> 'maria.gomez',
			'promotor_avatar' => 'MG',
			'capacitacion'    => [
				'asistentes'     => 18,
				'aprobados'      => 16,
				'pct_aprobacion' => 88.9,
				'horas'          => 2,
				'temas'          => 'Beneficios de cabezal PrecisionCore vs térmico, rendimiento de botellas de tinta original EcoTank y cálculo de costo por página en el punto de venta.',
			],
			'fotos'           => [
				['id' => 'equipo', 'label' => 'Promotor junto al equipo de Epson sobre el cual va a capacitar', 'hora' => '10:05', 'estado' => 'Verificada'],
				['id' => 'capacitacion', 'label' => 'Promotor dando la capacitación', 'hora' => '10:45', 'estado' => 'Verificada'],
				['id' => 'entrega', 'label' => 'Promotor entregando breaks y/o premios', 'hora' => '11:30', 'estado' => 'Verificada'],
			],
			'comentarios'     => [
				'El equipo comercial de piso mostró gran interés en el argumento de 2 años de garantía con registro online.',
				'Se entregaron 16 certificados de aprobación tras la prueba práctica de llenado de tanques de tinta.'
			],
		],
		[
			'id'              => 'REG-2024-1023-01',
			'tipo'            => 'colocacion-pop',
			'actividad_label' => 'Colocación de POP',
			'actividad_badge' => 'Visibilidad Retail',
			'fecha_iso'       => '2024-10-23',
			'fecha_texto'     => 'Miércoles, 23 de Octubre 2024',
			'hora'            => '11:00',
			'duracion'        => '1h 30m',
			'grupo_dia'       => 'Ayer — Miércoles, 23 de Octubre',
			'estado'          => 'Aprobado',
			'estado_tipo'     => 'ok',
			'punto_venta'     => 'SUKASA - JARDIN',
			'cadena'          => 'Sukasa',
			'ciudad'          => 'QUITO',
			'canal'           => 'RETAIL',
			'promotor'        => 'David Andrade',
			'promotor_usuario'=> 'david.andrade',
			'promotor_avatar' => 'DA',
			'pop_materiales'  => [
				['material' => 'Vibrines (Retail)', 'bodega' => 25, 'canales' => 5, 'retail' => 15, 'disponible' => 5],
				['material' => 'Banners Roll Up (1.80m)', 'bodega' => 6, 'canales' => 1, 'retail' => 4, 'disponible' => 1],
				['material' => 'Glorificadores Acrílico L3250', 'bodega' => 10, 'canales' => 2, 'retail' => 6, 'disponible' => 2],
				['material' => 'Stickers de Garantía 2 Años', 'bodega' => 80, 'canales' => 15, 'retail' => 50, 'disponible' => 15],
				['material' => 'Cenefas de Lineal', 'bodega' => 30, 'canales' => 0, 'retail' => 24, 'disponible' => 6],
			],
			'fotos'           => [
				['id' => 'implementacion-1', 'label' => 'Correcta implementación del material POP (1)', 'hora' => '11:15', 'estado' => 'Verificada'],
				['id' => 'implementacion-2', 'label' => 'Correcta implementación del material POP (2)', 'hora' => '11:50', 'estado' => 'Verificada'],
				['id' => 'implementacion-3', 'label' => 'Correcta implementación del material POP (3)', 'hora' => '12:15', 'estado' => 'Verificada'],
			],
			'comentarios'     => [
				'Se renovó el banner central y se colocaron vibrines en las islas de tecnología.',
				'El punto de venta quedó con 100% de visibilidad homologada según lineamientos Epson.'
			],
		],
		[
			'id'              => 'REG-2024-1023-02',
			'tipo'            => 'epson-day',
			'actividad_label' => 'Epson Day',
			'actividad_badge' => 'Jornada Especial',
			'fecha_iso'       => '2024-10-23',
			'fecha_texto'     => 'Miércoles, 23 de Octubre 2024',
			'hora'            => '10:30',
			'duracion'        => '6h 00m',
			'grupo_dia'       => 'Ayer — Miércoles, 23 de Octubre',
			'estado'          => 'Aprobado',
			'estado_tipo'     => 'ok',
			'punto_venta'     => 'SUPER PACO - MALL DEL SOL',
			'cadena'          => 'Super Paco',
			'ciudad'          => 'GUAYAQUIL',
			'canal'           => 'RETAIL',
			'promotor'        => 'Andrea Morales',
			'promotor_usuario'=> 'andrea.morales',
			'promotor_avatar' => 'AM',
			'cobertura'       => [
				'nacional'     => 4,
				'coberturadas' => 4,
				'pct'          => 100.0,
			],
			'embudo'          => [
				'visitaron'             => 210,
				'interactuaron'         => 85,
				'compraron'             => 28,
				'tasa_interaccion_pct'  => 40.5,
				'tasa_conversion_pct'   => 32.9,
				'conversion_global_pct' => 13.3,
			],
			'modelos'         => [
				['modelo' => 'EcoTank L3250', 'cantidad' => 15, 'pct' => '53.6%'],
				['modelo' => 'EcoTank L4260', 'cantidad' => 8,  'pct' => '28.6%'],
				['modelo' => 'EcoTank L8180 (Fotográfica)', 'cantidad' => 5,  'pct' => '17.8%'],
			],
			'fotos'           => [
				['id' => 'materiales', 'label' => 'Materiales enviados', 'hora' => '10:35', 'estado' => 'Verificada'],
				['id' => 'redes-1', 'label' => 'Evidencia de publicación en redes sociales (1)', 'hora' => '12:30', 'estado' => 'Verificada'],
				['id' => 'redes-2', 'label' => 'Evidencia de publicación en redes sociales (2)', 'hora' => '15:40', 'estado' => 'Verificada'],
			],
			'comentarios'     => [
				'Jornada de gran afluencia. Se agotó el stock disponible de EcoTank L3250 en tienda a las 15:00.',
				'Excelente impacto de la demostración de fotos de alta resolución con el modelo fotográfico L8180.'
			],
		],
		[
			'id'              => 'REG-2024-1021-01',
			'tipo'            => 'exhibiciones',
			'actividad_label' => 'Exhibiciones que Inspiran',
			'actividad_badge' => 'Espacios Premium',
			'fecha_iso'       => '2024-10-21',
			'fecha_texto'     => 'Lunes, 21 de Octubre 2024',
			'hora'            => '14:15',
			'duracion'        => '2h 00m',
			'grupo_dia'       => 'Lunes, 21 de Octubre',
			'estado'          => 'Aprobado',
			'estado_tipo'     => 'ok',
			'punto_venta'     => 'POINT - QUICENTRO SUR',
			'cadena'          => 'Point',
			'ciudad'          => 'QUITO',
			'canal'           => 'RETAIL',
			'promotor'        => 'Esteban Salazar',
			'promotor_usuario'=> 'esteban.salazar',
			'promotor_avatar' => 'ES',
			'exhibiciones'    => [
				'muebles'       => 2,
				'rumas'         => 4,
				'cabeceras'     => 5,
				'total'         => 11,
				'muebles_pct'   => '18.2%',
				'rumas_pct'     => '36.4%',
				'cabeceras_pct' => '45.5%',
			],
			'fotos'           => [
				['id' => 'exhibicion-1', 'label' => 'Exhibición a participar (1)', 'hora' => '14:25', 'estado' => 'Verificada'],
				['id' => 'exhibicion-2', 'label' => 'Exhibición a participar (2)', 'hora' => '14:50', 'estado' => 'Verificada'],
				['id' => 'exhibicion-3', 'label' => 'Exhibición a participar (3)', 'hora' => '15:30', 'estado' => 'Verificada'],
			],
			'comentarios'     => [
				'Se obtuvo la cabecera principal de tecnología por todo el mes mediante acuerdo con gerencia de tienda.',
				'Las 4 rumas se armaron frente al pasillo de cajas con alta visibilidad.'
			],
		],
		[
			'id'              => 'REG-2024-1021-02',
			'tipo'            => 'evento-ferias',
			'actividad_label' => 'Evento o Ferias',
			'actividad_badge' => 'Feria Tecnológica',
			'fecha_iso'       => '2024-10-21',
			'fecha_texto'     => 'Lunes, 21 de Octubre 2024',
			'hora'            => '09:00',
			'duracion'        => '5h 00m',
			'grupo_dia'       => 'Lunes, 21 de Octubre',
			'estado'          => 'Aprobado',
			'estado_tipo'     => 'ok',
			'punto_venta'     => 'COMPUTRON - CENTRO 1',
			'cadena'          => 'Computron',
			'ciudad'          => 'GUAYAQUIL',
			'canal'           => 'RETAIL',
			'promotor'        => 'Carlos Proaño',
			'promotor_usuario'=> 'carlos.proano',
			'promotor_avatar' => 'CP',
			'feria'           => [
				'visitaron'             => 380,
				'interactuaron'         => 145,
				'compraron'             => 42,
				'tasa_interaccion_pct'  => 38.2,
				'tasa_conversion_pct'   => 29.0,
				'conversion_global_pct' => 11.1,
			],
			'modelos'         => [
				['modelo' => 'EcoTank L3250', 'cantidad' => 22, 'pct' => '52.4%'],
				['modelo' => 'SureColor F170 (Sublimación)', 'cantidad' => 12, 'pct' => '28.6%'],
				['modelo' => 'EcoTank L8180', 'cantidad' => 8,  'pct' => '19.0%'],
			],
			'fotos'           => [
				['id' => 'stand', 'label' => 'Promotor en su stand con todos los materiales y POP correctamente ubicados', 'hora' => '09:15', 'estado' => 'Verificada'],
				['id' => 'interaccion-1', 'label' => 'Promotor en una interacción con el cliente (1)', 'hora' => '11:40', 'estado' => 'Verificada'],
				['id' => 'interaccion-2', 'label' => 'Promotor en una interacción con el cliente (2)', 'hora' => '13:10', 'estado' => 'Verificada'],
			],
			'comentarios'     => [
				'Stand con alto tráfico corporativo y de emprendedores textiles interesados en la SureColor F170.',
				'Se generaron 18 cotizaciones formales para cierre en canal mayorista.'
			],
		],
	];
}

function ep_registros_datos(): array {
	$path = ep_registros_archivo_path();
	if (file_exists($path)) {
		$json = file_get_contents($path);
		$data = json_decode($json, true);
		if (is_array($data) && !empty($data)) {
			return $data;
		}
	}
	// Si no existe o está vacío, inicializar con la semilla
	$semilla = ep_registros_datos_semilla();
	$dir = dirname($path);
	if (!is_dir($dir)) {
		mkdir($dir, 0777, true);
	}
	file_put_contents($path, json_encode($semilla, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
	return $semilla;
}

function ep_guardar_nuevo_registro(array $registro): bool {
	$registros = ep_registros_datos();
	array_unshift($registros, $registro);
	$path = ep_registros_archivo_path();
	$dir = dirname($path);
	if (!is_dir($dir)) {
		mkdir($dir, 0777, true);
	}
	return file_put_contents($path, json_encode($registros, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}
