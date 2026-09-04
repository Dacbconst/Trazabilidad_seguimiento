<?php
require_once __DIR__ . '/conexion/DataSource.php';
use Phppot\DataSource;

$database = new DataSource();
$conn = $database->getConnection();

header("Content-Type: application/json");

try {
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
    $anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');
    $es_adicional = isset($_GET['es_adicional']) && $_GET['es_adicional'] === 'true';
    $jefatura = $_GET['jefatura'] ?? '';
    $ejecutivo = $_GET['ejecutivo'] ?? '';

    if (!$jefatura || !$ejecutivo) {
        throw new Exception("Faltan parámetros requeridos.");
    }

    // Condiciones dinámicas
    $cond_observaciones = $es_adicional ? "= 'Adicional'" : "!= 'Adicional'";
    $cond_productos = $es_adicional
        ? "AND (rpo.sku LIKE '%ADIC%' OR rpo.historico = 'Adicional')"
        : "AND (rpo.sku NOT LIKE '%ADIC%' AND rpo.historico != 'Adicional')";
    $cond_fecha_armado = !$es_adicional
        ? "AND MONTH(vt.fecha_creacion) = ? AND YEAR(vt.fecha_creacion) = ?"
        : "";
    $cond_fecha_distribucion = !$es_adicional
        ? "AND MONTH(STR_TO_DATE(ins.fecha, '%d/%m/%Y')) = ? AND YEAR(STR_TO_DATE(ins.fecha, '%d/%m/%Y')) = ?"
        : "";

    $sql = "
        SELECT 
            rd.mercaderista,
            GROUP_CONCAT(DISTINCT rd.distribuidor SEPARATOR ' | ') AS distribuidor,

            (
                SELECT FLOOR(SUM(rd_sub.cantidad_asignada))
                FROM repositorio_distributivo rd_sub
                WHERE rd_sub.mercaderista = rd.mercaderista
                  AND rd_sub.observaciones $cond_observaciones
                  AND MONTH(rd_sub.fecha_asignacion) = ?
                  AND YEAR(rd_sub.fecha_asignacion) = ?
            ) AS cantidad_distribuida,

            (
                SELECT IFNULL(SUM(vt.cantidad_armada), 0)
                FROM validacion_tacticos vt
                JOIN repositorio_productos_onpacks rpo ON vt.tactico = rpo.sku
                WHERE vt.gestor = rd.mercaderista
                  $cond_productos
                  $cond_fecha_armado
            ) AS cantidad_armada

        FROM repositorio_distributivo rd
        LEFT JOIN insert_packs ins ON ins.usuario = rd.mercaderista
        LEFT JOIN repositorio_locales_dtt2 rl ON ins.codigo = rl.pos_id
        WHERE rl.customer_owner = ?
          AND rl.sales_executive = ?
          AND rl.activar = 'SI'
          AND rd.mercaderista NOT IN ('LUCKY UIO', 'LUCKY GYE', 'PRUEBA GYE')
          AND rd.observaciones $cond_observaciones
          $cond_fecha_distribucion

        GROUP BY rd.mercaderista
        ORDER BY rd.mercaderista
    ";

    $params = [];
    $types = 'ii'; // distribución (rd_sub.fecha_asignacion)
    array_push($params, $mes, $anio);

    if (!$es_adicional) {
        $types .= 'ii'; // armado (vt.fecha_creacion)
        array_push($params, $mes, $anio);
    }

    $types .= 'ss'; // ejecutivo y jefatura
    array_push($params, $ejecutivo, $jefatura);

    if (!$es_adicional) {
        $types .= 'ii'; // insert_packs (ins.fecha)
        array_push($params, $mes, $anio);
    }



    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $response = [];
    while ($row = $result->fetch_assoc()) {
        $faltantes = max($row['cantidad_distribuida'] - $row['cantidad_armada'], 0);
        $avance = ($row['cantidad_distribuida'] > 0)
            ? round(($row['cantidad_armada'] / $row['cantidad_distribuida']) * 100, 2)
            : 0;

        $response[] = [
            "mercaderista" => $row['mercaderista'],
            "distribuidor" => $row['distribuidor'],
            "cantidad_distribuida" => intval($row['cantidad_distribuida']),
            "cantidad_armada" => intval($row['cantidad_armada']),
            "faltantes" => $faltantes,
            "avance" => $avance
        ];
    }

    echo json_encode(["success" => true, "data" => $response], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
