<?php
// Aritmética de dinero sin errores de punto flotante — nunca sumar montos con
// + / array_sum nativo de PHP. Usa BCMath (aritmética decimal exacta) para
// toda suma de montos en este proyecto.

function dinero_disponible_bcmath() {
	return function_exists('bcadd');
}

// Suma un array de valores numéricos con precisión decimal exacta. Sin BCMath
// disponible, cae a array_sum nativo en vez de romper la importación.
function dinero_sumar(array $valores, $escala = 2) {
	if (!dinero_disponible_bcmath()) {
		return round(array_sum(array_map('floatval', $valores)), $escala);
	}
	$acumulado = '0';
	// Escala intermedia más alta que la final para no acumular error en sumas encadenadas.
	$escalaIntermedia = max($escala + 4, 6);
	foreach ($valores as $v) {
		$acumulado = bcadd($acumulado, dinero_a_string($v, $escalaIntermedia), $escalaIntermedia);
	}
	return (float) bcadd($acumulado, '0', $escala);
}

// bcadd/bcmul exigen strings numéricos normales; un float en notación
// científica ("1.0E-5") rompe BCMath en silencio. number_format() lo evita.
function dinero_a_string($valor, $decimales = 6) {
	return number_format((float) $valor, $decimales, '.', '');
}
?>
