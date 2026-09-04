<?php
require_once __DIR__ . '/conexion/DataSource.php';
use Phppot\DataSource;

header("Content-Type: application/json");

$database = new DataSource();
$conn = $database->getConnection();

try {
    $baseURL = "https://luckyecuadorweb.blob.core.windows.net/app/AppJaboneriaWilson/Inserts/";

    $query = "
        SELECT 
            ins.fecha,
            ins.codigo,
            rpdv.pos_name AS local,
            rpdv.region,
            rpdv.province AS provincia,
            rpdv.city AS ciudad,
            rpdv.channel AS canal,
            rd.jefatura,
            rd.ejecutivo,
            rs.supervisor,
            ins.usuario AS mercaderista,
            ins.categoria AS categoria_1,
            ins.subcategoria AS subcategoria_1,
            ins.brand AS marca_1,
            rprod.contenido AS tamano,
            ins.sku_code AS sku_1,
            ins.brandsec AS marca_2,
            ins.categoriasec AS categoria_2,
            ins.sku_codesec AS sku_2,
            ins.cantidad_encontrada,
            ins.cantidad AS cantidad_armada,
            ins.foto_guia,
            ins.foto AS foto_armado
        FROM 
            insert_packs ins
            INNER JOIN repositorio_locales_dtt2 rpdv 
                ON ins.codigo = rpdv.pos_id
            LEFT JOIN repositorio_productos rprod 
                ON ins.sku_code = rprod.sku
            LEFT JOIN repositorio_supervisor rs 
                ON rpdv.supervisor = rs.id_supervisor
            INNER JOIN repositorio_usuario ru 
                ON ins.usuario = ru.user
            LEFT JOIN repositorio_distributivo rd 
                ON rd.mercaderista = ru.user
            INNER JOIN validacion_tacticos vt  
                ON vt.gestor = ru.user 
                AND vt.pdv = rpdv.pos_name 
                AND vt.tactico = ins.sku_codesec
                AND DATE(vt.fecha_creacion) BETWEEN 
                    DATE_FORMAT(CURDATE() - INTERVAL 3 MONTH, '%Y-%m-01')
                    AND CURDATE()
        WHERE 
            DATE(STR_TO_DATE(ins.fecha, '%d/%m/%Y')) BETWEEN 
                DATE_FORMAT(CURDATE() - INTERVAL 3 MONTH, '%Y-%m-01')
                AND CURDATE()
            AND ins.estado = 2
            AND ru.user NOT IN ('LUCKY UIO', 'LUCKY GYE', 'PRUEBA GYE')
        GROUP BY 
            ins.id_packs
        ORDER BY 
            DATE(STR_TO_DATE(ins.fecha, '%d/%m/%Y')) DESC
    ";

    $result = $database->select($query);

    if ($result) {
        $data = [];
        foreach ($result as $row) {
            $data[] = [
                "fecha"               => $row['fecha'],
                "codigo"              => $row['codigo'],
                "local"               => $row['local'],
                "region"              => $row['region'],
                "provincia"           => $row['provincia'],
                "ciudad"              => $row['ciudad'],
                "canal"               => $row['canal'],
                "jefatura"            => $row['jefatura'],
                "ejecutivo"           => $row['ejecutivo'],
                "supervisor"          => $row['supervisor'],
                "mercaderista"        => $row['mercaderista'],
                "categoria_1"         => $row['categoria_1'],
                "subcategoria_1"      => $row['subcategoria_1'],
                "marca_1"             => $row['marca_1'],
                "tamano"              => $row['tamano'],
                "sku_1"               => $row['sku_1'],
                "marca_2"             => $row['marca_2'],
                "categoria_2"         => $row['categoria_2'],
                "sku_2"               => $row['sku_2'],
                "cantidad_encontrada" => $row['cantidad_encontrada'],
                "cantidad_armada"     => $row['cantidad_armada'], 
                "foto_guia"           => $baseURL . $row['foto_guia'],
                "foto_armado"         => $baseURL . $row['foto_armado']
            ];
        }

        echo json_encode([
            "success" => true,
            "registros_encontrados" => count($data),
            "data" => $data
        ], JSON_PRETTY_PRINT);

    } else {
        echo json_encode([
            "success" => true,
            "registros_encontrados" => 0,
            "data" => []
        ], JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
