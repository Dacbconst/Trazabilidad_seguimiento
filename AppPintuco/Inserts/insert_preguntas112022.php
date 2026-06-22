<?php
/**
 * Insertar en la base de datos
 */

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);

    // Insertar meta
    $retorno = FuncionesSamsung::insertPreguntas112022(
    	$body['user'],
    	$body['test_id'],
    	$body['test'],
        $body['p1'],
        $body['p2'],
		$body['p3'],
		$body['p4'],
		$body['p5'],
		$body['p6'],
		$body['p7'],
		$body['p8'],
		$body['p9'],
		$body['p10'],
		$body['p11'],
		$body['p12'],
		$body['p13'],
		$body['p14'],
		$body['p15'],
        $body['correctas'],
        $body['incorrectas'],
        $body['calificacion'],
        $body['observacion'],
        $body['fecha'],
        $body['hora'],
        $body['cronometro']);

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