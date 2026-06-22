<?php
/**
 * Insertar una nueva meta en la base de datos
 */

require '../Data/Funciones.php';
require 'upload_azure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);
	
	$id = $body['id'];
	$evaluador = $body['evaluador'];
	$gestor = $body['gestor'];
	$id_pdv = $body['id_pdv'];
	$pdv = $body['pdv'];
	$tipo = $body['tipo'];
	$impletacion_rotacion_descripcion = $body['impletacion_rotacion_descripcion'];
	$impletacion_rotacion_puntaje = $body['impletacion_rotacion_puntaje'];
	$impletacion_rotacion_meta = $body['impletacion_rotacion_meta'];
	$exhibicion_visibilidad_descripcion = $body['exhibicion_visibilidad_descripcion'];
	$exhibicion_visibilidad_puntaje = $body['exhibicion_visibilidad_puntaje'];
	$exhibicion_visibilidad_meta = $body['exhibicion_visibilidad_meta'];
	$evaluacion_gestor_descripcion = $body['evaluacion_gestor_descripcion'];
	$evaluacion_gestor_puntaje = $body['evaluacion_gestor_puntaje'];
	$evaluacion_gestor_meta = $body['evaluacion_gestor_meta'];
	$evaluacion_pdv_descripcion = $body['evaluacion_pdv_descripcion'];
	$evaluacion_pdv_puntaje = $body['evaluacion_pdv_puntaje'];
	$evaluacion_pdv_meta = $body['evaluacion_pdv_meta'];
	$foto = $body['foto'];
	$fecha = $body['fecha'];
	$hora = $body['hora'];
	$comentario = $body['comentario'];
	
	$path_foto = "MCI/NO_FOTO.png";
	
	$fecha_numeros = str_replace('/','', $fecha);
	$hora_numeros = str_replace(':','', $hora);
	
	$unique = $fecha_numeros . $hora_numeros;
	    
	if($foto!='NO_FOTO'){
		$name = "$unique$pdv$gestor";
		$full_name = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
		$photo_name = str_replace(' ','', $full_name);
		$path_foto = "Evaluaciones/$photo_name.png";
	}
	
    // Insertar meta
    $retorno = FuncionesSamsung::insertEvaluaciones(
		$id,
		$evaluador,
		$gestor,
		$id_pdv,
		$pdv,
		$tipo,
		$impletacion_rotacion_descripcion,
		$impletacion_rotacion_puntaje,
		$impletacion_rotacion_meta,
		$exhibicion_visibilidad_descripcion,
		$exhibicion_visibilidad_puntaje,
		$exhibicion_visibilidad_meta,
		$evaluacion_gestor_descripcion,
		$evaluacion_gestor_puntaje,
		$evaluacion_gestor_meta,
		$evaluacion_pdv_descripcion,
		$evaluacion_pdv_puntaje,
		$evaluacion_pdv_meta,
		$path_foto,
		$fecha,
		$hora,
		$comentario
    );

    if ($retorno) {
		if ($foto!='NO_FOTO') {
			// file_put_contents($path_foto,base64_decode($foto));
			$container = 'app/AppAlicorp/Inserts/Evaluaciones';
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