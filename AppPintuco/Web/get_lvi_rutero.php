<?php
require '../Data/Funciones.php';

header('Content-Type: application/json');

$user  = isset($_GET['user'])  ? $_GET['user']  : '';
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

if (empty($user)) {
    echo json_encode(array('error' => 'Parametro user requerido'));
    exit;
}

$result = FuncionesSamsung::getRutero($user, $fecha);

if ($result !== false) {
    echo json_encode($result);
} else {
    echo json_encode(array('error' => 'Error al consultar lvi_rutero'));
}
?>
