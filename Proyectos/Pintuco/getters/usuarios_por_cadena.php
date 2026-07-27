<?php
// usuarios_por_cadena.php — usuarios/contraseñas de lvi_ruta_semanal agrupados
// por cadena (KYWI vs otras), para pruebas de acceso.
error_reporting(0);
ini_set('display_errors', '0');

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

include_once '../db_connect.php';

$query = "SELECT DISTINCT id_usuario, user, pass, subchannel, mercaderista
          FROM lvi_ruta_semanal
          WHERE user IS NOT NULL AND user <> ''
          ORDER BY subchannel, user";

$kywi = [];
$otras = [];
if ($res = $mysqli->query($query)) {
    while ($fila = $res->fetch_assoc()) {
        if (stripos((string) $fila['subchannel'], 'KYWI') !== false) {
            $kywi[] = $fila;
        } else {
            $otras[] = $fila;
        }
    }
}

echo json_encode([
    "kywi" => $kywi,
    "otras_cadenas" => $otras,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
