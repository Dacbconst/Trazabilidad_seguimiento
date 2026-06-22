<?php

/**
 * Obtiene las causales de asistencia desde el repositorio
 */

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json (por si envías parámetros, aunque aquí no se usen)
    $body = json_decode(file_get_contents("php://input"), true);

    // Llamada a la función
    $retorno = FuncionesSamsung::getCausalesAsistencia();

    if ($retorno) {
        $datos["estado"] = "1";
     
        $datos["causales_asistencia"] = $retorno;

        print json_encode($datos);
    } else {
        print json_encode(array(
            "estado" => 2,
            "mensaje" => "No se encontraron registros en el servidor."
        ));
    }
}

?>