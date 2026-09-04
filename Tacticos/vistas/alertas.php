<?php
$pageTitle = "Intercambio de Productos entre Mercaderistas";
ob_start();
?>

<div class="container">
    <h2 class="my-4 text-center">Intercambio de Tácticos entre Mercaderistas</h2>

    <div class="row">
        <!-- Selección de Mercaderista Receptor -->
        <div class="col-md-6">
            <div class="card p-4 shadow-sm">
                <h4 class="text-primary">Mercaderista Receptor</h4>
                <select class="form-select mb-3" id="mercaderistaReceptor" onchange="actualizarResumenReceptor()">
                    <option selected disabled>Seleccione el Mercaderista Receptor</option>
                </select>
                <h5 class="mt-3">Resumen de Tácticos Asignados</h5>
                <ul id="resumenReceptor" class="list-group"></ul>
            </div>
        </div>

        <!-- Selección de Mercaderistas Donantes -->
        <div class="col-md-6">
            <div class="card p-4 shadow-sm">
                <h4 class="text-danger">Mercaderistas Donantes</h4>
                <select multiple class="form-select mb-3" id="mercaderistasDonantes" onchange="actualizarResumenDonantes()">
                </select>
                <h5 class="mt-3">Resumen de Tácticos Disponibles</h5>
                <ul id="resumenDonantes" class="list-group"></ul>
            </div>
        </div>
    </div>

    <div class="card p-4 shadow-sm mt-4">
        <h4 class="text-center">Detalles del Intercambio</h4>
        <p class="text-muted text-center">Seleccione las cantidades de cada táctico a transferir.</p>

        <table class="table table-bordered">
            <thead class="table-secondary">
                <tr>
                    <th>Táctico</th>
                    <th>Cantidad Actual (Receptor)</th>
                    <th>Cantidad Disponible (Donantes)</th>
                    <th>Cantidad a Transferir</th>
                </tr>
            </thead>
            <tbody id="tablaIntercambio"></tbody>
        </table>

        <button class="btn btn-success w-100 mt-3" onclick="procesarIntercambio()">Procesar Intercambio</button>
    </div>

    <!-- Nueva Sección: Recorte de Cantidades -->
    <div class="card p-4 shadow-sm mt-4">
        <h4 class="text-center text-warning">Recorte de Cantidades</h4>
        <p class="text-muted text-center">Seleccione el mercaderista y reduzca las cantidades asignadas.</p>

        <div class="mb-3">
            <label for="mercaderistaRecorte" class="form-label">Mercaderista</label>
            <select class="form-select" id="mercaderistaRecorte" onchange="actualizarResumenRecorte()">
                <option selected disabled>Seleccione un Mercaderista</option>
            </select>
        </div>

        <table class="table table-bordered">
            <thead class="table-secondary">
                <tr>
                    <th>Táctico</th>
                    <th>Cantidad Actual</th>
                    <th>Cantidad a Recortar</th>
                </tr>
            </thead>
            <tbody id="tablaRecorte"></tbody>
        </table>

        <button class="btn btn-warning w-100 mt-3" onclick="procesarRecorte()">Procesar Recorte</button>
    </div>


    <!-- Historial de Intercambios -->
    <div class="card p-4 shadow-sm mt-4">
        <h4 class="text-center">Historial de Intercambios</h4>
        <table class="table table-striped" id="tablaHistorial">
            <thead class="table-secondary">
                <tr>
                    <th>Fecha</th>
                    <th>Táctico</th>
                    <th>Receptor</th>
                    <th>Donante</th>
                    <th>Cantidad</th>
                    <th>Tipo</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let receptor = null;
let donantesSeleccionados = [];
let inventario = {};

document.addEventListener("DOMContentLoaded", () => {
    cargarInventario();
    cargarHistorial();
});

function cargarInventario() {
    fetch("../get_intercambio_mercaderista.php")
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                inventario = data.data;
                cargarOpciones("mercaderistaReceptor", Object.keys(inventario));
                cargarOpciones("mercaderistasDonantes", Object.keys(inventario));
                cargarOpciones("mercaderistaRecorte", Object.keys(inventario));

            }
        })
        .catch(error => console.error("Error al cargar inventario:", error));
}

