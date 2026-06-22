<?php

/**
 * Obtiene todas las metas de la base de datos
 */

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);

    $usuario = $body['usuario'];
    // $categoria = $body['categoria'];
    // $subcategoria = $body['subcategoria'];
    // $marca = $body['marca'];

    // Insertar meta
    // $retorno = FuncionesSamsung::getProductosPVC($usuario, $categoria, $subcategoria, $marca);
    $retorno = FuncionesSamsung::getProductosPVC($usuario);

    if ($retorno) {
		$datos["estado"] = "1";
        $datos["productos_pvc"] = $retorno;

        print json_encode($datos);
    } else {
        print json_encode(array(
            "estado" => 2,
            "mensaje" => "No se encontraron registros en el servidor."
        ));
    }
}

?>