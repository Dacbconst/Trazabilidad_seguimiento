
<?php
$error = htmlspecialchars($_GET['err'] ?? '');

if (!$error) {
    $error = "Error desconocido";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Error de Login</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Error de inicio de sesión',
        text: 'Usuario o contraseña incorrectos.',
        confirmButtonText: 'Intentar de nuevo',
        confirmButtonColor: '#d33'
    }).then(() => {
		//redirige al login 
        window.location.href = 'login.php';
    });
</script>
</body>
</html>
