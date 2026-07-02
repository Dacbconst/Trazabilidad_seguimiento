<?php
// update_agenda.php — guarda la gestión del analista sobre una visita agendada
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Content-Type: application/json');

include_once '../db_connect.php';

$id     = isset($_POST['id'])     ? (int)$_POST['id'] : 0;
$accion = isset($_POST['accion']) ? $_POST['accion']  : 'guardar';

if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "Falta el id de la visita."]);
    exit;
}

// Cancelar y eliminar son las dos únicas acciones donde el analista elige el
// estado directamente; en "guardar" el estado lo decide el backend (ver más
// abajo). Cancelar es un estado de negocio real (el cliente canceló, la
// visita sigue visible en el historial); eliminar es borrado lógico vía
// "activar" para errores/duplicados que no deben aparecer en ningún lado.
if ($accion === 'cancelar') {
    $query = "UPDATE insert_proyectos_contacto SET estado_agenda = 'cancelada' WHERE id = ?";
    if ($sql = $mysqli->prepare($query)) {
        $sql->bind_param("i", $id);
        $ok = $sql->execute();
        $sql->close();
        echo json_encode(["success" => $ok, "message" => $ok ? "Visita cancelada." : $mysqli->error]);
    } else {
        echo json_encode(["success" => false, "message" => $mysqli->error]);
    }
    exit;
}

if ($accion === 'eliminar') {
    // activar es varchar(2) 'SI'/'NO' (confirmado contra la tabla real).
    $query = "UPDATE insert_proyectos_contacto SET activar = 'NO' WHERE id = ?";
    if ($sql = $mysqli->prepare($query)) {
        $sql->bind_param("i", $id);
        $ok = $sql->execute();
        $sql->close();
        echo json_encode(["success" => $ok, "message" => $ok ? "Visita eliminada." : $mysqli->error]);
    } else {
        echo json_encode(["success" => false, "message" => $mysqli->error]);
    }
    exit;
}

$fecha   = isset($_POST['fecha'])   ? $_POST['fecha']   : '';
$hora    = isset($_POST['hora'])    ? $_POST['hora']    : '';
$tecnico = isset($_POST['tecnico']) ? $_POST['tecnico'] : '';

// Campos del switch de edición (card de detalle) — SOLO llegan cuando el
// analista activó el switch (agenda.js los agrega al body en ese caso); si
// no vienen, esta gestión es la de siempre (reagendar fecha/hora/técnico) y
// no se tocan estas columnas. Promotor y Local deliberadamente no se leen
// acá bajo ningún escenario: ese endpoint nunca los toca.
$editaCompleto = isset($_POST['empresa']);
if ($editaCompleto) {
    $empresa               = trim($_POST['empresa']);
    $mail                  = isset($_POST['mail'])       ? trim($_POST['mail'])       : '';
    $direccion             = isset($_POST['direccion']) ? trim($_POST['direccion'])   : '';
    $latitud               = isset($_POST['latitud']) && $_POST['latitud'] !== ''   ? $_POST['latitud']   : null;
    $longitud              = isset($_POST['longitud']) && $_POST['longitud'] !== '' ? $_POST['longitud']  : null;
    $telefono              = isset($_POST['telefono'])   ? trim($_POST['telefono'])   : '';
    $telefono_convencional = isset($_POST['telefono_convencional']) && $_POST['telefono_convencional'] !== '' ? trim($_POST['telefono_convencional']) : null;
}

// Sin esta validación se podía guardar (y quedar marcado "confirmado") una
// visita sin técnico y a cualquier hora — ya pasó en datos reales: hora
// 00:00 con técnico vacío. El front ya restringe el input a este rango,
// pero la validación real tiene que estar acá: nunca confiar solo en lo que
// el navegador deja escribir.
if ($hora === '' || $tecnico === '') {
    echo json_encode(["success" => false, "message" => "Falta asignar hora y técnico antes de guardar."]);
    exit;
}
$minutosHora = (int)substr($hora, 0, 2) * 60 + (int)substr($hora, 3, 2);
if ($minutosHora < 6 * 60 || $minutosHora > 23 * 60) {
    echo json_encode(["success" => false, "message" => "La hora debe estar entre 06:00 y 23:00, el rango visible de la agenda."]);
    exit;
}

