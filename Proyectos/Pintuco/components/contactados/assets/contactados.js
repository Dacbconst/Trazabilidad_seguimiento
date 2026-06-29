(function () {
    var GETTERS_BASE = document.getElementById('contactadosApp').dataset.gettersBase;
    var currentRows = [];

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

    function pintarFila(r) {
        var tr = document.createElement('tr');
        tr.appendChild(celda(r.pdv));
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

    // Reusa el filtro GLOBAL del topbar (Promotor / Búsqueda rápida,
    // partials/topbar.php) en vez de uno propio — esos 2 controles no los
    // usa ninguna otra sección todavía, así que repintar sus opciones acá no
    // rompe nada. "Mes" del topbar se deja afuera a propósito: viene
    // preseleccionado con un mock fijo ($mes_actual en mock_data.php, no un
    // mes real calculado), así que filtrar por él escondería datos sin que
    // el analista lo haya pedido.
    function filasFiltradas() {
        var promotorSel = document.getElementById('filtroPromotor');
        var busquedaInput = document.getElementById('busquedaRapida');

        var promotor = promotorSel ? promotorSel.value : '';
        var q = busquedaInput ? busquedaInput.value.toLowerCase().trim() : '';

        return currentRows.filter(function (r) {
            if (promotor && r.usuario !== promotor) return false;
            if (q) {
                var coincide = [r.contacto, r.empresa, r.pdv, r.mail].some(function (v) {
                    return (v || '').toLowerCase().indexOf(q) !== -1;
                });
                if (!coincide) return false;
            }
            return true;
        });
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
            return;
        }

        filas.forEach(function (r) { tbody.appendChild(pintarFila(r)); });
    }

    // El <select> de Promotor del topbar global trae opciones de un mock
    // (id numérico → "Promotor 1") que no corresponden a los valores reales
    // de `usuario` en esta tabla. Mientras Contactados esté activo tiene más
    // sentido mostrar los promotores reales que sí aparecen en los datos —
    // ese select no lo usa ninguna otra sección hoy, así que pisarlo es seguro.
    function construirOpcionesPromotor() {
        var select = document.getElementById('filtroPromotor');
        if (!select) return;
        var valorPrevio = select.value;
        select.innerHTML = '<option value="">Todos</option>';
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
                construirOpcionesPromotor();
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
        hoja['!cols'] = [{ wch: 16 }, { wch: 14 }, { wch: 20 }, { wch: 20 }, { wch: 24 }, { wch: 14 }, { wch: 16 }, { wch: 12 }];

        var libro = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(libro, hoja, 'Contactos');
        XLSX.writeFile(libro, 'contactados_' + new Date().toISOString().slice(0, 10) + '.xlsx');
    }

    // Expuestos para que el topbar global (controlado desde index.php) sepa
    // refrescar/exportar esta sección sin que index.php tenga que conocer
    // los detalles internos de Contactados.
    window.ContactadosRefrescar = cargarContactados;
    window.ContactadosDescargarExcel = descargarExcel;

    document.addEventListener('DOMContentLoaded', function () {
        cargarContactados();
        document.getElementById('filtroPromotor').addEventListener('change', renderizar);
        document.getElementById('busquedaRapida').addEventListener('input', renderizar);
    });
})();
