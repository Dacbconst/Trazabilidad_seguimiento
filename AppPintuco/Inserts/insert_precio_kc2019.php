<?php
/**
 * Insertar rastreo coordenadas base de datos
 */

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);

    // Insertar meta
    $retorno = FuncionesSamsung::insertprecioskc2019(
	        $body['id'],
            $body['pos_id'],
            $body['user'],
            $body['supervisor'],
            $body['fecha'],
            $body['hora'],
            $body['categoria'],
            $body['subcategoria'],
            $body['presentacion'],
            $body['brand'],
            $body['sku_code'],
            $body['regular_price'],
            $body['promotional_price'],
			$body['ofert_price'],
			$body['manufacturer']);

    if ($retorno) {
        // Código de éxito
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