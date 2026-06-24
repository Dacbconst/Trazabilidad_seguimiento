<?php
	require $_SERVER["DOCUMENT_ROOT"].'/App/XploraEcuador/includes/config.php';
	require $_SERVER["DOCUMENT_ROOT"].'/App/XploraEcuador/includes/db_connect.php';
	require $_SERVER["DOCUMENT_ROOT"].'/App/XploraEcuador/includes/functions.php';

	sec_session_start();

	$tipo_cuenta = $_SESSION['tipo'];
	$username = $_SESSION['username'];
	$user_id = $_SESSION['user_id'];

	if (isset($username)) {

		// Cuentas habilitadas para ESTE usuario (igual consulta que usaba el
		// hub original) — esto es lo que llena el <select> "Cuenta" del sidebar.
		$consulta = "SELECT cuenta, detalle FROM vi_cuentas_por_usuario WHERE id_usuario=? AND activo=1 ORDER BY detalle;";
		$sql = $mysqli->prepare($consulta);
		$sql->bind_param('s', $user_id);
		$sql->execute();
		$sql->store_result();
		$sql->bind_result($cuenta_cod, $cuenta_detalle);
		$cuentas_disponibles = [];
		while ($sql->fetch()) {
			$cuentas_disponibles[$cuenta_cod] = $cuenta_detalle;
		}
		$sql->close();

		// Datos reales de sesión del hub XploraEcuador. Aún no hay fuente para
		// nombre completo / último ingreso / actualizado, así que se omiten
		// hasta que se identifique de dónde traerlos.
		$usuario_actual = [
			'nombre' => $username,
		];

		// ================================================================
		// MÓDULOS YA CONSTRUIDOS
		// No toda cuenta de $cuentas_disponibles tiene su carpeta lista todavía.
		// Esto solo dice "si elige X, dónde está su código"; el listado real de
		// cuentas que ve cada usuario sale de $cuentas_disponibles (arriba).
		// Para agregar una cuenta nueva ya construida: una línea aquí.
		// ================================================================
		$modulos_implementados = [
			'PINTUCO' => 'Pintuco',
		];

		$cuenta_actual = isset($_GET['cuenta']) ? strtoupper($_GET['cuenta']) : null;
		$cuenta_habilitada = $cuenta_actual !== null
			&& isset($cuentas_disponibles[$cuenta_actual])
			&& isset($modulos_implementados[$cuenta_actual]);

		$cuenta_dir = null;
		$secciones = [];
		$tabs = [];

		if ($cuenta_habilitada) {
			$cuenta_dir = __DIR__.'/'.$modulos_implementados[$cuenta_actual];
			require $cuenta_dir.'/includes/mock_data.php';

			$tabs = [
				['id' => 'tab-avance',       'label' => 'Avance %',      'partial' => 'partials/tab-avance.php'],
				['id' => 'tab-detalle',      'label' => 'Detalle',       'partial' => 'partials/tab-placeholder.php', 'placeholder' => 'Detalle'],
				['id' => 'tab-fotografico',  'label' => 'Fotográfico',   'partial' => 'partials/tab-placeholder.php', 'placeholder' => 'Fotográfico'],
				['id' => 'tab-historico',    'label' => 'Histórico',     'partial' => 'partials/tab-placeholder.php', 'placeholder' => 'Histórico'],
				['id' => 'tab-estadistico',  'label' => 'Estadístico',   'partial' => 'partials/tab-placeholder.php', 'placeholder' => 'Estadístico'],
			];
		}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Proyectos y Obras</title>

	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
	<link href="/App/XploraEcuador/Proyectos/assets/bootstrap2/css/style_nav.css" rel="stylesheet">
	<link rel="stylesheet" href="style.css">
</head>
<body>

	<nav class="navbar navbar-default navbar-fixed-top">
		<?php include __DIR__.'/nav.php'; ?>
	</nav>

	<div class="app-wrapper">

		<?php include __DIR__.'/partials/sidebar.php'; ?>

		<div class="main-content">

			<?php if ($cuenta_habilitada): ?>

				<?php include __DIR__.'/partials/topbar.php'; ?>

				<?php foreach ($secciones as $i => $seccion): ?>
				<div class="section-pane <?= $i === 0 ? 'active' : '' ?>" id="sec-<?= $seccion['id'] ?>">
					<?php if ($seccion['id'] === 'principal'): ?>

						<ul class="stepper" id="main-tabs">
							<?php foreach ($tabs as $j => $tab): ?>
							<li class="<?= $j === 0 ? 'active' : '' ?>">
								<a href="#<?= $tab['id'] ?>" data-toggle="tab"><?= htmlspecialchars($tab['label']) ?></a>
							</li>
							<?php endforeach; ?>
						</ul>

						<div class="content-panel">
							<div class="tab-content">
								<?php foreach ($tabs as $j => $tab): ?>
								<div class="tab-pane <?= $j === 0 ? 'active' : '' ?>" id="<?= $tab['id'] ?>">
									<?php
										if (isset($tab['placeholder'])) {
											$placeholder_label = $tab['placeholder'];
											$placeholder_message = null;
										}
										include __DIR__.'/'.$tab['partial'];
									?>
								</div>
								<?php endforeach; ?>
							</div>
						</div>

					<?php else: ?>

						<div class="content-panel">
							<?php
								$placeholder_message = null;
								include $cuenta_dir.'/'.$seccion['componente'];
							?>
						</div>

					<?php endif; ?>
				</div>
				<?php endforeach; ?>

			<?php elseif ($cuenta_actual !== null): ?>

				<div class="content-panel">
					<?php
						$placeholder_label = $cuentas_disponibles[$cuenta_actual] ?? $cuenta_actual;
						$placeholder_message = htmlspecialchars($placeholder_label).' — Próximamente';
						include __DIR__.'/partials/tab-placeholder.php';
					?>
				</div>

			<?php else: ?>

				<div class="content-panel">
					<?php
						$placeholder_label = 'cuenta';
						$placeholder_message = 'Elija una cuenta en el sidebar para comenzar';
						include __DIR__.'/partials/tab-placeholder.php';
					?>
				</div>

			<?php endif; ?>

		</div>

	</div>

	<center>
		<p>&copy; PromoLucky <?php echo date("Y");?></p>
	</center>

	<script src="https://code.jquery.com/jquery-1.12.0.min.js"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	<script>
		$(document).ready(function () {
			$('#main-tabs a[data-toggle="tab"]').on('click', function (e) {
				e.preventDefault();
				$('#main-tabs li').removeClass('active');
				$(this).parent('li').addClass('active');
				$('.tab-pane').removeClass('active');
				$($(this).attr('href')).addClass('active');
				window.dispatchEvent(new Event('resize'));
			});

			$('.sidebar-nav a[data-toggle="section"]').on('click', function (e) {
				e.preventDefault();
				$('.sidebar-nav li').removeClass('active');
				$(this).parent('li').addClass('active');
				$('.section-pane').removeClass('active');
				$($(this).attr('href')).addClass('active');

				// La sección pasa de display:none a visible recién aquí. Si contiene
				// un calendario (FullCalendar, etc.) que se inicializó mientras estaba
				// oculto, midió 0px de ancho. Disparar 'resize' fuerza que recalculen
				// su tamaño ahora que el contenedor ya es visible.
				window.dispatchEvent(new Event('resize'));
			});

			$('#sidebarCollapse').on('click', function () {
				$('#sidebar').toggleClass('active');
			});

			$('#btnActualizar').on('click', function () {
				// TODO: disparar recarga de datos vía AJAX (getters/) cuando se conecte la BD real.
				location.reload();
			});
		});
	</script>
</body>
</html>

<?php
	} else {
		header('Location: '.'/App/XploraEcuador/login.php');
	}
?>
