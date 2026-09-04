<?php
$pageTitle = "Seguimiento";
ob_start();

// Obtener la fecha del día anterior
$currentDate = date("d-m-Y", strtotime("-1 day"));

?>
<style>
    .progress-bar {
        background-color: #007bff;
    }
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
    .gestor-card {
        background-color: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        position: relative;
    }

    .gestor-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: bold;
        font-size: 1.1rem;
    }

    .toggle-arrow {
        font-size: 1.5rem;
        cursor: pointer;
        transition: transform 0.3s;
    }

    .toggle-arrow.collapsed {
        transform: rotate(-90deg);
    }

    #avanceTacticoChart {
        margin-top: 20px;
    }
    .btn-info {
        color: white;
        background-color: #007bff;
        border-color: #007bff;
    }

    .btn-info:hover {
        background-color: #0056b3;
        border-color: #0056b3;
    }
    .modal-image {
        max-width: 95%; /* Ajusta el ancho a casi todo el modal */
        max-height: 90vh; /* Ajusta el alto para usar hasta el 90% de la altura del viewport */
        object-fit: contain; /* Mantiene las proporciones */
        margin: auto; /* Centra la imagen */
        display: block; /* Asegura que se comporte como un bloque */
        border: 3px solid #ddd; /* Añade un borde para mejor visibilidad */
        padding: 5px; /* Espaciado interno */
        background-color: white; /* Fondo claro */
    }
    .badge {
        font-size: 1rem;
        padding: 0.5em 0.75em;
        border-radius: 0.25rem;
        display: inline-block;
    }
    .bg-warning {
        background-color: #ffc107 !important;
    }
    .text-dark {
        color: #212529 !important;
    }
    .card {
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0px 8px 10px rgba(0, 0, 0, 0.2);
    }

    .card-title {
        font-weight: bold;
        color: #007bff;
    }

    .card-text {
        font-size: 2rem;
        color: #495057;
    }
    .nav-tabs .nav-link {
        transition: opacity 0.3s ease, transform 0.3s ease;
        opacity: 0.5;
    }

    .nav-tabs .nav-link.active {
        opacity: 1;
        font-weight: bold;
        color: #007bff;
        transform: scale(1.1); /* Opcional: aumentar ligeramente el tamaño */
    }

    #corregidos-container .gestor-card {
        background-color: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        position: relative;
    }

    #corregidos-container .gestor-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: bold;
        font-size: 1.1rem;
        background-color: #e5d4fa;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 10px;
    }

    #corregidos-container .table {
        margin-top: 10px;
    }

    #corregidos-container .table th {
        text-align: center;
        background-color: #e9e9ff;
        color: #333;
    }

    #corregidos-container .table td {
        text-align: center;
    }

    #corregidos-container .img-thumbnail {
        cursor: pointer;
        width: 80px;
    }

    
</style>

<!-- Modal para Reportar -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="reportModalLabel">Reportar Táctico</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="reportForm">
          <div class="mb-3">
            <label for="reportObservation" class="form-label">Observación</label>
            <textarea id="reportObservation" class="form-control" rows="4" placeholder="Escriba la justificación del reporte..." required></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="submitReport">Enviar Reporte</button>
      </div>
    </div>
  </div>
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


