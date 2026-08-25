<?php
// Lista paginada de un repositorio (Rebate o Participación de Percha), con
// búsqueda — mismo patrón que listar_historial_acuerdos()/listar_usuarios_acuerdos().
// Solo superdesarrollador (mismo criterio que Liquidación/Gestión de Usuarios,
// ver CLAUDE.md "Módulo Repositorios").
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$tipo     = $_GET['tipo'] ?? '';
$busqueda = trim($_GET['q'] ?? '');
$pagina   = (int) ($_GET['pg'] ?? 1);

if (!in_array($tipo, ['rebate', 'participacion', 'cuotas'], true)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'message' => 'Tipo de repositorio inválido.']);
	exit;
}

if ($tipo === 'rebate') {
	$resultado = listar_repositorio_rebate($mysqli, $busqueda, $pagina);
} elseif ($tipo === 'participacion') {
	$resultado = listar_repositorio_participacion($mysqli, $busqueda, $pagina);
} else {
	$resultado = listar_repositorio_cuotas($mysqli, $busqueda, $pagina);
}

echo json_encode(['ok' => true] + $resultado);
?>
