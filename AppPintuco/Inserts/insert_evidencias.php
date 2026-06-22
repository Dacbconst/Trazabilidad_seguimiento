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
    $comentario  = $body['comentario'];
	$foto_antes = $body['foto_antes'];
	$foto_despues = $body['foto_despues'];
    $fecha = $body['fecha'];
    $hora = $body['hora'];
	
	$fecha_numeros = str_replace('/','', $fecha);
	$hora_numeros = str_replace(':','', $hora);
	
	$unique = $fecha_numeros . $hora_numeros;

	$name1 = "$unique$usuario$pharma_id$pos_id"."ANTES";
	$full_name1 = str_replace(str_split('\\/:*?"<>|%+#'),'', $name1);
	$photo_name1 = str_replace(' ','', $full_name1);
	$path1 = "Evidencias/$photo_name1.png";
	
	$name2 = "$unique$usuario$pharma_id$pos_id"."DESPUES";
	$full_name2 = str_replace(str_split('\\/:*?"<>|%+#'),'', $name2);
	$photo_name2 = str_replace(' ','', $full_name2);
	$path2 = "Evidencias/$photo_name2.png";
	
	// Insertar meta
    $retorno = FuncionesSamsung::insertEvidencias(
		$pharma_id,
		$pos_id,
        $usuario,
        $comentario,
		$path1,
		$path2,
		$fecha,
        $hora
	    );
		

    if ($retorno) {
		// file_put_contents($path1,base64_decode($foto_antes));
		// file_put_contents($path2,base64_decode($foto_despues));

        $container = 'app/AppAlicorp/Inserts/Evidencias';
        uploadBlobSample($blobClient, $container, $foto_antes, $photo_name1.'.png');
        uploadBlobSample($blobClient, $container, $foto_despues, $photo_name2.'.png');


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