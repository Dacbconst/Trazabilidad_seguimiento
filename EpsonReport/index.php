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
require_once __DIR__.'/includes/actividades_datos.php';

$secciones = ep_secciones();
$actividadesNav = ep_actividades();
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
	<button type="button" id="epMenuBtn" class="ep-mobile-menu-btn" aria-label="Abrir menú">
		<?= ep_icon('menu', 20) ?>
	</button>

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
		epMenuBtn.addEventListener('click', function () {
			if (epSidebar.classList.contains('open')) {
				cerrarDrawer();
			} else {
				abrirDrawer();
			}
		});
		epSidebarBackdrop.addEventListener('click', cerrarDrawer);

		// Clic en cualquier zona vacía del sidebar (nunca sobre un link real): en mobile cierra el drawer, en desktop colapsa el menú.
		epSidebar.addEventListener('click', function (ev) {
			if (ev.target.closest('a')) return;
			if (ev.target.closest('#epSidebarSubVolver')) return;
			if (mqMobile.matches) {
				cerrarDrawer();
				return;
			}
			epSidebar.classList.toggle('collapsed');
			localStorage.setItem('ep_sidebar_colapsado', epSidebar.classList.contains('collapsed') ? '1' : '0');
		});

		// Submenú "Actividades" en celular: reemplaza el menú principal dentro del mismo panel, en vez de navegar de una.
		var epSidebarSubVolver = document.getElementById('epSidebarSubVolver');
		epSidebar.querySelectorAll('[data-abre-submenu]').forEach(function (link) {
			link.addEventListener('click', function (ev) {
				if (!mqMobile.matches) return;
				ev.preventDefault();
				epSidebar.classList.add('ep-sidebar-mostrando-submenu');
			});
		});
		if (epSidebarSubVolver) {
			epSidebarSubVolver.addEventListener('click', function () {
				epSidebar.classList.remove('ep-sidebar-mostrando-submenu');
			});
		}
	</script>
	<script src="assets/js/app.js?v=<?= filemtime(__DIR__.'/assets/js/app.js') ?>"></script>
</body>
</html>
