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
        <label>Promotor</label>
        <select class="form-control" id="agendaFiltroPromotor">
            <option value="">Todos</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Búsqueda rápida</label>
        <div class="input-group">
            <input type="text" class="form-control" id="agendaBusqueda" placeholder="Buscar...">
            <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
        </div>
    </div>
    <div class="filter-group">
        <label>Estado</label>
        <select class="form-control" id="agendaFiltroEstado">
            <option value="">Todos</option>
            <option value="agendada">Agendada</option>
            <option value="reagendada">Reagendada</option>
            <option value="cancelada">Cancelada</option>
        </select>
    </div>

    <div class="agenda-filters-legend">
        <span class="agenda-legend-item"><span class="agenda-legend-dot is-agendada"></span><strong id="agendaCountAgendadas">0</strong> Agendadas</span>
        <span class="agenda-legend-item"><span class="agenda-legend-dot is-reagendada"></span><strong id="agendaCountReagendadas">0</strong> Reagendadas</span>
        <span class="agenda-legend-item"><span class="agenda-legend-dot is-cancelada"></span><strong id="agendaCountCanceladas">0</strong> Canceladas</span>
    </div>

    <button type="button" class="btn btn-actualizar" id="agendaBtnActualizar">Actualizar</button>
</div>

<div class="agenda-layout">

    <aside class="gcal-sidebar">
        <button type="button" class="gcal-crear-btn" id="agendaCrearBtn">
            <i class="glyphicon glyphicon-plus"></i>
            <span>Crear</span>
            <i class="glyphicon glyphicon-triangle-bottom gcal-crear-caret"></i>
        </button>

        <div class="gcal-mini-calendar-wrap" id="agendaMiniCalendarWrap">
            <button type="button" class="gcal-mini-header-bar" id="agendaMiniToggle" title="Mostrar/ocultar mini-calendario">
                <i class="glyphicon glyphicon-calendar"></i>
                <span id="agendaMiniHeaderLabel">Calendario</span>
                <i class="glyphicon glyphicon-chevron-up gcal-mini-header-chevron"></i>
            </button>
            <div id="agendaMiniCalendar" class="gcal-mini-calendar"></div>
            <div class="gcal-mini-yearpicker" id="agendaMiniYearPicker">
                <div class="gcal-mini-yearpicker-header">
                    <button type="button" class="gcal-mini-yearpicker-arrow" id="agendaMiniYearPrev"><i class="glyphicon glyphicon-chevron-up"></i></button>
                    <span id="agendaMiniYearLabel">2026</span>
                    <button type="button" class="gcal-mini-yearpicker-arrow" id="agendaMiniYearNext"><i class="glyphicon glyphicon-chevron-down"></i></button>
                </div>
                <div class="gcal-mini-yearpicker-grid" id="agendaMiniYearGrid"></div>
            </div>
        </div>

        <div class="gcal-pendientes">
            <div class="gcal-pendientes-title">
                Agendas pendientes
                <span class="gcal-pendientes-count" id="agendaPendientesCount">0</span>
            </div>
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
        <button type="button" class="agenda-edit-close" id="agendaEditClose" aria-label="Cerrar">&times;</button>

        <div class="agenda-edit-header">
            <h4 class="agenda-edit-title" id="agendaEditTitulo"></h4>
            <span class="agenda-edit-badge" id="agendaEditBadge">Pendiente</span>
        </div>

        <div class="agenda-edit-alert" id="agendaEditAlerta" style="display:none">
            <i class="glyphicon glyphicon-warning-sign"></i>
            <span id="agendaEditAlertaTexto"></span>
        </div>

        <div class="agenda-edit-divider"></div>

        <div class="agenda-edit-info">
            <div class="agenda-edit-info-row">
                <i class="glyphicon glyphicon-briefcase"></i>
                <div class="agenda-edit-info-text">
                    <span class="agenda-edit-info-label">Promotor</span>
                    <span class="agenda-edit-info-value" id="agendaEditPromotor">—</span>
                </div>
            </div>
            <div class="agenda-edit-info-row">
                <i class="glyphicon glyphicon-home"></i>
                <div class="agenda-edit-info-text">
                    <span class="agenda-edit-info-label">Local</span>
                    <span class="agenda-edit-info-value" id="agendaEditLocal">—</span>
                </div>
            </div>
            <div class="agenda-edit-info-row">
                <i class="glyphicon glyphicon-building"></i>
                <div class="agenda-edit-info-text">
                    <span class="agenda-edit-info-label">Empresa</span>
                    <span class="agenda-edit-info-value" id="agendaEditEmpresa">—</span>
                </div>
            </div>
            <div class="agenda-edit-info-row">
                <i class="glyphicon glyphicon-envelope"></i>
                <div class="agenda-edit-info-text">
                    <span class="agenda-edit-info-label">Correo</span>
                    <span class="agenda-edit-info-value" id="agendaEditMail">—</span>
                </div>
            </div>
            <div class="agenda-edit-info-row">
                <i class="glyphicon glyphicon-map-marker"></i>
                <div class="agenda-edit-info-text">
                    <span class="agenda-edit-info-label">Dirección</span>
                    <span class="agenda-edit-info-value" id="agendaEditDireccion">—</span>
                </div>
            </div>
            <div class="agenda-edit-info-row">
                <i class="glyphicon glyphicon-earphone"></i>
                <div class="agenda-edit-info-text">
                    <span class="agenda-edit-info-label">Teléfono</span>
                    <span class="agenda-edit-info-value" id="agendaEditTelefono">—</span>
                </div>
            </div>
        </div>

        <div class="agenda-edit-divider"></div>

        <div class="agenda-edit-row">
            <i class="glyphicon glyphicon-calendar"></i>
            <input type="date" class="form-control" id="agendaEditFecha">
        </div>
        <div class="agenda-edit-row">
            <i class="glyphicon glyphicon-time"></i>
            <input type="time" class="form-control" id="agendaEditHora">
        </div>
        <div class="agenda-edit-row">
            <i class="glyphicon glyphicon-user"></i>
            <input type="text" class="form-control" id="agendaEditTecnico" placeholder="Técnico asignado">
        </div>

        <div class="agenda-edit-actions">
            <div class="agenda-edit-actions-left">
                <button type="button" class="agenda-edit-cancelar-visita" id="agendaEditCancelarVisita">Cancelar visita</button>
                <button type="button" class="agenda-edit-eliminar" id="agendaEditEliminar"><i class="glyphicon glyphicon-trash"></i> Eliminar</button>
            </div>
            <div class="agenda-edit-actions-right">
                <button type="button" class="btn" id="agendaEditCancelar">Cerrar</button>
                <button type="button" class="btn btn-actualizar" id="agendaEditGuardar">Guardar</button>
            </div>
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
    var syncingMini = false;
    var yearPickerYear = new Date().getFullYear();
    var DURACION_APROX_MIN = 45;

    function highlightMiniRange(start, end) {
        var cells = document.querySelectorAll('#agendaMiniCalendar .fc-daygrid-day');
        cells.forEach(function (cell) {
            cell.classList.remove('gcal-mini-selected');
            var d = cell.getAttribute('data-date');
            if (!d) return;
            var date = new Date(d + 'T00:00:00');
            if (date >= start && date < end) {
                cell.classList.add('gcal-mini-selected');
            }
        });
    }

    var MESES_CORTOS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    function pintarYearGrid() {
        document.getElementById('agendaMiniYearLabel').textContent = yearPickerYear;
        var grid = document.getElementById('agendaMiniYearGrid');
        grid.innerHTML = '';
        var actual = miniCalendar.getDate();
        MESES_CORTOS.forEach(function (nombre, idx) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'gcal-mini-yearpicker-month';
            if (yearPickerYear === actual.getFullYear() && idx === actual.getMonth()) {
                btn.classList.add('is-actual');
            }
            btn.textContent = nombre;
            btn.addEventListener('click', function () {
                miniCalendar.gotoDate(new Date(yearPickerYear, idx, 1));
                cerrarYearPicker();
            });
            grid.appendChild(btn);
        });
    }

    function abrirYearPicker() {
        yearPickerYear = miniCalendar.getDate().getFullYear();
        pintarYearGrid();
        document.getElementById('agendaMiniYearPicker').classList.add('active');
    }

    function cerrarYearPicker() {
        document.getElementById('agendaMiniYearPicker').classList.remove('active');
    }

    // Estados reales del negocio: agendada / reagendada / cancelada.
    // Filas viejas (pendiente/confirmado/vacío) se tratan como "agendada"
    // hasta que se vuelvan a guardar y el backend les asigne el estado nuevo.
    function normalizarEstado(estado) {
        if (estado === 'cancelada') return 'cancelada';
        if (estado === 'reagendada') return 'reagendada';
        return 'agendada';
    }

    // Fuente única de verdad del estado que se MUESTRA: cancelada/reagendada
    // salen directo de estado_agenda, pero "agendada" solo es real si ya
    // tiene hora y técnico — si no, todavía es una agenda nueva (típicamente
    // llegada desde el lado móvil) que el analista no ha terminado de
    // gestionar, sin importar lo que diga (o no diga) estado_agenda.
    // Usar SIEMPRE esta función para decidir el estado visible — badge,
    // lista de pendientes y color en el calendario deben coincidir siempre.
    function estadoVisual(r) {
        var estado = normalizarEstado(r.estado_agenda);
        if (estado === 'cancelada' || estado === 'reagendada') return estado;
        return (r.hora && r.tecnico) ? 'agendada' : 'sin_agendar';
    }

    var ESTADO_LABEL = { agendada: 'Agendada', reagendada: 'Reagendada', cancelada: 'Cancelada', sin_agendar: 'Sin agendar' };

    function estadoClase(r) {
        return 'agenda-evt-' + estadoVisual(r);
    }

    function buildEvents(rows) {
        return rows
            .filter(function (r) { return !hiddenIds[r.id]; })
            .map(function (r) {
                // fecha_agendamiento ya llega en formato ISO (YYYY-MM-DD) desde el getter.
                if (!r.fecha_agendamiento) return null;
                var start = r.hora ? (r.fecha_agendamiento + 'T' + r.hora) : r.fecha_agendamiento;
                // No registramos duración real: se asume un fin aproximado de
                // DURACION_APROX_MIN para que el bloque y la hora de fin se vean
                // en el calendario (marcado como "(aprox)" en el card).
                var end = r.hora ? new Date(new Date(start).getTime() + DURACION_APROX_MIN * 60000) : null;
                return {
                    id: String(r.id),
                    title: (r.titulo && r.titulo.trim() !== '') ? r.titulo : (r.pdv || r.contacto || r.empresa || 'Visita'),
                    start: start,
                    end: end,
                    allDay: !r.hora,
                    className: estadoClase(r),
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
                'Estado: ' + ESTADO_LABEL[estadoVisual(r)]
            );
            markersById[r.id] = marker;
            puntos.push([lat, lng]);
        });
        if (puntos.length) {
            map.fitBounds(puntos, { padding: [30, 30], maxZoom: 14 });
        }
    }

    function hoyISO() {
        var hoy = new Date();
        return hoy.getFullYear() + '-' + String(hoy.getMonth() + 1).padStart(2, '0') + '-' + String(hoy.getDate()).padStart(2, '0');
    }

    // "Pendiente" ya no es "todo lo no cancelado": si una visita ya tiene
    // hora y técnico asignados (estadoVisual === 'agendada'/'reagendada') y
    // su fecha no venció, ya está gestionada y no necesita aparecer aquí.
    // Solo se lista lo que de verdad requiere acción: llegó del lado móvil
    // sin terminar de agendar (estadoVisual === 'sin_agendar'), o su fecha
    // ya venció (necesita reagendarse aunque ya tuviera hora/técnico).
    //
    // El "semáforo" de color (rojo = vencida) solo aplica a algo que ya fue
    // agendado de verdad; si nunca se agendó, siempre es "SIN AGENDAR" en
    // gris, sin importar si su fecha ya pasó — no hubo nada que "vencer".
    function motivoPendiente(r) {
        if (estadoVisual(r) === 'sin_agendar') return { texto: 'SIN AGENDAR', clase: 'is-incompleta' };
        return { texto: 'VENCIDA', clase: 'is-vencida' }; // única otra razón de estar en esta lista
    }

    function pintarPendientes(rows) {
        var lista = document.getElementById('agendaPendientesList');
        var hoy = hoyISO();
        var pendientes = rows
            .filter(function (r) {
                if (estadoVisual(r) === 'cancelada') return false;
                var vencida = r.fecha_agendamiento && r.fecha_agendamiento < hoy;
                return vencida || estadoVisual(r) === 'sin_agendar';
            })
            .slice()
            .sort(function (a, b) {
                var va = (a.fecha_agendamiento && a.fecha_agendamiento < hoy) ? 0 : 1; // vencidas primero
                var vb = (b.fecha_agendamiento && b.fecha_agendamiento < hoy) ? 0 : 1;
                if (va !== vb) return va - vb;
                return (Number(a.id) || 0) - (Number(b.id) || 0); // orden de llegada
            });

        document.getElementById('agendaPendientesCount').textContent = pendientes.length;

        if (!pendientes.length) {
            lista.innerHTML = '<li class="gcal-pendientes-empty">Sin agendas pendientes</li>';
            return;
        }

        lista.innerHTML = '';
        pendientes.forEach(function (r) {
            var motivo = motivoPendiente(r);
            var li = document.createElement('li');
            li.className = 'gcal-pendiente-item ' + motivo.clase;
            li.addEventListener('click', function (ev) {
                if (ev.target.closest('input[type="checkbox"]')) return;
                abrirEdicion(r);
            });

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = !hiddenIds[r.id];
            checkbox.addEventListener('change', function () {
                if (checkbox.checked) {
                    delete hiddenIds[r.id];
                } else {
                    hiddenIds[r.id] = true;
                }
                refrescarEventos(filtrarPorBusqueda(currentRows));
            });

            var texto = document.createElement('div');
            texto.className = 'gcal-pendiente-card-text';

            // Fila 1: título + motivo (semáforo solo si ya fue agendada).
            var filaTitulo = document.createElement('div');
            filaTitulo.className = 'gcal-pendiente-card-fila';

            var titulo = document.createElement('span');
            titulo.className = 'gcal-pendiente-card-title';
            titulo.textContent = (r.titulo && r.titulo.trim() !== '') ? r.titulo : '(Sin título)';
            filaTitulo.appendChild(titulo);

            var tag = document.createElement('span');
            tag.className = 'gcal-pendiente-tag ' + motivo.clase;
            tag.textContent = motivo.texto;
            filaTitulo.appendChild(tag);

            // Fila 2: local (PDV).
            var local = document.createElement('span');
            local.className = 'gcal-pendiente-card-local';
            local.textContent = r.pdv || r.empresa || 'PDV';

            // Fila 3: promotor a la izquierda, fecha agendada a la derecha.
            var filaMeta = document.createElement('div');
            filaMeta.className = 'gcal-pendiente-card-fila gcal-pendiente-card-meta';

            var promotor = document.createElement('span');
            promotor.textContent = r.usuario || 'Sin promotor';

            var fecha = document.createElement('span');
            fecha.textContent = formatFecha(r.fecha_agendamiento);

            filaMeta.appendChild(promotor);
            filaMeta.appendChild(fecha);

            texto.appendChild(filaTitulo);
            texto.appendChild(local);
            texto.appendChild(filaMeta);

            li.appendChild(checkbox);
            li.appendChild(texto);
            lista.appendChild(li);
        });
    }

    function refrescarEventos(rows) {
        calendar.removeAllEvents();
        buildEvents(rows).forEach(function (ev) { calendar.addEvent(ev); });
    }

    function pintarLeyenda(rows) {
        // "sin_agendar" no tiene dot propio en esta leyenda (son 3 categorías
        // fijas pedidas así); se cuenta aparte para no inflar "Agendadas".
        var c = { agendada: 0, reagendada: 0, cancelada: 0, sin_agendar: 0 };
        rows.forEach(function (r) { c[estadoVisual(r)]++; });
        document.getElementById('agendaCountAgendadas').textContent = c.agendada;
        document.getElementById('agendaCountReagendadas').textContent = c.reagendada;
        document.getElementById('agendaCountCanceladas').textContent = c.cancelada;
    }

    function filtrarPorBusqueda(rows) {
        var q = document.getElementById('agendaBusqueda').value.toLowerCase().trim();
        if (!q) return rows;
        return rows.filter(function (r) {
            return [r.titulo, r.pdv, r.empresa, r.contacto].some(function (v) {
                return (v || '').toLowerCase().indexOf(q) !== -1;
            });
        });
    }

    function renderizar() {
        var rows = filtrarPorBusqueda(currentRows);
        refrescarEventos(rows);
        pintarMapa(rows);
        pintarPendientes(rows);
        pintarLeyenda(rows);
    }

    var promotorOpcionesListas = false;

    function construirOpcionesPromotor(rows) {
        var select = document.getElementById('agendaFiltroPromotor');
        var vistos = {};
        rows.forEach(function (r) {
            if (r.usuario && !vistos[r.usuario]) {
                vistos[r.usuario] = true;
                var opt = document.createElement('option');
                opt.value = r.usuario;
                opt.textContent = r.usuario;
                select.appendChild(opt);
            }
        });
    }

    function cargarAgenda() {
        var promotor = document.getElementById('agendaFiltroPromotor').value;
        var estado = document.getElementById('agendaFiltroEstado').value;
        var params = new URLSearchParams();
        if (promotor) params.set('usuario', promotor);
        if (estado) params.set('estado_agenda', estado);

        fetch(GETTERS_BASE + 'get_agenda.php?' + params.toString())
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                currentRows = json.data || [];
                if (!promotorOpcionesListas && !promotor) {
                    construirOpcionesPromotor(currentRows);
                    promotorOpcionesListas = true;
                }
                renderizar();
            });
    }

    // El locale 'es' formatea AM/PM como "a. m."/"p. m."; lo normalizamos a
    // "AM"/"PM" para que se vea igual que en Google Calendar.
    function formatoHora12(texto) {
        return texto
            .toUpperCase()
            .replace(/\./g, '')
            .replace(/\s+/g, ' ')
            .replace(/([AP]) M\b/g, '$1M')
            .trim();
    }

    function formatFecha(iso) {
        if (!iso) return '—';
        var partes = iso.split('-');
        if (partes.length !== 3) return iso;
        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    function abrirEdicion(props) {
        editingId = props.id;
        document.getElementById('agendaEditTitulo').textContent =
            (props.titulo && props.titulo.trim() !== '') ? props.titulo : '(Sin título)';
        var estado = estadoVisual(props);
        var badge = document.getElementById('agendaEditBadge');
        badge.textContent = ESTADO_LABEL[estado];
        badge.className = 'agenda-edit-badge is-' + estado;

        document.getElementById('agendaEditPromotor').textContent = props.usuario || '—';
        document.getElementById('agendaEditLocal').textContent = props.pdv || '—';
        document.getElementById('agendaEditEmpresa').textContent = props.empresa || '—';
        document.getElementById('agendaEditMail').textContent = props.mail || '—';
        document.getElementById('agendaEditDireccion').textContent = props.direccion || '—';
        document.getElementById('agendaEditTelefono').textContent = props.telefono || '—';

        document.getElementById('agendaEditFecha').value = props.fecha_agendamiento || '';
        document.getElementById('agendaEditHora').value = props.hora || '';
        document.getElementById('agendaEditTecnico').value = props.tecnico || '';

        // Si la fecha pactada ya pasó y la visita no está cancelada, se le pide
        // al analista que la reagende ahora mismo en vez de dejarlo pasar
        // desapercibido (antes solo se marcaba en la lista del sidebar).
        var alerta = document.getElementById('agendaEditAlerta');
        var vencida = estado !== 'cancelada' && props.fecha_agendamiento && props.fecha_agendamiento < hoyISO();
        if (vencida) {
            document.getElementById('agendaEditAlertaTexto').textContent =
                'Esta visita estaba programada para el ' + formatFecha(props.fecha_agendamiento) + ' y ya venció. Reagenda la fecha antes de guardar.';
            alerta.style.display = 'flex';
        } else {
            alerta.style.display = 'none';
        }

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
        body.set('fecha', document.getElementById('agendaEditFecha').value);
        body.set('hora', document.getElementById('agendaEditHora').value);
        body.set('tecnico', document.getElementById('agendaEditTecnico').value);
        // El lugar se sincroniza con la dirección guardada y el estado
        // (agendada/reagendada) lo decide el backend.

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

    function cancelarVisita() {
        if (!editingId) return;
        if (!confirm('¿Cancelar esta visita?')) return;
        var body = new URLSearchParams();
        body.set('id', editingId);
        body.set('accion', 'cancelar');

        fetch(GETTERS_BASE + 'update_agenda.php', { method: 'POST', body: body })
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                if (json.success) {
                    cerrarEdicion();
                    cargarAgenda();
                } else {
                    alert(json.message || 'No se pudo cancelar.');
                }
            });
    }

    function eliminarVisita() {
        if (!editingId) return;
        if (!confirm('¿Eliminar este agendamiento? No volverá a aparecer en la agenda.')) return;
        var body = new URLSearchParams();
        body.set('id', editingId);
        body.set('accion', 'eliminar');

        fetch(GETTERS_BASE + 'update_agenda.php', { method: 'POST', body: body })
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                if (json.success) {
                    cerrarEdicion();
                    cargarAgenda();
                } else {
                    alert(json.message || 'No se pudo eliminar.');
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        calendar = new FullCalendar.Calendar(document.getElementById('agendaCalendar'), {
            locale: 'es',
            headerToolbar: { left: 'today prev,next', center: 'title', right: 'timeGridDay,timeGridWeek,dayGridMonth' },
            initialView: 'timeGridWeek',
            height: '100%',
            nowIndicator: true,
            slotMinTime: '06:00:00',
            buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
            slotLabelFormat: { hour: 'numeric', minute: '2-digit', omitZeroMinute: true, meridiem: 'short', hour12: true },
            slotLabelContent: function (arg) {
                return formatoHora12(arg.text);
            },
            eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true },
            displayEventEnd: true,
            eventContent: function (arg) {
                var wrap = document.createElement('div');
                wrap.className = 'gcal-event-content';

                var titulo = document.createElement('div');
                titulo.className = 'gcal-event-title';
                titulo.textContent = arg.event.title;
                wrap.appendChild(titulo);

                if (arg.timeText) {
                    var hora = document.createElement('div');
                    hora.className = 'gcal-event-time';
                    hora.textContent = formatoHora12(arg.timeText) + (arg.event.end ? ' (aprox)' : '');
                    wrap.appendChild(hora);
                }

                return { domNodes: [wrap] };
            },
            eventClick: function (info) {
                abrirEdicion(info.event.extendedProps);
            },
            datesSet: function (info) {
                if (!syncingMini && miniCalendar) {
                    syncingMini = true;
                    miniCalendar.gotoDate(info.start);
                    syncingMini = false;
                }
                highlightMiniRange(info.start, info.end);
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
                cerrarYearPicker();
                calendar.gotoDate(info.date);
            },
            datesSet: function (info) {
                if (!syncingMini) {
                    syncingMini = true;
                    calendar.gotoDate(info.start);
                    syncingMini = false;
                }
                var view = calendar.view;
                if (view) highlightMiniRange(view.activeStart, view.activeEnd);
                document.getElementById('agendaMiniHeaderLabel').textContent = info.view.title;
            }
        });
        miniCalendar.render();

        function colapsarMiniCalendario(colapsado) {
            document.getElementById('agendaMiniCalendarWrap').classList.toggle('collapsed', colapsado);
            localStorage.setItem('agendaMiniColapsado', colapsado ? '1' : '0');
        }
        document.getElementById('agendaMiniToggle').addEventListener('click', function () {
            colapsarMiniCalendario(!document.getElementById('agendaMiniCalendarWrap').classList.contains('collapsed'));
        });
        if (localStorage.getItem('agendaMiniColapsado') === '1') {
            colapsarMiniCalendario(true);
        }

        document.querySelector('.gcal-mini-calendar .fc-toolbar-title').style.cursor = 'pointer';
        document.querySelector('.gcal-mini-calendar .fc-toolbar-title').addEventListener('click', function () {
            var picker = document.getElementById('agendaMiniYearPicker');
            if (picker.classList.contains('active')) {
                cerrarYearPicker();
            } else {
                abrirYearPicker();
            }
        });
        document.getElementById('agendaMiniYearPrev').addEventListener('click', function () {
            yearPickerYear -= 1;
            pintarYearGrid();
        });
        document.getElementById('agendaMiniYearNext').addEventListener('click', function () {
            yearPickerYear += 1;
            pintarYearGrid();
        });

        map = L.map('agendaMap').setView([-2.170998, -79.922359], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        }).addTo(map);

        cargarAgenda();

        document.getElementById('agendaBtnActualizar').addEventListener('click', cargarAgenda);
        document.getElementById('agendaFiltroPromotor').addEventListener('change', cargarAgenda);
        document.getElementById('agendaFiltroEstado').addEventListener('change', cargarAgenda);
        document.getElementById('agendaBusqueda').addEventListener('input', renderizar);

        document.getElementById('agendaEditCancelar').addEventListener('click', cerrarEdicion);
        document.getElementById('agendaEditClose').addEventListener('click', cerrarEdicion);
        document.getElementById('agendaEditGuardar').addEventListener('click', guardarEdicion);
        document.getElementById('agendaEditCancelarVisita').addEventListener('click', cancelarVisita);
        document.getElementById('agendaEditEliminar').addEventListener('click', eliminarVisita);

        document.getElementById('agendaCrearBtn').addEventListener('click', function () {
            alert('Crear nueva visita: flujo de creación pendiente de definir.');
        });

        document.getElementById('agendaMapToggle').addEventListener('click', function () {
            document.getElementById('agendaMapPanel').classList.toggle('collapsed');
            this.classList.toggle('collapsed');
            setTimeout(function () {
                if (map) map.invalidateSize();
                if (calendar) calendar.updateSize();
            }, 320);
        });

        document.addEventListener('click', function (ev) {
            var picker = document.getElementById('agendaMiniYearPicker');
            if (!picker.classList.contains('active')) return;
            if (picker.contains(ev.target)) return;
            if (ev.target.closest('.fc-toolbar-title')) return;
            cerrarYearPicker();
        });
    });
})();
</script>
