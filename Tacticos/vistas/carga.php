<?php
$pageTitle = "Carga de Archivos - Distributivo";
ob_start();
?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Carga de Distributivo</h2>
    <div id="response" class="alert" 
    style="display:none; 
         text-align: center;
        font-size: 16px;
        font-weight: bold;
        margin: 20px 0;"></div>

    <!-- Formulario para cargar archivo -->
    <div class="card">
        <div class="card-body">
            <h4>Cargar Archivo CSV</h4>
            <form enctype="multipart/form-data" id="uploadForm">
                <div class="mb-3">
                    <label class="form-label">Seleccione un archivo CSV:</label>
                    <input type="file" name="file" id="file" class="form-control" accept=".csv" required>
                </div>
                <button type="submit" id="submitBtn" class="btn btn-primary w-100">Cargar Archivo</button>
            </form>
        </div>
    </div>

    <!-- Mostrar la última fecha y hora de carga -->
    <div class="row mt-4">
        <div class="col-xl-12 col-lg-12 mb-4">
            <h4>Última carga realizada:</h4>
            <div id="lastUploadTimeDisplay" style="font-size: 16px; font-weight: bold; color: #007bff;">
                No hay registros previos.
            </div>
        </div>
    </div>

    <!-- Tabla de datos subidos -->
    <div class="row mt-4">
        <div class="col-xl-12 col-lg-12 mb-4">
            <h4>Datos Subidos en la Base</h4>
            <div id="data-result">Cargando datos...</div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script>
    $(document).ready(function () {
        loadTableData();

        // Mostrar la última fecha y hora de carga guardada en localStorage
        const lastUploadTime = localStorage.getItem("lastUploadTime");
        if (lastUploadTime) {
            $("#lastUploadTimeDisplay").text(lastUploadTime);
        }

        // Subir el archivo
        $("#uploadForm").on("submit", function (event) {
            event.preventDefault();

            var formData = new FormData(this);

            $.ajax({
                url: "../upload_distributivo.php",
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function () {
                    $("#submitBtn").prop("disabled", true).text("Cargando...");
                },
                success: function (response) {
                    $("#submitBtn").prop("disabled", false).text("Cargar Archivo");

                    if (response.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Éxito",
                            text: response.message
                        });

                        // Capturar la fecha y hora actual
                        const currentDateTime = new Date().toLocaleString();
                        
                        // Mostrar en el frontend
                        $("#lastUploadTimeDisplay").text(currentDateTime);
                        
                        // Guardar en localStorage
                        localStorage.setItem("lastUploadTime", currentDateTime);

                        loadTableData();
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: response.message
                        });
                    }
                },
                error: function () {
                    $("#submitBtn").prop("disabled", false).text("Cargar Archivo");

                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "No se pudo procesar la solicitud."
                    });
                }
            });
        });

        // Cargar datos en la tabla
        function loadTableData() {
            $.ajax({
                url: "../get_distributivo_data.php",
                type: "GET",
                success: function (response) {
                    if (response.success) {
                        let tableHtml = `
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Mercaderista</th>
                                        <th>Regional</th>
                                        <th>Jefatura</th>
                                        <th>Ejecutivo</th>
                                        <th>Supervisor</th>
                                        <th>Distribuidor</th>
                                        <th>Ciudad</th>
                                        <th>Táctico</th>
                                        <th>Cantidad Asignada</th>
                                        <th>Fecha de Asignación</th>
                                        <th>Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;

                        response.data.forEach(row => {
                            tableHtml += `
                                <tr>
                                    <td>${row.mercaderista}</td>
                                    <td>${row.regional}</td>
                                    <td>${row.jefatura}</td>
                                    <td>${row.ejecutivo}</td>
                                    <td>${row.supervisor}</td>
                                    <td>${row.distribuidor}</td>
                                    <td>${row.ciudad}</td>
                                    <td>${row.tactico}</td>
                                    <td>${row.cantidad_asignada}</td>
                                    <td>${row.fecha_asignacion}</td>
                                    <td>${row.observaciones}</td>
                                </tr>
                            `;
                        });

                        tableHtml += `
                                </tbody>
                            </table>
                        `;

                        $("#data-result").html(tableHtml);
                    } else {
                        $("#data-result").html("<p>No hay datos disponibles.</p>");
                    }
                },
                error: function () {
                    $("#data-result").html("<p>Error al cargar los datos.</p>");
                }
            });
        }
    });
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
