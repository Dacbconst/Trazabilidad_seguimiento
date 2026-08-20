<?php
include_once 'includes/db_connect.php'; // LOGICA PARA LA CONEXION CON LA BASE DE DATOS
include_once 'includes/functions.php';

$tipo = $_GET['tipo'] ?? '';

// Para recibir los datos del fetch (en formato JSON)
$data = json_decode(file_get_contents('php://input'), true);

// Si los resultados son enviados correctamente
if (isset($data['resultados']) && is_array($data['resultados'])) {
    $resultados = $data['resultados'];

    // Aquí solo simulas el procesamiento de los datos, sin hacer el INSERT
    echo json_encode([
        'status' => 'success',
        'message' => 'Datos recibidos correctamente',
        'data' => $resultados // Puedes mostrar los datos recibidos para verificación
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se recibieron resultados']);
}
?>
