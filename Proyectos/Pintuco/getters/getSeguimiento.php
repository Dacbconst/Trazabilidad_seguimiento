<?php
include_once '../db_connect.php';
include_once '../includes/filtros.php';

$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin    = $_POST['fecha_fin'];
$supervisor   = $_POST['supervisor'] ?? '.';
$gestor       = $_POST['gestor']     ?? '.';

$filtroSupervisor = ($supervisor === '.' || $supervisor === '') ? '1=1' : "l.supervisor REGEXP '$supervisor'";
$filtroGestor     = ($gestor === '.'    || $gestor === '')    ? '1=1' : "l.mercaderista REGEXP '$gestor'";

$query = "
    SELECT
        DATE_FORMAT(l.fecha_visita, '%d/%m/%Y')                                      AS fecha,
        DATE_FORMAT(l.fecha_visita, '%Y-%m-%d')                                      AS fecha_iso,
        l.mercaderista                                                                AS gestor,
        COUNT(*)                                                                      AS asignado,
        SUM(CASE WHEN l.estado_visitado = 'ATENDIDO' THEN 1 ELSE 0 END)             AS relevado,
        SUM(CASE WHEN rv.tiene_antes = 1 OR rv.tiene_despues = 1 THEN 1 ELSE 0 END) AS con_relevo
    FROM lvi_rutero l
    LEFT JOIN (
        SELECT pos_id, mercaderista, fecha,
               MAX(CASE WHEN foto_antes IS NOT NULL AND foto_antes != ''
                             AND foto_antes != 'N/A' THEN 1 ELSE 0 END)   AS tiene_antes,
               MAX(CASE WHEN foto_despues IS NOT NULL AND foto_despues != ''
                             AND foto_despues != 'N/A' THEN 1 ELSE 0 END) AS tiene_despues
        FROM vi_registro
        GROUP BY pos_id, mercaderista, fecha
    ) rv
        ON  rv.pos_id = l.pos_id
        AND rv.mercaderista = l.mercaderista
        AND STR_TO_DATE(rv.fecha, '%d/%m/%Y') = l.fecha_visita
    WHERE l.fecha_visita BETWEEN '$fecha_inicio' AND '$fecha_fin'
    AND   l.mercaderista NOT LIKE '%PRUEBA%'
    AND   $filtroSupervisor
    AND   $filtroGestor
    GROUP BY l.mercaderista, l.fecha_visita
    ORDER BY l.fecha_visita DESC, LOWER(l.mercaderista) ASC
";

$rows = [];
if ($sql = $mysqli->prepare($query)) {
    $sql->execute();
    $sql->store_result();
    if ($sql->num_rows > 0) {
        $sql->bind_result($fecha, $fecha_iso, $gestor_r, $asignado, $relevado, $con_relevo)
            or die($sql->error);
        while ($sql->fetch()) {
            $rows[] = [
                'fecha'        => $fecha,
                'fecha_iso'    => $fecha_iso,
                'gestor'       => $gestor_r,
                'asignado'     => (int)$asignado,
                'relevado'     => (int)$relevado,
                'con_relevo'   => (int)$con_relevo,
            ];
        }
    }
    $sql->close();
}

echo json_encode(['count' => count($rows), 'rows' => $rows]);
