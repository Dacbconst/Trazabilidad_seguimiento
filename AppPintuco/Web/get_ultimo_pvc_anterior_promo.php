<?php
require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $body = json_decode(file_get_contents("php://input"), true);
    $usuario = $body['usuario'];
    $codigo  = $body['codigo'];

    $retorno = FuncionesSamsung::getUltimoPvcAnteriorPromocionPorUsuario($usuario, $codigo);

    if ($retorno && count($retorno) > 0) {
        print json_encode(array(
            "estado" => "1",
            "productos_promo" => $retorno
        ));
    } else {
        print json_encode(array(
            "estado" => "2",
            "mensaje" => "No se encontraron registros."
        ));
    }
}
?>