<?php

#region 13/02/2026 RESPALDO ANTES DE ULTIMO RELEVO (CAMBIO DE DIA ACTUAL A ULTIMO RELEVO)
// header('Content-Type: application/json');
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: POST, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type, Accept, User-Agent');

// require_once '../Data/Funciones.php';

// if ($_SERVER['REQUEST_METHOD'] != 'POST') {
//     http_response_code(405);
//     echo json_encode(array(
//         'estado' => '0',
//         'mensaje' => 'Método no permitido. Solo POST.',
//         'method_received' => $_SERVER['REQUEST_METHOD']
//     ));
//     exit;
// }


// $input = file_get_contents('php://input');

// $body = json_decode($input, true);

// if (json_last_error() !== JSON_ERROR_NONE) {
//     echo json_encode(array(
//         'estado' => '0',
//         'mensaje' => 'Error en formato JSON: ' . json_last_error_msg()
//     ));
//     exit;
// }

// $required = ['usuario','categoria', 'codigo_pdv', 'fecha'];
// foreach ($required as $param) {
//     if (!isset($body[$param]) || empty(trim($body[$param]))) {
//         echo json_encode(array(
//             'estado' => '0',
//             'mensaje' => "Parámetro requerido faltante: $param"
//         ));
//         exit;
//     }
// }


// $usuario = trim($body['usuario']);
// $categoria = trim($body['categoria']);
// $codigo_pdv = trim($body['codigo_pdv']);
// $fecha = trim($body['fecha']);
// $tipo_logistico = isset($body['tipo_logistico']) ? trim($body['tipo_logistico']) : '';


// $registros = FuncionesSamsung::getUltimoRelevoLogistico($usuario, $categoria, $codigo_pdv, $fecha, $tipo_logistico);


// if ($registros === false) {
    
//     echo json_encode(array(
//         'estado' => '0',
//         'mensaje' => 'Error en la consulta a la base de datos'
//     ));
// } elseif (empty($registros)) {
    
//     echo json_encode(array(
//         'estado' => '2',
//         'mensaje' => 'No hay registros del día anterior',
//         'registros' => array()  
//     ));
// } else {
    
//     echo json_encode(array(
//         'estado' => '1',
//         'mensaje' => 'Registros encontrados: ' . count($registros),
//         'registros' => $registros,  
//         'total' => count($registros)
//     ));
// }
// exit;
#endregion

// header('Content-Type: application/json');
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: POST, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type, Accept, User-Agent');

// require_once '../Data/Funciones.php';

// if ($_SERVER['REQUEST_METHOD'] != 'POST') {
//     http_response_code(405);
//     echo json_encode(array('estado' => '0', 'mensaje' => 'Solo POST'));
//     exit;
// }

// $input = file_get_contents('php://input');
// $body = json_decode($input, true);

// if (json_last_error() !== JSON_ERROR_NONE) {
//     echo json_encode(array('estado' => '0', 'mensaje' => 'JSON inválido'));
//     exit;
// }

// // ===== CASO 1: SOLO QUIERE LA FECHA DEL ÚLTIMO RELEVO =====
// if (isset($body['solo_fecha']) && $body['solo_fecha'] == '1') {
//     $usuario = trim($body['usuario']);
//     $codigo_pdv = trim($body['codigo_pdv']);
//     $tipo_logistico = isset($body['tipo_logistico']) ? trim($body['tipo_logistico']) : '';
    
//     $fecha = FuncionesSamsung::getFechaUltimoRelevo($usuario, $codigo_pdv, $tipo_logistico);
    
//     if ($fecha) {
//         echo json_encode(array(
//             'estado' => '1',
//             'fecha_ultimo_relevo' => $fecha,
//             'mensaje' => 'Fecha encontrada'
//         ));
//     } else {
//         echo json_encode(array(
//             'estado' => '2',
//             'mensaje' => 'No hay relevos previos'
//         ));
//     }
//     exit;
// }

// // ===== CASO 2: CONSULTA NORMAL CON CATEGORÍA Y FECHA =====
// $required = ['usuario', 'categoria', 'codigo_pdv'/*, 'fecha'*/];
// foreach ($required as $param) {
//     if (!isset($body[$param]) || empty(trim($body[$param]))) {
//         echo json_encode(array('estado' => '0', 'mensaje' => "Falta: $param"));
//         exit;
//     }
// }

// $usuario = trim($body['usuario']);
// $categoria = trim($body['categoria']);
// $codigo_pdv = trim($body['codigo_pdv']);
// /*$fecha = trim($body['fecha']);*/
// $tipo_logistico = isset($body['tipo_logistico']) ? trim($body['tipo_logistico']) : '';

// $ultimaFecha = FuncionesSamsung::getFechaUltimoRelevo($usuario, $codigo_pdv, $tipo_logistico);

// if (!$ultimaFecha) {
//     // No hay registros anteriores a hoy
//     echo json_encode(array(
//         'estado' => '2', 
//         'mensaje' => 'No hay relevos previos para esta categoría',
//         'registros' => array()
//     ));
//     exit;
// }


// // $registros = FuncionesSamsung::getRegistrosPorFecha($usuario, $categoria, $codigo_pdv, $ultimaFecha, $tipo_logistico);

// // if ($registros === false) {
// //     echo json_encode(array('estado' => '0', 'mensaje' => 'Error en BD'));
// // } elseif (empty($registros)) {
// //     echo json_encode(array('estado' => '2', 'mensaje' => 'No hay registros', 'registros' => array()));
// // } else {
// //     echo json_encode(array(
// //         'estado' => '1',
// //         'mensaje' => 'Registros: ' . count($registros),
// //         'registros' => $registros,
// //         'total' => count($registros),
// //         'fecha_relevo' => $ultimaFecha
// //     ));
// // }
// // exit;

