(function () {
    var GETTERS_BASE = document.getElementById('agendaApp').dataset.gettersBase;
    var MAPBOX_TOKEN = document.getElementById('agendaApp').dataset.mapboxToken;
    var calendar, miniCalendar, map;
    var markersById = {};
    var editingId = null;
    var currentRows = [];
    var hiddenIds = {};
    var syncingMini = false;
    var yearPickerYear = new Date().getFullYear();
    var DURACION_APROX_MIN = 45;
    var estadosPorFecha = {}; // { 'YYYY-MM-DD': { agendada: true, ... } } — para los puntitos del mini-calendario

    // Mapa de la card de edición (switch "Editar") — mismo patrón de pin fijo
    // + reverse-geocode Mapbox que ya usa agenda-crear.js, duplicado con IDs
    // propios para no chocar con el modal de creación (self-contenido, mismo
    // criterio que ya sigue este proyecto entre agenda.js/agenda-crear.js).
    var editMapaPin = null;
    var editCoordenadas = null; // { lat, lng } — arranca con la ubicación ya guardada de la visita
    var PUNTO_INICIAL_EDICION = [-2.170998, -79.922359];
    var PATRON_PLUS_CODE_EDICION = /^[23456789CFGHJMPQRVWX]{4,8}\+[23456789CFGHJMPQRVWX]{2,3}$/i;
    var RE_EMPRESA_EDICION = /^[A-Za-z0-9ÁÉÍÓÚÑáéíóúñ.\-&' ]+$/;

    function indexarEstadosPorFecha(rows) {
        estadosPorFecha = {};
        rows.forEach(function (r) {
            if (hiddenIds[r.id] || !r.fecha_agendamiento) return;
            var dia = estadosPorFecha[r.fecha_agendamiento] || (estadosPorFecha[r.fecha_agendamiento] = {});
            dia[estadoVisual(r)] = true;
        });
    }

    // Puntito bajo el número del día, igual que Google Calendar marca los
    // días con eventos. Se repinta tanto al cambiar de mes en el mini-
    // calendario (las celdas son nuevas) como al recargar/filtrar datos.
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

    // Contrato de estados acordado con la app móvil (Constantes.java /
    // AdapterAgenda.java): la app lee esta misma tabla directo por sync, así
    // que estado_agenda debe ser SIEMPRE uno de estos 6 valores literales —
    // cualquier otro string lo muestra la app sin color (fallback inerte).
    var ESTADOS_VALIDOS = ['pendiente', 'confirmado', 'reagendada', 'vencida', 'cancelada', 'completada'];

    // Ya no se re-deriva el estado a partir de hora/técnico: el backend
    // (update_agenda.php) y el cron de "vencida" en get_agenda.php son la
    // única fuente de verdad. Un valor legado o desconocido cae a "pendiente".
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

    // Solo se lista lo que de verdad requiere acción del analista: nunca se
    // le asignó técnico/hora ('pendiente'), o su fecha ya venció sin
    // reagendarse ('vencida' — la pone get_agenda.php automáticamente).
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
                var visibles = filtrarPorBusqueda(currentRows);
                refrescarEventos(visibles);
                // Los puntitos del mini-calendario también deben dejar de
                // marcar un día si la única visita de ese día se ocultó
                // desde este checkbox — no solo cuando se recarga de la BD.
                indexarEstadosPorFecha(visibles);
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

    function pintarLeyenda(rows) {
        var c = { pendiente: 0, confirmado: 0, reagendada: 0, vencida: 0, cancelada: 0, completada: 0 };
        rows.forEach(function (r) { c[estadoVisual(r)]++; });
        document.getElementById('agendaCountPendientes').textContent = c.pendiente;
        document.getElementById('agendaCountConfirmadas').textContent = c.confirmado;
        document.getElementById('agendaCountReagendadas').textContent = c.reagendada;
        document.getElementById('agendaCountVencidas').textContent = c.vencida;
        document.getElementById('agendaCountCanceladas').textContent = c.cancelada;
    }

    function filtrarPorBusqueda(rows) {
        var q       = document.getElementById('agendaBusqueda').value.toLowerCase().trim();
        var tecnico = document.getElementById('agendaFiltroTecnico').value;
        return rows.filter(function (r) {
            if (tecnico && r.tecnico !== tecnico) return false;
            if (q && ![r.titulo, r.pdv, r.empresa, r.contacto].some(function (v) {
                return (v || '').toLowerCase().indexOf(q) !== -1;
            })) return false;
            return true;
        });
    }

    function renderizar() {
        var rows = filtrarPorBusqueda(currentRows);
        refrescarEventos(rows);
        pintarMapa(rows);
        pintarPendientes(rows);
        pintarLeyenda(rows);
        indexarEstadosPorFecha(rows);
        pintarPuntosMiniCalendario();
    }

    var promotorOpcionesListas = false;
    var tecnicoOpcionesListas  = false;

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

    function construirOpcionesTecnico(rows) {
        var select = document.getElementById('agendaFiltroTecnico');
        var vistos = {};
        rows.forEach(function (r) {
            if (r.tecnico && !vistos[r.tecnico]) {
                vistos[r.tecnico] = true;
                var opt = document.createElement('option');
                opt.value = r.tecnico;
                opt.textContent = r.tecnico;
                select.appendChild(opt);
            }
        });
    }

    function cargarAgenda() {
        var promotor = document.getElementById('agendaFiltroPromotor').value;
        var estado   = document.getElementById('agendaFiltroEstado').value;
        var params = new URLSearchParams();
        if (promotor) params.set('usuario', promotor);
        if (estado)   params.set('estado_agenda', estado);

        // Se retorna la promesa para poder encadenar acciones que necesitan
        // esperar a que el calendario ya tenga los eventos repintados (p.ej.
        // navegar y resaltar la visita recién guardada).
        return fetch(GETTERS_BASE + 'get_agenda.php?' + params.toString())
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                currentRows = json.data || [];
                if (!promotorOpcionesListas && !promotor) {
                    construirOpcionesPromotor(currentRows);
                    promotorOpcionesListas = true;
                }
                if (!tecnicoOpcionesListas) {
                    construirOpcionesTecnico(currentRows);
                    tecnicoOpcionesListas = true;
                }
                renderizar();
                // agenda-crear.js lee esto para llenar su select de Promotor
                // sin tener que pedirle los mismos datos de nuevo al getter.
                window.AgendaCurrentRows = currentRows;
            });
    }

    // Para que agenda-crear.js pueda recargar el calendario después de
    // guardar una visita nueva, sin que ese archivo conozca nada de cómo
    // funciona internamente cargarAgenda(). AgendaResaltar es la misma
    // navegación+resalto que ya usa guardarEdicion() — sin esto, una visita
    // creada para una fecha fuera de la semana/día que se está viendo en
    // ese momento queda guardada en la BD pero invisible hasta navegar
    // manualmente hasta ahí (esto era el bug: "se guardó pero no apareció").
    window.AgendaRecargar = cargarAgenda;
    window.AgendaResaltar = function (id, fecha, hora) { resaltarVisita(id, fecha, hora); };
    // Reusado por agenda-crear.js: el modal de "Nueva visita" pega contra el
    // mismo endpoint/misma regla de conflicto (DURACION_APROX_MIN por
    // técnico) y quiere el mismo diálogo detallado, no un toast genérico que
    // desaparece solo — así el analista ve claramente CON QUÉ visita choca.
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

    // Panel propio con alto fijo y scroll interno (no un <select> nativo):
    // un <select> con 69 opciones (cada 15 min, 6 AM-11 PM) hace que el
    // navegador despliegue una lista gigante sin límite de alto — eso es
    // justo lo que Google Calendar/Outlook evitan usando un panel propio en
    // vez del control nativo. El valor real vive en data-value del wrapper.
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
            });
            lista.appendChild(item);
        }
    }

    function abrirHoraDropdown() {
        document.getElementById('agendaEditHora').classList.add('abierto');
        var actualValor = getHora();
        var lista = document.getElementById('agendaEditHoraLista');

        // El panel de hora vivía con position:absolute dentro de
        // .agenda-edit-body, que tiene overflow-y:auto — si la fila de
        // hora quedaba cerca del borde inferior visible, el navegador
        // recortaba la lista ahí mismo (se "ocultaba" dentro del card).
        // Posicionarlo con fixed, calculado a mano contra el trigger,
        // hace que escape de ese recorte porque ya no se mide contra el
        // contenedor con scroll, sino contra el viewport.
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
            // El panel no se recrea entre aperturas — sin esto, si quedó
            // scrolleado de una vez anterior, abre donde quedó en vez de
            // arrancar arriba cuando todavía no hay hora elegida.
            lista.scrollTop = 0;
        }
    }

    function cerrarHoraDropdown() {
        document.getElementById('agendaEditHora').classList.remove('abierto');
    }

    // Switch único de edición: reemplaza el lápiz por-campo de antes. La
    // clase is-editando en la card decide en CSS qué se ve — texto o
    // input/mapa — para todos los campos editables a la vez (Promotor y
    // Local quedan fuera de este mecanismo, siempre son solo texto).
    function setModoEdicion(activo) {
        document.getElementById('agendaEditCard').classList.toggle('is-editando', activo);
        document.getElementById('agendaEditModoEdicion').checked = activo;
        document.getElementById('agendaEditModoTexto').textContent = activo ? 'Editando' : 'Modo edición';
        if (activo) inicializarMapaEdicion();
    }

    // Mismo patrón de mapa que agenda-crear.js (Leaflet + pin fijo, sin
    // arrastre — el mapa se desplaza debajo). Arranca centrado en la
    // ubicación YA guardada de la visita (editCoordenadas, seteado en
    // abrirEdicion) en vez del punto por defecto, para no obligar al
    // analista a rebuscar la ubicación si solo va a corregir otro campo.
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

        var registro = formatFechaHoraRegistro(props.fecha_registro);
        document.getElementById('agendaEditRegistro').textContent = registro ? ('Registrado: ' + registro) : '';

        // Mientras nunca se confirmó (estado "pendiente"), la fecha que
        // llegó del lado móvil es solo una sugerencia inicial — la fecha
        // real recién se fija cuando el analista la confirma por primera
        // vez aquí en la web (cualquier cambio después de eso ya cuenta
        // como "reagendada", no como ajustar una sugerencia).
        document.getElementById('agendaEditFechaLabel').textContent =
            estado === 'pendiente' ? 'Sugerido' : 'Fecha agendada';

        // Promotor y Local: siempre solo texto, el switch de edición no los toca.
        document.getElementById('agendaEditPromotor').textContent = props.usuario || '—';
        document.getElementById('agendaEditLocal').textContent = props.pdv || '—';

        document.getElementById('agendaEditEmpresaTexto').textContent = props.empresa || '—';
        document.getElementById('agendaEditEmpresa').value = props.empresa || '';

        // Un correo es una sola "palabra" sin espacios: sin esto, el navegador
        // lo corta donde quiera (a mitad de "hotmail.com") en una columna
        // angosta — se le da un punto de corte sensato justo tras el "@".
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

        document.getElementById('agendaEditCelularTexto').textContent = props.telefono || '—';
        document.getElementById('agendaEditCelular').value = props.telefono || '';
        document.getElementById('agendaEditConvencionalTexto').textContent = props.telefono_convencional || 'No registrado';
        document.getElementById('agendaEditConvencional').value = props.telefono_convencional || '';

        // Arranca centrado en la ubicación ya guardada — si el analista
        // activa el switch sin tocar el mapa, esta misma coordenada se
        // reenvía tal cual (no se pierde por no haber confirmado un pin).
        var lat = parseFloat(props.latitud), lng = parseFloat(props.longitud);
        editCoordenadas = (!isNaN(lat) && !isNaN(lng)) ? { lat: lat, lng: lng } : null;

        document.getElementById('agendaEditFecha').value = props.fecha_agendamiento || '';
        // La BD guarda "HH:MM:SS"; el panel usa "HH:MM". Si la hora real no
        // cae en un slot de 15 min (datos legado, como el caso "00:00" que
        // encontramos), el botón la muestra igual tal cual es.
        setHora(props.hora ? props.hora.slice(0, 5) : '');
        document.getElementById('agendaEditTecnico').value = props.tecnico || '';

        setModoEdicion(false);

        // get_agenda.php ya marca 'vencida' en la BD cuando la fecha pactada
        // pasó sin reagendarse; aquí solo se le pide al analista que la
        // reagende ahora mismo en vez de dejarlo pasar desapercibido.
        var alerta = document.getElementById('agendaEditAlerta');
        if (estado === 'vencida') {
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
        setModoEdicion(false);
        document.getElementById('agendaEditOverlay').classList.remove('active');
    }

    // Después de guardar, el analista puede estar viendo un mes/semana
    // distinto al de la visita (por estar navegando el calendario "de
    // curioso"). Se lo lleva directo a la fecha y hora exactas donde quedó
    // insertada, y se resalta el bloque para que no tenga que buscarlo ni
    // scrollear a ciegas para encontrar la hora.
    function resaltarVisita(id, fecha, hora) {
        if (!fecha) return;
        var fechaObjetivo = new Date(fecha + 'T' + (hora || '00:00'));
        // Si ya estaba viendo semana, se queda en semana (solo navega dentro
        // de ella); cualquier otro caso (día o mes) usa día, porque mes no
        // muestra horas y no tendría sentido "mantenerse" ahí.
        var vistaDestino = calendar.view.type === 'timeGridWeek' ? 'timeGridWeek' : 'timeGridDay';
        calendar.changeView(vistaDestino, fechaObjetivo);
        if (hora) calendar.scrollToTime(hora + ':00');

        // Defensivo: renderizar() ya repintó los puntitos con datos frescos
        // antes de llegar aquí, pero si el changeView de arriba navega el
        // mini-calendario a un mes que no estaba montado en ese momento, se
        // vuelve a pintar ahora que sus celdas ya existen en el DOM.
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

        // Misma clase de color que usa el evento real en el calendario
        // grande (agenda-evt-confirmado/reagendada/vencida/...).
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

        // El panel ya solo ofrece horas de 6 AM-11 PM, pero se valida igual
        // por si quedó una hora legado fuera de ese rango sin tocar.
        if (!horaEnRango(horaGuardada)) {
            var alertaHora = document.getElementById('agendaEditAlerta');
            document.getElementById('agendaEditAlertaTexto').textContent =
                'Selecciona una hora entre 6:00 AM y 11:00 PM, el rango que se ve en la agenda.';
            alertaHora.style.display = 'flex';
            document.getElementById('agendaEditHoraTrigger').focus();
            return;
        }

        var body = new URLSearchParams();
        body.set('id', idGuardado);
        body.set('fecha', fechaGuardada);
        body.set('hora', horaGuardada);
        body.set('tecnico', document.getElementById('agendaEditTecnico').value);
        // El lugar se sincroniza con la dirección guardada y el estado
        // (confirmado/reagendada) lo decide el backend.

        // Los campos del switch solo se mandan (y validan) si el switch
        // estuvo activo — si el analista solo reagenda fecha/hora/técnico
        // sin haber tocado "Editar", el guardado se comporta exactamente
        // como antes, sin arriesgar datos legado que no pasen estas reglas.
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

                // El bloque solo tiene altura real para 2 líneas (lo mide la
                // duración de 45 min contra el rango 6am-11pm — una 3ra línea
                // se corta por overflow). Para que quepan las 3 cosas (título,
                // empresa, hora) sin que nada se corte, empresa y hora
                // comparten la 2da línea en vez de ir cada una en la suya.
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

        // zoomControl en 'topright': el control propio de Leaflet por defecto
        // se pone en la esquina superior izquierda, exactamente donde vive
        // nuestro botón hamburguesa de mostrar/ocultar el mapa — se mueve a
        // la derecha para que nunca choquen entre sí.
        map = L.map('agendaMap', { zoomControl: false }).setView([-2.170998, -79.922359], 12);
        L.control.zoom({ position: 'topright' }).addTo(map);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        }).addTo(map);

        construirOpcionesHora();
        cargarAgenda();

        document.getElementById('agendaBtnActualizar').addEventListener('click', cargarAgenda);
        document.getElementById('agendaFiltroPromotor').addEventListener('change', cargarAgenda);
        document.getElementById('agendaFiltroEstado').addEventListener('change', cargarAgenda);
        document.getElementById('agendaBusqueda').addEventListener('input', renderizar);
        document.getElementById('agendaFiltroTecnico').addEventListener('change', renderizar);

        document.getElementById('agendaEditCancelar').addEventListener('click', cerrarEdicion);
        document.getElementById('agendaEditClose').addEventListener('click', cerrarEdicion);
        document.getElementById('agendaEditGuardar').addEventListener('click', guardarEdicion);
        document.getElementById('agendaEditCancelarVisita').addEventListener('click', cancelarVisita);
        document.getElementById('agendaEditEliminar').addEventListener('click', eliminarVisita);
        document.getElementById('agendaConflictoCerrar').addEventListener('click', cerrarConflicto);

        document.getElementById('agendaEditModoEdicion').addEventListener('change', function () {
            setModoEdicion(this.checked);
        });
        document.getElementById('agendaEditConfirmarPin').addEventListener('click', confirmarPinEdicion);

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
        // El panel ahora se posiciona con fixed calculado a mano (ver
        // abrirHoraDropdown) — si el body del modal se scrollea mientras
        // está abierto, esas coordenadas quedan desactualizadas. Más
        // simple y confiable cerrarlo que andar recalculando en cada
        // evento de scroll.
        document.querySelector('.agenda-edit-body').addEventListener('scroll', function () {
            if (document.getElementById('agendaEditHora').classList.contains('abierto')) {
                cerrarHoraDropdown();
            }
        });

        // El botón "Crear" abre su propio modal — lógica completa en
        // agenda-crear.js (insert_contacto.php, selects de Promotor/PDV,
        // búsqueda de dirección con Mapbox).

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
