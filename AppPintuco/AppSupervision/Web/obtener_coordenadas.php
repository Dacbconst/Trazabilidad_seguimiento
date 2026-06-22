<?php

/**
 * Obtiene todas las metas de la base de datos
 */

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);
    $user= $body['promotor'];

    // Insertar meta
    $retorno = Funciones::get_coordenadas(
        $body['promotor'],
    	$body['fecha'],
    	$body['desde'],
        $body['hasta']);

    if ($retorno) {
	    $datos["estado"] = "1";
        $datos["coordresult"] = $retorno;

        print json_encode($datos);
    } else {
        print json_encode(array(
            "estado" => 2,
            "mensaje" => "No se encontraron registros en el servidor.".$user
        ));
    }
}

?>