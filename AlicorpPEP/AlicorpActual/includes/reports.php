<?php
include_once 'config.php';
 $output = '';
 
function rastreo_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT usuario, latitude, longitude, fecha, hora 
FROM insert_rastreo
WHERE fecha LIKE ?;
"
)){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
	$sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$usuario, 
		$latitud, 
		$longitud, 
		$fecha, 
		$hora
		) or die($sql->error);
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                     <tr>  
						<th>USUARIO</th>
						<th>FECHA</th>
						<th>HORA</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
					</tr>  
           '; 
             //$img_url = "https://webecuador.azurewebsites.net/App/AppExhibicionesKC/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                     <tr>  
						<td style="height:20px">'.utf8_decode($usuario).'</td>
						<td style="height:20px">'.utf8_decode($fecha).'</td>
						<td style="height:20px">'.utf8_decode($hora).'</td>
						<td class="cls1">'.$latitud.'</td>
						<td class="cls1">'.$longitud.'</td>
                     </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteAppRatreo.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function exhibiciones_excel($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT ciudad, formato, pos, tipo, exhibicion, categoria, estado, observacion, foto, fecha, hora, fecha_servidor 
FROM insert_exhibicioneskc;
"
)){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$ciudad, 
		$formato, 
		$pos, 
		$tipo, 
		$exhibicion, 
		$categoria, 
		$estado, 
		$observacion, 
		$foto, 
		$fecha, 
		$hora, 
		$fecha_servidor
		) or die($sql->error);
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                     <tr>  
						<th>FECHA</th>
						<th>HORA</th>
						<th>CIUDAD</th>
						<th>FORMATO</th>
						<th>POS</th>
						<th>TIPO</th>
						<th>EXHIBICION</th>
						<th>CATEGORIA</th>
						<th>ESTADO</th>
						<th>OBSERVACION</th>
						<th>FOTO URL</th>
                        </tr>  
           '; 
             $img_url = "https://webecuador.azurewebsites.net/App/AppExhibicionesKC/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                     <tr>  
						<td style="height:20px">'.utf8_decode($fecha).'</td>
						<td style="height:20px">'.utf8_decode($hora).'</td>
						<td style="height:20px">'.utf8_decode($ciudad).'</td>
						<td style="height:20px">'.utf8_decode($formato).'</td>
						<td style="height:20px">'.utf8_decode($pos).'</td>
						<td style="height:20px">'.utf8_decode($tipo).'</td>
						<td style="height:20px">'.utf8_decode($exhibicion).'</td>
						<td style="height:20px">'.utf8_decode($categoria).'</td>
						<td style="height:20px">'.utf8_decode($estado).'</td>
						<td style="height:20px">'.utf8_decode($observacion).'</td>
						<td style="height:20px">'.$img_url.$foto.'</td>
                     </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteAppExhibiciones.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function test_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT 
ins.usuario, 
ins.p1, 
ins.p2, 
ins.p3, 
ins.p4, 
ins.p5, 
ins.p6, 
ins.p7, 
ins.p8, 
ins.p9, 
ins.p10, 
ins.p11, 
ins.p12, 
ins.p13, 
ins.p14, 
ins.p15, 
ins.correctas, 
ins.incorrectas, 
ins.calificacion, 
ins.observacion, 
ins.cronometro, 
ins.fecha, 
ins.hora, 
rp.supervisor 
FROM insert_preguntas ins 
INNER JOIN repositorio_locales_dtt rp 
ON ins.usuario=rp.user 
WHERE ins.fecha REGEXP ?;")){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$usuario,
        $p1,
		$p2,
		$p3,
		$p4,
		$p5,
		$p6,
		$p7,
		$p8,
		$p9,
		$p10,
		$p11,
		$p12,
		$p13,
		$p14,
		$p15,
        $correctas,
        $incorrectas,
        $calificacion,
        $observacion,
        $cronometro,
        $fecha,
        $hora, 
		$supervisor
		) or die($sql->error);
		
		   $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                     <tr>  
						<th>USUARIO</th>
						<th>SUPERVISOR</th>
						<th>FECHA</th>
						<th>HORA</th>
						<th>PREGUNTA 1</th>
						<th>PREGUNTA 2</th>
						<th>PREGUNTA 3</th>
						<th>PREGUNTA 4</th>
						<th>PREGUNTA 5</th>
						<th>PREGUNTA 6</th>
						<th>PREGUNTA 7</th>
						<th>PREGUNTA 8</th>
						<th>PREGUNTA 9</th>
						<th>PREGUNTA 10</th>
						<th>PREGUNTA 11</th>
						<th>PREGUNTA 12</th>
						<th>PREGUNTA 13</th>
						<th>PREGUNTA 14</th>
						<th>PREGUNTA 15</th>
						<th>TOTAL CORRECTAS</th>
						<th>TOTAL INCORRECTAS</th>
						<th>NOTA FINAL</th>
						<th>APROBADO/REPROBADO</th>
						<th>CRONOMETRO</th>
						<th>COMENTARIOS</th>
					</tr>  
           '; 
             $img_url = "https://webecuador.azurewebsites.net/App/AppExhibicionesKC/Inserts/";
           while($sql->fetch())  
           {  
				$aprobado;
		
				if($calificacion >= 75){
					$aprobado = 'SI';
				}else{
					$aprobado = 'NO';
				}
				
                $output .= '  
                     <tr>  
						<td style="height:20px">'.utf8_decode($usuario).'</td>
						<td style="height:20px">'.utf8_decode($supervisor).'</td>
						<td style="height:20px">'.utf8_decode($fecha).'</td>
						<td style="height:20px">'.utf8_decode($hora).'</td>
						<td style="height:20px">'.utf8_decode($p1).'</td>
						<td style="height:20px">'.utf8_decode($p2).'</td>
						<td style="height:20px">'.utf8_decode($p3).'</td>
						<td style="height:20px">'.utf8_decode($p4).'</td>
						<td style="height:20px">'.utf8_decode($p5).'</td>
						<td style="height:20px">'.utf8_decode($p6).'</td>
						<td style="height:20px">'.utf8_decode($p7).'</td>
						<td style="height:20px">'.utf8_decode($p8).'</td>
						<td style="height:20px">'.utf8_decode($p9).'</td>
						<td style="height:20px">'.utf8_decode($p10).'</td>
						<td style="height:20px">'.utf8_decode($p11).'</td>
						<td style="height:20px">'.utf8_decode($p12).'</td>
						<td style="height:20px">'.utf8_decode($p13).'</td>
						<td style="height:20px">'.utf8_decode($p14).'</td>
						<td style="height:20px">'.utf8_decode($p15).'</td>
						<td style="height:20px">'.utf8_decode($correctas).'</td>
						<td style="height:20px">'.utf8_decode($incorrectas).'</td>
						<td style="height:20px">'.utf8_decode($calificacion).'</td>
						<td style="height:20px">'.utf8_decode($aprobado).'</td>
						<td style="height:20px">'.utf8_decode($observacion).'</td>
						<td style="height:20px">'.utf8_decode($cronometro).'</td>
                     </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteAppTest.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function exh_ant_desp_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT
ins.fecha,
ins.hora,
rpdv.pos_id,
rpdv.channel,
rpdv.subchannel,
rpdv.customer_owner,
rpdv.pos_name,
rpdv.region,
rpdv.province,
rpdv.city,
rpdv.zone,
rpdv.address,
rpdv.supervisor,
rpdv.mercaderista,
ins.usuario,
rpdv.latitud,
rpdv.longitud,
rpdv.kam AS territorio, 
rpdv.tipo AS zona_territorio,
rp.categoria,
ins.categoria,
ins.fabricante,
rp.fabricante,
ins.sku_code,
ins.agotados,
ins.sugerido,
ins.observacion,
ins.fechaservidor 
FROM insert_agotados_sugeridos ins 
INNER JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
LEFT JOIN repositorio_productos rp
ON ins.sku_code=rp.sku
WHERE ins.fecha REGEXP ?
GROUP BY ins.codigo, ins.usuario, ins.fecha, ins.hora;
"
)){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$fecha,
		$hora,
		$pos_id,
		$channel,
		$subchannel,
		$customer_owner,
		$pos_name,
		$region,
		$province,
		$city,
		$zone,
		$address,
		$supervisor,
		$mercaderista,
		$user,
		$latitud,
		$longitud,
		$kam, 
		$zona_territorio,
		$categoria,
		$categoria2,
		$fabricante,
		$fabricante2,
		$sku_code,
		$agotados,
		$sugerido,
		$observacion,
		$fechaservidor 
		) or die($sql->error);
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                     <tr>  
						<th>FECHA</th>
						<th>HORA</th>
						<th>CODIGO</th>
						<th>CANAL</th>
						<th>CADENA</th>
						<th>NOMBRE COMERCIAL</th>
						<th>LOCAL</th>
						<th>REGION</th>
						<th>TERRITORIO</th>
						<th>PROVINCIA</th>
						<th>CIUDAD</th>
						<th>ZONA</th>
						<th>ZONA TERRITORIO</th>
						<th>DIRECCION</th>
						<th>SUPERVISOR</th>
						<th>MERCADERISTA</th>
						<th>USUARIO</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						
						<th>CATEGORIA</th>
						<th>SUBCATEGORIA</th>
						<th>MARCA</th>
						<th>FABRICANTE</th>
						<th>SKU</th>
						<th>QUIEBRES</th>
						<th>CAUSALES</th>
						<th>MOTIVO/OTROS</th>
						<th>FECHA SERVIDOR</th>
                        </tr>  
           '; 
             $img_url = "https://webecuador.azurewebsites.net/App/AppSalica/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                     <tr>  
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
						<td style="height:20px">'.utf8_decode($pos_id).'</td> 
						<td style="height:20px">'.utf8_decode($channel).'</td> 
						<td style="height:20px">'.utf8_decode($subchannel).'</td> 
						<td style="height:20px">'.utf8_decode($customer_owner).'</td> 
						<td style="height:20px">'.utf8_decode($pos_name).'</td> 
						<td style="height:20px">'.utf8_decode($region).'</td> 
						<td style="height:20px">'.utf8_decode($kam).'</td>
						
						<td style="height:20px">'.utf8_decode($province).'</td> 
						<td style="height:20px">'.utf8_decode($city).'</td> 
						<td style="height:20px">'.utf8_decode($zone).'</td> 
						<td style="height:20px">'.utf8_decode($zona_territorio).'</td> 
						<td style="height:20px">'.utf8_decode($address).'</td> 
						<td style="height:20px">'.utf8_decode($supervisor).'</td> 
						<td style="height:20px">'.utf8_decode($mercaderista).'</td> 
						<td style="height:20px">'.utf8_decode($user).'</td> 
						
						<td class="cls1">'.$latitud.'</td>
						<td class="cls1">'.$longitud.'</td>
						
						<td style="height:20px">'.utf8_decode($categoria).'</td> 
						<td style="height:20px">'.utf8_decode($categoria2).'</td> 
						<td style="height:20px">'.utf8_decode($fabricante).'</td> 
						<td style="height:20px">'.utf8_decode($fabricante2).'</td> 
						<td style="height:20px">'.utf8_decode($sku_code).'</td> 
						<td style="height:20px">'.utf8_decode($agotados).'</td> 
						<td style="height:20px">'.utf8_decode($sugerido).'</td> 
						<td style="height:20px">'.utf8_decode($observacion).'</td> 
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td>
                     </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteQuiebres.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function caducar_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT 
