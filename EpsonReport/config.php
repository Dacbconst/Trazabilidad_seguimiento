<?php
// Hora de Ecuador para todo date()/strtotime(); el servidor corre en UTC y la base ya está en hora local.
date_default_timezone_set('America/Guayaquil');
// Que la regla de inactividad (includes/functions.php) sea la única que cierre la sesión: si el php.ini del servidor
// trae un session.gc_maxlifetime más corto, PHP podía borrar el archivo de sesión antes, sin pasar por nuestra lógica.
ini_set('session.gc_maxlifetime', 3600);
// Cookie propia: Acuerdos_Comerciales vive en el mismo dominio y compartían PHPSESSID, pisándose sesion_token y rol.
session_name('EPSONSESS');
// DB configuration variables
	define('HOST','mysqlecuadorsf.mysql.database.azure.com');
	define('USER','xplora_mysql');
    define('PASS','XpL0r@Ec8Ad0R..');
	define('DB','luckyec_epson_nuevo');

	define("SECURE", FALSE);    // ¡¡¡SOLO PARA DESARROLLAR!!!!
	// Cookie de sesión persistente (30 días) en vez de "hasta cerrar el navegador": en celular el navegador se mata en
	// segundo plano todo el tiempo y esa cookie de sesión moría con él, botando al usuario aunque no hubiera estado inactivo.
	// Quien de verdad cierra la sesión es la regla de inactividad (ult_interaccion, includes/functions.php), no esta cookie.
	define('EP_COOKIE_VIDA', 60 * 60 * 24 * 30);
?>
