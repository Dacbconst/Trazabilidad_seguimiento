<?php

/**
 * Insertar notificacion en la base de datos
 */

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);

    $usuario = $body['user'];
    $supervisor = $body['supervisor'];
    $descripcion = $body['descripcion'];
    $fecha = $body['fecha'];
    $hora = $body['hora'];

    // Insertar
    $retorno = FuncionesSamsung::insertNot0622(
        $usuario,
        $supervisor,
        $descripcion,
        $fecha,
        $hora
    );

    if ($retorno) {
        // Código de éxito
        print json_encode(
            array(
                'estado' => '1',
                'mensaje' => 'Creación exitosa',
                'ultimoId' => $retorno
            )
        );
    } else {
        // Código de falla
        print json_encode(
            array(
                'estado' => '2',
                'mensaje' => 'Creación fallida'
            )
        );
    }
}
