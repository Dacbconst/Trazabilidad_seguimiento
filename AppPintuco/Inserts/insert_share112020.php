<?php
/**
 * Insertar rastreo coordenadas base de datos
 */

require '../Data/Funciones.php';
require 'upload_azure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);
	$id = $body['id'];
	$pos_id = $body['pos_id'];
	$user = $body['user'];
	$supervisor = $body['supervisor'];
	$fecha = $body['fecha'];
	$hora = $body['hora'];
	$sector = $body['sector'];
	$categoria = $body['categoria'];
	$segmento = $body['segmento'];
	$marca_seleccionada = $body['marca_seleccionada'];
	$brand = $body['brand'];
	$ctms_percha = $body['ctms_percha'];
	$ctms_marca = $body['ctms_marca'];
	$otros = $body['otros'];
	$manufacturer = $body['manufacturer'];
	$image = $body['image'];
	
	$fecha_numeros = str_replace('/','', $fecha);
	$hora_numeros = str_replace(':','', $hora);
	
	$unique = $fecha_numeros . $hora_numeros;

    $path = "Share/NO_FOTO.png";
	
    if($image!='NO_FOTO'){
        $name = "$unique$user$pos_id$brand";
        $full_name = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
        $photo_name = str_replace(' ','', $full_name);
        $path = "Share/$photo_name.png";
    }
	
    // Insertar meta
    $retorno = FuncionesSamsung::insertShare112020(
	    $id,
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
		$segmento,
        $marca_seleccionada,
        $brand,
        $ctms_percha,
        $ctms_marca,
        $otros,
		$manufacturer,
		$path
		);

    if ($retorno) {
        if($image!='NO_FOTO'){
            // file_put_contents($path,base64_decode($image));
            $container = 'app/AppAlicorp/Inserts/Share';
            uploadBlobSample($blobClient, $container, $image, $photo_name.'.png');
        }
            
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