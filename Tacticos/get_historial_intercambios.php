<?php
use Phppot\DataSource;

require_once 'conexion/DataSource.php';
$db = new DataSource();
$conn = $db->getConnection();

header("Content-Type: application/json");

try {
    $query = "SELECT tactico, receptor, donante, cantidad, tipo, fecha FROM historial_intercambios ORDER BY fecha DESC";
    $result = $db->select($query);

    if ($result === false) {
        throw new Exception("Error al obtener el historial de intercambios.");
    }

    echo json_encode(["success" => true, "data" => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
