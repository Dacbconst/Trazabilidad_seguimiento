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
		$user = $body['usuario'];
		$supervisor = $body['supervisor'];
		$fecha = $body['fecha'];
		$hora = $body['hora'];
		$fecha_venta = $body['fecha_venta'];
		$categoria = $body['categoria'];
		$subcategoria = $body['subcategoria'];
		$presentacion = $body['presentacion'];
		$marca = $body['marca'];
		$sku_code = $body['sku_code'];
		$tipo_venta = $body['tipo_venta'];
		$stock_inicial = $body['stock_inicial'];
		$cantidad = $body['cantidad'];
		$regular_price = $body['regular_price'];
		$promotional_price = "N/A";
		$ofert_price  = "N/A";
		$stock_final = $body['stock_final'];
		$manufacturer  = $body['manufacturer'];
		$pos_name  = $body['pos_name'];
		$foto = $body['foto'];


		
		$fecha_numeros = str_replace('/','', $fecha);
		$hora_numeros = str_replace(':','', $hora);
		
		$unique = $fecha_numeros . $hora_numeros;

		

		if ($foto==='NO_FOTO') {
			$path_foto = "NO_FOTO";
		} else {
			$name = "$unique$user$pos_id$brand$categoria$subcategoria$numero_factura";
			$full_name = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
			$photo_name = str_replace(' ','', $full_name);
			$path_foto = "Ventas/$photo_name.png";
		}

		// if ($foto_adicional==='NO_FOTO') {
		// 	$path_foto_adicional = "NO_FOTO";
		// } else {
		// 	$name_adicional = "$unique$user$pos_id$brand$categoria$subcategoria$numero_factura";
		// 	$full_additional_name = str_replace(str_split('\\/:*?"<>|%+#'),'', $name_adicional);
		// 	$photo_additional_name = str_replace(' ','', $full_additional_name);
		// 	$path_foto_adicional = "Ventas/FotoAdicional/$photo_additional_name.png";
		// }

    // Insertar meta 
	
    $retorno = FuncionesSamsung::insertVentas012025(
	        $id,
            $pos_id,
            $user,
            $supervisor,
            $fecha,
            $hora,
			$fecha_venta,
            $categoria,
            $subcategoria,
            $presentacion,
            $marca,
            $sku_code,
			$tipo_venta,
			$stock_inicial,
			$cantidad,
            $regular_price,
            $promotional_price,
			$ofert_price,
			$stock_final,
			$manufacturer,
			$pos_name,
			$path_foto
		);

    if ($retorno) {
        // Código de éxito
		
			if($foto!='NO_FOTO'){
				$container = 'app/AppPintuco/Inserts/Ventas';
				uploadBlobSample($blobClient, $container, $foto, $photo_name.'.png');					
			}

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