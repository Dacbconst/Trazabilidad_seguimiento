<?php
// insert_contacto.php — registra una visita nueva desde el panel del
// analista (modal "Nueva visita" de Agendamientos). Mismos campos que pide
// la app móvil en su pestaña "Contacto", más fecha/hora/técnico que en este
// panel SÍ son obligatorios (en el celular se asignan después). Las mismas
// reglas ya se validan en agenda-crear.js, pero se repiten aquí porque
// cualquiera puede pegarle un POST directo a este getter sin pasar por el
// formulario — el JS es para la experiencia, esto es lo que de verdad
// protege la tabla.
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

include_once '../db_connect.php';

$codigo_pdv            = isset($_POST['codigo_pdv'])            ? $_POST['codigo_pdv']            : '';
$pdv                   = isset($_POST['pdv'])                   ? $_POST['pdv']                   : '';
$usuario               = isset($_POST['usuario'])               ? $_POST['usuario']               : '';
$contacto              = isset($_POST['contacto'])               ? $_POST['contacto']               : '';
$empresa               = isset($_POST['empresa'])               ? $_POST['empresa']               : '';
$mail                  = isset($_POST['mail'])                   ? $_POST['mail']                   : '';
$direccion             = isset($_POST['direccion'])             ? $_POST['direccion']             : '';
$latitud               = isset($_POST['latitud']) && $_POST['latitud'] !== ''   ? $_POST['latitud']   : null;
$longitud              = isset($_POST['longitud']) && $_POST['longitud'] !== '' ? $_POST['longitud']  : null;
$telefono              = isset($_POST['telefono'])               ? $_POST['telefono']               : '';
$telefono_convencional = isset($_POST['telefono_convencional']) && $_POST['telefono_convencional'] !== '' ? $_POST['telefono_convencional'] : null;
$fecha_agendamiento    = isset($_POST['fecha_agendamiento']) && $_POST['fecha_agendamiento'] !== ''       ? $_POST['fecha_agendamiento']    : null;
$hora                  = isset($_POST['hora']) && $_POST['hora'] !== ''         ? $_POST['hora']      : null;
$tecnico               = isset($_POST['tecnico']) && $_POST['tecnico'] !== ''   ? $_POST['tecnico']   : null;

function error($mensaje) {
    echo json_encode(["success" => false, "message" => $mensaje]);
    exit;
}

if ($usuario === '' && $codigo_pdv === '' && $contacto === '' && $empresa === '' && $mail === ''
    && $direccion === '' && $telefono === '' && !$fecha_agendamiento && !$hora && !$tecnico) {
    error("El formulario está vacío.");
}

if ($usuario === '')  error("Falta el promotor.");
if ($codigo_pdv === '' || $pdv === '') error("Falta seleccionar un PDV de la lista.");
if ($contacto === '' || !preg_match("/^[A-Za-zÁÉÍÓÚÑáéíóúñ' -]+$/u", $contacto) || preg_match_all('/[A-Za-zÁÉÍÓÚÑáéíóúñ]/u', $contacto) < 2) {
    error("Contacto inválido: solo letras, espacios, apóstrofes y guiones (mínimo 2 letras).");
}
if ($empresa === '' || !preg_match("/^[A-Za-z0-9ÁÉÍÓÚÑáéíóúñ.\\-&' ]+$/u", $empresa)) {
    error("Empresa inválida.");
}
if ($mail === '' || !preg_match('/^[^\s@.][^\s@]*[^\s@.]@[^\s@]+\.[^\s@]+$/', $mail) || strpos($mail, '..') !== false) {
    error("Correo inválido.");
}
if ($direccion === '' || mb_strlen($direccion) < 5 || !preg_match("/^[A-Za-z0-9ÁÉÍÓÚÑáéíóúñ.,#\\-\\/()'& ]+$/u", $direccion)) {
    error("Dirección inválida (mínimo 5 caracteres, sin símbolos raros).");
}
if (preg_match('/^[23456789CFGHJMPQRVWX]{4,8}\+[23456789CFGHJMPQRVWX]{2,3}$/i', $direccion)) {
    error("La dirección parece un Plus Code — se necesita una dirección legible.");
}
// El celular puede empezar con cualquier dígito — solo se exige que sean
// puros números y exactamente 10 (igual que valida agenda-crear.js).
if ($telefono === '' || !preg_match('/^\d{10}$/', $telefono)) {
    error("Teléfono inválido: debe ser numérico y de exactamente 10 dígitos.");
}
if ($telefono_convencional && !preg_match('/^\d+$/', $telefono_convencional)) {
    error("El teléfono convencional solo admite dígitos.");
}
if (!$fecha_agendamiento || $fecha_agendamiento < date('Y-m-d')) {
    error("La fecha de agendamiento es obligatoria y no puede ser pasada.");
}
if (!$hora)    error("La hora es obligatoria.");
if (!$tecnico) error("El técnico es obligatorio.");

// Con fecha+hora+técnico siempre presentes (las 3 son obligatorias en este
// panel), una visita creada aquí nace 'confirmado' — igual que cuando
// update_agenda.php asigna técnico/hora por primera vez a una pendiente.
$estado_agenda = 'confirmado';

$query = "INSERT INTO insert_proyectos_contacto
    (codigo_pdv, pdv, usuario, fecha, fecha_registro, contacto, empresa, mail, direccion,
     latitud, longitud, telefono, telefono_convencional, fecha_agendamiento, titulo, hora,
     lugar, tecnico, estado_agenda, activar)
    VALUES (?, ?, ?, CURDATE(), NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Visita Técnica', ?, ?, ?, ?, 'SI')";

if ($sql = $mysqli->prepare($query)) {
    $sql->bind_param(
        "sssssssddsssssss",
        $codigo_pdv, $pdv, $usuario, $contacto, $empresa, $mail, $direccion,
        $latitud, $longitud, $telefono, $telefono_convencional, $fecha_agendamiento,
        $hora, $direccion, $tecnico, $estado_agenda
    );
    $ok = $sql->execute();
    $nuevoId = $mysqli->insert_id;
    $sql->close();
    echo json_encode(["success" => $ok, "id" => $nuevoId, "message" => $ok ? "Visita registrada." : $mysqli->error]);
} else {
    echo json_encode(["success" => false, "message" => $mysqli->error]);
}
?>
