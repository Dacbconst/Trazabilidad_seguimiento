<?php
/**
 * COMPONENTE: agendamientos.php
 * Agenda de visitas estilo Google Calendar: sidebar (Crear + mini-calendario +
 * Agendas pendientes) y calendario semanal a la derecha, con mapa colapsable
 * que empuja el layout (no se sobrepone) al desplegarse.
 * Datos reales de insert_proyectos_contacto vía Pintuco/getters/get_agenda.php
 * y Pintuco/getters/update_agenda.php. $cuenta_dir/$cuenta_actual vienen del
 * index.php que incluye este componente.
 */
$modulo_base = basename((string) $cuenta_dir);
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="agenda-filters" id="agendaFiltros">
    <div class="filter-group">
        <label>Desde</label>
        <input type="date" class="form-control" id="agendaFechaInicio">
    </div>
    <div class="filter-group">
        <label>Hasta</label>
        <input type="date" class="form-control" id="agendaFechaFin">
    </div>
    <div class="filter-group">
        <label>Estado</label>
        <select class="form-control" id="agendaEstado">
            <option value="">Todos</option>
            <option value="pendiente">Pendiente</option>
            <option value="confirmado">Confirmado</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Técnico</label>
        <input type="text" class="form-control" id="agendaTecnico" placeholder="Todos">
    </div>
    <button type="button" class="btn btn-actualizar" id="agendaBtnFiltrar">Filtrar</button>
</div>

<div class="agenda-layout">

    <aside class="gcal-sidebar">
        <button type="button" class="gcal-crear-btn" id="agendaCrearBtn">
            <i class="glyphicon glyphicon-plus"></i>
            <span>Crear</span>
            <i class="glyphicon glyphicon-triangle-bottom gcal-crear-caret"></i>
        </button>

        <div id="agendaMiniCalendar" class="gcal-mini-calendar"></div>

        <div class="gcal-pendientes">
            <div class="gcal-pendientes-title">Agendas pendientes</div>
            <ul class="gcal-pendientes-list" id="agendaPendientesList">
                <li class="gcal-pendientes-empty">Sin agendas pendientes</li>
            </ul>
        </div>
    </aside>

    <div class="agenda-calendar-wrap">
        <div id="agendaCalendar"></div>
    </div>

    <button type="button" class="agenda-map-toggle collapsed" id="agendaMapToggle" title="Mostrar/ocultar mapa">
        <i class="glyphicon glyphicon-chevron-left"></i>
    </button>

    <div class="agenda-map-panel collapsed" id="agendaMapPanel">
        <div id="agendaMap"></div>
    </div>

</div>

<div class="agenda-edit-overlay" id="agendaEditOverlay">
    <div class="agenda-edit-card">
        <h4>Gestionar visita</h4>
        <p class="agenda-edit-cliente" id="agendaEditCliente"></p>

        <div class="filter-group">
            <label>Título</label>
            <input type="text" class="form-control" id="agendaEditTitulo">
        </div>
        <div class="filter-group">
            <label>Hora</label>
            <input type="time" class="form-control" id="agendaEditHora">
        </div>
        <div class="filter-group">
            <label>Lugar</label>
            <input type="text" class="form-control" id="agendaEditLugar">
        </div>
        <div class="filter-group">
            <label>Técnico</label>
            <input type="text" class="form-control" id="agendaEditTecnico">
        </div>
        <div class="filter-group">
            <label>Estado</label>
            <select class="form-control" id="agendaEditEstado">
                <option value="pendiente">Pendiente</option>
                <option value="confirmado">Confirmado</option>
            </select>
        </div>

        <div class="agenda-edit-actions">
            <button type="button" class="btn" id="agendaEditCancelar">Cancelar</button>
            <button type="button" class="btn btn-actualizar" id="agendaEditGuardar">Guardar</button>
        </div>
    </div>
</div>

