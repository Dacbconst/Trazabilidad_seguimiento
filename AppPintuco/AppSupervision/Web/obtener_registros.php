<?php

/**
 * Obtiene todas las metas de la base de datos
 */

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);

    $user= $body['promotor'];
    $fecha=$body['fecha'];
    // Insertar meta
    $retorno = Funciones::get_registros(
        $user,
        $fecha);

    if ($retorno) {
    $datos["estado"] = "1";
    $datos["registrosresult"] = $retorno;

        print json_encode($datos);
    } else {
        print json_encode(array(
            "estado" => 2,
            "mensaje" => "Ha ocurrido un error".$fecha.$user
        ));
    }
}

?>