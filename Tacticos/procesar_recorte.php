<?php
use Phppot\DataSource;

require_once 'conexion/DataSource.php';
$db = new DataSource();
$conn = $db->getConnection();

try {
    // Leer datos de la solicitud
    $data = json_decode(file_get_contents("php://input"), true);

    // Validar que los datos estén completos
    if (!isset($data["mercaderista"], $data["recortes"])) {
        echo json_encode(["success" => false, "message" => "Datos incompletos en la solicitud"]);
        http_response_code(400);
        exit;
    }

    $mercaderista = $data["mercaderista"];
    $recortes = $data["recortes"];

    // Iniciar transacción
    $conn->begin_transaction();

    // Validar cantidades antes de procesar
    foreach ($recortes as $recorte) {
        $tactico = $recorte["tactico"];
        $cantidadNueva = $recorte["cantidad"];

        // Verificar que el táctico existe y tiene suficiente cantidad
        $query = "SELECT cantidad_asignada FROM repositorio_distributivo WHERE mercaderista = ? AND tactico = ?";
        $result = $db->select($query, "ss", [$mercaderista, $tactico]);

        if (empty($result)) {
            throw new Exception("El táctico '$tactico' no existe para el mercaderista '$mercaderista'.");
        }

        $cantidadActual = $result[0]["cantidad_asignada"];

        if ($cantidadNueva > $cantidadActual) {
            throw new Exception("La cantidad nueva no puede ser mayor que la cantidad actual para el táctico '$tactico'.");
        }
    }

    // Procesar cada recorte
    foreach ($recortes as $recorte) {
        $tactico = $recorte["tactico"];
        $cantidadNueva = $recorte["cantidad"];

        // Actualizar la cantidad en el inventario
        $queryUpdate = "
            UPDATE repositorio_distributivo 
            SET cantidad_asignada = ? 
            WHERE mercaderista = ? AND tactico = ?";
        $affectedRows = $db->update($queryUpdate, "iss", [$cantidadNueva, $mercaderista, $tactico]);

        if ($affectedRows === false) {
            throw new Exception("Error al actualizar la cantidad del táctico '$tactico' para el mercaderista '$mercaderista'.");
        }

        // Registrar en el historial con tipo 'recorte'
        $queryHistorial = "
            INSERT INTO historial_intercambios (tactico, receptor, donante, cantidad, tipo, fecha)
            VALUES (?, ?, 'N/A', ?, 'recorte', NOW())";
        $db->update($queryHistorial, "ssi", [$tactico, $mercaderista, $cantidadNueva]);
    }

    // Confirmar transacción
    $conn->commit();

    // Respuesta exitosa
    echo json_encode(["success" => true, "message" => "Recorte procesado correctamente."]);
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();

    // Respuesta de error al cliente
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
