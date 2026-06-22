<?php
/**
 * Insertar exhibition en la base de datos
 */

require '../Data/Funciones.php';
include 'upload_azure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
    // Decodificando formato Json
    $body = json_decode(file_get_contents("php://input"), true);
	
	$usuario = $body['usuario'];
	$jornada_laboral = $body['jornada_laboral'];
    $tiempo_almuerzo = $body['tiempo_almuerzo'];
    $tiempo_fuera = $body['tiempo_fuera'];
	$foto = $body['foto'];
    $latitud = $body['latitud'];
    $longitud = $body['longitud'];
    $fecha = $body['fecha'];
    $hora_ini_almuerzo = $body['hora_ini_almuerzo'];
    $hora_fin_almuerzo = $body['hora_fin_almuerzo'];

    $path = "NO_FOTO";
	

    if ($foto != "NO_FOTO") {

        $fecha_numeros = str_replace('/','', $fecha);
        $hora_numeros = str_replace(':','', $hora);
        $unique = $fecha_numeros . $hora_numeros;
        $name = "$unique$usuario";
        $full_name = str_replace(str_split('\\/:*?"<>|%+#-.áéíóúÁÉÍÓÚÑñ'),'', $name);
        $photo_name = str_replace(' ','', $full_name);
        $path = "Almuerzo/$photo_name.png";

    } 

	// Insertar meta
    $retorno = FuncionesSamsung::insertAlmuerzo04042025(
        $usuario,
        $jornada_laboral,
        $tiempo_almuerzo,
        $tiempo_fuera,
		$path,
        $latitud,
        $longitud,
		$fecha,
        $hora_ini_almuerzo,
        $hora_fin_almuerzo
	    );

        
    if ($retorno) {
 	    // file_put_contents($path,base64_decode($image));

        if ($path != "NO_FOTO") {
            $container = 'app/AppPintuco/Inserts/Almuerzo';
            uploadBlobSample($blobClient, $container, $foto, $photo_name.'.png');
        }



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