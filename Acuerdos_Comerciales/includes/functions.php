<?php
// Punto de entrada: carga las funciones compartidas, separadas por módulo en includes/functions/.

// En este hosting (nginx) un fatal error de PHP se ve en el navegador como "404 Not Found", sin pista real — esto deja la causa real en logs/errores_fatales.log.
register_shutdown_function(function () {
	$error = error_get_last();
	if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
		$linea = '['.date('Y-m-d H:i:s').'] '.($_SERVER['REQUEST_URI'] ?? 'cli').' -> '.$error['message'].' en '.$error['file'].':'.$error['line'].PHP_EOL;
		@file_put_contents(__DIR__.'/../logs/errores_fatales.log', $linea, FILE_APPEND);
	}
});

require_once __DIR__.'/functions/sesion.php';
require_once __DIR__.'/functions/usuarios.php';
require_once __DIR__.'/functions/clientes.php';
require_once __DIR__.'/functions/catalogo.php';
require_once __DIR__.'/functions/asignacion.php';
require_once __DIR__.'/functions/cuotas.php';
require_once __DIR__.'/functions/historial.php';
require_once __DIR__.'/functions/repositorios.php';
require_once __DIR__.'/functions/acuerdo_completo.php';
require_once __DIR__.'/functions/seguimiento.php';
require_once __DIR__.'/functions/negociacion.php';
require_once __DIR__.'/functions/cumplimiento.php';
