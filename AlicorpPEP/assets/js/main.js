const URL_PDVS_RELEVADOS = '../../AppAlicorpSupervision/Web/get_evaluacion_relevada.php';
const URL_PDVS_RELEVADOS_HOY = '../../AppAlicorpSupervision/Web/get_pdvs_evaluados_hoy.php';
const URL_GENERAR_REPORTE = "../../XploraEcuador/mantenimiento_descarga_bases/Alicorp/epv/includes/indexEpvDiarioV2.php";
const URL_GET_MARGENES_PRECIO_PVC = "../../AppAlicorpSupervision/Web/get_precios_margen_pvc.php";
const URL_GET_PRECIO_PVP = "../../AppAlicorpSupervision/Web/get_precios_pvp.php";
const URL_GET_NOVEDADES = "../../AppAlicorpSupervision/Web/get_novedades.php";
const URL_INSERT_NOVEDAD = "../../AppAlicorpSupervision/Inserts/insert_novedad.php";

document.addEventListener("DOMContentLoaded", mostrarMensajeDeCarga);
document.addEventListener("DOMContentLoaded", verificarReporte);
document.addEventListener("DOMContentLoaded", obtenerMargenesPrecio);
document.addEventListener("DOMContentLoaded", obtenerPreciosPvp);
document.addEventListener("DOMContentLoaded", obtenerNovedades);
document.addEventListener('DOMContentLoaded', abrirModalNovedades);
document.addEventListener("DOMContentLoaded", obtenerPdvsRelevados);
document.getElementById("btn-buscar").addEventListener("submit", obtenerPdvsRelevados);
document.getElementById("btn-generar-reporte").addEventListener("click", generarReporte);
const btnGenerarReporte = document.getElementById("btn-generar-reporte");


const usuario = document.getElementById("nombre_supervisor").innerText.trim();
let fecha = '';
let hora = '';
const arrPreciosMargenesPVC = [];
const arrPreciosPVP = [];

const arrNovedades = [];

let preguntasOriginales = [];
let codigo_seleccionado = '';
let punto_venta_seleccionado = '';




