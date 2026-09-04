<?php
require_once '../../XploraEcuador/google-api-php-client--PHP8.2/vendor/autoload.php';
require_once __DIR__ . '/conexion/DataSource.php';

use Phppot\DataSource;

// Crear instancia de la conexión
$database = new DataSource();
$conn = $database->getConnection();

header("Content-Type: application/json");

// Leer el cuerpo de la solicitud
$input = json_decode(file_get_contents("php://input"), true);



// Datos para procesar
$idPacks = $input['id_packs'];
$gestor = $input['gestor'];
$pdv = $input['pdv'];
$tactico = $input['tactico'];
$observation = $input['observation']  ?? "";
$cantidadEncontrada = $input['cantidad_encontrada'] ?? 0;
$cantidadArmada = $input['cantidad_armada'] ?? 0;
$fotoArmado = $input['foto_armado'] ?? "";
$fotoGuia = $input['foto_guia'] ?? "";
$fechaCreacion = date('Y-m-d H:i:s');



// Función para obtener el token de acceso de Firebase
function getAccessToken()   
{
    $client = new Google\Client();
    $client->setAuthConfig('service-account.json');
    $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
    return $client->fetchAccessTokenWithAssertion()["access_token"];
}

function getToken($gestor,$conn)
{
    $tiktok ="";
    $query = "SELECT token FROM repositorio_usuario WHERE user=? ;"; 

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $gestor);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tiktok = $row["token"];
        }
     }
     return $tiktok;

}

// Función para enviar una notificación
function send_report($gestor, $conn, $pdv, $tactico, $observation, $fechaCreacion)
{
    $accessToken = getAccessToken();

     $token_device = getToken($gestor,$conn);

    $url = 'https://fcm.googleapis.com/v1/projects/jaboneriaapp/messages:send';
    $headers = [
        "Authorization: Bearer " . $accessToken,
        "content-type: application/json"
    ];

    $notification = [
        "title" => "Reporte de Táctico",
        "body" => "PDV: $pdv\nTáctico: $tactico\nMotivo: $observation\nFecha: $fechaCreacion"
    ];

    $data = [
        "key_id" => strval(round(microtime(true)))
    ];

    $fields = [
        "message" => [
            "token" => $token_device,
            "notification" => $notification,
            "data" => $data
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

    $result = curl_exec($ch);
    if ($result === false) {
        error_log('Error de cURL: ' . curl_error($ch));
    }
    curl_close($ch);
}


try {

    $updateQuery = "
        UPDATE insert_packs
        SET estado = 3, motivo_reporte = ?
        WHERE id_packs = ?
    ";

    $updateResult = $database->execute($updateQuery, "si", [$observation, $idPacks]);
   

    $insertQuery = "
        INSERT INTO reporte_validaciones (gestor, pdv, tactico, observacion, cantidad_encontrada, cantidad_armada, foto_armado, foto_guia, fecha_creacion)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";  

    $insertParams = [$gestor, $pdv, $tactico, $observation, $cantidadEncontrada, $cantidadArmada, $fotoArmado, $fotoGuia, $fechaCreacion];
    $insertResult = $database->execute($insertQuery, "sssssssss", $insertParams);

    // if (!$insertResult) {
    //     http_response_code(500);
    //     echo json_encode(["success" => false, "message" => "Error al registrar el reporte en reporte_validaciones."]);
    //     exit;
    // }

   
    send_report($gestor, $conn, $pdv, $tactico, $observation, $fechaCreacion);


   echo json_encode(["success" => true, "message" => "Reporte registrado y actualizado correctamente."]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error al procesar la solicitud", "details" => $e->getMessage()]);
}
