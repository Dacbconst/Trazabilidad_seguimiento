<?php
// Datos que el promotor escribe de su actividad (tipo, fecha y horario): limpieza y validación en el servidor.

// Mayúsculas, solo letras, números, espacios y guion; espacios sobrantes fuera.
function ep_actividad_normalizar_texto(string $texto, int $largo = 40): string {
	$texto = mb_strtoupper(trim($texto), 'UTF-8');
	$texto = preg_replace('/[^\p{L}\p{N} \-]/u', '', $texto);
	$texto = preg_replace('/\s+/u', ' ', $texto);
	return mb_substr(trim($texto), 0, $largo, 'UTF-8');
}

// Devuelve [datos, error]; error es null cuando todo es válido.
function ep_actividad_datos(array $valores): array {
	$tipo = ep_actividad_normalizar_texto((string) ($valores['tipo_actividad'] ?? ''));
	if ($tipo === '') {
		return [null, 'Escribe el tipo de actividad.'];
	}
	$fecha = (string) ($valores['fecha_actividad'] ?? '');
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes) || !checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
		return [null, 'La fecha de la actividad no es válida.'];
	}
	$inicio = (string) ($valores['hora_inicio'] ?? '');
	$fin = (string) ($valores['hora_fin'] ?? '');
	$formatoHora = '/^([01]\d|2[0-3]):[0-5]\d$/';
	if (!preg_match($formatoHora, $inicio) || !preg_match($formatoHora, $fin) || $fin <= $inicio) {
		return [null, 'El horario no es válido: la hora de fin debe ser posterior a la de inicio.'];
	}
	return [['tipo_actividad' => $tipo, 'fecha_actividad' => $fecha, 'hora_inicio' => $inicio, 'hora_fin' => $fin], null];
}
