<?php
$pageTitle = "Avance de Tácticos Adicionales";
ob_start();
?>

<style>
      .table-title {
        background-color: rgba(200, 150, 255, 0.2); /* Lila opaco */
        color: #5a3d8a; /* Texto lila oscuro */
        font-weight: bold;
        text-align: center;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
</style>

<div class="container">
    <h1 class="text-center mb-4">Avance de Tácticos Adicionales</h1>

    <!-- Botón para descargar el informe -->
    <div class="d-flex justify-content-end mb-4">
        <button class="btn btn-success" onclick="downloadExcel()">Descargar Informe Semanal</button>
    </div>

    <!-- Avance por Región -->
    <h4 class="table-title">Avance por Región</h4>
    <table class="table table-bordered table-hover" id="avanceRegionTable">
        <thead style="background-color: #FFD700; color: black;">
            <tr>
                <th>Región</th>
                <th>Cantidad de Recursos</th>
                <th>Cantidades Distribuidas</th>
                <th>Cantidades Armadas</th>
                <th>Faltantes</th>
                <th>% Avance</th>
            </tr>
        </thead>
        <tbody id="avanceRegionTableBody">
        </tbody>
    </table>

    <!-- Avance por Ejecutivo -->
    <h4 class="table-title">Avance por Ejecutivo</h4>
    <table class="table table-bordered table-hover" id="avanceEjecutivoTable">
        <thead style="background-color: #FFD700; color: black;">
            <tr>
                <th>Jefatura</th>
                <th>Ejecutivo</th>
                <th>Cantidad de Recursos</th>
                <th>Cantidades Distribuidas</th>
                <th>Cantidades Armadas</th>
                <th>Faltantes</th>
                <th>% Avance</th>
            </tr>
        </thead>
        <tbody id="avanceEjecutivoTableBody">
        </tbody>
    </table>

    <!-- Avance por Mercaderista -->
    <h4 class="table-title">Avance por Mercaderista</h4>
    <div id="spinner-mercaderistas" class="text-center my-3" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <p class="mt-2">Cargando datos, por favor espera...</p>
    </div>
    <table class="table table-bordered table-hover" id="avanceMercaderistaTable">
        <thead style="background-color: #007bff; color: white;">
            <tr>
                <th>Supervisor</th>
                <th>Mercaderista</th>
                <th>Distribuidor</th>
                <th class="text-center">Cantidades Distribuidas</th>
                <th class="text-center">Cantidades Armadas</th>
                <th class="text-center">Faltantes</th>
                <th>% Avance</th>
            </tr>
        </thead>
        <tbody id="avanceMercaderistaTableBody">
        </tbody>
    </table>
</div>

<!-- Modal para Detalle de Mercaderistas -->
<div class="modal fade" id="mercaderistaModal" tabindex="-1" aria-labelledby="mercaderistaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mercaderistaModalLabel">Detalle de Avance por Mercaderista</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <p><strong>Jefatura:</strong> <span id="modalJefatura"></span></p>
                    </div>
                    <div class="col-6">
                        <p><strong>Ejecutivo:</strong> <span id="modalEjecutivo"></span></p>
                    </div>
                </div>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Mercaderista</th>
                            <th>Distribuidor</th>
                            <th>Cantidad Distribuida</th>
                            <th>Cantidad Armada</th>
                            <th>Faltantes</th>
                            <th>% Avance</th>
                        </tr>
                    </thead>
                    <tbody id="mercaderistaTableBody">
                        <!-- Aquí se llenarán los datos dinámicamente -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.2/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/progressbar.js/1.1.0/progressbar.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/progressbar.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        // Cargar avance por región
        fetch('../get_avance_regional.php?es_adicional=true')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderAvanceRegionTable(data.data); 
                } else {
                    console.error("Error al cargar avance regional:", data.message);
                }
            })
            .catch(error => console.error("Error al cargar la tabla de avance por región:", error));

        // Cargar avance por ejecutivo
        fetch('../get_avance_ejecutivo.php?es_adicional=true')
            .then(response => {
                if (!response.ok) {
                    throw new Error("Error al obtener los datos");
                }
                return response.json();
            })
            .then(data => {
                renderAvanceEjecutivoTable(data);
            })
            .catch(error => {
                console.error("Error:", error);
                alert("No se pudo cargar la tabla de avance por ejecutivo.");
            });

        // Cargar avance por mercaderista
        document.getElementById("spinner-mercaderistas").style.display = "block";
        fetch('../get_avance_mercaderistas.php?es_adicional=true')
            .then(response => response.json())
            .then(data => {
                document.getElementById("spinner-mercaderistas").style.display = "none";
                renderMercaderistaTable(data);
            })
            .catch(error => console.error("Error:", error));


            function renderAvanceRegionTable(data) {
        const tableBody = document.getElementById("avanceRegionTableBody");
        tableBody.innerHTML = "";

        data.forEach((row, index) => {
            const faltantes = Math.max((row.cantidad_distribuida || 0) - (row.cantidad_armada || 0), 0);
            const progress = (row.cantidad_distribuida > 0)
                ? Math.min(((row.cantidad_armada / row.cantidad_distribuida) * 100).toFixed(1), 100)
                : 0;

            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td>${row.region || "N/A"}</td>
                <td class="text-center">${row.cantidad_recursos || 0}</td>
                <td class="text-center">${row.cantidad_distribuida || 0}</td>
                <td class="text-center">${row.cantidad_armada || 0}</td>
                <td class="text-center">${faltantes}</td>
                <td class="text-center">
                    <div id="regionProgressContainer-${index}" style="width:100%; height:20px;"></div>
                </td>
            `;

            tableBody.appendChild(tr);

            // Crear la barra de progreso
            const container = document.getElementById(`regionProgressContainer-${index}`);
            const color = progress <= 40 ? '#ff4d4d' : progress <= 60 ? '#ffc107' : '#28a745';
            const bar = new ProgressBar.Line(container, {
                strokeWidth: 10,
                color: color,
                trailColor: '#e9ecef',
                trailWidth: 10,
                svgStyle: { width: '100%', height: '100%' },
                text: {
                    value: `${progress}%`,
                    style: {
                        color: '#000',
                        position: 'absolute',
                        right: '10px',
                        top: '-3px',
                        fontSize: '0.9rem',
                    },
                },
            });
            bar.animate(progress / 100);
        });
    }

        function renderAvanceEjecutivoTable(data) {
            const tableBody = document.getElementById("avanceEjecutivoTableBody");
            tableBody.innerHTML = "";

            let currentJefatura = "";
            let subtotalRecursos = 0;
            let subtotalDistribuidos = 0;
            let subtotalArmados = 0;
            let subtotalFaltantes = 0;

            let totalRecursos = 0;
            let totalDistribuidos = 0;
            let totalArmados = 0;
            let totalFaltantes = 0;

            data.forEach((row, index) => {
                if (currentJefatura !== row.jefatura && currentJefatura !== "") {
                    // Agregar subtotales para cada jefatura
                    const subtotalRow = document.createElement("tr");
                    subtotalRow.style.backgroundColor = "#f2f2f2";
                    subtotalFaltantes = Math.max(subtotalDistribuidos - subtotalArmados, 0);
                    subtotalRow.innerHTML = `
                        <td colspan="2" class="text-end fw-bold">Subtotal ${currentJefatura}</td>
                        <td class="text-center fw-bold">${subtotalRecursos}</td>
                        <td class="text-center fw-bold">${subtotalDistribuidos}</td>
                        <td class="text-center fw-bold">${subtotalArmados}</td>
                        <td class="text-center fw-bold">${subtotalFaltantes}</td>
                        <td></td>
                    `;
                    tableBody.appendChild(subtotalRow);

                    // Reiniciar subtotales
                    subtotalRecursos = 0;
                    subtotalDistribuidos = 0;
                    subtotalArmados = 0;
                    subtotalFaltantes = 0;
                }

                // Actualizar la jefatura actual
                if (currentJefatura !== row.jefatura) {
                    currentJefatura = row.jefatura;
                }

                // Crear una nueva fila para el ejecutivo
                const tr = document.createElement("tr");

                if (currentJefatura === row.jefatura) {
                    // Insertar celda para la jefatura con rowspan si es la primera aparición
                    if (!data.some((r, idx) => idx < index && r.jefatura === row.jefatura)) {
                        const tdJefatura = document.createElement("td");
                        tdJefatura.textContent = currentJefatura;
                        tdJefatura.rowSpan = data.filter(r => r.jefatura === currentJefatura).length;
                        tdJefatura.style.verticalAlign = "middle";
                        tr.appendChild(tdJefatura);
                    }
                }

                // Añadir la celda para el Ejecutivo con el botón de detalle
                const tdEjecutivo = document.createElement("td");
                tdEjecutivo.innerHTML = `
                    <button class="btn btn-sm btn-info me-2" onclick="showMercaderistaDetail('${row.jefatura}', '${row.ejecutivo}')">
                        <i class="bi bi-plus-circle"></i>
                    </button>
                    ${row.ejecutivo || ""}
                `;
                tr.appendChild(tdEjecutivo);

                const tdRecursos = document.createElement("td");
                tdRecursos.textContent = row.cantidad_recursos || 0;
                tdRecursos.classList.add("text-center");
                tr.appendChild(tdRecursos);

                const tdDistribuidos = document.createElement("td");
                tdDistribuidos.textContent = row.cantidad_distribuida || 0;
                tdDistribuidos.style.textAlign = "center";
                tdDistribuidos.style.verticalAlign = "middle";
                tr.appendChild(tdDistribuidos);

                const tdArmados = document.createElement("td");
                tdArmados.textContent = row.cantidad_armada || 0;
                tdArmados.classList.add("text-center");
                tr.appendChild(tdArmados);

                const tdFaltantes = document.createElement("td");
                const faltantes = Math.max((row.cantidad_distribuida || 0) - (row.cantidad_armada || 0), 0);
                tdFaltantes.textContent = faltantes;
                tdFaltantes.classList.add("text-center");
                tr.appendChild(tdFaltantes);

                const tdAvance = document.createElement("td");
                const progressContainer = document.createElement("div");
                progressContainer.style.width = "100%";
                progressContainer.style.height = "20px";
                tdAvance.appendChild(progressContainer);

                const progress = (row.cantidad_distribuida > 0)
                    ? Math.min(((row.cantidad_armada / row.cantidad_distribuida) * 100).toFixed(1), 100)
                    : 0;

                const color = progress <= 40 ? '#ff4d4d' : progress <= 60 ? '#ffc107' : '#28a745';

                const bar = new ProgressBar.Line(progressContainer, {
                    strokeWidth: 10,
                    color: color,
                    trailColor: '#e9ecef',
                    trailWidth: 10,
                    svgStyle: { width: '100%', height: '100%' },
                    text: {
                        value: `${progress}%`,
                        style: {
                            color: '#000',
                            position: 'absolute',
                            right: '10px',
                            top: '-3px',
                            fontSize: '0.9rem',
                        },
                    },
                });
                bar.animate(progress / 100);

                tr.appendChild(tdAvance);
                tableBody.appendChild(tr);

                // Actualizar subtotales
                subtotalRecursos += parseInt(row.cantidad_recursos || 0, 10);
                subtotalDistribuidos += parseInt(row.cantidad_distribuida || 0, 10);
                subtotalArmados += parseInt(row.cantidad_armada || 0, 10);
                subtotalFaltantes = Math.max(subtotalDistribuidos - subtotalArmados, 0);

                // Actualizar totales generales
                totalRecursos += parseInt(row.cantidad_recursos || 0, 10);
                totalDistribuidos += parseInt(row.cantidad_distribuida || 0, 10);
                totalArmados += parseInt(row.cantidad_armada || 0, 10);
                totalFaltantes += parseInt(row.faltantes || 0, 10);
            });

            // Agregar Totales Generales al final
            const totalRow = document.createElement("tr");
            totalRow.style.backgroundColor = "#d4edda";
            totalFaltantes = Math.max(totalDistribuidos - totalArmados, 0);
            totalRow.innerHTML = `
                <td colspan="2" class="text-end fw-bold">Totales Generales</td>
                <td class="text-center fw-bold">${totalRecursos}</td>
                <td class="text-center fw-bold">${totalDistribuidos}</td>
                <td class="text-center fw-bold">${totalArmados}</td>
                <td class="text-center fw-bold">${totalFaltantes}</td>
                <td></td>
            `;
            tableBody.appendChild(totalRow);
        }

        function renderMercaderistaTable(data) {
            const tableBody = document.getElementById("avanceMercaderistaTableBody");
            tableBody.innerHTML = "";

            let currentSupervisor = "";
            data.forEach((row, index) => {
                const tr = document.createElement("tr");

                if (currentSupervisor !== row.supervisor) {
                    currentSupervisor = row.supervisor;

                    const supervisorRows = data.filter(r => r.supervisor === currentSupervisor).length;

                    const tdSupervisor = document.createElement("td");
                    tdSupervisor.textContent = currentSupervisor || "N/A";
                    tdSupervisor.rowSpan = supervisorRows;
                    tdSupervisor.style.verticalAlign = "middle";
                    tr.appendChild(tdSupervisor);
                }

                tr.innerHTML += `
                    <td>${row.mercaderista}</td>
                    <td>${row.distribuidor || 0}</td>
                    <td class="text-center">${row.cantidad_distribuida || 0}</td>
                    <td class="text-center">${row.cantidad_armada || 0}</td>
                    <td class="text-center">${row.cantidad_distribuida - row.cantidad_armada || 0}</td>
                `;

                const tdAvance = document.createElement("td");
                const progressContainer = document.createElement("div");
                progressContainer.style.width = "100%";
                progressContainer.style.height = "20px";
                tdAvance.appendChild(progressContainer);

                const progress = row.cantidad_distribuida
                    ? Math.min(((row.cantidad_armada / row.cantidad_distribuida) * 100).toFixed(1), 100)
                    : 0;

                const color = progress <= 40 ? '#ff4d4d' : progress <= 60 ? '#ffc107' : '#28a745';

                const bar = new ProgressBar.Line(progressContainer, {
                    strokeWidth: 10,
                    color: color,
                    trailColor: '#e9ecef',
                    trailWidth: 10,
                    svgStyle: { width: '100%', height: '100%' },
                    text: {
                        value: `${progress}%`,
                        style: {
                            color: '#000',
                            position: 'absolute',
                            right: '10px',
                            top: '-3px',
                            fontSize: '0.9rem',
                        },
                    },
                });
                bar.animate(progress / 100);

                tr.appendChild(tdAvance);
                tableBody.appendChild(tr);
            });
        }

       

        function downloadExcel() {
            const regionTable = document.getElementById("avanceRegionTable");
            const ejecutivoTable = document.getElementById("avanceEjecutivoTable");
            const mercaderistaTable = document.getElementById("avanceMercaderistaTable");

            const wb = XLSX.utils.book_new();
            wb.SheetNames.push("Avance por Región");
            wb.SheetNames.push("Avance por Ejecutivo");
            wb.SheetNames.push("Avance por Mercaderista");

            const wsRegion = XLSX.utils.table_to_sheet(regionTable);
            const wsEjecutivo = XLSX.utils.table_to_sheet(ejecutivoTable);
            const wsMercaderista = XLSX.utils.table_to_sheet(mercaderistaTable);

            wb.Sheets["Avance por Región"] = wsRegion;
            wb.Sheets["Avance por Ejecutivo"] = wsEjecutivo;
            wb.Sheets["Avance por Mercaderista"] = wsMercaderista;

            XLSX.writeFile(wb, "reporte_avance.xlsx");
        }
    });

    function showMercaderistaDetail(jefatura, ejecutivo) {
            // Configurar encabezados del modal
            document.getElementById("modalJefatura").textContent = jefatura || "N/A";
            document.getElementById("modalEjecutivo").textContent = ejecutivo || "N/A";

            // Limpiar contenido previo
            const tableBody = document.getElementById("mercaderistaTableBody");
            tableBody.innerHTML = "<tr><td colspan='6'>Cargando datos...</td></tr>";

            // Realizar la llamada al web service con el método POST
            fetch("../get_mercaderista_detail.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ ejecutivo: ejecutivo }), // Enviar el ejecutivo en el cuerpo
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error("Error al obtener los datos de los mercaderistas.");
                    }
                    return response.json();
                })
                .then(data => {
                    tableBody.innerHTML = ""; // Limpiar el mensaje de carga

                    if (data.length > 0) {
                        data.forEach(row => {
                            const tr = document.createElement("tr");
                            tr.innerHTML = `
                                <td>${row.mercaderista}</td>
                                <td>${row.distribuidor}</td>
                                <td>${row.cantidad_distribuida}</td>
                                <td>${row.cantidad_armada}</td>
                                <td>${row.faltantes}</td>
                                <td>${row.avance}</td>
                            `;
                            tableBody.appendChild(tr);
                        });
                    } else {
                        tableBody.innerHTML = "<tr><td colspan='6'>No hay datos disponibles</td></tr>";
                    }

                    // Mostrar el modal
                    new bootstrap.Modal(document.getElementById("mercaderistaModal")).show();
                })
                .catch(error => {
                    console.error("Error:", error);
                    tableBody.innerHTML = "<tr><td colspan='6'>Error al cargar los datos</td></tr>";
                });
        }
 


</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>