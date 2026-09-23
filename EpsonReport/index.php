<?php
require_once __DIR__.'/config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();

require_once __DIR__.'/includes/functions.php';

if (!ep_login_check()) {
	$motivoCierre = $GLOBALS['ep_motivo_cierre'] ?? '';
	$redirectParam = !empty($_SERVER['REQUEST_URI']) ? '?redirect='.urlencode($_SERVER['REQUEST_URI']) : '';
	$errorParam = $motivoCierre === 'inactividad' ? 'inactividad' : ($motivoCierre !== '' ? 'sesion' : '');
	$extra = $errorParam !== '' ? ($redirectParam !== '' ? '&' : '?').'error='.$errorParam : '';
	header('Location: login.php'.$redirectParam.$extra);
	exit;
}

require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/secciones.php';
require_once __DIR__.'/includes/actividades_datos.php';

$secciones = ep_secciones();
$actividadesNav = ep_actividades();
$vista = $_GET['vista'] ?? 'actividades';
if ($vista === 'registros') {
	$vista = 'historial';
}
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
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
</head>
<body>
	<header class="ep-mobile-header" id="epMobileHeader">
		<button type="button" id="epMenuBtn" class="ep-mobile-menu-btn" aria-label="Abrir menú">
			<?= ep_icon('menu', 20) ?>
		</button>
		<div class="ep-mobile-header-info">
			<span class="ep-mobile-header-brand">EPSON REPORT</span>
			<span class="ep-mobile-header-sep">/</span>
			<span class="ep-mobile-header-vista"><?= htmlspecialchars($secciones[$vista]['label'] ?? 'Actividades') ?></span>
		</div>
		<div class="ep-mobile-header-user" title="<?= htmlspecialchars($_SESSION['usuario'] ?? '') ?>">
			<?= strtoupper(substr($_SESSION['usuario'] ?? 'U', 0, 1)) ?>
		</div>
	</header>

	<div class="ep-shell">
		<?php require __DIR__.'/layout/sidebar.php'; ?>
		<div class="ep-sidebar-backdrop" id="epSidebarBackdrop"></div>

		<?php require __DIR__.'/components/'.$vista.'/'.$vista.'.php'; ?>
	</div>

	<script>
		var epSidebar = document.getElementById('epSidebar');
		var epSidebarBackdrop = document.getElementById('epSidebarBackdrop');
		var epMenuBtn = document.getElementById('epMenuBtn');
		var epSidebarCloseBtn = document.getElementById('epSidebarCloseBtn');
		var mqMobile = window.matchMedia('(max-width: 900px)');

		if (localStorage.getItem('ep_sidebar_colapsado') === '1') {
			epSidebar.classList.add('collapsed');
		}

		function abrirDrawer() {
			epSidebar.classList.add('open');
			epSidebarBackdrop.classList.add('open');
			document.body.style.overflow = 'hidden';
		}
		function cerrarDrawer() {
			epSidebar.classList.remove('open');
			epSidebarBackdrop.classList.remove('open');
			document.body.style.overflow = '';
		}
		if (epMenuBtn) {
			epMenuBtn.addEventListener('click', function () {
				if (epSidebar.classList.contains('open')) {
					cerrarDrawer();
				} else {
					abrirDrawer();
				}
			});
		}
		if (epSidebarCloseBtn) {
			epSidebarCloseBtn.addEventListener('click', cerrarDrawer);
		}
		epSidebarBackdrop.addEventListener('click', cerrarDrawer);

		// Clic en zona vacía del sidebar: en mobile cierra el drawer, en desktop colapsa el menú.
		epSidebar.addEventListener('click', function (ev) {
			if (ev.target.closest('a')) return;
			if (ev.target.closest('button')) return;
			if (mqMobile.matches) {
				cerrarDrawer();
				return;
			}
			epSidebar.classList.toggle('collapsed');
			localStorage.setItem('ep_sidebar_colapsado', epSidebar.classList.contains('collapsed') ? '1' : '0');
		});

		// Submenú Actividades en celular: reemplaza el menú principal dentro del mismo panel.
		var epSidebarSubVolver = document.getElementById('epSidebarSubVolver');
		var epSidebarSubCloseBtn = document.getElementById('epSidebarSubCloseBtn');
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
		if (epSidebarSubCloseBtn) {
			epSidebarSubCloseBtn.addEventListener('click', cerrarDrawer);
		}

		// Al tocar una actividad en el submenú móvil: activa el formulario y cierra el drawer.
		var epSidebarSubList = document.getElementById('epSidebarSubList');
		if (epSidebarSubList) {
			epSidebarSubList.addEventListener('click', function (ev) {
				var btn = ev.target.closest('.ep-sidebar-sub-item');
				if (!btn) return;
				var id = btn.dataset.id;
				var targetBtn = document.querySelector('#ep-lista-actividades .ep-activity-item[data-id="' + id + '"]');
				if (targetBtn) {
					targetBtn.click();
					epSidebarSubList.querySelectorAll('.ep-sidebar-sub-item').forEach(function (el) {
						el.classList.remove('selected');
					});
					btn.classList.add('selected');
					cerrarDrawer();
				} else {
					window.location.href = 'index.php?vista=actividades';
				}
			});
		}
	</script>
	<script src="assets/js/sesion-watch.js?v=<?= filemtime(__DIR__.'/assets/js/sesion-watch.js') ?>"></script>
	<script src="assets/js/reportes.js?v=<?= filemtime(__DIR__.'/assets/js/reportes.js') ?>"></script>
	<script src="assets/js/historial.js?v=<?= filemtime(__DIR__.'/assets/js/historial.js') ?>"></script>
	<script src="assets/js/app.js?v=<?= filemtime(__DIR__.'/assets/js/app.js') ?>"></script>
</body>
</html>
