<?php
// Evidencia fotográfica requerida por plantilla — no por id, así una actividad nueva que "copia lógica" hereda las fotos solas.
function ep_fotos_requeridas(string $plantilla): array {
	if ($plantilla === 'activaciones') {
		return [
			['id' => 'calendario', 'label' => 'Calendario de Activación'],
			['id' => 'stand', 'label' => 'Promotor en su stand con todos los materiales y POP correctamente ubicados'],
			['id' => 'interaccion-1', 'label' => 'Promotor en una interacción con el cliente (1)'],
			['id' => 'interaccion-2', 'label' => 'Promotor en una interacción con el cliente (2)'],
			['id' => 'venta-1', 'label' => 'Promotor con el cliente luego de ejecutar la venta (1)'],
			['id' => 'venta-2', 'label' => 'Promotor con el cliente luego de ejecutar la venta (2)'],
			['id' => 'venta-3', 'label' => 'Promotor con el cliente luego de ejecutar la venta (3)'],
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
			['id' => 'redes-1', 'label' => 'Evidencia de publicación en redes sociales (1)'],
			['id' => 'redes-2', 'label' => 'Evidencia de publicación en redes sociales (2)'],
		];
	}
	if ($plantilla === 'evento-ferias') {
		return [
			['id' => 'stand', 'label' => 'Promotor en su stand con todos los materiales y POP correctamente ubicados'],
			['id' => 'interaccion-1', 'label' => 'Promotor en una interacción con el cliente (1)'],
			['id' => 'interaccion-2', 'label' => 'Promotor en una interacción con el cliente (2)'],
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
	return [];
}
