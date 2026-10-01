<?php
// Aprobaciones: el mismo listado del Historial, con lo pendiente y lo devuelto y los botones de aprobar y devolver.
require_once __DIR__.'/../../includes/functions.php';

if (!ep_es_gestor()) {
	echo '<main class="ep-content"><p>No tienes permiso para ver esta sección.</p></main>';
	return;
}
$modoAprobacion = true;
include __DIR__.'/../historial/historial.php';
