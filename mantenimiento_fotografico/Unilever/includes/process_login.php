<?php
// RQFOTOGRAFICODACB - refactor: incluye auth/Auth.php directamente (contiene Session + login)
require_once __DIR__ . '/auth/Auth.php';
require_once __DIR__ . '/db_connect.php';
 
sec_session_start(); // Nuestra manera personalizada segura de iniciar sesión PHP.
 
if (isset($_POST['email'], $_POST['password'])) {
    $email = $_POST['email'];
    $password = $_POST['password']; // La contraseña con hash
 
    if (login($email, $password, $mysqli) == true) {
        // Inicio de sesión exitosa
        header('Location: ../protected_page.php');
    } else {
        // Inicio de sesión exitosa
        header('Location: ../error.php?err=1');
    }
} else {
    // Las variables POST correctas no se enviaron a esta página.
    echo 'Solicitud no valida';
}