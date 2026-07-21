<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/functions.php';
iniciar_sesion();

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
?>
