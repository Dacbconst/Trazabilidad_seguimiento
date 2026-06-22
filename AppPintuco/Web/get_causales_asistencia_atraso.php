<?php

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $body = json_decode(file_get_contents("php://input"), true);

    $retorno = FuncionesSamsung::getCausalesAsistenciaAtraso();

    if ($retorno) {
        $datos["estado"] = "1";
        $datos["causales_asistencia_atraso"] = $retorno;
        print json_encode($datos);
    } else {
        print json_encode(array(
            "estado" => 2,
            "mensaje" => "No se encontraron registros en el servidor."
        ));
    }
}

?>