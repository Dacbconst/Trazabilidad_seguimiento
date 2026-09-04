<?php
require_once __DIR__ . '/conexion/DataSource.php';
use Phppot\DataSource;

$database = new DataSource();
$conn = $database->getConnection();

header("Content-Type: application/json");

try {
    // Obtener la variable bandera
    $es_adicional = isset($_GET['es_adicional']) && $_GET['es_adicional'] === 'true';

    // Consulta base
    $query_base = "
        SELECT 
            rd.supervisor AS supervisor,
            rd.mercaderista AS mercaderista,
            GROUP_CONCAT(DISTINCT rd.distribuidor SEPARATOR ' | ') AS distribuidor,
            (
                SELECT FLOOR(SUM(rd_sub.cantidad_asignada))
                FROM repositorio_distributivo rd_sub
                WHERE rd_sub.mercaderista = rd.mercaderista
    ";

    // Filtrar por adicionales o excluirlos según la bandera
    if ($es_adicional) {
        $query_base .= " AND rd_sub.observaciones = 'Adicional'"; // Solo adicionales
    } else {
        $query_base .= " AND rd_sub.observaciones != 'Adicional'"; // Excluir adicionales
    }

    // Filtrar por mes actual en fecha_asignacion solo si NO es adicional
    if (!$es_adicional) {
        $query_base .= "
                  AND YEAR(rd_sub.fecha_asignacion) = YEAR(CURDATE())
                  AND MONTH(rd_sub.fecha_asignacion) = MONTH(CURDATE())
        ";
    }

    $query_base .= "
            ) AS cantidad_distribuida,
            (
                SELECT IFNULL(SUM(vt.cantidad_armada), 0)
                FROM validacion_tacticos vt
                JOIN repositorio_productos_onpacks rpo 
                  ON vt.tactico = rpo.sku
                WHERE vt.gestor = rd.mercaderista
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
                  AND MONTH(vt.fecha_creacion) = MONTH(CURDATE())
                  AND YEAR(vt.fecha_creacion) = YEAR(CURDATE())
        ";
    }

    $query_base .= "
            ) AS cantidad_armada
        FROM 
            repositorio_distributivo rd
        LEFT JOIN 
            insert_packs ins
            ON ins.usuario = rd.mercaderista
        WHERE 
            rd.mercaderista NOT IN ('LUCKY UIO', 'LUCKY GYE', 'PRUEBA GYE')
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
        GROUP BY 
            rd.supervisor, rd.mercaderista
        ORDER BY 
            rd.supervisor, rd.mercaderista;
    ";

    $result = $database->select($query_base);

    if ($result) {
        $tablaAvance = [];
        foreach ($result as $row) {
            $faltantes = $row['cantidad_distribuida'] - $row['cantidad_armada'];
            $avance = ($row['cantidad_distribuida'] > 0) 
                ? round(($row['cantidad_armada'] / $row['cantidad_distribuida']) * 100, 2) 
                : 0;

            $tablaAvance[] = [
                "supervisor" => $row['supervisor'],
                "mercaderista" => $row['mercaderista'],
                "distribuidor" => $row['distribuidor'],
                "cantidad_distribuida" => $row['cantidad_distribuida'],
                "cantidad_armada" => $row['cantidad_armada'],
                "faltantes" => max($faltantes, 0),
                "avance" => $avance . "%"
            ];
        }

        echo json_encode($tablaAvance, JSON_PRETTY_PRINT);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "No se encontraron registros"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error al procesar la solicitud", "details" => $e->getMessage()]);
}