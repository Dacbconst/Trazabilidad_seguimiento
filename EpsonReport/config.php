<?php
// Hora de Ecuador para todo date()/strtotime(); el servidor corre en UTC y la base ya está en hora local.
date_default_timezone_set('America/Guayaquil');
// DB configuration variables
	define('HOST','mysqlecuadorsf.mysql.database.azure.com');
	define('USER','xplora_mysql');
    define('PASS','XpL0r@Ec8Ad0R..');
	define('DB','luckyec_epson_nuevo');

	define("SECURE", FALSE);    // ¡¡¡SOLO PARA DESARROLLAR!!!!
?>
