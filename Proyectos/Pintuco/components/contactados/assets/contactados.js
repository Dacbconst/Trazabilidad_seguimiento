(function () {
    var GETTERS_BASE = document.getElementById('contactadosApp').dataset.gettersBase;
    var currentRows = [];
    var FILAS_POR_PAGINA = 15;
    var paginaActual = 1;
    var selectedIds = {}; // { id: true } — IDs marcados, no índices (sobrevive a la paginación)

    function formatFechaHora(valor) {
        if (!valor) return '—';
        var partes = valor.split(' ');
        var fechaPartes = partes[0].split('-');
        if (fechaPartes.length !== 3) return valor;
        var fecha = fechaPartes[2] + '/' + fechaPartes[1] + '/' + fechaPartes[0];
        if (partes.length < 2) return { fecha: fecha, hora: '' };
        return { fecha: fecha, hora: partes[1].slice(0, 5) };
    }

    function iniciales(nombre) {
        if (!nombre) return '?';
        return nombre.trim().charAt(0).toUpperCase();
    }

    function celdaTexto(texto, claseSpan) {
        var td = document.createElement('td');
        var span = document.createElement('span');
        if (claseSpan) span.className = claseSpan;
        span.textContent = texto || '—';
        td.appendChild(span);
        return td;
    }

    // Ícono de coordenada al inicio del texto de la dirección — sin
    // consumir ninguna API: el link de Google Maps con "?q=lat,lng" abre
    // el mapa directo en esas coordenadas usando lo que ya está guardado
    // en la BD, gratis. Si el contacto no tiene lat/lng todavía (no se le
    // confirmó pin en Agendamientos), no se pinta el ícono, solo el texto.
    function celdaDireccion(r) {
        var td = document.createElement('td');
        var wrap = document.createElement('div');
        wrap.className = 'ctc-direccion';
        var lat = parseFloat(r.latitud);
        var lng = parseFloat(r.longitud);
        if (lat && lng) {
            var link = document.createElement('a');
            link.href = 'https://maps.google.com/maps?q=' + lat + ',' + lng;
            link.target = '_blank';
            link.rel = 'noopener';
            link.className = 'ctc-pin-link';
            link.title = 'Ver en Google Maps';
            link.innerHTML = '<i class="glyphicon glyphicon-map-marker"></i>';
            wrap.appendChild(link);
        }
        wrap.appendChild(document.createTextNode(r.direccion || '—'));
        td.appendChild(wrap);
        return td;
    }

    function celdaCorreoTelefono(r) {
        var td = document.createElement('td');
        var correo = document.createElement('div');
        correo.className = 'ctc-correo';
        correo.textContent = r.mail || '—';
        td.appendChild(correo);

        var tel = r.telefono || '';
        if (r.telefono_convencional) tel = tel ? (tel + '-' + r.telefono_convencional) : r.telefono_convencional;
        if (tel) {
            var telDiv = document.createElement('div');
            telDiv.className = 'ctc-telefono';
            telDiv.textContent = tel;
            td.appendChild(telDiv);
        }
        return td;
    }

    function celdaPromotor(r) {
        var td = document.createElement('td');
        var wrap = document.createElement('div');
        wrap.className = 'ctc-promotor';
        var avatar = document.createElement('div');
        avatar.className = 'ctc-promotor-avatar';
        avatar.textContent = iniciales(r.usuario);
        var nombre = document.createElement('span');
        nombre.className = 'ctc-promotor-nombre';
        nombre.textContent = r.usuario || 'Sin promotor';
        wrap.appendChild(avatar);
        wrap.appendChild(nombre);
        td.appendChild(wrap);
        return td;
    }

    function celdaRegistrado(r) {
        var td = document.createElement('td');
        var partes = formatFechaHora(r.fecha_registro);
        if (typeof partes === 'string') {
            td.textContent = partes;
            return td;
        }
        var fecha = document.createElement('div');
        fecha.className = 'ctc-registrado-fecha';
        fecha.textContent = partes.fecha;
        td.appendChild(fecha);
        if (partes.hora) {
            var hora = document.createElement('div');
            hora.className = 'ctc-registrado-hora';
            hora.textContent = partes.hora;
            td.appendChild(hora);
        }
        return td;
    }

    function pintarFila(r) {
        var tr = document.createElement('tr');
        var id = String(r.id);

        var tdCheck = document.createElement('td');
        var check = document.createElement('input');
        check.type = 'checkbox';
        check.checked = !!selectedIds[id];
        check.addEventListener('change', function (ev) {
            ev.stopPropagation();
            if (check.checked) { selectedIds[id] = true; } else { delete selectedIds[id]; }
            actualizarBarraSeleccion();
        });
        tdCheck.appendChild(check);
        tr.appendChild(tdCheck);

        tr.appendChild(celdaTexto(r.empresa, 'ctc-empresa'));
        tr.appendChild(celdaTexto(r.contacto, 'ctc-contacto'));
        tr.appendChild(celdaDireccion(r));
        tr.appendChild(celdaCorreoTelefono(r));
        tr.appendChild(celdaPromotor(r));
        tr.appendChild(celdaTexto(r.pdv));
        tr.appendChild(celdaRegistrado(r));

        return tr;
    }

    // Solo se busca por empresa/contacto/correo/dirección — PDV se excluyó
    // a pedido explícito del usuario (el campo ya no dice "buscar PDV...").
    function filasFiltradas() {
        var q        = document.getElementById('contactadosBusqueda').value.toLowerCase().trim();
        var mercader = document.getElementById('contactadosMercaderista').value;

        return currentRows.filter(function (r) {
            if (mercader && r.usuario !== mercader) return false;
            if (q) {
                var coincide = [r.contacto, r.empresa, r.mail, r.direccion].some(function (v) {
                    return (v || '').toLowerCase().indexOf(q) !== -1;
                });
                if (!coincide) return false;
            }
            return true;
        });
    }

    // ---------------------------------------------------------------
    // Selección + descarga: "Descargar selección" queda siempre visible
    // (nunca aparece/desaparece), solo cambia de apagado a activo. El
    // check del header selecciona/deselecciona TODO lo filtrado (no solo
    // la página visible), para que "descargar selección" con el filtro
    // puesto equivalga a "descargar todo lo que ves en ese filtro".
    // ---------------------------------------------------------------
    function actualizarBarraSeleccion() {
        var ids = Object.keys(selectedIds);
        var count = ids.length;

        var btnSel = document.getElementById('contactadosDescargarSeleccion');
        var textoSel = document.getElementById('contactadosSeleccionTexto');
        btnSel.disabled = count === 0;
        btnSel.classList.toggle('is-activo', count > 0);
        textoSel.textContent = count > 0 ? ('Descargar selección (' + count + ')') : 'Descargar selección';

        document.getElementById('contactadosSeleccionInfo').textContent =
            count > 0 ? (count + ' seleccionado' + (count === 1 ? '' : 's')) : '';

        var filtradas = filasFiltradas();
        var idsFiltrados = filtradas.map(function (r) { return String(r.id); });
        var checkTodo = document.getElementById('contactadosCheckTodo');
        checkTodo.checked = idsFiltrados.length > 0 && idsFiltrados.every(function (id) { return selectedIds[id]; });
    }

    function toggleSeleccionarTodo() {
        var idsFiltrados = filasFiltradas().map(function (r) { return String(r.id); });
        var todosMarcados = idsFiltrados.length > 0 && idsFiltrados.every(function (id) { return selectedIds[id]; });
        if (todosMarcados) {
            idsFiltrados.forEach(function (id) { delete selectedIds[id]; });
        } else {
            idsFiltrados.forEach(function (id) { selectedIds[id] = true; });
        }
        renderizar();
    }

    // Cambiar cualquier filtro resetea la selección: mezclar selección de
    // un filtro anterior con uno nuevo es más confuso que útil, y evita el
    // caso de "tengo 5 marcados pero ya no veo 2 de ellos en pantalla".
    function resetearSeleccionYRenderizar() {
        selectedIds = {};
        paginaActual = 1;
        renderizar();
    }

    // ---------------------------------------------------------------
    // Paginación (client-side)
    // ---------------------------------------------------------------
    function renderizarPaginacion(totalFilas) {
        var totalPaginas = Math.max(1, Math.ceil(totalFilas / FILAS_POR_PAGINA));
        if (paginaActual > totalPaginas) paginaActual = totalPaginas;

        var info = document.getElementById('contactadosPaginacionInfo');
        var paginaEl = document.getElementById('contactadosPaginaActual');
        var btnAnterior = document.getElementById('contactadosPagAnterior');
        var btnSiguiente = document.getElementById('contactadosPagSiguiente');

        if (!totalFilas) {
            info.textContent = '0 contactos';
        } else {
            var desde = (paginaActual - 1) * FILAS_POR_PAGINA + 1;
            var hasta = Math.min(totalFilas, paginaActual * FILAS_POR_PAGINA);
            info.textContent = desde + '–' + hasta + ' de ' + totalFilas + ' contactos';
        }
        paginaEl.textContent = 'Página ' + paginaActual + ' de ' + totalPaginas;
        btnAnterior.disabled = paginaActual <= 1;
        btnSiguiente.disabled = paginaActual >= totalPaginas;
    }

    function renderizar() {
        var tbody = document.getElementById('contactadosTbody');
        var filas = filasFiltradas();
        tbody.innerHTML = '';

        document.getElementById('contactadosCount').textContent =
            filas.length + (filas.length === 1 ? ' registro' : ' registros');

        if (!filas.length) {
            var tr = document.createElement('tr');
            var td = document.createElement('td');
            td.colSpan = 8;
            td.className = 'ctc-vacio';
            td.textContent = 'Sin contactos que coincidan con el filtro.';
            tr.appendChild(td);
            tbody.appendChild(tr);
            renderizarPaginacion(0);
            actualizarBarraSeleccion();
            return;
        }

        renderizarPaginacion(filas.length);
        var inicio = (paginaActual - 1) * FILAS_POR_PAGINA;
        filas.slice(inicio, inicio + FILAS_POR_PAGINA).forEach(function (r) { tbody.appendChild(pintarFila(r)); });
        actualizarBarraSeleccion();
    }

    function construirOpcionesMercaderista() {
        var select = document.getElementById('contactadosMercaderista');
        var valorPrevio = select.value;
        select.innerHTML = '<option value="">Todos los mercaderistas</option>';
        var vistos = {};
        currentRows.forEach(function (r) {
            if (r.usuario && !vistos[r.usuario]) {
                vistos[r.usuario] = true;
                var opt = document.createElement('option');
                opt.value = r.usuario;
                opt.textContent = r.usuario;
                select.appendChild(opt);
            }
        });
        if (vistos[valorPrevio]) select.value = valorPrevio;
    }

    function cargarContactados() {
        fetch(GETTERS_BASE + 'get_contactados.php')
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                currentRows = json.data || [];
                selectedIds = {};
                paginaActual = 1;
                construirOpcionesMercaderista();
                renderizar();
            });
    }

    // .xlsx real vía SheetJS (cargado en contactados.php).
    function exportarExcel(filas, prefijoArchivo) {
        var datos = filas.map(function (r) {
            return {
                'Empresa': r.empresa || '',
                'Contacto': r.contacto || '',
                'Dirección': r.direccion || '',
                'Correo': r.mail || '',
                'Teléfono': r.telefono || '',
                'Convencional': r.telefono_convencional || '-',
                'Promotor': r.usuario || '',
                'PDV': r.pdv || '',
                'Registrado': formatFechaHoraTexto(r.fecha_registro)
            };
        });

        var hoja = XLSX.utils.json_to_sheet(datos);
        hoja['!cols'] = [{ wch: 20 }, { wch: 20 }, { wch: 26 }, { wch: 24 }, { wch: 14 }, { wch: 14 }, { wch: 14 }, { wch: 16 }, { wch: 16 }];

        var libro = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(libro, hoja, 'Contactos');

        // Formato día-mes-año pedido explícitamente (no el YYYY-MM-DD de
        // toISOString) para el nombre del archivo descargado.
        var hoy = new Date();
        var dd = String(hoy.getDate()).padStart(2, '0');
        var mm = String(hoy.getMonth() + 1).padStart(2, '0');
        var fechaArchivo = dd + '-' + mm + '-' + hoy.getFullYear();
        XLSX.writeFile(libro, prefijoArchivo + fechaArchivo + '.xlsx');
    }

    function formatFechaHoraTexto(valor) {
        var partes = formatFechaHora(valor);
        if (typeof partes === 'string') return partes;
        return partes.hora ? (partes.fecha + ' ' + partes.hora) : partes.fecha;
    }

    // "Descargar selección": solo las filas marcadas con checkbox.
    function descargarSeleccionados() {
        if (!Object.keys(selectedIds).length) return;
        var filas = currentRows.filter(function (r) { return selectedIds[String(r.id)]; });
        exportarExcel(filas, 'contactados_seleccion_');
    }

    // "Descargar todo": todo lo que hay en el filtro actual, sin importar
    // qué esté marcado con checkbox (mismo comportamiento que el botón
    // único de antes, ahora separado de "Descargar selección").
    function descargarTodo() {
        exportarExcel(filasFiltradas(), 'contactados_');
    }

    // Expuestos para que el topbar global (controlado desde index.php) sepa
    // refrescar esta sección sin conocer sus detalles internos.
    window.ContactadosRefrescar = cargarContactados;
    window.ContactadosDescargarExcel = descargarTodo;

    document.addEventListener('DOMContentLoaded', function () {
        cargarContactados();
        document.getElementById('contactadosBusqueda').addEventListener('input', resetearSeleccionYRenderizar);
        document.getElementById('contactadosMercaderista').addEventListener('change', resetearSeleccionYRenderizar);
        document.getElementById('contactadosPagAnterior').addEventListener('click', function () {
            if (paginaActual > 1) { paginaActual--; renderizar(); }
        });
        document.getElementById('contactadosPagSiguiente').addEventListener('click', function () {
            paginaActual++;
            renderizar();
        });
        document.getElementById('contactadosActualizar').addEventListener('click', cargarContactados);
        document.getElementById('contactadosCheckTodo').addEventListener('change', toggleSeleccionarTodo);
        document.getElementById('contactadosDescargarSeleccion').addEventListener('click', descargarSeleccionados);
        document.getElementById('contactadosDescargarTodo').addEventListener('click', descargarTodo);
    });
})();
