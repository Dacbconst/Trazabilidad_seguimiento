<?php
	$ruta = '/App/AppAlicorp/Inserts/Share/*.png';
	//$ruta = '../3MBORRAR/FOTOSPOP/*.jpg';
	echo "Ruta a trabajar: $ruta"."<br>";
	$files = glob($ruta, GLOB_NOSORT); //obtenemos todos los nombres de los archivos
	$contar = 0;
	foreach($files as $file){
		//echo "archivo--> ".$file . "<br>";
    		if(is_file($file)) unlink($file); //elimino el archivo
			$contar++;
	}
	echo "Hubo ".$contar." archivos eliminados";
?>