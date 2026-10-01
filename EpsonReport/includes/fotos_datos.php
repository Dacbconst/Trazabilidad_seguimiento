<?php
// Evidencia fotográfica requerida por plantilla — no por id, así una actividad nueva que "copia lógica" hereda las fotos solas.
function ep_fotos_requeridas(string $plantilla): array {
	if ($plantilla === 'activaciones') {
		return [
			['id' => 'stand', 'label' => 'Promotor junto a su stand con todos los materiales y POP correctamente ubicados'],
			['id' => 'interaccion-1', 'label' => 'Promotor ejecutando una atención o interacción con los clientes (1)'],
			['id' => 'venta-1', 'label' => 'Promotor con el cliente luego de ejecutar la venta (1)'],
			['id' => 'interaccion-2', 'label' => 'Promotor ejecutando una atención o interacción con los clientes (2)', 'opcional' => true],
			['id' => 'venta-2', 'label' => 'Promotor con el cliente luego de ejecutar la venta (2)', 'opcional' => true],
			['id' => 'venta-3', 'label' => 'Promotor con el cliente luego de ejecutar la venta (3)', 'opcional' => true],
		];
	}
	if ($plantilla === 'capacitaciones') {
		return [
			['id' => 'equipo', 'label' => 'Promotor junto al equipo de Epson sobre el cual va a capacitar'],
			['id' => 'capacitacion', 'label' => 'Promotor dando la capacitación'],
			['id' => 'entrega', 'label' => 'Promotor entregando breaks y/o premios'],
		];
	}
	if ($plantilla === 'epson-day') {
		return [
			['id' => 'materiales', 'label' => 'Materiales enviados'],
			['id' => 'redes-1', 'label' => 'Evidencia de publicación en las redes sociales (1)'],
			['id' => 'redes-2', 'label' => 'Evidencia de publicación en las redes sociales (2)'],
		];
	}
	if ($plantilla === 'evento-ferias') {
		return [
			['id' => 'stand', 'label' => 'Promotor junto a su stand con todos los materiales y POP correctamente ubicados'],
			['id' => 'interaccion-1', 'label' => 'Promotor ejecutando una atención o interacción con los clientes (1)'],
			['id' => 'interaccion-2', 'label' => 'Promotor ejecutando una atención o interacción con los clientes (2)'],
		];
	}
	if ($plantilla === 'exhibiciones') {
		return [
			['id' => 'exhibicion-1', 'label' => 'Exhibición a participar (1)'],
			['id' => 'exhibicion-2', 'label' => 'Exhibición a participar (2)'],
			['id' => 'exhibicion-3', 'label' => 'Exhibición a participar (3)'],
		];
	}
	if ($plantilla === 'colocacion-pop') {
		return [
			['id' => 'implementacion-1', 'label' => 'Correcta implementación del material POP (1)'],
			['id' => 'implementacion-2', 'label' => 'Correcta implementación del material POP (2)'],
			['id' => 'implementacion-3', 'label' => 'Correcta implementación del material POP (3)'],
		];
	}
	if ($plantilla === 'informe-fotografico') {
		return [
			['id' => 'foto-1', 'label' => 'Foto de lo encontrado en el punto de venta'],
			['id' => 'foto-2', 'label' => 'Foto adicional (opcional)', 'opcional' => true],
			['id' => 'foto-3', 'label' => 'Foto adicional (opcional)', 'opcional' => true],
			['id' => 'foto-4', 'label' => 'Foto adicional (opcional)', 'opcional' => true],
			['id' => 'foto-5', 'label' => 'Foto adicional (opcional)', 'opcional' => true],
			['id' => 'foto-6', 'label' => 'Foto adicional (opcional)', 'opcional' => true],
		];
	}
	if ($plantilla === 'competencia') {
		return [
			['id' => 'foto-1', 'label' => 'Hallazgo de la competencia'],
			['id' => 'foto-2', 'label' => 'Otro hallazgo (opcional)', 'opcional' => true],
			['id' => 'foto-3', 'label' => 'Otro hallazgo (opcional)', 'opcional' => true],
		];
	}
	return [];
}

// Toda actividad con lista de fotos deja sumar más, sin límite hasta nuevo aviso (botón "Agregar otra foto", en la página y en el asistente).
function ep_fotos_extensible(string $plantilla): bool {
	return ep_fotos_requeridas($plantilla) !== [];
}

// Actividades donde cada foto subida lleva su propia descripción obligatoria (va como pie de la foto en el PPT).
function ep_fotos_con_descripcion(string $plantilla): bool {
	return $plantilla === 'competencia';
}

// Etiqueta de las casillas que se suman con "+ Agregar foto".
function ep_foto_extra_label(string $plantilla): string {
	return $plantilla === 'competencia' ? 'Otro hallazgo' : 'Foto adicional';
}

// Máximo de caracteres de la descripción de una foto (lo que entra en el pie de la diapositiva).
const EP_FOTO_DESCRIPCION_MAX = 200;
