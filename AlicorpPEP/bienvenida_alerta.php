<?php
// Puedes agregar validación si el usuario no debería estar aquí directamente
session_start();
// Verifica si hay sesión iniciada, por ejemplo
// if (!isset($_SESSION['usuario'])) {
//     header('Location: login.php');
//     exit();
// }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bienvenida</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<script>
const Toast = Swal.mixin({
  toast: true,
  position: "top",
  showConfirmButton: false,
  timer: 1500,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.onmouseenter = Swal.stopTimer;
    toast.onmouseleave = Swal.resumeTimer;
  }
});

Toast.fire({
  icon: "success",
  title: "Inicio de sesión exitoso"
});

setTimeout(() => {
  window.location.href = 'muestrapostlogin.php';
}, 1500); // Redirige después de 2 segundos
</script>

</body>
</html>
