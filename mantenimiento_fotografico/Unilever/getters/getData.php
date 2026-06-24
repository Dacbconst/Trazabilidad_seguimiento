<?php
// RQFOTOGRAFICODACB - getData reescrito siguiendo patron Pinguino: usa GET, retorna JSON {html, count}
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

include_once '../db_connect.php';

// Recibe parametros por GET (patron Pinguino)
$fecha_inicio = $_GET['fecha_inicio'];
$fecha_fin    = $_GET['fecha_fin'];
$reporte      = $_GET['reporte'];      // vista a consultar
$supervisor   = $_GET['supervisor'];
$mercaderista = $_GET['mercaderista'];
$cadena       = $_GET['cadena'];
$ciudad       = $_GET['ciudad'];
$local        = $_GET['local'];

// URL base del blob de Unilever (confirmada desde ConveniosFragment.java)
$img_url_base    = "https://luckyecuadorweb.blob.core.windows.net/app/AppUnilever/Inserts/";
$img_url_no_foto = $img_url_base . "NO_FOTO.png";

// Consulta: vi_evidencias tiene foto_antes + foto_despues + mercaderista + categoria
$query = "SELECT id, fecha, hora, mercaderista, city, pos_name, address,
                 foto_antes, foto_despues, '' AS logro, comentario AS status, categoria AS tipo
          FROM " . $reporte . "
          WHERE (STR_TO_DATE(fecha, '%d/%m/%Y') BETWEEN '" . $fecha_inicio . "' AND '" . $fecha_fin . "')
          AND supervisor REGEXP '" . $supervisor . "'
          AND mercaderista REGEXP '" . $mercaderista . "'
          AND (COALESCE(customer_owner, 'Sin Cadena') REGEXP '" . $cadena . "')
          AND city REGEXP '" . $ciudad . "'
          AND pos_name REGEXP '" . $local . "'
          ORDER BY fecha, hora";

$html     = "";
$contador = 0;
$total    = 0;

if ($sql = $mysqli->prepare($query)) {
    $sql->execute();
    $sql->store_result();
    $total = $sql->num_rows;

    if ($total > 0) {
        $sql->bind_result($id, $fecha, $hora, $merc, $city, $pos_name, $address,
                          $foto_antes, $foto_despues, $logro, $status, $tipo);

        while ($sql->fetch()) {
            if ($contador == 0) $html .= "<tr>";
            if ($contador == 3) { $html .= "</tr><tr>"; $contador = 0; }

            // Construir URLs de imagen
            $srcAntes   = empty($foto_antes)   ? $img_url_no_foto : $img_url_base . $foto_antes;
            $srcDespues = empty($foto_despues) ? $img_url_no_foto : $img_url_base . $foto_despues;
            $tieneImagen = !empty($foto_despues) && $foto_despues !== 'NO_FOTO.png';

            // Renderizar la card desde el template — sin HTML en strings PHP
            ob_start();
            include __DIR__ . '/../components/card-evidencias.php';
            $html .= ob_get_clean();
            $contador++;
        }

        if ($contador > 0) {
            while ($contador < 3) { $html .= "<td></td>"; $contador++; }
            $html .= "</tr>";
        }
    }
    $sql->close();
}

echo json_encode(["html" => $html, "count" => $total]);
?>