document.querySelectorAll('.tipo-canal').forEach(select => {
    select.addEventListener('change', function () {
        const tipo = this.value;
    //    console.log(tipo);
        const posId = this.dataset.posId;
        const container = document.querySelector(`#preguntas-container-${posId}`);
        const formulario = document.getElementById(`formulario-preguntas-${posId}`);

        if (!tipo) return;

        formulario.innerHTML = '<p class="tex-mute">Cargando preguntas...</p>';

        fetch(`preguntas_repositorio.php?tipo=${tipo}`)
            .then(response => response.json())
            .then(data => {
              //  console.log(data);
                // constantes para mostrar la cantidad de preguntas para el canal seleccionado:
                const cantidadPreguntas = data.length;
                const contadorPreguntas = document.getElementById(`contador-preguntas-${posId}`);
                if (contadorPreguntas) {
                    contadorPreguntas.textContent = `Se encontraron ${cantidadPreguntas} preguntas para este canal:`;
                }
                // fin de mostrar las preguntas del canal.

                if (!data.length) {
                    formulario.innerHTML = '<p class="text-success">No hay preguntas para este canal.</p>';
                    return;
                }

                let html = '';

                //se crea uun objeto para agrupar las preguntas por su clasificacion perfect_store
                let groupedByPerfectStore = {}; // se inicializa en vacio

                // se itera por cada pregunta y se agrupan en perfect_store
                data.forEach(item => {
                    const perfectStore = item.perfect_store;
                    const categoria = item.categoria;
                    8
                    // si no existe se inicializa
                    if (!groupedByPerfectStore[perfectStore]) {
                        groupedByPerfectStore[perfectStore] = {};
                    }

                    // si no existe tampoco esa categoria se inicializa en 0

                    if (!groupedByPerfectStore[perfectStore][categoria]) {
                        groupedByPerfectStore[perfectStore][categoria] = [];
                    }

                    // se agrega la pregunta al grupo de perfect sotre correspondiente

                    groupedByPerfectStore[perfectStore][categoria].push(item);
                });

for (const perfectStore in groupedByPerfectStore) {

    html += `<h5 class="text-uppercase text-center" style="background-color :rgba(255, 0, 0, 0.99); color: white; border-radius: 3px">${perfectStore}</h5>`;

    for (const categoria in groupedByPerfectStore[perfectStore]) {
        html += `   
        <div class="card mb-3 text-center" style="box-shadow: 0 4px 50px rgba(231, 90, 90, 0.2);">
            <div class="card-header fw-bold text-uppercase">${categoria}</div>
            <div class="card-body">`;

        groupedByPerfectStore[perfectStore][categoria].forEach(preg => {
            html += `
            <p class="mb-2 w-100 fw-bold"><strong id="pregunta-${preg.id}">${preg.pregunta1}</strong></p>
            <p class="text-muted">${preg.variante}</p>
            <div class="d-flex align-items-center gap-4 mb-3">`;

            // ✅ Verificar si el perfectStore contiene 'PRECIO'
            if (perfectStore.includes('PRECIO')) {
                html += `
                <div class="form-group w-50">
                    <input id="precio_${preg.id}" type="text"
                        inputmode="decimal" 
                        class="form-control cleave-precio" 
                        name="precio_${preg.id}" 
                        data-pregunta="${preg.pregunta1}"       
                        min="0.01"
                        step="any"
                        placeholder="$"
                        oninput='verificarPrecios(
                            "${tipo}",
                            ${preg.id}, 
                            this.value, 
                            "${preg.pregunta1.replace(/"/g, '\\"').replace(/\./g, '_dot_')}" 
                        )'
                        required>
                    <span id="validacion-${preg.id}" class="badge d-none mt-2"></span>
                </div>`;
            } else {
                html += `
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="respuesta_${preg.id}" id="si_${preg.id}"  
                        onchange="habilitarPrecioInput('${preg.pregunta1}', true)"  
                        ${preg.puntaje == 0 ? 'disabled' : 'required'}>
                    <label class="form-check-label" for="si_${preg.id}">Sí cumple</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="respuesta_${preg.id}" id="no_${preg.id}"  
                        onchange="habilitarPrecioInput('${preg.pregunta1}', false)" 
                        ${preg.puntaje == 0 ? 'checked disabled' : ''}>
                    <label class="form-check-label" for="no_${preg.id}">No cumple</label>
                </div>

                <!-- ✅ SOLO se muestra si NO es PRECIO -->
                <div class="dropdown text-center">
                    <button class="btn btn-secondary1 dropdown-toggle" type="button" data-bs-toggle="dropdown" 
                        id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" 
                        ${preg.puntaje == 0 ? 'disabled' : ''}>
                        Elija Archivo
                    </button>
                    <ol class="dropdown-menu text-center">
                        <li>
                            <button class="dropdown-item open-camera" type="button" data-pregunta-id="${preg.id}">📷 Abrir Cámara</button>
                        </li>
                        <li>
                            <label class="dropdown-item" for="inputArchivo_${preg.id}" style="cursor: pointer;">
                                📁 Cargar Archivo
                            </label>
                            <input type="file" name="evidencia" id="inputArchivo_${preg.id}"
                                class="d-none" onchange="previsualizarImagen(${preg.id})" 
                                accept=".jpg, .jpeg, .png, image/jpeg, image/png, image/jfif"/>
                        </li>
                    </ol>
                </div>`;
            }

            html += `
            </div>
            <div id="comentario-container_${preg.id}"></div>
            <div id="imagen-container_${preg.id}">
                <img id="imagenPrevisualizacion_${preg.id}"
                    class="imagen-previsualizacion rounded float-right mb-3" 
                    style="width: 345px; height: 300px; display: none;">
            </div>`;
        });

        html += `</div></div>`;
    }
}


                html += `
<div class="modal-footer">
<button type="button" class="btn btn-secondary btn-cerrar-modal" data-modal-id="modal-<?php echo $pdv['pos_id']; ?>">Cerrar</button>
  <!--A ESTE BOTON AGREGARLE UN EVENTO PARA MOSTARR LA DATA -->
<button type="submit" class="btn btn_custom " style="background-color: rgb(233, 86, 53); color: white;">Guardar</button>
</div>
`;

                formulario.innerHTML = html;
                ///////////////////////////////////////////////////////////////////


                // Inicializar Cleave en los inputs de precio
                document.querySelectorAll('.cleave-precio').forEach(input => {


                    // Permitir puntos y reemplazarlos por comas al escribir
                    input.addEventListener("input", (e) => {
                        let value = e.target.value.replace(/\,/g, "."); // Convertir puntos a comas
                        cleave.setRawValue(value); // Forzar el formato de Cleave
                    });


                    const cleave = new Cleave(input, {
                        numeral: true,
                        numeralThousandsGroupStyle: 'none', // Sin separador de miles
                        numeralDecimalMark: '.',
                        numeralDecimalScale: 2, // Máximo 2 decimales
                        numeralIntegerScale: 3 // Máximo 3 enteros
                    });

                });







                // FUNCIONALIDAD DE QUE SE DESLIZE SOLITO HACIA ABAJO
                ///////////////////////////////////////////////////////////////////
                // const container = document.querySelector(`#preguntas-container-${posId}`);

                // // Espera un pequeño momento para garantizar que el DOM se haya actualizado antes de hacer el scroll
                // setTimeout(() => {
                //     // Solo hacer scroll si el contenedor existe
                //     if (container) {
                //         container.scrollIntoView({ behavior: 'smooth', block: 'end' });
                //     }
                // }, 200);




document.querySelector('.btn_custom').addEventListener('click', async function (event) {
    event.preventDefault();

    





    if (!formulario.checkValidity()) {
        formulario.reportValidity();
        return;
    }

    const respuestas = [];
    const comentarios = [];
    const archivos = [];
    let usuario = document.getElementById("nombre_supervisor").innerText.trim();
    let rol = "SUPERVISOR_CLIENTE";
    let cedi = document.getElementById(`cedi-${posId}`).innerText;
    let cliente = document.getElementById(`pos-name-${posId}`).innerText;
    let codigo = document.getElementById(`pos-id-${posId}`).innerText;

    codigo = codigo.replace("Código: ", "").trim();
    cliente = cliente.replace("Cliente: ", "").trim();
    cedi = cedi.replace("Ciudad: ", "").trim();

    const { fecha, hora } = obtenerFechaHoraActual();

    for (const preg of data) {


    let valid = true;
    let mensajeError = '';
    // Verificar preguntas de VISIBILIDAD con "Sí" seleccionado

    if (preg.perfect_store.includes("VISIBILIDAD")) {
      const siRadio = document.getElementById(`si_${preg.id}`);
      const archivoInput = document.getElementById(`inputArchivo_${preg.id}`);
     
      if (siRadio && siRadio.checked) {
        const previsualizacion = document.getElementById(`imagenPrevisualizacion_${preg.id}`);
        const estilo = window.getComputedStyle(previsualizacion);
       
        if (estilo.display === "none" && (!archivoInput || archivoInput.files.length === 0)) {
          valid = false;
          mensajeError = `Debe subir una. foto para la pregunta:  ${preg.pregunta1}`;

        }
      }
    }
 
  if (!valid) {
    event.preventDefault();
    Swal.fire({
      icon: 'warning',
      title: 'Falta foto obligatoria',
      text: mensajeError
    });
    return;
  }












        const pregunta = document.getElementById(`pregunta-${preg.id}`).innerHTML;
        const respuestaSeleccionada = document.querySelector(`input[name="respuesta_${preg.id}"]:checked`);
        const comentarioInput = document.getElementById(`recomendacion_${preg.id}`);
        const archivoInput = document.getElementById(`inputArchivo_${preg.id}`);
        const unidades_minimas = preg.puntaje;
        const categoria = preg.categoria;
        const variante = preg.variante;
        const perfect_store = preg.perfect_store;
        const origen = "WEB";

        let respuesta = '';
        let precio = 'N/A';
        
        if (perfect_store.includes("PRECIO")) {
            const input_precio = document.getElementById(`precio_${preg.id}`);
            precio = input_precio.value;
            const cumplimiento = document.getElementById(`validacion-${preg.id}`);
            respuesta = cumplimiento.classList.contains("bg-success") ? "Si" : "No";
        } else {
            respuesta = respuestaSeleccionada ? (respuestaSeleccionada.id.includes('si_') ? 'Si' : 'No') : '';
        }

        let comentario_pregunta = comentarioInput ? comentarioInput.value : '';
        let foto_pregunta = 'NO_FOTO';
        
        if (!perfect_store.includes("PRECIO")) {

        if (archivoInput.files.length > 0) {
            try {
                foto_pregunta = await convertirImgBase64(preg.id,0.70);
            } catch (error) {
                console.error('Error convirtiendo imagen:', error);
                foto_pregunta = 'ERROR_FOTO';
            }
        }
            
        }



        

        respuestas.push({
            codigo, 
            rol, 
            cedi, 
            tipo_cliente: tipo, 
            unidades_minimas, 
            usuario, 
            cliente,
            perfect_store, 
            categoria, 
            variante, 
            pregunta, 
            respuesta, 
            precio, 
            foto_pregunta, 
            comentario_pregunta,
            origen, 
            fecha, 
            hora
        });
    }

    guardarPreguntas(respuestas, posId);
});


                /*
                                document.getElementById("btn-cerrar").addEventListener("click",()=>{
                                    const modalEl = document.getElementById(`modal-${posId}`);
                                    const modal = new bootstrap.Modal(modalEl);
                                    console.log(modal);
                
                                });
                */
                //////////////////////////////////////////////////////////////////////


                ///////Activar el input dinámico cuando se selecciona el check de "No"/////////
                data.forEach(preg => {
                    document.querySelectorAll(`input[name="respuesta_${preg.id}"]`).forEach(radio => {
                        radio.addEventListener('change', function () {
                            const comentarioContainer = document.getElementById(`comentario-container_${preg.id}`);
                            if (this.id === `no_${preg.id}`) {
                                comentarioContainer.innerHTML = `
                                    <label for="recomendacion_${preg.id}" class="form-label mt-2 text-center w-100 fw-bold ">Comentario:</label>
                                    <input type="text" id="recomendacion_${preg.id}" class="form-control text-center mb-3" placeholder="Escriba su observacion">
                                `;
                            } else {
                                comentarioContainer.innerHTML = '';
                            }
                        });
                    });
                });
            })
            .catch(error => {
                console.error('Error:', error);
                formulario.innerHTML = '<p class="text-danger">Error al cargar preguntas.</p>';
            });
    });
});


// document.querySelectorAll('[data-pos-id]').forEach(button => {
//     button.addEventListener('click', function () {
//         const posId = this.dataset.posId;
//         const posName = this.dataset.posName;
//         const formularioNovedades = document.getElementById("formNovedades");

//         //  console.log("POS ID obtenido:", posId);
//         //  console.log("POS Nombre:", posName);

//         // Insertar datos en el modal
//         document.getElementById('modalPosId').textContent = posId;
//         document.getElementById('modalPosName').textContent = posName;
//         const selectNovedad = document.getElementById("select-novedad");


//         for (let i = 0; i < arrNovedades.length; i++) {
//             let novedad = arrNovedades[i];           
//             const option = document.createElement('option');
//             option.value = novedad;
//             option.textContent = novedad;
//             selectNovedad.appendChild(option);
//         }

//         // Mostrar modal (si es necesario)
//         const modal = new bootstrap.Modal(document.getElementById(`novedadesModal-${posId}`));
//         modal.show();


//         formularioNovedades.addEventListener("submit",(event)=>{
//             event.preventDefault();

//             let str_novedad = selectNovedad.value;

//             console.log(selectNovedad.value);
//         })




//     });
// });








// Debounce con parámetro adicional





// Manejar apertura del modal

// function abrirModalNovedades() {

//     const modalNovedades = document.getElementById('novedadesModal');


