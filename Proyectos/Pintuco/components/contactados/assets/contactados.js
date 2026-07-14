(function () {
    var GETTERS_BASE = document.getElementById('contactadosApp').dataset.gettersBase;
    var currentRows = [];
    var FILAS_POR_PAGINA = 15;
    var paginaActual = 1;
    var selectedIds = {}; // { id: true } — IDs marcados, no índices (sobrevive a la paginación)
    var contactoGestionActual = null; // fila abierta en el modal "Gestión de Contacto"
    var gestionGruposCache = []; // rondas de TODO el historial del contacto abierto (sin filtrar por mes)
    var gestionFacturadoCache = {}; // facturadoPorProforma del contacto abierto, para re-filtrar sin refetch

    function formatFechaHora(valor) {
        if (!valor) return '—';
        var partes = valor.split(' ');
        var fechaPartes = partes[0].split('-');
        if (fechaPartes.length !== 3) return valor;
        var fecha = fechaPartes[2] + '/' + fechaPartes[1] + '/' + fechaPartes[0];
        if (partes.length < 2) return { fecha: fecha, hora: '' };
        return { fecha: fecha, hora: partes[1].slice(0, 5) };
    }

    // Consolida las filas del JOIN: puede llegar 1 fila por ciclo de proforma.
    // Se queda con la de mayor proforma_id por contacto (igual que ultimosCiclos
    // en estado-flujo.js), manteniendo el orden original de fecha_registro DESC.
    function consolidarContactos(rows) {
        var mapa = {};
        var orden = [];
        rows.forEach(function (r) {
            var key = String(r.id);
            var pid = parseInt(r.proforma_id, 10) || 0;
            if (!mapa[key]) {
                mapa[key] = {};
                for (var k in r) { if (r.hasOwnProperty(k)) mapa[key][k] = r[k]; }
                orden.push(key);
            } else {
                var pidActual = parseInt(mapa[key].proforma_id, 10) || 0;
                if (pid > pidActual) {
                    mapa[key].proforma_id   = r.proforma_id;
                    mapa[key].foto_factura  = r.foto_factura;
                    mapa[key].monto_validado = r.monto_validado;
                    mapa[key].evidencia     = r.evidencia;
                }
            }
        });
        return orden.map(function (k) { return mapa[k]; });
    }

    // Estado comercial del contacto — 5 valores posibles.
    // Cancelado pisa todo; vencido es una alerta encima del estado comercial.
    function getEstado(r) {
        if (r.estado_agenda === 'cancelada') return { label: 'Cancelado', cls: 'is-cancelado' };
        var vencido = r.estado_agenda === 'vencida';
        if (r.foto_factura)   return { label: 'Facturado',   cls: 'is-facturado',   vencido: vencido };
        if (r.monto_validado) return { label: 'Negociando',  cls: 'is-negociando',  vencido: vencido };
        // fecha_agendamiento llega de MySQL como '0000-00-00' (no NULL)
        // cuando no se ha agendado nada — mismo chequeo que ya usan
        // estado-flujo.js, principal.js, factura.js y proforma.js; sin él,
        // ese string no vacío se evalúa como truthy y marcaba "Agendado"
        // contactos que en realidad nunca tuvieron visita ni técnico.
        if (r.proforma_id || (r.fecha_agendamiento && r.fecha_agendamiento !== '0000-00-00') || r.tecnico)
                              return { label: 'Agendado',    cls: 'is-agendado',    vencido: vencido };
        // El promotor marcó desde el móvil que este contacto no necesita
        // visita técnica — para el analista cuenta igual como "ya pasó por
        // agendamiento" (mismo criterio que ya usa proforma.js/factura.js
        // en su timeline de auditoría), con su propia etiqueta.
        if (r.no_requiere_visita === 'SI')
                              return { label: 'No requirió visita', cls: 'is-agendado', vencido: vencido };
        return { label: 'Sin agendar', cls: 'is-sin-agendar', vencido: vencido };
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
            link.addEventListener('click', function (ev) { ev.stopPropagation(); });
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

    function celdaEstado(r) {
        var td = document.createElement('td');
        var est = getEstado(r);
        var badge = document.createElement('span');
        badge.className = 'ctc-estado-badge ' + est.cls;
        badge.textContent = est.label;
        td.appendChild(badge);
        if (est.vencido) {
            var warn = document.createElement('div');
            warn.className = 'ctc-estado-vencida';
            warn.textContent = '⚠ Vencida';
            td.appendChild(warn);
        }
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
        check.addEventListener('click', function (ev) { ev.stopPropagation(); });
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
        tr.appendChild(celdaEstado(r));

        // Clic en cualquier parte de la fila (menos el checkbox y el link
        // de mapa, que ya frenan la propagación) abre el historial de
        // cotizaciones de ese contacto.
        tr.addEventListener('click', function () { abrirGestionContacto(r); });

        return tr;
    }

    // ---------------------------------------------------------------
    // Modal "Gestión de Contacto": historial de cotizaciones (todos los
    // ciclos de proforma del agendamiento) + monto facturado por ciclo
    // (suma de insert_pago_factura por id_proforma, mismo patrón que
    // agruparPagosPorAgendamiento en estado-flujo.js).
    // ---------------------------------------------------------------
    function fmtMonedaModal(v) {
        var n = parseFloat(v) || 0;
        return '$' + n.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Selector de periodo del historial: mes actual por defecto, con opción
    // de elegir otro mes (con su año, para no confundir "julio" de un año
    // con el de otro) o "Todos los meses" — pedido explícito del usuario.
    var NOMBRES_MES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    function claveMes(fechaStr) {
        if (!fechaStr) return null;
        var partes = String(fechaStr).split(' ')[0].split('-'); // "YYYY-MM-DD..."
        if (partes.length < 2) return null;
        return partes[0] + '-' + partes[1]; // "YYYY-MM"
    }

    function claveMesActual() {
        var hoy = new Date();
        return hoy.getFullYear() + '-' + String(hoy.getMonth() + 1).padStart(2, '0');
    }

    function etiquetaMes(clave) {
        var partes = clave.split('-');
        var nombre = NOMBRES_MES[parseInt(partes[1], 10) - 1];
        return nombre.charAt(0).toUpperCase() + nombre.slice(1) + ' ' + partes[0];
    }

    function claveDelGrupo(g) {
        var fechaBase = g.montoCiclo || g.facturaCiclos[0];
        return claveMes(fechaBase.proforma_fecha_registro || fechaBase.fecha_proforma);
    }

    // Arma las opciones del selector a partir de los meses que realmente
    // tienen datos + el mes actual (siempre presente, aunque esté vacío,
    // para que el default pedido tenga dónde caer), más reciente primero.
    function poblarSelectorMes(grupos) {
        var select = document.getElementById('ctcGestionMes');
        var actual = claveMesActual();

        var claves = {};
        claves[actual] = true;
        grupos.forEach(function (g) {
            var clave = claveDelGrupo(g);
            if (clave) claves[clave] = true;
        });

        select.innerHTML = '<option value="todos">Todos los meses</option>';
        Object.keys(claves).sort().reverse().forEach(function (clave) {
            var opt = document.createElement('option');
            opt.value = clave;
            opt.textContent = etiquetaMes(clave);
            select.appendChild(opt);
        });
        select.value = actual;
    }

    function filtrarGruposPorMes(grupos, valor) {
        if (valor === 'todos') return grupos;
        return grupos.filter(function (g) { return claveDelGrupo(g) === valor; });
    }

    // Cada "ronda" real de negociación queda partida en 2+ filas de
    // insert_proforma: una o varias con monto_validado (en_proceso, el
    // promotor puede corregir el monto antes de cerrar) y luego una sin
    // monto (realizado, con foto_factura) donde queda enganchado el pago —
    // confirmado contra BD 2026-07-09. Se agrupan en una sola fila por
    // ronda: el monto vigente es el último con monto_validado ANTES de que
    // aparezca la primera fila de factura de esa ronda; todas las filas de
    // factura que sigan (pueden ser varias cuotas) se suman como facturado
    // de esa misma ronda. Igual criterio que "ciclosConMonto"/"ultimaFactura"
    // en estado-flujo.js, aplicado aquí a TODO el historial en vez de solo
    // la ronda vigente.
    function agruparCiclosCotizacion(ciclosOrdenAsc) {
        var grupos = [];
        var actual = null;
        ciclosOrdenAsc.forEach(function (c) {
            var tieneMonto = c.monto_validado !== null && c.monto_validado !== '' && c.monto_validado !== undefined;
            if (tieneMonto) {
                if (actual && !actual.facturaCiclos.length) {
                    actual.montoCiclo = c; // corrección de monto antes de facturar: reemplaza, no suma fila nueva
                } else {
                    actual = { montoCiclo: c, facturaCiclos: [] };
                    grupos.push(actual);
                }
            } else {
                if (!actual) {
                    actual = { montoCiclo: null, facturaCiclos: [] };
                    grupos.push(actual);
                }
                actual.facturaCiclos.push(c);
            }
        });
        return grupos;
    }

    function pintarHistorialCotizaciones(grupos, facturadoPorProforma) {
        var tbody = document.getElementById('ctcGestionTbody');
        tbody.innerHTML = '';

        if (!grupos.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="ctc-vacio">Sin cotizaciones en este periodo.</td></tr>';
            document.getElementById('ctcGestionSubtotalCotizado').textContent = fmtMonedaModal(0);
            document.getElementById('ctcGestionSubtotalFacturado').textContent = fmtMonedaModal(0);
            return;
        }

        var totalCotizado = 0;
        var totalFacturado = 0;

        grupos.forEach(function (g) {
            var fechaBase = g.montoCiclo || g.facturaCiclos[0];
            var fecha = formatFechaHora(fechaBase.proforma_fecha_registro || fechaBase.fecha_proforma);
            var fechaTexto = typeof fecha === 'string' ? fecha : fecha.fecha;
            var cotizado = g.montoCiclo ? (parseFloat(g.montoCiclo.monto_validado) || 0) : 0;
            var facturado = g.facturaCiclos.reduce(function (suma, fc) {
                return suma + (facturadoPorProforma[String(fc.id)] || 0);
            }, 0);
            totalCotizado += cotizado;
            totalFacturado += facturado;

            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + fechaTexto + '</td>'
                + '<td>' + fmtMonedaModal(cotizado) + '</td>'
                + '<td>' + fmtMonedaModal(facturado) + '</td>';
            tbody.appendChild(tr);
        });

        document.getElementById('ctcGestionSubtotalCotizado').textContent = fmtMonedaModal(totalCotizado);
        document.getElementById('ctcGestionSubtotalFacturado').textContent = fmtMonedaModal(totalFacturado);
    }

    function abrirGestionContacto(r) {
        contactoGestionActual = r;
        document.getElementById('ctcGestionSub').textContent =
            'Empresa: ' + (r.empresa || '—') + ' | Contacto: ' + (r.contacto || '—');
        document.getElementById('ctcGestionTbody').innerHTML = '<tr><td colspan="3" class="ctc-vacio">Cargando...</td></tr>';
        document.getElementById('ctcGestionSubtotalCotizado').textContent = '—';
        document.getElementById('ctcGestionSubtotalFacturado').textContent = '—';
        document.getElementById('ctcGestionOverlay').classList.add('is-abierto');

        Promise.all([
            fetch(GETTERS_BASE + 'proformas_listar.php?id_agendamiento=' + encodeURIComponent(r.id)).then(function (resp) { return resp.json(); }),
            fetch(GETTERS_BASE + 'get_pagos_factura.php').then(function (resp) { return resp.json(); })
        ]).then(function (resultados) {
            // Filas del LEFT JOIN sin proforma (p.id null) se descartan: no
            // son un ciclo de cotización, son solo el agendamiento base.
            // Se trae TODO el historial (no se filtra acá) — agrupar por
            // ronda necesita ver la secuencia completa para emparejar bien
            // monto↔factura aunque caigan en meses distintos; el filtro de
            // periodo se aplica después, solo sobre qué se muestra.
            var ciclos = (resultados[0].data || []).filter(function (c) { return !!c.id; });
            var pagos = resultados[1].data || [];

            var facturadoPorProforma = {};
            pagos.forEach(function (p) {
                var pid = String(p.id_proforma);
                facturadoPorProforma[pid] = (facturadoPorProforma[pid] || 0) + (parseFloat(p.monto_pago) || 0);
            });

            // Orden ascendente por id para agrupar rondas correctamente
            // (agruparCiclosCotizacion necesita ver primero el monto y
            // después su factura); se invierte al final para mostrar la
            // ronda más reciente primero, igual que el mockup pedido.
            ciclos.sort(function (a, b) { return (parseInt(a.id, 10) || 0) - (parseInt(b.id, 10) || 0); });
            var grupos = agruparCiclosCotizacion(ciclos).reverse();

            gestionGruposCache = grupos;
            gestionFacturadoCache = facturadoPorProforma;
            poblarSelectorMes(grupos);

            var mesSeleccionado = document.getElementById('ctcGestionMes').value;
            pintarHistorialCotizaciones(filtrarGruposPorMes(grupos, mesSeleccionado), facturadoPorProforma);
        });
    }

    function cerrarGestionContacto() {
        document.getElementById('ctcGestionOverlay').classList.remove('is-abierto');
    }

    // Abre la misma card "Visita Técnica" del módulo de Agendamiento
    // (agenda-crear.js), prellenada con los datos de este contacto.
    // Promotor, PDV, fecha de agendamiento, hora y técnico quedan libres
    // — pedido explícito del usuario, son datos de la NUEVA visita, no del
    // contacto ya existente.
    function registrarNuevoAgendamiento() {
        if (!contactoGestionActual || !window.AgendaAbrirCrear) return;
        var r = contactoGestionActual;
        cerrarGestionContacto();
        window.AgendaAbrirCrear({
            contacto: r.contacto,
            empresa: r.empresa,
            mail: r.mail,
            direccion: r.direccion,
            telefono: r.telefono,
            telefono_convencional: r.telefono_convencional,
            latitud: r.latitud,
            longitud: r.longitud
        });
    }

    // Filtro "Periodo" de la lista principal — arranca en "Mes actual" pero
    // deja elegir CUALQUIER mes/año con contactos registrados, o "Todos"
    // (pedido explícito del usuario). Mismo mecanismo de mes+año que
    // poblarSelectorMes/claveDelGrupo del historial de cotizaciones (ver
    // más abajo), aplicado acá sobre fecha_registro de cada contacto.
    function poblarSelectorPeriodoPrincipal() {
        var select = document.getElementById('contactadosPeriodo');
        var valorPrevio = select.value;
        var actual = claveMesActual();

        var claves = {};
        claves[actual] = true;
        currentRows.forEach(function (r) {
            var clave = claveMes(r.fecha_registro);
            if (clave) claves[clave] = true;
        });

        select.innerHTML = '<option value="todos">Todos</option>';
        Object.keys(claves).sort().reverse().forEach(function (clave) {
            var opt = document.createElement('option');
            opt.value = clave;
            opt.textContent = etiquetaMes(clave);
            select.appendChild(opt);
        });

        // Al refrescar (botón "Actualizar"), respeta el mes que el
        // analista ya tenía elegido en vez de saltar siempre a "actual".
        var opciones = Array.prototype.map.call(select.options, function (o) { return o.value; });
        select.value = opciones.indexOf(valorPrevio) !== -1 ? valorPrevio : actual;
    }

    function coincidePeriodo(r) {
        var sel = document.getElementById('contactadosPeriodo');
        var valor = sel ? sel.value : 'todos';
        if (!valor || valor === 'todos') return true;
        return claveMes(r.fecha_registro) === valor;
    }

    // Solo se busca por empresa/contacto/correo/dirección — PDV se excluyó
    // a pedido explícito del usuario (el campo ya no dice "buscar PDV...").
    function filasFiltradas() {
        var q        = document.getElementById('contactadosBusqueda').value.toLowerCase().trim();
        var mercader = document.getElementById('contactadosMercaderista').value;
        var estadoFil = document.getElementById('contactadosEstado').value;

        return currentRows.filter(function (r) {
            if (!coincidePeriodo(r)) return false;
            if (mercader && r.usuario !== mercader) return false;
            if (estadoFil && getEstado(r).cls !== estadoFil) return false;
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
            td.colSpan = 9;
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
                currentRows = consolidarContactos(json.data || []);
                selectedIds = {};
                paginaActual = 1;
                construirOpcionesMercaderista();
                poblarSelectorPeriodoPrincipal();
                renderizar();
            });
    }

    // .xlsx real vía SheetJS (cargado en contactados.php).
    function exportarExcel(filas, prefijoArchivo) {
        var datos = filas.map(function (r) {
            return {
                'Empresa': r.empresa || '',
                'Estado': getEstado(r).label,
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
        hoja['!cols'] = [{ wch: 20 }, { wch: 14 }, { wch: 20 }, { wch: 26 }, { wch: 24 }, { wch: 14 }, { wch: 14 }, { wch: 14 }, { wch: 16 }, { wch: 16 }];

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
        document.getElementById('contactadosEstado').addEventListener('change', resetearSeleccionYRenderizar);
        document.getElementById('contactadosPeriodo').addEventListener('change', resetearSeleccionYRenderizar);
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

        var gestionOverlay = document.getElementById('ctcGestionOverlay');
        document.getElementById('ctcGestionClose').addEventListener('click', cerrarGestionContacto);
        document.getElementById('ctcGestionCerrar').addEventListener('click', cerrarGestionContacto);
        document.getElementById('ctcGestionBtnNuevo').addEventListener('click', registrarNuevoAgendamiento);
        document.getElementById('ctcGestionMes').addEventListener('change', function () {
            pintarHistorialCotizaciones(filtrarGruposPorMes(gestionGruposCache, this.value), gestionFacturadoCache);
        });
        gestionOverlay.addEventListener('click', function (ev) {
            if (ev.target === gestionOverlay) cerrarGestionContacto();
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && gestionOverlay.classList.contains('is-abierto')) cerrarGestionContacto();
        });
    });
})();
