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
		$brand = $body['brand'];
		$sku_code = $body['sku_code'];
		$asignada = $body['asignada'];
        $vendida = $body['vendida'];
        $adicional = $body['adicional'];
        $cumplimiento = $body['cumplimiento'];
        $impulsadora = $body['impulsadora'];
		$observacion = $body['observacion'];
        $foto = $body['foto'];
        $precio_venta = $body['precio_venta'];
        $alerta_stock = $body['alerta_stock'];
        $plataforma = $body['plataforma'];
		
		$fecha_numeros = str_replace('/','', $fecha);
		$hora_numeros = str_replace(':','', $hora);
		
		$unique = $fecha_numeros . $hora_numeros;
    	
	// $idphoto = FuncionesSamsung::getIdImpulso();

    // if ($idphoto){
    //     foreach ($idphoto as $row => $link) {
    //         $id = $link['id_impulso'];
    //     }
	// 	$name = "$unique$usuario$pos_id";
	// 	$name_final = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
	// 	$photo_name = str_replace(' ','', $name_final);
	// 	$path = "Impulso/$photo_name.png"; 
    // }else{
	    $name = "$unique$usuario$pos_id";
     	$name_final = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
		$photo_name = str_replace(' ','', $name_final);
		$path = "Impulso/$photo_name.png"; 
    // }

     	
    // Insertar meta
    $retorno = FuncionesSamsung::insertImpulso022023
        ($pharma_id,
		$pos_id,
		$usuario,
		$supervisor,
		$fecha,
		$hora,
		$categoria,
		$brand,
		$sku_code,
		$asignada,
		$vendida,
		$adicional,
		$cumplimiento,
		$impulsadora,
		$observacion,
		$path,
		$precio_venta,
		$alerta_stock,
		$plataforma);
	
	
    if ($retorno) {
 	    // file_put_contents($path,base64_decode($foto));
        $container = 'app/AppAlicorp/Inserts/Impulso';
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