<div class="container">
    <h2 class="my-4 text-center">
        Revisión de Actividades - Fecha: <?php echo $currentDate; ?>
        <span class="badge bg-warning text-dark"> A día vencido</span>
    </h2>


    <h4 class="mt-4">Progreso Total del Mes</h4>
    <div class="progress mb-4">
        <div class="progress-bar bg-success" id="totalProgressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
    </div>

    <ul class="nav nav-tabs" id="trackingTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">General</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="gestores-tab" data-bs-toggle="tab" data-bs-target="#gestores" type="button" role="tab" aria-controls="gestores" aria-selected="false">Gestores</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="inspeccion-tab" data-bs-toggle="tab" data-bs-target="#inspeccion" type="button" role="tab" aria-controls="inspeccion" aria-selected="false">Inspección</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="corregidos-tab" data-bs-toggle="tab" data-bs-target="#corregidos" type="button" role="tab" aria-controls="corregidos" aria-selected="false">Registros Corregidos</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="trimestral-tab" data-bs-toggle="tab" data-bs-target="#trimestral" type="button" role="tab" aria-controls="trimestral" aria-selected="false">Trimestral</button>
        </li>

        <li class="nav-item" role="presentation">
            <button class="nav-link" id="base-tab" data-bs-toggle="tab" data-bs-target="#base" type="button" role="tab" aria-controls="base" aria-selected="false">Base</button>
        </li>


    </ul>

    <div class="tab-content" id="trackingTabsContent">

        <!-- Tab General -->
        <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="my-4">Resumen Semanal de Actividades</h3>
                <div>
                    <button class="btn btn-success" onclick="downloadExcel()">Descargar Informe Semanal</button>
                </div>
            </div>

            

            <!-- <h4 class="mt-5">Gráfico de Avance por Táctico</h4>
            <canvas id="avanceTacticoChart" width="400" height="200"></canvas> -->
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

    <script>
        document.addEventListener("DOMContentLoaded", () => {

            fetch('../get_avance_regional.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderAvanceRegionTable(data.data);
                    } else {
                        console.error("Error al cargar avance regional:", data.message);
                    }
                })
                .catch(error => console.error("Error al cargar la tabla de avance por región:", error));
                
                function renderAvanceRegionTable(data) {
                    const tableBody = document.getElementById("avanceRegionTableBody");
                    tableBody.innerHTML = "";

                    data.forEach((row, index) => {
                        // Calcula faltantes y el avance igual que en Ejecutivo/Mercaderista
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



            fetch('../get_avance_ejecutivo.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error("Error al obtener los datos");
                    }
                    return response.json();
                })
                .then(data => {
                    // updateTotalProgressBar(data);
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
                        bar.animate(progress / 100); // Normaliza a [0, 1]

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

                    // Agregar Totales Generales al final, sin duplicar
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
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("No se pudo cargar la tabla de avance por ejecutivo.");
                });
        });


        document.addEventListener("DOMContentLoaded", () => {
            document.getElementById("spinner-mercaderistas").style.display = "block";
            fetch('../get_avance_mercaderistas.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById("spinner-mercaderistas").style.display = "none";
                    updateTotalProgressBar(data);
                    renderMercaderistaTable(data);
                })
                .catch(error => console.error("Error:", error));
            });

            function renderMercaderistaTable(data) {
                document.getElementById("spinner-mercaderistas").style.display = "none";
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

                function toggleFiltroTrimestral() {
                    const filtroTrimestral = document.getElementById("filtroTrimestral");
                    if (filtroTrimestral.style.display === "none") {
                        filtroTrimestral.style.display = "block";
                    } else {
                        filtroTrimestral.style.display = "none";
                    }
                }
    </script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.2/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/progressbar.js/1.1.0/progressbar.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/progressbar.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>


    <!-- Tab de Gestores -->
    <div class="tab-pane fade" id="gestores" role="tabpanel" aria-labelledby="gestores-tab">
        <h3 class="my-4 table-title">Progreso de Tácticos por Gestores</h3>

        <div class="input-group mb-4">
            <input type="text" class="form-control" placeholder="Buscar gestor" id="search-input" onkeyup="searchGestores()">
        </div>

        <div id="gestor-container"></div>
    </div>

               
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl"> <!-- Modal más amplio -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">Detalle de la Foto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Barra superior con cantidades -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p><strong>Cantidad Encontrada:</strong> <span id="imageCantidadEncontrada"></span></p>
                        <p><strong>Cantidad Armada:</strong> <span id="imageCantidadArmada"></span></p>
                    </div>
                    <!-- Imagen centrada y ocupando espacio máximo -->
                    <div class="text-center d-flex justify-content-center align-items-center">
                        <img id="imageModalSrc" src="" alt="Imagen Ampliada" class="img-fluid modal-image">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        
            document.addEventListener("DOMContentLoaded", () => {
                fetch('../get_gestores.php') 
                .then(response => {
                    if (!response.ok) {
                        throw new Error("Error al obtener los datos");
                    }
                    return response.json();
                })
                .then(data => {
                    renderGestores(data); 
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("No se pudo cargar la información de los gestores.");
                });
            });


            function renderGestores(gestores) {
                const container = document.getElementById("gestor-container");
                container.innerHTML = ""; // Limpiar contenido previo

                gestores.forEach((gestor, indexGestor) => {
                    const gestorCard = `
                        <div class="gestor-card">
                            <div class="gestor-header" onclick="togglePdvDetails(${indexGestor})">
                                <span>Gestor: ${gestor.nombre}</span>
                                <span>${gestor.pdvs.length} PDVs Visitados</span>
                                <span class="toggle-arrow collapsed" id="arrow-${indexGestor}">&#9662;</span>
                            </div>
                            <div class="collapse mt-3" id="pdvs${indexGestor}">
                                ${gestor.pdvs.map(pdv => {
                                    // Capturar solo la primera foto guía del PDV
                                    const fotoGuia = pdv.tacticos.length > 0 ? pdv.tacticos[0].foto_guia : '';

                                    return `
                                        <h5>${pdv.nombre}</h5>
                                        <table class="table table-striped mt-3">
                                            <thead>
                                                <tr>
                                                    <th>Foto Guía</th>
                                                    <th>Producto Primario</th>
                                                    <th>Táctico</th>
                                                    <th>Cantidad Encontrada</th>
                                                    <th>Cantidad Armada</th>
                                                    <th>Foto Armado</th>
                                                    <th>Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${pdv.tacticos.map((tactico, tacticoIndex) => `
                                                    <tr 
                                                        data-gestor="${gestor.nombre}" 
                                                        data-pdv="${pdv.nombre}" 
                                                        data-tactico="${tactico.nombre}"
                                                        data-idpacks="${tactico.id_packs}">
                                                    
                                                        <td>
                                                            ${tacticoIndex === 0 ? `
                                                                <img src="${fotoGuia}" class="img-thumbnail" style="width: 100px; cursor: pointer;" 
                                                                    onclick="showImageModal('${fotoGuia}', '${tactico.cantidad_encontrada}', '${tactico.cantidad_armada}')">
                                                            ` : ''}
                                                        </td>
                                                        <td>${tactico.producto_primario}</td>
                                                        <td>${tactico.nombre}</td>
                                                        <td>${tactico.cantidad_encontrada}</td>
                                                        <td>${tactico.cantidad_armada}</td>
                                                        <td>
                                                            <img src="${tactico.foto_armado}" class="img-thumbnail" style="width: 100px; cursor: pointer;" 
                                                                onclick="showImageModal('${tactico.foto_armado}', '${tactico.cantidad_encontrada}', '${tactico.cantidad_armada}')">
                                                        </td>
                                                        <td>
                                                            ${tactico.estado == "2"
                                                                ? '<i class="bi bi-check-circle text-success"></i> Validado'
                                                                : tactico.estado == "3"
                                                                ? '<i class="bi bi-x-circle text-danger"></i> Reportado'
                                                                : `
                                                                    <button class="btn btn-success btn-sm" 
                                                                        onclick="validarTactico(${tactico.id_packs}, '${gestor.nombre}', '${pdv.nombre}', '${tactico.nombre}', ${tactico.cantidad_armada})">
                                                                        Validar
                                                                    </button>
                                                                    <button class="btn btn-danger btn-sm" 
                                                                                onclick="reportarTactico(
                                                                                '${tactico.id_packs}', 
                                                                                '${gestor.nombre}', 
                                                                                '${pdv.nombre}', 
                                                                                '${tactico.nombre}', 
                                                                                '${tactico.cantidad_encontrada}', 
                                                                                '${tactico.cantidad_armada}', 
                                                                                '${tactico.foto_armado}', 
                                                                                '${fotoGuia}'
                                                                            )">
                                                                        Reportar
                                                                    </button>
                                                                `}
                                                        </td>
                                                    </tr>
                                                `).join("")}
                                            </tbody>
                                        </table>
                                    `;
                                }).join("")}
                            </div>
                        </div>
                    `;

                    container.innerHTML += gestorCard;
                });
            }


            function setVerticalImageOrientation(imageElement) {
                const img = new Image();
                img.src = imageElement.src;
                img.onload = function () {
                    if (img.width < img.height) {
                        // Si la imagen es horizontal, rotarla
                        imageElement.style.transform = "rotate(270deg)";
                    } else {
                        // Resetear cualquier rotación previa
                        imageElement.style.transform = "none";
                    }
                };
            }

            function showImageModal(imageSrc, cantidadEncontrada, cantidadArmada) {
                const imageElement = document.getElementById('imageModalSrc');
                imageElement.src = imageSrc;

                // Verificar y ajustar la orientación de la imagen
                setVerticalImageOrientation(imageElement);

                document.getElementById('imageCantidadEncontrada').textContent = cantidadEncontrada;
                document.getElementById('imageCantidadArmada').textContent = cantidadArmada;
                new bootstrap.Modal(document.getElementById('imageModal')).show();
            }

            function togglePdvDetails(index) {
                const pdvDetails = document.getElementById(`pdvs${index}`);
                const arrow = document.getElementById(`arrow-${index}`);

                if (pdvDetails.classList.contains("show")) {
                    pdvDetails.classList.remove("show");
                    arrow.classList.add("collapsed");
                } else {
                    document.querySelectorAll(".collapse").forEach(el => el.classList.remove("show"));
                    document.querySelectorAll(".toggle-arrow").forEach(el => el.classList.add("collapsed"));
                    pdvDetails.classList.add("show");
                    arrow.classList.remove("collapsed");
                }
            }

            function searchGestores() {
                const filter = document.getElementById("search-input").value.toLowerCase();
                const cards = document.querySelectorAll(".gestor-card");
                cards.forEach(card => {
                    const text = card.querySelector(".gestor-header span").textContent.toLowerCase();
                    card.style.display = text.includes(filter) ? "" : "none";
                });
            }
    </script>

    <!-- Tab de BASE -->
   <!-- Tab de BASE -->
