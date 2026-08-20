<?php

include_once 'includes/db_connect.php'; // LOGICA PARA LA CONEXION CON LA BASE DE DATOS
include_once 'includes/functions.php';

//(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
//header('Content-Type: application/json');


$tipo = $_GET['tipo'] ?? '';

// CAMBIADO $tipo =  $_GET['tipo'];


//$tipo = "MAYORISTAS";


$preguntas = [];


class Pregunta {
    public $id ="";
    public $perfect_store = "";
    public $categoria = "";
    public $variante = "";
    public $pregunta1 = "";
    public $tipo_cliente = "";
    public $puntaje = "";
}





$query_preguntas = "SELECT  * FROM repositorio_evaluacion_gestor WHERE tipo_cliente = ? ";

$stmt1 = $mysqli->prepare($query_preguntas);
$stmt1->bind_param("s",$tipo);
$stmt1->execute();
$result1 = $stmt1->get_result();
if ($result1->num_rows > 0) {

    while ($item = $result1->fetch_assoc()) {


        //  AQUI EN LOS CORCHETE SE PONEN LOS NOMBRES  TAL CUAL DE LOS CAMPOS QUE SE ENCUENTRAN EN LA BASE DE DATOS. 
        $id = $item['id'];
        $perfect_store = $item['perfect_store'];
        $categoria = $item['categoria'];
        $variante = $item['variante'];
        $pregunta1 = $item['pregunta'];
        $tipo_cliente = $item['tipo_cliente'];
        $puntaje = $item['unidades'];

        $pregunta = new Pregunta();
        $pregunta->id = $id;
        $pregunta->perfect_store = $perfect_store;
        $pregunta->categoria = $categoria;
        $pregunta->variante = $variante;
        $pregunta->pregunta1 = $pregunta1;
        $pregunta->tipo_cliente = $tipo_cliente;
        $pregunta->puntaje = $puntaje;


        array_push($preguntas,$pregunta);

    }

}
  


// Crear un array asociativo que contenga el status y los datos
// $response = [   
//     "data" => $preguntas         // Listado de PDVs
// ];



// Establecer el header de tipo JSON
//header('Content-Type: application/json');

echo json_encode($preguntas);

?>