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
        $categoria = $body['categoria'];
		$subcategoria = $body['subcategoria'];
		$presentacion = $body['presentacion'];
        $brand = $body['brand'];
        $sku_code = $body['sku_code'];
        $observacion = $body['observacion'];
        $manufacturer = $body['manufacturer'];
        $foto = $body['foto'];
		
		$fecha_numeros = str_replace('/','', $fecha);
		$hora_numeros = str_replace(':','', $hora);
		
		$unique = $fecha_numeros . $hora_numeros;
    	
	$idphoto = FuncionesSamsung::getIdPacks();


	if ($idphoto){
        foreach ($idphoto as $row => $link) {
            $id = $link['id_packs'];
        }
		$name = "$unique$usuario$pharma_id";
		$name_final = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
		$photo_name = str_replace(' ','', $name_final);
		$path = "Packs/$photo_name.png"; 
    }else{
	    $name = "$unique$usuario$pharma_id";
		$name_final = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
     	$photo_name = str_replace(' ','', $name_final);
		$path = "Packs/$photo_name.png"; 
    }
     	
    // Insertar meta
    $retorno = FuncionesSamsung::insertPacks
        ($pharma_id,
		$pos_id,
		$usuario,
		$supervisor,
		$fecha,
		$hora,
		$categoria,
		$subcategoria,
		$presentacion,
		$brand,
		$sku_code,
		$observacion,
		$manufacturer,
		$path);
	
	
    if ($retorno) {
 	    // file_put_contents($path,base64_decode($foto));
        $container = 'app/AppAlicorp/Inserts/Packs';
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