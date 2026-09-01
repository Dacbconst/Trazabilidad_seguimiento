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
$toast_js_v = @filemtime(__DIR__.'/assets/js/toast.js') ?: time();
$select_bonito_js_v = @filemtime(__DIR__.'/assets/js/select-bonito.js') ?: time();
$cargando_js_v = @filemtime(__DIR__.'/assets/js/cargando.js') ?: time();
$lightbox_js_v = @filemtime(__DIR__.'/assets/js/lightbox.js') ?: time();
$alertas_firma_js_v = @filemtime(__DIR__.'/assets/js/alertas-firma.js') ?: time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Acuerdos Comerciales</title>
	<link rel="icon" href="assets/img/favicon.ico" sizes="any">
	<link rel="icon" type="image/png" href="assets/img/favicon-32x32.png">
	<link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">
	<link rel="stylesheet" href="assets/css/style.css?v=<?= $style_v ?>">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
	<script src="assets/js/toast.js?v=<?= $toast_js_v ?>"></script>
	<script src="assets/js/cargando.js?v=<?= $cargando_js_v ?>"></script>
	<script src="assets/js/select-bonito.js?v=<?= $select_bonito_js_v ?>" defer></script>
	<script src="assets/js/lightbox.js?v=<?= $lightbox_js_v ?>" defer></script>
	<script src="assets/js/alertas-firma.js?v=<?= $alertas_firma_js_v ?>" defer></script>
