<?php
// Gestión de registros de actividades reales en puntos de venta para Epson & Lucky Ecuador.
// Conecta los datos reales capturados en los formularios con persistencia dinámica.

function ep_registros_archivo_path(): string {
	return __DIR__.'/../data/registros_guardados.json';
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
	return [];
}

function ep_guardar_nuevo_registro(array $registro): bool {
	$registros = ep_registros_datos();
	// Insertar al inicio (el más reciente primero)
	array_unshift($registros, $registro);
	$path = ep_registros_archivo_path();
	$dir = dirname($path);
	if (!is_dir($dir)) {
		mkdir($dir, 0777, true);
	}
	return file_put_contents($path, json_encode($registros, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}
