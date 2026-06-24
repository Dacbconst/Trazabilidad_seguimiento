<?php
include_once '../db_connect.php';

$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin    = $_POST['fecha_fin'];
$reporte      = $_POST['reporte'];
$supervisor   = $_POST['supervisor'] ?? '.';
$tipo         = $_POST['tipo'] ?? '.';

include_once '../includes/filtros.php';

$vista = ($reporte === 'exhibiciones') ? 'vi_exhibiciones' : $reporte;

$query = "SELECT DISTINCT ins.mercaderista
          FROM $vista ins
          WHERE (STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN '$fecha_inicio' AND '$fecha_fin')
          AND ins.supervisor REGEXP '$supervisor'
          AND ins.mercaderista NOT LIKE '%PRUEBA%'
          AND " . filtroTipo($tipo) . "
          ORDER BY ins.mercaderista";

$html = "<option value='.'>Todos</option>";
if ($sql = $mysqli->prepare($query)) {
    $sql->execute();
    $sql->store_result();
    if ($sql->num_rows > 0) {
        $sql->bind_result($mercaderista) or die($sql->error);
        while ($sql->fetch()) {
            $html .= "<option value='$mercaderista'>$mercaderista</option>";
        }
    }
    $sql->close();
}
echo $html;
?>
