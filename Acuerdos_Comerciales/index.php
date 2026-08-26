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
							<!-- Botón de refrescar manual (2026-08-25, pedido explícito: "dar
							     la sensación que está en vivo la página siempre") — sin
							     conexión en tiempo real (Firebase o similar) de verdad, este
							     botón + el refresco automático en cada cambio de módulo (ver
							     index.php más abajo) son el sustituto: nunca hay push real,
							     pero nunca hace falta recargar toda la página para ver algo
							     nuevo tampoco. -->
							<button type="button" class="ac-alertas-refrescar" id="acAlertasRefrescarBtn" title="Actualizar notificaciones">
								<span class="material-symbols-outlined">refresh</span>
							</button>
						</div>
						<!-- 2 pestañas — diseño tomado de "diseños ideas/code.html" (mockup
						     de referencia): "Actas Asignadas" (precargadas del Repositorio
						     de Cuotas, formato activity feed) y "Actas Por Firmar" (plazo de
						     20 días, formato de caja de alerta con franja de color). Por
						     firmar arranca activa (mismo default que el mockup: es la más
						     urgente de las 2). -->
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

	<!-- Lightbox de imágenes, reusable a nivel proyecto (2026-08-25): un solo
	     overlay global, cualquier módulo lo abre llamando
	     window.acAbrirLightbox(srcDeLaImagen) — ver assets/js/lightbox.js.
	     No hace zoom "a mano": el viewport de la app nunca deshabilita el
	     pinch-zoom nativo (sin user-scalable=no/maximum-scale), así que el
	     zoom real lo hace el propio navegador sobre esta imagen a pantalla
	     completa. -->
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

		// Colapso a rail de íconos — SOLO desktop (≥900px). En mobile la
		// sidebar es un drawer (abierta/cerrada, ver abrirDrawer/cerrarDrawer
		// más abajo) — las dos lógicas de clase (.collapsed vs .open) nunca
		// deben pisarse, por eso este toggle chequea el viewport primero.
		acSidebarToggle.addEventListener('click', function () {
			if (mqMobile.matches) {
				cerrarDrawer();
				return;
			}
			acSidebar.classList.toggle('collapsed');
			localStorage.setItem('ac_sidebar_colapsado', acSidebar.classList.contains('collapsed') ? '1' : '0');
		});

		// Drawer mobile (2026-08-25): no se persiste en localStorage a
		// propósito (a diferencia del colapso desktop) — siempre arranca
		// cerrado, patrón estándar de drawer.
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

		// Cada módulo se renderiza UNA sola vez al cargar la página (todas las
		// secciones van incluidas de entrada, ver el foreach de arriba) — cambiar
		// de módulo es solo mostrar/ocultar con CSS, nunca una navegación real.
		// Eso significa que si un módulo cambia datos en el servidor (ej. creás
		// un Acuerdo en Registrar), el HTML de Historial ya renderizado se queda
		// desactualizado. Por eso cada módulo con datos "vivos" expone su propia
		// función de refresco global (window.ac*Refrescar) y acá se llama sola
		// al entrar a esa sección. "Registrar Acuerdo PDV" NO tiene hook a
		// propósito: refrescarlo destruiría el formulario en progreso del
		// usuario, justo lo que no debe pasar al solo cambiar de pestaña.
		var refrescoPorSeccion = {
			'#sec-historial':        function () { if (window.acHistorialRefrescar) window.acHistorialRefrescar(); },
			'#sec-gestion-usuarios': function () { if (window.acUsuariosRefrescar) window.acUsuariosRefrescar(); },
			'#sec-liquidacion':      function () { if (window.acLiquidacionRefrescar) window.acLiquidacionRefrescar(); },
			'#sec-repositorios':     function () { if (window.acRepositoriosRefrescar) window.acRepositoriosRefrescar(); }
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
				// Campanita de notificaciones (2026-08-25, pedido explícito): se
				// refresca en CUALQUIER cambio de módulo, no solo los que ya
				// tenían su propio hook arriba — a diferencia de esos, esto nunca
				// destruye nada en pantalla (Registrar incluido), solo vuelve a
				// pedir la data de alertas.
				if (window.acAlertasFirmaRefrescar) window.acAlertasFirmaRefrescar();
				// En mobile, elegir una sección cierra el drawer — si no, el menú
				// se queda tapando la pantalla recién abierta.
				if (mqMobile.matches) cerrarDrawer();
			});
		});
	</script>
</body>
</html>
