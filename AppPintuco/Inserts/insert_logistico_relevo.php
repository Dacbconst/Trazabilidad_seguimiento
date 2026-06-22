<?php
/**
 * Insertar rastreo coordenadas base de datos
 */

require '../Data/Funciones.php';
require 'upload_azure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $body = json_decode(file_get_contents("php://input"), true);

        $pharma_id = $body['id'];
        $pos_id = $body['codigo'];
        $usuario = $body['usuario'];
        $supervisor = $body['supervisor'];
        $fecha = $body['fecha'];
        $hora = $body['hora'];
        $categoria = $body['categoria'];
        $brand = $body['brand'];
        $sku_code = $body['sku_code'];
        $cantidad = $body['regular_price'];
        $causal = $body['causal'];
        $tipo_logistico = $body['tipo_logistico'];
        $foto = $body['foto'];
        $comentario = $body['comentario'];
        $fecha_prod_caducado = $body['fecha_prod_caducado'];
        $fecha_prod_propenso = $body['fecha_prod_propenso'];

        $fecha_numeros = str_replace('/','', $fecha);
		$hora_numeros = str_replace(':','', $hora);

        $unique = $fecha_numeros . $hora_numeros;
    
        if ($foto==='NO_FOTO' || $foto === null || $foto === "N/A") {
            $path = "LogisticoPreforma/NO_DISPONIBLE.png";
        }else {
            $name = "$unique$usuario$pos_id";
            $name_final = str_replace(str_split('\\/:*?"<>|%+#'),'', $name);
            $photo_name = str_replace(' ','', $name_final);
            $path = "LogisticoPreforma/$photo_name.png";
        }


    $retorno = FuncionesSamsung::insertLogisticoRelevo(
        $pharma_id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $categoria,
        $brand,
        $sku_code,
        $cantidad,
        $causal,
        $tipo_logistico,
        $path,
        $comentario,
        $fecha_prod_caducado,
        $fecha_prod_propenso    
    );

    if ($retorno) {

        $container = 'app/AppPintuco/Inserts/LogisticoPreforma';   
        uploadBlobSample($blobClient, $container, $foto, $photo_name.'.png');

        print json_encode(
            array(
                'estado' => '1',
                'mensaje' => 'Creación exitosa',
                'ultimoId' => $retorno)
        );
    } else {

        print json_encode(
            array(
                'estado' => '2',
                'mensaje' => 'Creación fallida')
        );
    }
}


?>