// $registrosAnteriores = FuncionesSamsung::getRegistrosPorFecha($usuario, $categoria, $codigo_pdv, $ultimaFecha, $tipo_logistico);

// // Obtener registros de HOY (23/02/2026)
// $hoy = date('d/m/Y');
// $registrosHoy = FuncionesSamsung::getRegistrosPorFecha($usuario, $categoria, $codigo_pdv, $hoy, $tipo_logistico);

// // Crear un mapa de SKU -> cantidad para los registros de hoy
// $cantidadesHoy = array();
// if ($registrosHoy && !empty($registrosHoy)) {
//     foreach ($registrosHoy as $reg) {
//         $cantidadesHoy[$reg['sku_code']] = $reg['regular_price'];
//     }
// }

// // Combinar la información
// $resultado = array();
// if ($registrosAnteriores && !empty($registrosAnteriores)) {
//     foreach ($registrosAnteriores as $registro) {
//         $sku = $registro['sku_code'];
//         $registro['cantidad_anterior'] = $registro['regular_price']; // Cantidad del día anterior
//         $registro['cantidad_actual'] = isset($cantidadesHoy[$sku]) ? $cantidadesHoy[$sku] : '0'; // Cantidad de hoy (0 si no hay)
//         $resultado[] = $registro;
//     }
// }

// if (empty($resultado)) {
//     echo json_encode(array(
//         'estado' => '2', 
//         'mensaje' => 'No hay registros para esta categoría',
//         'registros' => array()
//     ));
// } else {
//     echo json_encode(array(
//         'estado' => '1',
//         'mensaje' => 'Registros: ' . count($resultado),
//         'registros' => $resultado,
//         'total' => count($resultado),
//         'fecha_relevo' => $ultimaFecha,
//         'fecha_hoy' => $hoy
//     ));
// }


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, User-Agent');

require_once '../Data/Funciones.php'; 

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(array('estado' => '0', 'mensaje' => 'Solo POST'));
    exit;
}

$input = file_get_contents('php://input');
$body = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(array('estado' => '0', 'mensaje' => 'JSON inválido'));
    exit;
}

// ===== CASO 1: SOLO QUIERE LA FECHA DEL ÚLTIMO RELEVO =====
if (isset($body['solo_fecha']) && $body['solo_fecha'] == '1') {
    $usuario = trim($body['usuario']);
    $codigo_pdv = trim($body['codigo_pdv']);
    $tipo_logistico = isset($body['tipo_logistico']) ? trim($body['tipo_logistico']) : '';
    
    $fecha = FuncionesSamsung::getFechaUltimoRelevo($usuario, $codigo_pdv, $tipo_logistico);
    
    if ($fecha) {
        echo json_encode(array(
            'estado' => '1',
            'fecha_ultimo_relevo' => $fecha,
            'mensaje' => 'Fecha encontrada'
        ));
    } else {
        echo json_encode(array(
            'estado' => '2',
            'mensaje' => 'No hay relevos previos'
        ));
    }
    exit;
}

// ===== CASO 2: CONSULTA NORMAL CON CATEGORÍA =====
$required = ['usuario', 'categoria', 'codigo_pdv'];
foreach ($required as $param) {
    if (!isset($body[$param]) || empty(trim($body[$param]))) {
        echo json_encode(array('estado' => '0', 'mensaje' => "Falta: $param"));
        exit;
    }
}

$usuario = trim($body['usuario']);
$categoria = trim($body['categoria']);
$codigo_pdv = trim($body['codigo_pdv']);
$tipo_logistico = isset($body['tipo_logistico']) ? trim($body['tipo_logistico']) : '';

// Obtener la última fecha de relevo ANTERIOR a hoy
$ultimaFecha = FuncionesSamsung::getFechaUltimoRelevo($usuario, $codigo_pdv, $tipo_logistico);

if (!$ultimaFecha) {
    echo json_encode(array(
        'estado' => '2', 
        'mensaje' => 'No hay relevos previos'
    ));
    exit;
}

// Obtener registros de la ÚLTIMA FECHA (anterior a hoy)
$registrosAnteriores = FuncionesSamsung::getRegistrosPorFecha($usuario, $categoria, $codigo_pdv, $ultimaFecha, $tipo_logistico);

// Obtener registros de HOY para comparar
$hoy = date('d/m/Y');
$registrosHoy = FuncionesSamsung::getRegistrosPorFecha($usuario, $categoria, $codigo_pdv, $hoy, $tipo_logistico);

// Crear mapa de cantidades de hoy
$cantidadesHoy = array();
if ($registrosHoy && !empty($registrosHoy)) {
    foreach ($registrosHoy as $reg) {
        $cantidadesHoy[$reg['sku_code']] = $reg['regular_price'];
    }
}

// Combinar la información
$resultado = array();
if ($registrosAnteriores && !empty($registrosAnteriores)) {
    foreach ($registrosAnteriores as $registro) {
        $sku = $registro['sku_code'];
        $registro['cantidad_anterior'] = $registro['regular_price']; // Cantidad del último relevo
        $registro['cantidad_actual'] = isset($cantidadesHoy[$sku]) ? $cantidadesHoy[$sku] : '0'; // Cantidad de hoy (0 si no hay)
        $resultado[] = $registro;
    }
}

if (empty($resultado)) {
    echo json_encode(array(
        'estado' => '2', 
        'mensaje' => 'No hay registros para esta categoría',
        'registros' => array()
    ));
} else {
    echo json_encode(array(
        'estado' => '1',
        'mensaje' => 'Registros: ' . count($resultado),
        'registros' => $resultado,
        'fecha_relevo' => $ultimaFecha,
        'fecha_hoy' => $hoy
    ));
}
exit;

?>