// Mismas reglas que ya usa insert_contacto.php para estos mismos campos —
// el switch de edición reusa el formulario de creación, así que reusa
// también su validación. Solo corre si el switch mandó estos campos.
if ($editaCompleto) {
    if ($empresa === '' || !preg_match("/^[A-Za-z0-9ÁÉÍÓÚÑáéíóúñ.\\-&' ]+$/u", $empresa)) {
        echo json_encode(["success" => false, "message" => "Empresa inválida."]);
        exit;
    }
    if ($mail === '' || !preg_match('/^[^\s@.][^\s@]*[^\s@.]@[^\s@]+\.[^\s@]+$/', $mail) || strpos($mail, '..') !== false) {
        echo json_encode(["success" => false, "message" => "Correo inválido."]);
        exit;
    }
    if ($direccion === '') {
        echo json_encode(["success" => false, "message" => "La dirección es obligatoria."]);
        exit;
    }
    if (preg_match('/^[23456789CFGHJMPQRVWX]{4,8}\+[23456789CFGHJMPQRVWX]{2,3}$/i', $direccion)) {
        echo json_encode(["success" => false, "message" => "La dirección parece un Plus Code — se necesita una dirección legible."]);
        exit;
    }
    if ($telefono === '' || !preg_match('/^\d{10}$/', $telefono)) {
        echo json_encode(["success" => false, "message" => "Teléfono inválido: debe ser numérico y de exactamente 10 dígitos."]);
        exit;
    }
    if ($telefono_convencional && !preg_match('/^\d+$/', $telefono_convencional)) {
        echo json_encode(["success" => false, "message" => "El teléfono convencional solo admite dígitos."]);
        exit;
    }
}

// Estado automático (contrato compartido con la app móvil — Constantes.java /
// AdapterAgenda.java, que lee esta misma tabla por sync): si la visita no
// tenía hora asignada todavía, esta gestión asigna técnico/hora por primera
// vez ('confirmado'); si ya tenía una, guardar de nuevo (cambie o no la
// fecha/hora) significa que se está reagendando ('reagendada').
$horaPrevia = null;
if ($sql = $mysqli->prepare("SELECT hora FROM insert_proyectos_contacto WHERE id = ?")) {
    $sql->bind_param("i", $id);
    $sql->execute();
    $sql->bind_result($horaPrevia);
    $sql->fetch();
    $sql->close();
}
$estado_agenda = ($horaPrevia === null || $horaPrevia === '') ? 'confirmado' : 'reagendada';

// Un técnico no puede estar en dos visitas a la vez: se rechaza si la nueva
// hora cae dentro de los DURACION_APROX_MIN minutos (mismo valor que usa el
// calendario web para dibujar el bloque) de otra visita YA agendada del
// mismo técnico, el mismo día, que no esté cancelada/eliminada.
$DURACION_APROX_MIN = 45;
if ($fecha !== '' && $hora !== '' && $tecnico !== '') {
    $query = "SELECT hora, titulo, pdv, contacto, empresa, estado_agenda FROM insert_proyectos_contacto
              WHERE fecha_agendamiento = ? AND tecnico = ? AND activar = 'SI'
                AND estado_agenda != 'cancelada' AND id != ? AND hora IS NOT NULL AND hora != ''";
    if ($sql = $mysqli->prepare($query)) {
        $sql->bind_param("ssi", $fecha, $tecnico, $id);
        $sql->execute();
        $resultado = $sql->get_result();
        $minutosNuevaHora = (int)substr($hora, 0, 2) * 60 + (int)substr($hora, 3, 2);
        while ($fila = $resultado->fetch_assoc()) {
            $horaExistente = $fila['hora'];
            $minutosExistente = (int)substr($horaExistente, 0, 2) * 60 + (int)substr($horaExistente, 3, 2);
            if (abs($minutosNuevaHora - $minutosExistente) < $DURACION_APROX_MIN) {
                $sql->close();
                echo json_encode([
                    "success" => false,
                    "message" => "El técnico ya tiene una visita a esa hora.",
                    "conflicto" => [
                        "hora" => substr($horaExistente, 0, 5),
                        "titulo" => $fila['titulo'],
                        "pdv" => $fila['pdv'],
                        "contacto" => $fila['contacto'],
                        "empresa" => $fila['empresa'],
                        "estado_agenda" => $fila['estado_agenda'],
                    ],
                ]);
                exit;
            }
        }
        $sql->close();
    }
}

