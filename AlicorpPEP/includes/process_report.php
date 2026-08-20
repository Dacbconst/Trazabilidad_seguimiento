<?php
include_once 'db_connect.php';
include_once 'functions.php';
include_once 'reports.php';
 
sec_session_start(); // Nuestra manera personalizada segura de iniciar sesion PHP.
$fecha=$_POST["fecha"];

	if (isset($_POST['agotados_excel'])){
	    agot_reporte($fecha, $mysqli);
	}else if(isset($_POST['exh_excel'])){
	   exh_reporte($fecha,$mysqli); 
	}else if(isset($_POST['implementacion_excel'])){
	   impl_reporte($fecha,$mysqli); 
	}else if(isset($_POST['inv_excel'])){
	   inv_reporte($fecha,$mysqli); 
	}else if(isset($_POST['notificacion_excel'])){
	   not_reporte($fecha,$mysqli); 
	}else if(isset($_POST['notificacion_excel_reporte'])){
	   not_reporte_excel($fecha,$mysqli); 
	}else if(isset($_POST['precios_excel'])){
	   pre_reporte($fecha,$mysqli); 
	}else if(isset($_POST['registro_excel'])){
	   reg_reporte($fecha,$mysqli); 
	}else if(isset($_POST['share_excel'])){
	   share_reporte($fecha,$mysqli); 
	}else if(isset($_POST['rastreo_excel'])){
	   rastreo_reporte($fecha,$mysqli); 
	}else if(isset($_POST['ventas_excel'])){
	   vent_reporte($fecha,$mysqli); 
	}else if(isset($_POST['prom_excel'])){
	   promo_reporte($fecha,$mysqli); 
	}else if(isset($_POST['venta_excel'])){
	   venta_reporte($fecha,$mysqli); 	
	}else if(isset($_POST['venta_excel_imp'])){
	   venta_excel_impulso($fecha,$mysqli); 
	}else if(isset($_POST['exh_ant_desp'])){
	   exh_ant_desp_reporte($fecha,$mysqli); 
	}else if(isset($_POST['exhibiciones_excel'])){
	   app_exhibiciones_reporte($fecha,$mysqli); 
	}else if(isset($_POST['test_excel'])){
	   test_reporte($fecha,$mysqli); 
	}else if(isset($_POST['caducar_excel'])){
	   caducar_reporte($fecha,$mysqli); 
	}else {
	    // Las variables POST correctas no se enviaron a esta pagina.
	    echo 'Solicitud no valida';
	}