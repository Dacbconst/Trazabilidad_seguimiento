<?php
// Aritmética de dinero sin errores de punto flotante — nunca sumar montos
// con + / array_sum nativo de PHP (representación binaria imprecisa, ej.
// 0.1+0.2 = 0.30000000000000004). Usa BCMath (aritmética decimal exacta,
// como cualquier sistema contable/bancario real) para toda suma de montos
// en Liquidación, y en cualquier otro lado de este proyecto que sume dinero.
//
// Por qué no importa tanto en las columnas DECIMAL(x,2) que ya existían: al
// insertar, MySQL redondea cualquier valor al scale declarado de la columna,
// así que el ruido de float de un par de sumas casi nunca se nota ahí. Pero
// en columnas con más decimales (ej. rebate_pct DECIMAL(6,4)) o en cálculos
// encadenados (Resumen de Pagos: rebate $ + visibilidad $, por varias
// categorías) ese ruido sí puede acumularse — más vale hacerlo bien siempre
// que a veces.

function dinero_disponible_bcmath() {
	return function_exists('bcadd');
}

// Suma un array de valores numéricos (float, string, int — lo que venga de
// una celda de Excel o de la base) con precisión decimal exacta. Devuelve un
// float ya redondeado a $escala decimales, listo para bind_param 'd' o para
// mostrar. Si BCMath no está disponible en el servidor (no debería pasar,
// viene con PHP por defecto, pero este proyecto ya aprendió a no asumir
// extensiones — ver GD/zip en otros archivos), cae a array_sum nativo en vez
// de romper toda la importación.
function dinero_sumar(array $valores, $escala = 2) {
	if (!dinero_disponible_bcmath()) {
		return round(array_sum(array_map('floatval', $valores)), $escala);
	}
	$acumulado = '0';
	// Escala intermedia más alta que la final: evita perder precisión en
	// sumas encadenadas de muchos términos antes del redondeo de una sola
	// vez al final (redondear en cada paso intermedio sí podría acumular
	// error, aunque cada paso individual sea "exacto").
	$escalaIntermedia = max($escala + 4, 6);
	foreach ($valores as $v) {
		$acumulado = bcadd($acumulado, dinero_a_string($v, $escalaIntermedia), $escalaIntermedia);
	}
	return (float) bcadd($acumulado, '0', $escala);
}

// bcadd/bcmul exigen strings numéricos "normales" (sin notación científica,
// sin coma) — los floats de PHP en valores muy chicos o muy grandes a veces
// se imprimen en notación científica con (string), lo que rompe BCMath en
// silencio (trata "1.0E-5" como 1). number_format() lo evita siempre.
function dinero_a_string($valor, $decimales = 6) {
	return number_format((float) $valor, $decimales, '.', '');
}
?>
