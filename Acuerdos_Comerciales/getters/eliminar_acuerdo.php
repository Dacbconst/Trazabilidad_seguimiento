<?php
// Nunca DELETE físico: marca estado='anulado'. listar_historial_acuerdos() no filtra ese estado todavía, así que se excluye acá también.
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
$rolSesion = $_SESSION['rol'] ?? null;

if ($acuerdoId <= 0) {
	echo json_encode(['ok' => false, 'message' => 'Acuerdo inválido.']);
	exit;
}

// Dueño del acuerdo, salvo superdesarrollador (ve/administra todo, igual que en Historial).
$stmt = $mysqli->prepare('SELECT creado_por FROM repositorio_acuerdos WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $acuerdoId);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

$esDueno = $fila && (int) $fila['creado_por'] === (int) $usuarioSesion;
$esAdmin = $rolSesion === 'superdesarrollador';
if (!$fila || (!$esDueno && !$esAdmin)) {
	echo json_encode(['ok' => false, 'message' => 'Acuerdo no encontrado.']);
	exit;
}

$stmt = $mysqli->prepare("UPDATE repositorio_acuerdos SET estado = 'anulado' WHERE id = ?");
$stmt->bind_param('i', $acuerdoId);
$ok = $stmt->execute();
$stmt->close();

// Libera las filas de origen (Cuotas/Acuerdo Completo) que quedaron en 'usada' apuntando a esta Acta — bug real: sin esto, quedaban bloqueadas para siempre aunque la Acta ya no existiera.
if ($ok) {
	foreach (['repositorio_cuota_cliente', 'repositorio_acuerdo_completo_linea'] as $tabla) {
		$stmtLiberar = $mysqli->prepare(
			"UPDATE $tabla SET estado = 'pendiente_uso', acuerdo_id_generado = NULL WHERE acuerdo_id_generado = ? AND estado = 'usada'"
		);
		if ($stmtLiberar) {
			$stmtLiberar->bind_param('i', $acuerdoId);
			$stmtLiberar->execute();
			$stmtLiberar->close();
		}
	}
}

echo json_encode(['ok' => (bool) $ok, 'message' => $ok ? 'Acuerdo eliminado.' : 'No se pudo eliminar el acuerdo.']);
?>
