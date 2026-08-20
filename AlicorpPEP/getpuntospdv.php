<?php
include_once 'includes/db_connect.php';
include_once 'includes/functions.php';

sec_session_start(); 

header('Content-Type: application/json'); // Set header to return JSON

// if (login_check($mysqli) !== true) {
//     echo json_encode(['error' => 'Unauthorized access.']);
//     exit;
// }

if (!isset($_SESSION['supervisor'])) {
    echo json_encode(['error' => 'Supervisor not logged in.']);
    exit;
}

$supervisor = $_SESSION['supervisor'];
$buscar = $_GET['buscar'] ?? '';

$query = "SELECT  DISTINCT pos_id, pos_name, cedi as 'provincia', asesor as 'distribuidor'  
FROM repositorio_locales_supervisores_cliente
WHERE supervisor = ?
  AND (
    pos_id LIKE CONCAT('%', ?, '%')
    OR pos_name LIKE CONCAT('%', ?, '%')
  )
  AND canal = 'TIENDAS'
LIMIT 100"; 

if ($stmt = $mysqli->prepare($query)) {
    $stmt->bind_param('sss', $supervisor, $buscar, $buscar);
    $stmt->execute();
    $result = $stmt->get_result();

    $pdvs = [];
    while ($row = $result->fetch_assoc()) {
        $pdvs[] = $row;
    }
    $stmt->close();
    echo json_encode($pdvs);
} else {
    echo json_encode(['error' => 'Database query failed: ' . $mysqli->error]);
}
$mysqli->close();
?>