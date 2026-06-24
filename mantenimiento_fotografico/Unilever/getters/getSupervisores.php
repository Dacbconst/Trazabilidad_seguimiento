<?php
include_once '../db_connect.php';

$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin    = $_POST['fecha_fin'];
$reporte      = $_POST['reporte'];
$tipo         = $_POST['tipo'] ?? '.';

include_once '../includes/filtros.php';

// Patrón Pinguino: una sola consulta para cualquier reporte (vi_exhibiciones expone las mismas columnas)
$vista = ($reporte === 'exhibiciones') ? 'vi_exhibiciones' : $reporte;

$query = "SELECT DISTINCT ins.supervisor
          FROM $vista ins
          WHERE (STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN '$fecha_inicio' AND '$fecha_fin')
          AND ins.supervisor NOT LIKE '%PRUEBA%'
          AND " . filtroTipo($tipo) . "
          ORDER BY ins.supervisor";

$html = "<option value='.'>Todos</option>";
if ($sql = $mysqli->prepare($query)) {
    $sql->execute();
    $sql->store_result();
    if ($sql->num_rows > 0) {
        $sql->bind_result($supervisor) or die($sql->error);
        while ($sql->fetch()) {
            $html .= "<option value='$supervisor'>$supervisor</option>";
        }
    }
    $sql->close();
}
echo $html;
?>