function cargarHistorial() {
    fetch("../get_historial_intercambios.php")
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tbody = document.querySelector("#tablaHistorial tbody");
                tbody.innerHTML = "";

                data.data.forEach(item => {
                    const fila = `
                        <tr>
                            <td>${item.fecha}</td>
                            <td>${item.tactico}</td>
                            <td>${item.receptor}</td>
                            <td>${item.donante}</td>
                            <td>${item.cantidad}</td>
                            <td>${item.tipo}</td>
                        </tr>
                    `;
                    tbody.insertAdjacentHTML("beforeend", fila);
                });
            } else {
                console.error("Error al cargar historial:", data.message);
            }
        })
        .catch(error => console.error("Error al obtener el historial:", error));
}

function cargarOpciones(selectId, opciones) {
    const select = document.getElementById(selectId);
    opciones.forEach(opcion => {
        const optionElement = document.createElement("option");
        optionElement.value = opcion;
        optionElement.textContent = opcion;
        select.appendChild(optionElement);
    });
}

function actualizarResumenReceptor() {
    receptor = document.getElementById("mercaderistaReceptor").value;
    const resumen = document.getElementById("resumenReceptor");
    resumen.innerHTML = "";

    console.log("Inventario cargado:", inventario); 
    console.log("Receptor seleccionado:", receptor);

    if (inventario[receptor]) {
        Object.entries(inventario[receptor]).forEach(([tactico, cantidad]) => {
            const li = document.createElement("li");
            li.textContent = `${tactico}: ${cantidad}`;
            li.classList.add("list-group-item");
            resumen.appendChild(li);
        });
    } else {
        resumen.innerHTML = "<li class='list-group-item text-muted'>No hay tácticos asignados</li>";
    }
}


function actualizarResumenDonantes() {
    donantesSeleccionados = Array.from(document.getElementById("mercaderistasDonantes").selectedOptions).map(option => option.value);
    const resumen = document.getElementById("resumenDonantes");
    resumen.innerHTML = "";

    donantesSeleccionados.forEach(donante => {
        if (inventario[donante]) {
            const li = document.createElement("li");
            li.innerHTML = `<strong>${donante}</strong>: ` +
                Object.entries(inventario[donante])
                    .map(([tactico, cantidad]) => `${tactico}: ${cantidad}`)
                    .join(", ");
            li.classList.add("list-group-item");
            resumen.appendChild(li);
        }
    });
    actualizarTablaIntercambio();
}

function actualizarTablaIntercambio() {
    const tabla = document.getElementById("tablaIntercambio");
    tabla.innerHTML = "";

    if (receptor && donantesSeleccionados.length > 0) {
        const tacticosReceptor = inventario[receptor];
        Object.keys(tacticosReceptor).forEach(tactico => {
            const cantidadReceptor = tacticosReceptor[tactico];
            const cantidadTotalDonantes = donantesSeleccionados.reduce((sum, donante) => {
                return sum + (inventario[donante][tactico] || 0);
            }, 0);

            const fila = document.createElement("tr");
            fila.innerHTML = `
                <td>${tactico}</td>
                <td>${cantidadReceptor}</td>
                <td>${cantidadTotalDonantes}</td>
                <td>
                    <input type="number" class="form-control" min="0" max="${cantidadTotalDonantes}" 
                           placeholder="Cantidad a transferir">
                </td>
            `;
            tabla.appendChild(fila);
        });
    }
}

async function procesarIntercambio() {
    const intercambios = [];
    if (!validarIntercambio()) {
        return;
    }

    document.querySelectorAll("#tablaIntercambio tr").forEach(row => {
        const tactico = row.children[0].textContent.trim();
        const cantidadTransferir = parseInt(row.children[3].querySelector("input").value) || 0;

        if (cantidadTransferir > 0) {
            intercambios.push({ tactico, cantidad: cantidadTransferir });
        }
    });

    if (intercambios.length > 0) {
        try {
            const response = await fetch("../procesar_intercambio.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ receptor, donantes: donantesSeleccionados, intercambios }),
            });

            if (!response.ok) {
                throw new Error("Error en la respuesta del servidor");
            }

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: "success",
                    title: "Éxito",
                    text: data.message || "Intercambio procesado con éxito",
                });

                // Actualizar inventario localmente
                intercambios.forEach(intercambio => {
                    const { tactico, cantidad } = intercambio;

                    // Actualizar receptor
                    if (inventario[receptor][tactico]) {
                        inventario[receptor][tactico] += cantidad;
                    } else {
                        inventario[receptor][tactico] = cantidad;
                    }

                    // Actualizar donantes
                    donantesSeleccionados.forEach(donante => {
                        if (inventario[donante][tactico]) {
                            inventario[donante][tactico] -= cantidad;

                            // Si la cantidad llega a 0, eliminar el táctico del inventario
                            if (inventario[donante][tactico] <= 0) {
                                delete inventario[donante][tactico];
                            }
                        }
                    });
                });

                // Actualizar vistas
                actualizarResumenReceptor();
                actualizarResumenDonantes();
                actualizarTablaIntercambio();
                cargarHistorial(); // Actualiza el historial
                resetFormulario(); // Limpia los selectores
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: data.message || "Error al procesar el intercambio",
                });
            }
        } catch (error) {
            console.error("Error al procesar el intercambio:", error);
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "Ocurrió un error inesperado. Intenta de nuevo.",
            });
        }
    } else {
        Swal.fire({
            icon: "warning",
            title: "Sin selección",
            text: "No se ha seleccionado ninguna cantidad para transferir.",
        });
    }
}


