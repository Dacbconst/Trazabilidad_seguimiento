<?php
/**
 * Insertar una nueva meta en la base de datos
 */

require '../Data/Funciones.php';
require 'upload_azure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);
		$pos_id = $body['pos_id'];
        $user = $body['user'];
        $supervisor = $body['supervisor'];
        $fecha = $body['fecha'];
        $hora = $body['hora'];
        $channel = $body['channel'];
		$codigo_pdv = $body['codigo_pdv'];
		$mercaderista = $body['mercaderista'];
		$tareas = $body['tareas'];
        $realizado = $body['realizado'];
        $image = $body['foto'];
		
		if($image!='NO_FOTO'){
			$fecha_numeros = str_replace('/','', $fecha);
			$hora_numeros = str_replace(':','', $hora);
			
			$unique = $fecha_numeros . $hora_numeros;
		
			$name = "$unique$user$pos_id$channel$realizado";
			$full_name = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
			$photo_name = str_replace(' ','', $full_name);
			$path = "EvidenciasFotograficas/$photo_name.png";
		} else {
			$path = "EvidenciasFotograficas/NO_FOTO.png";
		}
    
     	
    // Insertar meta
    $retorno = FuncionesSamsung::insertEvidenciaFotografica (
	    $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $channel,
		$codigo_pdv,
		$mercaderista,
		$tareas,
        $realizado,
        $path
    );

    if ($retorno) {
        if ($image!='NO_FOTO') {
			// file_put_contents($path,base64_decode($image));
			$container = 'app/AppAlicorp/Inserts/EvidenciasFotograficas';
			uploadBlobSample($blobClient, $container, $image, $photo_name.'.png');
		}
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