<?php
/**
 * Insertar una nueva meta en la base de datos
 */

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);
	
    $user = $body['user'];
    $device_id = $body['device_id'];

    $existe = FuncionesSamsung::existeDeviceId($device_id);

    if ($existe) {
        print json_encode(
            array(
                'estado' => '3',
                'mensaje' => 'Ya está registrado')
        );
    } else {
        //Update
        $retorno = FuncionesSamsung::updateDeviceId($user, $device_id);

        if ($retorno) {
            // Código de éxito
            print json_encode(
                array(
                    'estado' => '1',
                    'mensaje' => 'Actualización exitosa')
            );
        } else {
            // Código de falla
            print json_encode(
                array(
                    'estado' => '2',
                    'mensaje' => 'Actualización fallida')
            );
        }
    }
    
}

?>