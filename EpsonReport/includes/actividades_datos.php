<?php
// Lógicas base (plantilla, campos y fotos que hereda cada botón) y actividades (botones) guardadas en insert_reporte_actividad.
require_once __DIR__.'/db.php';

// Las seis lógicas del sistema, por plantilla; su id es el de la actividad base que las renderiza.
function ep_logicas(): array {
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
	return array_column($base, null, 'plantilla');
}

// Botones de actividad guardados; cada uno hereda campos y fotos de su lógica.
function ep_actividades(): array {
	$db = ep_db();
	$res = $db ? $db->query('SELECT id, nombre, logica, badge, activo FROM insert_reporte_actividad WHERE eliminado_en IS NULL ORDER BY orden, id') : false;
	$logicas = ep_logicas();
	$actividades = [];
	foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $fila) {
		$logica = $logicas[$fila['logica']] ?? null;
		if (!$logica) {
			continue;
		}
		$actividades[] = [
			'id' => (int) $fila['id'],
			'label' => $fila['nombre'],
			'badge' => $fila['badge'],
			'plantilla' => $fila['logica'],
			'campos' => $logica['campos'],
			'sin_estadisticas' => $logica['sin_estadisticas'] ?? false,
			'render_id' => $logica['id'],
			'activo' => (bool) $fila['activo'],
		];
	}
	return $actividades;
}

// Solo las activas: lo que ven los promotores y el modal de reportes.
function ep_actividades_activas(): array {
	return array_values(array_filter(ep_actividades(), fn($a) => $a['activo']));
}

// El admin ve todas (con su interruptor); el promotor solo las activas.
function ep_actividades_visibles(): array {
	return ep_rol_actual() === 'admin' ? ep_actividades() : ep_actividades_activas();
}

// Crea un botón que copia la lógica de otro. Devuelve el mensaje de error, o null si se guardó.
function ep_actividad_crear(string $nombre, string $logica, int $creadoPor): ?string {
	$db = ep_db();
	if (!$db || !isset(ep_logicas()[$logica])) {
		return 'No se pudo crear la actividad.';
	}
	$stmt = $db->prepare('SELECT 1 FROM insert_reporte_actividad WHERE LOWER(nombre) = LOWER(?) AND eliminado_en IS NULL LIMIT 1');
	$stmt->bind_param('s', $nombre);
	$stmt->execute();
	$existe = $stmt->get_result()->num_rows > 0;
	$stmt->close();
	if ($existe) {
		return 'Ya existe una actividad con ese nombre.';
	}
	$stmt = $db->prepare("INSERT INTO insert_reporte_actividad (nombre, logica, badge, orden, creado_por) SELECT ?, ?, 'Nuevo', COALESCE(MAX(orden), 0) + 1, ? FROM insert_reporte_actividad");
	$stmt->bind_param('ssi', $nombre, $logica, $creadoPor);
	$ok = $stmt->execute();
	$stmt->close();
	return $ok ? null : 'No se pudo crear la actividad.';
}

function ep_actividad_activar(int $id, bool $activa): bool {
	$db = ep_db();
	$stmt = $db ? $db->prepare('UPDATE insert_reporte_actividad SET activo = ? WHERE id = ? AND eliminado_en IS NULL') : false;
	if (!$stmt) {
		return false;
	}
	$valor = $activa ? 1 : 0;
	$stmt->bind_param('ii', $valor, $id);
	$ok = $stmt->execute();
	$stmt->close();
	return $ok;
}
