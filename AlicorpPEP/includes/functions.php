<?php
include_once 'config.php';

function sec_session_start(){
	$session_name = 'sec_session_id';	//configura un nombre de sesion personalizado
	$secure = SECURE;
    // Esto detiene que JavaScript sea capaz de acceder a la identificación de la sesión.
	
	$httponly = true;
	// Obliga a las sesiones a solo utilizar cookies.
    if (ini_set('session.use_only_cookies', 1) === FALSE) {
        header("Location: ../clerror.php?err=Could not initiate a safe session (ini_set)");
        exit();
    }
    // Obtiene los params de los cookies actuales.
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params($cookieParams["lifetime"],
        $cookieParams["path"], 
        $cookieParams["domain"], 
        $secure,
        $httponly);
    // Configura el nombre de sesión al configurado arriba.
    session_name($session_name);
    session_start();            // Inicia la sesión PHP.
    session_regenerate_id();    // Regenera la sesión, borra la previa. 
}

function login($usuario, $mysqli) {
  //  if ($stmt = $mysqli->prepare("SELECT supervisor FROM repositorio_locales_supervisores_cliente WHERE supervisor = ? LIMIT 1")) {
      if ($stmt = $mysqli->prepare("(SELECT supervisor,sector AS rol FROM repositorio_locales_supervisores_cliente WHERE supervisor = ?)
    UNION
    (SELECT usuario,rol FROM repositorio_cliente_usuarios_universales WHERE usuario = ?)
    LIMIT 1;")) {
        $stmt->bind_param('ss', $usuario,$usuario);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($supervisor,$rol);
            $stmt->fetch();

            if ($usuario !== $supervisor) {
                $supervisor = $usuario;
            }

            
            // Crear cadena de verificación
            $user_browser = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['supervisor'] = $supervisor;
            $_SESSION['rol'] = $rol;
            $_SESSION['login_string'] = hash('sha512', $supervisor . $user_browser);
            
            return true;
        }
    }
    return false;
}


function login_check($mysqli) {
    if (isset($_SESSION['supervisor'], $_SESSION['login_string'])) {
        $supervisor = $_SESSION['supervisor'];
        $login_string = $_SESSION['login_string'];
        $user_browser = $_SERVER['HTTP_USER_AGENT'];

        if ($stmt = $mysqli->prepare("(SELECT supervisor,sector AS rol FROM repositorio_locales_supervisores_cliente WHERE supervisor = ?)
                             UNION
                             (SELECT usuario,rol FROM repositorio_cliente_usuarios_universales WHERE usuario = ?)
                             LIMIT 1")) {
            $stmt->bind_param('ss',$supervisor, $supervisor);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $expected_login = hash('sha512', $supervisor . $user_browser);
                return hash_equals($expected_login, $login_string);
            }
        }
    }
    return false;
}



// FUNCION PARA QUE EN EL NAVEGADOR NO LEA EL CODIGO DEL PDV COMO UN NUMERODE CELULAR:
function break_number($number) {
    // Inserta un carácter invisible cada 4 dígitos
    return implode('&#8203;', str_split($number, 4));
    echo(number);
}