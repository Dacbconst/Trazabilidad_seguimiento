<?php
include_once '../db_connect.php';

$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin    = $_POST['fecha_fin'];
$gestor       = $_POST['gestor'];
$supervisor   = $_POST['supervisor'] ?? '.';

$filtroSupervisor = ($supervisor === '.' || $supervisor === '') ? '1=1' : 'l.supervisor = ?';

$img_url_base = "https://luckyecuadorweb.blob.core.windows.net/app/AppUnilever/Inserts/";

// Detalle por PDV asignado al gestor en el día: estado del rutero, rutas de
// foto antes/después (cruce con vi_registro por pos_id) y exhibiciones (insert_exhibiciones)
$query = "SELECT
              l.pos_name, l.city, l.estado_visitado,
              MAX(CASE WHEN r.tipo = 'ENTRADA' AND r.foto_antes IS NOT NULL AND r.foto_antes != ''
                            AND r.foto_antes != 'N/A' THEN r.foto_antes END)   AS foto_antes,
              MAX(CASE WHEN r.tipo = 'SALIDA' AND r.foto_despues IS NOT NULL AND r.foto_despues != ''
                            AND r.foto_despues != 'N/A' THEN r.foto_despues END) AS foto_despues,
              MAX(ex.fotos) AS exh_fotos,
              MAX(ex.total) AS exh_total
          FROM lvi_rutero l
          LEFT JOIN vi_registro r
              ON  l.pos_id = r.pos_id
              AND l.mercaderista = r.mercaderista
              AND l.fecha_visita = STR_TO_DATE(r.fecha, '%d/%m/%Y')
          LEFT JOIN (
              SELECT codigo, fecha,
                     GROUP_CONCAT(DISTINCT foto SEPARATOR '|') AS fotos,
                     COUNT(DISTINCT foto) AS total
              FROM insert_exhibiciones
              GROUP BY codigo, fecha
          ) ex
              ON  ex.codigo = l.pos_id
              AND STR_TO_DATE(ex.fecha, '%d/%m/%Y') = l.fecha_visita
          WHERE l.fecha_visita BETWEEN ? AND ?
          AND l.mercaderista = ?
          AND $filtroSupervisor
          GROUP BY l.pos_id, l.pos_name, l.city, l.estado_visitado
          ORDER BY LOWER(l.pos_name) ASC";

$rows = [];
if ($sql = $mysqli->prepare($query)) {
    if ($filtroSupervisor === '1=1') {
        $sql->bind_param('sss', $fecha_inicio, $fecha_fin, $gestor);
    } else {
        $sql->bind_param('ssss', $fecha_inicio, $fecha_fin, $gestor, $supervisor);
    }
    $sql->execute();
    $sql->bind_result($pos_name, $city, $estado, $foto_antes, $foto_despues, $exh_fotos, $exh_total);
    while ($sql->fetch()) {
        $exh_arr   = $exh_fotos ? array_filter(explode('|', $exh_fotos)) : [];
        $exh_first = !empty($exh_arr) ? $img_url_base . reset($exh_arr) : null;

        $rows[] = [
            'pos_name'     => $pos_name,
            'city'         => $city,
            'estado'       => $estado,
            'tiene_antes'  => $foto_antes   ? 1 : 0,
            'tiene_despues'=> $foto_despues ? 1 : 0,
            'foto_antes'   => $foto_antes   ? $img_url_base . $foto_antes   : null,
            'foto_despues' => $foto_despues ? $img_url_base . $foto_despues : null,
            'tiene_exh'    => $exh_total ? 1 : 0,
            'exh_total'    => (int)$exh_total,
            'exh_foto'     => $exh_first,
        ];
    }
    $sql->close();
}

echo json_encode(['count' => count($rows), 'rows' => $rows]);
