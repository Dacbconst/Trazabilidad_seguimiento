<?php

/**
 * Obtiene los rangos de precios especiales por SKU
 */

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $body = json_decode(file_get_contents("php://input"), true);

    $retorno = FuncionesSamsung::getRangosPreciosSku(
        $body['operator']);

    if ($retorno) {
        $datos["estado"] = "1";
        $datos["rango_precios_sku"] = $retorno;

        print json_encode($datos);
    } else {
        print json_encode(array(
            "estado" => 2,
            "mensaje" => "No se encontraron registros en el servidor."
        ));
    }
}

?>
