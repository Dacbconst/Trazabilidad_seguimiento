<?php
/**
 * Insertar exhibition en la base de datos
 */

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);

    // Insertar meta
    $retorno = FuncionesSamsung::insertInventario042023(
		$body['id'],
        $body['pos_id'],
        $body['user'],
        $body['supervisor'],
        $body['fecha'],
        $body['hora'],
        $body['sector'],
        $body['categoria'],
        $body['subcategoria'],
        $body['presentacion'],
        $body['brand'],
        $body['contenido'],
        $body['sku_code'],
        $body['inventarios'],
        $body['sugeridos'],
        $body['semana'],
        $body['causal'],
		$body['otros'],
		$body['caducidad'],
		$body['plataforma']);
		
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