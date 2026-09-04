<?php
require_once __DIR__ . '/conexion/DataSource.php';
use Phppot\DataSource;

$database = new DataSource();
$conn = $database->getConnection();

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents("php://input"), true);
    $id_packs = isset($input['id_packs']) ? intval($input['id_packs']) : 0;
    $activar = isset($input['activar']) && $input['activar'] === true;

    if ($id_packs <= 0) {
        throw new Exception("ID no válido.");
    }

    // Si activar == true → estado NULL (o 1 si prefieres)
    // Si activar == false → estado 4
    $nuevo_estado = $activar ? NULL : 4;

    $query = "UPDATE insert_packs SET estado = ? WHERE id_packs = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $nuevo_estado, $id_packs);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "nuevo_estado" => $nuevo_estado
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
