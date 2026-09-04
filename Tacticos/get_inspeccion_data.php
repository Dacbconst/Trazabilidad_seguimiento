<?php
require_once __DIR__ . '/conexion/DataSource.php';

use Phppot\DataSource;

$database = new DataSource();
$conn = $database->getConnection();

header("Content-Type: application/json");

try {
    $currentMonth = date("Y-m");
    
    // Consulta para los registros validados
    $queryValidaciones = "
        SELECT tactico, gestor, pdv, cantidad_armada, fecha_creacion 
        FROM validacion_tacticos 
        WHERE DATE_FORMAT(fecha_creacion, '%Y-%m') = ?
    ";
    $validaciones = $database->select($queryValidaciones, "s", [$currentMonth]);

    // Consulta para los registros reportados
    $queryReportes = "
        SELECT tactico, gestor, pdv, observacion, fecha_creacion 
        FROM reporte_validaciones 
        WHERE DATE_FORMAT(fecha_creacion, '%Y-%m') = ?
    ";
    $reportes = $database->select($queryReportes, "s", [$currentMonth]);

    echo json_encode([
        "success" => true,
        "validaciones" => $validaciones,
        "reportes" => $reportes
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener los datos",
        "details" => $e->getMessage()
    ]);
}