</head>
<body>

	<header class="ac-header">
		<div class="ac-header-inner">
			<div class="ac-header-brand-group">
				<button type="button" id="acHeaderMenuBtn" class="ac-header-menu-btn" aria-label="Abrir menú">
					<span class="material-symbols-outlined">menu</span>
				</button>
				<div class="ac-brand"><img src="assets/img/logo_alicorp.png" alt="Alicorp" class="ac-brand-logo"></div>
			</div>
			<div class="ac-header-user">
				<div class="ac-alertas-wrap">
					<button type="button" id="acAlertasBtn" class="ac-alertas-btn" aria-label="Alertas de firma" aria-expanded="false">
						<span class="material-symbols-outlined">notifications</span>
						<span class="ac-alertas-badge" id="acAlertasBadge" hidden>0</span>
					</button>
					<div class="ac-alertas-panel" id="acAlertasPanel" hidden>
						<div class="ac-alertas-panel-header">
							<h3 class="ac-alertas-panel-titulo">Notificaciones</h3>
							<!-- Sin push real (no hay Firebase); esto + el refresco por módulo simulan "en vivo". -->
							<button type="button" class="ac-alertas-refrescar" id="acAlertasRefrescarBtn" title="Actualizar notificaciones">
								<span class="material-symbols-outlined">refresh</span>
							</button>
						</div>
						<!-- 2 pestañas: Actas Asignadas (activity feed) y Por Firmar (alerta de vencimiento, arranca activa). -->
						<div class="ac-alertas-tabs">
							<button type="button" class="ac-alertas-tab" id="acAlertasTabAsignadas" data-tab="asignadas">Actas Asignadas</button>
							<button type="button" class="ac-alertas-tab ac-alertas-tab-activa" id="acAlertasTabFirmar" data-tab="firmar">Actas Por Firmar</button>
						</div>
						<div class="ac-alertas-panel-body" id="acAlertasBodyAsignadas" hidden>
							<p class="ac-alertas-vacio">Cargando...</p>
						</div>
						<div class="ac-alertas-panel-body" id="acAlertasBodyFirmar">
							<p class="ac-alertas-vacio">Cargando...</p>
						</div>
					</div>
				</div>
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
		<div class="ac-sidebar-backdrop" id="acSidebarBackdrop"></div>

		<main class="ac-content">
			<?php foreach ($secciones as $i => $seccion): ?>
			<div class="ac-content-panel <?= $i === 0 ? 'active' : '' ?>" id="sec-<?= $seccion['id'] ?>">
				<?php include __DIR__.'/'.$seccion['componente']; ?>
			</div>
			<?php endforeach; ?>
		</main>
	</div>

	<!-- Overlay global reusable: cualquier módulo lo abre con window.acAbrirLightbox(src). -->
	<div class="ac-lightbox-overlay" id="acLightboxOverlay">
		<button type="button" class="ac-lightbox-close" id="acLightboxClose" aria-label="Cerrar">
			<span class="material-symbols-outlined">close</span>
		</button>
		<img id="acLightboxImg" alt="Imagen ampliada">
	</div>

	<script>
		var acSidebar = document.getElementById('acSidebar');
		var acSidebarToggle = document.getElementById('sidebarToggle');
		var acHeaderMenuBtn = document.getElementById('acHeaderMenuBtn');
		var acSidebarBackdrop = document.getElementById('acSidebarBackdrop');
		var mqMobile = window.matchMedia('(max-width: 900px)');

		if (localStorage.getItem('ac_sidebar_colapsado') === '1') {
			acSidebar.classList.add('collapsed');
		}

		// Colapso a rail de íconos solo en desktop; en mobile es un drawer (.collapsed vs .open nunca se pisan).
		acSidebarToggle.addEventListener('click', function () {
			if (mqMobile.matches) {
				cerrarDrawer();
				return;
			}
			acSidebar.classList.toggle('collapsed');
			localStorage.setItem('ac_sidebar_colapsado', acSidebar.classList.contains('collapsed') ? '1' : '0');
		});

		// Drawer mobile: nunca se persiste (a diferencia del colapso desktop), siempre arranca cerrado.
		function abrirDrawer() {
			acSidebar.classList.add('open');
			acSidebarBackdrop.classList.add('open');
		}
		function cerrarDrawer() {
			acSidebar.classList.remove('open');
			acSidebarBackdrop.classList.remove('open');
		}
		acHeaderMenuBtn.addEventListener('click', abrirDrawer);
		acSidebarBackdrop.addEventListener('click', cerrarDrawer);

		// Cada módulo se renderiza una sola vez (mostrar/ocultar con CSS, no navegación real),
		// así que cada uno con datos vivos expone su propio window.ac*Refrescar. Registrar no
		// tiene hook a propósito: refrescarlo destruiría el formulario en progreso del usuario.
		var refrescoPorSeccion = {
			'#sec-historial':        function () { if (window.acHistorialRefrescar) window.acHistorialRefrescar(); },
			'#sec-gestion-usuarios': function () { if (window.acUsuariosRefrescar) window.acUsuariosRefrescar(); },
			'#sec-liquidacion':      function () { if (window.acLiquidacionRefrescar) window.acLiquidacionRefrescar(); },
			'#sec-repositorios':     function () { if (window.acRepositoriosRefrescar) window.acRepositoriosRefrescar(); },
			'#sec-seguimiento':      function () { if (window.acSeguimientoRefrescar) window.acSeguimientoRefrescar(); },
			'#sec-cumplimiento':     function () { if (window.acCumplimientoRefrescar) window.acCumplimientoRefrescar(); }
		};

		document.querySelectorAll('.ac-sidebar-nav a[data-toggle="section"]').forEach(function (link) {
			link.addEventListener('click', function (e) {
				e.preventDefault();
				var href = link.getAttribute('href');
				document.querySelectorAll('.ac-sidebar-nav li').forEach(function (li) { li.classList.remove('active'); });
				link.parentElement.classList.add('active');
				document.querySelectorAll('.ac-content-panel').forEach(function (panel) { panel.classList.remove('active'); });
				document.querySelector(href).classList.add('active');
				if (refrescoPorSeccion[href]) refrescoPorSeccion[href]();
				// Apaga el pulso infinito del filtro de período de Historial (nunca se destruye al cambiar de pestaña).
				if (window.acHistorialLimpiarResaltadoFiltro) window.acHistorialLimpiarResaltadoFiltro();
				// Campanita: se refresca en cualquier cambio de módulo, sin destruir nada en pantalla.
				if (window.acAlertasFirmaRefrescar) window.acAlertasFirmaRefrescar();
				// En mobile, elegir una sección cierra el drawer.
				if (mqMobile.matches) cerrarDrawer();
			});
		});
	</script>
</body>
</html>
