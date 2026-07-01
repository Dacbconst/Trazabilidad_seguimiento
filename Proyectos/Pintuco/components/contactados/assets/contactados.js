(function () {
    var GETTERS_BASE = document.getElementById('contactadosApp').dataset.gettersBase;
    var currentRows = [];
    var FILAS_POR_PAGINA = 15;
    var paginaActual = 1;

    // Mismo contrato de 6 estados que Agendamientos (estado_agenda), pero
    // con etiqueta propia de este directorio — "pendiente" se llama "Nuevo"
    // aquí porque es justo eso: un contacto recién capturado que todavía no
    // se ha gestionado.
    var ESTADOS_VALIDOS = ['pendiente', 'confirmado', 'reagendada', 'vencida', 'cancelada', 'completada'];
    var ESTADO_LABEL = {
        pendiente: 'Nuevo',
        confirmado: 'Confirmado',
        reagendada: 'Reagendada',
        vencida: 'Vencida',
        cancelada: 'Cancelada',
        completada: 'Completada'
    };

    function estadoVisual(r) {
        return ESTADOS_VALIDOS.indexOf(r.estado_agenda) !== -1 ? r.estado_agenda : 'pendiente';
    }

    function formatFechaHora(valor) {
        if (!valor) return '—';
        var partes = valor.split(' ');
        var fechaPartes = partes[0].split('-');
        if (fechaPartes.length !== 3) return valor;
        var fecha = fechaPartes[2] + '/' + fechaPartes[1] + '/' + fechaPartes[0];
        if (partes.length < 2) return fecha;
        return fecha + ' ' + partes[1].slice(0, 5);
    }

    function celda(texto) {
        var td = document.createElement('td');
        td.textContent = texto || '—';
        return td;
    }

    // Ícono de coordenada al inicio del texto de la dirección — sin
    // consumir ninguna API: el link de Google Maps con "?q=lat,lng" abre
    // el mapa directo en esas coordenadas usando lo que ya está guardado
    // en la BD, gratis. Si el contacto no tiene lat/lng todavía (no se le
    // confirmó pin en Agendamientos), no se pinta el ícono, solo el texto.
    function celdaDireccion(r) {
        var td = document.createElement('td');
        var lat = parseFloat(r.latitud);
        var lng = parseFloat(r.longitud);
        if (lat && lng) {
            var link = document.createElement('a');
            link.href = 'https://maps.google.com/maps?q=' + lat + ',' + lng;
            link.target = '_blank';
            link.rel = 'noopener';
            link.className = 'contactados-pin-link';
            link.title = 'Ver en Google Maps';
            link.innerHTML = '<i class="glyphicon glyphicon-map-marker"></i>';
            td.appendChild(link);
        }
        td.appendChild(document.createTextNode(r.direccion || '—'));
        return td;
    }

    function pintarFila(r) {
        var tr = document.createElement('tr');
        // PDV oculto a pedido del usuario (2026-06-30), no se quiere ver por ahora
        // tr.appendChild(celda(r.pdv));
        tr.appendChild(celdaDireccion(r));
        tr.appendChild(celda(r.usuario));
        tr.appendChild(celda(r.contacto));
        tr.appendChild(celda(r.empresa));
        tr.appendChild(celda(r.mail));
        tr.appendChild(celda(r.telefono || r.telefono_convencional));
        tr.appendChild(celda(formatFechaHora(r.fecha_registro)));

        var estado = estadoVisual(r);
        var tdEstado = document.createElement('td');
        var badge = document.createElement('span');
        badge.className = 'contactados-badge is-' + estado;
        badge.textContent = ESTADO_LABEL[estado];
        tdEstado.appendChild(badge);
        tr.appendChild(tdEstado);

        return tr;
    }

    function filasFiltradas() {
        var q        = document.getElementById('contactadosBusqueda').value.toLowerCase().trim();
        var estado   = document.getElementById('contactadosEstado').value;
        var mercader = document.getElementById('contactadosMercaderista').value;

        return currentRows.filter(function (r) {
            if (mercader && r.usuario !== mercader) return false;
            if (estado   && estadoVisual(r) !== estado) return false;
            if (q) {
                var coincide = [r.contacto, r.empresa, r.pdv, r.mail, r.direccion].some(function (v) {
                    return (v || '').toLowerCase().indexOf(q) !== -1;
                });
                if (!coincide) return false;
            }
            return true;
        });
    }

    // Paginado en cliente: la data ya llega completa de get_contactados.php
    // (igual que el resto del filtrado), así que no hace falta tocar el
    // getter — solo recortar lo que se pinta por página, para no tener una
    // tabla eterna en pantalla.
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

        if (!filas.length) {
            var tr = document.createElement('tr');
            var td = document.createElement('td');
            td.colSpan = 8;
            td.className = 'contactados-vacio';
            td.textContent = 'Sin contactos que coincidan con el filtro.';
            tr.appendChild(td);
            tbody.appendChild(tr);
            renderizarPaginacion(0);
            return;
        }

        renderizarPaginacion(filas.length);
        var inicio = (paginaActual - 1) * FILAS_POR_PAGINA;
        filas.slice(inicio, inicio + FILAS_POR_PAGINA).forEach(function (r) { tbody.appendChild(pintarFila(r)); });
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
                paginaActual = 1;
                construirOpcionesMercaderista();
                renderizar();
            });
    }

    // .xlsx real vía SheetJS (cargado en contactados.php) — exporta
    // exactamente lo que está filtrado/visible, no todo el directorio.
    function descargarExcel() {
        var filas = filasFiltradas();
        var datos = filas.map(function (r) {
            var estado = estadoVisual(r);
            return {
                'PDV': r.pdv || '',
                'Local': r.direccion || '',
                'Promotor': r.usuario || '',
                'Contacto': r.contacto || '',
                'Empresa': r.empresa || '',
                'Correo': r.mail || '',
                'Teléfono': r.telefono || r.telefono_convencional || '',
                'Registrado': formatFechaHora(r.fecha_registro),
                'Estado': ESTADO_LABEL[estado]
            };
        });

        var hoja = XLSX.utils.json_to_sheet(datos);
        hoja['!cols'] = [{ wch: 16 }, { wch: 26 }, { wch: 14 }, { wch: 20 }, { wch: 20 }, { wch: 24 }, { wch: 14 }, { wch: 16 }, { wch: 12 }];

        var libro = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(libro, hoja, 'Contactos');
        XLSX.writeFile(libro, 'contactados_' + new Date().toISOString().slice(0, 10) + '.xlsx');
    }

    // Expuestos para que el topbar global (controlado desde index.php) sepa
    // refrescar/exportar esta sección sin que index.php tenga que conocer
    // los detalles internos de Contactados.
    window.ContactadosRefrescar = cargarContactados;
    window.ContactadosDescargarExcel = descargarExcel;

    function renderizarDesdePrimeraPagina() {
        paginaActual = 1;
        renderizar();
    }

    document.addEventListener('DOMContentLoaded', function () {
        cargarContactados();
        document.getElementById('contactadosBusqueda').addEventListener('input', renderizarDesdePrimeraPagina);
        document.getElementById('contactadosEstado').addEventListener('change', renderizarDesdePrimeraPagina);
        document.getElementById('contactadosMercaderista').addEventListener('change', renderizarDesdePrimeraPagina);
        document.getElementById('contactadosPagAnterior').addEventListener('click', function () {
            if (paginaActual > 1) { paginaActual--; renderizar(); }
        });
        document.getElementById('contactadosPagSiguiente').addEventListener('click', function () {
            paginaActual++;
            renderizar();
        });
        document.getElementById('contactadosExportarExcel').addEventListener('click', descargarExcel);
    });
})();
