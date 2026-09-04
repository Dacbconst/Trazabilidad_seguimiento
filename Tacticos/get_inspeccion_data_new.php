<?php
require_once __DIR__ . '/conexion/DataSource.php';

use Phppot\DataSource;

$database = new DataSource();
$conn = $database->getConnection();

header("Content-Type: application/json");

try {
    $esAdicional = isset($_GET['es_adicional']) && $_GET['es_adicional'] === 'true';
    $currentMonth = date("Y-m");
    $currentMonthNumber = date("n");


    $whereTipo = $esAdicional
        ? "AND rpo.historico = 'Adicional'"
        : "AND (rpo.historico IS NULL OR rpo.historico <> 'Adicional')";


    $queryCorregidos = "
        SELECT 
            ip.usuario AS gestor,
            ip.pos_name AS pdv,
            ip.sku_codesec AS tactico,
            ip.motivo_reporte AS motivo,
            STR_TO_DATE(ip.fecha, '%d/%m/%Y') AS fecha_creacion
        FROM insert_packs ip
        LEFT JOIN repositorio_productos_onpacks rpo 
            ON ip.sku_codesec = rpo.sku AND rpo.activar = 'SI'
        WHERE ip.estado = 1
        AND DATE_FORMAT(STR_TO_DATE(ip.fecha, '%d/%m/%Y'), '%Y-%m') = '$currentMonth'
        $whereTipo  
    ";
    $correjidos = $database->select($queryCorregidos);


$queryValidaciones = "
    SELECT
        vt.gestor,
        vt.pdv,
        vt.tactico,
        vt.cantidad_armada,
        STR_TO_DATE(ip.fecha, '%d/%m/%Y') AS fecha_pack,
        vt.fecha_creacion AS fecha_validado
    FROM
        validacion_tacticos vt
    LEFT JOIN
        repositorio_productos_onpacks rpo ON vt.tactico = rpo.sku AND rpo.activar = 'SI'
    LEFT JOIN
        (SELECT
            sku_codesec,
            pos_name,
            usuario,
            fecha
        FROM
            insert_packs
        GROUP BY
            sku_codesec, pos_name) AS ip
        ON vt.tactico = ip.sku_codesec AND vt.pdv = ip.pos_name
    WHERE
        vt.mes_reporte = '$currentMonthNumber'
        AND DATE_FORMAT(vt.fecha_creacion, '%Y-%m') = '$currentMonth'
        $whereTipo
";
    $validaciones = $database->select($queryValidaciones);


    $queryReportes = "
        SELECT 
            rv.gestor, 
            rv.pdv, 
            rv.tactico, 
            rv.observacion AS motivo, 
            STR_TO_DATE(ip.fecha, '%d/%m/%Y') AS fecha_pack,
            rv.fecha_creacion AS fecha_reportado
        FROM reporte_validaciones rv
        LEFT JOIN repositorio_productos_onpacks rpo   
            ON rv.tactico = rpo.sku AND rpo.activar = 'SI'
        LEFT JOIN insert_packs ip
            ON rv.tactico = ip.sku_codesec AND rv.pdv = ip.pos_name
        WHERE
            DATE_FORMAT(rv.fecha_creacion, '%Y-%m') = '$currentMonth'
        $whereTipo 
    ";
    $reportes = $database->select($queryReportes);

    $response = [
        "success" => true,
        "validaciones" => $validaciones,
        "reportes" => $reportes,
        "correjidos" => $correjidos
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {   
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener los datos",
        "details" => $e->getMessage()
    ]);
} 
