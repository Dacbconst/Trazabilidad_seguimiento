<?php
// TEMPORAL: panel para simular el vencimiento de firma (20 días) sin esperar de verdad.
// Borrar este archivo + getters/_dev_simular_vencimiento.php cuando termine la prueba.
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	header('Location: login.php');
	exit;
}

$usuarioId = $_SESSION['user_id'];
$stmt = $mysqli->prepare(
	"SELECT id, documento_no, estado, fecha_generacion,
	        DATEDIFF(DATE_ADD(fecha_generacion, INTERVAL 20 DAY), CURDATE()) AS dias_restantes
	 FROM repositorio_acuerdos
	 WHERE creado_por = ? AND estado IN ('generado', 'enviado', 'vencido')
	 ORDER BY fecha_generacion DESC"
);
$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$acuerdos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Panel de prueba — Vencimiento de firma</title>
<style>
	body { font-family: system-ui, sans-serif; max-width: 900px; margin: 32px auto; padding: 0 16px; color: #222; }
	h1 { font-size: 20px; }
	.aviso { background: #fff2cc; border: 1px solid #e0c46b; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; }
	table { width: 100%; border-collapse: collapse; margin-top: 16px; }
	th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #ddd; font-size: 14px; }
	th { background: #f5f5f5; }
	.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
	.b-generado, .b-enviado { background: #fff2cc; color: #7a5b00; }
	.b-vencido { background: #f7d7d7; color: #8a1c1c; }
	button { padding: 6px 10px; border-radius: 4px; border: 1px solid #ccc; background: #fff; cursor: pointer; font-size: 12px; margin-right: 4px; }
	button:hover { background: #f0f0f0; }
	button.revertir { border-color: #1e5c26; color: #1e5c26; }
	#msg { margin-top: 16px; padding: 10px 14px; border-radius: 6px; display: none; font-size: 14px; }
	#msg.ok { background: #d7f2db; color: #1e5c26; display: block; }
	#msg.error { background: #f7d7d7; color: #8a1c1c; display: block; }
</style>
</head>
<body>
<h1>Panel de prueba — Vencimiento de firma</h1>
<div class="aviso">
	Página temporal, no linkeada desde el menú. Simula el paso de los 20 días
	sin esperar de verdad. Solo ves tus propios Acuerdos generados/enviados/
	vencidos. Borrar este archivo cuando termines de probar.
</div>

<div id="msg"></div>

<table>
	<thead><tr><th>Documento</th><th>Estado</th><th>Fecha generación</th><th>Días restantes</th><th>Acciones</th></tr></thead>
	<tbody id="tbody">
	<?php if (!$acuerdos): ?>
		<tr><td colspan="5">No tenés Acuerdos generados/enviados/vencidos para probar.</td></tr>
	<?php else: foreach ($acuerdos as $a): ?>
		<tr data-id="<?= (int) $a['id'] ?>">
			<td>#<?= htmlspecialchars($a['documento_no']) ?></td>
			<td><span class="badge b-<?= htmlspecialchars($a['estado']) ?>"><?= htmlspecialchars($a['estado']) ?></span></td>
			<td><?= htmlspecialchars($a['fecha_generacion']) ?></td>
			<td><?= (int) $a['dias_restantes'] ?></td>
			<td>
				<?php if ($a['estado'] !== 'vencido'): ?>
				<button data-modo="aviso" data-id="<?= (int) $a['id'] ?>">Aviso (16d)</button>
				<button data-modo="vencido" data-id="<?= (int) $a['id'] ?>">Vencido (21d)</button>
				<?php else: ?>
				<button class="revertir" data-modo="revertir" data-id="<?= (int) $a['id'] ?>">Revertir</button>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; endif; ?>
	</tbody>
</table>

<script>
document.getElementById('tbody').addEventListener('click', function (e) {
	var btn = e.target.closest('button[data-modo]');
	if (!btn) return;
	btn.disabled = true;
	fetch('getters/_dev_simular_vencimiento.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: new URLSearchParams({ id: btn.dataset.id, modo: btn.dataset.modo })
	})
		.then(function (r) { return r.json(); })
		.then(function (data) {
			var msg = document.getElementById('msg');
			msg.textContent = data.message;
			msg.className = data.ok ? 'ok' : 'error';
			if (data.ok) setTimeout(function () { location.reload(); }, 900);
		})
		.catch(function () {
			var msg = document.getElementById('msg');
			msg.textContent = 'Error de conexión.';
			msg.className = 'error';
			btn.disabled = false;
		});
});
</script>
</body>
</html>
