<?php
use Phppot\DataSource;

require_once 'conexion/DataSource.php';

header('Content-Type: application/json');
$response = [];

try {
    $db = new DataSource();
    $result = $db->select("SELECT * FROM repositorio_distributivo");

    if (!empty($result)) {
        $response = [
            "success" => true,
            "message" => "Datos recuperados exitosamente.",
            "data" => $result
        ];
    } else {
        $response = [
            "success" => true,
            "message" => "No hay datos disponibles.",
            "data" => []
        ];
    }
} catch (Exception $e) {
    $response = [
        "success" => false,
        "message" => "Error al recuperar los datos: " . $e->getMessage()
    ];
}

echo json_encode($response);
