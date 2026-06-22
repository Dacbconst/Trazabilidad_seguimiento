<?php
/**
 * Insertar una nueva meta en la base de datos
 */

require '../Data/Funciones.php';
require 'upload_azure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);
		$pharma_id = $body['pharma_id'];
        $codigo = $body['codigo'];
        $usuario = $body['usuario'];
        $supervisor = $body['supervisor'];
        $fecha = $body['fecha'];
        $hora = $body['hora'];
        $categoria = $body['categoria'];
		$producto = $body['producto'];
		$promocional = $body['promocional'];
		$mecanica = $body['mecanica'];
        $peso = $body['peso'];
        $cantidad = $body['cantidad'];
        $fecha_rot = $body['fecha_rot'];
		$image = $body['foto_guia'];
		$observaciones = $body['observaciones'];
		
		if($image!='NO_FOTO'){
			$fecha_numeros = str_replace('/','', $fecha);
			$hora_numeros = str_replace(':','', $hora);
			
			$unique = $fecha_numeros . $hora_numeros;
		
			$name = "$unique$usuario$codigo$categoria";
			$full_name = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
			$photo_name = str_replace(' ','', $full_name);
			$path = "Rotacion/$photo_name.png";
		} else {
			$path = "Rotacion/NO_FOTO.png";
		}
    
     	
    // Insertar meta
    $retorno = FuncionesSamsung::insertRotacion (
	    $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $categoria,
		$producto,
		$promocional,
		$mecanica,
        $peso,
        $cantidad,
        $fecha_rot,
        $path,
        $observaciones
    );

    if ($retorno) {
        if ($image!='NO_FOTO') {
			// file_put_contents($path,base64_decode($image));
			$container = 'app/AppAlicorp/Inserts/Rotacion';
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