ins.fecha,
ins.hora,
rpdv.pos_id,
rpdv.channel,
rpdv.subchannel,
rpdv.customer_owner,
rpdv.pos_name,
rpdv.region,
rpdv.province,
rpdv.city,
rpdv.zone,
rpdv.address,
rpdv.supervisor,
rpdv.mercaderista,
ins.usuario,
rpdv.latitud,
rpdv.longitud,
rpdv.kam AS territorio, rpdv.tipo AS zona_territorio,
rp.categoria,
ins.subcategoria,
ins.brand,
ins.sku_code,
ins.cantidad_prod,
ins.fechaservidor 
FROM insert_prod_caducar ins 
LEFT JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
LEFT JOIN repositorio_productos rp 
ON ins.sku_code=rp.sku 
WHERE ins.fecha REGEXP ?
GROUP BY fecha, hora;
"
)){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$fecha,
		$hora,
		$pos_id,
		$channel,
		$subchannel,
		$customer_owner,
		$pos_name,
		$region,
		$province,
		$city,
		$zone,
		$address,
		$supervisor,
		$mercaderista,
		$user,
		$latitud,
		$longitud,
		$kam, 
		$zona_territorio,
		$categoria,
		$subcategoria,
		$brand,
		$sku_code,
		$cantidad_prod,
		$fechaservidor 
		) or die($sql->error);
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                    <tr>  
						<th>FECHA</th>
						<th>HORA</th>
						<th>CODIGO</th>
						<th>CANAL</th>
						<th>CADENA</th>
						<th>NOMBRE COMERCIAL</th>
						<th>LOCAL</th>
						<th>REGION</th>
						<th>TERRITORIO</th>
						<th>PROVINCIA</th>
						<th>CIUDAD</th>
						<th>ZONA</th>
						<th>ZONA TERRITORIO</th>
						<th>DIRECCION</th>
						<th>SUPERVISOR</th>
						<th>MERCADERISTA</th>
						<th>USUARIO</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						
						<th>CATEGORIA</th>
						<th>SUBCATEGORIA</th>
						<th>MARCA</th>
						<th>SKU</th>
						<th>SUGERIDO</th>
						<th>FECHA SERVIDOR</th>
					</tr>  
           '; 
             $img_url = "https://webecuador.azurewebsites.net/App/AppDanec/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                     <tr>  
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
						<td style="height:20px">'.utf8_decode($pos_id).'</td> 
						<td style="height:20px">'.utf8_decode($channel).'</td> 
						<td style="height:20px">'.utf8_decode($subchannel).'</td> 
						<td style="height:20px">'.utf8_decode($customer_owner).'</td> 
						<td style="height:20px">'.utf8_decode($pos_name).'</td> 
						<td style="height:20px">'.utf8_decode($region).'</td> 
						<td style="height:20px">'.utf8_decode($kam).'</td>  
						<td style="height:20px">'.utf8_decode($province).'</td> 
						<td style="height:20px">'.utf8_decode($city).'</td> 
						<td style="height:20px">'.utf8_decode($zone).'</td> 
						<td style="height:20px">'.utf8_decode($zona_territorio).'</td> 
						<td style="height:20px">'.utf8_decode($address).'</td> 
						<td style="height:20px">'.utf8_decode($supervisor).'</td> 
						<td style="height:20px">'.utf8_decode($mercaderista).'</td> 
						<td style="height:20px">'.utf8_decode($user).'</td> 
						<td class="cls1">'.$latitud.'</td>
						<td class="cls1">'.$longitud.'</td>
						<td style="height:20px">'.utf8_decode($categoria).'</td> 
						<td style="height:20px">'.utf8_decode($subcategoria).'</td> 
						<td style="height:20px">'.utf8_decode($brand).'</td> 
						<td style="height:20px">'.utf8_decode($sku_code).'</td> 
						<td style="height:20px">'.utf8_decode($cantidad_prod).'</td> 
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td> 
                     </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteSugeridos.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function agot_reporte($fecha, $mysqli) {
//$fecha = '%'.$fecha;
if ($sql = $mysqli->prepare(
"
SELECT 
ins.fecha,
ins.hora,
rpdv.pos_id,
rpdv.channel,
rpdv.subchannel,
rpdv.customer_owner,
rpdv.pos_name,
rpdv.region,
rpdv.province,
rpdv.city,
rpdv.zone,
rpdv.address,
rpdv.supervisor,
rpdv.mercaderista,
ins.usuario,
rpdv.latitud,
rpdv.longitud,
rpdv.kam AS territorio, rpdv.tipo AS zona_territorio,
rp.categoria,
ins.subcategoria,
ins.sku_code,
rp.marca,
ins.presentacion,
ins.contenido,
ins.presencia,
ins.fechaservidor 
FROM insert_codificados_sku ins 
LEFT JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
LEFT JOIN repositorio_productos rp
ON ins.sku_code=rp.sku
WHERE ins.fecha REGEXP ?
GROUP BY ins.fecha, ins.hora, ins.usuario;
"
)){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$fecha,
		$hora,
		$pos_id,
		$channel,
		$subchannel,
		$customer_owner,
		$pos_name,
		$region,
		$province,
		$city,
		$zone,
		$address,
		$supervisor,
		$mercaderista,
		$user,
		$latitud,
		$longitud,
		$kam, $zona_territorio,
		$categoria,
		$subcategoria,
		$sku_code,
		$brand,
		$presentacion,
		$contenido,
		$presencia,
		$fechaservidor
		) or die($sql->error);
	    
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                     <tr>  
						<th>FECHA</th>
						<th>HORA</th>
						<th>CODIGO</th>
						<th>CANAL</th>
						<th>CADENA</th>
						<th>NOMBRE COMERCIAL</th>
						<th>LOCAL</th>
						<th>REGION</th>
						<th>TERRITORIO</th>
						<th>PROVINCIA</th>
						<th>CIUDAD</th>
						<th>ZONA</th>
						<th>ZONA TERRITORIO</th>
						<th>DIRECCION</th>
						<th>SUPERVISOR</th>
						<th>MERCADERISTA</th>
						<th>USUARIO</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						<th>CATEGORIA</th>
						<th>SUBCATEGORIA</th>
						<th>SKU</th>
						<th>MARCA</th>
						<th>PRESENTACION</th>
						<th>CONTENIDO</th>
						<th>PRESENCIA</th>
						<th>FECHA SERVIDOR</th>
                     </tr>  
           '; 
         //    $img_url = "https://webecuador.azurewebsites.net/App/CtaEpson/AppEpson/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                     <tr>  
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
						<td style="height:20px">'.utf8_decode($pos_id).'</td> 
						<td style="height:20px">'.utf8_decode($channel).'</td> 
						<td style="height:20px">'.utf8_decode($subchannel).'</td> 
						<td style="height:20px">'.utf8_decode($customer_owner).'</td> 
						<td style="height:20px">'.utf8_decode($pos_name).'</td> 
						<td style="height:20px">'.utf8_decode($region).'</td> 
						<td style="height:20px">'.utf8_decode($kam).'</td>
						<td style="height:20px">'.utf8_decode($province).'</td> 
						<td style="height:20px">'.utf8_decode($city).'</td> 
						<td style="height:20px">'.utf8_decode($zone).'</td> 
						<td style="height:20px">'.utf8_decode($zona_territorio).'</td> 
						<td style="height:20px">'.utf8_decode($address).'</td> 
						<td style="height:20px">'.utf8_decode($supervisor).'</td> 
						<td style="height:20px">'.utf8_decode($mercaderista).'</td> 
						<td style="height:20px">'.utf8_decode($user).'</td> 
						<td class="cls1">'.$latitud.'</td>
						<td class="cls1">'.$longitud.'</td>
						<td style="height:20px">'.utf8_decode($categoria).'</td> 
						<td style="height:20px">'.utf8_decode($subcategoria).'</td> 
						<td style="height:20px">'.utf8_decode($sku_code).'</td> 
						<td style="height:20px">'.utf8_decode($brand).'</td> 
						<td style="height:20px">'.utf8_decode($presentacion).'</td> 
						<td style="height:20px">'.utf8_decode($contenido).'</td> 
						<td style="height:20px">'.utf8_decode($presencia).'</td> 
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td> 
                     </tr>  
                ';
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteCodificadosSKU.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function exh_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT 
ins.fecha,
ins.hora,
rpdv.pos_id,
rpdv.channel,
rpdv.subchannel,
rpdv.customer_owner,
rpdv.pos_name,
rpdv.region,
rpdv.province,
rpdv.city,
rpdv.zone,
rpdv.address,
rpdv.supervisor,
rpdv.mercaderista,
ins.usuario,
rpdv.latitud,
rpdv.longitud,
rpdv.kam AS territorio, rpdv.tipo AS zona_territorio,
ins.sector,
ins.categoria,
rp.fabricante,
ins.brand,
ins.tipo_exh,
ins.num_exh,
ins.foto,
ins.fechaservidor 
FROM insert_exhibiciones ins 
LEFT JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
LEFT JOIN repositorio_productos rp 
ON ins.brand=rp.marca 
WHERE ins.fecha REGEXP ?
GROUP BY ins.fecha, ins.hora;
"
)){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$fecha,
		$hora,
		$pos_id,
		$channel,
		$subchannel,
		$customer_owner,
		$pos_name,
		$region,
		$province,
		$city,
		$zone,
		$address,
		$supervisor,
		$mercaderista,
		$user,
		$latitud,
		$longitud,
		$kam, $zona_territorio,
		$sector,
		$categoria,
		$fabricante,
		$brand,
		$tipo_exh,
		$num_exh,
		$foto,
		$fechaservidor
		) or die($sql->error);
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                     <tr>  
						<th>FECHA</th>
						<th>HORA</th>
						<th>CODIGO</th>
						<th>CANAL</th>
						<th>CADENA</th>
						<th>NOMBRE COMERCIAL</th>
						<th>LOCAL</th>
						<th>REGION</th>
						<th>TERRITORIO</th>
						<th>PROVINCIA</th>
						<th>CIUDAD</th>
						<th>ZONA</th>
						<th>ZONA TERRITORIO</th>
						<th>DIRECCION</th>
						<th>SUPERVISOR</th>
						<th>MERCADERISTA</th>
						<th>USUARIO</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						<th>SECTOR</th>
						<th>CATEGORIA</th>
						<th>FABRICANTE</th>
						<th>MARCA</th>
						<th>TIPO DE EXHIBICION</th>
						<th>NUMERO EXHIBICION</th>
						<th>FOTO</th>
						<th>FECHA SERVIDOR</th>
					</tr>  
           '; 
             $img_url = "https://webecuador.azurewebsites.net/App/AppSalica/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                     <tr>  
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
						<td style="height:20px">'.utf8_decode($pos_id).'</td> 
						<td style="height:20px">'.utf8_decode($channel).'</td> 
						<td style="height:20px">'.utf8_decode($subchannel).'</td> 
						<td style="height:20px">'.utf8_decode($customer_owner).'</td> 
						<td style="height:20px">'.utf8_decode($pos_name).'</td> 
						<td style="height:20px">'.utf8_decode($region).'</td> 
						<td style="height:20px">'.utf8_decode($kam).'</td>
						<td style="height:20px">'.utf8_decode($province).'</td> 
						<td style="height:20px">'.utf8_decode($city).'</td> 
						<td style="height:20px">'.utf8_decode($zone).'</td> 
						<td style="height:20px">'.utf8_decode($zona_territorio).'</td> 
						<td style="height:20px">'.utf8_decode($address).'</td> 
						<td style="height:20px">'.utf8_decode($supervisor).'</td> 
						<td style="height:20px">'.utf8_decode($mercaderista).'</td> 
						<td style="height:20px">'.utf8_decode($user).'</td> 
						<td class="cls1">'.$latitud.'</td>
						<td class="cls1">'.$longitud.'</td>
						<td style="height:20px">'.utf8_decode($sector).'</td> 
						<td style="height:20px">'.utf8_decode($categoria).'</td> 
						<td style="height:20px">'.utf8_decode($fabricante).'</td> 
						<td style="height:20px">'.utf8_decode($brand).'</td> 
						<td style="height:20px">'.utf8_decode($tipo_exh).'</td> 
						<td style="height:20px">'.utf8_decode($num_exh).'</td> 
						<td style="height:20px">'.$img_url.$foto.'</td> 
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td> 
                     </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteExhibiciones.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function impl_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT 