<div class="tab-pane fade" id="base" role="tabpanel" aria-labelledby="base-tab">
    <h3 class="my-4 table-title">Base de Actividades</h3>

    <!-- Loader -->
    <div id="baseLoader" class="text-center mb-4" style="display:none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <p>Cargando datos...</p>
    </div>

    <!-- Contenedor de la tabla (sin botón personalizado) -->
    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="baseTable">
            <thead class="thead-dark">
                <tr>
                    <!-- Mismas columnas que antes -->
                    <th>Fecha</th>
                    <th>Código</th>
                    <th>Local</th>
                    <th>Región</th>
                    <th>Provincia</th>
                    <th>Ciudad</th>
                    <th>Supervisor</th>
                    <th>Mercaderista</th>
                    <th>Categoría 1</th>
                    <th>Subcategoría 1</th>
                    <th>Marca 1</th>
                    <th>Tamaño</th>
                    <th>SKU 1</th>
                    <th>Marca 2</th>
                    <th>Categoría 2</th>
                    <th>SKU 2</th>
                    <th>Cantidad Encontrada</th>
                    <th>Cantidad Armada</th>
                    <th>Foto Guía</th>
                    <th>Foto Armado</th>
                </tr>
            </thead>
            <tbody id="baseTableBody">
                <!-- Datos dinámicos -->
            </tbody>
        </table>
    </div>
