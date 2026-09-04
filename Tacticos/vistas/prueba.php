<?php
$pageTitle = "Seguimiento Tácticos";
ob_start();
$currentDate = date("d-m-Y", strtotime("-1 day"));
?> 

<!-- Bootstrap CDN JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>


<style>
    .kpi-card {
        display: inline-block;
        background-color: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 15px;
        margin: 10px;
        width: 200px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    .kpi-card h4 {
        margin: 5px 0;
        font-size: 1.2em;
        color: #5a3d8a;
    }
    .progress {
        height: 20px;
    }
    .progress-bar {
        font-size: 0.75rem;
    }
    .table-title {
        background-color: rgba(200, 150, 255, 0.2);
        color: #5a3d8a;
        font-weight: bold;
        text-align: center;
        padding: 10px;
        border-radius: 5px;
        margin-top: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .month-selector {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }
    .month-button {
        padding: 8px 14px;
        background-color: #f1f1f1;
        border: 1px solid #ccc;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .month-button.active {
        background-color: #007bff;
        color: white;
        font-weight: bold;
    }
    table td, table th {
        text-align: center;
        vertical-align: middle;
    }
    table td {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .card.text-center {
        border-radius: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .card-title {
        font-weight: 600;
        color: #5a3d8a;
    }

    .display-4 {
        font-size: 2.5rem;
        font-weight: 700;
        color: #333;
    }

    .table {
        border-radius: 0.5rem;
        overflow: hidden;
    }

    .table th {
        background-color: #f8f9fa;
        color: #333;
        font-weight: 600;
    }

    .table td, .table th {
        vertical-align: middle;
        padding: 0.75rem;
    }
    thead th {
        background: #002060;
        color: #fff;
        text-align: center;
    }

</style>

<div class="row mb-3">
    <div class="col-md-3">
        <label for="tipoTactico">Tipo de Táctico:</label>
        <select class="form-control" id="tipoTactico" onchange="cargarAvances()">
            <option value="false">Normales</option>
            <option value="true">Adicionales</option>
        </select>
    </div>
</div>

<ul class="nav nav-tabs" id="tabSeguimiento" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="avance-tab" data-bs-toggle="tab" data-bs-target="#avance" type="button" role="tab">Avances</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="gestores-tab" data-bs-toggle="tab" data-bs-target="#gestores" type="button" role="tab">Gestores</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="inspeccion-tab" data-bs-toggle="tab" data-bs-target="#inspeccion" type="button" role="tab">Inspección</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="corregidos-tab" data-bs-toggle="tab" data-bs-target="#corregidos" type="button" role="tab">Corregidos</button>
    </li>
</ul>

<div class="tab-content pt-3" id="tabContent">
    <div class="tab-pane fade show active" id="avance" role="tabpanel">
        <div class="row mb-3">
            <div class="col-md-8">
                <label class="form-label">Seleccionar Mes</label>
                <div class="month-selector" id="monthSelector">
                    <?php
                    $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                    foreach($meses as $i => $nombre) {
                        echo "<div class='month-button' data-mes='".($i+1)."'>$nombre</div>";
                    }
                    ?>
                </div>
            </div>
            <div class="col-md-3">
                <label for="anioSelect" class="form-label">Seleccionar Año</label>
                <select id="anioSelect" class="form-control" onchange="cargarAvances()">
                    <?php for($y = date('Y'); $y >= 2023; $y--): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        <div class="col-md-1 d-flex flex-column align-items-end">
            <button class="btn btn-success mb-2 w-100" onclick="descargarExcel()">Descargar Excel</button>
            <button class="btn btn-primary w-100" onclick="cargarAvances()" title="Refrescar avances">
                <i class="bi bi-arrow-clockwise"></i> Refrescar
            </button>    
        </div>
        </div>
        <div id="kpiContainer" class="d-flex justify-content-center flex-wrap"></div>
        <div id="tablaAvances"></div>
    </div>
    <div class="tab-pane fade" id="gestores" role="tabpanel">
        <h3 class="my-4 table-title">Progreso de Tácticos por Gestores</h3>
         <h6 class="my-4 text-center">
            Revisión de Actividades - <?php echo $currentDate; ?>
            <span class="badge bg-warning text-dark"> A día vencido</span>
        </h6>
        <div class="input-group mb-4">
            <input type="text" class="form-control" placeholder="Buscar gestor" id="search-input" onkeyup="searchGestores()">
                <input 
                    type="date" 
                    id="fecha_registro" 
                    class="form-control"
                    value="<?php echo date('Y-m-d', strtotime('-1 day')); ?>"
                    max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>"
                >

                <button style="background-color: #004b8d; border-radius: 5px; border-color: #004b8d; color:white" type="button" onclick="buscarActividadesPorDia()">
                    <i class="bi bi-search"></i>
                </button>
        </div>

        <div id="fechaRevisionContainer" class="mb-3">
            <span>Fecha de revisión: <strong id="fechaRevision">-</strong></span>
            <span class="ms-3" id="registroCounter"></span>
        </div>
        <div id="gestor-container"></div>
    </div>

    <div class="tab-pane fade" id="inspeccion" role="tabpanel">
        <!-- KPIs -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Reportados</h5>
                        <p class="card-text display-4" id="contadorDevueltos">0</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Validados</h5>
                        <p class="card-text display-4" id="contadorValidados">0</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Corregidos</h5>
                        <p class="card-text display-4" id="contadorCorrejidos">0</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tablas -->
        <h5 class="mt-4">Registros Reportados</h5>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Táctico</th>
                    <th>Gestor</th>
                    <th>PDV</th>
                    <th>Motivo</th>
                    <th>Fecha</th>
                    <th>Fecha Reportado</th>
                </tr>
            </thead>
            <tbody id="reportesTableBody"></tbody>
        </table>

        <h5 class="mt-4">Registros Validados</h5>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Táctico</th>
                    <th>Gestor</th>
                    <th>PDV</th>
                    <th>Cantidad Armada</th>
                    <th>Fecha</th>
                    <th>Fecha Validado</th>
                </tr>
            </thead>
            <tbody id="validacionesTableBody"></tbody>
        </table>

        <h5 class="mt-4">Registros Corregidos</h5>
        <table class="table table-bordered">
            <thead><tr><th>Táctico</th><th>Gestor</th><th>PDV</th><th>Fecha</th></tr></thead>
            <tbody id="correjidosTableBody"></tbody>
        </table>

    </div>
    <div class="tab-pane fade" id="corregidos" role="tabpanel">
         <h3 class="my-4 table-title">Registros Corregidos</h3>
        <div id="corregidos-container"></div>
    </div>
</div>

<script>
    
    document.getElementById("inspeccion-tab")?.addEventListener("click", () => {
        cargarInspeccion();
    });

    document.getElementById("corregidos-tab")?.addEventListener("click", () => {
        cargarCorregidos();
    });


    // TAB AVANCES JS
    let mesSeleccionado = new Date().getMonth() + 1;
    let anioSeleccionado = new Date().getFullYear();
    document.getElementById('anioSelect').value = anioSeleccionado;

    function cambiarAnio() {
        console.log("Cambiando año...");
        anioSeleccionado = document.getElementById('anioSelect').value;
        cargarAvances();
    }

    function descargarExcel() {
        const wb = XLSX.utils.book_new();

        [
            { id: "avanceRegionTable", name: "Avance por Región" },
            { id: "avanceEjecutivoTable", name: "Avance por Ejecutivo" },
            { id: "avanceMercaderistaTable", name: "Avance por Mercaderista" }
        ].forEach(cfg => {
            const table = document.getElementById(cfg.id);
            if (!table) return;

            // Toma la tabla HTML tal como está con estilos visibles
            const ws = XLSX.utils.table_to_sheet(table);
            XLSX.utils.book_append_sheet(wb, ws, cfg.name);
        });

        XLSX.writeFile(wb, "reporte_avance.xlsx");
    }


    function cargarAvances() {
        const tipo = document.getElementById('tipoTactico').value;
        if (!mesSeleccionado || !anioSeleccionado) return;

        anioSeleccionado = document.getElementById('anioSelect').value;
        console.log(`Cargando avances para mes ${mesSeleccionado}, año ${anioSeleccionado}, tipo adicional: ${tipo}`);

        mostrarCargando();

        fetch(`../get_avances.php?es_adicional=${tipo}&mes=${mesSeleccionado}&anio=${anioSeleccionado}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderKPIs(data.data);
                    renderTablaAvances(data.data);
                } else {
                    alert("Error al obtener datos");
                }
            }).catch(err => {
                console.error("Error:", err);
                alert("Error de conexión con el servidor");
            });

         const tabActivo = document.querySelector(".nav-link.active")?.id;

            switch (tabActivo) {
                case 'gestores-tab':
                    cargarGestores();
                    break;
                case 'inspeccion-tab':
                    if (typeof cargarInspeccion === 'function') cargarInspeccion();
                    break;
                case 'corregidos-tab':
                    if (typeof cargarCorregidos === 'function') cargarCorregidos();
                    break;
            }
    }

    function mostrarCargando() {
        document.getElementById('kpiContainer').innerHTML = `
            <div class='kpi-card skeleton'></div>
            <div class='kpi-card skeleton'></div>
            <div class='kpi-card skeleton'></div>
            <div class='kpi-card skeleton'></div>
        `;
        document.getElementById('tablaAvances').innerHTML = '<p class="text-center">Cargando datos...</p>';
    }

    function renderKPIs(data) {
        const resumen = {
            regional: { distribuida: 0, armada: 0, recursos: 0 },
            ejecutivo: { distribuida: 0, armada: 0, recursos: 0 },
            mercaderista: { distribuida: 0, armada: 0, recursos: 0 }
        };

        for (const tipo in data) {
            data[tipo].forEach(row => {
                resumen[tipo].distribuida += parseFloat(row.cantidad_distribuida || 0);
                resumen[tipo].armada += parseFloat(row.cantidad_armada || 0);
                 if (tipo === 'mercaderista') {
                    resumen[tipo].recursos += 1;
                } else {
                    resumen[tipo].recursos += parseFloat(row.cantidad_recursos || 0);
                }
            });
        }
        console.log("Comparación resumen:");
        console.table(resumen);


        const iguales =
            resumen.regional.distribuida === resumen.ejecutivo.distribuida &&
            resumen.ejecutivo.distribuida === resumen.mercaderista.distribuida &&
            resumen.regional.armada === resumen.ejecutivo.armada &&
            resumen.ejecutivo.armada === resumen.mercaderista.armada &&
            resumen.regional.recursos === resumen.ejecutivo.recursos &&
            resumen.ejecutivo.recursos === resumen.mercaderista.recursos;

        const color = iguales ? "#5a3d8a" : "red";
        const avance = resumen.regional.distribuida === 0 ? 0 :
            ((resumen.regional.armada / resumen.regional.distribuida) * 100).toFixed(2);

        document.getElementById('kpiContainer').innerHTML = `
            <div class='kpi-card' style="color:${color}"><h4>Total Distribuido</h4><p>${resumen.regional.distribuida}</p></div>
            <div class='kpi-card' style="color:${color}"><h4>Total Armado</h4><p>${resumen.regional.armada}</p></div>
            <div class='kpi-card' style="color:${color}"><h4>Avance Global %</h4><p>${avance}%</p></div>
            <div class='kpi-card' style="color:${color}"><h4>Recursos Totales</h4><p>${resumen.regional.recursos}</p></div>
        `;
    }

    function getProgressBar(avance) {
        const value = parseFloat(avance);
        const color = value >= 80 ? 'bg-success' : value >= 50 ? 'bg-warning' : 'bg-danger';
        return `<div class='progress'><div class='progress-bar ${color}' style='width:${value}%;'>${value}%</div></div>`;
    }

    function renderTablaAvances(data) {
        const container = document.getElementById('tablaAvances');
        container.innerHTML = '';

        for (const tipo in data) {
            const grupo = data[tipo];
            if (!grupo.length) continue;

            // Calcular avance por fila si no está calculado
            grupo.forEach(row => {
                if (row.avance === undefined || row.avance === null) {
                    const dist = parseFloat(row.cantidad_distribuida || 0);
                    const arm = parseFloat(row.cantidad_armada || 0);
                    row.avance = dist === 0 ? 0 : ((arm / dist) * 100).toFixed(2);
                }
            });

            let html = `<div class='table-title'>${tipo.toUpperCase()}</div>`;
            let tableId = tipo === 'regional' ? 'avanceRegionTable' :
              tipo === 'ejecutivo' ? 'avanceEjecutivoTable' :
              tipo === 'mercaderista' ? 'avanceMercaderistaTable' : '';

            html += `<div class="table-responsive"><table class="table table-bordered table-hover table-striped" id="${tableId}"><thead><tr>`;
            const headers = Object.keys(grupo[0]);
            headers.forEach(h => html += `<th>${h.replace('_', ' ').toUpperCase()}</th>`);
            html += '</tr></thead><tbody>';

            if (tipo === 'ejecutivo' || tipo === 'mercaderista') {
                const campoAgrupado = tipo === 'ejecutivo' ? 'jefatura' : 'supervisor';
                let actual = null;
                grupo.forEach((row, index) => {
                    html += '<tr>';
                    if (row[campoAgrupado] !== actual) {
                        const rowspan = grupo.filter(r => r[campoAgrupado] === row[campoAgrupado]).length;
                        html += `<td rowspan="${rowspan}">${row[campoAgrupado]}</td>`;
                        actual = row[campoAgrupado];
                    }
                    headers.slice(1).forEach((h, colIndex) => {
                        if (h === 'ejecutivo' && tipo === 'ejecutivo') {
                           html += `<td style="min-width: 180px;">
                                <div class="d-flex align-items-center justify-content-start gap-2">
                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalleMercaderistas('${row['jefatura']}', '${row['ejecutivo']}')" title="Ver detalle">
                                        <i class="bi bi-plus-circle"></i>
                                    </button>
                                    <span>${row[h]}</span>
                                </div>
                            </td>`;
                        } else {
                            html += h === 'avance'
                                ? `<td>${getProgressBar(row[h])}</td>`
                                : `<td>${row[h]}</td>`;
                        }
                    });

                    html += '</tr>';
                });
            } else {
                grupo.forEach(row => {
                    html += '<tr>';
                    headers.forEach(h => {
                        html += h === 'avance'
                            ? `<td>${getProgressBar(row[h])}</td>`
                            : `<td>${row[h]}</td>`;
                    });
                    html += '</tr>';
                });
            }

            html += '</tbody></table></div>';
            container.innerHTML += html;
        }
    }

    document.querySelectorAll('.month-button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.month-button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            mesSeleccionado = parseInt(btn.dataset.mes);
            cargarAvances();
        });
    });

    window.addEventListener('DOMContentLoaded', () => {
        document.querySelector(`.month-button[data-mes='${mesSeleccionado}']`).classList.add('active');
        cargarAvances();
    });

    function verDetalleMercaderistas(jefatura, ejecutivo) {
        const tipo = document.getElementById('tipoTactico').value;
        const url = `../get_mercaderista_detail.php?mes=${mesSeleccionado}&anio=${anioSeleccionado}&es_adicional=${tipo}&jefatura=${encodeURIComponent(jefatura)}&ejecutivo=${encodeURIComponent(ejecutivo)}`;
        
        const contenedor = document.getElementById('contenidoMercaderistas');
        contenedor.innerHTML = "<p class='text-center'>Cargando...</p>";
        new bootstrap.Modal(document.getElementById('modalMercaderistas')).show();

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    contenedor.innerHTML = `<p class='text-danger'>Error: ${data.message}</p>`;
                    return;
                }

                if (!data.data.length) {
                    contenedor.innerHTML = `<p class='text-warning'>No hay mercaderistas registrados.</p>`;
                    return;
                }

                let html = `<table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Mercaderista</th>
                                        <th>Asignados</th>
                                        <th>Armados</th>
                                        <th>Avance</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                data.data.forEach(m => {
                    html += `<tr>
                                <td>${m.mercaderista}</td>
                                <td>${m.cantidad_distribuida}</td>
                                <td>${m.cantidad_armada}</td>
                                <td>${getProgressBar(m.avance)}</td>
                            </tr>`;
                });
                html += `</tbody></table>`;
                contenedor.innerHTML = html;
            })
            .catch(err => {
                console.error(err);
                contenedor.innerHTML = "<p class='text-danger'>Error al conectar con el servidor.</p>";
            }); 
    }

    // TAB GESTORES DIARIOS JS

    document.getElementById("gestores-tab").addEventListener("click", () => {
        cargarGestores();
    });

    function cargarGestores() {
        const tipo = document.getElementById('tipoTactico').value;
        const fechaInput = document.getElementById('fecha_registro');
        
        // Si no tiene valor, establecer día anterior por defecto
        if (!fechaInput.value) {
            fechaInput.value = new Date(new Date().setDate(new Date().getDate() - 1)).toISOString().split('T')[0];
        }
        
        buscarActividadesPorDia();
    }

function buscarActividadesPorDia() {
    const fecha = document.getElementById('fecha_registro').value;
    if (!fecha) return;
    
    const tipo = document.getElementById('tipoTactico').value;
    
    // Formatea la fecha a dd-mm-yyyy y actualiza el span
    const partes = fecha.split('-');
    if (partes.length === 3) {
        document.getElementById('fechaRevision').textContent = `${partes[2]}-${partes[1]}-${partes[0]}`;
    }
    
    console.log(' Enviando petición con:');
    console.log('  - fecha:', fecha);
    console.log('  - tipo:', tipo);
    console.log('  - URL:', `../get_gestores_nuevo.php?fecha=${fecha}&es_adicional=${tipo}`);
    
    fetch(`../get_gestores_nuevo.php?fecha=${fecha}&es_adicional=${tipo}`)
        .then(response => {
            console.log('  Respuesta recibida:');
            console.log('  - Status:', response.status);
            console.log('  - Content-Type:', response.headers.get('content-type'));
            
            // IMPORTANTE: Primero obtenemos el texto
            return response.text().then(text => {
                console.log('Texto completo de la respuesta (primeros 500 caracteres):');
                console.log(text.substring(0, 500));
                
                if (!text) {
                    throw new Error('RESPUESTA VACÍA DEL SERVIDOR');
                }
                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('ERROR AL PARSEAR JSON:', e.message);
                    console.error('Texto recibido:', text);
                    throw new Error('JSON inválido: ' + e.message);
                }
            });
        })
        .then(data => {
            console.log('=== DEBUG DETALLADO ===');
            console.log('Total rows:', data.debug.total_rows_query);
            console.log('IDs únicos:', data.debug.total_ids_unicos);
            console.log('¿Hay duplicados?:', data.debug.hay_duplicados);
            console.log('Total duplicados:', data.debug.total_duplicados);
            
            if (data.debug.hay_duplicados) {
                console.log('DETALLES DE DUPLICADOS:', data.debug.duplicados_detalle);
            }
            
            console.log('Primeros 5 registros:', data.debug.primeros_5_registros);
            console.log('Últimos 5 registros:', data.debug.ultimos_5_registros);
            
            // NUEVO: Verificar las fechas de todos los registros
            console.log('=== VERIFICACIÓN DE FECHAS ===');
            const fechasBuscadas = data.debug.fecha_buscada;
            console.log('Fecha que buscaste:', fechasBuscadas);
            
            let fechasEncontradas = {};
            data.gestores.forEach(gestor => {
                gestor.pdvs.forEach(pdv => {
                    pdv.tacticos.forEach(tactico => {
                        const fechaRegistro = tactico.fecha_registro;
                        if (!fechasEncontradas[fechaRegistro]) {
                            fechasEncontradas[fechaRegistro] = 0;
                        }
                        fechasEncontradas[fechaRegistro]++;
                    });
                });
            });
            
            console.log('Fechas encontradas en los registros:', fechasEncontradas);
            console.log('Total de fechas diferentes:', Object.keys(fechasEncontradas).length);
            
            renderGestores(data.gestores);
            actualizarContadorRegistros(data.gestores);
        })
        .catch(error => {
            console.error('❌ Error completo:', error);
            document.getElementById('registroCounter').innerHTML = '<span class="badge bg-danger">Error: ' + error.message + '</span>';
        });
}

function actualizarContadorRegistros(gestores) {
    let totalRegistros = 0;
    
    // Contar todos los tácticos de todos los gestores
    gestores.forEach(gestor => {
        gestor.pdvs.forEach(pdv => {
            totalRegistros += pdv.tacticos.length;
        });
    });
    
    const counterElement = document.getElementById('registroCounter');
    
    if (totalRegistros === 0) {
        counterElement.innerHTML = '<span class="badge bg-warning text-dark">No se encontraron registros</span>';
    } else {
        counterElement.innerHTML = `<span class="badge bg-info">Mostrando ${totalRegistros} registro${totalRegistros !== 1 ? 's' : ''}</span>`;
    }
}

    function renderGestores(gestores) {
        const container = document.getElementById("gestor-container");
        container.innerHTML = "";

        gestores.forEach((gestor, indexGestor) => {
            const gestorCard = `
                <div class="card mb-4 shadow-sm border-0 rounded-4">
                    <div class="card-body d-flex justify-content-between align-items-center bg-light rounded-top-4 px-4 py-3"
                        style="cursor: pointer;"
                        onclick="togglePdvDetails(${indexGestor})">
                        <div>
                            <span class="fw-bold text-uppercase text-secondary small">Gestor:</span>
                            <span class="fw-semibold fs-5 text-dark">${gestor.nombre}</span>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold text-muted">${gestor.pdvs.length} PDVs Visitados</span>
                            <i class="bi bi-chevron-down ms-3 toggle-arrow collapsed" id="arrow-${indexGestor}" style="font-size: 1.25rem;"></i>
                        </div>
                    </div>
                    <div class="collapse mt-2 px-4 pb-3" id="pdvs${indexGestor}">
                        ${gestor.pdvs.map(pdv => {
                            // const fotoGuia = pdv.tacticos.length > 0 ? pdv.tacticos[0].foto_guia : '';
                            return `
                                <h6 class="mt-4 mb-2 text-primary fw-semibold">${pdv.nombre}</h6>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Foto Guía</th>
                                                <th>Producto Primario</th>
                                                <th>Táctico</th>
                                                <th>Encontrada</th>
                                                <th>Armada</th>
                                                <th>Foto Armado</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${pdv.tacticos.map((t, i) => `
                                                <tr data-gestor="${gestor.nombre}" data-pdv="${pdv.nombre}" data-tactico="${t.nombre}" data-idpacks="${t.id_packs}" class="${t.estado == '4' ? 'table-secondary' : ''}">
                                                    <td>
                                                        ${t.foto_guia
                                                            ? `<img src="${t.foto_guia}" class="img-thumbnail" style="width: 80px; cursor: pointer;" 
                                                                onclick="showImageModal('${t.foto_guia}', '${t.cantidad_encontrada}', '${t.cantidad_armada}')">`
                                                            : `<span class="text-muted small">Sin imagen</span>`
                                                        }
                                                    </td>
                                                    <td>${t.producto_primario}</td>
                                                    <td>${t.nombre}</td>
                                                    <td>${t.cantidad_encontrada}</td>
                                                    <td>${t.cantidad_armada}</td>
                                                    <td>
                                                        <img src="${t.foto_armado}" class="img-thumbnail" style="width: 80px; cursor: pointer;" onclick="showImageModal('${t.foto_armado}', '${t.cantidad_encontrada}', '${t.cantidad_armada}')">
                                                    </td>
                                                    <td>
                                                        ${t.estado == "2"
                                                            ? '<i class="bi bi-check-circle-fill text-success"></i> Validado'
                                                            : t.estado == "3"
                                                            ? '<i class="bi bi-x-circle-fill text-danger"></i> Reportado'
                                                            :`
                                                                <button class="btn btn-sm btn-outline-success me-1" onclick="validarTactico(${t.id_packs}, '${gestor.nombre}', '${pdv.nombre}', '${t.nombre}', ${t.cantidad_armada})" ${t.estado == "4" ? "disabled" : ""}>Validar</button>
                                                                <button class="btn btn-sm btn-outline-danger me-1" onclick="reportarTactico('${t.id_packs}', '${gestor.nombre}', '${pdv.nombre}', '${t.nombre}', '${t.cantidad_encontrada}', '${t.cantidad_armada}', '${t.foto_armado}', '${t.foto_guia}')" ${t.estado == "4" ? "disabled" : ""}>Reportar</button>
                                                                <button class="btn btn-sm ${t.estado == "4" ? "btn-outline-success" : "btn-outline-secondary"}"
                                                                        onclick="toggleEstadoTactico(${t.id_packs}, ${t.estado == "4" ? "true" : "false"})">
                                                                    ${t.estado == "4" ? "Activar" : "Desactivar"}
                                                                </button>
                                                            `}
                                                    </td>
                                                </tr>
                                            `).join("")}
                                        </tbody>
                                    </table>
                                </div>
                            `;
                        }).join("")}
                    </div>
                </div>`;
            container.innerHTML += gestorCard;
        });
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


    function showImageModal(imageSrc, cantidadEncontrada, cantidadArmada) {
        const imageElement = document.getElementById('imageModalSrc');
        imageElement.src = imageSrc;
        document.getElementById('imageCantidadEncontrada').textContent = cantidadEncontrada;
        document.getElementById('imageCantidadArmada').textContent = cantidadArmada;
        new bootstrap.Modal(document.getElementById('imageModal')).show();
    }

    function searchGestores() {
        const filter = document.getElementById("search-input").value.toLowerCase();
        const cards = document.querySelectorAll(".gestor-card");

        cards.forEach(card => {
            const gestorText = card.querySelector(".card-body").textContent.toLowerCase();
            card.style.display = gestorText.includes(filter) ? "" : "none";
        });
    }
    document.addEventListener("DOMContentLoaded", function () {
        document.getElementById("submitReport").addEventListener("click", () => {
                const observation = document.getElementById("reportObservation").value.trim();
                if (!observation) {
                    Swal.fire({ icon: "error", title: "Error", text: "Debe escribir una observación para el reporte." });
                    return;
                }
                const payload = { ...currentReportData, observation };
                fetch("../reporte_tactico.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(payload),
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: "success", title: "Reporte Registrado", text: "El reporte se ha registrado correctamente." });
                        marcarReportado(currentReportData.gestor, currentReportData.pdv, currentReportData.tactico);
                    } else {
                        Swal.fire({ icon: "error", title: "Error", text: "Hubo un problema al registrar el reporte." });
                    }
                }).catch(() => {
                    Swal.fire({ icon: "error", title: "Error", text: "No se pudo completar el reporte." });
                });

                bootstrap.Modal.getInstance(document.getElementById("reportModal")).hide();
        });
    });
    
    let currentReportData = {};
    function reportarTactico(idPacks, gestor, pdv, tactico, cantidadEncontrada, cantidadArmada, fotoArmado, fotoGuia) {
        currentReportData = { id_packs: idPacks, gestor, pdv, tactico, cantidad_encontrada: cantidadEncontrada, cantidad_armada: cantidadArmada, foto_armado: fotoArmado, foto_guia: fotoGuia };
        new bootstrap.Modal(document.getElementById("reportModal")).show();
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
                fetch("../validar_tactico.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        id_packs,
                        gestor,
                        pdv,
                        tactico,
                        cantidad_armada: cantidadArmada
                    }),
                })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        Swal.fire("Validado", "El táctico ha sido validado.", "success");
                        marcarValidado(id_packs);
                    } else {
                        Swal.fire("Error", "Hubo un problema al validar.", "error");
                    }
                })
                .catch((error) => {
                    console.error(error);
                    Swal.fire("Error", "No se pudo completar la validación.", "error");
                });
            }
        });
    }

    function desactivarTactico(id_packs) {
        Swal.fire({
            title: "¿Estás seguro?",
            text: "Esta acción desactivará el táctico y no podrá ser validado o reportado.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#6c757d",
            cancelButtonColor: "#d33",
            confirmButtonText: "Sí, desactivar",
            cancelButtonText: "Cancelar",
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("../desactivar_tactico.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({ id_packs }),
                })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        Swal.fire("Desactivado", "El táctico ha sido desactivado.", "success");
                        marcarDesactivado(id_packs);
                    } else {
                        Swal.fire("Error", data.message || "No se pudo desactivar el táctico.", "error");
                    }
                })
                .catch((error) => {
                    console.error(error);
                    Swal.fire("Error", "No se pudo completar la solicitud.", "error");
                });
            }
        });
    }

   function toggleEstadoTactico(id_packs, activar) {
        Swal.fire({
            title: activar ? "¿Activar táctico?" : "¿Desactivar táctico?",
            text: activar 
                ? "Esto volverá a activar el táctico y podrás validarlo o reportarlo." 
                : "Esto desactivará el táctico y no podrás validarlo ni reportarlo.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: activar ? "#28a745" : "#6c757d",
            cancelButtonColor: "#d33",
            confirmButtonText: activar ? "Sí, activar" : "Sí, desactivar",
            cancelButtonText: "Cancelar",
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("../desactivar_tactico.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ id_packs, activar })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire(
                                activar ? "Activado" : "Desactivado",
                                `El táctico ha sido ${activar ? "activado" : "desactivado"}.`,
                                "success"
                            );

                            // ✅ ACTUALIZAR SOLO ESA FILA
                            const fila = document.querySelector(`tr[data-idpacks="${id_packs}"]`);
                            if (fila) {
                                if (!activar) {
                                    fila.classList.add("table-secondary");
                                    fila.querySelectorAll("button").forEach(b => {
                                        if (b.textContent.trim() === "Validar" || b.textContent.trim() === "Reportar") {
                                            b.disabled = true;
                                        }
                                    });
                                    // Cambia texto y clase del toggle:
                                    const toggleBtn = Array.from(fila.querySelectorAll("button"))
                                        .find(b => b.textContent.includes("Desactivar") || b.textContent.includes("Activar"));
                                    if (toggleBtn) {
                                        toggleBtn.textContent = "Activar";
                                        toggleBtn.className = "btn btn-sm btn-outline-success";
                                        toggleBtn.setAttribute("onclick", `toggleEstadoTactico(${id_packs}, true)`);
                                    }
                                } else {
                                    fila.classList.remove("table-secondary");
                                    fila.querySelectorAll("button").forEach(b => {
                                        if (b.textContent.trim() === "Validar" || b.textContent.trim() === "Reportar") {
                                            b.disabled = false;
                                        }
                                    });
                                    const toggleBtn = Array.from(fila.querySelectorAll("button"))
                                        .find(b => b.textContent.includes("Desactivar") || b.textContent.includes("Activar"));
                                    if (toggleBtn) {
                                        toggleBtn.textContent = "Desactivar";
                                        toggleBtn.className = "btn btn-sm btn-outline-secondary";
                                        toggleBtn.setAttribute("onclick", `toggleEstadoTactico(${id_packs}, false)`);
                                    }
                                }
                            }

                        } else {
                            Swal.fire("Error", data.message, "error");
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire("Error", "No se pudo conectar al servidor.", "error");
                    });
                }
        });
    }

    function marcarValidado(id_packs) {
        if (document.querySelector("#corregidos").classList.contains("active")) {
            cargarCorregidos(); // ⚡ En Corregidos: recargar lista.
            return;
        }
        const row = document.querySelector(`tr[data-idpacks="${id_packs}"]`);
        if (row) {
            row.style.backgroundColor = "#d4edda";
            row.style.color = "#155724";
            row.querySelector("td:last-child").innerHTML = '<i class="bi bi-check-circle text-success"></i> Validado';
        }
    }

    function marcarReportado(gestor, pdv, tactico) {
         if (document.querySelector("#corregidos").classList.contains("active")) {
            cargarCorregidos(); // ⚡ En Corregidos: recargar lista.
            return;
        }
        const row = document.querySelector(`tr[data-gestor="${gestor}"][data-pdv="${pdv}"][data-tactico="${tactico}"]`);
        if (row) {
            row.style.backgroundColor = "#f8d7da";
            row.style.color = "#842029";
            row.querySelector("td:last-child").innerHTML = '<i class="bi bi-x-circle text-danger"></i> Reportado';
        }
    }

    function marcarDesactivado(id_packs) {
        const row = document.querySelector(`tr[data-idpacks="${id_packs}"]`);
        if (row) {
            row.classList.add("table-secondary");
            row.querySelector("td:last-child").innerHTML = '<span class="text-muted">Desactivado</span>';
        }
    }


    // TAB inspeccion  JS
 
    function cargarInspeccion() {
        const tipo = document.getElementById('tipoTactico').value;

        fetch(`../get_inspeccion_data_new.php?es_adicional=${tipo}`)
            .then(response => response.json())
            .then(data => {
                if (!data.success) throw new Error("Error al obtener los datos");

                document.getElementById("contadorDevueltos").textContent = data.reportes.length;
                document.getElementById("contadorValidados").textContent = data.validaciones.length;
                document.getElementById("contadorCorrejidos").textContent = data.correjidos.length;

                renderTable("reportesTableBody", data.reportes, ["tactico", "gestor", "pdv", "motivo", "fecha_pack", "fecha_reportado"]);
                
                renderTable("validacionesTableBody", data.validaciones, ["tactico", "gestor", "pdv", "cantidad_armada", "fecha_pack", "fecha_validado"]);
                
                renderTable("correjidosTableBody", data.correjidos, ["tactico", "gestor", "pdv", "fecha_creacion"]);
            })  
            .catch(err => {
                console.error("Error:", err);
            });
    }
   

    function renderTable(tableId, data, keys) {
        const body = document.getElementById(tableId);
        body.innerHTML = data.length === 0
            ? `<tr><td colspan="${keys.length}" class="text-center">Sin datos.</td></tr>`
            : data.map(item => `
                <tr>${keys.map(k => `<td>${item[k] || ""}</td>`).join("")}</tr>
            `).join("");
    }

    // TAB CORREGIDOS  JS
    function cargarCorregidos() {
        const tipo = document.getElementById('tipoTactico').value;

        fetch(`../get_registros_corregidos_new.php`)
            .then(response => response.json())
            .then(response => {
                if (response.success && response.data) {
                    const groupedData = {};

                    response.data.forEach(item => {
                        const { mercaderista, pdv, tactico, ...rest } = item;

                        if (!groupedData[mercaderista]) {
                            groupedData[mercaderista] = { nombre: mercaderista, pdvs: [] };
                        }

                        const pdvIndex = groupedData[mercaderista].pdvs.findIndex(p => p.nombre === pdv);

                        if (pdvIndex === -1) {
                            groupedData[mercaderista].pdvs.push({
                                nombre: pdv,
                                tacticos: [{ nombre: tactico, ...rest }]
                            });
                        } else {
                            groupedData[mercaderista].pdvs[pdvIndex].tacticos.push({
                                nombre: tactico,
                                ...rest
                            });
                        }
                    });

                    const finalData = Object.values(groupedData);
                    renderCorregidos(finalData);
                } else {
                    document.getElementById("corregidos-container").innerHTML =
                        "<p class='text-center'>No hay registros corregidos disponibles para este mes.</p>";
                }
            })
            .catch(error => {
                console.error("Error al cargar registros corregidos:", error);
            });
    }

    function renderCorregidos(gestores) {
        const container = document.getElementById("corregidos-container");
        container.innerHTML = "";

        if (!gestores || gestores.length === 0) {
            container.innerHTML = "<p class='text-center'>No hay registros corregidos disponibles para este mes.</p>";
            return;
        }

        gestores.forEach((gestor, indexGestor) => {
            const gestorCard = `
                <div class="card mb-4 shadow-sm border-0 rounded-4">
                    <div class="card-body d-flex justify-content-between align-items-center bg-light rounded-top-4 px-4 py-3"
                        style="cursor: pointer;"
                        onclick="toggleCorregidosDetails(${indexGestor})">
                        <div>
                            <span class="fw-bold text-uppercase text-secondary small">Gestor:</span>
                            <span class="fw-semibold fs-5 text-dark">${gestor.nombre}</span>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold text-muted">${gestor.pdvs.length} PDVs</span>
                            <i class="bi bi-chevron-down ms-3 toggle-arrow collapsed" id="arrow-corregidos${indexGestor}" style="font-size: 1.25rem;"></i>
                        </div>
                    </div>
                    <div class="collapse mt-2 px-4 pb-3" id="corregidos${indexGestor}">
                        ${gestor.pdvs.map(pdv => `
                            <h6 class="mt-4 mb-2 text-primary fw-semibold">${pdv.nombre}</h6>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Foto Guía</th>
                                            <th>Producto Primario</th>
                                            <th>Táctico</th>
                                            <th>Encontrada</th>
                                            <th>Armada</th>
                                            <th>Foto Armado</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${pdv.tacticos.map(t => `
                                            <tr data-gestor="${gestor.nombre}" data-pdv="${pdv.nombre}" data-tactico="${t.nombre}" data-idpacks="${t.id_packs}">
                                                <td>
                                                    <img src="${t.foto_guia}" class="img-thumbnail" style="width: 80px; cursor: pointer;" onclick="showImageModal('${t.foto_guia}', '${t.cantidad_encontrada}', '${t.cantidad_armada}')">
                                                </td>
                                                <td>${t.primario}</td>
                                                <td>${t.nombre}</td>
                                                <td>${t.cantidad_encontrada || 0}</td>
                                                <td>${t.cantidad_armada || 0}</td>
                                                <td>
                                                    <img src="${t.foto_armado}" class="img-thumbnail" style="width: 80px; cursor: pointer;" onclick="showImageModal('${t.foto_armado}', '${t.cantidad_encontrada}', '${t.cantidad_armada}')">
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-success me-1"
                                                        onclick="validarTactico('${t.id_packs}', '${gestor.nombre}', '${pdv.nombre}', '${t.nombre}', ${t.cantidad_armada})">
                                                        Validar
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger"
                                                        onclick="reportarTactico('${t.id_packs}', '${gestor.nombre}', '${pdv.nombre}', '${t.nombre}', '${t.cantidad_encontrada}', '${t.cantidad_armada}', '${t.foto_armado}', '${t.foto_guia}')">
                                                        Reportar
                                                    </button>
                                                </td>
                                            </tr>
                                        `).join("")}
                                    </tbody>
                                </table>
                            </div>
                        `).join("")}
                    </div>
                </div>`;
            container.innerHTML += gestorCard;
        });
    }
    function toggleCorregidosDetails(index) {
        const pdvDetails = document.getElementById(`corregidos${index}`);
        const arrow = document.getElementById(`arrow-corregidos${index}`);

        if (pdvDetails.classList.contains("show")) {
            pdvDetails.classList.remove("show");
            arrow.classList.add("collapsed");
        } else {
            // Cierra todos los demás
            document.querySelectorAll(".collapse").forEach(el => el.classList.remove("show"));
            document.querySelectorAll(".toggle-arrow").forEach(el => el.classList.add("collapsed"));
            pdvDetails.classList.add("show");
            arrow.classList.remove("collapsed");
        }
    }



</script>

<!-- Modal de Mercaderistas -->
<div class="modal fade" id="modalMercaderistas" tabindex="-1" aria-labelledby="modalMercaderistasLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalMercaderistasLabel">Detalle de Mercaderistas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="contenidoMercaderistas">Cargando...</div>
      </div>
    </div>
  </div>
</div>

<!-- Modal de Imagen -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imageModalLabel">Detalle de la Foto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <p><strong>Cantidad Encontrada:</strong> <span id="imageCantidadEncontrada"></span></p>
          <p><strong>Cantidad Armada:</strong> <span id="imageCantidadArmada"></span></p>
        </div>
        <div class="text-center d-flex justify-content-center align-items-center">
          <img id="imageModalSrc" src="" alt="Imagen Ampliada" class="img-fluid modal-image">
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal para reporte -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="reportModalLabel">Reportar Táctico</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <textarea id="reportObservation" class="form-control" rows="4" placeholder="Escriba su observación..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="submitReport">Enviar Reporte</button>
      </div>
    </div>
  </div>
</div>






<?php
$content = ob_get_clean();
include '../layout.php';
?>
