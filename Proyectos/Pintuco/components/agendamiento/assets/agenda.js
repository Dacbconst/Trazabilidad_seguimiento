(function () {
    var GETTERS_BASE = document.getElementById('agendaApp').dataset.gettersBase;
    var MAPBOX_TOKEN = document.getElementById('agendaApp').dataset.mapboxToken;
    var calendar, miniCalendar, map;
    var markersById = {};
    var editingId = null;
    var currentRows = [];      // dataset "principal": respeta el filtro de Estado (calendario/mapa/leyenda)
    var pendientesRows = [];   // dataset de "Agendas pendientes": mismos filtros MENOS Estado (ver cargarAgenda)
    var hiddenIds = {};
    var syncingMini = false;
    var yearPickerYear = new Date().getFullYear();
    var DURACION_APROX_MIN = 45;
    var estadosPorFecha = {}; // { 'YYYY-MM-DD': { agendada: true, ... } } — para los puntitos del mini-calendario

    // Mapa de la card de edición: mismo patrón de agenda-crear.js, IDs propios para no chocar.
    var editMapaPin = null;
    var editCoordenadas = null; // { lat, lng } — arranca con la ubicación ya guardada de la visita
    var editSnapshot = null; // foto de los campos al abrir, para saber si hay algo que guardar
    var PUNTO_INICIAL_EDICION = [-2.170998, -79.922359];
    var PATRON_PLUS_CODE_EDICION = /^[23456789CFGHJMPQRVWX]{4,8}\+[23456789CFGHJMPQRVWX]{2,3}$/i;
    var RE_EMPRESA_EDICION = /^[A-Za-z0-9ÁÉÍÓÚÑáéíóúñ.\-&' ]+$/;

    // Fecha local en formato YYYY-MM-DD (no toISOString, que corre por UTC).
    function hoyISO() {
        var h = new Date();
        return h.getFullYear() + '-' + String(h.getMonth() + 1).padStart(2, '0') + '-' + String(h.getDate()).padStart(2, '0');
    }

    function indexarEstadosPorFecha(rows) {
        estadosPorFecha = {};
        rows.forEach(function (r) {
            if (hiddenIds[r.id] || !r.fecha_agendamiento) return;
            var dia = estadosPorFecha[r.fecha_agendamiento] || (estadosPorFecha[r.fecha_agendamiento] = {});
            dia[estadoVisual(r)] = true;
        });
    }

    // Puntito bajo el día con eventos, igual que Google Calendar.
    function pintarPuntosMiniCalendario() {
        document.querySelectorAll('#agendaMiniCalendar .fc-daygrid-day').forEach(function (cell) {
            var anterior = cell.querySelector('.gcal-mini-day-dots');
            if (anterior) anterior.remove();

            var fecha = cell.getAttribute('data-date');
            var estados = fecha && estadosPorFecha[fecha];
            if (!estados) return;

            var frame = cell.querySelector('.fc-daygrid-day-frame');
            if (!frame) return;

            var dots = document.createElement('div');
            dots.className = 'gcal-mini-day-dots';
            Object.keys(estados).slice(0, 3).forEach(function (estado) {
                var dot = document.createElement('span');
                dot.className = 'gcal-mini-day-dot is-' + estado;
                dots.appendChild(dot);
            });
            frame.appendChild(dots);
        });
    }

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

    // Contrato de estados con la app móvil (Constantes.java): estado_agenda debe ser
    // siempre uno de estos 6 valores, cualquier otro string lo muestra la app sin color.
    var ESTADOS_VALIDOS = ['pendiente', 'confirmado', 'reagendada', 'vencida', 'cancelada', 'completada'];

    // El backend (update_agenda.php / get_agenda.php) es la única fuente de verdad del estado.
    function estadoVisual(r) {
        return ESTADOS_VALIDOS.indexOf(r.estado_agenda) !== -1 ? r.estado_agenda : 'pendiente';
    }

    var ESTADO_LABEL = {
        pendiente: 'Pendiente técnico',
        confirmado: 'Técnico confirmado',
        reagendada: 'Reagendada',
        vencida: 'Vencida',
        cancelada: 'Cancelada',
        completada: 'Completada'
    };

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
                // No registramos duración real: se asume DURACION_APROX_MIN para el fin.
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

    // Solo lista lo que requiere acción: sin técnico/hora ('pendiente') o vencida.
    function motivoPendiente(r) {
        if (estadoVisual(r) === 'pendiente') return { texto: 'PENDIENTE TÉCNICO', clase: 'is-pendiente' };
        return { texto: 'VENCIDA', clase: 'is-vencida' };
    }

    function pintarPendientes(rows) {
        var lista = document.getElementById('agendaPendientesList');
        var pendientes = rows
            .filter(function (r) {
                var estado = estadoVisual(r);
                return estado === 'pendiente' || estado === 'vencida';
            })
            .slice()
            .sort(function (a, b) {
                var va = estadoVisual(a) === 'vencida' ? 0 : 1; // vencidas primero
                var vb = estadoVisual(b) === 'vencida' ? 0 : 1;
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
                // refrescarEventos/indexarEstadosPorFecha ya descartan hiddenIds internamente.
                refrescarEventos(currentRows);
                indexarEstadosPorFecha(currentRows);
                pintarPuntosMiniCalendario();
            });

            var texto = document.createElement('div');
            texto.className = 'gcal-pendiente-card-text';

            // Fila 1: título + motivo (pendiente técnico o vencida).
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

    // "Pendientes"/"Vencidas" ignoran el filtro de Estado, igual que "Agendas pendientes".
    function pintarLeyenda(rows, pendientes) {
        var c = { confirmado: 0, reagendada: 0, cancelada: 0 };
        rows.forEach(function (r) { var e = estadoVisual(r); if (e in c) c[e]++; });
        var cAccion = { pendiente: 0, vencida: 0 };
        pendientes.forEach(function (r) { var e = estadoVisual(r); if (e in cAccion) cAccion[e]++; });
        document.getElementById('agendaCountPendientes').textContent = cAccion.pendiente;
        document.getElementById('agendaCountConfirmadas').textContent = c.confirmado;
        document.getElementById('agendaCountReagendadas').textContent = c.reagendada;
        document.getElementById('agendaCountVencidas').textContent = cAccion.vencida;
        document.getElementById('agendaCountCanceladas').textContent = c.cancelada;
    }

    function renderizar() {
        refrescarEventos(currentRows);
        pintarMapa(currentRows);
        pintarPendientes(pendientesRows);
        pintarLeyenda(currentRows, pendientesRows);
        indexarEstadosPorFecha(currentRows);
        pintarPuntosMiniCalendario();
    }

    // ── Filtros: Promotor / Técnico / PDV / Empresa / Estado ───────────
    // Los 4 primeros viajan como parámetros reales de get_agenda.php (AND entre sí).
    function paramsFiltros(incluirEstado) {
        var params = new URLSearchParams();
        var promotor = document.getElementById('agendaFiltroPromotor').value;
        var tecnico  = document.getElementById('agendaFiltroTecnico').value;
        var pdv      = document.getElementById('agendaFiltroPdv').value;
        var empresa  = document.getElementById('agendaFiltroEmpresa').value;
        if (promotor) params.set('usuario', promotor);
        if (tecnico)  params.set('tecnico', tecnico);
        if (pdv)      params.set('pdv', pdv);
        if (empresa)  params.set('empresa', empresa);
        var estado = document.getElementById('agendaFiltroEstado').value;
        if (incluirEstado && estado) {
            params.set('estado_agenda', estado);
        } else if (promotor || tecnico || pdv || empresa) {
            // Con algún filtro activo pero sin Estado, incluye también las visitas "completada".
            params.set('incluir_completadas', '1');
        }
        return params;
    }

    function cargarAgenda() {
        var paramsPrincipal  = paramsFiltros(true);
        // Ignora a propósito el filtro de Estado para "Agendas pendientes".
        var paramsPendientes = paramsFiltros(false);

        // Retorna la promesa para encadenar navegar+resaltar tras guardar.
        return Promise.all([
            fetch(GETTERS_BASE + 'get_agenda.php?' + paramsPrincipal.toString()).then(function (r) { return r.json(); }),
            fetch(GETTERS_BASE + 'get_agenda.php?' + paramsPendientes.toString()).then(function (r) { return r.json(); })
        ]).then(function (respuestas) {
            currentRows = respuestas[0].data || [];
            pendientesRows = respuestas[1].data || [];
            renderizar();
            // agenda-crear.js lee esto para llenar su select de Promotor
            // sin tener que pedirle los mismos datos de nuevo al getter.
            window.AgendaCurrentRows = currentRows;
        });
    }

    // Universo completo sin filtros: fuente de los desplegables de Promotor y Empresa.
    var opcionesBaseCache = null;
    function cargarOpcionesBase() {
        if (opcionesBaseCache) return Promise.resolve(opcionesBaseCache);
        var params = new URLSearchParams();
        params.set('incluir_completadas', '1');
        return fetch(GETTERS_BASE + 'get_agenda.php?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (json) {
                opcionesBaseCache = json.data || [];
                return opcionesBaseCache;
            });
    }

    function poblarSelectDistinct(selectId, rows, campo, etiquetaTodos) {
        var select = document.getElementById(selectId);
        var valorPrevio = select.value;
        var vistos = {};
        var valores = [];
        rows.forEach(function (r) {
            var v = r[campo];
            if (v && !vistos[v]) { vistos[v] = true; valores.push(v); }
        });
        valores.sort(function (a, b) { return a.localeCompare(b, 'es'); });
        select.innerHTML = '<option value="">' + etiquetaTodos + '</option>';
        valores.forEach(function (v) {
            var opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            select.appendChild(opt);
        });
        // Preserva el técnico elegido si sigue siendo válido para el nuevo promotor.
        if (valorPrevio && valores.indexOf(valorPrevio) !== -1) select.value = valorPrevio;
    }

    // Técnicos con agendamiento (con o sin promotor); cuenta todos los estados,
    // el filtro de Estado no debe acotar esta lista.
    function cargarOpcionesTecnico(promotor) {
        var params = new URLSearchParams();
        params.set('incluir_completadas', '1');
        if (promotor) params.set('usuario', promotor);
        return fetch(GETTERS_BASE + 'get_agenda.php?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (json) {
                poblarSelectDistinct('agendaFiltroTecnico', json.data || [], 'tecnico', 'Todos');
            });
    }

    // El filtro de PDV se puebla desde el universo real de insert_proyectos_contacto
    // (solo PDV con agendamiento), no desde el catálogo externo get_pdvs.php.
    // Ese catálogo se sigue usando tal cual en "Crear visita" (agenda-crear.js).

    // Expuesto para que agenda-crear.js recargue/resalte el calendario tras guardar,
    // sin conocer la implementación interna de cargarAgenda().
    window.AgendaRecargar = cargarAgenda;
    // Vuelve el calendario a "Hoy" al entrar a la sección desde el sidebar.
    window.AgendaEntrar = function () {
        if (calendar) calendar.today();
        return cargarAgenda();
    };
    window.AgendaResaltar = function (id, fecha, hora) { resaltarVisita(id, fecha, hora); };
    // Reusado por agenda-crear.js: mismo diálogo de conflicto que "Editar visita".
    window.AgendaMostrarConflicto = mostrarConflicto;

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

    // Mismo formato que usa la cabecera de días del calendario grande
    // (ej. "VIE 26/6"), para la píldora de fecha del diálogo de conflicto.
    var DIAS_CORTOS = ['DOM', 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB'];
    function formatDiaCorto(iso) {
        if (!iso) return '';
        var partes = iso.split('-');
        var d = new Date(iso + 'T00:00:00');
        return DIAS_CORTOS[d.getDay()] + ' ' + parseInt(partes[2], 10) + '/' + parseInt(partes[1], 10);
    }

    function sumarMinutosHora(hora, minutos) {
        var partes = hora.split(':');
        var total = (parseInt(partes[0], 10) * 60 + parseInt(partes[1], 10) + minutos) % (24 * 60);
        var h = Math.floor(total / 60), m = total % 60;
        return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
    }

    // fecha_registro llega como datetime de MySQL ("YYYY-MM-DD HH:MM:SS"),
    // independiente de la fecha/hora de agendamiento que elige el analista.
    function formatFechaHoraRegistro(valor) {
        if (!valor) return null;
        var partes = valor.split(' ');
        var fecha = formatFecha(partes[0]);
        if (partes.length < 2) return fecha;
        var hora = partes[1].slice(0, 5);
        return fecha + ' ' + hora;
    }

    function formatHoraVisual(hora) {
        if (!hora) return '—';
        var partes = hora.split(':');
        var h = parseInt(partes[0], 10);
        var h12 = h % 12 || 12;
        return h12 + ':' + (partes[1] || '00') + ' ' + (h >= 12 ? 'PM' : 'AM');
    }

    // Mismo límite que se ve en la agenda (6 AM-11 PM); se valida igual al
    // guardar aunque el panel ya solo ofrezca horas de ese rango.
    function horaEnRango(hora) {
        if (!hora) return false;
        var partes = hora.split(':');
        var minutos = parseInt(partes[0], 10) * 60 + parseInt(partes[1], 10);
        return minutos >= 6 * 60 && minutos <= 23 * 60;
    }

    // Panel propio con scroll interno, no <select> nativo (69 opciones desplegaría
    // una lista gigante). El valor real vive en data-value del wrapper.
    var HORA_OPCION_PASO = 15;

    function getHora() {
        return document.getElementById('agendaEditHora').dataset.value || '';
    }

    function setHora(hora) {
        document.getElementById('agendaEditHora').dataset.value = hora || '';
        document.getElementById('agendaEditHoraTrigger').textContent =
            hora ? formatHoraVisual(hora) : 'Selecciona una hora';
    }

    function construirOpcionesHora() {
        var lista = document.getElementById('agendaEditHoraLista');
        lista.innerHTML = '';
        for (var min = 6 * 60; min <= 23 * 60; min += HORA_OPCION_PASO) {
            var hh = Math.floor(min / 60), mm = min % 60;
            var valor = (hh < 10 ? '0' : '') + hh + ':' + (mm < 10 ? '0' : '') + mm;
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'agenda-edit-hora-item';
            item.dataset.valor = valor;
            item.textContent = formatHoraVisual(valor);
            item.addEventListener('click', function () {
                setHora(this.dataset.valor);
                cerrarHoraDropdown();
                actualizarEstadoGuardar();
            });
            lista.appendChild(item);
        }
    }

    function abrirHoraDropdown() {
        document.getElementById('agendaEditHora').classList.add('abierto');
        var actualValor = getHora();
        var lista = document.getElementById('agendaEditHoraLista');

        // position:fixed contra el trigger para escapar del overflow-y:auto del card,
        // que recortaba la lista si la fila de hora quedaba cerca del borde inferior.
        var rect = document.getElementById('agendaEditHoraTrigger').getBoundingClientRect();
        lista.style.position = 'fixed';
        lista.style.top = (rect.bottom + 4) + 'px';
        lista.style.left = rect.left + 'px';
        lista.style.width = rect.width + 'px';
        lista.style.right = 'auto';

        lista.querySelectorAll('.agenda-edit-hora-item').forEach(function (item) {
            item.classList.toggle('is-actual', item.dataset.valor === actualValor);
        });
        var actual = lista.querySelector('.is-actual');
        if (actual) {
            actual.scrollIntoView({ block: 'center' });
        } else {
            // El panel no se recrea entre aperturas, hay que resetear el scroll a mano.
            lista.scrollTop = 0;
        }
    }

    function cerrarHoraDropdown() {
        document.getElementById('agendaEditHora').classList.remove('abierto');
    }

    // La clase is-editando decide en CSS qué se ve (texto o input/mapa); Promotor
    // y Local quedan fuera, siempre son solo texto.
    function setModoEdicion(activo) {
        document.getElementById('agendaEditCard').classList.toggle('is-editando', activo);
        document.getElementById('agendaEditModoEdicion').checked = activo;
        document.getElementById('agendaEditModoTexto').textContent = activo ? 'Editando' : 'Modo edición';
        if (activo) inicializarMapaEdicion();
    }

    // "Guardar" queda fijo en pantalla, solo se activa cuando hay un cambio real contra
    // este snapshot. "modoEdicion" queda fuera a propósito: activar el switch solo no cuenta.
    function tomarSnapshotEdicion() {
        return {
            fecha: document.getElementById('agendaEditFecha').value,
            hora: getHora(),
            tecnico: document.getElementById('agendaEditTecnico').value,
            empresa: document.getElementById('agendaEditEmpresa').value,
            mail: document.getElementById('agendaEditMail').value,
            direccion: document.getElementById('agendaEditDireccion').value,
            celular: document.getElementById('agendaEditCelular').value,
            convencional: document.getElementById('agendaEditConvencional').value,
            lat: editCoordenadas ? editCoordenadas.lat : null,
            lng: editCoordenadas ? editCoordenadas.lng : null
        };
    }

    function actualizarEstadoGuardar() {
        var btn = document.getElementById('agendaEditGuardar');
        var hayCambios = false;
        if (editSnapshot) {
            var actual = tomarSnapshotEdicion();
            hayCambios = Object.keys(actual).some(function (k) { return actual[k] !== editSnapshot[k]; });
        }
        btn.disabled = !hayCambios;
        btn.classList.toggle('is-activo', hayCambios);
    }

    // Mismo patrón de mapa que agenda-crear.js; arranca centrado en la ubicación ya
    // guardada de la visita (editCoordenadas) en vez del punto por defecto.
    function inicializarMapaEdicion() {
        var centro = editCoordenadas ? [editCoordenadas.lat, editCoordenadas.lng] : PUNTO_INICIAL_EDICION;
        if (editMapaPin) {
            editMapaPin.setView(centro, 15);
            setTimeout(function () { editMapaPin.invalidateSize(); }, 80);
            return;
        }
        editMapaPin = L.map(document.getElementById('agendaEditMapaPin')).setView(centro, 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        }).addTo(editMapaPin);
        setTimeout(function () { editMapaPin.invalidateSize(); }, 80);
    }

    function esPlusCodeEdicion(texto) {
        return PATRON_PLUS_CODE_EDICION.test((texto || '').trim());
    }

    // Única consulta a Mapbox de este flujo: se dispara solo al hacer clic
    // en "Confirmar pin", igual que en agenda-crear.js.
    function confirmarPinEdicion() {
        var pos = editMapaPin.getCenter();
        editCoordenadas = { lat: pos.lat, lng: pos.lng };
        actualizarEstadoGuardar();

        if (!MAPBOX_TOKEN) {
            alert('Pin fijado. Falta el token de Mapbox para autocompletar la calle — escríbela a mano.');
            return;
        }

        var url = 'https://api.mapbox.com/search/geocode/v6/reverse'
            + '?longitude=' + pos.lng + '&latitude=' + pos.lat
            + '&language=es&access_token=' + MAPBOX_TOKEN;

        var input = document.getElementById('agendaEditDireccion');
        fetch(url)
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                var feature = (json.features || [])[0];
                var nombre = feature && (feature.properties.full_address || feature.properties.name);
                if (!nombre || esPlusCodeEdicion(nombre)) return;
                input.value = nombre;
            });
    }

    function validarEmailEdicion(valor) {
        var partes = valor.split('@');
        if (partes.length !== 2) return false;
        var local = partes[0], dominio = partes[1];
        if (local.length < 2) return false;
        if (local.charAt(0) === '.' || local.charAt(local.length - 1) === '.') return false;
        if (valor.indexOf('..') !== -1) return false;
        if (!/^[^\s@]+$/.test(local)) return false;
        if (!/^[^\s@]+\.[^\s@]+$/.test(dominio)) return false;
        return true;
    }

    function abrirEdicion(props) {
        editingId = props.id;
        document.getElementById('agendaEditTitulo').textContent =
            (props.titulo && props.titulo.trim() !== '') ? props.titulo : '(Sin título)';
        var estado = estadoVisual(props);
        var badge = document.getElementById('agendaEditBadge');
        document.getElementById('agendaEditBadgeTexto').textContent = ESTADO_LABEL[estado];
        badge.className = 'agenda-edit-badge is-' + estado;

        // Motivo de una reagendación ya guardada (solo lectura); distinto del textarea
        // de más abajo, que arranca vacío porque es para una reagendación nueva.
        var motivoPrevioWrap = document.getElementById('agendaEditMotivoPrevioWrap');
        if (estado === 'reagendada' && props.motivo_reagendacion) {
            document.getElementById('agendaEditMotivoPrevioTexto').textContent = props.motivo_reagendacion;
            motivoPrevioWrap.style.display = '';
        } else {
            motivoPrevioWrap.style.display = 'none';
        }

        var registro = formatFechaHoraRegistro(props.fecha_registro);
        document.getElementById('agendaEditRegistro').textContent = registro ? ('Registrado: ' + registro) : '';

        // En "pendiente" la fecha del lado móvil es solo sugerencia inicial, no confirmada.
        document.getElementById('agendaEditFechaLabel').textContent =
            estado === 'pendiente' ? 'Sugerido' : 'Fecha agendada';

        // Promotor y Local: siempre solo texto, el switch de edición no los toca.
        document.getElementById('agendaEditPromotor').textContent = props.usuario || '—';
        document.getElementById('agendaEditLocal').textContent = props.pdv || '—';

        document.getElementById('agendaEditEmpresaTexto').textContent = props.empresa || '—';
        document.getElementById('agendaEditEmpresa').value = props.empresa || '';

        // Punto de corte tras el "@" para que el navegador no parta el correo a mitad de palabra.
        var mailTexto = document.getElementById('agendaEditMailTexto');
        mailTexto.textContent = '';
        if (props.mail) {
            var arrobaPos = props.mail.indexOf('@');
            if (arrobaPos === -1) {
                mailTexto.textContent = props.mail;
            } else {
                mailTexto.appendChild(document.createTextNode(props.mail.slice(0, arrobaPos + 1)));
                mailTexto.appendChild(document.createElement('wbr'));
                mailTexto.appendChild(document.createTextNode(props.mail.slice(arrobaPos + 1)));
            }
        } else {
            mailTexto.textContent = '—';
        }
        document.getElementById('agendaEditMail').value = props.mail || '';

        document.getElementById('agendaEditDireccionTexto').textContent = props.direccion || '—';
        document.getElementById('agendaEditDireccion').value = props.direccion || '';

        // Contacto: solo lectura, viene tal cual de la BD (nunca se guarda desde acá).
        document.getElementById('agendaEditContactoTexto').textContent = props.contacto || '—';

        // Registros viejos traen "celular / convencional" pegados en telefono.
        var soloCelular = (props.telefono || '').split('/')[0].trim();
        document.getElementById('agendaEditCelularTexto').textContent = soloCelular || '—';
        document.getElementById('agendaEditCelular').value = soloCelular;
        document.getElementById('agendaEditConvencionalTexto').textContent = props.telefono_convencional || 'No registrado';
        document.getElementById('agendaEditConvencional').value = props.telefono_convencional || '';

        // Si el analista activa el switch sin tocar el mapa, esta coordenada se reenvía tal cual.
        var lat = parseFloat(props.latitud), lng = parseFloat(props.longitud);
        editCoordenadas = (!isNaN(lat) && !isNaN(lng)) ? { lat: lat, lng: lng } : null;

        document.getElementById('agendaEditFecha').value = props.fecha_agendamiento || '';
        // La BD guarda "HH:MM:SS"; el panel usa "HH:MM", aunque no caiga en un slot de 15 min.
        setHora(props.hora ? props.hora.slice(0, 5) : '');
        document.getElementById('agendaEditTecnico').value = props.tecnico || '';

        // "Pendiente técnico" oculta el switch ver/editar; no activa modo edición.
        document.getElementById('agendaEditModeSwitchWrap').style.display =
            (estado === 'pendiente') ? 'none' : '';
        setModoEdicion(false);

        // get_agenda.php ya marca 'vencida' en la BD; aquí se le pide al analista reagendar.
        var alerta = document.getElementById('agendaEditAlerta');
        // El campo de fecha queda en rojo mientras esté vencida, para forzar a tocarla
        // antes de guardar (antes se podía guardar solo el motivo y quedaba vencida).
        var campoFecha = document.querySelector('.agenda-edit-agendar-campo[data-campo="fecha"]');
        campoFecha.classList.toggle('is-invalid', estado === 'vencida');
        if (estado === 'vencida') {
            document.getElementById('agendaEditAlertaTexto').textContent =
                'Esta visita estaba programada para el ' + formatFecha(props.fecha_agendamiento) + ' y ya venció. Reagenda la fecha antes de guardar.';
            alerta.style.display = 'flex';
        } else {
            alerta.style.display = 'none';
        }

        // Motivo de reagendación: solo aparece y se exige cuando la visita estaba Vencida.
        var motivoInput = document.getElementById('agendaEditMotivo');
        motivoInput.value = '';
        motivoInput.classList.remove('is-invalid');
        document.getElementById('agendaEditErrMotivo').textContent = '';
        document.getElementById('agendaEditMotivoWrap').style.display = (estado === 'vencida') ? 'block' : 'none';

        // Foto de "cómo llegó" la card; cualquier diferencia contra esto activa "Guardar".
        editSnapshot = tomarSnapshotEdicion();
        actualizarEstadoGuardar();

        document.getElementById('agendaEditOverlay').classList.add('active');

        var marker = markersById[editingId];
        if (marker) {
            map.setView(marker.getLatLng(), 15);
            marker.openPopup();
        }
    }

    function cerrarEdicion() {
        editingId = null;
        editSnapshot = null;
        setModoEdicion(false);
        document.getElementById('agendaEditOverlay').classList.remove('active');
    }

    // Navega directo a la fecha/hora de la visita guardada y resalta el bloque.
    function resaltarVisita(id, fecha, hora) {
        if (!fecha) return;
        var fechaObjetivo = new Date(fecha + 'T' + (hora || '00:00'));
        // Se queda en semana si ya estaba ahí; mes/día caen a día, mes no muestra horas.
        var vistaDestino = calendar.view.type === 'timeGridWeek' ? 'timeGridWeek' : 'timeGridDay';
        calendar.changeView(vistaDestino, fechaObjetivo);
        if (hora) calendar.scrollToTime(hora + ':00');

        // Defensivo: repinta por si changeView montó celdas nuevas del mini-calendario.
        pintarPuntosMiniCalendario();

        setTimeout(function () {
            var el = document.querySelector('[data-event-id="' + id + '"]');
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('agenda-evt-resaltado');
            setTimeout(function () { el.classList.remove('agenda-evt-resaltado'); }, 2200);
        }, 150);
    }

    function mostrarConflicto(conflicto, fecha) {
        document.getElementById('agendaConflictoMiniFecha').textContent = formatDiaCorto(fecha);

        // Misma clase de color que usa el evento real en el calendario grande.
        document.getElementById('agendaConflictoMiniEvento').className =
            'agenda-conflicto-mini-evento agenda-evt-' + estadoVisual(conflicto);

        document.getElementById('agendaConflictoMiniTitulo').textContent =
            (conflicto.titulo && conflicto.titulo.trim() !== '') ? conflicto.titulo : (conflicto.pdv || 'Visita');

        var fin = sumarMinutosHora(conflicto.hora, DURACION_APROX_MIN);
        document.getElementById('agendaConflictoMiniHora').textContent =
            formatHoraVisual(conflicto.hora) + ' - ' + formatHoraVisual(fin) + ' (aprox)';

        document.getElementById('agendaConflictoOverlay').classList.add('active');
    }

    function cerrarConflicto() {
        document.getElementById('agendaConflictoOverlay').classList.remove('active');
    }

    function guardarEdicion() {
        if (!editingId) return;
        var idGuardado = editingId;
        var fechaGuardada = document.getElementById('agendaEditFecha').value;
        var horaGuardada = getHora();

        // Se valida el rango igual por si quedó una hora legado sin tocar.
        if (!horaEnRango(horaGuardada)) {
            var alertaHora = document.getElementById('agendaEditAlerta');
            document.getElementById('agendaEditAlertaTexto').textContent =
                'Selecciona una hora entre 6:00 AM y 11:00 PM, el rango que se ve en la agenda.';
            alertaHora.style.display = 'flex';
            document.getElementById('agendaEditHoraTrigger').focus();
            return;
        }

        // El motivo solo es visible/obligatorio cuando la visita estaba Vencida.
        var motivoWrap = document.getElementById('agendaEditMotivoWrap');
        var reagendandoVencida = motivoWrap.style.display !== 'none';
        var motivoReagendacion = document.getElementById('agendaEditMotivo').value.trim();
        if (reagendandoVencida && !motivoReagendacion) {
            document.getElementById('agendaEditMotivo').classList.add('is-invalid');
            document.getElementById('agendaEditErrMotivo').textContent = 'El motivo de la reagendación es obligatorio.';
            document.getElementById('agendaEditMotivo').focus();
            return;
        }

        // Exige fecha nueva (hoy o futura); mismo criterio que valida update_agenda.php.
        if (reagendandoVencida && (!fechaGuardada || fechaGuardada < hoyISO())) {
            var campoFechaInvalido = document.querySelector('.agenda-edit-agendar-campo[data-campo="fecha"]');
            campoFechaInvalido.classList.add('is-invalid');
            document.getElementById('agendaEditAlertaTexto').textContent =
                'Elige una fecha válida (hoy o posterior) — no se puede reagendar dejando la misma fecha vencida.';
            document.getElementById('agendaEditAlerta').style.display = 'flex';
            document.getElementById('agendaEditFecha').focus();
            return;
        }

        var body = new URLSearchParams();
        body.set('id', idGuardado);
        body.set('fecha', fechaGuardada);
        body.set('hora', horaGuardada);
        body.set('tecnico', document.getElementById('agendaEditTecnico').value);
        if (reagendandoVencida) {
            body.set('motivo_reagendacion', motivoReagendacion);
        }
        // Los campos del switch solo se mandan y validan si el switch estuvo activo.
        if (document.getElementById('agendaEditModoEdicion').checked) {
            var empresa = document.getElementById('agendaEditEmpresa').value.trim();
            var mail = document.getElementById('agendaEditMail').value.trim();
            var direccion = document.getElementById('agendaEditDireccion').value.trim();
            var celular = document.getElementById('agendaEditCelular').value.trim();
            var convencional = document.getElementById('agendaEditConvencional').value.trim();

            if (!empresa || !RE_EMPRESA_EDICION.test(empresa)) { alert('Empresa inválida.'); return; }
            if (!mail || !validarEmailEdicion(mail)) { alert('Correo inválido.'); return; }
            if (!direccion) { alert('La dirección es obligatoria.'); return; }
            if (esPlusCodeEdicion(direccion)) { alert('Esa dirección parece un Plus Code — escribe una más específica.'); return; }
            if (!/^\d{10}$/.test(celular)) { alert('El celular debe ser numérico y de exactamente 10 dígitos.'); return; }
            if (convencional && !/^\d+$/.test(convencional)) { alert('El teléfono convencional solo admite dígitos.'); return; }

            body.set('empresa', empresa);
            body.set('mail', mail);
            body.set('direccion', direccion);
            body.set('latitud', editCoordenadas ? editCoordenadas.lat : '');
            body.set('longitud', editCoordenadas ? editCoordenadas.lng : '');
            body.set('telefono', celular);
            body.set('telefono_convencional', convencional);
        }

        fetch(GETTERS_BASE + 'update_agenda.php', { method: 'POST', body: body })
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                if (json.success) {
                    cerrarEdicion();
                    cargarAgenda().then(function () {
                        resaltarVisita(idGuardado, fechaGuardada, horaGuardada);
                    });
                } else if (json.conflicto) {
                    mostrarConflicto(json.conflicto, fechaGuardada);
                } else if (json.requiere_motivo) {
                    // El servidor detectó 'vencida' aunque el panel se abrió antes; se revela el campo.
                    document.getElementById('agendaEditMotivoWrap').style.display = 'block';
                    document.getElementById('agendaEditMotivo').classList.add('is-invalid');
                    document.getElementById('agendaEditErrMotivo').textContent = json.message || 'El motivo de la reagendación es obligatorio.';
                    document.getElementById('agendaEditMotivo').focus();
                } else if (json.requiere_fecha) {
                    // Respaldo del servidor por si el chequeo de fecha del
                    // cliente se saltó (misma mecánica que requiere_motivo).
                    var campoFechaRechazado = document.querySelector('.agenda-edit-agendar-campo[data-campo="fecha"]');
                    campoFechaRechazado.classList.add('is-invalid');
                    document.getElementById('agendaEditAlertaTexto').textContent = json.message || 'Elige una fecha válida (hoy o posterior) para reagendar.';
                    document.getElementById('agendaEditAlerta').style.display = 'flex';
                    document.getElementById('agendaEditFecha').focus();
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
            slotMaxTime: '23:00:00',
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

                // El bloque solo tiene altura para 2 líneas; empresa y hora comparten la 2da.
                var empresa = arg.event.extendedProps.empresa;
                var horaTexto = arg.timeText ? (formatoHora12(arg.timeText) + (arg.event.end ? ' (aprox)' : '')) : '';
                var subtitulo = [empresa, horaTexto].filter(Boolean).join(' · ');
                if (subtitulo) {
                    var sub = document.createElement('div');
                    sub.className = 'gcal-event-time';
                    sub.textContent = subtitulo;
                    wrap.appendChild(sub);
                }

                return { domNodes: [wrap] };
            },
            // data-event-id permite ubicar el bloque en el DOM después de un
            // guardado, para navegar y resaltarlo (ver resaltarVisita()).
            eventDidMount: function (info) {
                info.el.dataset.eventId = info.event.id;
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
                pintarPuntosMiniCalendario();
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

        // zoomControl en 'topright' para no chocar con el botón hamburguesa del mapa.
        map = L.map('agendaMap', { zoomControl: false }).setView([-2.170998, -79.922359], 12);
        L.control.zoom({ position: 'topright' }).addTo(map);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        }).addTo(map);

        construirOpcionesHora();

        // Universo completo se pide una sola vez; Técnico se recalcula al cambiar Promotor.
        cargarOpcionesBase().then(function (base) {
            poblarSelectDistinct('agendaFiltroPromotor', base, 'usuario', 'Todos');
            poblarSelectDistinct('agendaFiltroPdv', base, 'pdv', 'Todos');
            poblarSelectDistinct('agendaFiltroEmpresa', base, 'empresa', 'Todas');
        });
        cargarOpcionesTecnico('');
        cargarAgenda();

        // Mismo widget de combo con buscador que "Crear visita" (agenda-crear.js).
        ['agendaFiltroPromotor', 'agendaFiltroTecnico', 'agendaFiltroPdv', 'agendaFiltroEmpresa'].forEach(function (id) {
            window.AgendaHabilitarComboBuscador(id);
        });

        document.getElementById('agendaBtnActualizar').addEventListener('click', cargarAgenda);
        document.getElementById('agendaBtnBuscar').addEventListener('click', cargarAgenda);
        // Elegir un filtro no recarga solo, solo al apretar "Buscar"/"Actualizar".
        // Promotor es la excepción: recalcula las opciones de Técnico sin traer datos.
        document.getElementById('agendaFiltroPromotor').addEventListener('change', function () {
            cargarOpcionesTecnico(this.value);
        });

        document.getElementById('agendaEditCancelar').addEventListener('click', cerrarEdicion);
        document.getElementById('agendaEditClose').addEventListener('click', cerrarEdicion);
        document.getElementById('agendaEditGuardar').addEventListener('click', guardarEdicion);
        document.getElementById('agendaEditCancelarVisita').addEventListener('click', cancelarVisita);
        document.getElementById('agendaEditEliminar').addEventListener('click', eliminarVisita);
        document.getElementById('agendaConflictoCerrar').addEventListener('click', cerrarConflicto);

        document.getElementById('agendaEditModoEdicion').addEventListener('change', function () {
            setModoEdicion(this.checked);
            actualizarEstadoGuardar();
        });
        document.getElementById('agendaEditConfirmarPin').addEventListener('click', confirmarPinEdicion);

        // "Guardar" solo se activa si algo cambió; se escucha cada campo tocable.
        ['agendaEditFecha', 'agendaEditTecnico', 'agendaEditEmpresa', 'agendaEditMail',
            'agendaEditDireccion', 'agendaEditCelular', 'agendaEditConvencional'].forEach(function (id) {
            document.getElementById(id).addEventListener('input', actualizarEstadoGuardar);
        });

        document.getElementById('agendaEditHoraTrigger').addEventListener('click', function (ev) {
            ev.stopPropagation();
            if (document.getElementById('agendaEditHora').classList.contains('abierto')) {
                cerrarHoraDropdown();
            } else {
                abrirHoraDropdown();
            }
        });
        document.addEventListener('click', function (ev) {
            var wrapper = document.getElementById('agendaEditHora');
            if (!wrapper.classList.contains('abierto')) return;
            if (wrapper.contains(ev.target)) return;
            cerrarHoraDropdown();
        });
        // El panel usa fixed calculado a mano; más simple cerrarlo al scrollear que recalcular.
        document.querySelector('.agenda-edit-body').addEventListener('scroll', function () {
            if (document.getElementById('agendaEditHora').classList.contains('abierto')) {
                cerrarHoraDropdown();
            }
        });

        // El botón "Crear" abre su propio modal, lógica completa en agenda-crear.js.

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