</div>

<!-- Asegurar que tienes estas librerías en tu head -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">

<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>

<script>
    // Cargar datos al inicio
    document.addEventListener('DOMContentLoaded', () => {
        cargarBaseData();
    });

    function cargarBaseData() {
        document.getElementById('baseLoader').style.display = 'block';

        fetch('../get_base_data.php')
            .then(response => {
                if (!response.ok) throw new Error(`Error al obtener datos: ${response.status}`);
                return response.json();
            })
            .then(json => {
                document.getElementById('baseLoader').style.display = 'none';
                if (json.success) {
                    renderBaseTable(json.data);
                    initDataTables(); // Inicializa DataTables con los datos
                } else {
                    console.error('Error del servidor:', json.message || 'Error desconocido');
                }
            })
            .catch(error => {
                document.getElementById('baseLoader').style.display = 'none';
                console.error('Error al cargar la base:', error);
            });
    }

    function renderBaseTable(dataArray) {
        const tbody = document.getElementById('baseTableBody');
        tbody.innerHTML = '';

        dataArray.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.fecha}</td>
                <td>${item.codigo}</td>
                <td>${item.local}</td>
                <td>${item.region}</td>
                <td>${item.provincia}</td>
                <td>${item.ciudad}</td>
                <td>${item.supervisor}</td>
                <td>${item.mercaderista}</td>
                <td>${item.categoria_1}</td>
                <td>${item.subcategoria_1}</td>
                <td>${item.marca_1}</td>
                <td>${item.tamano}</td>
                <td>${item.sku_1}</td>
                <td>${item.marca_2}</td>
                <td>${item.categoria_2}</td>
                <td>${item.sku_2}</td>
                <td>${item.cantidad_encontrada}</td>
                <td>${item.cantidad_armada}</td>
                <td><a href="${item.foto_guia}" target="_blank">Ver Guía</a></td>
                <td><a href="${item.foto_armado}" target="_blank">Ver Armado</a></td>
            `;
            tbody.appendChild(tr);
        });
    }

    function initDataTables() {
    if ($.fn.DataTable.isDataTable('#baseTable')) {
        $('#baseTable').DataTable().destroy();
    }
    
    $('#baseTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                text: 'Descargar Excel',
                className: 'btn-success',
                title: 'Base de Actividades',
                exportOptions: {
                    // Ajuste clave: Incluir todas las columnas
                    columns: ':not(:last-child)',  // O eliminar esta línea si es necesario
                    format: {
                        body: function(data, row, column, node) {
                            // Columnas 18 y 19 son las últimas (Foto Guía y Foto Armado)
                            if (column === 18 || column === 19) { 
                                const link = $(node).find('a').attr('href');
                                return link ? link : ''; 
                            }
                            // Formato para fecha (columna 0)
                            if (column === 0) { 
                                const [day, month, year] = data.split('/');
                                return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
                            }
                            return data;
                        }
                    }
                }
            }
        ],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        ordering: true,
        searching: true,
        language: { 
            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json',
            buttons: { excel: 'Excel' } 
        }
    });
}
</script>


    <!-- Tab de Inspección -->
    <div class="tab-pane fade" id="inspeccion" role="tabpanel" aria-labelledby="inspeccion-tab">
        <h3 class="my-4 table-title">Inspección de Tácticos</h3>
        
        <!-- Contadores de registros -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Registros Devueltos</h5>
                        <p class="card-text display-4" id="contadorDevueltos">0</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Registros Validados</h5>
                        <p class="card-text display-4" id="contadorValidados">0</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Registros Devueltos -->
        <h4>Registros Devueltos</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Gestor</th>
                    <th>PDV</th>
                    <th>Motivo de Devolución</th>
                    <th>Fecha de Devolución</th>
                </tr>
            </thead>
            <tbody id="reportesTableBody">
                <!-- Se llenará dinámicamente -->
            </tbody>
        </table>

        <!-- Tabla de Registros Validados -->
        <h4 class="mt-5">Registros Validados</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Gestor</th>
                    <th>PDV</th>
                    <th>Cantidad Armada</th>
                    <th>Fecha de Validación</th>
                </tr>
            </thead>
            <tbody id="validacionesTableBody">
                <!-- Se llenará dinámicamente -->
            </tbody>
        </table>
    </div>

    <!-- Tab de CORREGIDOS -->
    <div class="tab-pane fade" id="corregidos" role="tabpanel" aria-labelledby="corregidos-tab">
        <h3 class="my-4  table-title">Registros Corregidos</h3>
        <div id="corregidos-container"></div>
    </div>

    <script>
        function renderCorregidos(gestores) {
            const container = document.getElementById("corregidos-container");
            container.innerHTML = ""; // Limpiar contenido previo

            // Si no hay datos, mostrar mensaje
            if (!gestores || gestores.length === 0) {
                container.innerHTML = "<p class='text-center'>No hay registros corregidos disponibles para este mes.</p>";
                return;
            }

            // Recorrer los mercaderistas
            gestores.forEach((gestor) => {
                const gestorContainer = document.createElement("div");
                gestorContainer.classList.add("gestor-container");

                // Título del mercaderista
                const gestorTitle = document.createElement("h4");
                gestorTitle.textContent = `Mercaderista: ${gestor.nombre}`;
                gestorContainer.appendChild(gestorTitle);

                // Recorrer los PDVs del mercaderista
                gestor.pdvs.forEach((pdv) => {
                    const pdvTitle = document.createElement("h5");
                    pdvTitle.textContent = `PDV: ${pdv.nombre}`;
                    gestorContainer.appendChild(pdvTitle);

                    // Crear tabla para los tácticos
                    const table = document.createElement("table");
                    table.classList.add("table", "table-striped", "mt-3");

                    table.innerHTML = `
                        <thead>
                            <tr>
                                <th>Foto Guía</th>
                                <th>Producto Primario</th>
                                <th>Táctico</th>
                                <th>Cantidad Encontrada</th>
                                <th>Cantidad Armada</th>
                                <th>Foto Armado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${pdv.tacticos
                                .map(
                                    (tactico) => `
                                    <tr 
                                        data-gestor="${gestor.nombre}" 
                                        data-pdv="${pdv.nombre}" 
                                        data-tactico="${tactico.nombre}"
                                        data-idpacks="${tactico.id_packs}">
                                        <td>
                                            <img src="${tactico.foto_guia}" class="img-thumbnail" style="width: 80px; cursor: pointer;" onclick="showImageModal('${tactico.foto_guia}')">
                                        </td>
                                        <td>${tactico.primario}</td>
                                        <td>${tactico.nombre}</td>
                                        <td>${tactico.cantidad_encontrada || 0}</td>
                                        <td>${tactico.cantidad_armada || 0}</td>
                                        <td>
                                            <img src="${tactico.foto_armado}" class="img-thumbnail" style="width: 80px; cursor: pointer;" onclick="showImageModal('${tactico.foto_armado}')">
                                        </td>
                                        <td>
                                            <button class="btn btn-success btn-sm" 
                                                onclick="validarTactico('${tactico.id_packs}', '${gestor.nombre}', '${pdv.nombre}', '${tactico.nombre}', ${tactico.cantidad_armada})">
                                                Validar
                                            </button>
                                            <button class="btn btn-danger btn-sm" 
                                                onclick="reportarTactico('${tactico.id_packs}', '${gestor.nombre}', '${pdv.nombre}', '${tactico.nombre}', '${tactico.cantidad_encontrada}', '${tactico.cantidad_armada}', '${tactico.foto_armado}', '${tactico.foto_guia}')">
                                                Reportar
                                            </button>
                                        </td>
                                    </tr>
                                `
                                )
                                .join("")}
                        </tbody>
                    `;

                    gestorContainer.appendChild(table);
                });

                container.appendChild(gestorContainer);
            });
        }

        // Llamada al web service
        fetch("../get_registros_corregidos.php")
            .then((response) => {
                if (!response.ok) {
                    throw new Error("Error en el servidor");
                }
                return response.json();
            })
            .then((response) => {
                if (response.success && response.data) {
                    console.log("Datos recibidos:", response.data); // Verificar los datos

                    // Procesar los datos para agruparlos por mercaderista y PDV
                    const groupedData = {};
                    response.data.forEach((item) => {
                        const { mercaderista, pdv, tactico, ...rest } = item;

                        if (!groupedData[mercaderista]) {
                            groupedData[mercaderista] = {
                                nombre: mercaderista,
                                pdvs: []
                            };
                        }

                        const pdvIndex = groupedData[mercaderista].pdvs.findIndex(
                            (pdvItem) => pdvItem.nombre === pdv
                        );

                        if (pdvIndex === -1) {
                            groupedData[mercaderista].pdvs.push({
                                nombre: pdv,
                                tacticos: [
                                    {
                                        nombre: tactico,
                                        ...rest
                                    }
                                ]
                            });
                        } else {
                            groupedData[mercaderista].pdvs[pdvIndex].tacticos.push({
                                nombre: tactico,
                                ...rest
                            });
                        }
                    });

                    // Convertir el objeto agrupado en un array
                    const processedData = Object.values(groupedData);

                    renderCorregidos(processedData); // Renderizar los datos procesados
                } else {
                    console.error("No se encontraron registros corregidos.");
                    document.getElementById("corregidos-container").innerHTML =
                        "<p>No hay registros corregidos disponibles.</p>";
                }
            })
            .catch((error) => {
                console.error("Error al cargar los registros corregidos:", error);
        });
    </script>




    <!-- Contenido del Tab Trimestral -->
    <div class="tab-pane fade" id="trimestral" role="tabpanel" aria-labelledby="trimestral-tab">
        <div class="d-flex justify-content-between align-items-center">
            <h3 class="my-4">Seguimiento Trimestral</h3>
        </div>

        <!-- Filtro de mes -->
        <div class="mb-4">
            <div class="row">
                <div class="col-md-3">
                    <label for="mesTrimestral" class="form-label">Seleccionar Mes</label>
                    <input type="month" id="mesTrimestral" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary" onclick="aplicarFiltroTrimestral()">Aplicar</button>
                </div>
            </div>
        </div>

        <!-- Tablas de avance (inicialmente ocultas) -->
        <div id="avanceTrimestral" style="display: none;">
            <h4 class="table-title">Avance por Región</h4>
            <table class="table table-bordered table-hover" id="avanceRegionTrimestralTable">
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
                <tbody id="avanceRegionTrimestralTableBody">
                </tbody>
            </table>

            <h4 class="table-title">Avance por Ejecutivo</h4>
            <table class="table table-bordered table-hover" id="avanceEjecutivoTrimestralTable">
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
                <tbody id="avanceEjecutivoTrimestralTableBody">
                </tbody>
            </table>

            <h4 class="table-title">Avance por Mercaderista</h4>
            <div id="spinner-mercaderistas-trimestral" class="text-center my-3" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2">Cargando datos, por favor espera...</p>
            </div>
            <table class="table table-bordered table-hover" id="avanceMercaderistaTrimestralTable">
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
                <tbody id="avanceMercaderistaTrimestralTableBody">
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Función para mostrar errores con SweetAlert
        function showError(message) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message,
                confirmButtonText: 'Aceptar'
            });
        }

        // Función para obtener el mes seleccionado
        function getSelectedMonth() {
            const monthSelector = document.getElementById("mesTrimestral");
            if (!monthSelector || !monthSelector.value) {
                showError("Por favor, selecciona un mes.");
                return null;
            }
            const [year, month] = monthSelector.value.split('-');
            return { year: parseInt(year), month: parseInt(month) };
        }

        // Función para aplicar el filtro trimestral
        function aplicarFiltroTrimestral() {
            const selectedMonth = getSelectedMonth();
            if (!selectedMonth) return;

            // Mostrar el contenedor de avance trimestral
            document.getElementById("avanceTrimestral").style.display = "block";

            // Mostrar spinner de carga
            document.getElementById("spinner-mercaderistas-trimestral").style.display = "block";

            // Llamar al web service unificado
            fetch(`../get_avance_unificado.php?month=${selectedMonth.month}&year=${selectedMonth.year}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error("Error al obtener los datos");
                    }
                    return response.json();
                })
                .then(data => {
                    // Ocultar spinner
                    document.getElementById("spinner-mercaderistas-trimestral").style.display = "none";

                    // Verificar si la respuesta es exitosa
                    if (data.success) {
                        // Renderizar las tablas con los datos recibidos
                        renderAvanceRegionTableTri(data.data.regional);
                        renderAvanceEjecutivoTableTri(data.data.ejecutivo);
                        renderAvanceMercaderistaTableTri(data.data.mercaderista);
                    } else {
                        showError(data.message || "Error al cargar los datos.");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    showError("No se pudo cargar la información. Inténtalo de nuevo más tarde.");
                    document.getElementById("spinner-mercaderistas-trimestral").style.display = "none";
                });
        }

        // Función para renderizar la tabla de avance regional
        function renderAvanceRegionTableTri(data) {
            const tableBody = document.getElementById("avanceRegionTrimestralTableBody");
            if (!tableBody) return;
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
                        <div id="regionProgressContainerTri-${index}" style="width:100%; height:20px;"></div>
                    </td>
                `;

                tableBody.appendChild(tr);

                // Crear la barra de progreso
                const container = document.getElementById(`regionProgressContainerTri-${index}`);
                if (container) {
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
                }
            });
        }

                // Función para renderizar la tabla de avance ejecutivo
                function renderAvanceEjecutivoTableTri(data) {
                    const tableBody = document.getElementById("avanceEjecutivoTrimestralTableBody");
                    if (!tableBody) return;
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

        // Función para renderizar la tabla de avance mercaderista
        function renderAvanceMercaderistaTableTri(data) {
            const tableBody = document.getElementById("avanceMercaderistaTrimestralTableBody");
            if (!tableBody) return;
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
</script>






    <!-- JS-->

    <script>
    

        // Función de búsqueda de gestores
        function searchGestores() {
            const input = document.getElementById("search-input");
            const filter = input.value.toLowerCase();
            const gestorCards = document.querySelectorAll(".gestor-card");
            
            gestorCards.forEach(card => {
                const nombreGestor = card.querySelector(".gestor-header span").textContent.toLowerCase();
                if (nombreGestor.includes(filter)) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        }

        function updateTotalProgress(data) {
            const totalDistribuido = data.reduce((sum, row) => sum + (row.cantidad_distribuida || 0), 0);
            const totalArmado = data.reduce((sum, row) => sum + (row.cantidad_armada || 0), 0);

            const porcentajeAvance = totalDistribuido
                ? ((totalArmado / totalDistribuido) * 100).toFixed(1)
                : 0;

            const progressBar = document.getElementById("totalProgressBar");
            progressBar.style.width = `${porcentajeAvance}%`;
            progressBar.setAttribute("aria-valuenow", porcentajeAvance);
            progressBar.textContent = `${porcentajeAvance}%`;
        }

        document.addEventListener("DOMContentLoaded", () => {
        fetch('../get_avance_tacticos.php')
            .then(response => response.json())
            .then(data => renderTacticoChart(data))
            .catch(error => console.error("Error al cargar los datos para el gráfico:", error));
        });

        function renderTacticoChart(data) {
            const tacticoLabels = data.map(row => row.tactico);
            const distribuidos = data.map(row => row.cantidad_distribuida || 0);
            const armados = data.map(row => row.cantidad_armada || 0);

            const ctx = document.getElementById('avanceTacticoChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: tacticoLabels,
                    datasets: [
                        {
                            label: 'Cantidad Distribuida',
                            data: distribuidos,
                            backgroundColor: 'rgba(255, 245, 9, 0.8)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Cantidad Armada',
                            data: armados,
                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Tácticos'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Cantidad'
                            }
                        }
                    }
                }
            });
        }
        function validarTactico(id_packs, gestor, pdv, tactico, cantidadArmada) {
    Swal.fire({
        title: "¿Estás seguro?",
        text: `¿Deseas validar el táctico "${tactico}"?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#28a745",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, validar",
        cancelButtonText: "Cancelar",
    }).then((result) => {
        if (result.isConfirmed) {
            // Llamada al web service
            fetch("../validar_tactico.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    id_packs: id_packs,            
                    gestor: gestor,
                    pdv: pdv,
                    tactico: tactico,
                    cantidad_armada: cantidadArmada
                }),
            })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Validación Exitosa",
                        text: "El táctico ha sido validado correctamente.",
                    });
                    marcarValidado(id_packs); // Actualizar solo la fila correspondiente
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Hubo un problema al validar el táctico.",
                    });
                }
            })
            .catch((error) => {
                console.error("Error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "No se pudo completar la validación.",
                });
            });
        }
    });
}

