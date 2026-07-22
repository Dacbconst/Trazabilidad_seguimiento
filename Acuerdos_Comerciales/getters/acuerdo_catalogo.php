<?php
// Catálogo de Segmento -> Categoría -> Marca para las 4 tablas del Acta.
// repositorio_productos es compartida con otros fabricantes (La Fabril,
// Unilever, Colgate, etc.) — SIEMPRE filtrar por fabricante para no mezclar
// catálogo de la competencia en los spinners de este acuerdo. También se
// filtra `activar = 'SI'` — de los 342 SKU de Wilson, 79 están marcados
// como descontinuados (activar='NO') y no deben aparecer como opción.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['admin', 'desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

define('FABRICANTE_ACUERDOS', 'JABONERIA WILSON');

$segmentos = [];
$res = $mysqli->query(
	"SELECT DISTINCT segmento, categoria, marca
	 FROM repositorio_productos
	 WHERE fabricante = '".$mysqli->real_escape_string(FABRICANTE_ACUERDOS)."'
	   AND activar = 'SI'
	   AND segmento IS NOT NULL AND segmento <> ''
	   AND categoria IS NOT NULL AND categoria <> ''
	   AND marca IS NOT NULL AND marca <> ''
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
	 ORDER BY marca"
);
while ($row = $res->fetch_assoc()) {
	$marcasPercha[] = $row['marca'];
}

// Sector (ej: "CREMA"/"BARRA"/"LIQUIDO" para LAVA+LAVAVAJILLAS) — solo se usa
// en la tabla de Meta de Compras, como columna extra tras Marca (pedido del
// usuario para poder armar el nombre completo tipo "Crema Lavavajillas
// LAVA"). Se guarda separado de `segmentos` (en vez de meterlo como un nivel
// más del árbol) para no tocar la forma que ya usan Cabeceras/Rumas/Perchas.
// Clave: "segmento|categoria|marca" -> [sectores...].
$sectores = [];
$res = $mysqli->query(
	"SELECT DISTINCT segmento, categoria, marca, sector
	 FROM repositorio_productos
	 WHERE fabricante = '".$mysqli->real_escape_string(FABRICANTE_ACUERDOS)."'
	   AND activar = 'SI'
	   AND segmento IS NOT NULL AND segmento <> ''
	   AND categoria IS NOT NULL AND categoria <> ''
	   AND marca IS NOT NULL AND marca <> ''
	   AND sector IS NOT NULL AND sector <> ''
	 ORDER BY segmento, categoria, marca, sector"
);
while ($row = $res->fetch_assoc()) {
	$key = $row['segmento'].'|'.$row['categoria'].'|'.$row['marca'];
	if (!isset($sectores[$key])) $sectores[$key] = [];
	if (!in_array($row['sector'], $sectores[$key], true)) $sectores[$key][] = $row['sector'];
}

echo json_encode([
	'ok'            => true,
	'segmentos'     => $segmentos,
	'marcas_percha' => $marcasPercha,
	'sectores'      => $sectores,
]);
?>
