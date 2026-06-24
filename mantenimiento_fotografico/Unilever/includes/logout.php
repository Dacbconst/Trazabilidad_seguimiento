<?php
// RQFOTOGRAFICODACB - refactor: incluye auth/Session.php directamente
require_once __DIR__ . '/auth/Session.php';
sec_session_start();
 
// Desconfigura todos los valores de sesion.
$_SESSION = array();
 
// Obtiene los parametros de sesion.
$params = session_get_cookie_params();
 
// Borra el cookie actual.
setcookie(session_name(),
        '', time() - 42000, 
        $params["path"], 
        $params["domain"], 
        $params["secure"], 
        $params["httponly"]);
 
// Destruye sesión. 
session_destroy();
header('Location: ../login.php');