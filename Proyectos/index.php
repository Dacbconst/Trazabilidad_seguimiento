<?php
	require $_SERVER["DOCUMENT_ROOT"].'/App/XploraEcuador/includes/config.php';
	require $_SERVER["DOCUMENT_ROOT"].'/App/XploraEcuador/includes/db_connect.php';
	require $_SERVER["DOCUMENT_ROOT"].'/App/XploraEcuador/includes/functions.php';
	
	// sec_session_start();

	// $tipo_cuenta = $_SESSION['tipo'];
	// $username = $_SESSION['username'];
	// $user_id = $_SESSION['user_id'];
	
	// if(isset($username)){
	// 	$consulta = "SELECT cuenta, detalle FROM vi_cuentas_por_usuario WHERE id_usuario=? AND activo=1 ORDER BY detalle;";
    //     $sql = $mysqli->prepare($consulta);
    //     $sql->bind_param('s', $user_id);
	// 	$sql->execute();
	// 	$sql->store_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>

	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Datos de Bases</title>

	<!-- Bootstrap -->
	<link href="/App/XploraEcuador/mantenimiento_correos/bootstrap2/css/bootstrap.min.css" rel="stylesheet">
	<link href="/App/XploraEcuador/mantenimiento_correos/bootstrap2/css/style_nav.css" rel="stylesheet">

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
			<h2>Cuentas</h2>
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
			<br />
			<div class="box">
				<div class="box-body" style="padding-right: 10px;">
				<?php 
					switch (strtoupper($filter)) {
						case 'ALICORP-SAPOLIO':
				?>			
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/alicorp/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Previsualización <?php echo $filter;?></button>
				<?php 	
						break;
				?>
				<?php 
						case 'SAPOLIO':
				?>			
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/alicorp/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Previsualización <?php echo $filter;?></button>
				<?php 	
						break;
				?>
				<?php 
						case 'ALICORP':
				?>			
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/alicorp/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Previsualización <?php echo $filter;?></button>
							<!-- <button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/<?php echo $filter;?>/rutas.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Rutero <?php echo $filter;?></button> -->
				<?php 	
						break;
				?>
				<?php 
						case 'TONI_MODERNO':
				?>			
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/toni/EvaluacionVisita/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Resumen Gestión de Supervisores <?php echo $filter;?></button>
				<?php 	
						break;
				?>
				<?php 
						case 'TONI_TRADICIONAL':
				?>			
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/toni/EvalaucionVisita/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Resumen Gestión de Supervisores <?php echo $filter;?></button>
				<?php 	
						break;
				?>
				<?php 
						case 'COCA_COLA_MODERNO':
				?>			
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/coca_cola/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Previsualización <?php echo $filter;?></button>
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/coca_cola/ControlAsistencia/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Previsualización Entradas <?php echo $filter;?></button>
				<?php 	
						break;
				?>
				<?php 
						case 'SEMVRA':
				?>			
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/semvra/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Previsualización <?php echo $filter;?></button>
				<?php 	
						break;
				?>
				<?php 
						case 'DANEC':
				?>			
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/danec/asistencia/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Previsualización Asistencia <?php echo $filter;?></button>
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/danec/horas_laboradas/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Previsualización Horas laboradas <?php echo $filter;?></button>
				<?php 	
						break;
				?>
				<?php 
						case 'JABONERIA_WILSON':
				?>			
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/jaboneria_wilson/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Previsualización a día vencido <?php echo $filter;?></button>
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/jaboneria_wilson/PrevisualizacionDiaEnCurso/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Previsualización día en curso <?php echo $filter;?></button>
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/jaboneria_wilson/ControlAsistencia/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Control de asistencia <?php echo $filter;?></button>
				<?php 	
						break;
				?>
				<?php 
						case 'BIC_MODERNO':
				?>			
							<button type="button" class="btn btn-primary btn-lg btn-block" onclick="window.location.href='/App/XploraEcuador/mantenimiento_correos/bic/index.php';" <?php if(!isset($_GET['filter'])){echo 'disabled';} ?>>Sugeridos <?php echo $filter;?></button>
				<?php 	
						break;
				?>
				<?php 	
						default:
					}
				?>
				
				</div>
			</div>
		</div>
	</div><center>
	<p>&copy; PromoLucky <?php echo date("Y");?></p
		</center>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
	<script src="/App/XploraEcuador/mantenimiento_correos/bootstrap2/js/bootstrap.min.js"></script>
	<script src="/App/XploraEcuador/mantenimiento_correos/bootstrap2/js/jquery-3.3.1.js"></script>
	<script src="/App/XploraEcuador/mantenimiento_correos/bootstrap2/js/jquery.dataTables.min.js"></script>
	<script src="/App/XploraEcuador/mantenimiento_correos/bootstrap2/js/dataTables.bootstrap4.min.js"></script>
</body>
</html>

<?php
	// } else {
	// 	header('Location: '.'/App/XploraEcuador/login.php');
	// }
?>