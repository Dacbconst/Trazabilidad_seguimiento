<?php

include_once 'includes/db_connect.php'; // LOGICA PARA LA CONEXION CON LA BASE DE DATOS
include_once 'includes/functions.php';

//(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
//header('Content-Type: application/json');



// CAMBIADO $tipo =  $_GET['tipo'];


//$tipo = "MAYORISTAS";


$preguntas = [];


class Pregunta {
    public $id ="";
    public $categoria = "";
    public $subcategoria = "";
    public $marca = "";
    public $presentacion = "";
    public $contenido = "";
    public $variante = "";
    public $fabricante = "";
    public $tipo_empresa = "";
    public $sku = "";
    public $pvc = "";
    public $pvp = "";
    public $margen = "";
}





$query_preguntas = "SELECT  * FROM repositorio_precios_cliente";

$stmt1 = $mysqli->prepare($query_preguntas);

$stmt1->execute();
$result1 = $stmt1->get_result();
if ($result1->num_rows > 0) {

    while ($item = $result1->fetch_assoc()) {


        //  AQUI EN LOS CORCHETE SE PONEN LOS NOMBRES  TAL CUAL DE LOS CAMPOS QUE SE ENCUENTRAN EN LA BASE DE DATOS. 
        $id = $item['id'];
        $categoria = $item['categoria'];
        $subcategoria = $item['subcategoria'];
        $marca = $item['marca'];
        $presentacion = $item['presentacion'];
        $contenido = $item['contenido'];
         $variante = $item['variante'];
        $fabricante = $item['fabricante'];
        $tipo_empresa = $item['tipo_empresa'];
        $sku = $item['sku'];
        $pvp = $item['pvp'];
        $pvc = $item['pvc'];
        $margen = $item['margen'];
        

        $pregunta = new Pregunta();
        $pregunta->id = $id;
        $pregunta->categoria = $categoria;
        $pregunta->subcategoria = $subcategoria;
        $pregunta->marca = $marca;
        $pregunta->presentacion = $presentacion;
        $pregunta->contenido = $contenido;
        $pregunta->variante = $variante;
        $pregunta->fabricante = $fabricante;
       $pregunta->tipo_empresa = $tipo_empresa;
        $pregunta->sku = $sku;
        $pregunta->pvp = $pvp;
        $pregunta->pvc = $pvc;
        $pregunta->margen = $margen;



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