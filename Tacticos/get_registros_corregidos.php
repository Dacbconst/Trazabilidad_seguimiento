<?php
require_once __DIR__ . '/conexion/DataSource.php';
use Phppot\DataSource;

$database = new DataSource();
$conn = $database->getConnection();

header("Content-Type: application/json");

try {
    // Base URL para las imágenes
    $baseURL = "https://luckyecuadorweb.blob.core.windows.net/app/AppJaboneriaWilson/Inserts/";

    
    $query = "
        SELECT 
            ins.id_packs,
            ins.usuario AS mercaderista,
            ins.pos_name AS pdv,
            ins.cantidad_encontrada,
            ins.cantidad AS cantidad_armada,
            ins.sku_code As primario,
            ins.sku_codesec AS tactico,
            ins.categoriasec,
            ins.subcategoriasec,
            ins.brandsec AS marca,
            ins.pvc,
            ins.foto AS foto_armado,
            ins.foto_guia AS foto_guia,
            STR_TO_DATE(ins.fecha, '%d/%m/%Y') AS fecha_registro
        FROM 
            insert_packs ins
        INNER JOIN 
            repositorio_usuario ru 
            ON ins.usuario = ru.`user`
        WHERE 
            ins.estado = 1
        ORDER BY 
            ins.usuario, ins.pos_name, ins.fecha, ins.sku_codesec;
    ";

    $result = $database->select($query);

    if (!$result) {
        throw new Exception("No se encontraron datos.");
    }

    // Procesar y limpiar los datos
    $corregidos = array_map(function ($row) use ($baseURL) {
        return [
            "id_packs" => (int)($row['id_packs'] ?? 0),
            "mercaderista" => trim($row['mercaderista'] ?? ''),
            "pdv" => trim($row['pdv'] ?? ''),
            "cantidad_encontrada" => (int)($row['cantidad_encontrada'] ?? 0),
            "cantidad_armada" => (int)($row['cantidad_armada'] ?? 0),
            "primario" => trim($row['primario'] ?? ''),
            "tactico" => trim($row['tactico'] ?? ''),
            "categoriasec" => trim($row['categoriasec'] ?? ''),
            "subcategoriasec" => trim($row['subcategoriasec'] ?? ''),
            "marca" => trim($row['marca'] ?? ''),
            "pvc" => trim($row['pvc'] ?? ''),
            "foto_armado" => !empty($row['foto_armado']) ? $baseURL . trim($row['foto_armado']) : '',
            "foto_guia" => !empty($row['foto_guia']) ? $baseURL . trim($row['foto_guia']) : '',
            "fecha_registro" => trim($row['fecha_registro'] ?? '')
        ];
    }, $result);

    $response = [
        "success" => true,
        "data" => $corregidos
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al procesar la solicitud",
        "details" => $e->getMessage()
    ]);
}
?>
