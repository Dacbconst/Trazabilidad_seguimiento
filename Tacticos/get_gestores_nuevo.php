<?php
require_once __DIR__ . '/conexion/DataSource.php';

use Phppot\DataSource;

function utf8_encode_deep(&$input) {
    if (is_string($input)) {
        $input = mb_convert_encoding($input, 'UTF-8', 'UTF-8');
    } else if (is_array($input)) {
        foreach ($input as &$value) {
            utf8_encode_deep($value);
        }
        unset($value);
    } else if (is_object($input)) {
        $vars = array_keys(get_object_vars($input));
        foreach ($vars as $var) {
            utf8_encode_deep($input->$var);
        }
    }
}

$database = new DataSource();
$conn = $database->getConnection();

header("Content-Type: application/json");

try {
    $baseURL = "https://luckyecuadorweb.blob.core.windows.net/app/AppJaboneriaWilson/Inserts/";
    $es_adicional = isset($_GET['es_adicional']) && $_GET['es_adicional'] === 'true';
    $fecha = isset($_GET['fecha']) ? $_GET['fecha'] : null;

    if ($fecha) {
        $fechaDMY = date('d/m/Y', strtotime($fecha));
        $whereFecha = "ins.fecha = ?";
        $params = [$fechaDMY];
        $types = "s";
    } else {
        $whereFecha = "STR_TO_DATE(ins.fecha, '%d/%m/%Y') = DATE_SUB(CURDATE(), INTERVAL 13 DAY)";
        $params = [];
        $types = "";
        $fechaDMY = date('d/m/Y', strtotime('-13 days'));
    }

    $query = "
        SELECT DISTINCT
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
            ins.fecha AS fecha_original,
            STR_TO_DATE(ins.fecha, '%d/%m/%Y') AS fecha_registro
        FROM insert_packs ins
        INNER JOIN repositorio_usuario ru 
            ON ins.usuario = ru.user
        LEFT JOIN repositorio_productos_onpacks rpo
            ON ins.sku_codesec = rpo.sku
        WHERE $whereFecha
          AND ru.user NOT IN ('LUCKY UIO', 'LUCKY GYE', 'PRUEBA GYE')
          AND (
              " . ($es_adicional ? "rpo.historico = 'Adicional'" : "(rpo.historico != 'Adicional' OR rpo.historico IS NULL)") . "
              AND rpo.activar = 'SI'
          )
        ORDER BY 
            ins.usuario, ins.pos_name, ins.fecha, ins.sku_codesec;
    ";

    $result = $database->select($query, $params, $types);
    
    if ($result === false || $result === null) {
        $result = [];
    }

    // Filtrar registros por fecha
    if ($fecha) {
        $result_filtrado = [];
        
        foreach ($result as $row) {
            if (!isset($row['fecha_registro']) || $row['fecha_registro'] === null || $row['fecha_registro'] === '') {
                continue;
            }
            
            $timestamp = @strtotime($row['fecha_registro']);
            if ($timestamp === false) {
                continue;
            }
            
            $fechaRegistro = date('d/m/Y', $timestamp);
            
            if ($fechaRegistro === $fechaDMY) {
                $result_filtrado[] = $row;
            }
        }
        
        $result = $result_filtrado;
    }

    // Si no hay resultados
    if (empty($result)) {
        echo json_encode([
            'gestores' => [],
            'debug' => [
                'total_rows_query' => 0,
                'fecha_buscada' => $fechaDMY,
                'es_adicional' => $es_adicional,
                'mensaje' => 'No se encontraron registros para esta fecha'
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Construir estructura de gestores
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

    $jsonData = [
        'gestores' => array_values($gestores),
        'debug' => [
            'total_rows_query' => count($result),
            'fecha_buscada' => $fechaDMY,
            'es_adicional' => $es_adicional
        ]
    ];

    // Limpiar caracteres mal codificados
    utf8_encode_deep($jsonData);

    echo json_encode($jsonData, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Error al procesar la solicitud",
        "details" => $e->getMessage(),
        "line" => $e->getLine()
    ], JSON_PRETTY_PRINT);
}