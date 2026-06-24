<?php
// RQFOTOGRAFICODACB - process_report.php simplificado para Unilever: solo módulo exhibiciones
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth/Session.php';
require_once __DIR__ . '/reports.php';

sec_session_start();

$fechaInicio = $_POST["fechaInicio"] ?? '';
$fechaFin    = $_POST["fechaFin"]    ?? '';
$modulo      = $_POST["modulo"]      ?? '';

switch ($modulo) {
    case 'exhibiciones_excel':
        exhibiciones_reporte($fechaInicio, $fechaFin, $mysqli);
        break;
    default:
        echo 'Solicitud no válida';
        break;
}
