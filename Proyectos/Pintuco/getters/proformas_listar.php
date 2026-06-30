<?php
// proformas_listar.php — bandeja de auditoría de proformas (Web).
// Mismo estilo que get_pdvs.php / get_contactados.php: $mysqli->query()
// directo para el caso sin filtros (el más común), y prepare/execute
// SOLO cuando llegan parámetros de filtro, con verificación en cada paso
// para evitar el fatal error que causaba antes (get_result() sobre un
// stmt cuyo execute() falló devuelve false, y false->fetch_assoc() es
// un fatal error en PHP que IIS sirve como HTML de error, rompiendo
// el JSON del lado del cliente con "Unexpected token '<'").
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Content-Type: application/json');

include_once '../db_connect.php';

$estado  = isset($_GET['estado_proforma']) ? $_GET['estado_proforma'] : '';
$usuario = isset($_GET['usuario'])         ? $_GET['usuario']         : '';

// Las columnas monto_validado / observaciones_auditoria / fecha_auditoria
// todavía no existen en insert_proforma (falta correr el ALTER TABLE — ver
// memoria del proyecto). Se excluyen del SELECT y se rellenan con null en
// PHP para que el JS las reciba sin romperse: así la bandeja funciona ya
// aunque las columnas no estén, y cuando se agreguen solo hay que quitar
// los null de aquí.
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
    // Caso más común: sin filtros — $mysqli->query() directo,
    // igual que get_pdvs.php que sí funciona.
    $query = $selectBase . " ORDER BY p.fecha_registro DESC";
    $res = $mysqli->query($query);
    if ($res) {
        while ($fila = $res->fetch_assoc()) {
            rellenarNulos($fila);
            $registros[] = $fila;
        }
    }
} else {
    // Con filtros: prepared statement con verificación en cada paso.
    $where  = "WHERE 1=1";
    $params = [];
    $types  = "";
    if ($estado !== '') {
        $where   .= " AND p.estado_proforma = ?";
        $params[] = $estado;
        $types   .= "s";
    }
    if ($usuario !== '') {
        $where   .= " AND p.usuario = ?";
        $params[] = $usuario;
        $types   .= "s";
    }
    $query = $selectBase . " $where ORDER BY p.fecha_registro DESC";
    $sql = $mysqli->prepare($query);
    if ($sql) {
        $sql->bind_param($types, ...$params);
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

echo json_encode(["data" => $registros]);
?>
