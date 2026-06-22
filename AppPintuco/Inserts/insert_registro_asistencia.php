<?php
/**
 * Insertar rastreo coordenadas base de datos
 */

require '../Data/Funciones.php';
require 'upload_azure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);

    $id_pdv = $body['id_pdv'];
    $usuario = $body['usuario'];
    $foto = $body['foto'];
    $version = $body['version'];
    $latitude = $body['latitude'];
    $longitude = $body['longitude'];
    $fecha = $body['fecha'];
    $hora = $body['hora'];
    $distancia = $body['distancia'];
    $pos_name = $body['pos_name'];
    $bateria = $body['bateria'];
    $estado_asistencia = $body['estado_asistencia'];
    $idRemotaRuta = $body['idRemotaRuta'];
    $supervisor = $body['supervisor'];

    $fecha_numeros = str_replace('/','', $fecha);
    $hora_numeros = str_replace(':','', $hora);
    
    $unique = $fecha_numeros . $hora_numeros;

    $name = "$unique$usuario$id_pdv";
    $full_name = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
    $photo_name = str_replace(' ','', $full_name);
    $path = "Asistencia/$photo_name.png";

    // Insertar meta
    $retorno = FuncionesSamsung::insertRegistroAsistencia(
        $id_pdv, 
        $usuario, 
        $version, 
        $path, 
        $latitude, 
        $longitude, 
        $fecha, 
        $hora, 
        $distancia, 
        $pos_name, 
        $bateria, 
        $estado_asistencia,
        $supervisor
    );

    if ($retorno) {
        $container = 'app/AppAlicorp/Inserts/Asistencia';
        uploadBlobSample($blobClient, $container, $foto, $photo_name.'.png');
        
        // Código de éxito
        print json_encode(
            array(
                'estado' => '1',
                'mensaje' => 'Creación exitosa',
                'ultimoId' => $retorno)
        );
    } else {
        // Código de falla
        print json_encode(
            array(
                'estado' => '2',
                'mensaje' => 'Creación fallida')
        );
    }
}


?>