ico.codigo,
bpa.user, 
ico.fechaservidor, 
bpa.city, 
bpa.channel_segment, 
bpa.customer_owner, 
bpa.format, 
bpa.pos_name, 
bpa.address, 
bpa.latitud, 
bpa.longitud, 
ico.tipo, 
ico.establecimiento,  
ico.direccion,  
ico.fecha, 
ico.hora
FROM 
insert_inicial ico, 
repositorio_locales_dtt bpa 
WHERE ico.fecha REGEXP ? 
AND 
ico.id_pdv=bpa.id
AND
ico.codigo=bpa.pos_id 
GROUP BY
ico.codigo,
ico.tipo, 
ico.establecimiento,  
ico.direccion,  
ico.fecha
 ")){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$codigo,
		$usuario, 
		$fechaservidor, 
		$ciudad, 
		$channel_segment, 
		$nombre_dueno, 
		$formato, 
		$pos, 
		$direccion, 
		$latitud, 
		$longitud, 
		$tipo, 
		$establecimiento,  
		$direccion,  
		$fecha, 
		$hora
		) or die($sql->error);
	    
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                    <tr>  
						<th>CODIGO</th> 
						<th>USUARIO</th> 
						<th>FECHA SERVIDOR</th>  
						<th>CIUDAD</th>
						<th>CHANNEL SEGMENT</th>  
						<th>NOMBRE DUENO</th> 
						<th>FORMATO</th>
						<th>POS</th>
						<th>DIRECCION</th> 
						<th>LATITUD</th>
						<th>LONGITUD</th> 
						<th>TIPO</th>
						<th>ESTABLECIMIENTO</th>
						<th>DIRECCION</th> 
						<th>FECHA</th>
						<th>HORA</th> 
                    </tr>  
           '; 
             $img_url = "https://webecuador.azurewebsites.net/App/CtaEpson/AppEpson/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                     <tr>  
						<td style="height:20px">'.utf8_decode($codigo).'</td> 
						<td style="height:20px">'.utf8_decode($usuario).'</td> 
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td> 
						<td style="height:20px">'.utf8_decode($ciudad).'</td> 
						<td style="height:20px">'.utf8_decode($channel_segment).'</td> 
						<td style="height:20px">'.utf8_decode($nombre_dueno).'</td> 
						<td style="height:20px">'.utf8_decode($formato).'</td> 
						<td style="height:20px">'.utf8_decode($pos).'</td> 
						<td style="height:20px">'.utf8_decode($direccion).'</td> 
						<td class="cls1">'.$latitud.'</td>
						<td class="cls1">'.$longitud.'</td>
						<td style="height:20px">'.utf8_decode($tipo).'</td> 
						<td style="height:20px">'.utf8_decode($establecimiento).'</td> 
						<td style="height:20px">'.utf8_decode($direccion).'</td>  
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
                     </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteInicial.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function inv_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT 
