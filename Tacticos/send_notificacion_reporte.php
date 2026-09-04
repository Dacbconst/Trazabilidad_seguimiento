<?php

// Include Composer dependencies
require_once '../../XploraEcuador/google-api-php-client--PHP8.2/vendor/autoload.php';
require_once __DIR__ . '/conexion/DataSource.php';
use Phppot\DataSource;

// Crear instancia de la conexión
$database = new DataSource();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato JSON
    $body = json_decode(file_get_contents("php://input"), true);

    $gestor = $body['gestor'];
    // $pdv = $body['pdv'];
    // $tactico = $body['tactico'];
    // $motivo = $body['observation'];
    // $fecha = date("Y-m-d H:i:s"); 

    // Autenticación con Firebase
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

   
    
    // Tiempo en ms para identificar notificación

    $key_id = strval(round(microtime(true)));

    function send_report($gestor,$conn)
    {
        $accessToken = getAccessToken();

        $token_device = getToken($gestor,$conn);

        $url = 'https://fcm.googleapis.com/v1/projects/tu-proyecto/messages:send';
        $headers = [
            "Authorization: Bearer " . $accessToken,
            "content-type: application/json"
        ];

        // Configuración de la notificación
        $notification = [];
        $notification["title"] = "Reporte de Táctico";
        $notification["body"] = "PDV: $pdv\nTáctico: $tactico\nMotivo: $motivo\nFecha: $fecha";

        // Datos adicionales para la aplicación
        $data = [];
        $data["key_id"] = $key_id;
    }

    if ($result !== false) {
        $token_device = $result[0]['Token'];

        // Obtenemos el token de acceso para Firebase
        $accessToken = getAccessToken();

        // URL de la API de Firebase Messaging
        $url = 'https://fcm.googleapis.com/v1/projects/tu-proyecto/messages:send';
        $headers = [
            "Authorization: Bearer " . $accessToken,
            "content-type: application/json"
        ];

        // Configuración de la notificación
        $notification = [];
        $notification["title"] = "Reporte de Táctico";
        $notification["body"] = "PDV: $pdv\nTáctico: $tactico\nMotivo: $motivo\nFecha: $fecha";

        // Datos adicionales para la aplicación
        $data = [];
        $data["key_id"] = $key_id;
        $data["pdv"] = $pdv;
        $data["tactico"] = $tactico;
        $data["motivo"] = $motivo;
        $data["fecha"] = $fecha;

        // Cuerpo de la notificación
        $fields["message"] = [
            "token" => $token_device,
            "notification" => $notification,
            "data" => $data
        ];

        // Enviar notificación
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        $result = curl_exec($ch);
        if ($result === false) {
            // Manejar errores de cURL
            error_log('Error de cURL: ' . curl_error($ch));
        }
        curl_close($ch);

        // Devolver respuesta
        if ($result) {
            echo json_encode([
                'estado' => '1',
                'mensaje' => 'Notificación enviada exitosamente',
                'firebase_response' => $result
            ]);
        } else {
            echo json_encode([
                'estado' => '2',
                'mensaje' => 'Hubo un error al enviar la notificación'
            ]);
        }
    } else {
        // Token no encontrado
        echo json_encode([
            'estado' => '3',
            'mensaje' => 'No se encontró el token del dispositivo para el gestor'
        ]);
    }
}
?>
