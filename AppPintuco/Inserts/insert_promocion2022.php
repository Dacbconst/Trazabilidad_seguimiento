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
		$brand = $body['brand'];
		$canal = $body['canal'];
        $tipo_promocion = $body['tipo_promocion'];
        $descripcion_promocion = $body['descripcion_promocion'];
        $mecanica = $body['mecanica'];
		$inicio_promocion = $body['inicio_promocion'];
		$fin_promocion = $body['fin_promocion'];
		$agotar_stock = $body['agotar_stock'];
		$pvc_anterior = $body['pvc_anterior'];
		$pvc_actual = $body['pvc_actual'];
		$foto = $body['foto'];
        $manufacturer = $body['manufacturer'];
        $sku = $body['sku'];
		
		$fecha_numeros = str_replace('/','', $fecha);
		$hora_numeros = str_replace(':','', $hora);
		
		$unique = $fecha_numeros . $hora_numeros;

        $path = "NO_FOTO";

        if ($foto!="NO_FOTO") {
            $name = "$unique$usuario$pos_id$sku";
            $full_name = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
            $photo_name = str_replace(' ','', $full_name);
            $path = "Promocion/$photo_name.png";
        }
     	
    // Insertar meta
    $retorno = FuncionesSamsung::insertPromocion2020(
	    $pharma_id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $categoria,
		$subcategoria,
		$brand,
		$canal,
        $tipo_promocion,
        $descripcion_promocion,
		$mecanica,
		$inicio_promocion,
		$fin_promocion,
		$agotar_stock,
		$pvc_anterior,
		$pvc_actual,
        $path,
        $manufacturer,
        $sku
    );

    if ($retorno) {
 	    // file_put_contents($path,base64_decode($foto));
        if ($foto!="NO_FOTO") {
            $container = 'app/AppAlicorp/Inserts/Promocion';
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