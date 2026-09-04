<?php
use Phppot\DataSource;

require_once 'conexion/DataSource.php';
$db = new DataSource();
$conn = $db->getConnection();

header("Content-Type: application/json");

try {
    // Obtener la variable bandera
    $es_adicional = isset($_GET['es_adicional']) && $_GET['es_adicional'] === 'true';

    // Consulta base
    $query_base = "
        SELECT
            a.region,
            a.cantidad_recursos,
            a.cantidad_distribuida,
            COALESCE(b.total_armada, 0) AS cantidad_armada,
            (a.cantidad_distribuida - COALESCE(b.total_armada, 0)) AS faltantes,
            CASE 
                WHEN a.cantidad_distribuida = 0 THEN 0
                ELSE ROUND(
                    (COALESCE(b.total_armada, 0) / a.cantidad_distribuida) * 100
                , 2)
            END AS avance
        FROM 
        (
            /* Subconsulta A: Cantidad de recursos y cantidad distribuida */
            SELECT 
                rd.regional AS region,
                COUNT(DISTINCT rd.mercaderista) AS cantidad_recursos,
                SUM(rd.cantidad_asignada) AS cantidad_distribuida
            FROM repositorio_distributivo rd
            WHERE 1=1
    ";

    // Filtrar por adicionales o excluirlos según la bandera
    if ($es_adicional) {
        $query_base .= " AND rd.observaciones = 'Adicional'"; // Solo adicionales
    } else {
        $query_base .= " AND rd.observaciones != 'Adicional'"; // Excluir adicionales
    }

    // Filtrar por mes actual en fecha_asignacion solo si NO es adicional
    if (!$es_adicional) {
        $query_base .= "
              AND YEAR(rd.fecha_asignacion) = YEAR(CURDATE())
              AND MONTH(rd.fecha_asignacion) = MONTH(CURDATE())
        ";
    }

    $query_base .= "
            GROUP BY rd.regional
        ) AS a
        LEFT JOIN
        (
            /* Subconsulta B: Cantidad armada */
            SELECT 
                rd.regional AS region,
                SUM(vt.cantidad_armada) AS total_armada
            FROM (
                SELECT 
                    gestor, 
                    SUM(cantidad_armada) AS cantidad_armada
                FROM validacion_tacticos vt
                JOIN repositorio_productos_onpacks rpo 
                  ON vt.tactico = rpo.sku
                WHERE 1=1
    ";

    // Filtrar por adicionales o excluirlos según la bandera
    if ($es_adicional) {
        $query_base .= " AND (rpo.sku LIKE '%ADIC%' OR rpo.historico = 'Adicional')"; // Solo tácticos adicionales
    } else {
        $query_base .= " AND (rpo.sku NOT LIKE '%ADIC%' AND rpo.historico != 'Adicional')"; // Excluir tácticos adicionales
    }

    // Filtrar por mes actual en fecha_creacion solo si NO es adicional
    if (!$es_adicional) {
        $query_base .= "
                  AND YEAR(vt.fecha_creacion) = YEAR(CURDATE())
                  AND MONTH(vt.fecha_creacion) = MONTH(CURDATE())
        ";
    }

    $query_base .= "
                GROUP BY gestor
            ) vt
            JOIN (
                SELECT DISTINCT
                    regional,
                    mercaderista
                FROM repositorio_distributivo
                WHERE 1=1
    ";

    // Filtrar por adicionales o excluirlos según la bandera
    if ($es_adicional) {
        $query_base .= " AND observaciones = 'Adicional'"; // Solo adicionales
    } else {
        $query_base .= " AND observaciones != 'Adicional'"; // Excluir adicionales
    }

    // Filtrar por mes actual en fecha_asignacion solo si NO es adicional
    if (!$es_adicional) {
        $query_base .= "
              AND YEAR(fecha_asignacion) = YEAR(CURDATE())
              AND MONTH(fecha_asignacion) = MONTH(CURDATE())
        ";
    }

    $query_base .= "
            ) rd 
              ON vt.gestor = rd.mercaderista
            GROUP BY rd.regional
        ) AS b
          ON a.region = b.region
        ORDER BY a.region;
    ";

    $data = $db->select($query_base);

    if ($data === false) {
        throw new Exception("Error al obtener el avance por región.");
    }

    echo json_encode(["success" => true, "data" => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}