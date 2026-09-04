<?php
require_once __DIR__ . '/conexion/DataSource.php';
use Phppot\DataSource;

header("Content-Type: application/json");

try {
    $database = new DataSource();
    $conn = $database->getConnection();

    $query = "SELECT 
            mercaderista,
            GROUP_CONCAT(DISTINCT tactico) AS tacticos,
            SUM(cantidad_asignada) AS total_asignada
        FROM repositorio_distributivo
        GROUP BY mercaderista;";
    
    $result = $database->select($query);

    if ($result === false) {
        throw new Exception("Error al ejecutar la consulta en la base de datos.");
    }

    $inventario = [];
    foreach ($result as $row) {
        $mercaderista = $row["mercaderista"];
        $tacticos = explode(',', $row["tacticos"]); // Convertimos la lista en array
        $cantidad = $row["total_asignada"]; // Verificar el alias correcto de la columna

        if (!isset($inventario[$mercaderista])) {
            $inventario[$mercaderista] = [];
        }

        foreach ($tacticos as $tactico) {
            $inventario[$mercaderista][$tactico] = $cantidad; 
        }
    }

    echo json_encode(["success" => true, "data" => $inventario]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
