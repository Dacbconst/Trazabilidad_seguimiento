<?php
include_once 'includes/db_connect.php'; // Incluye tu conexión a la base de datos
include_once 'includes/functions.php'; // Incluye tus funciones de sesión

sec_session_start(); // Inicia la sesión

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON

// Verifica si el usuario está logeado
// if (login_check($mysqli) !== true) {
//     echo json_encode(['error' => 'Unauthorized access.']);
//     exit;
// }

if (!isset($_SESSION['supervisor'])) {
    echo json_encode(['error' => 'Supervisor not logged in.']);
    exit;
}

//$supervisor = $_SESSION['supervisor'];
$provincia = $_GET['provincia'] ?? ''; // Captura la provincia enviado desde el JS

// Valida que se recibió una provincia
if (empty($provincia)) {
    echo json_encode(['error' => 'No se recibió la provincia.']);
    exit;
}

// Consulta para obtener las ciudades distintas
$query = "SELECT DISTINCT ciudad
          FROM repositorio_ciudades_cliente
          WHERE provincia = ?
          ORDER BY ciudad ASC"; // Ordenar alfabéticamente las ciudades

if ($stmt = $mysqli->prepare($query)) {
    $stmt->bind_param('s',  $provincia); // 'ss' porque son dos strings
    $stmt->execute();
    $result = $stmt->get_result();

    $ciudades = [];
    while ($row = $result->fetch_assoc()) {
        $ciudades[] = $row['ciudad'];
    }
    $stmt->close();
    echo json_encode(['ciudades' => $ciudades]); // Devuelve un array de ciudades
} else {
    echo json_encode(['error' => 'Error en la consulta de la base de datos: ' . $mysqli->error]);
}
$mysqli->close();
?>