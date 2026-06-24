<?php
include_once 'config.php';
require $_SERVER["DOCUMENT_ROOT"] . '/App/XploraEcuador/assets/pluginsV3/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$output = '';

function rastreo_reporte_new($fechaInicio, $fechaFin, $mysqli)
{
	$documento = new Spreadsheet();
	$documento
	->getProperties()
	->setCreator("Lucky Ecuador")
	->setLastModifiedBy('Lucky Ecuador')
	->setTitle('Reporte')
	->setDescription('Reporte');
	
	$fileName="Reporte Rastreo.xlsx";

	$hojaDeProductos = $documento->getActiveSheet();
	$hojaDeProductos->setTitle("Productos");

	# Encabezado
	$encabezado = ["USUARIO",
					"FECHA",
					"HORA",
					"LATITUD",
					"LONGITUD"];
	# El último argumento es por defecto A1
	$hojaDeProductos->fromArray($encabezado, null, 'A1');
	if ($sql = $mysqli->prepare("
		SELECT usuario, latitude, longitude, fecha, hora 
		FROM insert_rastreo 
		WHERE STR_TO_DATE(fecha, '%d/%m/%Y') BETWEEN ? AND ? ")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
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
			
			//$img_url = "https://webecuador.azurewebsites.net/App/AppExhibicionesKC/Inserts/";
			# Comenzamos en la fila 2
			/*$numeroDeFila = 2;
			while ($sql->fetch()) {
				# Escribir registros en el documento
				$hojaDeProductos->setCellValueByColumnAndRow(1, $numeroDeFila, $usuario);
				$hojaDeProductos->setCellValueByColumnAndRow(2, $numeroDeFila, $fecha);
				$hojaDeProductos->setCellValueByColumnAndRow(3, $numeroDeFila, $hora);
				$hojaDeProductos->setCellValueByColumnAndRow(4, $numeroDeFila, $latitud);
				$hojaDeProductos->setCellValueByColumnAndRow(5, $numeroDeFila, $longitud);
				$numeroDeFila++;
			}*/
			# Crear un "escritor"
			$writer = new Xlsx($documento);
			# Le pasamos la ruta de guardado
			ob_end_clean();
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment; filename="'. urlencode($fileName).'"');			
			header("Pragma: no-cache");
			header("Expires: 0");
			ob_end_clean();
			$writer->save('php://output');
			$sql->close();
		} else {
			echo "1";
			die();
		}
	} else {
		echo "2";
		die();
	}

	// echo "1";
}

