<?php
// RQFOTOGRAFICODACB - refactor: este archivo ya no contiene lógica directa.
// Las responsabilidades se separaron en:
//   auth/Session.php → sec_session_start()
//   auth/Auth.php    → login() + login_check()
require_once __DIR__ . '/auth/Auth.php';
