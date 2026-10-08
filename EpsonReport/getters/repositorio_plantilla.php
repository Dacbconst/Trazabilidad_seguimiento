<?php
// Descarga el formato .xlsx en blanco de un repositorio de POP (GET tipo = material|campana). Solo Fabricio o el admin.
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

$esMaterial = ($_GET['tipo'] ?? '') === 'material';
$archivo = ep_xlsx_generar([$esMaterial ? 'Material' : 'Campaña'], []);
ep_xlsx_descargar($archivo, $esMaterial ? 'formato_materiales.xlsx' : 'formato_campanas.xlsx');
