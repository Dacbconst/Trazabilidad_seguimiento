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
	$categoria = $body['categoria'];
	$subcategoria = $body['subcategoria'];
	$marca = $body['marca'];
	$producto = $body['producto'];
	$tipo_combo = $body['tipo_combo'];
	$mecanica = $body['mecanica'];
	$combos_armados = $body['combos_armados'];
	$stock = $body['stock'];
	$pvc_combo = $body['pvc_combo'];
	$pvc_unitario = $body['pvc_unitario'];
	$visita = $body['visita'];
	$mes = $body['mes'];
	$observaciones = $body['observaciones'];
	$foto = $body['foto'];
	$image = $body['foto_guia'];
	$fecha = $body['fecha'];
	$hora = $body['hora'];
	$plataforma = $body['plataforma'];
	
	$path_foto = "Canjes/NO_FOTO.png";
	$path_foto_guia = "Canjes/NO_FOTO.png";
	
	$fecha_numeros = str_replace('/','', $fecha);
	$hora_numeros = str_replace(':','', $hora);
	
	$unique = $fecha_numeros . $hora_numeros;
	    
	if ($foto!='NO_FOTO') {
		$name1 = "$unique$usuario$codigo$categoria$subcategoria$marca$producto$visita";
		$full_name1 = str_replace(str_split('\\/:*?"<>|%+#'),'', $name1);
		$photo_name1 = str_replace(' ','', $full_name1);
		$path_foto = "Canjes/Foto/$photo_name1.png";
	}
	
	if ($image!='NO_FOTO') {
		$name2 = "$unique$usuario$codigo$categoria$subcategoria$marca";
		$full_name2 = str_replace(str_split('\\/:*?"<>|%+#'),'', $name2);
		$photo_name2 = str_replace(' ','', $full_name2);
		$path_foto_guia = "Canjes/FotoGuia/$photo_name2.png";
	}
	
    // Insertar meta
    $retorno = FuncionesSamsung::insertCanjes022023(
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
		$categoria,
		$subcategoria,
		$marca,
		$producto,
		$tipo_combo,
		$mecanica,
		$combos_armados,
		$stock,
		$pvc_combo,
		$pvc_unitario,
		$visita,
		$mes,
		$observaciones,
		$path_foto,
		$path_foto_guia,
		$fecha,
		$hora,
		$plataforma
    );

    if ($retorno) {
		if ($foto!='NO_FOTO') {
			// file_put_contents($path_foto,base64_decode($foto));
			$container = 'app/AppAlicorp/Inserts/Canjes/Foto';
			uploadBlobSample($blobClient, $container, $foto, $photo_name1.'.png');
		}
		if ($image!='NO_FOTO') {
			// file_put_contents($path_foto_guia,base64_decode($image));
			$container = 'app/AppAlicorp/Inserts/Canjes/FotoGuia';
			uploadBlobSample($blobClient, $container, $image, $photo_name2.'.png');
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