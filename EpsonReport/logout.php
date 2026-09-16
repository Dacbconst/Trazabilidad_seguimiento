<?php
require_once __DIR__.'/config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
session_destroy();
header('Location: login.php');
exit;
