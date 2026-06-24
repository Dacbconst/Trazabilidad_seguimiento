<?php
	require $_SERVER["DOCUMENT_ROOT"].'/App/XploraEcuador/includes/config.php';
	require $_SERVER["DOCUMENT_ROOT"].'/App/XploraEcuador/includes/db_connect.php';
	require $_SERVER["DOCUMENT_ROOT"].'/App/XploraEcuador/includes/functions.php';

	sec_session_start();

	$tipo_cuenta = $_SESSION['tipo'];
	$username = $_SESSION['username'];
	$user_id = $_SESSION['user_id'];

	if (isset($username)) {
		$consulta = "SELECT cuenta, detalle FROM vi_cuentas_por_usuario WHERE id_usuario=? AND activo=1 ORDER BY detalle;";
		$sql = $mysqli->prepare($consulta);
		$sql->bind_param('s', $user_id);
		$sql->execute();
		$sql->store_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>

	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Proyectos y Obras</title>

	<!-- Bootstrap -->
	<link href="/App/XploraEcuador/Proyectos/assets/bootstrap2/css/bootstrap.min.css" rel="stylesheet">
	<link href="/App/XploraEcuador/Proyectos/assets/bootstrap2/css/style_nav.css" rel="stylesheet">

	<style>
		.content {
			margin-top: 80px;
		}
	</style>

</head>
<body>
	<nav class="navbar navbar-default navbar-fixed-top">
		<?php include('nav.php');?>
	</nav>
	<div class="container">
		<div class="content">
			<h4>Proyectos y Obras</h4>
			<hr />
			<form class="form-inline" method="get">
				<div class="form-group">
					<select name="filter" class="form-control" onchange="form.submit()">
						<option value="0">Filtros por Cuentas</option>
						<?php $filter = (isset($_GET['filter']) ? strtolower($_GET['filter']) : NULL); ?>
						<?php
							$sql->bind_result($cuenta, $detalle);
							while ($sql->fetch()) {
								echo '<option value="'.$cuenta.'" '.(strtolower($cuenta) === $filter ? 'selected' : '').'>'.$detalle.'</option>';
							}
						?>
					</select>
				</div>
			</form>
		</div>
	</div>
	<br>
	<?php
		switch (strtoupper($filter)) {
			// ================================================================
			// Cada cuenta es una carpeta hermana con su propio index.php.
			// Para agregar una cuenta nueva: crear su carpeta (ej. Bassa,
			// Unilever) y sumar un case aquí.
			// ================================================================
			case 'PINTUCO':
				echo '<iframe id="frm_proyectos_cuentas" style="position: absolute;" src="/App/XploraEcuador/Proyectos/Pintuco/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			default:
				break;
		}
	?>
	<center>
		<p>&copy; PromoLucky <?php echo date("Y");?></p>
	</center>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
	<script src="/App/XploraEcuador/Proyectos/assets/bootstrap2/js/bootstrap.min.js"></script>
</body>
</html>

<?php
	} else {
		header('Location: '.'/App/XploraEcuador/login.php');
	}
?>
