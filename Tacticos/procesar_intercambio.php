<?php
use Phppot\DataSource;

require_once 'conexion/DataSource.php';
$db = new DataSource();
$conn = $db->getConnection();

try {
    // Leer datos de la solicitud
    $data = json_decode(file_get_contents("php://input"), true);

    // Validar que los datos estén completos
    if (!isset($data["receptor"], $data["donantes"], $data["intercambios"])) {
        echo json_encode(["success" => false, "message" => "Datos incompletos en la solicitud"]);
        http_response_code(400);
        exit;
    }   
    
    $receptor = $data["receptor"];
    $donantes = $data["donantes"];
    $intercambios = $data["intercambios"];

    // Iniciar transacción
    $conn->begin_transaction();

    // Validar cantidades antes de procesar
    foreach ($intercambios as $intercambio) {
        $tactico = $intercambio["tactico"];
        $cantidad = $intercambio["cantidad"];

        foreach ($donantes as $donante) {
            $query = "SELECT cantidad_asignada FROM repositorio_distributivo WHERE mercaderista = ? AND tactico = ?";
            $result = $db->select($query, "ss", [$donante, $tactico]);

            if (empty($result) || $result[0]["cantidad_asignada"] < $cantidad) {
                throw new Exception("Cantidad insuficiente para el táctico '{$tactico}' del mercaderista '{$donante}'");
            }
        }
    }

    // Procesar cada intercambio
    foreach ($intercambios as $intercambio) {
        $tactico = $intercambio["tactico"];
        $cantidad = $intercambio["cantidad"];

        // Reducir inventario de los donantes
        foreach ($donantes as $donante) {
            $query = "
                UPDATE repositorio_distributivo 
                SET cantidad_asignada = cantidad_asignada - ? 
                WHERE mercaderista = ? AND tactico = ? AND cantidad_asignada >= ?";
            $affectedRows = $db->update($query, "issi", [$cantidad, $donante, $tactico, $cantidad]);
            if ($affectedRows === false) {
                throw new Exception("Error al reducir inventario del donante: $donante, Táctico: $tactico");
            }
        }

        // Incrementar inventario del receptor
        $query = "
            UPDATE repositorio_distributivo 
            SET cantidad_asignada = cantidad_asignada + ? 
            WHERE mercaderista = ? AND tactico = ?";
        $affectedRows = $db->update($query, "iss", [$cantidad, $receptor, $tactico]);

        // Si no existe, agregar el registro
        // if ($affectedRows === 0) {
        //     $query = "
        //         INSERT INTO repositorio_distributivo (mercaderista, tactico, cantidad_asignada)
        //         VALUES (?, ?, ?)";
        //     $affectedRows = $db->update($query, "ssi", [$receptor, $tactico, $cantidad]);
        //     if ($affectedRows === false) {
        //         throw new Exception("Error al agregar inventario del receptor: $receptor, Táctico: $tactico");
        //     }
        // }

        // Registrar en el historial sin la columna observaciones
        foreach ($donantes as $donante) {
            $queryHistorial = "
                INSERT INTO historial_intercambios (tactico, receptor, donante, cantidad, tipo, fecha)
                VALUES (?, ?, ?, ?, ?, NOW())";
            $db->update($queryHistorial, "sssis", [$tactico, $receptor, $donante, $cantidad, 'intercambio']);
        }
        
    }

    // Confirmar transacción
    $conn->commit();

    // Respuesta exitosa
    echo json_encode(["success" => true, "message" => "Intercambio procesado con éxito"]);
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();

    // Respuesta de error al cliente
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error interno en el servidor", "details" => $e->getMessage()]);
}
?>