<script>
(function () {
    var GETTERS_BASE = '<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/';
    var calendar, miniCalendar, map;
    var markersById = {};
    var editingId = null;
    var currentRows = [];
    var hiddenIds = {};

    function estadoClase(estado) {
        return estado === 'confirmado' ? 'agenda-evt-confirmado' : 'agenda-evt-pendiente';
    }

    function buildEvents(rows) {
        return rows
            .filter(function (r) { return !hiddenIds[r.id]; })
            .map(function (r) {
                // fecha_agendamiento ya llega en formato ISO (YYYY-MM-DD) desde el getter.
                if (!r.fecha_agendamiento) return null;
                var start = r.hora ? (r.fecha_agendamiento + 'T' + r.hora) : r.fecha_agendamiento;
                return {
                    id: String(r.id),
                    title: (r.titulo && r.titulo.trim() !== '') ? r.titulo : (r.pdv || r.contacto || r.empresa || 'Visita'),
                    start: start,
                    allDay: !r.hora,
                    className: estadoClase(r.estado_agenda),
                    extendedProps: r
                };
            }).filter(Boolean);
    }

    function pintarMapa(rows) {
        Object.keys(markersById).forEach(function (id) { map.removeLayer(markersById[id]); });
        markersById = {};
        var puntos = [];
        rows.forEach(function (r) {
            var lat = parseFloat(r.latitud), lng = parseFloat(r.longitud);
            if (!lat || !lng) return;
            var marker = L.marker([lat, lng]).addTo(map);
            marker.bindPopup(
                '<strong>' + (r.titulo || r.contacto || r.empresa || 'Visita') + '</strong><br>' +
                (r.direccion || '') + '<br>' +
                'Estado: ' + (r.estado_agenda || 'pendiente')
            );
            markersById[r.id] = marker;
            puntos.push([lat, lng]);
        });
        if (puntos.length) {
            map.fitBounds(puntos, { padding: [30, 30], maxZoom: 14 });
        }
    }

    function pintarPendientes(rows) {
        var lista = document.getElementById('agendaPendientesList');
        var pendientes = rows.filter(function (r) { return r.estado_agenda !== 'confirmado'; });

        if (!pendientes.length) {
            lista.innerHTML = '<li class="gcal-pendientes-empty">Sin agendas pendientes</li>';
            return;
        }

        lista.innerHTML = '';
        pendientes.forEach(function (r) {
            var li = document.createElement('li');
            li.className = 'gcal-pendiente-item';

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = !hiddenIds[r.id];
            checkbox.addEventListener('change', function () {
                if (checkbox.checked) {
                    delete hiddenIds[r.id];
                } else {
                    hiddenIds[r.id] = true;
                }
                refrescarEventos();
            });

            var dot = document.createElement('span');
            dot.className = 'gcal-pendiente-dot';

            var label = document.createElement('span');
            label.className = 'gcal-pendiente-label';
            label.textContent = (r.pdv || r.empresa || 'PDV') + ' — ' + (r.contacto || 'Sin contacto');
            label.addEventListener('click', function () { abrirEdicion(r); });

            li.appendChild(checkbox);
            li.appendChild(dot);
            li.appendChild(label);
            lista.appendChild(li);
        });
    }

    function refrescarEventos() {
        calendar.removeAllEvents();
        buildEvents(currentRows).forEach(function (ev) { calendar.addEvent(ev); });
    }

    function cargarAgenda() {
        var params = new URLSearchParams();
        var fi = document.getElementById('agendaFechaInicio').value;
        var ff = document.getElementById('agendaFechaFin').value;
        var estado = document.getElementById('agendaEstado').value;
        var tecnico = document.getElementById('agendaTecnico').value;
        if (fi) params.set('fecha_inicio', fi);
        if (ff) params.set('fecha_fin', ff);
        if (estado) params.set('estado_agenda', estado);
        if (tecnico) params.set('tecnico', tecnico);

        fetch(GETTERS_BASE + 'get_agenda.php?' + params.toString())
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                currentRows = json.data || [];
                refrescarEventos();
                pintarMapa(currentRows);
                pintarPendientes(currentRows);
            });
    }

    function abrirEdicion(props) {
        editingId = props.id;
        document.getElementById('agendaEditCliente').textContent =
            (props.contacto || props.empresa || '') + ' — ' + (props.direccion || '');
        document.getElementById('agendaEditTitulo').value = props.titulo || '';
        document.getElementById('agendaEditHora').value = props.hora || '';
        document.getElementById('agendaEditLugar').value = props.lugar || '';
        document.getElementById('agendaEditTecnico').value = props.tecnico || '';
        document.getElementById('agendaEditEstado').value = props.estado_agenda === 'confirmado' ? 'confirmado' : 'pendiente';
        document.getElementById('agendaEditOverlay').classList.add('active');

        var marker = markersById[editingId];
        if (marker) {
            map.setView(marker.getLatLng(), 15);
            marker.openPopup();
        }
    }

    function cerrarEdicion() {
        editingId = null;
        document.getElementById('agendaEditOverlay').classList.remove('active');
    }

    function guardarEdicion() {
        if (!editingId) return;
        var body = new URLSearchParams();
        body.set('id', editingId);
        body.set('titulo', document.getElementById('agendaEditTitulo').value);
        body.set('hora', document.getElementById('agendaEditHora').value);
        body.set('lugar', document.getElementById('agendaEditLugar').value);
        body.set('tecnico', document.getElementById('agendaEditTecnico').value);
        body.set('estado_agenda', document.getElementById('agendaEditEstado').value);

        fetch(GETTERS_BASE + 'update_agenda.php', { method: 'POST', body: body })
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                if (json.success) {
                    cerrarEdicion();
                    cargarAgenda();
                } else {
                    alert(json.message || 'No se pudo guardar.');
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        calendar = new FullCalendar.Calendar(document.getElementById('agendaCalendar'), {
            locale: 'es',
            headerToolbar: { left: '', center: 'title', right: 'timeGridDay,timeGridWeek,dayGridMonth' },
            initialView: 'timeGridWeek',
            height: 'auto',
            nowIndicator: true,
            slotMinTime: '06:00:00',
            buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
            eventClick: function (info) {
                abrirEdicion(info.event.extendedProps);
            }
        });
        calendar.render();

        miniCalendar = new FullCalendar.Calendar(document.getElementById('agendaMiniCalendar'), {
            locale: 'es',
            headerToolbar: { left: 'prev', center: 'title', right: 'next' },
            initialView: 'dayGridMonth',
            height: 'auto',
            dayHeaderFormat: { weekday: 'narrow' },
            dateClick: function (info) {
                calendar.gotoDate(info.date);
            }
        });
        miniCalendar.render();

        map = L.map('agendaMap').setView([-2.170998, -79.922359], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        }).addTo(map);

        cargarAgenda();

        document.getElementById('agendaBtnFiltrar').addEventListener('click', cargarAgenda);
        document.getElementById('agendaEditCancelar').addEventListener('click', cerrarEdicion);
        document.getElementById('agendaEditGuardar').addEventListener('click', guardarEdicion);

        document.getElementById('agendaCrearBtn').addEventListener('click', function () {
            alert('Crear nueva visita: flujo de creación pendiente de definir.');
        });

        document.getElementById('agendaMapToggle').addEventListener('click', function () {
            document.getElementById('agendaMapPanel').classList.toggle('collapsed');
            this.classList.toggle('collapsed');
            setTimeout(function () { if (map) map.invalidateSize(); }, 320);
        });
    });
})();
</script>