//     modalNovedades.addEventListener('show.bs.modal', (event) => {

//         console.log('Elemento que disparó el modal:', event.target);

//         if (event.relatedTarget) {
//             const boton = event.relatedTarget;
//             // Verifica los dataset
//             console.log('Dataset del botón:', boton.dataset);

//             // Usa los nombres correctos de los atributos
//             const pdvId = boton.dataset.posId; // Para data-pdv-id
//             const pdvNombre = boton.dataset.posName; // Para data-pdv-name
//             const selectNovedad = document.getElementById("select-novedad");
//             let divComentario = document.getElementById("comentario-container");
//             let txtComentario = document.getElementById("comentario-novedad");
//             let novedad = '';

//             if (selectNovedad.options.length === 1) {
//                 for (let i = 0; i < arrNovedades.length; i++) {
//                     let novedad = arrNovedades[i];
//                     const option = document.createElement('option');
//                     option.value = novedad;
//                     option.textContent = novedad;
//                     selectNovedad.appendChild(option);
//                 }
//             }

//             selectNovedad.addEventListener("change", (e) => {
//                 novedad = e.target.value;

//                 if (novedad == "OTRAS") {
//                     divComentario.style.display = "block";
//                     txtComentario.style.display = "block";
//                     txtComentario.setAttribute("required", "");
//                 } else {
//                     divComentario.style.display = "none";
//                     txtComentario.style.display = "none";
//                     txtComentario.removeAttribute("required");
//                 }
//             })


//             // Actualizar contenido del modal
//             document.getElementById('modalPosId').textContent = pdvId;
//             document.getElementById('modalPosName').textContent = pdvNombre;

//             console.log('ID obtenido:', pdvId);
//             console.log('Nombre obtenido:', pdvNombre);


//             document.getElementById("formNovedad").addEventListener("submit", (e) => {
//                 e.preventDefault();

//                 const { fecha, hora } = obtenerFechaHoraActual();

//                 const inputArchivoNovedad = document.getElementById(`inputArchivo_novedad`);

//                 let foto_novedad = 'NO_FOTO';
//                 if (inputArchivoNovedad && inputArchivoNovedad.files.length > 0) {
//                     foto_novedad = convertirImgBase64('novedad');
//                 }

//                 if (novedad == "OTRAS") {
//                     novedad = novedad + ": " + txtComentario.value;
//                 }



//                 // Envio de datos 
//                 // Datos a enviar (ejemplo)
//                 const data = {
//                     pos_id: pdvId,
//                     pos_name: pdvNombre,
//                     user: usuario,
//                     fecha,
//                     hora,
//                     causal: novedad,
//                     foto: foto_novedad
//                 };

//                 fetch(URL_INSERT_NOVEDAD, {
//                     method: 'POST',
//                     headers: {
//                         'Content-Type': 'application/json',
//                     },
//                     body: JSON.stringify(data)
//                 })

//                     .then(response => response.json())
//                     .then(data => {
//                         if (data.estado === '1') {
//                             Swal.fire({
//                                 icon: 'success',
//                                 title: '¡Éxito!',
//                                 text: data.mensaje,
//                                 timer: 3000, // 2 segundos
//                                 showConfirmButton: false
//                             }).then(() => {
//                                 // Cierra el modal 
//                                 const modalNovedad = document.getElementById('novedadesModal'); // Reemplaza con tu ID
//                                 const modal = bootstrap.Modal.getInstance(modalNovedad);
//                                 if (modal) {
//                                     modal.hide();
//                                 }

//                                 // Opcional: Resetear formulario
//                                 document.getElementById('formNovedad').reset(); // Reemplaza con tu ID
//                                 location.reload(); // Se ejecutará después del timer
//                             });

//                         } else {
//                             console.error('Error:', data.mensaje);
//                         }
//                     })
//                     .catch(error => {
//                         console.error('Error de red:', error);
//                     });


//             });






//         } else {
//             console.warn('El modal no fue abierto por un botón con datos');
//         }



//         //   // Resetear validación
//         //     const formulario = document.getElementById('formNovedades');
//         //     formulario.classList.remove('was-validated');
//         //     document.getElementById('selectNovedad').classList.remove('is-invalid');


//     });

//     // Escuchar evento de cierre
//     modalNovedades.addEventListener('hidden.bs.modal', (event) => {
//         // Limpiar formulario
//         document.getElementById('formNovedad').reset();
//         let imgVistaPrevia = document.getElementById('imagenPrevisualizacion_novedad');
//         imgVistaPrevia.style.display = "none";
//         imgVistaPrevia.value = '';
//         let divComentario = document.getElementById("comentario-container");
//         let txtComentario = document.getElementById("comentario-novedad");
//         divComentario.style.display = "none";
//         txtComentario.style.display = "none";
//         txtComentario.removeAttribute("required");




//         // Opcional: Remover mensajes de error
//         // const errores = document.querySelectorAll('.error-message');
//         // errores.forEach(e => e.remove());
//     });

// }

function abrirModalNovedades() {
    const modalNovedades = document.getElementById('novedadesModal');

    modalNovedades.addEventListener('show.bs.modal', (event) => {
        if (event.relatedTarget) {
            const boton = event.relatedTarget;
            const pdvId = boton.dataset.posId;
            const pdvNombre = boton.dataset.posName;
            const selectNovedad = document.getElementById("select-novedad");
            const divComentario = document.getElementById("comentario-container");
            const txtComentario = document.getElementById("comentario-novedad");
            const inputArchivoNovedad = document.getElementById("inputArchivo_novedad");
            let novedad = '';

            // Cargar opciones de novedades
            if (selectNovedad.options.length === 1) {
                arrNovedades.forEach(novedad => {
                    const option = document.createElement('option');
                    option.value = novedad;
                    option.textContent = novedad;
                    selectNovedad.appendChild(option);
                });
            }

            // Manejar cambio de selección
            selectNovedad.addEventListener("change", (e) => {
                novedad = e.target.value;
                divComentario.style.display = novedad === "OTRAS" ? "block" : "none";
                txtComentario.style.display = novedad === "OTRAS" ? "block" : "none";
                txtComentario.toggleAttribute("required", novedad === "OTRAS");
            });

            // Actualizar contenido del modal
            document.getElementById('modalPosId').textContent = pdvId;
            document.getElementById('modalPosName').textContent = pdvNombre;

            // Manejar submit del formulario
            document.getElementById("formNovedad").addEventListener("submit", async (e) => {
                e.preventDefault();

                try {
                    const { fecha, hora } = obtenerFechaHoraActual();
                    let foto_novedad = 'NO_FOTO';

                    // Convertir imagen si existe
                    if (inputArchivoNovedad.files.length > 0) {
                        foto_novedad = await convertirImgBase64('novedad', 0.35); // 35% de calidad
                    }

                    // Construir objeto de datos
                    const data = {
                        pos_id: pdvId,
                        pos_name: pdvNombre,
                        user: usuario,
                        fecha,
                        hora,
                        causal: novedad === "OTRAS" ? `${novedad}: ${txtComentario.value}` : novedad,
                        foto: foto_novedad
                    };

                    // Enviar datos
                    const response = await fetch(URL_INSERT_NOVEDAD, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();

                    if (result.estado === '1') {
                        await Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: result.mensaje,
                            timer: 3000,
                            showConfirmButton: false
                        });

                        // Cerrar modal y resetear
                        bootstrap.Modal.getInstance(modalNovedades).hide();
                        document.getElementById('formNovedad').reset();
                        location.reload();
                    } else {
                        throw new Error(result.mensaje);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Ocurrió un error al guardar la novedad'
                    });
                }
            });
        }
    });

    // Resetear al cerrar el modal
    modalNovedades.addEventListener('hidden.bs.modal', () => {
        const form = document.getElementById('formNovedad');
        form.reset();
        document.getElementById('imagenPrevisualizacion_novedad').style.display = "none";
        document.getElementById("comentario-container").style.display = "none";
        document.getElementById("comentario-novedad").removeAttribute("required");
    });
}






