<?php
// "Eliminar" un Acuerdo desde Historial nunca es un DELETE físico — se marca
// estado='anulado' (ya existía en el ENUM, ver CLAUDE.md), mismo patrón que
// repositorio_usuarios_acuerdos.status (activo/inactivo). listar_historial_acuerdos()
// no filtra por estado <> 'anulado' todavía, así que además se excluye acá.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$acuerdoId = (int) ($_POST['id'] ?? 0);
$usuarioSesion = $_SESSION['user_id'] ?? null;

if ($acuerdoId <= 0) {
	echo json_encode(['ok' => false, 'message' => 'Acuerdo inválido.']);
	exit;
}

// Mismo criterio de propiedad que Historial/Mis Borradores/generar_acta_pdf.php:
// nadie puede anular un acuerdo ajeno adivinando el id.
$stmt = $mysqli->prepare('SELECT creado_por FROM repositorio_acuerdos WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $acuerdoId);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila || (int) $fila['creado_por'] !== (int) $usuarioSesion) {
	echo json_encode(['ok' => false, 'message' => 'Acuerdo no encontrado.']);
	exit;
}

$stmt = $mysqli->prepare("UPDATE repositorio_acuerdos SET estado = 'anulado' WHERE id = ?");
$stmt->bind_param('i', $acuerdoId);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => (bool) $ok, 'message' => $ok ? 'Acuerdo eliminado.' : 'No se pudo eliminar el acuerdo.']);
?>
