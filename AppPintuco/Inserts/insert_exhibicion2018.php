<?php
/**
 * Insertar una nueva meta en la base de datos
 */

require '../Data/Funciones.php';
require 'upload_azure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);
       
		$pharma_id = $body['id'];
        $pos_id = $body['pos_id'];
        $usuario = $body['user'];
        $supervisor = $body['supervisor'];
        $fecha = $body['fecha'];
        $hora = $body['hora'];
        $sector = $body['sector'];
        $categoria = $body['categoria'];
		$subcategoria = $body['subcategoria'];
		$segmento = $body['segmento'];
        $brand = $body['brand'];
        $tipo_exh = $body['tipo_exh'];
        $zona_exh = $body['zona_exh'];
        $contratada = $body['contratada'];
        $condicion  = $body['condicion'];
        $foto = $body['foto'];
    	 
    	$fecha_numeros = str_replace('/','', $fecha);
		$hora_numeros = str_replace(':','', $hora);
		
		$unique = $fecha_numeros . $hora_numeros;

	// $idphoto = FuncionesSamsung::getIdExhibicion();

    // if ($idphoto){
    //     foreach ($idphoto as $row => $link) {
    //         $id = $link['id_exhibiciones'];
    //     }
	// 	$name = "$unique$usuario$pos_id";
	// 	$name_final = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
	// 	$photo_name = str_replace(' ','', $name_final);
	// 	$path = "Exhibicion/$photo_name.png";
    // }else{
	    $name = "$unique$usuario$pos_id";
		$name_final = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
     	$photo_name = str_replace(' ','', $name_final);
		$path = "Exhibicion/$photo_name.png";
    // }

     	
    // Insertar meta
    $retorno = FuncionesSamsung::insertExhibicion2018
        ($pharma_id,
		$pos_id,
		$usuario,
		$supervisor,
		$fecha,
		$hora,
		$sector,
		$categoria,
		$subcategoria,
		$segmento,
		$brand,
		$tipo_exh,
		$zona_exh,
		$contratada,
		$condicion,
		$path);
	
	
    if ($retorno) {
 	    // file_put_contents($path,base64_decode($foto));
        $container = 'app/AppAlicorp/Inserts/Exhibicion';
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