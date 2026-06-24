<?php
	require $_SERVER["DOCUMENT_ROOT"].'/App/XploraEcuador/includes/config.php';
	require $_SERVER["DOCUMENT_ROOT"].'/App/XploraEcuador/includes/db_connect.php';
	require $_SERVER["DOCUMENT_ROOT"].'/App/XploraEcuador/includes/functions.php';
	
	sec_session_start();

	$tipo_cuenta = $_SESSION['tipo'];
	$username = $_SESSION['username'];
	$user_id = $_SESSION['user_id'];
	
	if(isset($username)){
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
	<title>Datos de Bases</title>

	<!-- Bootstrap -->
	<link href="/App/XploraEcuador/mantenimiento_fotografico/assets/bootstrap2/css/bootstrap.min.css" rel="stylesheet">
	<link href="/App/XploraEcuador/mantenimiento_fotografico/assets/bootstrap2/css/style_nav.css" rel="stylesheet">

	<style>
		.body {
			/*background-image: url('/App/XploraEcuador/css3/fondo.jpg');*/
		}
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
			<h4>Descargas</h4>
			<hr />
			<form class="form-inline" method="get">
				<div class="form-group">
					<select name="filter" class="form-control" onchange="form.submit()">
						<option value="0">Filtros por Cuentas</option>
						<?php $filter = (isset($_GET['filter']) ? strtolower($_GET['filter']) : NULL);  ?>
						
						<?php 
							$sql->bind_result($cuenta,$detalle);
							while($sql->fetch()) {
								echo '<option value="'.$cuenta.'">'.$detalle.'</option>';
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
			case 'ALICORP-SAPOLIO':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/Alicorp/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'ALICORP':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/Alicorp/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'SAPOLIO':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/Alicorp/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'DANEC':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/Danec/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'JABONERIA_WILSON':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/Jaboneria_Wilson/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'CALBAQ':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/Calbaq/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'TONI_MODERNO':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/toni_moderno/index.php?cuenta=TONI%20MODERNO,ARCA%20MODERNO" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'COCA_COLA_MODERNO':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/coca_cola_moderno/index.php?cuenta=COCA%20COLA%20MODERNO" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'GISIS':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/Gisis/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'XTRIM':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/Xtrim/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'FACUNDO':
					echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/Facundo/index.php?cuenta=FACUNDO" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'NORMA':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/Norma/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'LA_UNIVERSAL':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/LaUniversal/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'BIC_MODERNO':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/bic_moderno/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'BIC_PAPELERO':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/bic_papelero/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'BIC_TRADICIONAL':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/bic_tradicional/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'FINI':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/fini/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'BASSA':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/bassa/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'PINGUINO':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/Pinguino/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			case 'PINTUCO':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/Pintuco/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			// RQFOTOGRAFICODACB
			case 'UNILEVER':
				echo '<iframe id="frm_descargas_cuentas" style="position: absolute;" src="/App/XploraEcuador/mantenimiento_fotografico/Unilever/index.php" frameborder="0" width="100%" height="100%"></iframe>';
				break;
			default:
				break;
		}
	?>
	<center>
		<p>&copy; PromoLucky <?php echo date("Y");?></p>
	</center>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
	<script src="/App/XploraEcuador/mantenimiento_fotografico/assets/bootstrap2/js/bootstrap.min.js"></script>
	<script src="/App/XploraEcuador/mantenimiento_fotografico/assets/bootstrap2/js/jquery-3.3.1.js"></script>
	<script src="/App/XploraEcuador/mantenimiento_fotografico/assets/bootstrap2/js/jquery.dataTables.min.js"></script>
	<script src="/App/XploraEcuador/mantenimiento_fotografico/assets/bootstrap2/js/dataTables.bootstrap4.min.js"></script>
</body>
</html>

<?php
	} else {
		header('Location: '.'/App/XploraEcuador/login.php');
	}
?>