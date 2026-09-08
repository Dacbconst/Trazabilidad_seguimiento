<?php
// Catálogo Segmento -> Categoría -> Marca para las 4 tablas del Acta.
// repositorio_productos es compartida entre fabricantes: siempre filtrar por fabricante y activar='SI'.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

define('FABRICANTE_ACUERDOS', 'JABONERIA WILSON');

// Restringido a los 4 Sectores que realmente usa el módulo (BARRA/CREMA/LIQUIDO/POLVO) — ver CLAUDE.md "Alcance real de Acuerdos Comerciales".
// Filtra las 4 tablas del Acta, no solo Meta de Compras.
$combosValidos = [
	['BARRA', 'LAVAVAJILLAS'],
	['BARRA', 'ROPA'],
	['CREMA', 'LAVAVAJILLAS'],
	['LIQUIDO', 'DESINFECTANTES'],
	['LIQUIDO', 'DETERGENTE'],
	['LIQUIDO', 'JABON TOCADOR'],
	['LIQUIDO', 'LAVAVAJILLAS'],
	['LIQUIDO', 'SUAVIZANTES'],
	['POLVO', 'DETERGENTE'],
];
$condicionesCombo = [];
foreach ($combosValidos as $combo) {
	$condicionesCombo[] = "(sector = '".$mysqli->real_escape_string($combo[0])."' AND categoria = '".$mysqli->real_escape_string($combo[1])."')";
}
$filtroSectorCategoria = '('.implode(' OR ', $condicionesCombo).')';

$segmentos = [];
$res = $mysqli->query(
	"SELECT DISTINCT segmento, categoria, marca
	 FROM repositorio_productos
	 WHERE fabricante = '".$mysqli->real_escape_string(FABRICANTE_ACUERDOS)."'
	   AND activar = 'SI'
	   AND segmento IS NOT NULL AND segmento <> ''
	   AND categoria IS NOT NULL AND categoria <> ''
	   AND marca IS NOT NULL AND marca <> ''
	   AND $filtroSectorCategoria
	 ORDER BY segmento, categoria, marca"
);
while ($row = $res->fetch_assoc()) {
	$seg = $row['segmento'];
	$cat = $row['categoria'];
	$mar = $row['marca'];
	if (!isset($segmentos[$seg])) $segmentos[$seg] = [];
	if (!isset($segmentos[$seg][$cat])) $segmentos[$seg][$cat] = [];
	if (!in_array($mar, $segmentos[$seg][$cat], true)) $segmentos[$seg][$cat][] = $mar;
}

// La tabla de Perchas no usa Segmento/Categoría (ver CLAUDE.md), solo Marca.
$marcasPercha = [];
$res = $mysqli->query(
	"SELECT DISTINCT marca FROM repositorio_productos
	 WHERE fabricante = '".$mysqli->real_escape_string(FABRICANTE_ACUERDOS)."'
	   AND activar = 'SI'
	   AND marca IS NOT NULL AND marca <> ''
	   AND $filtroSectorCategoria
	 ORDER BY marca"
);
while ($row = $res->fetch_assoc()) {
	$marcasPercha[] = $row['marca'];
}

// Árbol Segmento -> Sector -> Categoría -> [Marcas], solo para Meta de Compras: el nombre impreso del Acta es "Sector + Categoría + Marca".
// Cabeceras/Rumas/Perchas siguen usando `segmentos` (sin Sector) a propósito.
$segmentosSector = [];
$res = $mysqli->query(
	"SELECT DISTINCT segmento, sector, categoria, marca
	 FROM repositorio_productos
	 WHERE fabricante = '".$mysqli->real_escape_string(FABRICANTE_ACUERDOS)."'
	   AND activar = 'SI'
	   AND segmento IS NOT NULL AND segmento <> ''
	   AND sector IS NOT NULL AND sector <> ''
	   AND categoria IS NOT NULL AND categoria <> ''
	   AND marca IS NOT NULL AND marca <> ''
	   AND $filtroSectorCategoria
	 ORDER BY segmento, sector, categoria, marca"
);
while ($row = $res->fetch_assoc()) {
	$seg = $row['segmento'];
	$sec = $row['sector'];
	$cat = $row['categoria'];
	$mar = $row['marca'];
	if (!isset($segmentosSector[$seg])) $segmentosSector[$seg] = [];
	if (!isset($segmentosSector[$seg][$sec])) $segmentosSector[$seg][$sec] = [];
	if (!isset($segmentosSector[$seg][$sec][$cat])) $segmentosSector[$seg][$sec][$cat] = [];
	if (!in_array($mar, $segmentosSector[$seg][$sec][$cat], true)) $segmentosSector[$seg][$sec][$cat][] = $mar;
}

echo json_encode([
	'ok'               => true,
	'segmentos'        => $segmentos,
	'marcas_percha'    => $marcasPercha,
	'segmentos_sector' => $segmentosSector,
]);
?>