function validarIntercambio() {
    const filas = document.querySelectorAll("#tablaIntercambio tr");
    let valid = true;
    filas.forEach(fila => {
        const tactico = fila.children[0].textContent.trim();
        const cantidadDisponible = parseInt(fila.children[2].textContent.trim());
        const cantidadTransferir = parseInt(fila.children[3].querySelector("input").value.trim()) || 0;

        if (cantidadTransferir > cantidadDisponible) {
            Swal.fire({
                icon: "error",
                title: "Cantidad inválida",
                text: `La cantidad a transferir para el táctico "${tactico}" no puede exceder ${cantidadDisponible}.`,
            });
            valid = false;
        }
    });
    return valid;
}

let mercaderistaRecorte = null;

function actualizarResumenRecorte() {
    mercaderistaRecorte = document.getElementById("mercaderistaRecorte").value;
    const tablaRecorte = document.getElementById("tablaRecorte");
    tablaRecorte.innerHTML = "";

    if (inventario[mercaderistaRecorte]) {
        Object.entries(inventario[mercaderistaRecorte]).forEach(([tactico, cantidad]) => {
            const fila = `
                <tr>
                    <td>${tactico}</td>
                    <td>${cantidad}</td>
                    <td>
                        <input type="number" class="form-control" min="0" max="${cantidad}" placeholder="Cantidad a recortar">
                    </td>
                </tr>`;
            tablaRecorte.insertAdjacentHTML("beforeend", fila);
        });
    }
}

async function procesarRecorte() {
    const recortes = [];
    document.querySelectorAll("#tablaRecorte tr").forEach(row => {
        const tactico = row.children[0].textContent.trim();
        const cantidadRecortar = parseInt(row.children[2].querySelector("input").value) || 0;

        if (cantidadRecortar > 0) {
            recortes.push({ tactico, cantidad: cantidadRecortar });
        }
    });

    if (recortes.length > 0) {
        try {
            const response = await fetch("../procesar_recorte.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ mercaderista: mercaderistaRecorte, recortes }),
            });

            if (!response.ok) {
                throw new Error("Error en la respuesta del servidor");
            }

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: "success",
                    title: "Éxito",
                    text: data.message || "Recorte procesado con éxito",
                });

                recortes.forEach(recorte => {
                    const { tactico, cantidad } = recorte;
                    if (inventario[mercaderistaRecorte][tactico]) {
                        inventario[mercaderistaRecorte][tactico] -= cantidad;

                        if (inventario[mercaderistaRecorte][tactico] <= 0) {
                            delete inventario[mercaderistaRecorte][tactico];
                        }
                    }
                });

                actualizarResumenRecorte();
                cargarHistorial();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: data.message || "Error al procesar el recorte",
                });
            }
        } catch (error) {
            console.error("Error al procesar el recorte:", error);
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "Ocurrió un error inesperado. Intenta de nuevo.",
            });
        }
    } else {
        Swal.fire({
            icon: "warning",
            title: "Sin selección",
            text: "No se ha seleccionado ninguna cantidad para recortar.",
        });
    }
}


function resetFormulario() {
    document.getElementById("mercaderistaReceptor").value = "";
    document.getElementById("mercaderistasDonantes").value = "";
    document.getElementById("resumenReceptor").innerHTML = "";
    document.getElementById("resumenDonantes").innerHTML = "";
    document.getElementById("tablaIntercambio").innerHTML = "";
    receptor = null;
    donantesSeleccionados = [];
}
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
