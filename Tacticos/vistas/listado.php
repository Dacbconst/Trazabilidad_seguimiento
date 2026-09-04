<?php
$pageTitle = "Registro de Cantidades a Productos";
ob_start();
?>
<style>
    /* Estilos generales */
    .container {
        max-width: 800px;
        margin: auto;
    }
    .form-select, .form-control {
        margin-bottom: 10px;
    }
</style>

<div class="container">
    <h2 class="my-4 text-center">Registro de Cantidades a Productos</h2>
    
    <!-- Formulario de selección -->
    <div class="card p-4 mb-4">
        <div class="row">
            <!-- Selección de categoría -->
            <div class="col-md-4">
                <label for="categoriaSelect" class="form-label">Categoría</label>
                <select class="form-select" id="categoriaSelect" onchange="actualizarProductos()">
                    <option value="" selected disabled>Seleccione una categoría</option>
                    <option value="Limpieza de Hogar">Limpieza de Hogar</option>
                    <option value="Detergentes">Detergentes</option>
                    <option value="Desinfectantes">Desinfectantes</option>
                </select>
            </div>

            <!-- Selección de producto -->
            <div class="col-md-4">
                <label for="productoSelect" class="form-label">Producto</label>
                <select class="form-select" id="productoSelect">
                    <option value="" selected disabled>Seleccione un producto</option>
                </select>
            </div>

            <!-- Selección de mes -->
            <div class="col-md-4">
                <label for="mesSelect" class="form-label">Mes</label>
                <select class="form-select" id="mesSelect">
                    <option value="" selected disabled>Seleccione un mes</option>
                    <option value="Enero">Enero</option>
                    <option value="Febrero">Febrero</option>
                    <option value="Marzo">Marzo</option>
                    <option value="Abril">Abril</option>
                </select>
            </div>
        </div>

        <!-- Asignar cantidad -->
        <div class="row mt-3">
            <div class="col-md-8">
                <input type="number" class="form-control" id="cantidadInput" placeholder="Cantidad" min="1">
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100" onclick="agregarProducto()">Agregar Producto</button>
            </div>
        </div>
    </div>

    <!-- Tabla de productos seleccionados -->
    <h3 class="my-4">Resumen de Productos Asignados</h3>
    <table class="table table-bordered" id="tablaResumen">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Mes</th>
                <th>Cantidad</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tablaResumenBody">
            <!-- Los datos agregados aparecerán aquí -->
        </tbody>
    </table>
    <button class="btn btn-success" id="guardarCambiosBtn" style="display: none;" onclick="guardarCambios()">Guardar Cambios</button>
</div>

<script>
    // Datos quemados de productos por categoría (Limpieza del hogar, detergentes, etc.)
    const productosPorCategoria = {
        "Limpieza de Hogar": ["Limpiador Multiusos", "Ambientador", "Toallas de Papel"],
        "Detergentes": ["Detergente en Polvo", "Detergente Líquido", "Jabón para Ropa"],
        "Desinfectantes": ["Desinfectante en Aerosol", "Gel Antibacterial", "Desinfectante Líquido"]
    };

    // Función para actualizar el listado de productos según la categoría seleccionada
    function actualizarProductos() {
        const categoria = document.getElementById("categoriaSelect").value;
        const productoSelect = document.getElementById("productoSelect");

        // Limpiar opciones previas
        productoSelect.innerHTML = '<option value="" selected disabled>Seleccione un producto</option>';

        // Agregar productos correspondientes a la categoría seleccionada
        if (productosPorCategoria[categoria]) {
            productosPorCategoria[categoria].forEach(producto => {
                const option = document.createElement("option");
                option.value = producto;
                option.textContent = producto;
                productoSelect.appendChild(option);
            });
        }
    }

    // Función para agregar el producto seleccionado a la tabla de resumen
    function agregarProducto() {
        const categoria = document.getElementById("categoriaSelect").value;
        const producto = document.getElementById("productoSelect").value;
        const mes = document.getElementById("mesSelect").value;
        const cantidad = document.getElementById("cantidadInput").value;

        if (categoria && producto && mes && cantidad > 0) {
            const tablaResumenBody = document.getElementById("tablaResumenBody");

            // Crear nueva fila en la tabla
            const fila = document.createElement("tr");
            fila.innerHTML = `
                <td>${producto}</td>
                <td>${categoria}</td>
                <td>${mes}</td>
                <td><input type="number" class="form-control" value="${cantidad}" min="1" onchange="activarGuardarCambios()"></td>
                <td>
                    <button class="btn btn-warning btn-sm" onclick="editarCantidad(this)">Editar</button>
                    <button class="btn btn-danger btn-sm" onclick="eliminarProducto(this)">Eliminar</button>
                </td>
            `;
            tablaResumenBody.appendChild(fila);
            
            // Mostrar el botón de guardar cambios
            document.getElementById("guardarCambiosBtn").style.display = "block";

            // Limpiar campos después de agregar
            document.getElementById("productoSelect").selectedIndex = 0;
            document.getElementById("mesSelect").selectedIndex = 0;
            document.getElementById("cantidadInput").value = "";
        } else {
            alert("Por favor, complete todos los campos antes de agregar.");
        }
    }

    // Función para eliminar un producto de la tabla
    function eliminarProducto(button) {
        const fila = button.closest("tr");
        fila.remove();

        // Ocultar el botón de guardar cambios si no hay filas
        if (document.getElementById("tablaResumenBody").children.length === 0) {
            document.getElementById("guardarCambiosBtn").style.display = "none";
        }
    }

    // Función para activar el botón de guardar cambios al editar una cantidad
    function activarGuardarCambios() {
        document.getElementById("guardarCambiosBtn").style.display = "block";
    }

    // Función para guardar cambios
    function guardarCambios() {
        alert("Cambios guardados correctamente.");
        document.getElementById("guardarCambiosBtn").style.display = "none";
    }
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
