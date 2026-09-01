<?php
include_once 'includes/db_connect.php'; // LOGICA PARA LA CONEXION CON LA BASE DE DATOS
include_once 'includes/functions.php'; // AQUI SE LLAMA A FUNCIONES COMO SEC_SESSION_START()

sec_session_start(); // SE PERMITE EL INICION DE SESION

if (login_check($mysqli) !== true) {
    header('Location: login.php');
    exit;
}

 

$usuario = $_SESSION['supervisor']; // se obtiene el nombre del supervisor logeado.

// Lógica de búsqueda
$buscar = $_GET['buscar'] ?? ''; // aqui se captura lo que el usuario INGRESA en el buscador DEL LOGIN

// Consulta a la base de datos con filtro por supervisor y búsqueda
$query = "SELECT pos_id, pos_name, cedi
          FROM repositorio_locales_supervisores_cliente
          WHERE
              (EXISTS( SELECT 1 FROM repositorio_cliente_usuarios_universales WHERE usuario = ? ) OR supervisor = ?)
               AND (pos_id LIKE CONCAT('%', ?, '%')
               OR  pos_name LIKE CONCAT('%', ?, '%')
               OR      cedi LIKE CONCAT('%', ?, '%'))
               AND pos_id IS NOT NULL AND pos_id <> ''
               AND pos_name IS NOT NULL AND pos_name <> ''
               AND cedi IS NOT NULL AND cedi <> ''
          GROUP BY pos_id
          ORDER BY pos_id ASC
          LIMIT 100"; // esto esque ordena alfabeticamente
 
 
 
 
// PREPARA Y EJEUTA LA CONSULTA:
$stmt = $mysqli->prepare($query);
// aqui se filtran los 4 parametros 1 supervisor y 3 condiciones de busqueda
//la cadena 'ssss' signfiica que los parametros se buscaran datos string
$stmt->bind_param('sssss',
    $_SESSION['supervisor'],  // Para EXISTS (universal)
    $_SESSION['supervisor'],  // Para supervisor = ?
    $buscar, $buscar, $buscar );
$stmt->execute(); // ejecutas la consulta antes preparada
$result = $stmt->get_result(); // se obtiene el resultado
 
// Guardar en array
//se crea el una variable para un array, inicializa vacia
$pdvs = [];
while ($row = $result->fetch_assoc()) { //el result contiende los datos devuelyos por el select
    $pdvs[] = $row; //row agregal los resultados en el arr
}




?>



