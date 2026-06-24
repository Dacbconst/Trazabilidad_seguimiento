<?php
include_once '../db_connect.php';

$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin    = $_POST['fecha_fin'];
$reporte      = $_POST['reporte'];
$supervisor   = $_POST['supervisor'] ?? '.';
$gestor       = $_POST['gestor'] ?? '.';
$cadena       = $_POST['cadena'] ?? '.';
$ciudad       = $_POST['ciudad'] ?? '.';
$tipo         = $_POST['tipo']      ?? '.';
$categoria    = $_POST['categoria'] ?? '.';

include_once '../includes/filtros.php';

$vista = ($reporte === 'exhibiciones') ? 'vi_exhibiciones' : $reporte;

$query = "SELECT DISTINCT ins.pos_name
          FROM $vista ins
          WHERE (STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN '$fecha_inicio' AND '$fecha_fin')
          AND ins.supervisor REGEXP '$supervisor'
          AND ins.mercaderista REGEXP '$gestor'
          AND COALESCE(ins.customer_owner, 'Sin Cadena') REGEXP '$cadena'
          AND ins.city REGEXP '$ciudad'
          AND " . filtroTipo($tipo) . "
          AND " . filtroCategoria($categoria) . "
          ORDER BY ins.pos_name";

$html = "<option value='.'>Todos</option>";
if ($sql = $mysqli->prepare($query)) {
    $sql->execute();
    $sql->store_result();
    if ($sql->num_rows > 0) {
        $sql->bind_result($local) or die($sql->error);
        while ($sql->fetch()) {
            $html .= "<option value='$local'>$local</option>";
        }
    }
    $sql->close();
}
echo $html;
?>
