<?php
// proformas_listar.php — bandeja de auditoría de proformas (Web).
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Content-Type: application/json');

include_once '../db_connect.php';

// Limpieza de parámetros (se eliminaron espacios invisibles NBSP de la copia original)
$estado  = isset($_GET['estado_proforma']) ? $_GET['estado_proforma'] : '';
$usuario = isset($_GET['usuario'])         ? $_GET['usuario']         : '';

$selectBase = "SELECT
        p.id, p.id_agendamiento, p.codigo_pdv, p.usuario,
        p.fecha_proforma, p.estado_proforma, p.evidencia,
        p.caracteristica_visita, p.acompanamiento_tecnico,
        p.fecha_registro AS proforma_fecha_registro,
        c.pdv, c.contacto, c.empresa, c.direccion,
        c.latitud, c.longitud, c.telefono,
        c.fecha_registro AS contacto_fecha_registro,
        c.fecha_agendamiento, c.hora, c.tecnico
    FROM insert_proforma p
    LEFT JOIN insert_proyectos_contacto c ON p.id_agendamiento = c.id";

$registros = [];

function rellenarNulos(&$fila) {
    $fila['monto_validado']          = null;
    $fila['observaciones_auditoria'] = null;
    $fila['fecha_auditoria']         = null;
}

if ($estado === '' && $usuario === '') {
    // Caso sin filtros: consulta directa estable
    $query = $selectBase . " ORDER BY p.fecha_registro DESC";
    $res = $mysqli->query($query);
    if ($res) {
        while ($fila = $res->fetch_assoc()) {
            rellenarNulos($fila);
            $registros[] = $fila;
        }
    }
} else {
    // Con filtros: prepared statement compatible con paso por referencia estricto
    $where  = "WHERE 1=1";
    $params = [];
    $types  = "";

    if ($estado !== '') {
        $where  .= " AND p.estado_proforma = ?";
        $params[] = $estado;
        $types  .= "s";
    }
    if ($usuario !== '') {
        $where  .= " AND p.usuario = ?";
        $params[] = $usuario;
        $types  .= "s";
    }

    $query = $selectBase . " $where ORDER BY p.fecha_registro DESC";
    $sql = $mysqli->prepare($query);

    if ($sql) {
        if (!empty($types)) {
            $bindArgs = [$types];
            foreach ($params as $key => $value) {
                // Se fuerza el paso por referencia requerido por bind_param en PHP antiguo/IIS
                $bindArgs[] = &$params[$key];
            }
            call_user_func_array([$sql, 'bind_param'], $bindArgs);
        }

        if ($sql->execute()) {
            $res = $sql->get_result();
            if ($res) {
                while ($fila = $res->fetch_assoc()) {
                    rellenarNulos($fila);
                    $registros[] = $fila;
                }
            }
        }
        $sql->close();
    }
}

// Retorno seguro codificado en UTF-8 nativo para evitar respuestas vacías o JSON malformados
echo json_encode(["data" => $registros], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
?>
