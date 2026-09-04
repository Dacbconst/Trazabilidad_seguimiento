<?php

// error_reporting(E_ALL); // Habilitar reporte de errores
// ini_set('display_errors', 1); // Mostrar errores en pantalla

require_once  '../conexion/DataSource.php';
use Phppot\DataSource;

$database = new DataSource();
$conn = $database->getConnection();



// Verificar si el parámetro "gestor" está presente en la URL
if (!isset($_GET['gestor'])) {
    die("Error: El parámetro 'gestor' es requerido en la URL.");
}

$gestor = $_GET['gestor']; // Obtener el gestor desde la URL

// Consulta para obtener los datos de los reportes
$query = "
    SELECT 
        pos_name AS pdv, 
        SUM(CASE WHEN estado = 3 THEN 1 ELSE 0 END) AS reportados,
        SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) AS corregidos
    FROM                    
        insert_packs
    WHERE 
        usuario = ?
        AND estado IN (1,3)
        AND MONTH(STR_TO_DATE(fecha, '%d/%m/%Y')) = MONTH(CURDATE())
        AND YEAR(STR_TO_DATE(fecha, '%d/%m/%Y')) = YEAR(CURDATE())
    GROUP BY 
        pos_name
    ORDER BY 
        pos_name;
";

// Consulta para obtener el total de reportes pendientes de corregir (estado = 3)
$query_total_reportados = "
    SELECT 
        COUNT(*) AS total_reportados
    FROM                    
        insert_packs
    WHERE 
        usuario = ?
        AND estado = 3
        AND MONTH(STR_TO_DATE(fecha, '%d/%m/%Y')) = MONTH(CURDATE())
        AND YEAR(STR_TO_DATE(fecha, '%d/%m/%Y')) = YEAR(CURDATE());
";

try {
    // Obtener el total de reportes pendientes de corregir
    $stmt_total = $conn->prepare($query_total_reportados);
    $stmt_total->bind_param("s", $gestor);
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    $row_total = $result_total->fetch_assoc();
    $rcant = $row_total['total_reportados']; // Total de reportes pendientes

    // Obtener los datos para la tabla
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $gestor);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            "pdv" => $row['pdv'],
            "reportados" => $row['reportados'],
            "corregidos" => $row['corregidos']
        ];
    }
} catch (Exception $e) {
    die("Error en la consulta: " . $e->getMessage()); // Mostrar errores de la consulta
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.2/js/jquery.dataTables.min.js"></script>
    <!-- EXPORTAR -->
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.print.min.js"></script>

    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.2/css/jquery.dataTables.min.css">

    <script type="text/javascript">
        $(document).ready(function() {
            $('#reportesTable').DataTable({
                scrollX: true, // Habilitar scroll horizontal
                responsive: true, // Hacer la tabla responsive
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.2/i18n/es-ES.json' // Español
                }
            });
        });
    </script>

<style>
        /* Estilos generales */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 10px;
            color: #333;
        }

        h1, h2, h3 {
            text-align: center;
            color: #6a1b9a; /* Color lila */
        }

        h1 {
            font-size: 2em; /* Tamaño más pequeño para móviles */
            margin-bottom: 10px;
        }

        h2 {
            font-size: 1.5em; /* Tamaño más pequeño para móviles */
            margin-bottom: 5px;
        }

        h3 {
            font-size: 1.2em; /* Tamaño más pequeño para móviles */
            margin-bottom: 20px;
            color: #333;
        }

        /* Estilos para la tabla */
        #reportesTable {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #fff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        #reportesTable thead {
            background-color: #6a1b9a; /* Fondo lila */
            color: #fff;
        }

        #reportesTable th, #reportesTable td {
            padding: 10px 12px; /* Padding más pequeño para móviles */
            text-align: center;
        }

        #reportesTable th {
            font-weight: 500;
        }

        #reportesTable tbody tr {
            border-bottom: 1px solid #ddd;
        }

        #reportesTable tbody tr:nth-of-type(even) {
            background-color: #f9f9f9;
        }

        #reportesTable tbody tr:hover {
            background-color: #f1f1f1;
        }

        /* Contenedor principal */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 10px; /* Padding más pequeño para móviles */
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        /* Personalizar el scroll */
        .dataTables_scrollBody::-webkit-scrollbar {
            width: 8px; /* Ancho del scroll */
            height: 8px; /* Altura del scroll */
        }

        .dataTables_scrollBody::-webkit-scrollbar-thumb {
            background-color: #6a1b9a; /* Color del scroll */
            border-radius: 4px; /* Bordes redondeados */
        }

        .dataTables_scrollBody::-webkit-scrollbar-track {
            background-color: #f1f1f1; /* Color de fondo del scroll */
        }
        /* Ajustes para pantallas pequeñas */
        @media (max-width: 600px) {
            h1 {
                font-size: 1.8em; /* Tamaño más pequeño para móviles */
            }

            h2 {
                font-size: 1.3em; /* Tamaño más pequeño para móviles */
            }

            h3 {
                font-size: 1em; /* Tamaño más pequeño para móviles */
            }

            #reportesTable th, #reportesTable td {
                padding: 8px 10px; /* Padding más pequeño para móviles */
                font-size: 0.9em; /* Tamaño de fuente más pequeño */
            }

        }
    </style>
</head>

<body>
    <div class="container">
        <h1>REPORTES POR PDV</h1>
        <h2>Gestor: <?php echo strtoupper($gestor) ?></h2>
        <!-- Título dinámico con el total de reportes pendientes -->
        <h3>Usted tiene <?php echo $rcant; ?> Reporte(s) para corregir</h3>
        <p>Realizar la corrección en el modulo "reportado" dentro del PDV</p>
    </div>

    <div class="container">
        <div class="outer-scontainer">
            <table id="reportesTable" class="display">
                <thead>
                    <tr>
                        <th>PDV</th>
                        <th>Reportados</th>
                        <th>Corregidos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?php echo $row['pdv']; ?></td>
                            <td><?php echo $row['reportados']; ?></td>
                            <td><?php echo $row['corregidos']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>

</html>