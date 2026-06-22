<?php
/**
 * Insertar una nueva meta en la base de datos
 */

require '../Data/Funciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);
	
		$pharma_id = $body['id'];
        $codigo = $body['pos_id'];
        $usuario = $body['user'];
        $supervisor = $body['supervisor'];
		$tipo_factura = $body['tipo_factura'];
        $num_factura = $body['num_factura'];
        $monto_factura = $body['monto_factura'];
        $fecha_venta = $body['fecha_venta'];
		$foto = $body['foto'];
        $fecha = $body['fecha'];
        $hora = $body['hora'];
	
	$idphoto = FuncionesSamsung::getIdVenta();

    if ($idphoto)    {
        foreach ($idphoto as $row => $link) {
            $id = $link['id_venta'];
        }
	$name = "$id$codigo";
 	$path = "Venta/$name.png"; 
    }else{
	    $name = "$codigo$id";
     	$path = "Venta/$name.png"; 
    }

     	
    // Insertar meta
    $retorno = FuncionesSamsung::insertVenta(
		$pharma_id,
        $codigo,
        $usuario,
        $supervisor,
		$tipo_factura,
        $num_factura,
        $monto_factura,
        $fecha_venta,
		$path,
        $fecha,
        $hora
	);

    if ($retorno) {
 	file_put_contents($path,base64_decode($foto));
        // Código de éxito
        print json_encode(
            array(
                'estado' => '1',
                'mensaje' => 'Creación exitosa',
                //'idPdvFoto' => $retorno)
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