<?php
/**
 * Insertar exhibition en la base de datos
 */

require '../Data/Funciones.php';
require 'upload_azure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);
	
	$pharma_id = $body['id'];
	$pos_id = $body['pos_id'];
    $usuario = $body['usuario'];
    $categoria = $body['categoria'];
    $subcategoria = $body['subcategoria'];
    $marca  = $body['marca'];
    $logro = $body['logro'];
	$image = $body['photo'];
    $fecha = $body['fecha'];
    $hora = $body['hora'];
	
	$fecha_numeros = str_replace('/','', $fecha);
	$hora_numeros = str_replace(':','', $hora);
	
	$unique = $fecha_numeros . $hora_numeros;
		
	// $idphoto = FuncionesSamsung::getIdExhibidor();
	
	// if ($idphoto){
    //     foreach ($idphoto as $row => $link) {
    //         $id = $link['id_fotografico'];
    //     }
	// 	$name = "$unique$usuario$pharma_id$pos_id$categoria$subcategoria$marca";
	// 	$name_final = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
	// 	$photo_name = str_replace(' ','', $name_final);
	// 	$path = "ImgExhibidor/$photo_name.png"; 
    // }else{
	    $name = "$unique$usuario$pharma_id$pos_id$categoria$subcategoria$marca";
		$name_final = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
     	$photo_name = str_replace(' ','', $name_final);
		$path = "ImgExhibidor/$photo_name.png"; 
    // }
	
	// Insertar meta
    $retorno = FuncionesSamsung::insertFotografico(
		$pharma_id,
		$pos_id,
        $usuario,
        $categoria,
        $subcategoria,
        $marca,
        $logro,
		$path,
		$fecha,
        $hora
	    );
		

    if ($retorno) {
 	    // file_put_contents($path,base64_decode($image));
        $container = 'app/AppAlicorp/Inserts/ImgExhibidor';
        uploadBlobSample($blobClient, $container, $image, $photo_name.'.png');

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