<?php
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/db_connect.php';
iniciar_sesion();

if (!login_check()) {
	header('Location: login.php');
	exit;
}

require __DIR__.'/includes/secciones.php';
$secciones_visibles = [];
foreach ($secciones as $seccion) {
	if (rolPermitido($seccion['roles'])) {
		$secciones_visibles[] = $seccion;
	}
}
$secciones = $secciones_visibles;

$style_v = @filemtime(__DIR__.'/assets/css/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Acuerdos Comerciales</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">
	<link rel="stylesheet" href="assets/css/style.css?v=<?= $style_v ?>">
</head>
<body>

	<header class="ac-header">
		<div class="ac-header-inner">
			<div class="ac-brand">Acuerdos Comerciales</div>
			<div class="ac-header-user">
				<div class="ac-header-user-info">
					<span class="nombre"><?= htmlspecialchars($_SESSION['username']) ?></span>
					<span class="rol"><?= htmlspecialchars(strtoupper(rolEtiqueta($_SESSION['rol']))) ?></span>
				</div>
				<div class="ac-header-avatar">
					<img src="assets/img/avatar-default.webp" alt="" onerror="this.parentElement.style.display='none'">
				</div>
			</div>
		</div>
	</header>

	<div class="ac-shell">
		<?php include __DIR__.'/partials/sidebar.php'; ?>

		<main class="ac-content">
			<?php foreach ($secciones as $i => $seccion): ?>
			<div class="ac-content-panel <?= $i === 0 ? 'active' : '' ?>" id="sec-<?= $seccion['id'] ?>">
				<?php include __DIR__.'/'.$seccion['componente']; ?>
			</div>
			<?php endforeach; ?>
		</main>
	</div>

	<script>
		var acSidebar = document.getElementById('acSidebar');
		var acSidebarToggle = document.getElementById('sidebarToggle');

		if (localStorage.getItem('ac_sidebar_colapsado') === '1') {
			acSidebar.classList.add('collapsed');
		}

		acSidebarToggle.addEventListener('click', function () {
			acSidebar.classList.toggle('collapsed');
			localStorage.setItem('ac_sidebar_colapsado', acSidebar.classList.contains('collapsed') ? '1' : '0');
		});

		document.querySelectorAll('.ac-sidebar-nav a[data-toggle="section"]').forEach(function (link) {
			link.addEventListener('click', function (e) {
				e.preventDefault();
				document.querySelectorAll('.ac-sidebar-nav li').forEach(function (li) { li.classList.remove('active'); });
				link.parentElement.classList.add('active');
				document.querySelectorAll('.ac-content-panel').forEach(function (panel) { panel.classList.remove('active'); });
				document.querySelector(link.getAttribute('href')).classList.add('active');
			});
		});
	</script>
</body>
</html>
