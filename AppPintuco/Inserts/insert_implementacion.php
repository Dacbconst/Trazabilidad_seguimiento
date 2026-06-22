<?php
/**
 * Insertar una nueva meta en la base de datos
 */

require '../Data/Funciones.php';
require 'upload_azure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);
       
		$usuario = $body['user'];
        $fecha = $body['fecha'];
        $hora = $body['hora'];
        $ciudad = $body['city'];
        $canal = $body['canal'];
        $cliente = $body['cliente'];
        $formato = $body['subchannel'];
        $zona = $body['zone'];
        $pdv = $body['pdv'];
        $direccion = $body['address'];
		$local = $body['local'];
        $latitud = $body['latitud'];
        $longitud = $body['longitud'];
        $foto = $body['foto'];
    	 
    	$fecha_numeros = str_replace('/','', $fecha);
		$hora_numeros = str_replace(':','', $hora);
		
		$unique = $fecha_numeros . $hora_numeros;

	$idphoto = FuncionesSamsung::getIdImplementacion();


	if ($idphoto){
        foreach ($idphoto as $row => $link) {
            $id = $link['id_nuevo'];
        }
		$name = "$unique$ciudad$canal$pdv";
		$name_final = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
		$photo_name = str_replace(' ','', $name_final);
		$path = "Implementacion/$photo_name.png"; 
    }else{
	    $name = "$unique$ciudad$canal$pdv";
		$name_final = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
     	$photo_name = str_replace(' ','', $name_final);
		$path = "Implementacion/$photo_name.png"; 
    }

     	
    // Insertar meta
    $retorno = FuncionesSamsung::insertImplementacion
	($usuario, $fecha, $hora, $ciudad, $canal, $cliente, $formato, $zona, $pdv, $direccion,$local,
        $latitud,
        $longitud,
        $path);

    if ($retorno) {
 	    // file_put_contents($path,base64_decode($foto));

        $container = 'app/AppPintuco/Inserts/Implementacion';
        uploadBlobSample($blobClient, $container, $foto, $photo_name.'.png');

        // Código de éxito
        print json_encode(
            array(
                'estado' => '1',
                'mensaje' => 'Creación exitosa',
                //'idPdvFoto' => $retorno)
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