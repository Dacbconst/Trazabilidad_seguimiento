<?php
require_once __DIR__ . '/conexion/DataSource.php';

use Phppot\DataSource;

// Crear instancia de la conexión
$database = new DataSource();
$conn = $database->getConnection();
header("Content-Type: application/json");

$query = "
    SELECT 
        ins.sku_codesec AS tactico,
        SUM(DISTINCT rd.cantidad_asignada) AS cantidad_distribuida,
        SUM(CASE WHEN vt.cantidad_armada IS NOT NULL THEN vt.cantidad_armada ELSE 0 END) AS cantidad_armada
    FROM 
        insert_packs ins
    LEFT JOIN 
        validacion_tacticos vt ON vt.tactico = ins.sku_codesec
    LEFT JOIN 
        repositorio_distributivo rd ON ins.usuario = rd.mercaderista
    WHERE 
        MONTH(STR_TO_DATE(ins.fecha, '%d/%m/%Y')) = MONTH(CURDATE())
        AND YEAR(STR_TO_DATE(ins.fecha, '%d/%m/%Y')) = YEAR(CURDATE())
    GROUP BY 
        ins.sku_codesec
    ORDER BY 
        ins.sku_codesec;
";

$result = $database->select($query);

echo json_encode($result);
?>
