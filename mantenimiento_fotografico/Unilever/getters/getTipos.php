<?php
include_once '../db_connect.php';

// RQFOTOGRAFICODACB: solo los 3 tipos confirmados por el cliente (CAMPAÑA, CONVENIO, GANADAS)
$query = "
    SELECT tipo_norm
    FROM (
        SELECT
            CASE
                WHEN UPPER(TRIM(tipo)) IN ('CAMPAÑA', 'CAMPANA') THEN 'CAMPAÑA'
                ELSE UPPER(TRIM(tipo))
            END AS tipo_norm
        FROM insert_exhibiciones
        WHERE UPPER(TRIM(tipo)) IN ('CAMPAÑA', 'CAMPANA', 'CONVENIO', 'GANADAS')
    ) t
    GROUP BY tipo_norm
    ORDER BY tipo_norm
";

$html = "<option value='.'>Todos</option>";
if ($sql = $mysqli->prepare($query)) {
    $sql->execute();
    $sql->store_result();
    if ($sql->num_rows > 0) {
        $sql->bind_result($tipo_norm) or die($sql->error);
        while ($sql->fetch()) {
            $html .= "<option value='" . htmlspecialchars($tipo_norm) . "'>"
                   . htmlspecialchars($tipo_norm) . "</option>";
        }
    }
    $sql->close();
}
echo $html;
?>
