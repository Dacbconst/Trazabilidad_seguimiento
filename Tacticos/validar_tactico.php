<?php
require_once __DIR__ . '/conexion/DataSource.php';

use Phppot\DataSource;

// Crear instancia de la conexión
$database = new DataSource();
$conn = $database->getConnection();

header("Content-Type: application/json");

// Leer el cuerpo de la solicitud
$input = json_decode(file_get_contents("php://input"), true);

// Validar los datos recibidos
if (!isset($input['gestor'], $input['pdv'], $input['tactico'], $input['cantidad_armada'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Datos incompletos"]);
    exit;
}

// Datos para procesar
$idPacks = $input['id_packs'];
$gestor = $input['gestor'];
$pdv = $input['pdv'];
$tactico = $input['tactico'];
$cantidadArmada = $input['cantidad_armada'];
$fechaCreacion = date("Y-m-d H:i:s");

// Función para extraer mes, año y tipo
function extraerInfoDesdeSku($sku) {
    $tipo = stripos($sku, 'ADIC') !== false ? 'Adicional' : 'Actual';

    $meses = [
        'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4, 'MAYO' => 5, 'JUNIO' => 6,
        'JULIO' => 7, 'AGOSTO' => 8, 'SEPTIEMBRE' => 9, 'OCTUBRE' => 10, 'NOVIEMBRE' => 11, 'DICIEMBRE' => 12
    ];

    if (preg_match('/\((.*?)\)/', $sku, $matches)) {
        $contenido = strtoupper(trim($matches[1]));

        foreach ($meses as $mesTexto => $numeroMes) {
            if (strpos($contenido, $mesTexto) !== false) {
                // Buscar si hay un año presente
                if (preg_match('/\d{2}/', $contenido, $anioMatch)) {
                    $anio = intval('20' . $anioMatch[0]);
                } else {
                    $anio = intval(date('Y')); // Año actual si no hay número
                }

                return [
                    'mes' => $numeroMes,
                    'anio' => $anio,
                    'tipo' => $tipo
                ];
            }
        }
    }

    return [
        'mes' => null,
        'anio' => null,
        'tipo' => $tipo
    ];
}


try {
    // Iniciar una transacción
    $conn->begin_transaction();

    // Extraer info del nombre del táctico
    $info = extraerInfoDesdeSku($tactico);
    $mesReporte = $info['mes'];
    $anioReporte = $info['anio'];
    $tipoTactico = $info['tipo'];

    // Consulta SQL para insertar en validacion_tacticos
    $insertQuery = "
        INSERT INTO validacion_tacticos (id_onpaks,gestor, pdv, tactico, cantidad_armada, fecha_creacion, mes_reporte, anio_reporte, tipo_tactico)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $insertParams = [$idPacks, $gestor, $pdv, $tactico, $cantidadArmada, $fechaCreacion, $mesReporte, $anioReporte, $tipoTactico];
    $insertResult = $database->execute($insertQuery, "isssissis", $insertParams);

    if ($insertResult === false) {
        throw new Exception("Error al insertar en validacion_tacticos: " . $database->getLastError());
    }

    // Consulta SQL para actualizar el estado del táctico en su tabla correspondiente
    $updateQuery = "
        UPDATE insert_packs
        SET estado = 2 
        WHERE id_packs = ?
    ";

    $updateParams = [$idPacks];
    $updateResult = $database->execute($updateQuery, "i", $updateParams);

    if ($updateResult === false) {
        throw new Exception("Error al actualizar el estado en insert_packs: " . $database->getLastError());
    }

    // Confirmar la transacción
    $conn->commit();

    echo json_encode(["success" => true, "message" => "Validación exitosa y estado actualizado"]);
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    $conn->rollback();

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al procesar la solicitud",
        "details" => $e->getMessage()
    ]);
}
