<?php
// Evidencia fotográfica requerida por actividad — mock temporal, el resto de actividades todavía no tiene su lista definida.
function ep_fotos_requeridas(int $actividadId): array {
	if ($actividadId === 1) {
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
	return [];
}
