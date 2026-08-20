<?php

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);


include_once 'db_connect.php';
include_once 'functions.php';

sec_session_start();


if (isset($_POST['usuario'])) {
    $usuario = $_POST['usuario'];
    $usuario = trim(strtoupper($usuario));



    if (login($usuario,$mysqli) == true) {
        // Inicio de sesión exitosa
       // header('Location: ../muestrapostlogin.php');

        echo json_encode([
            'status' => 'success',
            'usuario' => $usuario,
            'message' => 'Inicio de sesión exitoso'
        ]);

    } else {
        // Inicio de sesión exitosa
       // header('Location: ../login.php?err=1');
        echo json_encode([
             'status' => 'error',
             'message' => 'Usuario incorrecto'
         ]);
    }
} else {
    // Las variables POST correctas no se enviaron a esta página.
    echo 'Solicitud no valida';
}




// header('Content-Type: application/json');

// if (isset($_POST['usuario'])) {
//     $usuario = $_POST['usuario'];

//     if ($usuario === '') {
//         echo json_encode([
            
//             'status' => 'error',
//             'message' => 'Usuario no puede estar vacío'
//         ]);
//         exit;
//     }

//     if (login($usuario, '', $mysqli) == true) {
//         $_SESSION['usuario'] = $usuario;

//         echo json_encode([
//             'status' => 'success',
//             'usuario' => $usuario,
//             'message' => 'Inicio de sesión exitoso'
//         ]);

//         header('Location: ../index.php');

//     } else {
//         echo json_encode([
//             'status' => 'error',
//             'message' => 'Usuario incorrecto'
//         ]);
//     }


// } else {
//     echo json_encode([
//         'status' => 'error',
//         'message' => 'Datos incompletos'
//     ]);
    
// }
//SE MODIFICO PARA QUE DEVUELVA UN JSON Y NO REDIRECCIONE  AL PHP ANTIGUO