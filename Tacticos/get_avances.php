<?php
require_once __DIR__ . '/conexion/DataSource.php';
use Phppot\DataSource;

$database = new DataSource();
header('Content-Type: application/json');

ob_clean();

try {
    $es_adicional = isset($_GET['es_adicional']) && $_GET['es_adicional'] === 'true';
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
    $anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');

    $condicion_observaciones = $es_adicional ? "LIKE '%Adicional%'" : "NOT LIKE '%Adicional%'";
    $condicion_tipo_tactico = $es_adicional ? "= 'Adicional'" : "!= 'Adicional'";

    function executeQuery($db, $query) {
        $result = $db->select($query);
        if ($result === false) {
            throw new Exception("Error ejecutando consulta.");
        }
        return $result;
    }

    $query_regional = "
        SELECT
            a.region,
            a.cantidad_recursos,
            a.cantidad_distribuida,
            COALESCE(b.total_armada, 0) AS cantidad_armada,
            (a.cantidad_distribuida - COALESCE(b.total_armada, 0)) AS faltantes,
            CASE WHEN a.cantidad_distribuida = 0 THEN 0
                 ELSE ROUND((COALESCE(b.total_armada, 0) / a.cantidad_distribuida) * 100, 2)
            END AS avance
        FROM (
            SELECT rd.regional AS region,
                   COUNT(DISTINCT rd.mercaderista) AS cantidad_recursos,
                   SUM(rd.cantidad_asignada) AS cantidad_distribuida
            FROM repositorio_distributivo rd
            WHERE MONTH(rd.fecha_asignacion) = $mes
              AND YEAR(rd.fecha_asignacion) = $anio
              AND (rd.observaciones IS NULL OR rd.observaciones $condicion_observaciones)
            GROUP BY rd.regional
        ) a
        LEFT JOIN (
            SELECT rd.regional AS region,
                   SUM(vt_sub.cantidad_armada) AS total_armada
            FROM (
                SELECT gestor, SUM(cantidad_armada) AS cantidad_armada
                FROM validacion_tacticos
                WHERE mes_reporte = $mes AND anio_reporte = $anio AND tipo_tactico $condicion_tipo_tactico
                GROUP BY gestor
            ) vt_sub
            JOIN (
                SELECT DISTINCT regional, mercaderista
                FROM repositorio_distributivo
                WHERE MONTH(fecha_asignacion) = $mes AND YEAR(fecha_asignacion) = $anio
                  AND (observaciones IS NULL OR observaciones $condicion_observaciones)
            ) rd ON vt_sub.gestor = rd.mercaderista
            GROUP BY rd.regional
        ) b ON a.region = b.region;
      ";

    $query_ejecutivo = "
        
      SELECT 
        rd.jefatura,
        rd.ejecutivo,
        COUNT(DISTINCT rd.mercaderista) AS cantidad_recursos,
        SUM(DISTINCT IFNULL(vt.total_armado,0)) AS cantidad_armada, -- <= SUM DISTINCT para no duplicar!
        SUM(rd.cantidad_asignada) AS cantidad_distribuida
      FROM 
        repositorio_distributivo rd
      LEFT JOIN (
        SELECT 
          gestor,
          SUM(cantidad_armada) AS total_armado
        FROM 
          validacion_tacticos
        WHERE 
          mes_reporte = $mes
          AND anio_reporte = $anio
          AND tipo_tactico $condicion_tipo_tactico
        GROUP BY gestor
      ) vt ON vt.gestor = rd.mercaderista
      WHERE 
        MONTH(rd.fecha_asignacion) = $mes
        AND YEAR(rd.fecha_asignacion) = $anio
        AND (rd.observaciones IS NULL OR rd.observaciones $condicion_observaciones)
        AND rd.mercaderista NOT IN ('LUCKY UIO', 'LUCKY GYE', 'PRUEBA GYE')
      GROUP BY 
        rd.jefatura, rd.ejecutivo;

          ";


    $query_mercaderista = "
        SELECT 
            rd.supervisor,
            rd.mercaderista,
            GROUP_CONCAT(DISTINCT rd.distribuidor SEPARATOR ' | ') AS distribuidor,
            (
                SELECT FLOOR(SUM(rd_sub.cantidad_asignada))
                FROM repositorio_distributivo rd_sub
                WHERE rd_sub.mercaderista = rd.mercaderista
                    AND YEAR(rd_sub.fecha_asignacion) = $anio
                    AND MONTH(rd_sub.fecha_asignacion) = $mes
                    AND (rd_sub.observaciones IS NULL OR rd_sub.observaciones $condicion_observaciones)
            ) AS cantidad_distribuida,
            (
                SELECT IFNULL(SUM(vt.cantidad_armada), 0)
                FROM validacion_tacticos vt
                JOIN repositorio_productos_onpacks rpo ON vt.tactico = rpo.sku
                WHERE vt.gestor = rd.mercaderista
                    AND anio_reporte = $anio
                    AND mes_reporte = $mes
                    AND tipo_tactico $condicion_tipo_tactico
                    AND (rpo.sku NOT LIKE '%ADIC%' AND rpo.historico != 'Adicional')
            ) AS cantidad_armada
        FROM 
            repositorio_distributivo rd
        WHERE 
            YEAR(rd.fecha_asignacion) = $anio
            AND MONTH(rd.fecha_asignacion) = $mes
            AND rd.mercaderista NOT IN ('LUCKY UIO', 'LUCKY GYE', 'PRUEBA GYE')
            AND (rd.observaciones IS NULL OR rd.observaciones $condicion_observaciones)
        GROUP BY 
            rd.supervisor, rd.mercaderista;
    ";

    $data_regional = executeQuery($database, $query_regional);
    $data_ejecutivo = executeQuery($database, $query_ejecutivo);
    $data_mercaderista = executeQuery($database, $query_mercaderista);

    echo json_encode([
        "success" => true,
        "data" => [
            "regional" => $data_regional,
            "ejecutivo" => $data_ejecutivo,
            "mercaderista" => $data_mercaderista
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "line" => $e->getLine(),
        "file" => $e->getFile()
    ]);
}