function marcarValidado(id_packs) {
    const row = document.querySelector(`tr[data-idpacks="${id_packs}"]`);
    if (row) {
        row.style.backgroundColor = "#d4edda";
        row.style.color = "#155724";
        const actionCell = row.querySelector("td:last-child");
        actionCell.innerHTML = '<i class="bi bi-check-circle text-success"></i> Validado';
    }
}


        let currentReportData = {};

        function reportarTactico(idPacks, gestor, pdv, tactico, cantidadEncontrada, cantidadArmada, fotoArmado, fotoGuia) {
        currentReportData = {
            id_packs: idPacks,
            gestor: gestor,
            pdv: pdv,
            tactico: tactico,
            cantidad_encontrada: cantidadEncontrada,
            cantidad_armada: cantidadArmada,
            foto_armado: fotoArmado,
            foto_guia: fotoGuia
        };
        console.log(currentReportData); // Verificar los datos antes de mostrar el modal
        new bootstrap.Modal(document.getElementById("reportModal")).show(); // Mostrar modal
        }


        document.getElementById("submitReport").addEventListener("click", () => {
            const observation = document.getElementById("reportObservation").value.trim();
            
            if (!observation) {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Debe escribir una observación para el reporte.",
                });
                return;
            }

            const payload = {
                ...currentReportData,
                observation,
            };
            console.log("Datos enviados al web service:", payload);


            fetch("../reporte_tactico.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload),
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Reporte Registrado",
                            text: "El reporte se ha registrado correctamente.",
                        });
                        marcarReportado(currentReportData.gestor, currentReportData.pdv, currentReportData.tactico);
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Hubo un problema al registrar el reporte.",
                        });
                    }
                })
                .catch((error) => {
                    console.error("Error:", error);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "No se pudo completar el reporte.",
                    });
                });

            const reportModal = bootstrap.Modal.getInstance(document.getElementById("reportModal"));
            reportModal.hide(); // Cerrar modal
        });

        function marcarReportado(gestor, pdv, tactico) {
            const row = document.querySelector(
                `tr[data-gestor="${gestor}"][data-pdv="${pdv}"][data-tactico="${tactico}"]`
            );
            if (row) {
                row.style.backgroundColor = "#f8d7da";
                row.style.color = "#842029";
                const actionCell = row.querySelector("td:last-child");
                actionCell.innerHTML = '<i class="bi bi-x-circle text-danger"></i> Reportado';
            }
        }

            document.addEventListener("DOMContentLoaded", () => {
                fetch("../get_inspeccion_data.php")
                    .then(response => {
                        if (!response.ok) {
                            throw new Error("Error al obtener los datos");
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            if (data.reportes) {
                                populateReportesTable(data.reportes);
                            } else {
                                console.warn("No hay reportes disponibles.");
                                document.getElementById("reportesTableBody").innerHTML = `<tr><td colspan="5" class="text-center">No hay registros devueltos para este mes.</td></tr>`;
                            }

                            if (data.validaciones) {
                                populateValidacionesTable(data.validaciones);
                            } else {
                                console.warn("No hay validaciones disponibles.");
                                document.getElementById("validacionesTableBody").innerHTML = `<tr><td colspan="5" class="text-center">No hay registros validados para este mes.</td></tr>`;
                            }
                        } else {
                            console.error("Error al obtener los datos:", data.message);
                        }
                    })
                    .catch(error => console.error("Error:", error));
            });

            function populateReportesTable(reportes) {
                const tableBody = document.getElementById("reportesTableBody");
                tableBody.innerHTML = "";

                document.getElementById("contadorDevueltos").textContent = reportes.length;

                reportes.forEach(reporte => {
                    const tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td>${reporte.tactico}</td>
                        <td>${reporte.gestor}</td>
                        <td>${reporte.pdv}</td>
                        <td>${reporte.observacion || "Sin observaciones"}</td>
                        <td>${reporte.fecha_creacion}</td>
                    `;
                    tableBody.appendChild(tr);
                });
            }

            function populateValidacionesTable(validaciones) {
                const tableBody = document.getElementById("validacionesTableBody");
                tableBody.innerHTML = "";

                document.getElementById("contadorValidados").textContent = validaciones.length;

                validaciones.forEach(validacion => {
                    const tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td>${validacion.tactico}</td>
                        <td>${validacion.gestor}</td>
                        <td>${validacion.pdv}</td>
                        <td>${validacion.cantidad_armada}</td>
                        <td>${validacion.fecha_creacion}</td>
                    `;
                    tableBody.appendChild(tr);
                });
            }

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
        function updateTotalProgressBar(data) {
            // 1. Sumar todas las "cantidades distribuidas" y "cantidades armadas"
            const totalDistribuido = data.reduce((sum, registro) => sum + (parseFloat(registro.cantidad_distribuida) || 0), 0);
            const totalArmado = data.reduce((sum, registro) => sum + (parseFloat(registro.cantidad_armada) || 0), 0);

            // 2. Calcular el porcentaje de avance
            const porcentajeAvance = totalDistribuido > 0
                ? ((totalArmado / totalDistribuido) * 100).toFixed(1)
                : 0;

            // 3. Actualizar la barra de progreso
            const progressBar = document.getElementById("totalProgressBar");
            progressBar.style.width = `${porcentajeAvance}%`;
            progressBar.setAttribute("aria-valuenow", porcentajeAvance);
            progressBar.textContent = `${porcentajeAvance}%`;

            // 4. Cambiar color según el rango
            progressBar.classList.remove("bg-danger", "bg-warning", "bg-success");

            if (porcentajeAvance <= 40) {
                progressBar.classList.add("bg-danger");
            } else if (porcentajeAvance <= 70) {
                progressBar.classList.add("bg-warning");
            } else {
                progressBar.classList.add("bg-success");
            }

            console.log(`Total Distribuido: ${totalDistribuido}, Total Armado: ${totalArmado}, % Avance: ${porcentajeAvance}%`);
        }





    </script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>