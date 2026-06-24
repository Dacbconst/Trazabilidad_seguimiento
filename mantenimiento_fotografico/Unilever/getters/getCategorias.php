<?php
include_once '../db_connect.php';

$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin    = $_POST['fecha_fin'];
$supervisor   = $_POST['supervisor'] ?? '.';
$gestor       = $_POST['gestor']     ?? '.';
$tipo         = $_POST['tipo']       ?? '.';

include_once '../includes/filtros.php';

$filtroSupervisor = ($supervisor === '.' || $supervisor === '') ? '1=1' : "ins.supervisor REGEXP '$supervisor'";
$filtroGestor     = ($gestor === '.'    || $gestor === '')    ? '1=1' : "ins.usuario REGEXP '$gestor'";

$query = "SELECT DISTINCT ins.categoria
          FROM insert_exhibiciones ins
          WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN '$fecha_inicio' AND '$fecha_fin'
          AND ins.usuario NOT LIKE '%PRUEBA%'
          AND $filtroSupervisor
          AND $filtroGestor
          AND " . filtroTipo($tipo) . "
          AND ins.categoria IS NOT NULL AND TRIM(ins.categoria) != ''
          ORDER BY ins.categoria";

$html = "<option value='.'>Todas</option>";
if ($sql = $mysqli->prepare($query)) {
    $sql->execute();
    $sql->store_result();
    if ($sql->num_rows > 0) {
        $sql->bind_result($categoria) or die($sql->error);
        while ($sql->fetch()) {
            $html .= "<option value='$categoria'>$categoria</option>";
        }
    }
    $sql->close();
}
echo $html;
?>