// El título no se modifica aquí (viene de la base de datos). Cuando el
// switch de edición mandó los campos extendidos, "lugar" deja de ser
// autoreferencial (lugar = direccion) y toma la $direccion recibida, porque
// esa sí puede venir cambiada en esta misma sentencia; si no vino nada de
// eso, "lugar" se sigue sincronizando con la dirección ya guardada, como
// siempre. La fecha solo se actualiza si llega un valor (nunca se deja la
// visita sin fecha; para eso está la acción "eliminar").
if ($fecha !== '') {
    if ($editaCompleto) {
        $query = "UPDATE insert_proyectos_contacto
                  SET fecha_agendamiento = ?, hora = ?, tecnico = ?, estado_agenda = ?,
                      empresa = ?, mail = ?, direccion = ?, latitud = ?, longitud = ?,
                      telefono = ?, telefono_convencional = ?, lugar = ?
                  WHERE id = ?";
        $sql = $mysqli->prepare($query);
        if ($sql) {
            $sql->bind_param(
                "sssssssddsssi",
                $fecha, $hora, $tecnico, $estado_agenda,
                $empresa, $mail, $direccion, $latitud, $longitud,
                $telefono, $telefono_convencional, $direccion, $id
            );
        }
    } else {
        $query = "UPDATE insert_proyectos_contacto
                  SET fecha_agendamiento = ?, hora = ?, tecnico = ?, estado_agenda = ?, lugar = direccion
                  WHERE id = ?";
        $sql = $mysqli->prepare($query);
        if ($sql) {
            $sql->bind_param("ssssi", $fecha, $hora, $tecnico, $estado_agenda, $id);
        }
    }
} else {
    if ($editaCompleto) {
        $query = "UPDATE insert_proyectos_contacto
                  SET hora = ?, tecnico = ?, estado_agenda = ?,
                      empresa = ?, mail = ?, direccion = ?, latitud = ?, longitud = ?,
                      telefono = ?, telefono_convencional = ?, lugar = ?
                  WHERE id = ?";
        $sql = $mysqli->prepare($query);
        if ($sql) {
            $sql->bind_param(
                "ssssssddsssi",
                $hora, $tecnico, $estado_agenda,
                $empresa, $mail, $direccion, $latitud, $longitud,
                $telefono, $telefono_convencional, $direccion, $id
            );
        }
    } else {
        $query = "UPDATE insert_proyectos_contacto
                  SET hora = ?, tecnico = ?, estado_agenda = ?, lugar = direccion
                  WHERE id = ?";
        $sql = $mysqli->prepare($query);
        if ($sql) {
            $sql->bind_param("sssi", $hora, $tecnico, $estado_agenda, $id);
        }
    }
}

if ($sql) {
    $ok = $sql->execute();
    $sql->close();
    echo json_encode(["success" => $ok, "message" => $ok ? "Actualizado." : $mysqli->error]);
} else {
    echo json_encode(["success" => false, "message" => $mysqli->error]);
}
?>
