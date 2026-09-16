<?php
require_once __DIR__.'/config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();

if (empty($_SESSION['usuario'])) {
	header('Location: login.php');
	exit;
}

require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/secciones.php';

$secciones = ep_secciones();
$vista = $_GET['vista'] ?? 'actividades';
if (!isset($secciones[$vista])) {
	$vista = 'actividades';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>EpsonReport</title>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
	<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
</head>
<body>
	<header class="ep-mobile-header">
		<button type="button" id="epMenuBtn" class="ep-mobile-menu-btn" aria-label="Abrir menú">
			<?= ep_icon('menu', 22) ?>
		</button>
		<div class="ep-brand-mark">ER</div>
		<span class="ep-mobile-header-title">EpsonReport</span>
	</header>

	<div class="ep-shell">
		<?php require __DIR__.'/partials/sidebar.php'; ?>
		<div class="ep-sidebar-backdrop" id="epSidebarBackdrop"></div>

		<?php require __DIR__.'/components/'.$vista.'/'.$vista.'.php'; ?>
	</div>

	<script>
		var epSidebar = document.getElementById('epSidebar');
		var epSidebarBackdrop = document.getElementById('epSidebarBackdrop');
		var epMenuBtn = document.getElementById('epMenuBtn');
		var mqMobile = window.matchMedia('(max-width: 900px)');

		if (localStorage.getItem('ep_sidebar_colapsado') === '1') {
			epSidebar.classList.add('collapsed');
		}

		function abrirDrawer() {
			epSidebar.classList.add('open');
			epSidebarBackdrop.classList.add('open');
		}
		function cerrarDrawer() {
			epSidebar.classList.remove('open');
			epSidebarBackdrop.classList.remove('open');
		}
		epMenuBtn.addEventListener('click', abrirDrawer);
		epSidebarBackdrop.addEventListener('click', cerrarDrawer);

		// Clic en cualquier zona vacía del sidebar (nunca sobre un link real): en mobile cierra el drawer, en desktop colapsa el menú.
		epSidebar.addEventListener('click', function (ev) {
			if (ev.target.closest('a')) return;
			if (mqMobile.matches) {
				cerrarDrawer();
				return;
			}
			epSidebar.classList.toggle('collapsed');
			localStorage.setItem('ep_sidebar_colapsado', epSidebar.classList.contains('collapsed') ? '1' : '0');
		});
	</script>
	<script src="assets/js/app.js?v=<?= filemtime(__DIR__.'/assets/js/app.js') ?>"></script>
</body>
</html>
