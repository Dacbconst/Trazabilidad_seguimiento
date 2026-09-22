<?php
// Catálogo Segmento -> Categoría -> Marca para las 4 tablas del Acta. repositorio_productos es compartida entre fabricantes: siempre filtrar por fabricante y activar='SI'.
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

// Restringido a lo que JW realmente configuró en el Repositorio de Rebate (2026-09-22, pedido explícito: "no me quemes datos en código", reemplaza el arreglo hardcodeado que había acá antes) — si un Sector+Marca no tiene Rebate configurado, no es un producto real de Acuerdos Comerciales, sin importar qué diga repositorio_productos (tabla compartida entre fabricantes/módulos). Match por Sector+Marca, NO por Categoría — confirmado con datos reales que el nombre de Categoría difiere entre las 2 tablas para el mismo producto real (ej. BARRA+EL MACHO: Rebate dice "DETERGENTE", el catálogo de productos dice "ROPA"), decisión explícita del usuario de ignorar esa columna acá. TRIM(TRAILING 'S'...) tolera singular/plural (LIQUIDO/LIQUIDOS, LAVAVAJILLA/LAVAVAJILLAS), mismo criterio ya usado en buscarRebateProducto().
$filtroSectorCategoria = "EXISTS (
	SELECT 1 FROM repositorio_rebate_producto r
	WHERE r.eliminado_en IS NULL
	  AND TRIM(TRAILING 'S' FROM UPPER(TRIM(r.sector))) = TRIM(TRAILING 'S' FROM UPPER(TRIM(repositorio_productos.sector)))
	  AND UPPER(TRIM(r.marca)) = UPPER(TRIM(repositorio_productos.marca)))";

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

// Árbol Segmento -> Sector -> Categoría -> [Marcas], solo para Meta de Compras: el nombre impreso del Acta es "Sector + Categoría + Marca". Cabeceras/Rumas/Perchas siguen usando `segmentos` (sin Sector) a propósito.
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
