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

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
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

// Árbol Segmento -> Sector -> Categoría -> [Marcas], SOLO para Meta de
// Compras (2026-08-18, pedido explícito del usuario tras revisar un Acta real
// escaneada: el nombre impreso de cada categoría es literalmente "Sector +
// Categoría + Marca", ej. "Crema Lavavajillas LAVA"). Cabeceras/Rumas/Perchas
// siguen usando `segmentos` de arriba (Segmento->Categoría->Marca, sin
// Sector) — no se tocó esa forma a propósito. Se comprobó con datos reales
// que Sector depende limpio de Segmento (ej. Cuidado del Hogar: solo 5
// sectores) y Categoría depende limpio de Segmento+Sector (1 a 4 categorías
// cada uno) — por eso este orden de cascada no explota en ningún paso.
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
