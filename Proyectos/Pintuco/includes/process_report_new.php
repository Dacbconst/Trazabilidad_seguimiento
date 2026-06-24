<?php
	include_once 'db_connect.php';
	include_once 'functions.php';
	include_once 'reports_new.php';
	 
	sec_session_start(); // Nuestra manera personalizada segura de iniciar sesion PHP.
	$fechaInicio = $_POST["fechaInicio"];
	$fechaFin = $_POST["fechaFin"];
	$modulo = $_POST["modulo"];
	
	if ($modulo==='agotados_excel'){
	    agot_reporte($fechaInicio, $fechaFin, $mysqli);
	}else if($modulo==='exh_excel'){
	   exh_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='implementacion_excel'){
	   impl_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='inv_excel'){
	   inv_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='notificacion_excel'){
	   not_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='notificacion_excel_reporte'){
	   not_reporte_excel($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='precios_excel'){
	   pre_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='registro_excel'){
	   reg_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='share_excel'){
	   share_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='share_excel2'){
	   share_reporte2($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='evidencias_excel'){
	   evidencias_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='rastreo_excel'){
	   rastreo_reporte_new($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='ventas_excel'){
	   vent_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='prom_excel'){
	   promo_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='venta_excel'){
	   venta_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='exh_ant_desp'){
	   exh_ant_desp_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='exhibiciones_excel'){
	   app_exhibiciones_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='test_excel'){
	   test_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='caducar_excel'){
	   caducar_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else if($modulo==='logros_excel'){
	   logro_reporte($fechaInicio, $fechaFin, $mysqli); 
	}else {
	    // Las variables POST correctas no se enviaron a esta pagina.
	    echo 'Solicitud no valida';
	}
?>