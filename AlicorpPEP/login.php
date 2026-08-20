<?php
include_once 'includes/db_connect.php';
include_once 'includes/functions.php';
 
sec_session_start();
 
if (login_check($mysqli) == true) {
    $logged = 'Connected';
} else {
    $logged = 'Disconnected';
}

?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <?php
	        header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	        header("Expires: Sat, 1 Jul 2000 05:00:00 GMT"); // Fecha en el pasado
	    ?>



    <meta http-equiv="cache-control" content="no-cache, must-revalidate, post-check=0, pre-check=0" />
	<meta http-equiv="cache-control" content="max-age=0" />
	<meta http-equiv="expires" content="0" />
	<meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT" />
	<meta http-equiv="pragma" content="no-cache" />
    <meta name = "format-detection"  content= "telephone =no"/>    
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/images/ali.jpg" type="image/jpg">
    <title>ALICORP LOGIN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 50px rgba(245, 11, 11, 0.1);
            width: 100%;
            max-width: 400px;
            transition: transform 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-5px);
        }

        .form-label {
            color:rgb(14, 14, 15);
            font-weight: 500;
        }

        .form-control {
            border: 1px solid #ffe6e6;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #ff9999;
            box-shadow: 0 0 0 3px rgba(255, 153, 153, 0.25);
        }

        .btn-primary {
            background: linear-gradient(165deg, rgba(240, 133, 192, 0.82),rgb(248, 36, 21), rgba(240, 133, 192, 0.82));
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
s
        .btn-primary:hover {
            background: linear-gradient(135deg, #ff4444, #ff2222);
            transform: translateY(-2px);
        }

        .form-check-input:checked {
            background-color: #ff6666;
            border-color: #ff6666;
        }

        .form-text {
            color: rgb(67, 5, 5);


        }

        .title {
            color:rgb(255, 0, 0);
            text-align: center;
            margin-bottom: 2rem;
            font-weight: 700;
            letter-spacing: -0.3px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;

        }

       
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="title" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;" >Bienvenido</h2>
        <div class="text-center mb-4">
    <img src="assets/images/alicorp.jpg" alt="Logo ALICORP" class="img-fluid rounded mx-auto d-block" width="150">
</div>

        <form id="login" name="login_form" method="post" action="includes/process_login.php">
        <div class="mb-4">
            <div id="emailHelp" class="form-text mt-4">Por favor ingrese sus credenciales:</div>
  
          <label for="exampleInputEmail1" class="form-label">Usuario</label>
          <input name= "usuario" type="text" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp" required>
        </div>

           <!--  <div class="mb-3">
                
                <label for="exampleInputPassword1" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="exampleInputPassword1">
            </div> -->
           
            <button type="submit" class="btn btn-primary">Ingresar</button>
    
    <footer class="text-center mt-4 py-3 text-muted">
    <div class="container">
    <small>
            <p class="mb-1">Español (Ecuador)</p>
            <p class="mb-0">© 2025 Alicorp from XPLORA</p>
        </small>
        </footer>
    </div>
        </div>
        
        </form>
        
    </div>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
 <script>
document.getElementById("login").addEventListener("submit", function(e) {
    e.preventDefault(); // Detiene el envío tradicional

    //VALIDACION DE QUE NO PERMITA LOGUEARSECON INPUT VACIO


    const formData = new FormData(this);

    fetch("includes/process_login.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === "success") {
            Swal.fire({
                icon: 'success',
                title: 'Inicio de sesión exito',
                timer: 1500,
                showConfirmButton: false,
                timerProgressBar: true
            }).then(() => {
                window.location.href = 'index.php';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message
            });
        }
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo contactar con el servidor.'
        });
    });
});
</script> 



</body>
</html>
 
 