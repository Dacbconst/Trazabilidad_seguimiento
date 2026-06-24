<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

include_once '../db_connect.php';

function filtroExh(string $campo, string $valor): string {
    if ($valor === "." || $valor === "") {
        return "$campo REGEXP '.'";
    }
    if ($valor === "Sin Cadena") {
        return "($campo IS NULL OR $campo = 'Sin Cadena')";
    }
    return "$campo = '{$valor}'";
}

$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '.';
$fecha_fin    = isset($_GET['fecha_fin'])    ? $_GET['fecha_fin']    : '.';
$supervisor   = isset($_GET['supervisor'])   ? $_GET['supervisor']   : '.';
$mercaderista = isset($_GET['mercaderista']) ? $_GET['mercaderista'] : '.';
$cadena       = isset($_GET['cadena'])       ? $_GET['cadena']       : '.';
$ciudad       = isset($_GET['ciudad'])       ? $_GET['ciudad']       : '.';
$local        = isset($_GET['local'])        ? $_GET['local']        : '.';
$tipo         = isset($_GET['tipo'])         ? $_GET['tipo']         : '.';
$categoria    = isset($_GET['categoria'])    ? $_GET['categoria']    : '.';

// RQFOTOGRAFICODACB: filtroTipo maneja NULL (Sin Tipo) y normaliza duplicados
function filtroTipoExh(string $valor): string {
    if ($valor === '.' || $valor === '') return "1=1";
    if ($valor === 'Sin Tipo')          return "ins.tipo IS NULL";
    if ($valor === 'GESTIONADA')        return "(TRIM(ins.tipo) IN ('GESTIONADA','GESTIONADAS'))";
    if ($valor === 'PAGADA')            return "(TRIM(ins.tipo) IN ('PAGADA','PAGADAS'))";
    return "TRIM(ins.tipo) = '" . $valor . "'";
}

$img_url = "https://luckyecuadorweb.blob.core.windows.net/app/AppUnilever/Inserts/";

// Supervisor y mercaderista vienen de insert_exhibiciones (tienen el nombre real).
// repositorio_locales_dtt2 aporta: city, address, channel, pos_name, activar.
// RQFOTOGRAFICODACB - subquery en rpdv para eliminar duplicados antes del JOIN
$query = "
SELECT
    ins.fecha,
    MIN(ins.hora) AS hora,
    MIN(rpdv.pos_name) AS pos_name,
    MIN(rpdv.city) AS city,
    MIN(rpdv.address) AS address,
    MIN(ins.supervisor) AS supervisor,
    ins.usuario,
    ins.codigo,
    MIN(ins.categoria) AS categoria,
    MIN(ins.subcategoria) AS subcategoria,
    MIN(ins.brand) AS brand,
    GROUP_CONCAT(DISTINCT ins.tipo_exh ORDER BY ins.tipo_exh SEPARATOR ', ') AS tipo_exh,
    MIN(ins.zona_exh) AS zona_exh,
    MIN(ins.condicion) AS condicion,
    COALESCE(GROUP_CONCAT(ins.foto ORDER BY ins.fechaservidor SEPARATOR '|'), '') AS fotos,
    COALESCE(GROUP_CONCAT(COALESCE(ins.tipo_exh,'') ORDER BY ins.fechaservidor SEPARATOR '|'), '') AS tipos,
    COALESCE(GROUP_CONCAT(COALESCE(ins.categoria,'') ORDER BY ins.fechaservidor SEPARATOR '|'), '') AS categorias,
    COALESCE(GROUP_CONCAT(COALESCE(ins.subcategoria,'') ORDER BY ins.fechaservidor SEPARATOR '|'), '') AS subcategorias
FROM insert_exhibiciones ins
INNER JOIN (
    SELECT DISTINCT pos_id, MIN(pos_name) AS pos_name, MIN(city) AS city,
                    MIN(address) AS address, MIN(channel_segment) AS channel_segment
    FROM repositorio_locales_dtt2
    WHERE activar = 'SI'
    GROUP BY pos_id
) rpdv ON ins.codigo = rpdv.pos_id
WHERE (STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN '{$fecha_inicio}' AND '{$fecha_fin}')
AND " . filtroExh("ins.supervisor",                         $supervisor)   . "
AND " . filtroExh("ins.usuario",                            $mercaderista) . "
AND " . filtroExh("COALESCE(rpdv.channel_segment,'Sin Cadena')", $cadena) . "
AND " . filtroExh("rpdv.city",                              $ciudad)       . "
AND " . filtroExh("rpdv.pos_name",                          $local)        . "
AND " . filtroTipoExh($tipo) . "
AND (" . ($categoria === '.' || $categoria === '' ? "1=1" : "ins.categoria = '$categoria'") . ")
GROUP BY ins.codigo, ins.usuario, ins.fecha, ins.categoria, ins.subcategoria
ORDER BY ins.fecha, hora
";

$html    = "";
$counter = 0;

if ($sql = $mysqli->prepare($query)) {
    $sql->execute();
    $sql->store_result();

    if ($sql->num_rows > 0) {

        // RQFOTOGRAFICODACB - siempre 3 columnas fijas
        $cols = 3; $td_width = "33.33%"; $td_margin = "0";

        $sql->bind_result(
            $fecha, $hora, $pos_name, $city, $address,
            $supervisor_r, $mercaderista_r, $codigo,
            $categoria, $subcategoria, $brand,
            $tipo_exh, $zona_exh, $condicion,
            $fotos_concat, $tipos_concat, $categorias_concat, $subcategorias_concat
        );

        while ($sql->fetch()) {
            // URLs simples para el carousel (no cambiar este formato)
            $fotos_arr  = array_slice(array_filter(explode('|', $fotos_concat ?? '')), 0, 6);
            $fotos_json = json_encode(array_map(function($f) use ($img_url) { return $img_url . $f; }, $fotos_arr));
            $first_foto = !empty($fotos_arr) ? $img_url . $fotos_arr[0] : '';
            $tipos_limpio      = $tipos_concat ?? '';
            $categorias_limpio = $categorias_concat ?? '';
            $subcats_limpio    = $subcategorias_concat ?? '';
            // Primera categoría/subcategoría para mostrar inicialmente en el footer
            $cat_arr  = explode('|', $categorias_limpio);
            $scat_arr = explode('|', $subcats_limpio);
            $cat_inicial  = $cat_arr[0] ?? '';
            $scat_inicial = $scat_arr[0] ?? '';
            $card_id     = "exh_" . $counter;

            if ($counter % $cols === 0) {
                $html .= "<tr>";
            }

            // Renderizar la card desde el template — sin HTML en strings PHP
            ob_start();
            include __DIR__ . '/../components/card-exhibiciones.php';
            $html .= ob_get_clean();

            $counter++;
            if ($counter % $cols === 0) {
                $html .= "</tr>";
            }
        }

        // Cerrar última fila y rellenar con celdas vacías para mantener proporción
        if ($counter % $cols !== 0) {
            $remaining = $cols - ($counter % $cols);
            for ($i = 0; $i < $remaining; $i++) {
                $html .= "<td style='padding:1.2%; width:{$td_width};'></td>";
            }
            $html .= "</tr>";
        }
    }

    $sql->close();
} else {
    error_log("getDataExhibiciones Unilever error: " . $mysqli->error);
}

echo json_encode(["html" => $html, "count" => $counter]);
?>
