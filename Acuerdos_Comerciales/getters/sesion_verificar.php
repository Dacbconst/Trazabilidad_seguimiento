<?php
// Ping liviano para sesion-watch.js: login_check() ya destruye la sesión server-side si otro login pisó el token, acá solo se informa al frontend para que redirija.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => login_check()]);
?>
