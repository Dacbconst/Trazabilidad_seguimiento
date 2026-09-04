<?php
require_once __DIR__ . '/conexion/DataSource.php';
use Phppot\DataSource;

$database = new DataSource();
header('Content-Type: application/json');
ob_clean();

$mes = isset($_GET['mes']) ? intval($_GET['mes']) : 1;
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : 2025;
$es_adicional = isset($_GET['es_adicional']) && $_GET['es_adicional'] === 'true';

$condicion_observaciones = $es_adicional ? "LIKE '%Adicional%'" : "NOT LIKE '%Adicional%'";
$condicion_tipo_tactico   = $es_adicional ? "= 'Adicional'" : "!= 'Adicional'";

// 1. Total crudo de validacion_tacticos (sin joins)
$q1 = "SELECT COUNT(*) as filas, SUM(cantidad_armada) as total_armada
        FROM validacion_tacticos
        WHERE mes_reporte = $mes AND anio_reporte = $anio
          AND tipo_tactico $condicion_tipo_tactico";

// 2. Total crudo de repositorio_distributivo (sin joins)
$q2 = "SELECT COUNT(*) as filas, SUM(cantidad_asignada) as total_distribuida,
               COUNT(DISTINCT mercaderista) as recursos
        FROM repositorio_distributivo
        WHERE MONTH(fecha_asignacion) = $mes AND YEAR(fecha_asignacion) = $anio
          AND (observaciones IS NULL OR observaciones $condicion_observaciones)
          AND mercaderista NOT IN ('LUCKY UIO', 'LUCKY GYE', 'PRUEBA GYE')";

// 3. Ver si hay gestores en validacion_tacticos que NO están en repositorio_distributivo
$q3 = "SELECT DISTINCT vt.gestor, SUM(vt.cantidad_armada) as armada
        FROM validacion_tacticos vt
        WHERE vt.mes_reporte = $mes AND vt.anio_reporte = $anio
          AND vt.tipo_tactico $condicion_tipo_tactico
          AND vt.gestor NOT IN (
              SELECT DISTINCT mercaderista FROM repositorio_distributivo
              WHERE MONTH(fecha_asignacion) = $mes AND YEAR(fecha_asignacion) = $anio
                AND (observaciones IS NULL OR observaciones $condicion_observaciones)
          )
        GROUP BY vt.gestor";

// 4. Ver si hay gestores con registros DUPLICADOS en validacion_tacticos
$q4 = "SELECT gestor, tactico, COUNT(*) as repeticiones, SUM(cantidad_armada) as total
        FROM validacion_tacticos
        WHERE mes_reporte = $mes AND anio_reporte = $anio
          AND tipo_tactico $condicion_tipo_tactico
        GROUP BY gestor, tactico
        HAVING COUNT(*) > 1
        ORDER BY repeticiones DESC
        LIMIT 20";

// 5. Ver si un mercaderista aparece en múltiples regionales (causa de multiplicación en JOINs)
$q5 = "SELECT mercaderista, COUNT(DISTINCT regional) as regionales
        FROM repositorio_distributivo
        WHERE MONTH(fecha_asignacion) = $mes AND YEAR(fecha_asignacion) = $anio
          AND (observaciones IS NULL OR observaciones $condicion_observaciones)
        GROUP BY mercaderista
        HAVING COUNT(DISTINCT regional) > 1";

function runQ($db, $q) {
    $r = $db->select($q);
    return $r ?: [];
}

echo json_encode([
    "parametros" => compact('mes', 'anio', 'es_adicional', 'condicion_tipo_tactico'),
    "1_total_crudo_validacion_tacticos"    => runQ($database, $q1),
    "2_total_crudo_repositorio_distributivo" => runQ($database, $q2),
    "3_gestores_sin_match_en_distributivo" => runQ($database, $q3),
    "4_tacticos_duplicados"                => runQ($database, $q4),
    "5_mercaderistas_en_multiples_regionales" => runQ($database, $q5),
], JSON_PRETTY_PRINT);