function validarDecimales(input) {
    const regex = /^\d{0,3}(\.\d{0,2})?$/; // 3 enteros, 2 decimales
    if (!regex.test(input.value)) {
        input.value = input.value.slice(0, -1);
    }
}







async function guardarPreguntas(array, posId) {

    const modalEl = document.getElementById(`modal-${posId}`);
    let modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    const btnPdv = document.getElementById(`btn-pdv-${posId}`);
    const formulario = document.getElementById(`formulario-preguntas-${posId}`);

    // 3. Cerramos el modal
    //  modalInstance.hide();

    if (modalInstance._isShown) {
        modalInstance.hide();
    }

    try {
        // Mostrar loading
        Swal.fire({
            title: 'Guardando...',
            text: 'Por favor espera',
            allowOutsideClick: false,

            didOpen: () => {
                Swal.showLoading();
            }
        });

        const response = await fetch('../../AppAlicorpSupervision/Inserts/insert_evaluacion_visita_web062025.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(array)
        });

        if (!response.ok) {
            throw new Error(`Error en la petición: ${response.status} ${response.statusText}`);
        }

        const resultado = await response.json();
    //    console.log('Respuesta del servidor:', resultado);

        // Mostrar éxito
        Swal.fire({
            icon: 'success',
            title: '¡Guardado!',
            text: 'Las preguntas fueron guardadas correctamente.',
            timer: 3000, // 3 segundos
            showConfirmButton: false
        }).then(() => {
            location.reload(); // Se ejecutará después del timer
        });


        // Desactivar el PDV Ya enviado
        // btnPdv.disabled = false;
        btnPdv.classList.add("pdv_disabled");
        //    btnPdv.setAttribute("disabled", "disabled");
        //    btnPdv.style.backgroundColor = '#9DA1A6';
        //    btnPdv.style.color = '#ffffff';
        formulario.reset();
        // location.reload();
        //  verificarReporte();
        // location.reload();
      //  console.log(btnPdv);



    } catch (err) {
        console.error('Error al guardar preguntas:', err);

        // Mostrar error
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error al guardar las preguntas.'
        });
    }
}



function previsualizarImagen(id) {

    const previsualizacion = document.querySelector(`#imagenPrevisualizacion_${id}`);
    const input_imagen = document.querySelector(`#inputArchivo_${id}`);

    // console.log(previsualizacion);
    // console.log(input_imagen);
    const archivos = input_imagen.files;

    if (!archivos || !archivos.length) {
        previsualizacion.src = "";
        return;
    }
    const primerArchivo = archivos[0];
    const objectURL = URL.createObjectURL(primerArchivo);
    previsualizacion.src = objectURL;
    previsualizacion.style.display = "block";
}
/////////


// function convertirImgBase64(pregId) {
//     return new Promise((resolve, reject) => {
//         const input = document.getElementById(`inputArchivo_${pregId}`);
//         if (input.files.length > 0) {
//             const file = input.files[0];
//             const reader = new FileReader();
//             reader.onload = () => resolve(reader.result.split(',')[1]); // Solo la parte base64
//             reader.onerror = error => reject(error);
//             reader.readAsDataURL(file);
//         } else {
//             resolve('NO_FOTO');
//         }
//     });
// }



function convertirImgBase64(pregId, calidad = 0.50) {
    return new Promise((resolve, reject) => {
        const input = document.getElementById(`inputArchivo_${pregId}`);
        if (!input.files || !input.files[0]) {
            resolve('NO_FOTO');
            return;
        }

        const file = input.files[0];
        const reader = new FileReader();
        const img = new Image();
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        reader.onload = (e) => {
            img.onload = () => {
                // Configurar canvas con dimensiones de la imagen
                canvas.width = img.width;
                canvas.height = img.height;
                
                // Dibujar imagen en canvas y aplicar compresión
                ctx.drawImage(img, 0, 0);
                
                // Convertir a JPEG con calidad ajustable
                const compressedBase64 = canvas.toDataURL('image/jpeg', calidad);
                
                // Extraer solo el Base64 (remover el prefijo data:)
                const base64Data = compressedBase64.split(',')[1];
                resolve(base64Data);
            };
            
            img.onerror = (error) => reject(error);
            img.src = e.target.result;
        };

        reader.onerror = (error) => reject(error);
        reader.readAsDataURL(file);
    });
}




function obtenerPdvsRelevados() {

    // const usuario = document.getElementById("nombre_supervisor").innerText.trim();

    fetch(URL_PDVS_RELEVADOS, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({
            gestor: usuario
        })
    })
        .then(response => response.json())
        .then(data => {

            const pdvsRelevados = data.relevadas || []; // Asigna array vacío si es null/undefined

        //   console.log(pdvsRelevados);

            if (Array.isArray(pdvsRelevados) && pdvsRelevados.length > 0) {



                pintarPdvsRelevados(pdvsRelevados);


                // console.log("Pdv's relevados en el mes: " + pdvsRelevados.length);
                // pdvsRelevados.forEach(pdv => {
                //     const codigo = pdv.codigo.trim(); // Asegúrate de que la propiedad sea "codigo"
                //     const btn = document.getElementById(`btn-pdv-${codigo.trim()}`);
                //     console.log(codigo.trim());
                //     if (btn) {
                //         btn.classList.add("pdv_disabled");
                //     }
                // });

            }



        });

}




function pintarPdvsRelevados(listaPdvs) {
    listaPdvs.forEach(pdv => {
        const container = document.getElementById(`btn-pdv-${pdv.codigo}`);
        if (container) {
            container.classList.add('position-relative'); // de esta forma se añade automaticamente clases
//se le quitan los atributos a esos PDV
            // Quitar atributos de modal del contenedor
            container.removeAttribute('data-bs-toggle');
            container.removeAttribute('data-bs-target');
           
            // Quitar atributos de modal de los botones internos
            container.querySelectorAll('[data-bs-toggle="modal"]').forEach(btn => {
                btn.removeAttribute('data-bs-toggle');
                btn.removeAttribute('data-bs-target');
            });
 
            // Evita duplicar overlays
            if (!container.querySelector('.pdv-overlay-block')) {
                const overlay = document.createElement('div');
                overlay.className = 'pdv-overlay-block';
                overlay.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    Swal.fire({
                        icon: 'error',
                        title: 'Esta evaluacion ya fue registrada'
                        
                       // text: 'Esta evaluacion ya fue registrada'
                    });
                });
                container.appendChild(overlay); // se agrega el overlay creado como hijo del contenedor del PDV
            }
 
            // Deshabilita todos los campos internos
            container.querySelectorAll('input, button, textarea, select').forEach(el => {
                el.disabled = true;
            });
        }
    });
}
 




function verificarReporte() {

    const { fecha, hora } = obtenerFechaHoraActual();

    fetch(URL_PDVS_RELEVADOS_HOY, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({
            supervisor: usuario,
            fecha_hoy: fecha
        })
    })

        .then(response => response.json())
        .then(data => {
            let tieneDatosRelevadosHoy = data.pdvs_evaluados_hoy;
        //    console.log(`Ha evaluado pdvs el dia de hoy? : ${tieneDatosRelevadosHoy ? 'Si' : 'No'}`);

            if (tieneDatosRelevadosHoy) {
                // Activa el boton si hay pdvs evaluados el dia de hoy
                btnGenerarReporte.removeAttribute("disabled");
                btnGenerarReporte.classList.remove("btn-light");
                btnGenerarReporte.classList.add("btn-primary")
            }
        })


}

