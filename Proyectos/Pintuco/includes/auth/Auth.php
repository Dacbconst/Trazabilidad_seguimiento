<?php
// RQFOTOGRAFICODACB - refactor: responsabilidad única — autenticación y verificación de sesión
require_once __DIR__ . '/Session.php';

function login($email, $password, $mysqli) {
    if ($stmt = $mysqli->prepare("SELECT id, name, pass FROM login_admin WHERE name = ? LIMIT 1")) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($user_id, $username, $db_password);
        $stmt->fetch();

        if ($stmt->num_rows == 1) {
            if ($db_password == $password) {
                $user_browser = $_SERVER['HTTP_USER_AGENT'];
                $_SESSION['user_id']      = preg_replace("/[^0-9]+/", "", $user_id);
                $_SESSION['username']     = preg_replace("/[^a-zA-Z0-9_\-]+/", "", $username);
                $_SESSION['login_string'] = hash('sha512', $password . $user_browser);
                return true;
            } else {
                $now = time();
                $mysqli->query("INSERT INTO login_attempt(user_id, time) VALUES ('$user_id', '$now')");
                return false;
            }
        }
        return false;
    }
}

function login_check($mysqli) {
    if (!isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['login_string'])) {
        return false;
    }

    $user_id      = $_SESSION['user_id'];
    $login_string = $_SESSION['login_string'];
    $user_browser = $_SERVER['HTTP_USER_AGENT'];

    if ($stmt = $mysqli->prepare("SELECT pass FROM login_admin WHERE id = ? LIMIT 1")) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($password);
            $stmt->fetch();
            return hash('sha512', $password . $user_browser) === $login_string;
        }
    }
    return false;
}
