<?php
// Incluir la clase DataSource
require_once __DIR__ . '/conexion/DataSource.php';

use Phppot\DataSource;

// Configurar cabeceras para devolver JSON
header("Content-Type: application/json");

try {
    // Crear instancia de la conexión
    $database = new DataSource();

    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["error" => "Método no permitido. Use POST."]);
        exit();
    }

    // Obtener los datos del POST
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;

    // Validar parámetros
    if (!$startDate || !$endDate) {
        http_response_code(400);
        echo json_encode(["error" => "Parámetros inválidos. Envíe 'start_date' y 'end_date'."]);
        exit();
    }

    // Consulta SQL para avance por ejecutivo
    $queryEjecutivo = "
        SELECT 
            rl.sales_executive AS jefatura, 
            rl.customer_owner AS ejecutivo, 
            GROUP_CONCAT(DISTINCT(ru.`user`)) AS mercaderistas, 
            COUNT(DISTINCT(ru.`user`)) AS cantidad_recursos, 
            IFNULL(SUM(DISTINCT rd.cantidad_asignada), 0) AS cantidad_asignada,
            IFNULL(SUM(DISTINCT vt.cantidad_armada), 0) AS cantidad_armada 
        FROM 
            repositorio_locales_dtt2 rl 
        INNER JOIN 
            insert_packs ins 
            ON ins.codigo = rl.pos_id 
        INNER JOIN 
            repositorio_usuario ru 
            ON ins.usuario = ru.`user` 
        LEFT JOIN 
            repositorio_distributivo rd 
            ON ru.`user` = rd.mercaderista 
        LEFT JOIN (
            SELECT 
                gestor, 
                SUM(cantidad_armada) AS cantidad_armada 
            FROM 
                validacion_tacticos 
            WHERE 
                DATE(fecha) BETWEEN ? AND ?
            GROUP BY 
                gestor
        ) vt 
        ON vt.gestor = ru.`user`
        WHERE 
            DATE(STR_TO_DATE(ins.fecha, '%d/%m/%Y')) BETWEEN ? AND ?
            AND rl.activar = 'SI' 
            AND rl.customer_owner != 'N/A' 
            AND ru.`status` = '1' 
            AND ru.`user` NOT IN ('LUCKY UIO', 'LUCKY GYE', 'PRUEBA GYE') 
        GROUP BY 
            rl.sales_executive, rl.customer_owner 
        ORDER BY 
            rl.sales_executive, rl.customer_owner;
    ";

    // Consulta SQL para avance por mercaderista
    $queryMercaderista = "
        SELECT 
            lrs.supervisor AS supervisor,
            ru.`user` AS mercaderista,
            IFNULL(rd.distribuidor, 'N/A') AS distribuidor,
            IFNULL(rd.cantidad_asignada, 0) AS cantidad_distribuida,
            (
                SELECT IFNULL(SUM(vt.cantidad_armada), 0)
                FROM validacion_tacticos vt
                WHERE vt.gestor = ru.`user`
                AND DATE(vt.fecha) BETWEEN ? AND ?
            ) AS cantidad_armada
        FROM 
            insert_packs ins
        INNER JOIN 
            repositorio_usuario ru 
            ON ins.usuario = ru.`user`
        LEFT JOIN 
            repositorio_distributivo rd 
            ON ru.`user` = rd.mercaderista
        LEFT JOIN 
            lvi_ruta_semanal lrs
            ON ru.`user` = lrs.mercaderista
        WHERE 
            DATE(STR_TO_DATE(ins.fecha, '%d/%m/%Y')) BETWEEN ? AND ?
            AND ru.`status` = '1'
            AND ru.`user` NOT IN ('LUCKY UIO', 'LUCKY GYE', 'PRUEBA GYE')
        GROUP BY 
            lrs.supervisor, ru.`user`, rd.distribuidor
        ORDER BY 
            lrs.supervisor, ru.`user`;
    ";

    // Ejecutar consultas
    $avanceEjecutivo = $database->select($queryEjecutivo, "ssss", [$startDate, $endDate, $startDate, $endDate]);
    $avanceMercaderista = $database->select($queryMercaderista, "ssss", [$startDate, $endDate, $startDate, $endDate]);

    // Validar resultados
    if (!$avanceEjecutivo && !$avanceMercaderista) {
        http_response_code(404);
        echo json_encode(["error" => "No se encontraron registros para las fechas proporcionadas."]);
        exit();
    }

    // Devolver resultados
    echo json_encode([
        "avance_ejecutivo" => $avanceEjecutivo,
        "avance_mercaderista" => $avanceMercaderista
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Manejo de errores
    http_response_code(500);
    echo json_encode(["error" => "Error al procesar la solicitud.", "details" => $e->getMessage()]);
}
