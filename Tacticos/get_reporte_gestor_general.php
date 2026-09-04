<?php
require_once __DIR__ . '/conexion/DataSource.php';

use Phppot\DataSource;

$database = new DataSource();
$conn = $database->getConnection();

header("Content-Type: application/json");

// Leer el cuerpo de la solicitud
$input = json_decode(file_get_contents("php://input"), true);

// Validar los datos recibidos
if (!isset($input['gestor'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Datos incompletos"]);
    exit;
}

$gestor = $input['gestor'];
//$pdv = $input['pdv'];

// Consulta SQL con formato de fecha DD/MM/YYYY
$query = "
    SELECT 
        id_packs as id_packs,
        usuario AS gestor,
        pos_name AS pdv, 
        categoriasec,
        subcategoriasec,
        presentacionsec,
        brandsec,
        sku_codesec,
        pvc,
        cantidad,
        cantidad_encontrada,
        motivo_reporte,
        foto AS foto_armado,
        foto_guia AS foto_guia,
        DATE_FORMAT(fecha, '%d/%m/%Y') AS fecha
    FROM 
        insert_packs
    WHERE 
        usuario = ?  
        AND estado = 3
        AND MONTH(STR_TO_DATE(fecha, '%d/%m/%Y')) = MONTH(CURDATE())
        AND YEAR(STR_TO_DATE(fecha, '%d/%m/%Y')) = YEAR(CURDATE())
    ORDER BY 
        STR_TO_DATE(fecha, '%d/%m/%Y') DESC
";

try {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $gestor);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            "id_packs" => $row['id_packs'],
            "gestor" => $row['gestor'],
            "pdv" => $row['pdv'],
            "categoriasec" => $row['categoriasec'],
            "subcategoriasec" => $row['subcategoriasec'],
            "presentacionsec" => $row['presentacionsec'],
            "brandsec" => $row['brandsec'],
            "sku_codesec" => $row['sku_codesec'],
            "pvc" => $row['pvc'],
            "cantidad" => $row['cantidad'],
            "cantidad_encontrada" => $row['cantidad_encontrada'],
            "observacion" => $row['observacion'], 
            "motivo_reporte" => $row['motivo_reporte'],
            "foto_armado" => $row['foto_armado'],
            "foto_guia" => $row['foto_guia'],
            "fecha" => $row['fecha']
        ];
    }

    echo json_encode(["success" => true, "data" => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error al procesar la solicitud", "details" => $e->getMessage()]);
}
?>