ins.fecha,
ins.hora,
rpdv.pos_id,
rpdv.channel,
rpdv.subchannel,
rpdv.customer_owner,
rpdv.pos_name,
rpdv.region,
rpdv.province,
rpdv.city,
rpdv.zone,
rpdv.address,
rpdv.supervisor,
rpdv.mercaderista,
ins.usuario,
rpdv.latitud,
rpdv.longitud,
rpdv.kam AS territorio, rpdv.tipo AS zona_territorio,
rp.categoria,
ins.subcategoria,
ins.presentacion,
ins.brand,
ins.inventarios,
ins.souvenirs,
ins.sku_code,
ins.fechaservidor 
FROM insert_inventario ins 
LEFT JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
LEFT JOIN repositorio_productos rp
ON ins.sku_code=rp.sku
WHERE ins.fecha REGEXP ?
GROUP BY ins.fecha, ins.hora, ins.usuario;
")){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$fecha,
		$hora,
		$pos_id,
		$channel,
		$subchannel,
		$customer_owner,
		$pos_name,
		$region,
		$province,
		$city,
		$zone,
		$address,
		$supervisor,
		$mercaderista,
		$user,
		$latitud,
		$longitud,
		$kam, $zona_territorio,
		$categoria,
		$subcategoria,
		$presentacion,
		$brand,
		$inventarios,
		$souvenirs,
		$sku_code,
		$fechaservidor 
		) or die($sql->error);
	    
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                    <tr>  
						<th>FECHA</th>
						<th>HORA</th>
						<th>CODIGO</th>
						<th>CANAL</th>
						<th>CADENA</th>
						<th>NOMBRE COMERCIAL</th>
						<th>LOCAL</th>
						<th>REGION</th>
						<th>TERRITORIO</th>
						<th>PROVINCIA</th>
						<th>CIUDAD</th>
						<th>ZONA</th>
						<th>ZONA TERRITORIO</th>
						<th>DIRECCION</th>
						<th>SUPERVISOR</th>
						<th>MERCADERISTA</th>
						<th>USUARIO</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						<th>CATEGORIA</th>
						<th>SUBCATEGORIA</th>
						<th>TIPO DE PRESENTACION</th>
						<th>MARCA</th>
						<th>CANTIDAD</th>
						<th>VENTAS PROMEDIOS</th>
						<th>SKU</th>
						<th>FECHA SERVIDOR</th>
                    </tr>  
           '; 
            // $img_url = "https://webecuador.azurewebsites.net/App/CtaEpson/AppEpson/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                     <tr>  
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
						<td style="height:20px">'.utf8_decode($pos_id).'</td> 
						<td style="height:20px">'.utf8_decode($channel).'</td> 
						<td style="height:20px">'.utf8_decode($subchannel).'</td> 
						<td style="height:20px">'.utf8_decode($customer_owner).'</td> 
						<td style="height:20px">'.utf8_decode($pos_name).'</td> 
						<td style="height:20px">'.utf8_decode($region).'</td> 
						<td style="height:20px">'.utf8_decode($kam).'</td>
						<td style="height:20px">'.utf8_decode($province).'</td> 
						<td style="height:20px">'.utf8_decode($city).'</td> 
						<td style="height:20px">'.utf8_decode($zone).'</td> 
						<td style="height:20px">'.utf8_decode($zona_territorio).'</td> 
						<td style="height:20px">'.utf8_decode($address).'</td> 
						<td style="height:20px">'.utf8_decode($supervisor).'</td> 
						<td style="height:20px">'.utf8_decode($mercaderista).'</td> 
						<td style="height:20px">'.utf8_decode($user).'</td> 
						<td class="cls1">'.$latitud.'</td>
						<td class="cls1">'.$longitud.'</td>

						<td style="height:20px">'.utf8_decode($categoria).'</td> 
						<td style="height:20px">'.utf8_decode($subcategoria).'</td> 
						<td style="height:20px">'.utf8_decode($presentacion).'</td> 
						<td style="height:20px">'.utf8_decode($brand).'</td> 
						<td style="height:20px">'.utf8_decode($inventarios).'</td> 
						<td style="height:20px">'.utf8_decode($souvenirs).'</td> 
						<td style="height:20px">'.utf8_decode($sku_code).'</td> 
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td>  
                     </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteInventario.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function not_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT 
