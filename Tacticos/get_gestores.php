<?php
require_once __DIR__ . '/conexion/DataSource.php';

use Phppot\DataSource;

$database = new DataSource();
$conn = $database->getConnection();

header("Content-Type: application/json");

try {
    $baseURL = "https://luckyecuadorweb.blob.core.windows.net/app/AppJaboneriaWilson/Inserts/";

    // Consulta principal para gestores

//     SELECT 
//     ins.id_packs,
//     ins.usuario AS mercaderista,
//     ins.pos_name AS pdv,
//     ins.cantidad_encontrada AS cantidad_encontrada,
//     ins.cantidad AS cantidad_armada,
//     ins.sku_codesec AS tactico,
//     ins.foto AS foto_armado,
//     ins.foto_guia AS foto_guia,
//     ins.estado,
//     STR_TO_DATE(ins.fecha, '%d/%m/%Y') AS fecha_registro
// FROM 
//     insert_packs ins
// INNER JOIN 
//     repositorio_usuario ru 
//     ON ins.usuario = ru.user
// WHERE 
//     STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN STR_TO_DATE('06/01/2025', '%d/%m/%Y') 
//                                            AND DATE_SUB(CURDATE(), INTERVAL 1 DAY)
//     AND ru.user NOT IN ('LUCKY UIO', 'LUCKY GYE', 'PRUEBA GYE')
// GROUP BY 
//     ins.usuario, ins.pos_name, ins.cantidad_encontrada, 
//     ins.cantidad, ins.sku_codesec, ins.estado, ins.fecha
// ORDER BY 
//     ins.usuario, ins.pos_name, ins.fecha, ins.sku_codesec;


    $query = "
            SELECT 
            ins.id_packs,
            ins.usuario AS mercaderista,
            ins.pos_name AS pdv,
            ins.cantidad_encontrada AS cantidad_encontrada,
            ins.cantidad AS cantidad_armada,
            ins.sku_codesec AS tactico,
            ins.sku_code AS producto_primario,
            ins.foto AS foto_armado,
            ins.foto_guia AS foto_guia,
            ins.estado,
            STR_TO_DATE(ins.fecha, '%d/%m/%Y') AS fecha_registro
        FROM insert_packs ins
        INNER JOIN repositorio_usuario ru 
            ON ins.usuario = ru.user
        WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        AND ru.user NOT IN ('LUCKY UIO', 'LUCKY GYE', 'PRUEBA GYE')
        GROUP BY  ins.usuario, ins.pos_name, ins.cantidad_encontrada, 
                ins.cantidad, ins.sku_codesec, ins.estado,ins.fecha
        ORDER BY ins.usuario, ins.pos_name, ins.fecha, ins.sku_codesec;

    ";

    $result = $database->select($query);

    $gestores = [];
    foreach ($result as $row) {
        $usuario = $row['mercaderista'];

        if (!isset($gestores[$usuario])) {
            $gestores[$usuario] = [
                "nombre" => $usuario,
                "pdvs" => []
            ];
        }

        $pdvIndex = array_search($row['pdv'], array_column($gestores[$usuario]['pdvs'], 'nombre'));

        if ($pdvIndex === false) {
            $gestores[$usuario]['pdvs'][] = [
                "nombre" => $row['pdv'],
                "tacticos" => []
            ];
            $pdvIndex = count($gestores[$usuario]['pdvs']) - 1;
        }

        $gestores[$usuario]['pdvs'][$pdvIndex]['tacticos'][] = [
            "id_packs" => $row['id_packs'],
            "producto_primario" => $row['producto_primario'],
            "nombre" => $row['tactico'],
            "cantidad_encontrada" => $row['cantidad_encontrada'],
            "cantidad_armada" => $row['cantidad_armada'],
            "foto_guia" => $baseURL . $row['foto_guia'],
            "foto_armado" => $baseURL . $row['foto_armado'],
            "fecha_registro" => $row['fecha_registro'],
            "estado" => $row['estado'], 
        ];
    }

    echo json_encode(array_values($gestores), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error al procesar la solicitud", "details" => $e->getMessage()]);
}