function verificarPrecios(tipo,preguntaId, value, preguntaTexto) {
    // Revertir el reemplazo de puntos
    const textoOriginal = preguntaTexto.replace(/_dot_/g, '.');
    const validacionLabel = document.getElementById(`validacion-${preguntaId}`);

//    console.log(tipo);

    // Nueva condición para input vacío
    if (value === '') {
        validacionLabel.classList.add('d-none');
        return;
    }

    if (tipo === "MAYORISTAS" || tipo === "AASSRR" || tipo === "AUTOSERVICIOS") {
        
            for (const item of arrPreciosMargenesPVC) {
        let sku = item.sku;
        let pvc_minimo = item.pvc_minimo;
        let pvc_maximo = item.pvc_maximo;
        if (textoOriginal == sku) {
            let precio = parseFloat(value) || 0;
            //    console.log(precio >= pvc_minimo);
            if (precio >= pvc_minimo && precio <= pvc_maximo) {
                validacionLabel.classList.remove('bg-danger');
                validacionLabel.classList.add('bg-success');
                //  validacionLabel.textContent = '✅ Si Cumple con ' + textoDecodificado;
                validacionLabel.textContent = '✅ Si Cumple';
            //    console.log("Si cumple");
            } else {
                validacionLabel.classList.remove('bg-success');
                validacionLabel.classList.add('bg-danger');
                validacionLabel.textContent = '❌ No cumple';
            //    console.log("No cumple");
            }
        }
    }

    } else if (tipo === "TIENDAS") {
        for (const item of arrPreciosPVP) {
            let sku = item.material;
            let pvp = item.pvp;

            if (textoOriginal == sku) {
                let precioIngresado = parseFloat(value) || 0;

                // Separar los valores por coma, convertir a flotante y comparar
                let preciosValidos = pvp.split(',').map(p => parseFloat(p.trim()));
                let coincide = preciosValidos.includes(precioIngresado);

                if (coincide) {
                    validacionLabel.classList.remove('bg-danger');
                    validacionLabel.classList.add('bg-success');
                    validacionLabel.textContent = '✅ Si Cumple';
                } else {
                    validacionLabel.classList.remove('bg-success');
                    validacionLabel.classList.add('bg-danger');
                    validacionLabel.textContent = '❌ No cumple';
                }
            }
        }
    }





    //    console.log("Texto decodificado:", textoOriginal); // INSEC.SAP.MAT.CUCARA. SP.360ML
}


function limitarDigitos(input) {
    const posicionCursor = input.selectionStart;

    // Conservar el punto decimal temporalmente
    let valor = input.value.replace(/,/g, '.').replace(/[^\d.]/g, '');

    // Manejar múltiples puntos
    const tienePunto = valor.includes('.');
    valor = valor.replace(/\.+/g, m => m[0]); // Solo conservar el primer punto

    let [entera = '', decimal = ''] = valor.split('.');

    // Limitar dígitos
    entera = entera.substring(0, 3);
    decimal = decimal.substring(0, 2);

    // Reconstruir valor conservando el punto si el usuario lo está usando
    let nuevoValor = entera;
    if (tienePunto || decimal.length > 0) {
        nuevoValor += '.' + decimal;
    }

    // Restaurar posición del cursor
    const cambioLongitud = nuevoValor.length - input.value.length;
    const nuevaPosicion = Math.max(0, posicionCursor + cambioLongitud);

    input.value = nuevoValor;
    input.setSelectionRange(nuevaPosicion, nuevaPosicion);
}




function generarReporte() {

    let { fecha, hora } = obtenerFechaHoraActual();

    fecha = fecha.replaceAll("/", "");
    hora = hora.replaceAll(":", "");

    mostrarMensajeDeCarga();

    fetch(URL_GENERAR_REPORTE + `?supervisor=${usuario}`, { method: 'GET', headers: { 'Content-Type': 'application/json' } })
        .then(response => response.json())
        .then(data => {

        //    console.log(data);
            let nombreExcel = "reporteEpv" + usuario + fecha + hora;
        //    console.log(nombreExcel);
            let urlExcel = data.excel_url;
        //    console.log(urlExcel);
            Swal.close();
            descargarExcel(nombreExcel, urlExcel);
        })

}





function obtenerFechaHoraActual() {
    // Obtener fecha y hora actual
    const fechaHoraActual = new Date();

    // Función para agregar ceros a la izquierda si es necesario
    const pad = (numero) => numero.toString().padStart(2, '0');

    // Obtener componentes de la fecha
    const dia = pad(fechaHoraActual.getDate());
    const mes = pad(fechaHoraActual.getMonth() + 1); // Los meses van de 0-11
    const año = fechaHoraActual.getFullYear();

    // Obtener componentes de la hora
    const horas = pad(fechaHoraActual.getHours());
    const minutos = pad(fechaHoraActual.getMinutes());
    const segundos = pad(fechaHoraActual.getSeconds());

    return {
        fecha: `${dia}/${mes}/${año}`,
        hora: `${horas}:${minutos}:${segundos}`
    };
}

async function descargarExcel(nombre_archivo, url) {
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const blob = await response.blob();
        const urlBlob = window.URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = urlBlob;
        a.download = nombre_archivo;

        document.body.appendChild(a);
        a.click();

        window.URL.revokeObjectURL(urlBlob);
        document.body.removeChild(a);
    } catch (error) {
        console.error("Error crítico:", error);
        alert(`No se pudo descargar: ${error.message}`);
    }
}

function mostrarMensajeDeCarga() {
    return Swal.fire({
        title: 'Procesando solicitud',
        text: 'Espere por favor...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        allowEnterKey: false,
        showConfirmButton: false,
        timer: 2000,
        didOpen: () => {
            Swal.showLoading();
        },
        // Background de fondo
        backdrop: 'rgba(0,0,0,0.8)'
    });
}

async function obtenerMargenesPrecio() {
    try {
        const response = await fetch(URL_GET_MARGENES_PRECIO_PVC, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ operator: usuario })
        });

        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);

        const { precios_margen_pvc: datos = [] } = await response.json();

        if (datos.length > 0) {
            arrPreciosMargenesPVC.push(...datos);
        //    console.log('Margenes actualizados:', arrPreciosMargenesPVC);
        }

        return datos; // Opcional: devolver datos para uso posterior
    } catch (error) {
        console.error('Error obteniendo márgenes:', error);
        // Considera relanzar el error o manejar según necesidades
        throw error;
    }
}

async function obtenerPreciosPvp() {
    try {
        const response = await fetch(URL_GET_PRECIO_PVP, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ operator: usuario })
        });

        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);

        const { precios_pvp: datos = [] } = await response.json();

        if (datos.length > 0) {
            arrPreciosPVP.push(...datos);
        //    console.log('Precios PVP:', arrPreciosPVP);
        }

        return datos; // Opcional: devolver datos para uso posterior
    } catch (error) {
        console.error('Error obteniendo precios pvp:', error);
        // Considera relanzar el error o manejar según necesidades
        throw error;
    }
}


async function obtenerNovedades() {
    try {
        const response = await fetch(URL_GET_NOVEDADES, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ operator: usuario })
        });

        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);

        const { novedades = [] } = await response.json();

        if (novedades.length > 0) {
            arrNovedades.push(...novedades.map(({ novedad }) => novedad));
        //    console.log(arrNovedades);
        }

        // return novedades; // Opcional: devolver los datos para mayor flexibilidad
    } catch (error) {
        console.error('Error al obtener novedades:', error);
        // Considera manejar el error o relanzarlo según tu flujo
        throw error;
    }
}

// Función para controlar los precios
function habilitarPrecioInput(pregunta, habilitar) {
    const inputsPrecio = document.querySelectorAll(`[data-pregunta="${pregunta}"]`);
    inputsPrecio.forEach(input => {
        input.disabled = !habilitar;
        if (!habilitar) input.value = ""; // Limpiar si se deshabilita
    });
}

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-cerrar-modal')) {
        e.preventDefault();
        new bootstrap.Modal(document.getElementById('confirmacionModal')).show();
    }
});

document.getElementById('btnConfirmarSalir').addEventListener('click', function () {
    // Cierra todos los modales abiertos no tan recomendable
    document.querySelectorAll('.modal.show').forEach(modal => {
        bootstrap.Modal.getInstance(modal).hide();
    });
});