usuario,
fecha, 
hora, 
fechaservidor, 
ciudad, 
canal, 
cliente,
cadena, 
formato, 
zona, 
tamano,
pdv, 
direccion, 
local, 
latitud, 
longitud, 
foto
FROM 
insert_nuevo_pdv
WHERE fecha REGEXP ? 
GROUP BY
usuario,
fecha, 
ciudad, 
canal, 
cliente, 
cadena,
formato, 
zona, 
tamano,
pdv, 
direccion, 
local, 
latitud, 
longitud
 ")){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$usuario,
		$fecha, 
		$hora, 
		$fechaservidor, 
		$ciudad, 
		$canal, 
		$cliente, 
		$cadena, 
		$formato, 
		$zona, 
		$tamano, 
		$pdv, 
		$direccion, 
		$local, 
		$latitud, 
		$longitud, 
		$foto
		) or die($sql->error);
	    
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                     <tr>  
						<th>USUARIO</th>
						<th>FECHA</th>
						<th>HORA</th>
						<th>FECHA SERVIDOR</th>
						<th>CIUDAD</th>
						<th>CANAL</th>
						<th>CLIENTE</th>
						<th>CADENA</th>
						<th>FORMATO</th>
						<th>ZONA</th>
						<th>TAMANO</th>
						<th>DUENO</th>
						<th>DIRECCION</th>
						<th>LOCAL</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						<th>FOTO</th>
                     </tr>  
           '; 
         $img_url = "https://webecuador.azurewebsites.net/App/AppSalica/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                    <tr>  
					 	<td style="height:20px">'.utf8_decode($usuario).'</td> 
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td>  
						<td style="height:20px">'.utf8_decode($ciudad).'</td>  
						<td style="height:20px">'.utf8_decode($canal).'</td>  
						<td style="height:20px">'.utf8_decode($cliente).'</td>  
						<td style="height:20px">'.utf8_decode($cadena).'</td>  
						<td style="height:20px">'.utf8_decode($formato).'</td>  
						<td style="height:20px">'.utf8_decode($zona).'</td> 
						<td style="height:20px">'.utf8_decode($tamano).'</td> 
						<td style="height:20px">'.utf8_decode($pdv).'</td> 
						<td style="height:20px">'.utf8_decode($direccion).'</td>  
						<td style="height:20px">'.utf8_decode($local).'</td>  
						<td class="cls1">'.$latitud.'</td>
						<td class="cls1">'.$longitud.'</td>
						<td style="height:20px">'.$img_url.$foto.'</td>
                    </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteNuevoPDV.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function not_reporte_excel($fecha, $mysqli) {
// $fecha = '%'.$fecha;
if ($sql = $mysqli->prepare("
SELECT 
ins.fecha,
ins.hora,
rpdv.pos_id,
rpdv.channel,
rpdv.subchannel,
rpdv.customer_owner,
rpdv.pos_name,
rpdv.region,
rpdv.province,
rpdv.city,
rpdv.zone,
rpdv.address,
rpdv.supervisor,
rpdv.mercaderista,
ins.usuario,
rpdv.latitud,
rpdv.longitud,
rpdv.kam AS territorio, rpdv.tipo AS zona_territorio,
rp.categoria,
ins.subcategoria,
ins.presentacion,
ins.sku_code,
ins.brand,
ins.manufacturer,
ins.regular_price,
ins.promotional_price,
ins.ofert_price,
ins.fechaservidor 
FROM insert_precios ins 
LEFT JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
LEFT JOIN repositorio_productos rp
ON ins.sku_code=rp.sku
WHERE ins.fecha REGEXP ?
GROUP BY ins.fecha, ins.hora, ins.usuario, ins.sku_code;
")){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$fecha,
		$hora,
		$pos_id,
		$channel,
		$subchannel,
		$customer_owner,
		$pos_name,
		$region,
		$province,
		$city,
		$zone,
		$address,
		$supervisor,
		$mercaderista,
		$user,
		$latitud,
		$longitud,
		$kam, 
		$zona_territorio,
		
		$categoria,
		$subcategoria,
		$presentacion,
		$sku_code,
		$brand,
		$manufacturer,
		$regular_price,
		$promotional_price,
		$ofert_price,
		$fechaservidor 
		) or die($sql->error);
	    
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                    <tr>  
						<th>FECHA</th>
						<th>HORA</th>
						<th>CODIGO</th>
						<th>CANAL</th>
						<th>CADENA</th>
						<th>NOMBRE COMERCIAL</th>
						<th>LOCAL</th>
						<th>REGION</th>
						<th>TERRITORIO</th>
						<th>PROVINCIA</th>
						<th>CIUDAD</th>
						<th>ZONA</th>
						<th>ZONA TERRITORIO</th>
						<th>DIRECCION</th>
						<th>SUPERVISOR</th>
						<th>MERCADERISTA</th>
						<th>USUARIO</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>

						<th>CATEGORIA</th>
						<th>SUBCATEGORIA</th>
						<th>TIPO DE PRESENTACION</th>
						<th>SKU</th>
						<th>MARCA</th>
						<th>FABRICANTE</th>
						<th>PRECIO PVP</th>
						<th>PRECIO REGULAR</th>
						<th>PRECIO OFERTA</th>
						<th>FECHA SERVIDOR</th>
                    </tr>  
           '; 
         $img_url = "https://webecuador.azurewebsites.net/App/CtaEpson/AppEpson/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                    <tr>  
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
						<td style="height:20px">'.utf8_decode($pos_id).'</td> 
						<td style="height:20px">'.utf8_decode($channel).'</td> 
						<td style="height:20px">'.utf8_decode($subchannel).'</td> 
						<td style="height:20px">'.utf8_decode($customer_owner).'</td> 
						<td style="height:20px">'.utf8_decode($pos_name).'</td> 
						<td style="height:20px">'.utf8_decode($region).'</td> 
						<td style="height:20px">'.utf8_decode($kam).'</td> 
						<td style="height:20px">'.utf8_decode($province).'</td> 
						<td style="height:20px">'.utf8_decode($city).'</td> 
						<td style="height:20px">'.utf8_decode($zone).'</td> 
						<td style="height:20px">'.utf8_decode($zona_territorio).'</td> 
						<td style="height:20px">'.utf8_decode($address).'</td> 
						<td style="height:20px">'.utf8_decode($supervisor).'</td> 
						<td style="height:20px">'.utf8_decode($mercaderista).'</td> 
						<td style="height:20px">'.utf8_decode($user).'</td> 
						<td class="cls1">'.$latitud.'</td>
						<td class="cls1">'.$longitud.'</td>
						<td style="height:20px">'.utf8_decode($categoria).'</td> 
						<td style="height:20px">'.utf8_decode($subcategoria).'</td> 
						<td style="height:20px">'.utf8_decode($presentacion).'</td> 
						<td style="height:20px">'.utf8_decode($sku_code).'</td> 
						<td style="height:20px">'.utf8_decode($brand).'</td> 
						<td style="height:20px">'.utf8_decode($manufacturer).'</td> 
						<td style="height:20px">'.utf8_decode($regular_price).'</td> 
						<td style="height:20px">'.utf8_decode($promotional_price).'</td> 
						<td style="height:20px">'.utf8_decode($ofert_price).'</td> 
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td> 
                    </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReportePrecios.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function pre_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT 
ins.fecha,
ins.hora,
rpdv.pos_id,
rpdv.channel,
rpdv.subchannel,
rpdv.customer_owner,
rpdv.pos_name,
rpdv.region,
rpdv.province,
rpdv.city,
rpdv.zone,
rpdv.kam AS territorio, rpdv.tipo AS zona_territorio,
rpdv.address,
rpdv.supervisor,
rpdv.mercaderista,
ins.usuario,
rpdv.latitud,
rpdv.longitud,
rp.categoria,
ins.subcategoria,
ins.sku,
rp.fabricante,
ins.brand,
ins.tipo_promocion,
ins.mecanica,
ins.foto,
ins.fechaservidor 
FROM insert_promociones ins 
LEFT JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id
LEFT JOIN repositorio_productos rp
ON ins.sku=rp.sku
WHERE ins.fecha REGEXP ?
GROUP BY ins.fecha, ins.hora;
")){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$fecha,
		$hora,
		$pos_id,
		$channel,
		$subchannel,
		$customer_owner,
		$pos_name,
		$region,
		$province,
		$city,
		$zone,
		$kam, $zona_territorio,
		$address,
		$supervisor,
		$mercaderista,
		$user,
		$latitud,
		$longitud,
		$categoria,
		$subcategoria,
		$sku,
		$fabricante,
		$brand,
		$tipo_promocion,
		$mecanica,
		$foto,
		$fechaservidor 
		) or die($sql->error);
		
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                    <tr>  
						<th>FECHA</th>
						<th>HORA</th>
						<th>CODIGO</th>
						<th>CANAL</th>
						<th>CADENA</th>
						<th>NOMBRE COMERCIAL</th>
						<th>LOCAL</th>
						<th>REGION</th>
						<th>TERRITORIO</th>
						<th>PROVINCIA</th>
						<th>CIUDAD</th>
						<th>ZONA</th>
						<th>ZONA TERRITORIO</th>
						<th>DIRECCION</th>
						<th>SUPERVISOR</th>
						<th>MERCADERISTA</th>
						<th>USUARIO</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						<th>CATEGORIA</th>
						<th>SUBCATEGORIA</th>
						<th>SKU</th>
						<th>FABRICANTE</th>
						<th>MARCA</th>
						<th>TIPO DE PROMOCION</th>
						<th>MECANICA PROMOCIONAL</th>
						<th>FOTO URL</th>
						<th>FECHA SERVIDOR</th>
                    </tr>  
           '; 
         $img_url = "https://webecuador.azurewebsites.net/App/AppSalica/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                    <tr>  
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
						<td style="height:20px">'.utf8_decode($pos_id).'</td> 
						<td style="height:20px">'.utf8_decode($channel).'</td> 
						<td style="height:20px">'.utf8_decode($subchannel).'</td> 
						<td style="height:20px">'.utf8_decode($customer_owner).'</td> 
						<td style="height:20px">'.utf8_decode($pos_name).'</td> 
						<td style="height:20px">'.utf8_decode($region).'</td> 
						<td style="height:20px">'.utf8_decode($kam).'</td>
						<td style="height:20px">'.utf8_decode($province).'</td> 
						<td style="height:20px">'.utf8_decode($city).'</td> 
						<td style="height:20px">'.utf8_decode($zone).'</td> 
						<td style="height:20px">'.utf8_decode($zona_territorio).'</td> 
						<td style="height:20px">'.utf8_decode($address).'</td> 
						<td style="height:20px">'.utf8_decode($supervisor).'</td> 
						<td style="height:20px">'.utf8_decode($mercaderista).'</td> 
						<td style="height:20px">'.utf8_decode($user).'</td> 
						<td class="cls1">'.$latitud.'</td>
						<td class="cls1">'.$longitud.'</td>
						<td style="height:20px">'.utf8_decode($categoria).'</td> 
						<td style="height:20px">'.utf8_decode($subcategoria).'</td> 
						<td style="height:20px">'.utf8_decode($sku).'</td> 
						<td style="height:20px">'.utf8_decode($fabricante).'</td> 
						<td style="height:20px">'.utf8_decode($brand).'</td> 
						<td style="height:20px">'.utf8_decode($tipo_promocion).'</td> 
						<td style="height:20px">'.utf8_decode($mecanica).'</td> 
						<td style="height:20px">'.$img_url.$foto.'</td>
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td> 
						
                    </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReportePromociones.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function venta_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT 
rpdv.pos_id,
rpdv.channel,
rpdv.subchannel,
rpdv.customer_owner,
rpdv.pos_name,
rpdv.region,
rpdv.province,
rpdv.city,
rpdv.zone,
rpdv.address,
rpdv.supervisor,
rpdv.mercaderista,
ins.usuario,
rpdv.latitud,
rpdv.longitud,
rpdv.kam AS territorio, rpdv.tipo AS zona_territorio,
ins.categoria,
ins.implementacion,
ins.observacion,
ins.foto,
ins.foto_despues,
ins.fecha,
ins.hora,
ins.fecha_servidor 
FROM insert_evidencias ins
INNER JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
WHERE ins.fecha REGEXP ?
GROUP BY foto, foto_despues, fecha, hora;
")){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$pos_id,
		$channel,
		$subchannel,
		$customer_owner,
		$pos_name,
		$region,
		$province,
		$city,
		$zone,
		$address,
		$supervisor,
		$mercaderista,
		$user,
		$latitud,
		$longitud,
		$kam, 
		$zona_territorio,
		$categoria,
		$implementacion,
		$observacion,
		$foto,
		$foto_despues,
		$fecha,
		$hora,
		$fechaservidor 
		) or die($sql->error);
	    
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
					<tr>  
						<th>CODIGO</th>
						<th>CANAL</th>
						<th>CADENA</th>
						<th>NOMBRE COMERCIAL</th>
						<th>LOCAL</th>
						<th>REGION</th>
						<th>TERRITORIO</th>
						<th>PROVINCIA</th>
						<th>CIUDAD</th>
						<th>ZONA</th>
						<th>ZONA TERRITORIO</th>
						<th>DIRECCION</th>
						<th>SUPERVISOR</th>
						<th>MERCADERISTA</th>
						<th>USUARIO</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						<th>SUBCATEGORIA</th>
						<th>STATUS</th>
						<th>OBSERVACION</th>
						<th>URL FOTO ANTES</th>
						<th>URL FOTO DESPUES</th>
						<th>FECHA</th>
						<th>HORA</th>
						<th>FECHA SERVIDOR</th>
					</tr>  
           '; 
         $img_url = "https://webecuador.azurewebsites.net/App/AppSalica/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                    <tr>  
						<td style="height:20px">'.utf8_decode($pos_id).'</td> 
						<td style="height:20px">'.utf8_decode($channel).'</td> 
						<td style="height:20px">'.utf8_decode($subchannel).'</td> 
						<td style="height:20px">'.utf8_decode($customer_owner).'</td> 
						<td style="height:20px">'.utf8_decode($pos_name).'</td> 
						<td style="height:20px">'.utf8_decode($region).'</td> 
						<td style="height:20px">'.utf8_decode($kam).'</td>
						<td style="height:20px">'.utf8_decode($province).'</td> 
						<td style="height:20px">'.utf8_decode($city).'</td> 
						<td style="height:20px">'.utf8_decode($zone).'</td> 
						<td style="height:20px">'.utf8_decode($zona_territorio).'</td> 
						<td style="height:20px">'.utf8_decode($address).'</td> 
						<td style="height:20px">'.utf8_decode($supervisor).'</td> 
						<td style="height:20px">'.utf8_decode($mercaderista).'</td> 
						<td style="height:20px">'.utf8_decode($user).'</td> 
						<td class="cls1">'.$latitud.'</td>
						<td class="cls1">'.$longitud.'</td>
						<td style="height:20px">'.utf8_decode($categoria).'</td> 
						<td style="height:20px">'.utf8_decode($implementacion).'</td> 
						<td style="height:20px">'.utf8_decode($observacion).'</td>
						<td style="height:20px">'.$img_url.$foto.'</td> 
						<td style="height:20px">'.$img_url.$foto_despues.'</td> 
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td> 
                    </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteEvidencias.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function venta_excel_impulso($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT 
rpdv.pos_id,
rpdv.channel,
rpdv.subchannel,
rpdv.customer_owner,
rpdv.pos_name,
rpdv.region,
rpdv.province,
rpdv.city,
rpdv.zone,
rpdv.address,
rpdv.supervisor,
rpdv.mercaderista,
ins.usuario,
rpdv.latitud,
rpdv.longitud,
rpdv.kam AS territorio, rpdv.tipo AS zona_territorio,
ins.categoria,
ins.brand,
rp.segmento,
ins.sku_code,
ins.asignada,
ins.vendida,
ins.adicional,
ins.cumplimiento,
ins.fecha,
ins.hora,
ins.fechaservidor 
FROM insert_impulso ins
INNER JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
INNER JOIN repositorio_productos rp
ON ins.sku_code=rp.sku
WHERE ins.fecha REGEXP ?
GROUP BY ins.foto, ins.fecha, ins.hora, ins.usuario;
")){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$pos_id,
		$channel,
		$subchannel,
		$customer_owner,
		$pos_name,
		$region,
		$province,
		$city,
		$zone,
		$address,
		$supervisor,
		$mercaderista,
		$user,
		$latitud,
		$longitud,
		$kam, 
		$zona_territorio,
		$categoria,
		$brand,
		$segmento,
		$sku_code,
		$asignada,
		$vendida,
		$adicional,
		$cumplimiento,
		$fecha,
		$hora,
		$fechaservidor 
		) or die($sql->error);
	    
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
					<tr>  
						<th>CODIGO</th>
						<th>CANAL</th>
						<th>CADENA</th>
						<th>NOMBRE COMERCIAL</th>
						<th>LOCAL</th>
						<th>REGION</th>
						<th>TERRITORIO</th>
						<th>PROVINCIA</th>
						<th>CIUDAD</th>
						<th>ZONA</th>
						<th>ZONA TERRITORIO</th>
						<th>DIRECCION</th>
						<th>SUPERVISOR</th>
						<th>MERCADERISTA</th>
						<th>USUARIO</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						<th>SUBCATEGORIA</th>
						<th>MARCA</th>
						<th>SEGMENTO</th>
						<th>SKU</th>
						<th>INVENTARIO INICIAL</th>
						<th>REPOSICION</th>
						<th>INVENTARIO FINAL</th>
						<th>VENTAS</th>
						<th>FECHA</th>
						<th>HORA</th>
						<th>FECHA SERVIDOR</th>
					</tr>  
           '; 
         $img_url = "https://webecuador.azurewebsites.net/App/AppSalica/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                    <tr>  
						<td style="height:20px">'.utf8_decode($pos_id).'</td> 
						<td style="height:20px">'.utf8_decode($channel).'</td> 
						<td style="height:20px">'.utf8_decode($subchannel).'</td> 
						<td style="height:20px">'.utf8_decode($customer_owner).'</td> 
						<td style="height:20px">'.utf8_decode($pos_name).'</td> 
						<td style="height:20px">'.utf8_decode($region).'</td> 
						<td style="height:20px">'.utf8_decode($kam).'</td>
						<td style="height:20px">'.utf8_decode($province).'</td> 
						<td style="height:20px">'.utf8_decode($city).'</td> 
						<td style="height:20px">'.utf8_decode($zone).'</td> 
						<td style="height:20px">'.utf8_decode($zona_territorio).'</td> 
						<td style="height:20px">'.utf8_decode($address).'</td> 
						<td style="height:20px">'.utf8_decode($supervisor).'</td> 
						<td style="height:20px">'.utf8_decode($mercaderista).'</td> 
						<td style="height:20px">'.utf8_decode($user).'</td> 
						<td class="cls1">'.$latitud.'</td>
						<td class="cls1">'.$longitud.'</td>
						<td style="height:20px">'.utf8_decode($categoria).'</td> 
						<td style="height:20px">'.utf8_decode($brand).'</td> 
						<td style="height:20px">'.utf8_decode($segmento).'</td> 
						<td style="height:20px">'.utf8_decode($sku_code).'</td>
						<td style="height:20px">'.utf8_decode($asignada).'</td>
						<td style="height:20px">'.utf8_decode($vendida).'</td>
						<td style="height:20px">'.utf8_decode($adicional).'</td>
						<td style="height:20px">'.utf8_decode($cumplimiento).'</td>
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td> 
                    </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteImpulso.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function reg_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT 
