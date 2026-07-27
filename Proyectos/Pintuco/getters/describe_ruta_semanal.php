<?php
// describe_ruta_semanal.php — diagnóstico de la tabla lvi_ruta_semanal.
// Devuelve estructura (DESCRIBE) + una muestra de filas, en JSON.
error_reporting(0);
ini_set('display_errors', '0');

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

include_once '../db_connect.php';

$tabla = 'lvi_ruta_semanal';

$estructura = [];
if ($res = $mysqli->query("DESCRIBE `$tabla`")) {
    while ($fila = $res->fetch_assoc()) {
        $estructura[] = $fila;
    }
}

$muestra = [];
if ($res = $mysqli->query("SELECT * FROM `$tabla` LIMIT 10")) {
    while ($fila = $res->fetch_assoc()) {
        $muestra[] = $fila;
    }
}

echo json_encode([
    "tabla" => $tabla,
    "describe" => $estructura,
    "ejemplo" => $muestra,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
