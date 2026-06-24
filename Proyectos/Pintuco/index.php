<?php
	include_once 'db_connect.php';
	require __DIR__.'/includes/mock_data.php';

	// ================================================================
	// SECCIONES DEL MÓDULO
	// Para agregar una pestaña nueva:
	//   1. Crear partials/tab-nueva.php
	//   2. Agregar una entrada al array $tabs abajo
	// ================================================================
	$tabs = [
		['id' => 'tab-avance',       'label' => 'Avance %',      'partial' => 'partials/tab-avance.php'],
		['id' => 'tab-detalle',      'label' => 'Detalle',       'partial' => 'partials/tab-placeholder.php', 'placeholder' => 'Detalle'],
		['id' => 'tab-fotografico',  'label' => 'Fotográfico',   'partial' => 'partials/tab-placeholder.php', 'placeholder' => 'Fotográfico'],
		['id' => 'tab-historico',    'label' => 'Histórico',     'partial' => 'partials/tab-placeholder.php', 'placeholder' => 'Histórico'],
		['id' => 'tab-estadistico',  'label' => 'Estadístico',   'partial' => 'partials/tab-placeholder.php', 'placeholder' => 'Estadístico'],
	];
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Proyectos y Obras</title>

	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
	<link rel="stylesheet" href="style.css">
</head>
<body>

	<div class="app-wrapper">

		<?php include __DIR__.'/partials/sidebar.php'; ?>

		<div class="main-content">

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
									}
									include __DIR__.'/'.$tab['partial'];
								?>
							</div>
							<?php endforeach; ?>
						</div>
					</div>

				<?php else: ?>

					<div class="content-panel">
						<?php include __DIR__.'/'.$seccion['componente']; ?>
					</div>

				<?php endif; ?>
			</div>
			<?php endforeach; ?>

		</div>

	</div>

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
			});

			$('.sidebar-nav a[data-toggle="section"]').on('click', function (e) {
				e.preventDefault();
				$('.sidebar-nav li').removeClass('active');
				$(this).parent('li').addClass('active');
				$('.section-pane').removeClass('active');
				$($(this).attr('href')).addClass('active');
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