codigo,
canal,
region,
provincia,
ciudad,
zona,
nombrecomercial,
local,
direccion,
supervisor,
mercaderista,
tipo,
latitude,
longitude,
foto,
fecha,
hora,
fechaservidor
FROM 
insert_punto_gps
WHERE fecha REGEXP ?
GROUP BY
codigo,
canal,
region,
provincia,
ciudad,
zona,
nombrecomercial,
local,
direccion,
supervisor,
mercaderista,
tipo,
latitude,
longitude,
fecha")){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$codigo,
		$canal,
		$region,
		$provincia,
		$ciudad,
		$zona,
		$nombrecomercial,
		$local,
		$direccion,
		$supervisor,
		$mercaderista,
		$tipo,
		$latitude,
		$longitude,
		$foto,
		$fecha,
		$hora,
		$fechaservidor
		) or die($sql->error);
	    
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                    <tr>  
						<th>CODIGO</th>
						<th>CANAL</th>
						<th>REGION</th>
						<th>PROVINCIA</th>
						<th>CIUDAD</th>
						<th>ZONA</th>
						<th>NOMBRE COMERCIAL</th>
						<th>LOCAL</th>
						<th>DIRECCION</th>
						<th>SUPERVISOR</th>
						<th>MERCADERISTA</th>
						<th>TIPO</th>
						<th>LATITUDE</th>
						<th>LONGITUDE</th>
						<th>FOTO</th>
						<th>FECHA</th>
						<th>HORA</th>
						<th>FECHA SERVIDOR</th>
                    </tr>  
           '; 
          $img_url = "https://webecuador.azurewebsites.net/App/AppKC/Geo/";
           while($sql->fetch())  
           {  
                $output .= '  
                    <tr>  
						<td style="height:20px">'.utf8_decode($codigo).'</td> 
						<td style="height:20px">'.utf8_decode($canal).'</td> 
						<td style="height:20px">'.utf8_decode($region).'</td> 
						<td style="height:20px">'.utf8_decode($provincia).'</td> 
						<td style="height:20px">'.utf8_decode($ciudad).'</td> 
						<td style="height:20px">'.utf8_decode($zona).'</td> 
						<td style="height:20px">'.utf8_decode($nombrecomercial).'</td> 
						<td style="height:20px">'.utf8_decode($local).'</td> 
						<td style="height:20px">'.utf8_decode($direccion).'</td> 
						<td style="height:20px">'.utf8_decode($supervisor).'</td> 
						<td style="height:20px">'.utf8_decode($mercaderista).'</td> 
						<td style="height:20px">'.utf8_decode($tipo).'</td> 
						<td class="cls1">'.$latitude.'</td> 
						<td class="cls1">'.$longitude.'</td> 
						<td style="height:20px">'.$img_url.$foto.'</td> 
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td> 
                    </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteGeoReferencia.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function share_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT 
rpdv.pos_id,
rpdv.channel,
rpdv.subchannel,
rpdv.customer_owner,
rpdv.pos_name,
rpdv.region,
rpdv.province,
rpdv.city,
rpdv.zone,
rpdv.address,
rpdv.supervisor,
ins.usuario,
ins.usuario,
rpdv.activar,
rpdv.latitud,
rpdv.longitud,
rpdv.kam AS territorio, rpdv.tipo AS zona_territorio,
ins.tipo,
ins.causal,
ins.version,
ins.latitude,
ins.longitude,
ins.bateria,
ins.fecha,
ins.hora,
ins.fechaservidor
FROM insert_registro ins 
INNER JOIN repositorio_locales_dtt rpdv 
ON ins.id_pdv=rpdv.pos_id
WHERE ins.fecha REGEXP ?
GROUP BY ins.fecha, ins.hora, ins.usuario, ins.tipo, ins.id_pdv;
")){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$pos_id,
		$channel,
		$subchannel,
		$customer_owner,
		$pos_name,
		$region,
		$province,
		$city,
		$zone,
		$address,
		$supervisor,
		$mercaderista,
		$user,
		$activar,
		$latitud,
		$longitud,
		$kam, $zona_territorio,
		$tipo,
		$causal,
		$version,
		$latitude,
		$longitude,
		$bateria,
		$fecha,
		$hora,
		$fechaservidor
		
		) or die($sql->error);
	    
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                     <tr>  
					 	<th>CODIGO</th>
						<th>CANAL</th>						
						<th>CADENA</th>
						<th>NOMBRE COMERCIAL</th>
						<th>LOCAL</th>
						<th>REGION</th>
						<th>TERRITORIO</th>
						<th>PROVINCIA</th>
						<th>CIUDAD</th>
						<th>ZONA</th>
						<th>ZONA TERRITORIO</th>
						<th>DIRECCION</th>
						<th>SUPERVISOR</th>
						<th>MERCADERISTA</th>
						<th>USUARIO</th>
						<th>ACTIVAR</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>

						<th>TIPO</th>
						<th>CAUSAL</th>
						<th>FECHA VERSION APP</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						<th>BATERIA</th>
						<th>FECHA</th>
						<th>HORA</th>
						<th>FECHA SERVIDOR</th>
                     </tr>  
           '; 
         $img_url = "https://webecuador.azurewebsites.net/App/AppSalica/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                     <tr>  		
						<td style="height:20px">'.utf8_decode($pos_id).'</td> 
						<td style="height:20px">'.utf8_decode($channel).'</td> 
						<td style="height:20px">'.utf8_decode($subchannel).'</td> 
						<td style="height:20px">'.utf8_decode($customer_owner).'</td> 
						<td style="height:20px">'.utf8_decode($pos_name).'</td> 
						<td style="height:20px">'.utf8_decode($region).'</td> 
						<td style="height:20px">'.utf8_decode($kam).'</td>
						<td style="height:20px">'.utf8_decode($province).'</td> 
						<td style="height:20px">'.utf8_decode($city).'</td> 
						<td style="height:20px">'.utf8_decode($zone).'</td> 
						<td style="height:20px">'.utf8_decode($zona_territorio).'</td> 
						<td style="height:20px">'.utf8_decode($address).'</td> 
						<td style="height:20px">'.utf8_decode($supervisor).'</td> 
						<td style="height:20px">'.utf8_decode($mercaderista).'</td> 
						<td style="height:20px">'.utf8_decode($user).'</td> 
						<td style="height:20px">'.utf8_decode($activar).'</td> 
						<td class="cls1">'.$latitude.'</td>
						<td class="cls1">'.$longitude.'</td>
						<td style="height:20px">'.utf8_decode($tipo).'</td> 
						<td style="height:20px">'.utf8_decode($causal).'</td> 
						<td style="height:20px">'.utf8_decode($version).'</td> 
						<td style="height:20px">'.utf8_decode($latitude).'</td> 
						<td style="height:20px">'.utf8_decode($longitude).'</td> 
						<td style="height:20px">'.utf8_decode($bateria).'</td> 
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td> 
                     </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteRegistro.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function vent_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT 
