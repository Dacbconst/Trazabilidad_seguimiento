<?php
// Plantilla .xlsx en blanco con el formato esperado por repositorio_parsear_rebate()/_participacion(). require config.php (no db_connect.php completo, no hace falta conexión a la base para esto) — sin esto, iniciar_sesion() usa la constante SECURE sin definir y PHP 8.2 tira un error fatal (se ve como "404 Not Found" en nginx, mismo patrón ya documentado en este archivo para otro incidente).
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../includes/functions.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo 'No autorizado.';
	exit;
}

$tipo = $_GET['tipo'] ?? '';
if (!in_array($tipo, ['rebate', 'participacion', 'cuotas', 'jerarquia', 'acuerdo_completo'], true)) {
	http_response_code(400);
	echo 'Tipo de repositorio inválido.';
	exit;
}

require_once __DIR__.'/../includes/xlsx_writer.php';

$wb = new XlsxWriter();

if ($tipo === 'rebate') {
	$hoja = $wb->agregarHoja('REBATE');
	$cols = ['CIUDAD', 'CANAL', 'CATEGORIA', 'SUBCATEGORIA', 'MARCA', 'REBATE'];
	foreach ($cols as $i => $titulo) $wb->celda($hoja, 1, $i + 1, $titulo, true);
	$wb->celda($hoja, 2, 1, 'QUITO');
	$wb->celda($hoja, 2, 2, 'DIRECTA');
	$wb->celda($hoja, 2, 3, 'CREMA');
	$wb->celda($hoja, 2, 4, 'LAVAVAJILLAS');
	$wb->celda($hoja, 2, 5, 'EJEMPLO');
	$wb->celda($hoja, 2, 6, 0.04, false, 'pct');
	$nombreBase = 'Formato_Rebate';
} elseif ($tipo === 'participacion') {
	$hoja = $wb->agregarHoja('PARTICIPACION PERCHA');
	$cols = ['CIUDAD', 'CATEGORIA', 'SUBCATEGORIA', 'MARCA', '%'];
	foreach ($cols as $i => $titulo) $wb->celda($hoja, 1, $i + 1, $titulo, true);
	$wb->celda($hoja, 2, 1, 'TODAS');
	$wb->celda($hoja, 2, 2, 'CREMA');
	$wb->celda($hoja, 2, 3, 'LAVAVAJILLAS');
	$wb->celda($hoja, 2, 4, 'EJEMPLO');
	$wb->celda($hoja, 2, 5, 0.5, false, 'pct');
	$nombreBase = 'Formato_Participacion_Percha';
} elseif ($tipo === 'jerarquia') {
	$hoja = $wb->agregarHoja('JERARQUIA SUPERVISORES');
	$cols = ['SUPERVISOR CAMPO', 'JEFE DE AGENCIA'];
	foreach ($cols as $i => $titulo) $wb->celda($hoja, 1, $i + 1, $titulo, true);
	$nombreBase = 'Formato_Jerarquia_Supervisores';
} elseif ($tipo === 'acuerdo_completo') {
	// El parser detecta Cabecera/Percha por OCURRENCIA del nombre real del mes (ENERO repetido), no por texto compuesto — fila 2 debe decir "ENERO" liso, nunca "CABECERA ENERO".
	$canal = $_GET['canal'] ?? 'directo';
	if (!in_array($canal, ['directo', 'distribuidor'], true)) {
		http_response_code(400);
		echo 'Canal inválido.';
		exit;
	}
	$hoja = $wb->agregarHoja('ACUERDO COMPLETO');
	if ($canal === 'directo') {
		$identidad = ['USUARIO', 'CEDI', 'CLIENTE', 'PLAN', 'CATEGORIAS', 'SUBCATEGORIA', 'MARCA'];
		$fila1 = ['NOMBRE DE USUARIO EXACTO', 'NOMBRE DEL ASESOR', 'CLIENTE EJEMPLO', '', 'CREMA', 'LAVAVAJILLAS', 'EJEMPLO', 700, 700, 700, 100, 100, 100, 20, 3, 50, 50, 50];
		$fila2 = ['NOMBRE DE USUARIO EXACTO', 'NOMBRE DEL ASESOR', 'CLIENTE EJEMPLO', '', 'BARRA', 'ROPA', 'OTRO EJEMPLO', 300, 300, 300, '', '', '', '', '', '', '', ''];
		$nombreBase = 'Formato_AcuerdoCompleto_Directo';
	} else {
		$identidad = ['USUARIO', 'DISTRIBUIDOR', 'CIUDAD', 'NOMBRE', 'CODIGO', 'RUC', 'CATEGORIA', 'SUBCATEGORIA', 'MARCA'];
		$fila1 = ['NOMBRE DE USUARIO EXACTO', 'ASERTIA COMERCIAL SA', 'GUAYAQUIL', 'CLIENTE EJEMPLO', '', '', 'CREMA', 'LAVAVAJILLAS', 'EJEMPLO', 700, 700, 700, 100, 100, 100, 20, 3, 50, 50, 50];
		$fila2 = ['NOMBRE DE USUARIO EXACTO', 'ASERTIA COMERCIAL SA', 'GUAYAQUIL', 'CLIENTE EJEMPLO', '', '', 'BARRA', 'ROPA', 'OTRO EJEMPLO', 300, 300, 300, '', '', '', '', '', '', '', ''];
		$nombreBase = 'Formato_AcuerdoCompleto_Distribuidor';
	}
	$nIdent = count($identidad);
	$colMeta = $nIdent + 1;
	$colCab = $colMeta + 3;
	$colRuma = $colCab + 3;
	$colCant = $colRuma + 1;
	$colPercha = $colCant + 1;
	$colesMoneda = [$colMeta, $colMeta + 1, $colMeta + 2, $colCab, $colCab + 1, $colCab + 2, $colRuma, $colPercha, $colPercha + 1, $colPercha + 2];

	foreach ($identidad as $i => $titulo) $wb->celda($hoja, 2, $i + 1, $titulo, true);
	foreach (['ENERO', 'FEBRERO', 'MARZO'] as $i => $m) { $wb->celda($hoja, 2, $colMeta + $i, $m, true); $wb->celda($hoja, 2, $colCab + $i, $m, true); $wb->celda($hoja, 2, $colPercha + $i, $m, true); }
	$wb->celda($hoja, 2, $colRuma, 'RUMA', true);
	$wb->celda($hoja, 2, $colCant, 'CANTIDAD', true);

	$wb->celda($hoja, 1, $colMeta, 'META DE COMPRAS', true);
	$wb->celda($hoja, 1, $colCab, 'CABECERA', true);
	$wb->celda($hoja, 1, $colRuma, 'RUMA', true);
	$wb->celda($hoja, 1, $colCant, 'PERCHA', true);
	$wb->combinarCeldas($hoja, XlsxWriter::colLetra($colMeta).'1:'.XlsxWriter::colLetra($colMeta + 2).'1');
	$wb->combinarCeldas($hoja, XlsxWriter::colLetra($colCab).'1:'.XlsxWriter::colLetra($colCab + 2).'1');
	$wb->combinarCeldas($hoja, XlsxWriter::colLetra($colCant).'1:'.XlsxWriter::colLetra($colPercha + 2).'1');

	foreach ([3 => $fila1, 4 => $fila2] as $filaNum => $valores) {
		foreach ($valores as $i => $v) {
			$col = $i + 1;
			if ($v === '') { $wb->celda($hoja, $filaNum, $col, ''); continue; }
			$esMoneda = in_array($col, $colesMoneda, true);
			$wb->celda($hoja, $filaNum, $col, $v, false, $esMoneda ? 'money' : null);
		}
	}
} else {
	// Cuotas Trimestrales — 2 formatos según canal (repositorio_parsear_cuotas()
	// detecta cuál es solo, sin picker: Directo por CEDI/CLIENTE/CATEGORIAS,
	// Distribuidor por CIUDAD/NOMBRE/CATEGORIA — ver includes/repositorio_import.php).
	// Meses de ejemplo: ENERO/FEBRERO/MARZO (Q1) — cualquier trimestre completo sirve,
	// repositorio_cuotas_detectar_trimestre() los detecta solo por nombre.
	$canal = $_GET['canal'] ?? 'directo';
	if (!in_array($canal, ['directo', 'distribuidor'], true)) {
		http_response_code(400);
		echo 'Canal inválido.';
		exit;
	}
	if ($canal === 'directo') {
		$hoja = $wb->agregarHoja('CUOTAS');
		// USUARIO (2026-09-28, pedido explícito): primera columna, el usuario exacto tal como está en Gestión de Usuarios — el Excel manda directo, sin adivinar por CEDI. CEDI = nombre real del asesor (queda como respaldo si USUARIO viene vacío o mal tipeado).
		$cols = ['USUARIO', 'CEDI', 'CLIENTE', 'PLAN', 'CATEGORIAS', 'SUBCATEGORIA', 'MARCA', 'ENERO', 'FEBRERO', 'MARZO'];
		foreach ($cols as $i => $titulo) $wb->celda($hoja, 1, $i + 1, $titulo, true);
		$wb->celda($hoja, 2, 1, 'NOMBRE DE USUARIO EXACTO');
		$wb->celda($hoja, 2, 2, 'NOMBRE DEL ASESOR');
		$wb->celda($hoja, 2, 3, 'CLIENTE EJEMPLO');
		$wb->celda($hoja, 2, 4, '');
		$wb->celda($hoja, 2, 5, 'CREMA');
		$wb->celda($hoja, 2, 6, 'LAVAVAJILLAS');
		$wb->celda($hoja, 2, 7, 'EJEMPLO');
		$wb->celda($hoja, 2, 8, 700, false, 'money');
		$wb->celda($hoja, 2, 9, 700, false, 'money');
		$wb->celda($hoja, 2, 10, 700, false, 'money');
		$nombreBase = 'Formato_Cuotas_Directo';
	} else {
		$hoja = $wb->agregarHoja('CUOTAS');
		// CODIGO/RUC (2026-09-18) opcionales, igual que SUBCATEGORIA/MARCA. USUARIO (2026-09-28) primera columna, mismo criterio que Directo arriba.
		$cols = ['USUARIO', 'DISTRIBUIDOR', 'CIUDAD', 'NOMBRE', 'CODIGO', 'RUC', 'CATEGORIA', 'SUBCATEGORIA', 'MARCA', 'ENERO', 'FEBRERO', 'MARZO'];
		foreach ($cols as $i => $titulo) $wb->celda($hoja, 1, $i + 1, $titulo, true);
		$wb->celda($hoja, 2, 1, 'NOMBRE DE USUARIO EXACTO');
		$wb->celda($hoja, 2, 2, 'ASERTIA COMERCIAL SA');
		$wb->celda($hoja, 2, 3, 'GUAYAQUIL');
		$wb->celda($hoja, 2, 4, 'CLIENTE EJEMPLO');
		$wb->celda($hoja, 2, 5, '');
		$wb->celda($hoja, 2, 6, '');
		$wb->celda($hoja, 2, 7, 'CREMA');
		$wb->celda($hoja, 2, 8, 'LAVAVAJILLAS');
		$wb->celda($hoja, 2, 9, 'EJEMPLO');
		$wb->celda($hoja, 2, 10, 700, false, 'money');
		$wb->celda($hoja, 2, 11, 700, false, 'money');
		$wb->celda($hoja, 2, 12, 700, false, 'money');
		$nombreBase = 'Formato_Cuotas_Distribuidor';
	}
}

$bin = $wb->generar();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$nombreBase.'.xlsx"');
header('Content-Length: '.strlen($bin));
echo $bin;
