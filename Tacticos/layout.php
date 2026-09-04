<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/App/XploraEcuador/Tacticos/css/style.css">


    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css"/>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables base -->
    <link 
    rel="stylesheet" 
    href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css"/>
    <script 
    src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js">
    </script>

    <!-- Extensión Buttons -->
    <link 
    rel="stylesheet" 
    href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css"/>
    <script 
    src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js">
    </script>
    <script 
    src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js">
    </script>
    <script 
    src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js">
    </script>
  


    <style>
        /* Posicionar el logo en la esquina superior derecha */
        .logo-container {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 999;
        }

        .logo-container img {
            max-width: 120px;
            height: auto;
        }
        
    </style>
</head>
<body>
    <!-- Contenedor para el logo -->
    <div class="logo-container">
        <img src="/App/XploraEcuador/Tacticos/img/logo_jw_mail.png" alt="Logo" class="img-fluid">
    </div>

    <div class="d-flex">
        <!-- Sidebar -->
        <?php include 'vistas/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content flex-grow-1 p-4">
            <h1><?php echo $pageTitle; ?></h1>
            <?php echo $content; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="/App/XploraEcuador/Tacticos/js/main.js"></script>
</body>
</html>