ins.fecha,
ins.hora,
rpdv.pos_id,
rpdv.channel,
rpdv.subchannel,
rpdv.customer_owner,
rpdv.pos_name,
rpdv.region,
rpdv.province,
rpdv.city,
rpdv.zone,
rpdv.address,
rpdv.supervisor,
rpdv.mercaderista,
ins.usuario,
rpdv.latitud,
rpdv.longitud,
rpdv.kam AS territorio, rpdv.tipo AS zona_territorio,
rp.categoria,
ins.subcategoria,
ins.segmento,
ins.marca_seleccionada,
ins.brand,
ins.manufacturer,
ins.ctms_percha,
ins.ctms_marca,
ins.porcentaje,
ins.fechaservidor 
FROM insert_share_shelf ins 
LEFT JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id
LEFT JOIN repositorio_productos rp
ON ins.subcategoria=rp.subcategoria
WHERE ins.fecha REGEXP ?
GROUP BY fecha, hora, codigo, usuario, channel, subcategoria, segmento, brand, manufacturer;
")){
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
	$sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$fecha,
		$hora,
		$pos_id,
		$channel,
		$subchannel,
		$customer_owner,
		$pos_name,
		$region,
		$province,
		$city,
		$zone,
		$address,
		$supervisor,
		$mercaderista,
		$user,
		$latitud,
		$longitud,
		$kam, $zona_territorio,
		$categoria,
		$subcategoria,
		$segmento,
		$marca_seleccioanda,
		$brand,
		$manufacturer,
		$ctms_percha,
		$ctms_marca,
		$porcentaje,
		$fechaservidor 
		) or die($sql->error);
	    
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                    <tr>  
						<th>FECHA</th>
						<th>HORA</th>
						<th>CODIGO</th>
						<th>CANAL</th>
						<th>CADENA</th>
						<th>NOMBRE COMERCIAL</th>
						<th>LOCAL</th>
						<th>REGION</th>
						<th>TERRITORIO</th>
						<th>PROVINCIA</th>
						<th>CIUDAD</th>
						<th>ZONA</th>
						<th>ZONA TERRITORIO</th>
						<th>DIRECCION</th>
						<th>SUPERVISOR</th>
						<th>MERCADERISTA</th>
						<th>USUARIO</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						<th>CATEGORIA</th>
						<th>SUBCATEGORIA</th>
						<th>SEGMENTO</th>
						<th>MARCA</th>
						<th>SKU</th>
						<th>FABRICANTE</th>
						<th>TOTAL CARAS PERCHA</th>
						<th>TOTAL CARAS MARCA</th>
						<th>PORCENTAJE</th>
						<th>FECHA SERVIDOR</th>
                    </tr>  
           '; 
        // $img_url = "https://webecuador.azurewebsites.net/App/CtaEpson/AppEpson/Inserts/";
           while($sql->fetch())  
           {  
			
			/*if($marca_seleccionada=='Otros'){
				$ctms_marca = $otros;
			}*/
			
                $output .= '  
                     <tr>  
						<td style="height:20px">'.utf8_decode($fecha).'</td> 
						<td style="height:20px">'.utf8_decode($hora).'</td> 
						<td style="height:20px">'.utf8_decode($pos_id).'</td> 
						<td style="height:20px">'.utf8_decode($channel).'</td> 
						<td style="height:20px">'.utf8_decode($subchannel).'</td> 
						<td style="height:20px">'.utf8_decode($customer_owner).'</td> 
						<td style="height:20px">'.utf8_decode($pos_name).'</td> 
						<td style="height:20px">'.utf8_decode($region).'</td> 
						<td style="height:20px">'.utf8_decode($kam).'</td>
						<td style="height:20px">'.utf8_decode($province).'</td> 
						<td style="height:20px">'.utf8_decode($city).'</td> 
						<td style="height:20px">'.utf8_decode($zone).'</td> 
						<td style="height:20px">'.utf8_decode($zona_territorio).'</td> 
						<td style="height:20px">'.utf8_decode($address).'</td> 
						<td style="height:20px">'.utf8_decode($supervisor).'</td> 
						<td style="height:20px">'.utf8_decode($mercaderista).'</td> 
						<td style="height:20px">'.utf8_decode($user).'</td> 
						<td class="cls1">'.$latitud.'</td>
						<td class="cls1">'.$longitud.'</td>
						<td style="height:20px">'.utf8_decode($categoria).'</td> 
						<td style="height:20px">'.utf8_decode($subcategoria).'</td> 
						<td style="height:20px">'.utf8_decode($segmento).'</td> 
						<td style="height:20px">'.utf8_decode($marca_seleccioanda).'</td> 
						<td style="height:20px">'.utf8_decode($brand).'</td> 
						<td style="height:20px">'.utf8_decode($manufacturer).'</td> 
						<td style="height:20px">'.utf8_decode($ctms_percha).'</td> 
						<td style="height:20px">'.utf8_decode($ctms_marca).'</td> 
						<td style="height:20px">'.utf8_decode($porcentaje).'</td> 
						<td style="height:20px">'.utf8_decode($fechaservidor).'</td> 
                     </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteShare.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

function promo_reporte($fecha, $mysqli) {
if ($sql = $mysqli->prepare("
SELECT 
ins.usuario,
ins.fecha,
ins.hora,
ins.accion,
ins.fecha_servidor
FROM 
insert_log ins
WHERE ins.fecha REGEXP ?
GROUP BY ins.usuario, ins.fecha, ins.hora, ins.accion;
"

)){ 
     
$sql->bind_param('s', $fecha);  // Une “$fecha” al parámetro.
        $sql->execute();    // Ejecuta la consulta preparada.
	$sql->store_result();
	if ($sql->num_rows > 0) {
	    $sql->bind_result(
		$usuario,
		$fecha,
		$hora,
		$accion,
		$fecha_servidor
		
		) or die($sql->error);
	    
           $output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                    <tr>  
						<th>USUARIO</th>
						<th>FECHA</th>
						<th>HORA</th>
						<th>ACCION</th>
						<th>FECHA SERVIDOR</th>
                    </tr>  
           '; 
         $img_url = "https://webecuador.azurewebsites.net/App/CtaEpson/AppEpson/Inserts/";
           while($sql->fetch())  
           {  
                $output .= '  
                     <tr>  
							<td style="height:20px">'.utf8_decode($usuario).'</td> 
							<td style="height:20px">'.utf8_decode($fecha).'</td> 
							<td style="height:20px">'.utf8_decode($hora).'</td> 
							<td style="height:20px">'.utf8_decode($accion).'</td> 
							<td style="height:20px">'.utf8_decode($fecha_servidor).'</td> 
                     </tr>  
                ';  
           }  
           $output .= '</table>';  
           header("Content-Type: application/xls");   
           header("Content-Disposition: attachment; filename=ReporteTimeline.xls");  
           echo $output;  
      }  else{    
	   echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../protected_page.php';
	</script>";
	header("Location:../protected_page.php");
	die();  
      }
   }else{
	echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../protected_page.php';
	</script>";
	die();   
	}
}

?>