<?php
// Descarga el formato .xlsx en blanco del repositorio de POP (columna A Material, B Campaña). Solo Fabricio o el admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/pop_reparto.php';
require_once __DIR__.'/../includes/xlsx_motor.php';

if (!ep_login_check() || !ep_pop_es_dueno()) {
	http_response_code(403);
	echo 'No tienes permiso para esto.';
	exit;
}

$archivo = ep_xlsx_generar(['Material', 'Campaña'], []);
ep_xlsx_descargar($archivo, 'formato_repositorio_pop.xlsx');