function rastreo_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare(
		"
SELECT usuario, latitude, longitude, fecha, hora 
FROM insert_rastreo 
WHERE STR_TO_DATE(fecha, '%d/%m/%Y') BETWEEN ? AND ? 
"
	)) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
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
			while ($sql->fetch()) {
				$output .= '  
                     <tr>  
						<td style="height:20px">' . utf8_decode($usuario) . '</td>
						<td style="height:20px">' . utf8_decode($fecha) . '</td>
						<td style="height:20px">' . utf8_decode($hora) . '</td>
						<td class="cls1">' . $latitud . '</td>
						<td class="cls1">' . $longitud . '</td>
                     </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteAppRatreo.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function exhibiciones_excel($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare(
		"
SELECT ciudad, formato, pos, tipo, exhibicion, categoria, estado, observacion, foto, fecha, hora, fecha_servidor 
FROM insert_exhibicioneskc 
WHERE STR_TO_DATE(fecha, '%d/%m/%Y') BETWEEN ? AND ? 
"
	)) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
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
			while ($sql->fetch()) {
				$output .= '  
                     <tr>  
						<td style="height:20px">' . utf8_decode($fecha) . '</td>
						<td style="height:20px">' . utf8_decode($hora) . '</td>
						<td style="height:20px">' . utf8_decode($ciudad) . '</td>
						<td style="height:20px">' . utf8_decode($formato) . '</td>
						<td style="height:20px">' . utf8_decode($pos) . '</td>
						<td style="height:20px">' . utf8_decode($tipo) . '</td>
						<td style="height:20px">' . utf8_decode($exhibicion) . '</td>
						<td style="height:20px">' . utf8_decode($categoria) . '</td>
						<td style="height:20px">' . utf8_decode($estado) . '</td>
						<td style="height:20px">' . utf8_decode($observacion) . '</td>
						<td style="height:20px">' . $img_url . $foto . '</td>
                     </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteAppExhibiciones.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function test_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare("
SELECT 
ins.usuario, 
rp.channel, 
rp.region, 
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
WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ? ")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$usuario,
				$channel,
				$region,
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
						<th>CANAL</th>
						<th>REGION</th>
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
			while ($sql->fetch()) {
				$aprobado;

				if ($calificacion >= 75) {
					$aprobado = 'SI';
				} else {
					$aprobado = 'NO';
				}

				$output .= '  
                     <tr>  
						<td style="height:20px">' . utf8_decode($usuario) . '</td>
						<td style="height:20px">' . utf8_decode($channel) . '</td>
						<td style="height:20px">' . utf8_decode($region) . '</td>
						<td style="height:20px">' . utf8_decode($supervisor) . '</td>
						<td style="height:20px">' . utf8_decode($fecha) . '</td>
						<td style="height:20px">' . utf8_decode($hora) . '</td>
						<td style="height:20px">' . utf8_decode($p1) . '</td>
						<td style="height:20px">' . utf8_decode($p2) . '</td>
						<td style="height:20px">' . utf8_decode($p3) . '</td>
						<td style="height:20px">' . utf8_decode($p4) . '</td>
						<td style="height:20px">' . utf8_decode($p5) . '</td>
						<td style="height:20px">' . utf8_decode($p6) . '</td>
						<td style="height:20px">' . utf8_decode($p7) . '</td>
						<td style="height:20px">' . utf8_decode($p8) . '</td>
						<td style="height:20px">' . utf8_decode($p9) . '</td>
						<td style="height:20px">' . utf8_decode($p10) . '</td>
						<td style="height:20px">' . utf8_decode($p11) . '</td>
						<td style="height:20px">' . utf8_decode($p12) . '</td>
						<td style="height:20px">' . utf8_decode($p13) . '</td>
						<td style="height:20px">' . utf8_decode($p14) . '</td>
						<td style="height:20px">' . utf8_decode($p15) . '</td>
						<td style="height:20px">' . utf8_decode($correctas) . '</td>
						<td style="height:20px">' . utf8_decode($incorrectas) . '</td>
						<td style="height:20px">' . utf8_decode($calificacion) . '</td>
						<td style="height:20px">' . utf8_decode($aprobado) . '</td>
						<td style="height:20px">' . utf8_decode($observacion) . '</td>
						<td style="height:20px">' . utf8_decode($cronometro) . '</td>
                     </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteAppTest.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function exh_ant_desp_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare(
		"
SELECT
rpdv.pos_id,
rpdv.channel,
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
rprod.sector, 
ins.categoria,
ins.subcategoria,
ins.brand,
ins.presentacion,
ins.sku_code,
ins.categoriasec,
ins.subcategoriasec,
ins.brandsec,
ins.presentacionsec,
ins.sku_codesec,
ins.pvc,
ins.cantidad,
ins.cantidad_encontrada,
ins.foto,
ins.observacion,
ins.fechaservidor 
FROM insert_packs ins 
INNER JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
LEFT JOIN repositorio_productos rprod 
ON ins.sku_code=rprod.sku 
WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ? 
GROUP BY ins.codigo, ins.usuario, ins.fecha, ins.hora;
"
	)) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$pos_id,
				$channel,
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
				$sector,
				$categoria,
				$subcategoria,
				$brand,
				$presentacion,
				$sku_code,
				$categoriasec,
				$subcategoriasec,
				$brandsec,
				$presentacionsec,
				$sku_codesec,
				$pvc,
				$cantidad,
				$cantidad_encontrada,
				$foto,
				$observacion,
				$fechaservidor
			) or die($sql->error);
			$output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                     <tr>  
						<th>CODIGO</th>
						<th>CANAL</th>
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
						<th>CATEGORIA PRIMARIA</th>
						<th>SUBCATEGORIA PRIMARIA</th>
						<th>MARCA PRIMARIA</th>
						<th>TIPO PRESENTACION PRIMARIA</th>
						<th>SKU PRIMARIA</th>
						<th>CATEGORIA SECUNDARIA</th>
						<th>SUBCATEGORIA SECUNDARIA</th>
						<th>MARCA SECUNDARIA</th>
						<th>TIPO PRESENTACION SECUNDARIA</th>
						<th>SKU SECUNDARIA</th>
						<th>PVC</th>
						<th>CANTIDAD ARMADA</th>
						<th>CANTIDAD ENCONTRADA</th>
						<th>FOTO URL</th>
						<th>OBSERVACION</th>
						<th>FECHA SERVIDOR</th>
                        </tr>  
           ';
			$img_url = "https://webecuador.azurewebsites.net/App/AppDanec/Inserts/";
			while ($sql->fetch()) {
				$output .= '  
                     <tr>  
						<td style="height:20px">' . utf8_decode($pos_id) . '</td> 
						<td style="height:20px">' . utf8_decode($channel) . '</td> 
						<td style="height:20px">' . utf8_decode($customer_owner) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_name) . '</td> 
						<td style="height:20px">' . utf8_decode($region) . '</td> 
						<td style="height:20px">' . utf8_decode($kam) . '</td>
						
						<td style="height:20px">' . utf8_decode($province) . '</td> 
						<td style="height:20px">' . utf8_decode($city) . '</td> 
						<td style="height:20px">' . utf8_decode($zone) . '</td> 
						<td style="height:20px">' . utf8_decode($zona_territorio) . '</td> 
						<td style="height:20px">' . utf8_decode($address) . '</td> 
						<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
						<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
						<td style="height:20px">' . utf8_decode($user) . '</td> 
						
						<td class="cls1">' . $latitud . '</td>
						<td class="cls1">' . $longitud . '</td>
						
						<td style="height:20px">' . utf8_decode($sector) . '</td> 
						<td style="height:20px">' . utf8_decode($categoria) . '</td> 
						<td style="height:20px">' . utf8_decode($subcategoria) . '</td> 
						<td style="height:20px">' . utf8_decode($brand) . '</td> 
						<td style="height:20px">' . utf8_decode($presentacion) . '</td> 
						<td style="height:20px">' . utf8_decode($sku_code) . '</td> 
						<td style="height:20px">' . utf8_decode($categoriasec) . '</td> 
						<td style="height:20px">' . utf8_decode($subcategoriasec) . '</td> 
						<td style="height:20px">' . utf8_decode($brandsec) . '</td> 
						<td style="height:20px">' . utf8_decode($presentacionsec) . '</td> 
						<td style="height:20px">' . utf8_decode($sku_codesec) . '</td> 
						<td style="height:20px">' . utf8_decode($pvc) . '</td> 
						<td style="height:20px">' . utf8_decode($cantidad) . '</td> 
						<td style="height:20px">' . utf8_decode($cantidad_encontrada) . '</td> 
						<td style="height:20px">' . $img_url . $foto . '</td>
						<td style="height:20px">' . utf8_decode($observacion) . '</td> 
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td>
                     </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteOnPacks.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function caducar_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare(
		"
SELECT 
rpdv.pos_id,
rpdv.channel,
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
ins.subcategoria,
ins.brand,
ins.sku_code,
ins.fecha_prod,
ins.cantidad_prod,
ins.fechaservidor 
FROM insert_prod_caducar ins 
INNER JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ? 
"
	)) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$pos_id,
				$channel,
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
				$fecha_prod,
				$cantidad_prod,
				$fechaservidor
			) or die($sql->error);
			$output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                    <tr>  
						<th>CODIGO</th>
						<th>CANAL</th>
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
						<th>FECHA PRODUCTO</th>
						<th>CANTIDAD</th>
						<th>FECHA SERVIDOR</th>
					</tr>  
           ';
			$img_url = "https://webecuador.azurewebsites.net/App/AppDanec/Inserts/";
			while ($sql->fetch()) {
				$output .= '  
                     <tr>  
						<td style="height:20px">' . utf8_decode($pos_id) . '</td> 
						<td style="height:20px">' . utf8_decode($channel) . '</td> 
						<td style="height:20px">' . utf8_decode($customer_owner) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_name) . '</td> 
						<td style="height:20px">' . utf8_decode($region) . '</td> 
						<td style="height:20px">' . utf8_decode($kam) . '</td>  
						<td style="height:20px">' . utf8_decode($province) . '</td> 
						<td style="height:20px">' . utf8_decode($city) . '</td> 
						<td style="height:20px">' . utf8_decode($zone) . '</td> 
						<td style="height:20px">' . utf8_decode($zona_territorio) . '</td> 
						<td style="height:20px">' . utf8_decode($address) . '</td> 
						<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
						<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
						<td style="height:20px">' . utf8_decode($user) . '</td> 
						<td class="cls1">' . $latitud . '</td>
						<td class="cls1">' . $longitud . '</td>
						<td style="height:20px">' . utf8_decode($categoria) . '</td> 
						<td style="height:20px">' . utf8_decode($subcategoria) . '</td> 
						<td style="height:20px">' . utf8_decode($brand) . '</td> 
						<td style="height:20px">' . utf8_decode($sku_code) . '</td> 
						<td style="height:20px">' . utf8_decode($fecha_prod) . '</td> 
						<td style="height:20px">' . utf8_decode($cantidad_prod) . '</td> 
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
                     </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteCaducar.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function agot_reporte($fechaInicio, $fechaFin, $mysqli)
{
	$fecha = '%' . $fecha;
	if ($sql = $mysqli->prepare(
		"
SELECT 
rpdv.pos_id,
rpdv.channel,
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
ins.segment1,
ins.sku_code,
ins.brand,
ins.codifica,
ins.ausencia,
ins.disponible,
ins.responsable,
ins.razones,
ins.fechaservidor 
FROM insert_codificados_osa ins 
INNER JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ? ;
"
	)) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$pos_id,
				$channel,
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
				$sector,
				$categoria,
				$segment1,
				$sku_code,
				$brand,
				$codifica,
				$ausencia,
				$disponible,
				$responsable,
				$razones,
				$fechaservidor
			) or die($sql->error);

			$output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                     <tr>  
						<th>CODIGO</th>
						<th>CANAL</th>
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
						<th>SEGMENTO</th>
						<th>SKU</th>
						<th>MARCA</th>
						<th>CODIFICA</th>
						<th>AUSENCIA</th>
						<th>DISPONIBLE</th>
						<th>RESPONSABLE</th>
						<th>RAZONES</th>
						<th>FECHA SERVIDOR</th>
                     </tr>  
           ';
			//    $img_url = "https://webecuador.azurewebsites.net/App/CtaEpson/AppEpson/Inserts/";
			while ($sql->fetch()) {
				$output .= '  
                     <tr>  
						<td style="height:20px">' . utf8_decode($pos_id) . '</td> 
						<td style="height:20px">' . utf8_decode($channel) . '</td> 
						<td style="height:20px">' . utf8_decode($customer_owner) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_name) . '</td> 
						<td style="height:20px">' . utf8_decode($region) . '</td> 
						<td style="height:20px">' . utf8_decode($kam) . '</td>
						<td style="height:20px">' . utf8_decode($province) . '</td> 
						<td style="height:20px">' . utf8_decode($city) . '</td> 
						<td style="height:20px">' . utf8_decode($zone) . '</td> 
						<td style="height:20px">' . utf8_decode($zona_territorio) . '</td> 
						<td style="height:20px">' . utf8_decode($address) . '</td> 
						<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
						<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
						<td style="height:20px">' . utf8_decode($user) . '</td> 
						<td class="cls1">' . $latitud . '</td>
						<td class="cls1">' . $longitud . '</td>
						<td style="height:20px">' . utf8_decode($sector) . '</td> 
						<td style="height:20px">' . utf8_decode($categoria) . '</td> 
						<td style="height:20px">' . utf8_decode($segment1) . '</td> 
						<td style="height:20px">' . utf8_decode($sku_code) . '</td> 
						<td style="height:20px">' . utf8_decode($brand) . '</td> 
						<td style="height:20px">' . utf8_decode($codifica) . '</td> 
						<td style="height:20px">' . utf8_decode($ausencia) . '</td> 
						<td style="height:20px">' . utf8_decode($disponible) . '</td> 
						<td style="height:20px">' . utf8_decode($responsable) . '</td> 
						<td style="height:20px">' . utf8_decode($razones) . '</td> 
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
                     </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteCodificadosyOsa.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function exh_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare(
		"
SELECT 
ins.fecha,
ins.hora,
rpdv.pos_id,
rpdv.channel,
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
ins.subcategoria,
ins.fabricante,
ins.otrosfabricantes,
ins.brand,
ins.tipo_exh,
ins.zona_exh,
ins.contratada,
ins.condicion,
ins.promocional,
ins.foto,
ins.fechaservidor 
FROM insert_exhibiciones ins 
INNER JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ? 
GROUP BY ins.codigo, ins.usuario, ins.fecha, ins.hora;
"
	)) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$fecha,
				$hora,
				$pos_id,
				$channel,
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
				$sector,
				$categoria,
				$subcategoria,
				$fabricante,
				$otrosfabricantes,
				$brand,
				$tipo_exh,
				$zona_exh,
				$contratada,
				$condicion,
				$promocional,
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
						<th>SUBCATEGORIA</th>
						<th>FABRICANTE</th>
						<th>OTROS FABRICANTES</th>
						<th>MARCA</th>
						<th>TIPO DE EXHIBICION</th>
						<th>ZONA EXHIBICION</th>
						<th>CONTRATADA</th>
						<th>CONDICION</th>
						<th>PROMOCIONAL</th>
						<th>FOTO</th>
						<th>FECHA SERVIDOR</th>
					</tr>  
           ';
			$img_url = "https://webecuador.azurewebsites.net/App/AppDanec/Inserts/";
			while ($sql->fetch()) {
				$output .= '  
                     <tr>  
						<td style="height:20px">' . utf8_decode($fecha) . '</td> 
						<td style="height:20px">' . utf8_decode($hora) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_id) . '</td> 
						<td style="height:20px">' . utf8_decode($channel) . '</td> 
						<td style="height:20px">' . utf8_decode($customer_owner) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_name) . '</td> 
						<td style="height:20px">' . utf8_decode($region) . '</td> 
						<td style="height:20px">' . utf8_decode($kam) . '</td>
						<td style="height:20px">' . utf8_decode($province) . '</td> 
						<td style="height:20px">' . utf8_decode($city) . '</td> 
						<td style="height:20px">' . utf8_decode($zone) . '</td> 
						<td style="height:20px">' . utf8_decode($zona_territorio) . '</td> 
						<td style="height:20px">' . utf8_decode($address) . '</td> 
						<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
						<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
						<td style="height:20px">' . utf8_decode($user) . '</td> 
						<td class="cls1">' . $latitud . '</td>
						<td class="cls1">' . $longitud . '</td>
						<td style="height:20px">' . utf8_decode($sector) . '</td> 
						<td style="height:20px">' . utf8_decode($categoria) . '</td> 
						<td style="height:20px">' . utf8_decode($subcategoria) . '</td> 
						<td style="height:20px">' . utf8_decode($fabricante) . '</td> 
						<td style="height:20px">' . utf8_decode($otrosfabricantes) . '</td> 
						<td style="height:20px">' . utf8_decode($brand) . '</td> 
						<td style="height:20px">' . utf8_decode($tipo_exh) . '</td> 
						<td style="height:20px">' . utf8_decode($zona_exh) . '</td> 
						<td style="height:20px">' . utf8_decode($contratada) . '</td> 
						<td style="height:20px">' . utf8_decode($condicion) . '</td> 
						<td style="height:20px">' . utf8_decode($promocional) . '</td> 
						<td style="height:20px">' . $img_url . $foto . '</td> 
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
                     </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteExhibiciones.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function impl_reporte($fechaInicio, $fechaFin, $mysqli)
{
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
WHERE STR_TO_DATE(ico.fecha, '%d/%m/%Y') BETWEEN ? AND ? 
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
 ")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
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
			while ($sql->fetch()) {
				$output .= '  
                     <tr>  
						<td style="height:20px">' . utf8_decode($codigo) . '</td> 
						<td style="height:20px">' . utf8_decode($usuario) . '</td> 
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
						<td style="height:20px">' . utf8_decode($ciudad) . '</td> 
						<td style="height:20px">' . utf8_decode($channel_segment) . '</td> 
						<td style="height:20px">' . utf8_decode($nombre_dueno) . '</td> 
						<td style="height:20px">' . utf8_decode($formato) . '</td> 
						<td style="height:20px">' . utf8_decode($pos) . '</td> 
						<td style="height:20px">' . utf8_decode($direccion) . '</td> 
						<td class="cls1">' . $latitud . '</td>
						<td class="cls1">' . $longitud . '</td>
						<td style="height:20px">' . utf8_decode($tipo) . '</td> 
						<td style="height:20px">' . utf8_decode($establecimiento) . '</td> 
						<td style="height:20px">' . utf8_decode($direccion) . '</td>  
						<td style="height:20px">' . utf8_decode($fecha) . '</td> 
						<td style="height:20px">' . utf8_decode($hora) . '</td> 
                     </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteInicial.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function inv_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare("
		SELECT 
		ins.fecha, 
		ins.hora, 
		rpdv.pos_id,
		rpdv.channel,
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
		ins.sector,
		ins.categoria,
		ins.subcategoria,
		ins.presentacion,
		ins.brand,
		ins.inventarios,
		ins.sku_code,
		ins.souvenirs,
		ins.total,
		ins.tipo_conteo_total,
		ins.cantidad_defectuosas,
		ins.tipo_conteo_defectuosas,
		ins.fecha_caducidad,
		ins.causal,
		ins.foto,
		ins.fechaservidor 
		FROM insert_inventario ins 
		INNER JOIN repositorio_locales_dtt rpdv 
		ON ins.codigo=rpdv.pos_id 
		WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ?
		GROUP BY 
		ins.fecha, 
		ins.hora, 
		ins.usuario,
		ins.sector,
		ins.categoria,
		ins.subcategoria,
		ins.presentacion,
		ins.brand,
		ins.inventarios,
		ins.sku_code,
		ins.souvenirs,
		ins.total,
		ins.tipo_conteo_total,
		ins.cantidad_defectuosas,
		ins.tipo_conteo_defectuosas,
		ins.fecha_caducidad,
		ins.causal,
		ins.foto;
		")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$fecha,
				$hora,
				$pos_id,
				$channel,
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
				$sector,
				$categoria,
				$subcategoria,
				$presentacion,
				$brand,
				$inventarios,
				$sku_code,
				$souvenirs,
				$total,
				$tipo_conteo_total,
				$cantidad_defectuosas,
				$tipo_conteo_defectuosas,
				$fecha_caducidad,
				$causal,
				$foto,
				$fechaservidor
			) or die($sql->error);

			$output .= '  
					<style>.cls1 {mso-number-format:"\@"}</style>

					<table class="table" bordered="1">  
						<tr>  
							<th>CODIGO</th>
							<th>CANAL</th>
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
							<th>SUBCATEGORIA</th>
							<th>TIPO DE PRESENTACION</th>
							<th>MARCA</th>
							<th>SKU</th>
							<th>TOTAL</th>
							<th>TIPO CONTEO TOTAL</th>
							<th>CANTIDAD DEFECTUOSOS</th>
							<th>TIPO CONTEO DEFECTUOSOS</th>
							<th>FECHA CADUCIDAD</th>
							<th>CAUSAL</th>
							<th>FOTO</th>
							<th>FECHA SERVIDOR</th>
						</tr>  
				';
			$img_url = "https://webecuador.azurewebsites.net/App/AppDanec/Inserts/";
			while ($sql->fetch()) {
				$output .= '  
						 <tr>  
							<td style="height:20px">' . utf8_decode($pos_id) . '</td> 
							<td style="height:20px">' . utf8_decode($channel) . '</td> 
							<td style="height:20px">' . utf8_decode($customer_owner) . '</td> 
							<td style="height:20px">' . utf8_decode($pos_name) . '</td> 
							<td style="height:20px">' . utf8_decode($region) . '</td> 
							<td style="height:20px">' . utf8_decode($kam) . '</td>
							<td style="height:20px">' . utf8_decode($province) . '</td> 
							<td style="height:20px">' . utf8_decode($city) . '</td> 
							<td style="height:20px">' . utf8_decode($zone) . '</td> 
							<td style="height:20px">' . utf8_decode($zona_territorio) . '</td> 
							<td style="height:20px">' . utf8_decode($address) . '</td> 
							<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
							<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
							<td style="height:20px">' . utf8_decode($user) . '</td> 
							<td class="cls1">' . $latitud . '</td>
							<td class="cls1">' . $longitud . '</td>
							<td style="height:20px">' . utf8_decode($sector) . '</td> 
							<td style="height:20px">' . utf8_decode($categoria) . '</td> 
							<td style="height:20px">' . utf8_decode($subcategoria) . '</td> 
							<td style="height:20px">' . utf8_decode($presentacion) . '</td> 
							<td style="height:20px">' . utf8_decode($brand) . '</td> 
							<td style="height:20px">' . utf8_decode($sku_code) . '</td>
							<td style="height:20px">' . utf8_decode($total) . '</td>  
							<td style="height:20px">' . utf8_decode($tipo_conteo_total) . '</td>  
							<td style="height:20px">' . utf8_decode($cantidad_defectuosas) . '</td>  
							<td style="height:20px">' . utf8_decode($tipo_conteo_defectuosas) . '</td>  
							<td style="height:20px">' . utf8_decode($fecha_caducidad) . '</td>  
							<td style="height:20px">' . utf8_decode($causal) . '</td>  
							<td style="height:20px">' . $img_url . $foto . '</td> 
							<td style="height:20px">' . utf8_decode($fechaservidor) . '</td>  
						 </tr>  
					';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteInventario.xls");
			echo $output;
		} else {
			echo "<script>
			alert('No se encontraron registros.');
			window.location.href='../index.php';
			</script>";
			die();
		}
	} else {
		echo "<script>
		alert('Ha ocurrido un error.');
		window.location.href='../index.php';
		</script>";
		die();
	}
}

function evidencias_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare("
		SELECT 
		ins.fecha, 
		ins.hora, 
		rpdv.pos_id,
		rpdv.channel,
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
		ins.comentario,
		ins.foto_antes,
		ins.foto_despues,
		ins.fechaservidor 
		FROM insert_evidencias ins 
		INNER JOIN repositorio_locales_dtt rpdv 
		ON ins.codigo=rpdv.pos_id 
		WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ? 
		GROUP BY ins.foto_antes, ins.foto_antes;
		")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$fecha,
				$hora,
				$pos_id,
				$channel,
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
				$comentario,
				$foto_antes,
				$foto_despues,
				$fechaservidor
			) or die($sql->error);

			$output .= '  
				<style>.cls1 {mso-number-format:"\@"}</style>
			  
					<table class="table" bordered="1">  
						<tr>  
							<th>CODIGO</th>
							<th>CANAL</th>
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
							<th>COMENTARIO</th>
							<th>FOTO ANTES</th>
							<th>FOTO DESPUES</th>
							<th>FECHA SERVIDOR</th>
						</tr>  
				';
			$img_url = "https://webecuador.azurewebsites.net/App/AppDanec/Inserts/";
			while ($sql->fetch()) {
				$output .= '  
						 <tr>  
							<td style="height:20px">' . utf8_decode($pos_id) . '</td> 
							<td style="height:20px">' . utf8_decode($channel) . '</td> 
							<td style="height:20px">' . utf8_decode($customer_owner) . '</td> 
							<td style="height:20px">' . utf8_decode($pos_name) . '</td> 
							<td style="height:20px">' . utf8_decode($region) . '</td> 
							<td style="height:20px">' . utf8_decode($kam) . '</td>
							<td style="height:20px">' . utf8_decode($province) . '</td> 
							<td style="height:20px">' . utf8_decode($city) . '</td> 
							<td style="height:20px">' . utf8_decode($zone) . '</td> 
							<td style="height:20px">' . utf8_decode($zona_territorio) . '</td> 
							<td style="height:20px">' . utf8_decode($address) . '</td> 
							<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
							<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
							<td style="height:20px">' . utf8_decode($user) . '</td> 
							<td class="cls1">' . $latitud . '</td>
							<td class="cls1">' . $longitud . '</td>
							<td style="height:20px">' . utf8_decode($comentario) . '</td> 
							<td style="height:20px">' . $img_url . $foto_antes . '</td> 
							<td style="height:20px">' . $img_url . $foto_despues . '</td>
							<td style="height:20px">' . utf8_decode($fechaservidor) . '</td>  
						 </tr>  
					';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteEvidencias.xls");
			echo $output;
		} else {
			echo "<script>
			alert('No se encontraron registros.');
			window.location.href='../index.php';
			</script>";
			die();
		}
	} else {
		echo "<script>
		alert('Ha ocurrido un error.');
		window.location.href='../index.php';
		</script>";
		die();
	}
}

function not_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare("
SELECT 
usuario,
fecha, 
hora, 
fechaservidor, 
ciudad, 
canal, 
cliente, 
formato, 
zona, 
pdv, 
direccion, 
local, 
latitud, 
longitud, 
foto
FROM 
insert_nuevo_pdv
WHERE STR_TO_DATE(fecha, '%d/%m/%Y') BETWEEN ? AND ?  
GROUP BY
usuario,
fecha, 
ciudad, 
canal, 
cliente, 
formato, 
zona, 
pdv, 
direccion, 
local, 
latitud, 
longitud
 ")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
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
				$formato,
				$zona,
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
						<th>CADENA</th>
						<th>FORMATO</th>
						<th>ZONA</th>
						<th>DUENO</th>
						<th>DIRECCION</th>
						<th>LOCAL</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						<th>FOTO</th>
                     </tr>  
           ';
			$img_url = "https://webecuador.azurewebsites.net/App/AppDanec/Inserts/";
			while ($sql->fetch()) {
				$output .= '  
                    <tr>  
					 	<td style="height:20px">' . utf8_decode($usuario) . '</td> 
						<td style="height:20px">' . utf8_decode($fecha) . '</td> 
						<td style="height:20px">' . utf8_decode($hora) . '</td> 
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td>  
						<td style="height:20px">' . utf8_decode($ciudad) . '</td>  
						<td style="height:20px">' . utf8_decode($canal) . '</td>  
						<td style="height:20px">' . utf8_decode($cliente) . '</td>  
						<td style="height:20px">' . utf8_decode($formato) . '</td>  
						<td style="height:20px">' . utf8_decode($zona) . '</td> 
						<td style="height:20px">' . utf8_decode($pdv) . '</td> 
						<td style="height:20px">' . utf8_decode($direccion) . '</td>  
						<td style="height:20px">' . utf8_decode($local) . '</td>  
						<td style="height:20px">' . $latitud . '</td>  
						<td style="height:20px">' . $longitud . '</td>  
						<td style="height:20px">' . $img_url . $foto . '</td>
                    </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteNuevoPDV.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function not_reporte_excel($fechaInicio, $fechaFin, $mysqli)
{
	$fecha = '%' . $fecha;
	if ($sql = $mysqli->prepare("
SELECT 
ins.fecha,
ins.hora,
rpdv.pos_id,
rpdv.channel,
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
INNER JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ? ;
")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$fecha,
				$hora,
				$pos_id,
				$channel,
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
			while ($sql->fetch()) {
				$output .= '  
                    <tr>  
						<td style="height:20px">' . utf8_decode($fecha) . '</td> 
						<td style="height:20px">' . utf8_decode($hora) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_id) . '</td> 
						<td style="height:20px">' . utf8_decode($channel) . '</td> 
						<td style="height:20px">' . utf8_decode($customer_owner) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_name) . '</td> 
						<td style="height:20px">' . utf8_decode($region) . '</td> 
						<td style="height:20px">' . utf8_decode($kam) . '</td> 
						<td style="height:20px">' . utf8_decode($province) . '</td> 
						<td style="height:20px">' . utf8_decode($city) . '</td> 
						<td style="height:20px">' . utf8_decode($zone) . '</td> 
						<td style="height:20px">' . utf8_decode($zona_territorio) . '</td> 
						<td style="height:20px">' . utf8_decode($address) . '</td> 
						<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
						<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
						<td style="height:20px">' . utf8_decode($user) . '</td> 
						<td class="cls1">' . $latitud . '</td>
						<td class="cls1">' . $longitud . '</td>
						<td style="height:20px">' . utf8_decode($categoria) . '</td> 
						<td style="height:20px">' . utf8_decode($subcategoria) . '</td> 
						<td style="height:20px">' . utf8_decode($presentacion) . '</td> 
						<td style="height:20px">' . utf8_decode($sku_code) . '</td> 
						<td style="height:20px">' . utf8_decode($brand) . '</td> 
						<td style="height:20px">' . utf8_decode($manufacturer) . '</td> 
						<td style="height:20px">' . utf8_decode($regular_price) . '</td> 
						<td style="height:20px">' . utf8_decode($promotional_price) . '</td> 
						<td style="height:20px">' . utf8_decode($ofert_price) . '</td> 
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
                    </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReportePrecios.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function pre_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare("
SELECT 
ins.fecha,
ins.hora,
rpdv.pos_id,
rpdv.channel,
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
ins.sector,
ins.categoria,
ins.subcategoria,
ins.sku,
rp.fabricante,
ins.brand,
ins.tipo_promocion,
ins.descripcion_promocion,
ins.inicio_promocion,
ins.fin_promocion,
ins.agotar_stock,
ins.pvc_anterior,
ins.pvc_actual,
ins.descuento,
ins.mecanica,
ins.foto,
ins.fechaservidor 
FROM insert_promociones ins 
LEFT JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id
LEFT JOIN repositorio_productos rp
ON ins.sku=rp.sku
WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ? 
GROUP BY ins.codigo, ins.usuario, ins.fecha, ins.hora;
")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$fecha,
				$hora,
				$pos_id,
				$channel,
				$customer_owner,
				$pos_name,
				$region,
				$province,
				$city,
				$zone,
				$kam,
				$zona_territorio,
				$address,
				$supervisor,
				$mercaderista,
				$user,
				$latitud,
				$longitud,
				$sector,
				$categoria,
				$subcategoria,
				$sku,
				$fabricante,
				$brand,
				$tipo_promocion,
				$descripcion_promocion,
				$inicio_promocion,
				$fin_promocion,
				$agotar_stock,
				$pvc_anterior,
				$pvc_actual,
				$descuento,
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
						<th>SUBCATEGORIA</th>
						<th>SKU</th>
						<th>FABRICANTE</th>
						<th>MARCA</th>
						<th>TIPO DE PROMOCION</th>
						<th>DESCRIPCION PROMOCION</th>
						<th>INICIO PROMOCION</th>
						<th>FIN PROMOCION</th>
						<th>HASTA AGOTAR STOCK</th>
						<th>PVC ANTERIOR</th>
						<th>PVC ACTUAL</th>
						<th>DESCUENTO</th>
						<th>MECANICA PROMOCIONAL</th>
						<th>FOTO URL</th>
						<th>FECHA SERVIDOR</th>
                    </tr>  
           ';
			$img_url = "https://webecuador.azurewebsites.net/App/AppDanec/Inserts/";
			while ($sql->fetch()) {
				$output .= '  
                    <tr>  
						<td style="height:20px">' . utf8_decode($fecha) . '</td> 
						<td style="height:20px">' . utf8_decode($hora) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_id) . '</td> 
						<td style="height:20px">' . utf8_decode($channel) . '</td> 
						<td style="height:20px">' . utf8_decode($customer_owner) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_name) . '</td> 
						<td style="height:20px">' . utf8_decode($region) . '</td> 
						<td style="height:20px">' . utf8_decode($kam) . '</td>
						<td style="height:20px">' . utf8_decode($province) . '</td> 
						<td style="height:20px">' . utf8_decode($city) . '</td> 
						<td style="height:20px">' . utf8_decode($zone) . '</td> 
						<td style="height:20px">' . utf8_decode($zona_territorio) . '</td> 
						<td style="height:20px">' . utf8_decode($address) . '</td> 
						<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
						<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
						<td style="height:20px">' . utf8_decode($user) . '</td> 
						<td class="cls1">' . $latitud . '</td>
						<td class="cls1">' . $longitud . '</td>
						<td style="height:20px">' . utf8_decode($sector) . '</td> 
						<td style="height:20px">' . utf8_decode($categoria) . '</td> 
						<td style="height:20px">' . utf8_decode($subcategoria) . '</td> 
						<td style="height:20px">' . utf8_decode($sku) . '</td> 
						<td style="height:20px">' . utf8_decode($fabricante) . '</td> 
						<td style="height:20px">' . utf8_decode($brand) . '</td> 
						<td style="height:20px">' . utf8_decode($tipo_promocion) . '</td> 
						<td style="height:20px">' . utf8_decode($descripcion_promocion) . '</td> 
						<td style="height:20px">' . utf8_decode($inicio_promocion) . '</td> 
						<td style="height:20px">' . utf8_decode($fin_promocion) . '</td> 
						<td style="height:20px">' . utf8_decode($agotar_stock) . '</td> 
						<td style="height:20px">' . utf8_decode($pvc_anterior) . '</td> 
						<td style="height:20px">' . utf8_decode($pvc_actual) . '</td> 
						<td style="height:20px">' . utf8_decode($descuento) . '</td> 
						<td style="height:20px">' . utf8_decode($mecanica) . '</td> 
						<td style="height:20px">' . $img_url . $foto . '</td>
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
						
                    </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReportePromociones.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function venta_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare("
SELECT 
rpdv.pos_id,
rpdv.channel,
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
ins.sku_code,
ins.pvc,
ins.asignada,
ins.vendida,
ins.adicional,
ins.cumplimiento,
ins.impulsadora,
ins.observacion,
ins.foto,
ins.fecha,
ins.hora,
ins.fechaservidor 
FROM insert_impulso ins
INNER JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ? 
GROUP BY ins.codigo, ins.usuario, ins.fecha, ins.hora;
")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$pos_id,
				$channel,
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
				$sku_code,
				$pvc,
				$asignada,
				$vendida,
				$adicional,
				$cumplimiento,
				$impulsadora,
				$observacion,
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
						<th>BRAND</th>
						<th>SKU</th>
						<th>PVC</th>
						<th>CANTIDAD ASIGNADA</th>
						<th>CANTIDAD VENDIDA</th>
						<th>CANTIDAD ADICIONAL</th>
						<th>CUMPLIMIENTO</th>
						<th>NOMBRE IMPULSADORA</th>
						<th>OBSERVACION</th>
						<th>URL FOTO</th>
						<th>FECHA</th>
						<th>HORA</th>
						<th>FECHA SERVIDOR</th>
					</tr>  
           ';
			$img_url = "https://webecuador.azurewebsites.net/App/AppDanec/Inserts/";
			while ($sql->fetch()) {
				$output .= '  
                    <tr>  
						<td style="height:20px">' . utf8_decode($pos_id) . '</td> 
						<td style="height:20px">' . utf8_decode($channel) . '</td> 
						<td style="height:20px">' . utf8_decode($customer_owner) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_name) . '</td> 
						<td style="height:20px">' . utf8_decode($region) . '</td> 
						<td style="height:20px">' . utf8_decode($kam) . '</td>
						<td style="height:20px">' . utf8_decode($province) . '</td> 
						<td style="height:20px">' . utf8_decode($city) . '</td> 
						<td style="height:20px">' . utf8_decode($zone) . '</td> 
						<td style="height:20px">' . utf8_decode($zona_territorio) . '</td> 
						<td style="height:20px">' . utf8_decode($address) . '</td> 
						<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
						<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
						<td style="height:20px">' . utf8_decode($user) . '</td> 
						<td class="cls1">' . $latitud . '</td>
						<td class="cls1">' . $longitud . '</td>
						<td style="height:20px">' . utf8_decode($categoria) . '</td> 
						<td style="height:20px">' . utf8_decode($brand) . '</td> 
						<td style="height:20px">' . utf8_decode($sku_code) . '</td> 
						<td style="height:20px">' . utf8_decode($pvc) . '</td> 
						<td style="height:20px">' . utf8_decode($asignada) . '</td> 
						<td style="height:20px">' . utf8_decode($vendida) . '</td> 
						<td style="height:20px">' . utf8_decode($adicional) . '</td> 
						<td style="height:20px">' . utf8_decode($cumplimiento) . '</td> 
						<td style="height:20px">' . utf8_decode($impulsadora) . '</td> 
						<td style="height:20px">' . utf8_decode($observacion) . '</td>
						<td style="height:20px">' . $img_url . $foto . '</td> 
						<td style="height:20px">' . utf8_decode($fecha) . '</td> 
						<td style="height:20px">' . utf8_decode($hora) . '</td> 
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
                    </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteImpulso.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function reg_reporte($fechaInicio, $fechaFin, $mysqli)
{
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
WHERE STR_TO_DATE(fecha, '%d/%m/%Y') BETWEEN ? AND ? 
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
fecha")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
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
			while ($sql->fetch()) {
				$output .= '  
                    <tr>  
						<td style="height:20px">' . utf8_decode($codigo) . '</td> 
						<td style="height:20px">' . utf8_decode($canal) . '</td> 
						<td style="height:20px">' . utf8_decode($region) . '</td> 
						<td style="height:20px">' . utf8_decode($provincia) . '</td> 
						<td style="height:20px">' . utf8_decode($ciudad) . '</td> 
						<td style="height:20px">' . utf8_decode($zona) . '</td> 
						<td style="height:20px">' . utf8_decode($nombrecomercial) . '</td> 
						<td style="height:20px">' . utf8_decode($local) . '</td> 
						<td style="height:20px">' . utf8_decode($direccion) . '</td> 
						<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
						<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
						<td style="height:20px">' . utf8_decode($tipo) . '</td> 
						<td class="cls1">' . $latitude . '</td> 
						<td class="cls1">' . $longitude . '</td> 
						<td style="height:20px">' . $img_url . $foto . '</td> 
						<td style="height:20px">' . utf8_decode($fecha) . '</td> 
						<td style="height:20px">' . utf8_decode($hora) . '</td> 
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
                    </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteGeoReferencia.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function share_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare("
SELECT 
ins.id_pdv,
ins.channel,
ins.customer_owner,
ins.pos_name,
ins.region,
ins.provincia,
ins.ciudad,
ins.zona,
ins.direccion,
ins.supervisor,
ins.mercaderista,
ins.usuario,
ins.lat_pdv,
ins.lng_pdv,
ins.kam AS territorio, 
ins.tipo AS zona_territorio,
ins.tipo,
ins.causal,
ins.version,
ins.latitude,
ins.longitude,
ins.fecha,
ins.hora,
ins.fechaservidor
FROM insert_registro ins 
WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ? 
GROUP BY ins.fecha, ins.hora, ins.usuario, ins.tipo;
")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$id_pdv,
				$channel,
				$customer_owner,
				$pos_name,
				$region,
				$provincia,
				$ciudad,
				$zona,
				$direccion,
				$supervisor,
				$mercaderista,
				$user,
				$lat_pdv,
				$lng_pdv,
				$territorio,
				$zona_territorio,
				$tipo,
				$causal,
				$version,
				$latitude,
				$longitude,
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
						<th>LATITUD PDV</th>
						<th>LONGITUD PDV</th>
						<th>MAPA PDV</th>
						<th>TIPO</th>
						<th>CAUSAL</th>
						<th>FECHA VERSION APP</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						<th>MAPA MARCACION</th>
						<th>FECHA</th>
						<th>HORA</th>
						<th>FECHA SERVIDOR</th>
                     </tr>  
           ';
			// $img_url = "https://webecuador.azurewebsites.net/App/CtaEpson/AppEpson/Inserts/";
			while ($sql->fetch()) {
				$link_mapa_pdv = "https://maps.google.com/?q=" . $lat_pdv . "," . $lng_pdv;
				$link_mapa_marcacion = "https://maps.google.com/?q=" . $latitude . "," . $longitude;
				$output .= '  
                     <tr>  		
						<td style="height:20px">' . utf8_decode($id_pdv) . '</td> 
						<td style="height:20px">' . utf8_decode($channel) . '</td> 
						<td style="height:20px">' . utf8_decode($customer_owner) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_name) . '</td> 
						<td style="height:20px">' . utf8_decode($region) . '</td> 
						<td style="height:20px">' . utf8_decode($territorio) . '</td>
						<td style="height:20px">' . utf8_decode($provincia) . '</td> 
						<td style="height:20px">' . utf8_decode($ciudad) . '</td> 
						<td style="height:20px">' . utf8_decode($zona) . '</td> 
						<td style="height:20px">' . utf8_decode($zona_territorio) . '</td> 
						<td style="height:20px">' . utf8_decode($direccion) . '</td> 
						<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
						<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
						<td style="height:20px">' . utf8_decode($user) . '</td> 
						<td class="cls1">' . $lat_pdv . '</td>
						<td class="cls1">' . $lng_pdv . '</td>
						<td style="height:20px">' . utf8_decode($link_mapa_pdv) . '</td>

						<td style="height:20px">' . utf8_decode($tipo) . '</td> 
						<td style="height:20px">' . utf8_decode($causal) . '</td> 
						<td style="height:20px">' . utf8_decode($version) . '</td> 
						<td class="cls1">' . $latitude . '</td>
						<td class="cls1">' . $longitude . '</td>
						<td style="height:20px">' . utf8_decode($link_mapa_marcacion) . '</td> 
						<td style="height:20px">' . utf8_decode($fecha) . '</td> 
						<td style="height:20px">' . utf8_decode($hora) . '</td> 
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
                     </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteRegistro.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function share_reporte2($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare("
SELECT 
rpdv.pos_id,
rpdv.channel,
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
ins.tipo,
ins.causal,
ins.version,
ins.latitude,
ins.longitude,
ins.fecha,
ins.hora,
ins.fechaservidor
FROM insert_registro ins 
INNER JOIN repositorio_locales_dtt rpdv 
ON ins.id_pdv=rpdv.pos_id 
WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ? 
GROUP BY ins.fecha, ins.hora, ins.usuario, ins.tipo;
")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$id_pdv,
				$channel,
				$customer_owner,
				$pos_name,
				$region,
				$provincia,
				$ciudad,
				$zona,
				$direccion,
				$supervisor,
				$mercaderista,
				$user,
				$lat_pdv,
				$lng_pdv,
				$territorio,
				$zona_territorio,
				$tipo,
				$causal,
				$version,
				$latitude,
				$longitude,
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
						<th>LATITUD PDV</th>
						<th>LONGITUD PDV</th>
						<th>MAPA PDV</th>
						<th>TIPO</th>
						<th>CAUSAL</th>
						<th>FECHA VERSION APP</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						<th>MAPA MARCACION</th>
						<th>FECHA</th>
						<th>HORA</th>
						<th>FECHA SERVIDOR</th>
                     </tr>  
           ';
			// $img_url = "https://webecuador.azurewebsites.net/App/CtaEpson/AppEpson/Inserts/";
			while ($sql->fetch()) {
				$link_mapa_pdv = "https://maps.google.com/?q=" . $lat_pdv . "," . $lng_pdv;
				$link_mapa_marcacion = "https://maps.google.com/?q=" . $latitude . "," . $longitude;
				$output .= '  
                     <tr>  		
						<td style="height:20px">' . utf8_decode($id_pdv) . '</td> 
						<td style="height:20px">' . utf8_decode($channel) . '</td> 
						<td style="height:20px">' . utf8_decode($customer_owner) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_name) . '</td> 
						<td style="height:20px">' . utf8_decode($region) . '</td> 
						<td style="height:20px">' . utf8_decode($territorio) . '</td>
						<td style="height:20px">' . utf8_decode($provincia) . '</td> 
						<td style="height:20px">' . utf8_decode($ciudad) . '</td> 
						<td style="height:20px">' . utf8_decode($zona) . '</td> 
						<td style="height:20px">' . utf8_decode($zona_territorio) . '</td> 
						<td style="height:20px">' . utf8_decode($direccion) . '</td> 
						<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
						<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
						<td style="height:20px">' . utf8_decode($user) . '</td> 
						<td class="cls1">' . $lat_pdv . '</td>
						<td class="cls1">' . $lng_pdv . '</td>
						<td style="height:20px">' . utf8_decode($link_mapa_pdv) . '</td>

						<td style="height:20px">' . utf8_decode($tipo) . '</td> 
						<td style="height:20px">' . utf8_decode($causal) . '</td> 
						<td style="height:20px">' . utf8_decode($version) . '</td> 
						<td class="cls1">' . $latitude . '</td>
						<td class="cls1">' . $longitude . '</td>
						<td style="height:20px">' . utf8_decode($link_mapa_marcacion) . '</td> 
						<td style="height:20px">' . utf8_decode($fecha) . '</td> 
						<td style="height:20px">' . utf8_decode($hora) . '</td> 
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
                     </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteRegistro.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function vent_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare("
SELECT 
rpdv.pos_id,
rpdv.channel,
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
ins.subcategoria,
ins.segmento,
ins.brand,
ins.manufacturer,
ins.ctms_percha,
ins.ctms_marca,
ins.porcentaje,
ins.otros,
ins.porcentajeotros,
ins.fechaservidor 
FROM insert_share_shelf ins 
INNER JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id
WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ? 
")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$pos_id,
				$channel,
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
				$sector,
				$categoria,
				$subcategoria,
				$segmento,
				$brand,
				$manufacturer,
				$ctms_percha,
				$ctms_marca,
				$porcentaje,
				$otros,
				$porcentajeotros,
				$fechaservidor
			) or die($sql->error);

			$output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                    <tr>  
						<th>CODIGO</th>
						<th>CANAL</th>
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
						<th>SUBCATEGORIA</th>
						<th>SEGMENTO</th>
						<th>MARCA</th>
						<th>FABRICANTE</th>
						<th>TOTAL CARAS PERCHA</th>
						<th>TOTAL CARAS MARCA</th>
						<th>PORCENTAJE</th>
						<th>OTROS</th>
						<th>PORCENTAJE OTROS</th>
						<th>FECHA SERVIDOR</th>
                    </tr>  
           ';
			// $img_url = "https://webecuador.azurewebsites.net/App/CtaEpson/AppEpson/Inserts/";
			while ($sql->fetch()) {

				/*if($marca_seleccionada=='Otros'){
				$ctms_marca = $otros;
			}*/

				$output .= '  
                     <tr>  
						<td style="height:20px">' . utf8_decode($pos_id) . '</td> 
						<td style="height:20px">' . utf8_decode($channel) . '</td> 
						<td style="height:20px">' . utf8_decode($customer_owner) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_name) . '</td> 
						<td style="height:20px">' . utf8_decode($region) . '</td> 
						<td style="height:20px">' . utf8_decode($kam) . '</td>
						<td style="height:20px">' . utf8_decode($province) . '</td> 
						<td style="height:20px">' . utf8_decode($city) . '</td> 
						<td style="height:20px">' . utf8_decode($zone) . '</td> 
						<td style="height:20px">' . utf8_decode($zona_territorio) . '</td> 
						<td style="height:20px">' . utf8_decode($address) . '</td> 
						<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
						<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
						<td style="height:20px">' . utf8_decode($user) . '</td> 
						<td class="cls1">' . $latitud . '</td>
						<td class="cls1">' . $longitud . '</td>
						<td style="height:20px">' . utf8_decode($sector) . '</td> 
						<td style="height:20px">' . utf8_decode($categoria) . '</td> 
						<td style="height:20px">' . utf8_decode($subcategoria) . '</td> 
						<td style="height:20px">' . utf8_decode($segmento) . '</td> 
						<td style="height:20px">' . utf8_decode($brand) . '</td> 
						<td style="height:20px">' . utf8_decode($manufacturer) . '</td> 
						<td style="height:20px">' . utf8_decode($ctms_percha) . '</td> 
						<td style="height:20px">' . utf8_decode($ctms_marca) . '</td> 
						<td style="height:20px">' . utf8_decode($porcentaje) . '</td> 
						<td style="height:20px">' . utf8_decode($otros) . '</td> 
						<td style="height:20px">' . utf8_decode($porcentajeotros) . '</td> 
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
                     </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteShare.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function promo_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare(
		"
SELECT 
ico.codigo,
ico.usuario,
ico.fechaservidor, 
bpa.city, 
bpa.channel_segment, 
bpa.customer_owner, 
bpa.format, 
bpa.pos_name, 
bpa.address, 
bpa.latitud, 
bpa.longitud, 
ico.tiempo_inicio,
ico.tiempo_fin,
ico.fecha,
ico.hora,
ico.fechaservidor
FROM 
insert_tiempo_gestion ico, 
repositorio_locales_dtt bpa 
WHERE STR_TO_DATE(ico.fecha, '%d/%m/%Y') BETWEEN ? AND ? 
AND
ico.codigo=bpa.pos_id
/*AND 
ico.usuario=bpa.USER*/ 
GROUP BY 
ico.codigo,
ico.usuario,
ico.tiempo_inicio,
ico.tiempo_fin,
ico.fecha
"

	)) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
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
				$tiemp_inicio,
				$tiempo_fin,
				$fecha,
				$hora,
				$fechaservidor

			) or die($sql->error);

			$output .= '  
            <style>.cls1 {mso-number-format:"\@"}</style>
          
                <table class="table" bordered="1">  
                    <tr>  
						<th>CODIGO</th>
						<th>USUARIO</th>
						<th>FECHASERVIDOR</th>
						<th>CIUDAD</th>
						<th>CHANNEL SEGMENT</th>
						<th>CLIENTE</th>
						<th>FORMATO</th>
						<th>PDV</th>
						<th>DIRECCION</th>
						<th>LATITUD</th>
						<th>LONGITUD</th>
						<th>TIEMPO INICIO</th>
						<th>TIEMPO DE GESTION</th>
						<th>TIEMPO FIN</th>
						<th>FECHA</th>
						<th>FECHA SERVIDOR</th>
                    </tr>  
           ';
			$img_url = "https://webecuador.azurewebsites.net/App/CtaEpson/AppEpson/Inserts/";
			while ($sql->fetch()) {
				$output .= '  
                     <tr>  
							<td style="height:20px">' . utf8_decode($codigo) . '</td> 
							<td style="height:20px">' . utf8_decode($usuario) . '</td> 
							<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
							<td style="height:20px">' . utf8_decode($ciudad) . '</td> 
							<td style="height:20px">' . utf8_decode($channel_segment) . '</td> 
							<td style="height:20px">' . utf8_decode($nombre_dueno) . '</td> 
							<td style="height:20px">' . utf8_decode($formato) . '</td> 
							<td style="height:20px">' . utf8_decode($pos) . '</td> 
							<td style="height:20px">' . utf8_decode($direccion) . '</td> 
							<td class="cls1">' . $latitud . '</td>
						    <td class="cls1">' . $longitud . '</td>
							<td style="height:20px">' . utf8_decode($tiemp_inicio) . '</td> 
							<td style="height:20px">' . utf8_decode($tiempo_fin) . '</td> 
							<td style="height:20px">' . utf8_decode($hora) . '</td> 
							<td style="height:20px">' . utf8_decode($fecha) . '</td> 
							<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
                     </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteTiempoGestion.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			header("Location:../index.php");
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}

function logro_reporte($fechaInicio, $fechaFin, $mysqli)
{
	if ($sql = $mysqli->prepare("
SELECT 
ins.fecha,
ins.hora,
rpdv.pos_id,
rpdv.channel,
rpdv.customer_owner,
rpdv.pos_name,
rpdv.region,
rpdv.province,
rpdv.city,
rpdv.zone,
rpdv.kam AS territorio, 
rpdv.tipo AS zona_territorio,
rpdv.address,
rpdv.supervisor,
rpdv.mercaderista,
ins.usuario,
rpdv.latitud,
rpdv.longitud,
ins.tipo,
ins.comentario,
ins.foto, 
ins.fechaservidor 
FROM insert_logros ins 
LEFT JOIN repositorio_locales_dtt rpdv 
ON ins.codigo=rpdv.pos_id 
WHERE STR_TO_DATE(ins.fecha, '%d/%m/%Y') BETWEEN ? AND ? 
GROUP BY ins.foto;
")) {

		$sql->bind_param('ss', $fechaInicio, $fechaFin);  // Une “$fecha” al parámetro.
		$sql->execute();    // Ejecuta la consulta preparada.
		$sql->store_result();
		if ($sql->num_rows > 0) {
			$sql->bind_result(
				$fecha,
				$hora,
				$pos_id,
				$channel,
				$customer_owner,
				$pos_name,
				$region,
				$province,
				$city,
				$zone,
				$kam,
				$zona_territorio,
				$address,
				$supervisor,
				$mercaderista,
				$user,
				$latitud,
				$longitud,
				$tipo,
				$comentario,
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
						<th>TIPO</th>
						<th>OBSERVACION</th>
						<th>FOTO URL</th>
						<th>FECHA SERVIDOR</th>
                    </tr>  
           ';
			$img_url = "https://webecuador.azurewebsites.net/App/AppDanec/Inserts/";
			while ($sql->fetch()) {
				$output .= '  
                    <tr>  
						<td style="height:20px">' . utf8_decode($fecha) . '</td> 
						<td style="height:20px">' . utf8_decode($hora) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_id) . '</td> 
						<td style="height:20px">' . utf8_decode($channel) . '</td> 
						<td style="height:20px">' . utf8_decode($customer_owner) . '</td> 
						<td style="height:20px">' . utf8_decode($pos_name) . '</td> 
						<td style="height:20px">' . utf8_decode($region) . '</td> 
						<td style="height:20px">' . utf8_decode($kam) . '</td>
						<td style="height:20px">' . utf8_decode($province) . '</td> 
						<td style="height:20px">' . utf8_decode($city) . '</td> 
						<td style="height:20px">' . utf8_decode($zone) . '</td> 
						<td style="height:20px">' . utf8_decode($zona_territorio) . '</td> 
						<td style="height:20px">' . utf8_decode($address) . '</td> 
						<td style="height:20px">' . utf8_decode($supervisor) . '</td> 
						<td style="height:20px">' . utf8_decode($mercaderista) . '</td> 
						<td style="height:20px">' . utf8_decode($user) . '</td> 
						<td class="cls1">' . $latitud . '</td>
						<td class="cls1">' . $longitud . '</td>
						<td style="height:20px">' . utf8_decode($tipo) . '</td> 
						<td style="height:20px">' . utf8_decode($comentario) . '</td> 
						<td style="height:20px">' . $img_url . $foto . '</td>
						<td style="height:20px">' . utf8_decode($fechaservidor) . '</td> 
						
                    </tr>  
                ';
			}
			$output .= '</table>';
			header("Content-Type: application/xls");
			header("Content-Disposition: attachment; filename=ReporteLogros.xls");
			echo $output;
		} else {
			echo "<script>
	alert('No se encontraron registros.');
	window.location.href='../index.php';
	</script>";
			die();
		}
	} else {
		echo "<script>
	alert('Ha ocurrido un error.');
	window.location.href='../index.php';
	</script>";
		die();
	}
}
