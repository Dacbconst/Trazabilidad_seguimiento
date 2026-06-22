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
    $tipo = $body['tipo'];
    $version = $body['version'];
    $causal = $body['causal'];
    $foto = $body['foto'];
    $latitude = $body['latitude'];
    $longitude = $body['longitude'];
    $fecha = $body['fecha'];
    $hora = $body['hora'];
    $distancia = $body['distancia'];
    $tipo_relevo = $body['tipo_relevo'];
    $idRemotaRuta = $body['idRemotaRuta'];

    $fecha_numeros = str_replace('/','', $fecha);
    $hora_numeros = str_replace(':','', $hora);
    
    $unique = $fecha_numeros . $hora_numeros;

    $name = "$unique$usuario$id_pdv";
    $full_name = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
    $photo_name = str_replace(' ','', $full_name);
    $path = "Jornada/$photo_name.png";
    // echo "sii";
    // Insertar meta
    $retorno = FuncionesSamsung::insertRegistro05_2022(
        $id_pdv,
        $idRemotaRuta,
        $usuario,
		$tipo,
		$version,
		$causal,
		$path,
        $latitude,
        $longitude,
        $fecha,
        $hora);
        
    if ($retorno) {
        if ($tipo == 'JUSTIFICACION') {
            $newId = FuncionesSamsung::codAleatorio();
            $id_sustento = FuncionesSamsung::insertSustento($newId, $idRemotaRuta, $fecha, $causal, $path);
        }



    

        FuncionesSamsung::updateRuta($idRemotaRuta, $tipo, $hora, $distancia, $tipo_relevo, $id_sustento, $causal);
        
        $container = 'app/AppPintuco/Inserts/Jornada';
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