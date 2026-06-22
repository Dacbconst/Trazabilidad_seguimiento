<?php
/**
 * Insertar en la base de datos
 */

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);

    // Insertar meta
    $retorno = FuncionesSamsung::insertResultadoPreguntasVentas(
    	$body['user'],
    	$body['test_id'],
    	$body['test'],
        $body['question'],
        $body['opta'],
		$body['optb'],
		$body['optc'],
		$body['answer'],
		$body['answer_user'],
		$body['result'],
		$body['fecha'],
		$body['hora']);

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