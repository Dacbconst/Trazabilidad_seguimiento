<?php
/**
 * Insertar una nueva meta en la base de datos
 */

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);
       
        $id_pdv= $body['id_pdv'];
		$estadovisita=$body['estado_visita'];
		$novedades=$body['novedades'];
        $image=$body['foto'];
        $fecha=$body['fecha'];
        $hora=$body['hora'];
    	 
    	 

	$idphoto = FuncionesSamsung::getIdNotificacion();

    if ($idphoto)    {
        foreach ($idphoto as $row => $link) {
            $id = $link['id'];
        }
	$name = "$id.$estadovisita";
 	$path = "Notificacion/$name.png"; 
    }else{
	    $name = "$estadovisita.$id";
     	$path = "Notificacion/$name.png"; 
    }

     	
    // Insertar meta
    $retorno = FuncionesSamsung::insertNotificacion(
    $id_pdv,$estadovisita,$novedades,$path,$fecha,$hora);

    if ($retorno) {
 	file_put_contents($path,base64_decode($image));
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


