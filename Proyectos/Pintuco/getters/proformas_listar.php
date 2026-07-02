<?php
// proformas_listar.php
//
// La fuente principal es insert_proyectos_contacto (todos los agendamientos),
// con LEFT JOIN a insert_proforma para traer los datos de la proforma si ya
// existe. Así aparecen en el módulo de Proforma TODOS los agendamientos desde
// fase 1, y cuando el promotor suba la proforma desde el celular el registro
// pasa automáticamente a fase 3 sin que nadie tenga que hacer nada extra.
//
// Sin ?id_agendamiento → todos los registros (el JS desduplicará si hay
//   múltiples ciclos de negociación por agendamiento con ultimosCiclos()).
// Con ?id_agendamiento=X → todos los ciclos de proforma de ese agendamiento
//   ordenados del más antiguo al más reciente (para el historial gris).
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Content-Type: application/json');
// Sin esto, el navegador (o un proxy de IIS/Azure) puede servir una
// respuesta cacheada de esta misma URL tras una acción que acaba de
// cambiar estado_proforma, mostrando datos viejos aunque el UPDATE en BD
// ya se haya aplicado correctamente.
header('Cache-Control: no-store, no-cache, must-revalidate');

include_once '../db_connect.php';

$estado          = isset($_GET['estado_proforma'])  ? $_GET['estado_proforma']      : '';
$usuario         = isset($_GET['usuario'])           ? $_GET['usuario']              : '';
$id_agendamiento = isset($_GET['id_agendamiento'])  ? (int)$_GET['id_agendamiento'] : 0;

// c.id AS agendamiento_id: siempre presente aunque no haya proforma todavía.
// foto_factura y fase_actual excluidos del SELECT hasta que existan en BD.
$selectBase = "SELECT
        c.id           AS agendamiento_id,
        c.codigo_pdv,
        c.pdv,
        c.contacto,
        c.empresa,
        c.direccion,
        c.latitud,
        c.longitud,
        c.telefono,
        c.usuario,
        c.estado_agenda,
        c.fecha_registro   AS contacto_fecha_registro,
        c.fecha_agendamiento,
        c.hora,
        c.tecnico,
        p.id,
        p.id_agendamiento,
        p.fecha_proforma,
        p.estado_proforma,
        p.evidencia,
        p.monto_validado,
        p.observaciones_auditoria,
        p.fecha_auditoria,
        p.caracteristica_visita,
        p.acompanamiento_tecnico,
        p.fecha_registro   AS proforma_fecha_registro
    FROM insert_proyectos_contacto c
    LEFT JOIN insert_proforma p ON p.id_agendamiento = c.id
    WHERE c.activar = 'SI'";

$registros = [];

function rellenarNulos(array &$fila): void {
    $fila['foto_factura'] = null;
    $fila['fase_actual']  = null;
}

if ($id_agendamiento > 0) {
    // Historial completo: todos los ciclos de proforma de un agendamiento,
    // ordenados del más antiguo al más reciente para el panel de historial.
    $sql = $mysqli->prepare($selectBase . " AND c.id = ? ORDER BY p.id ASC");
    if ($sql) {
        $sql->bind_param('i', $id_agendamiento);
        if ($sql->execute()) {
            $res = $sql->get_result();
            if ($res) {
                while ($fila = $res->fetch_assoc()) { rellenarNulos($fila); $registros[] = $fila; }
            }
        }
        $sql->close();
    }
} elseif ($estado === '' && $usuario === '') {
    // Sin filtros: $mysqli->query() directo — patrón probado de get_pdvs.php.
    $res = $mysqli->query($selectBase . " ORDER BY c.fecha_registro DESC");
    if ($res) {
        while ($fila = $res->fetch_assoc()) { rellenarNulos($fila); $registros[] = $fila; }
    }
} else {
    // Con filtros de estado_proforma y/o usuario.
    $where  = "";
    $params = [];
    $types  = "";
    if ($estado !== '') {
        $where   .= " AND p.estado_proforma = ?";
        $params[] = $estado;
        $types   .= "s";
    }
    if ($usuario !== '') {
        $where   .= " AND c.usuario = ?";
        $params[] = $usuario;
        $types   .= "s";
    }
    $sql = $mysqli->prepare($selectBase . $where . " ORDER BY c.fecha_registro DESC");
    if ($sql) {
        $bindArgs = [$types];
        foreach ($params as $k => $v) { $bindArgs[] = &$params[$k]; }
        call_user_func_array([$sql, 'bind_param'], $bindArgs);
        if ($sql->execute()) {
            $res = $sql->get_result();
            if ($res) {
                while ($fila = $res->fetch_assoc()) { rellenarNulos($fila); $registros[] = $fila; }
            }
        }
        $sql->close();
    }
}

echo json_encode(["data" => $registros], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
?>
