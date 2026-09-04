<?php
require_once __DIR__ . '/conexion/DataSource.php';

use Phppot\DataSource;

$database = new DataSource();
$conn = $database->getConnection();

header("Content-Type: application/json");

try {
    // Base URL para las imágenes
    $baseURL = "https://luckyecuadorweb.blob.core.windows.net/app/AppJaboneriaWilson/Inserts/";

    // Consulta principal para obtener las evidencias
    $query = "
        SELECT 
            mercaderista,
            fecha,
            hora,
            es_recibido,
            observacion,
            foto_recibido,
            foto_guia,
            cantidad_recibida
        FROM insert_tacticos_recibidos
        WHERE mercaderista NOT IN ('LUCKY UIO', 'LUCKY GYE', 'PRUEBA GYE')
        ORDER BY fecha DESC ";

    $result = $database->select($query);

    if (!$result) {
        throw new Exception("No se encontraron datos.");
    }

    // Procesar y limpiar los datos
    $evidencias = array_map(function ($row) use ($baseURL) {
        return [
            "mercaderista" => trim($row['mercaderista'] ?? ''),
            "fecha" => trim($row['fecha'] ?? ''),
            "hora" => trim($row['hora'] ?? ''),
            "es_recibido" => (int)($row['es_recibido'] ?? 0),
            //"observacion" => utf8_encode(trim($row['observacion'] ?? '')),
            "foto_recibido" => !empty($row['foto_recibido']) ? $baseURL . trim($row['foto_recibido']) : '',
            "foto_guia" => !empty($row['foto_guia']) ? $baseURL . trim($row['foto_guia']) : '',
            "cantidad_recibida" => (int)($row['cantidad_recibida'] ?? 0)
        ];
    }, $result);
    $arri ["success" ] = true;
    
    $arri  ["data"] = $evidencias;

    // $arri = array_map('utf8_encode_recursive', $arri);
 
    // function utf8_encode_recursive($value) {
    //     if (is_array($value)) {
    //         return array_map('utf8_encode_recursive', $value);
    //     } else {
    //         return utf8_encode($value);
    //     }
    // }
   

    print json_encode($arri);

   
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al procesar la solicitud",
        "details" => $e->getMessage()
    ]);
}
?>
