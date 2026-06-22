<?php
	/**
	 * Insertar una nueva meta en la base de datos
	 */

	require '../Data/Funciones.php';
	include 'upload_azure.php';

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
		$presentacion = $body['presentacion'];
		$brand = $body['brand'];
		$contenido = $body['contenido'];
		$sku_code = $body['sku_code'];
		$sku_cliente = $body['sku_cliente'];
		$inventarios = $body['inventarios'];
		$souvenirs  = $body['souvenirs'];
		$tipo_conteo_total  = $body['tipo_conteo_total'];
		$total  = $body['total'];
		$tipo_conteo_defectuosas  = $body['tipo_conteo_defectuosas'];
		$cantidad_defectuosas  = $body['cantidad_defectuosas'];
		$fecha_caducidad_total  = $body['fecha_caducidad_total'];
		$fecha_caducidad  = $body['fecha_caducidad'];
		$causal  = $body['causal'];
		$foto = $body['foto'];
		$plataforma = $body['plataforma'];
		$modulo = $body['modulo'];
		$litros = $body['litros'];
		$dias_restantes = $body['dias_restantes'];
		$valorizado = $body['valorizado'];


		$fecha_numeros = str_replace('/','', $fecha);
		$hora_numeros = str_replace(':','', $hora);
		
		$unique = $fecha_numeros . $hora_numeros;
		
		if ($modulo == "PROPENSOS") {
			
	//	if ($foto==='NO_FOTO') {
	//		$path = "Inventario/NO_DISPONIBLE.png";
	//	} else {
			$name = "$unique$usuario$pharma_id$sku_code";
			$full_name = str_replace(str_split('\\/:*?"<>|%+#-.áéíóúÁÉÍÓÚÑñ'),'', $name);
			$photo_name = str_replace(' ','', $full_name);
			$path = "Propensos/$photo_name.png";
	//	}

		} else if ($modulo == "PRODUCTOS EN MAL ESTADO" || $modulo == "MAL ESTADO") {

			$name = "$unique$usuario$pharma_id$sku_code";
			$full_name = str_replace(str_split('\\/:*?"<>|%+#-.áéíóúÁÉÍÓÚÑñ'),'', $name);
			$photo_name = str_replace(' ','', $full_name);
			$path = "ProductosEnMalEstado/$photo_name.png";

		}
			
		// Insertar meta
		$retorno = FuncionesSamsung::insertPropensosYProductosMalEst072025(
			$pharma_id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$subcategoria,
			$presentacion,
			$brand,
			$contenido,
			$sku_code,
			$sku_cliente,
			$inventarios,
			$souvenirs,
			$tipo_conteo_total,
			$total,
			$tipo_conteo_defectuosas,
			$cantidad_defectuosas,
			$fecha_caducidad_total,
			$fecha_caducidad,
			$causal,
			$path,
			$plataforma,
			$modulo,
			$litros,
			$dias_restantes,
			$valorizado
		);
		
		
		if ($retorno) {
			if ($foto!='NO_FOTO') {

				if ($modulo == "PROPENSOS") {
						$container = 'app/AppPintuco/Inserts/Propensos';
				} else if ($modulo == "PRODUCTOS EN MAL ESTADO") {
						$container = 'app/AppPintuco/Inserts/ProductosEnMalEstado';
				}

				uploadBlobSample($blobClient, $container, $foto, $photo_name.'.png');


			}
			
			// $containerName = "app/AppDanec/Inserts/Exhibicion";
			// $filetoUpload = realpath('./Exhibicion/'.$photo_name.'.png');
			// $blobName = $photo_name . '.png';
			
			// uploadBlob($filetoUpload, $containerName, $blobName);
			
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