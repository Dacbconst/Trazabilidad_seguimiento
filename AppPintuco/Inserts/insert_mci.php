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
	$canal = $body['canal'];
	$nombre_comercial = $body['nombre_comercial'];
	$local = $body['local'];
	$region = $body['region'];
	$provincia = $body['provincia'];
	$ciudad = $body['ciudad'];
	$zona = $body['zona'];
	$direccion = $body['direccion'];
	$supervisor = $body['supervisor'];
	$mercaderista = $body['mercaderista'];
	$usuario = $body['usuario'];
	$latitud = $body['latitud'];
	$longitud = $body['longitud'];
	$territorio = $body['territorio'];
	$zona_territorio = $body['zona_territorio'];
	$causal = $body['causal'];
	$observaciones = $body['observaciones'];
	$foto = $body['foto'];
	$fecha = $body['fecha'];
	$hora = $body['hora'];
	
	$path_foto = "MCI/NO_FOTO.png";
	
	$fecha_numeros = str_replace('/','', $fecha);
	$hora_numeros = str_replace(':','', $hora);
	
	$unique = $fecha_numeros . $hora_numeros;
	    
	if($foto!='NO_FOTO'){
		$name = "$unique$usuario$codigo$causal";
		$full_name = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
		$photo_name = str_replace(' ','', $full_name);
		$path_foto = "MCI/$photo_name.png";
	}
	
    // Insertar meta
    $retorno = FuncionesSamsung::insertMCI(
		$pharma_id,
		$codigo,
		$canal,
		$nombre_comercial,
		$local,
		$region,
		$provincia,
		$ciudad,
		$zona,
		$direccion,
		$supervisor,
		$mercaderista,
		$usuario,
		$latitud,
		$longitud,
		$territorio,
		$zona_territorio,
		$causal,
		$observaciones,
		$path_foto,
		$fecha,
		$hora
    );

    if ($retorno) {
		if ($foto!='NO_FOTO') {
			// file_put_contents($path_foto,base64_decode($foto));
			$container = 'app/AppAlicorp/Inserts/MCI';
			uploadBlobSample($blobClient, $container, $foto, $photo_name.'.png');
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