<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8"> <!-- Corregido UTF-3 a UTF-8 -->
    <link rel="icon" href="assets/images/ali.jpg" type="image/jpg">
    <title>Buscador de PDV</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Estilos CSS -->
    <link rel="stylesheet" href="assets/css/main.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />


    <style>
        body {
            margin: 0;
            padding: 1rem;
            background: rgb(13, 13, 13);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .content-container {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 50px rgba(255, 0, 0, 0.2);
            width: 100%;
            max-width: 1200px;
            margin: 1rem auto;
        }

        .footer-text {
            font-size: 0.8rem;
            color: #888;
            text-align: center;
            margin-top: 1.5rem;
        }

        .sombra-select {
            box-shadow: 0 4px 8px hsla(0, 94.40%, 51.00%, 0.20);
            border: 1px solid #ccc;
            border-radius: 5px;
        }


        .btn-secondary1 {
            background-color: rgb(233, 86, 53);
            color: white;
            border: none;
            width: 100%;
            margin-top: 0.5rem;
                border-radius: 80px; /* Ejemplo: 8px para un redondeado suave */

        }


        .imagen-previsualizacion {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .pdv_disabled {
            /* Evita cualquier interacción del ratón */
            pointer-events: none;
            background-color: #e1dcdc;
            /* Baja la opacidad para indicar visualmente que está inactivo */
            opacity: 0.5;
            /* Deshabilita selección de texto */
            user-select: none;
        }


        .pdv-overlay-block {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(220, 220, 220, 0.7);
            z-index: 10;
            cursor: not-allowed;
            /* cambia el cursor pror uno que iNDICA que esa accion no esta permtida*/
            display: flex;
            align-items: center;
            justify-content: center;
        }
        /* Estilo base del botón si no tienes un color predefinido */
.mi-boton-flotante {
   
    border: none; /* Quita el borde si no lo quieres */
    transition: all 0.3s ease; /* Suaviza la transición para la animación */
    position: relative; /* Necesario para que el z-index y la sombra funcionen bien */
    z-index: 1; 
}

/* Efecto al pasar el mouse por encima (hover) */
.mi-boton-flotante:hover {
    transform: translateY(-5px); /* Mueve el botón ligeramente hacia arriba */
    box-shadow: 0 4px 50px rgba(255, 0, 0, 0.2); /* La sombra que solicitaste */
    z-index: 2; /* Asegura que el botón "flote" por encima de todo lo demás */
}

/* Adjust Select2 width to fit Bootstrap modals */
        .select2-container {
            width: 100% !important;
        }


        @media (max-width: 768px) {
            .content-container {
                padding: 1rem;
                border-radius: 10px;
            }

            h3 {
                font-size: 1.4rem;
            }

            .btn-secondary1 {
                margin-left: -26px;
                 border-radius: 80px;
            }

            .row.fw-bold.border-bottom.py-2>div {
                font-size: 0.9rem;
            }

            .row.align-items-center.border-bottom.py-3>div {
                font-size: 0.9rem;
                padding: 0.5rem;
            }

            .modal-dialog {
                margin: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .input-group {
                flex-wrap: wrap;
            }

            .input-group .form-control {
                border-radius: 5px;
                margin-bottom: 0.5rem;
            }

            .input-group .btn {
                width: 100%;
                border-radius: 5px;
            }

            .col-3,
            .col-4,
            .col-2 {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .col-3 {
                order: 1;
            }

            .col-4 {
                order: 3;
                flex: 0 0 100%;
                max-width: 100%;
                margin-top: 0.5rem;
            }

            .col-2 {
                order: 2;
                text-align: left !important;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>

<body class="bg-light">


    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column flex-sm-row gap-2">
            <a href="includes/logout.php" class="btn btn-danger"> Cerrar sesión
            </a>
        </div>
    </div>



<div class="content-container">
    <div class="d-flex justify-content-between align-items-center"> <div class="d-flex justify-content-center flex-grow-1"> <img src="assets/images/alicorp.jpg" alt="Logo ALICORP" class="img-fluid rounded mx-auto d-block"
                style="max-width: 180px;">
        </div>

<button class="btn mb-2 d-flex flex-column align-items-center p-2 mi-boton-flotante" data-bs-toggle="modal" data-bs-target="#modalPreguntasAlicorp">
            <img src="assets/images/precios.jpg" alt="Precios Alicorp" style="width: 80px; height: 80px; object-fit: cover;">
<p class="fw-bold mb-2">Precios Alicorp </p></button>
                

<!-- NUEVO MODAL -->
</div>
<div class="modal fade" id="modalPreguntasAlicorp" tabindex="-1" aria-labelledby="modalPreguntasAlicorpLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalPreguntasAlicorpLabel" >Precios Alicorp</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="puntoVenta" class="form-label"><strong>Elija un punto de venta:</strong></label>
          <select class="form-select" id="puntoVenta" aria-label="Seleccione "></select>

          <div id="distribuidoraContainer" class="col-md-3 my-4 d-none">
            <strong>Distribuidora:</strong> <span id="distribuidoraTexto"></span>
          </div>

          <div id="provinciaContainer" class="col-md-3 my-4 d-none">
            <strong>Provincia:</strong> <span id="provinciaTexto"></span>
          </div>

          <div id= "ciudadElegidaContainer" class="col-md-3 my-4 d-none">
            <strong>Ciudad elegida:</strong> <span id="ciudadElegidaTexto"></span>
    </div>


          <select class="form-select tipoCiudad" id="elegirCiudadSelect" aria-label="Seleccione una Ciudad:">
            <option selected disabled>Seleccione una Ciudad:</option>
          </select>
        </div>

        <p id="contador-preguntasNuevas" class="fw-bold mt-2"></p>
        <div id="preguntasNuevas-container" class="mt-2">
          <form id="formulario-preguntasNuevas" action="getpreguntasalicorp.php" method="post">
          </form>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary">Guardar cambios</button>
      </div>
    </div>
  </div>
</div>



        <h3 class="text-center mb-2">Bienvenido, <strong id="nombre_supervisor">
                <?php echo htmlspecialchars($usuario); ?> </strong></h3>
        <!-- <p class="text-center text-muted mb-3">A continuación se muestran sus PDV disponibles</p> -->

        <div class="container">
            <div class="text-center m-2">
                <button id="btn-generar-reporte" class="btn btn-light btn-block" disabled>Generar Reporte</button>
            </div>
        </div>

        <form method="get" class="mb-3">
            <div class="input-group">
                <input type="text" class="form-control" name="buscar" id="buscar"
                    value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Buscar por cliente, código o ciudad">
                <button id="btn-buscar" type="submit" class="btn btn-secondary1">Buscar</button>
            </div>
        </form>

        <?php if (count($pdvs) > 0): ?>
            <p class="fw-bold mb-2">
                Se muestran <?php echo count($pdvs) === 1 ? '' : ' sus primeros'; ?>
                <?php echo count($pdvs); ?> puntos de ventas
            </p>

            <div class="container px-0">
                <div class="row fw-bold border-bottom py-2 d-none d-md-flex">
                    <div class="col-md-3">Código</div>
                    <div class="col-md-4">Cliente</div>
                    <div class="col-md-3">Ciudad</div>
                    <div class="col-md-2 text-end">Acción</div>
                </div>

                <?php foreach ($pdvs as $pdv): ?>

                    <div id="btn-pdv-<?php echo $pdv['pos_id']; ?>" class="row align-items-center border-bottom py-2" data-bs-toggle="modal" data-bs-target="#modal-<?php echo $pdv['pos_id']; ?>" style="cursor: pointer;">
                        <div class="col-6 col-md-3 mb-1"> <?php echo break_number(htmlspecialchars($pdv['pos_id'])); ?> </div>
                        <div class="col-12 col-md-4 mb-1"><?php echo htmlspecialchars($pdv['pos_name']); ?></div>
                        <div class="col-6 col-md-3"><?php echo (htmlspecialchars($pdv['cedi'])); ?></div>
                        <div class="col-6 col-md-2 text-md-end d-flex justify-content-end">
                            <button class=" btn btn-info btn-abrir-novedad"
                                data-bs-toggle="modal"
                                data-bs-target="#novedadesModal"
                                data-pos-id="<?php echo $pdv['pos_id']; ?>" data-pos-name="<?php echo htmlspecialchars($pdv['pos_name']); ?>">
                                Novedad
                            </button>
                        </div>
                    </div>

                    <!-- Modal -->
                    <div class="modal fade" id="modal-<?php echo $pdv['pos_id']; ?>" tabindex="-1"
                        aria-labelledby="modalLabel-<?php echo $pdv['pos_id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">PDV "<?php echo htmlspecialchars($pdv['pos_name']); ?>"</h5>
                                    <!--    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Cerrar"></button> -->
                                </div>
                                <div class="modal-body">
                                    <p id="pos-id-<?php echo $pdv['pos_id']; ?>" style="text-decoration: none;"><strong>Código:</strong> <?php echo htmlspecialchars($pdv['pos_id']); ?></p>
                                    <p id="pos-name-<?php echo $pdv['pos_id']; ?>"><strong>Cliente:</strong> <?php echo htmlspecialchars($pdv['pos_name']); ?></p>
                                    <p id="cedi-<?php echo $pdv['pos_id']; ?>"><strong>Ciudad:</strong> <?php echo htmlspecialchars($pdv['cedi']); ?></p>

                                    <div class="py-2">
                                        <label class="form-label fw-bold mb-2">Seleccione tipo de canal:</label>
                                        <select id="tipo" class="form-select tipo-canal sombra-select"
                                            data-pos-id="<?php echo $pdv['pos_id']; ?>">
                                            <option selected disabled>Escoga un tipo de canal</option>
                                            <option value="MAYORISTAS">MAYORISTAS</option>
                                            <option value="TIENDAS">TIENDAS</option>
                                            <option value="AASSRR">AASSRR</option>
                                        </select>
                                    </div>

                                    <p id="contador-preguntas-<?php echo $pdv['pos_id']; ?>" class="fw-bold mt-2"></p>

                                    <div id="preguntas-container-<?php echo $pdv['pos_id']; ?>" class="mt-2">
                                        <form id="formulario-preguntas-<?php echo $pdv['pos_id']; ?>"
                                            action="preguntas_repositorio.php" method="post">
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <p class="text-center text-muted">No se encontraron puntos de venta.</p>
        <?php endif; ?>

        <div class="footer-text">
            <p>Español (Ecuador)</p>
            <p>© 2025 Alicorp from XPLORA</p>
        </div>
    </div>

    <div class="modal fade" id="confirmacionModal" tabindex="-1" aria-labelledby="confirmacionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content m-3">

                <!-- Encabezado -->
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmacionModalLabel">Confirmación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <!-- Cuerpo -->
                <div class="modal-body">
                    ¿Desea salir de la vista de evaluación del PDV?
                </div>

                <!-- Pie de modal con acciones -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        No, quiero quedarme
                    </button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarSalir">
                        Sí, salir
                    </button>
                </div>

            </div>
        </div>
    </div>


      



    </div>

    <div class="modal fade" id="novedadesModal" tabindex="-1" aria-labelledby="novedadesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content m-3">

                <!-- Encabezado -->
                <div class="modal-header">
                    <h5 class="modal-title" id="novedadesModalLabel">Novedades</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <!-- Formulario dentro del modal -->
                <form id="formNovedad">

                    <!-- Cuerpo -->
                    <div class="modal-body">
                        <!-- Contenedor para datos del PDV -->
                        <div class="ms-1">
                            <p class="mb-1"><strong>Código:</strong> <span id="modalPosId"></span></p>
                            <p><strong>Nombre:</strong> <span id="modalPosName"></span></p>
                        </div>

                        <label for="select-novedad" class="form-label"><strong>Novedad</strong></label>
                        <select id="select-novedad" class="form-select" aria-label="Default select example" required>
                            <option selected disabled value=""> Escoja una novedad</option>
                        </select>

                        <div id="comentario-container" style="display: none;">
                            <div class="form-group mt-3">
                                <label for="comentario-novedad"><strong>Comentario</strong></label>
                                <input type="text" class="form-control mt-3" id="comentario-novedad" placeholder="Comentario">
                            </div>
                        </div>

                        <div class="container d-flex flex-row justify-content-center mt-3">
                            <div class="d-flex align-items-center gap-4"> <!-- Aumentamos a gap-4 -->
                                <label class="form-label m-0 me-2"><strong>Foto:</strong></label> <!-- Agregamos margen derecho -->

                                <div class="dropdown">
                                    <button class="btn btn-secondary1 dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown"
                                        id="dropdownMenuButton"
                                        aria-haspopup="true"
                                        aria-expanded="false">
                                        Elija Archivo
                                    </button>
                                    <ol class="dropdown-menu text-center">
                                        <li>
                                            <button class="dropdown-item open-camera" type="button" data-pregunta-id="novedad">
                                                📷 Abrir Cámara
                                            </button>
                                        </li>
                                        <li>
                                            <label class="dropdown-item" for="inputArchivo_novedad" style="cursor: pointer;">
                                                📁 Cargar Archivo
                                            </label>
                                            <input type="file"
                                                name="evidencia"
                                                id="inputArchivo_novedad"
                                                class="d-none"
                                                onchange="previsualizarImagen('novedad')"
                                                accept=".jpg, .jpeg, .png, image/jpeg, image/png, image/jfif" />
                                        </li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <div id="imagen-container" class="m-3">
                            <img id="imagenPrevisualizacion_novedad"
                                class="imagen-previsualizacion rounded float-right mb-3" style="width: 345px; height: 300px; display: none;">
                        </div>
                    </div>

                    <!-- Pie de modal con acciones -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                            Cerrar
                        </button>
                        <button type="submit" class="btn btn-success" id="btnGuardar">
                            Guardar
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>


    <!-- Modal para la cámara: modal fade -->
    <div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-ls modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Capturar Foto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body text-center">
                    <video id="video" autoplay playsinline style="width: 100%; max-height: 400px;"></video>
                    <canvas id="canvas" style="display: none;"></canvas>
                    <input type="hidden" id="currentPreguntaId">
                </div>
                <div class="modal-footer justify-content-between">
                    <button class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                    <button id="takePhoto" class="btn btn-success">Tomar Foto</button>
                </div>
            </div>
        </div>
    </div>
    


    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/accesscamera.js"></script>
</body>

</html>