// funcionalidad para el modal con el select2 MPIN 1
document.addEventListener('DOMContentLoaded', function() {
    var modalPreguntasAlicorp = document.getElementById('modalPreguntasAlicorp');
    var puntoVentaSelect = document.getElementById('puntoVenta');
    var elegirCiudadSelect = document.getElementById('elegirCiudadSelect');
    var distribuidoraContainer = document.getElementById('distribuidoraContainer');
    var distribuidoraTexto = document.getElementById('distribuidoraTexto');
    var provinciaContainer = document.getElementById('provinciaContainer');
    var provinciaTexto = document.getElementById('provinciaTexto');
    // nuevas referencias para CIUDAD ELEGIDA
    var ciudadElegidaContainer = document.getElementById('ciudadElegidaContainer');
    var ciudadElegidaTexto = document.getElementById('ciudadElegidaTexto');

    /**
     * Carga las ciudades de una provincia específica.
     * @param {string} provincia - La provincia de la cual cargar las ciudades.
     */
    function cargarCiudades(provincia) {
        elegirCiudadSelect.innerHTML = '<option selected disabled>Cargando ciudades...</option>';

        fetch(`getciudades.php?provincia=${encodeURIComponent(provincia)}`)
            .then(response => {
                if (!response.ok) throw new Error('Respuesta del servidor no válida.');
                return response.json();
            })
            .then(data => {
                elegirCiudadSelect.innerHTML = '<option selected disabled>Seleccione una Ciudad:</option>';
                if (data.error) {
                    console.error('Error desde PHP:', data.error);
                    elegirCiudadSelect.innerHTML = `<option selected disabled>Error: ${data.error}</option>`;
                } else if (data.ciudades && data.ciudades.length > 0) {
                    data.ciudades.forEach(ciudad => {
                        const option = document.createElement('option');
                        option.value = ciudad;
                        option.textContent = ciudad;
                        elegirCiudadSelect.appendChild(option);
                    });
                } else {
                    elegirCiudadSelect.innerHTML = '<option selected disabled>No hay ciudades disponibles</option>';
                }
            })
            .catch(error => {
                console.error('Error en fetch:', error);
                elegirCiudadSelect.innerHTML = '<option selected disabled>Error de conexión</option>';
            });
    }

    // Lógica al ABRIR el modal
    modalPreguntasAlicorp.addEventListener('show.bs.modal', function() {
        if ($(puntoVentaSelect).data('select2')) {
            $(puntoVentaSelect).select2('destroy');
        }

        // Resetea y deshabilita el select de ciudad al inicio.
        elegirCiudadSelect.disabled = true;
        delete elegirCiudadSelect.dataset.provincia; // Limpia datos anteriores
        delete elegirCiudadSelect.dataset.cargado; // Limpia datos anteriores
        elegirCiudadSelect.innerHTML = '<option selected disabled>Seleccione una Ciudad:</option>'; // Ensure city select is reset

        // Inicializa el Select2 para puntos de venta
        $(puntoVentaSelect).select2({
            dropdownParent: $('#modalPreguntasAlicorp'),
            placeholder: 'Buscar un punto de venta por código o nombre',
            minimumInputLength: 2,// para la busqueda minimo dos caracteres
            ajax: {
                url: 'getpuntospdv.php',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        buscar: params.term
                    };
                },
                processResults: function (data) {
                    if (data.error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudieron cargar los puntos de venta: ' + data.error
                        });
                        return { results: [] };
                    }
                    return {
                        results: data.map(pdv => ({
                            id: pdv.pos_id,
                            text: pdv.pos_name + ' (' + pdv.pos_id + ')',
                            provincia: pdv.provincia,
                            distri: pdv.distribuidor,
                            codigo:pdv.pos_id,
                            punto_venta:pdv.pos_name

                        }))
                    };
                },
                cache: true
            }
        });

        // Hide all info containers on modal open
        distribuidoraContainer.classList.add('d-none');
        distribuidoraTexto.textContent = '';
        provinciaContainer.classList.add('d-none');
        provinciaTexto.textContent = '';
        ciudadElegidaContainer.classList.add('d-none'); // HIDE NEW CONTAINER
        ciudadElegidaTexto.textContent = ''; // CLEAR NEW TEXT
    });

    // LÓGICA al SELECCIONAR un punto de venta
    $(puntoVentaSelect).on('select2:select', function(e) {
        var provincia = e.params.data.provincia;
        var distri = e.params.data.distri;
        codigo_seleccionado = e.params.data.codigo;
        punto_venta_seleccionado = e.params.data.punto_venta;

        distribuidoraContainer.classList.remove('d-none');
        distribuidoraTexto.textContent = distri;
        provinciaContainer.classList.remove('d-none');
        provinciaTexto.textContent = provincia;

        // Reset and hide city-related info when a new PDV is selected
        ciudadElegidaContainer.classList.add('d-none'); // al principio esta etiqueta esta oculta
        ciudadElegidaTexto.textContent = ''; 
        document.getElementById('formulario-preguntasNuevas').innerHTML = ''; // Clear form on new PDV select
        document.getElementById('contador-preguntasNuevas').textContent = ''; // Clear counter

        // **LÓGICA CAMBIADA**: Ahora solo se habilita y prepara el select. No se cargan las ciudades.
        elegirCiudadSelect.disabled = false; // Se habilita
        elegirCiudadSelect.dataset.provincia = provincia; // Se guarda la provincia para usarla después
        elegirCiudadSelect.dataset.cargado = 'false'; // Se marca como 'no cargado'
        elegirCiudadSelect.innerHTML = '<option selected disabled>Seleccione una Ciudad:</option>';
    });

    // Evento para cargar las ciudades solo al hacer clic en el select.
    elegirCiudadSelect.addEventListener('click', function() {
        const provincia = this.dataset.provincia;
        const yaFueCargado = this.dataset.cargado === 'true';

        if (provincia && !yaFueCargado) {
            cargarCiudades(provincia);
            this.dataset.cargado = 'true'; // Se marca como 'cargado' para no repetir la llamada.
        }
    });

    const containerComentario = document.getElementById("container-comentario");

    // CUANDO SE SELECCIONA UNA CIUDAD, TRAE LOS SKU Y ACTUALIZA EL TEXTO DE CIUDAD ELEGIDA
    elegirCiudadSelect.addEventListener('change', function () {
        const ciudad = this.value;
        const puntoVentaId = $('#puntoVenta').val();

        
        if (ciudad.includes("Seleccione")) {
            containerComentario.style.display = "none";
        } else {
            containerComentario.style.display = "block";
        }


        if (!ciudad || !puntoVentaId) return;

    // ACTUALIZAR LA NUEVA VISUALIZACIÓN DE LA CIUDAD ELEGIDA
        ciudadElegidaTexto.textContent = ciudad;
        ciudadElegidaContainer.classList.remove('d-none');


        fetch(`getpreguntasalicorp.php?ciudad=${encodeURIComponent(ciudad)}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    alert("Error: " + data.error);
                    return;
                }
                renderPreguntas(data, puntoVentaId);
            })
            .catch(err => {
                console.error("Error en fetch:", err);
                alert("Error al cargar las preguntas. Revise la consola.");
            });
    });

    // --- NUEVA FUNCIÓN DE VALIDACIÓN DE MARGEN ---
    function validateMargin(inputElement) {

           const raw = (inputElement.value || '').trim().replace(',', '.'); // normaliza coma->punto
        const inputUsuario = parseFloat(inputElement.value); // esta sera el precio que ingresa el usuario en el input 
        const pvc = parseFloat(inputElement.dataset.pvc);// valor que se compara de la
        const marginPercentage = parseFloat(inputElement.dataset.margen) / 100; //coge el margen de la base y lo divide para 100
        const skuDescription = inputElement.closest('.list-group-item').querySelector('p.text-dark').textContent;

        const validationIconElement = inputElement.closest('.d-flex').querySelector('.validation-icon');

        validationIconElement.classList.add('d-none');
        inputElement.classList.remove('is-invalid'); // Siempre remueve para re-evaluar
        validationIconElement.innerHTML = ''; // Limpiar iconos anteriores

        if (raw === '') {
        return;
    }

     // ) SOLO un punto "." (o variantes como ",") -> ALERTA de valor incompleto
    if (raw === '.' || raw === ',') {
        inputElement.classList.add('is-invalid');
        validationIconElement.innerHTML = '<i class="bi bi-exclamation-circle-fill text-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="Valor incompleto"></i>';
        validationIconElement.classList.remove('d-none');

        Swal.fire({
            icon: 'error',
            title: 'Precio invalido en SKU:',
             html:  `${skuDescription}`,
           confirmButtonText: 'ACEPTAR',
                confirmButtonColor: '#d33'
        });

        inputElement.value = '';
        inputElement.focus();
        // re-init tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });
        return;
    }




        if (isNaN(inputUsuario)) { //isNan significa que is not a number
            // Si el campo está vacío o es 0, no mostrar SweetAlert, solo limpiar el error si existía.
             
            return;
        }

       if (inputUsuario === 0) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'El precio ingresado no debe ser cero',
      confirmButtonText: 'ACEPTAR',
     confirmButtonColor: '#d33'
    });
    inputElement.value = '';
   // input.classList.add('is-invalid');
    return;
  }
  

  
        

//VALIDACION DE CONFIGUACION DE DATOS DESDE LA BASE, PARA PREVENIR GUARDAR DATOS INCORRECTOS TRAIDOS DE BASE
        // if (isNaN(pvc) || isNaN(marginPercentage)) {
        //     console.warn(`Datos de PVC o Margen no válidos para SKU: ${skuDescription}`);
        //     inputElement.classList.add('is-invalid');
        //     validationIconElement.innerHTML = '<i class="bi bi-exclamation-circle-fill text-warning" data-bs-toggle="tooltip" data-bs-placement="top" title="Datos de validación faltantes"></i>';
        //     validationIconElement.classList.remove('d-none');
        //     return;
        // }

        const min = pvc * (1 - marginPercentage);
        const max = pvc * (1 + marginPercentage);

        if (inputUsuario < min || inputUsuario > max ) {
            inputElement.classList.add('is-invalid');
            validationIconElement.innerHTML = '<i class="bi bi-exclamation-circle-fill text-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="¡Valor fuera de margen!"></i>';
            validationIconElement.classList.remove('d-none');
           // var text ="El precio ingresado sobrepasa el margen permitido ";
//sweet alert para cuaando se pasa del limite:
            Swal.fire({
                icon: 'info',
                title: 'Atencion!',
                html:  ` El precio ingresado sobrepasa el margen permitido<br>
El SKU <strong>"${skuDescription}"</strong>`,
                confirmButtonText: 'ACEPTAR',
                confirmButtonColor: '#d33'
            });
        } else {
            // Si está dentro del margen, solo asegura que no tenga borde de error
            inputElement.classList.remove('is-invalid');
        }
        
        // Re-inicializar tooltips para los iconos que se agregaron dinámicamente
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    }

    
    function renderPreguntas(preguntas, posId) {

        preguntasOriginales = preguntas;

        const form = document.getElementById('formulario-preguntasNuevas');
        form.innerHTML = ''; // Limpia preguntas anteriores

        const contador = document.getElementById('contador-preguntasNuevas');
        if (contador) {
            contador.textContent = `Total de preguntas: ${preguntas.length}`;
        }

        // Agrupar los datos: Categoría -> Tipo de Empresa -> Marca -> SKUs
        const groupedData = preguntas.reduce((acc, item) => {
            if (!acc[item.categoria]) {
                acc[item.categoria] = {};
            }
            if (!acc[item.categoria][item.tipo_empresa]) {
                acc[item.categoria][item.tipo_empresa] = {};
            }
            if (!acc[item.categoria][item.tipo_empresa][item.marca]) {
                acc[item.categoria][item.tipo_empresa][item.marca] = [];
            }
            acc[item.categoria][item.tipo_empresa][item.marca].push(item);
            return acc;
        }, {});

        let html = '';

        for (const categoria in groupedData) {
            if (Object.hasOwnProperty.call(groupedData, categoria)) {
                // Iniciar la tarjeta principal para toda la categoría
                html += `
                    <div class="card mb-4 border-secondary shadow-sm">
                        <div class="card-header bg-danger text-white text-uppercase text-center fw-bold p-2">
                            ${categoria}
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                `;

              // Iterar a través de Tipo Empresa dentro de cada Categoría
                for (const tipoEmpresa in groupedData[categoria]) {
                    if (Object.hasOwnProperty.call(groupedData[categoria], tipoEmpresa)) {
                        // Add Tipo Empresa header
                        html += `
                                <div class="list-group-item bg-light text-center fw-bold py-2 border-top border-bottom">
                                    ${tipoEmpresa}
                                </div>
                        `;
                // Recorre Marca dentro de cada Tipo Empresa
                        for (const marca in groupedData[categoria][tipoEmpresa]) {
                            if (Object.hasOwnProperty.call(groupedData[categoria][tipoEmpresa], marca)) {
                                // Agregar encabezado de Marca
                                html += `
                                        <div class="list-group-item px-3 py-2 bg-light-subtle fw-bold text-danger">
                                            ${marca}
                                        </div>
                                `;

                                const items = groupedData[categoria][tipoEmpresa][marca];
                                items.forEach((item) => {
                                    // Añadimos el artículo SKU individual con su entrada
                                    // // Añadimos data-pvc y data-margen a cada input
                                    html += `
    <div class="list-group-item d-flex flex-column flex-md-row justify-content-between align-items-md-center py-2 px-3">
        <div class="me-md-3 mb-2 mb-md-0">
            <p class="mb-0 text-dark" data-sku="${item.sku}">${item.sku_description || item.sku}</p>
        </div>
        <div class="d-flex align-items-center flex-grow-1">
            <span class="me-2 text-muted">$</span>
            <input
                type="text"
                inputmode="decimal"
                class="form-control form-control-sm input-precio"
                placeholder="0.00"
                step="0.01"
                min="0"
                name="sku_precio[${item.id}]"
                id="precio_${item.id}"
                data-pvc="${item.pvc || 0}"
                data-margen="${parseInt(item.margen)}"
            >
            <span class="ms-2 validation-icon">
            </span>
        </div>
        <input type="hidden" name="item_id[${item.id}]" value="${item.id}">
    </div>
                                    `;
                                });
                            }
                        }
                    }
                }

            
                html += `
                            </div>
                        </div>
                    </div>
                `;
            }
        }

        // Agrega el input pos_id oculto solo una vez al formulario
        html += `<input type="hidden" name="pos_id" value="${posId}">`;

        form.innerHTML = html;
     //   <label for="recomendacion_${preg.id}" class="form-label mt-2 text-center w-100 fw-bold ">Comentario:</label>

        // Agregar escuchas de eventos para las entradas numéricas y la validación de margen
        form.querySelectorAll('.input-precio').forEach(input => {
            // Normalización del input (coma a punto, límite de caracteres)
            input.addEventListener('input', function() {
                let value = this.value;

                // 1. Convertir comas a puntos inmediatamente
                value = value.replace(',', '.');

                // 2. Permitir solo dígitos y un punto decimal
                value = value.replace(/[^\d.]/g, '');

                // 3. Asegurar que solo haya un punto decimal
                const parts = value.split('.');
                if (parts.length > 2) {
                    value = parts[0] + '.' + parts.slice(1).join('');
                }

                // 4. Limitar a 4 enteros antes del punto
                let integerPart = parts[0];
                let decimalPart = parts[1] || '';

                if (integerPart.length > 4) {
                    integerPart = integerPart.substring(0, 4);
                }

                // 5. Limitar a 2 decimales después del punto
                if (decimalPart.length > 2) {
                    decimalPart = decimalPart.substring(0, 2);
                }

                // Reconstruir el valor
                if (value.includes('.')) {
                    this.value = integerPart + '.' + decimalPart;
                } else {
                    this.value = integerPart;
                }

                // Opcional: Si el input está vacío o solo tiene un punto, permitirlo inicialmente
                //  if ( value === '.') {
                //      this.value = value;
                //  }
            });

            // Llamar a la función de validación de margen cuando el input pierde el foco
            input.addEventListener('focusout', function() {
                validateMargin(this);
            });

            // Inicializar la validación al cargar el input (útil si hay valores pre-llenados)
            validateMargin(input); 
        });
        // Inicializar tooltips para los iconos después de que se han renderizado todos los elementos
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    }

    const btnGuardar = document.getElementById('btnGuardarCambios');
    const form = document.getElementById('formulario-preguntasNuevas');


    btnGuardar.addEventListener('click', function (event) {
    event.preventDefault();

    const inputsPrecio = form.querySelectorAll('.input-precio');
    let hayErrorDeMargen = false;

    // Validar márgenes
    inputsPrecio.forEach(input => {
        validateMargin(input);
        if (input.classList.contains('is-invalid')) {
            hayErrorDeMargen = true;
        }
    });

    if (hayErrorDeMargen) {
        Swal.fire({
            icon: 'error',
            title: 'Error de Validación',
            html: '<br>Corríge(los) el/los precio(s) que sobrepasen el margen permitido.',
            confirmButtonText: 'Entendido'
        });
        return;
    }

    const tieneAlMenosUnValorValido = Array.from(inputsPrecio).some(input => {
        return input.value.trim() !== '' && !input.classList.contains('is-invalid');
    });

    if (!tieneAlMenosUnValorValido) {
        Swal.fire({
            icon: 'info',
            html: '<strong>Debe ingresar al menos un precio válido para guardar.</strong>',
            confirmButtonText: 'Entendido'
        });
        return;
    }

    // ✅ Mostrar mensaje de confirmación
    Swal.fire({
        icon: 'info',
        title: '¿Confirmación?',
        html: '¿Desea guardar la información?',
        showCancelButton: true,
        confirmButtonText: 'CONFIRMAR',
        cancelButtonText: 'CANCELAR',
        customClass: {
            confirmButton: 'btn btn-primary me-2',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    }).then((result) => {
        if (!result.isConfirmed) return;

        // 🔽 Lógica de guardado original

        const pos_id = form.querySelector('input[name="pos_id"]').value;
        const distribuidor = document.getElementById('distribuidoraTexto').textContent.trim();
        const provincia = document.getElementById('provinciaTexto').textContent.trim();
        const ciudad = document.getElementById('ciudadElegidaTexto').textContent.trim();
        const comentario = document.getElementById('txtComentario');

        let str_comentario = comentario.value.trim();

        const fecha = new Date();
        const opciones = { day: '2-digit', month: '2-digit', year: 'numeric' };
        const fechaStr = fecha.toLocaleDateString('es-ES', opciones);
        const horaStr = fecha.toTimeString().split(' ')[0];

        const resultados = [];
        let pendientes = 0;
        let errores = 0;

        inputsPrecio.forEach(input => {
            const idMatch = input.name.match(/\[(\d+)\]/);
            if (!idMatch) return;
            const id = idMatch[1];
            const precio = input.value.trim();
            if (precio === '') return;

            const listItem = input.closest('.list-group-item');
            if (!listItem) return;

            const card = listItem.closest('.card');
            const categoria = card?.querySelector('.card-header')?.textContent.trim() || '';

            let marca = '';
            let actual = listItem.previousElementSibling;
            while (actual && actual.classList.contains('list-group-item')) {
                if (actual.classList.contains('fw-bold') && actual.classList.contains('text-danger')) {
                    marca = actual.textContent.trim();
                    break;
                }
                actual = actual.previousElementSibling;
            }

            const itemExtra = preguntasOriginales.find(p => p.id == id) || {};
            const subcategoria = itemExtra.subcategoria || '';
            const contenido = itemExtra.contenido || '';
            const presentacion = itemExtra.presentacion || '';
            const variante = itemExtra.variante || '';
            const fabricante = itemExtra.fabricante || '';
            const tipoEmpresa = itemExtra.tipo_empresa || '';
            const margen = itemExtra.margen;
            const pvp = itemExtra.pvp || '';
            const skuReal = itemExtra.sku || '';

            const payload = {
                codigo: codigo_seleccionado,
                punto_venta: punto_venta_seleccionado,
                distribuidor,
                provincia,
                ciudad,
                usuario,
                tipo: tipoEmpresa,
                categoria,
                subcategoria,
                marca,
                presentacion,
                contenido,
                variante,
                fabricante,
                sku: skuReal,
                precio_ingresado: parseFloat(precio),
                pvp,
                pvc: parseFloat(input.dataset.pvc || 0),
                margen,
                comentario: str_comentario,
                origen: 'WEB',
                fecha: fechaStr,
                hora: horaStr
            };

            pendientes++;

            fetch('../../AppAlicorpSupervision/Inserts/insert_precios_cliente.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.estado === '1') {
                        resultados.push(`✅ SKU ${skuReal} guardado con éxito`);
                    } else {
                        errores++;
                        resultados.push(`⚠️ SKU ${skuReal} falló: ${data.mensaje}`);
                    }
                })
                .catch(error => {
                    errores++;
                    resultados.push(`❌ Error con SKU ${skuReal}: ${error.message}`);
                })
                .finally(() => {
                    pendientes--;
                    if (pendientes === 0) {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('modalPreguntasAlicorp'));
                        if (modal) modal.hide();

                        containerComentario.style.display = "none";
                        comentario.value = '';

                        if (errores === 0) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Registros guardados!',
                                text: 'Todos los precios fueron guardados exitosamente.',
                                confirmButtonText: 'Cerrar'
                            });
                        } else {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Algunos registros fallaron',
                                html: resultados.join('<br>'),
                                confirmButtonText: 'Cerrar',
                                width: 600
                            });
                        }
                    }
                });
        });

        const modal = bootstrap.Modal.getInstance(document.getElementById('modalPreguntasAlicorp'));
        if (modal) modal.hide();
    });
});






    // Lógica al CERRAR el modal
    modalPreguntasAlicorp.addEventListener('hidden.bs.modal', function() {
        if ($(puntoVentaSelect).data('select2')) {
            $(puntoVentaSelect).select2('destroy');
        }
        $(puntoVentaSelect).val(null).trigger('change');

        // Hide and clear all info containers
        distribuidoraContainer.classList.add('d-none');
        distribuidoraTexto.textContent = '';
        provinciaContainer.classList.add('d-none');
        provinciaTexto.textContent = '';
        ciudadElegidaContainer.classList.add('d-none');
        ciudadElegidaTexto.textContent = '';

        // Reset city select
        elegirCiudadSelect.innerHTML = '<option selected disabled>Seleccione una Ciudad:</option>';
        elegirCiudadSelect.disabled = true;
        delete elegirCiudadSelect.dataset.provincia;
        delete elegirCiudadSelect.dataset.cargado;

        // Clear the form content when modal is closed
        document.getElementById('formulario-preguntasNuevas').innerHTML = '';
        document.getElementById('contador-preguntasNuevas').textContent = '';
    });
});

//bloque para la primera alerta al entrar al nuevo modal de PRECIOS

document.addEventListener('DOMContentLoaded', function() {
    const btnPreciosAlicorp = document.getElementById('btnPreciosAlicorp');
    const modalPreguntasAlicorp = new bootstrap.Modal(document.getElementById('modalPreguntasAlicorp'));

    btnPreciosAlicorp.addEventListener('click', function() {
        Swal.fire({
            title: 'Atención!',
            text: 'Para los sku"s que no esten codificados en el punto de venta, dejar el campo de precio en vacio.',
            icon: 'info',
            showCloseButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ACEPTAR',
            customClass: { 
                confirmButton: 'rounded-confirm-button' // <--- Nueva clase CSS
            }
        }).then(() => {
            modalPreguntasAlicorp.show();
        });
    });
});




// document.getElementById("buscar").addEventListener('input', (e) => {
//     let value = e.target.value;
//     console.log(value);
    
//     if (value === '') {
//         e.target.value = ''; // Establece el valor del input como vacío
//         // También puedes agregar aquí otras acciones si necesitas resetear algo más
//         location.reload();
//     }
// });
