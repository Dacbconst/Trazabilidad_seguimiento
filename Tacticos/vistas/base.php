<?php
$pageTitle = "Base de Seguimiento";
ob_start();
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
    .module-header {
        margin-bottom: 30px;
        padding: 15px;
        background: #f5f0ff;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    }
    .module-title {
        font-size: 2rem;
        font-weight: bold;
        color: #5a3d8a;
        margin: 0;
    }
    .table-title th {
        background-color: #e9e9ff;
        color: #333;
        text-align: center;
    }
    #baseTable td, #baseTable th {
        text-align: center;
        vertical-align: middle;
    }
</style>

<div class="container mt-5">
    <div class="module-header text-center">
        <h1 class="module-title">📊 Base de Actividades</h1>
    </div>

    <div id="baseLoader" class="text-center mb-4" style="display:none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <p>Cargando datos...</p>
    </div>

    <!-- <button id="btnDescargarTodo" class="btn btn-success mb-3">
        <i class="bi bi-file-earmark-excel-fill"></i> Descargar Todo en Excel
    </button> -->


    <div class="table-responsive shadow-sm p-3 bg-white rounded">
        <table class="table table-bordered table-hover" id="baseTable">
            <thead class="table-title">
                <tr>
                    <th>Fecha</th>
                    <th>Código</th>
                    <th>Local</th>
                    <th>Región</th>
                    <th>Provincia</th>
                    <th>Ciudad</th>
                    <th>Canal</th> 
                    <th>Jefatura</th>
                    <th>Ejecutivo</th>
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
            <tbody id="baseTableBody"></tbody>
        </table>
    </div>
</div>

<!-- DataTables core CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<!-- DataTables Buttons CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<!-- jQuery (necesario para DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- DataTables core JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<!-- DataTables Buttons JS -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<!-- JSZip para exportar Excel -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<!-- HTML5 export para Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>


<script>
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
                initDataTables();
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
            <td>${item.canal}</td>
            <td>${item.jefatura}</td> 
             <td>${item.ejecutivo}</td>
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
                text: '<i class="bi bi-file-earmark-excel-fill"></i> Descargar Todo en Excel',
                className: 'btn btn-success',
                exportOptions: {
                    modifier: {
                        page: 'all'
                    },
                    // ✅ Formatea cada celda
                    format: {
                        body: function (data, row, column, node) {
                            // Para columnas de Foto Guía y Foto Armado:
                            if (column === 21 || column === 22) { // OJO: columnas empiezan en 0
                                const a = $('<div>').html(data).find('a').attr('href');
                                return a ? a : '';
                            }
                            return data;
                        }
                    }
                },
                title: 'Base de Seguimiento',
                filename: 'Base_Seguimiento'
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

document.getElementById('btnDescargarTodo').addEventListener('click', () => {
    // Dispara el click del botón interno de DataTables:
    $('.buttons-excel').click();
});


</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
