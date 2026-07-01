(function () {
    'use strict';

    var app          = document.getElementById('proformaApp');
    var GETTERS_BASE = app.dataset.gettersBase;
    var MODULO_BASE  = app.dataset.moduloBase;
    var FOTO_BASE    = 'https://luckyecuadorweb.blob.core.windows.net/app/AppPintuco/Inserts/';

    var currentRows   = [];
    var filaAbiertaId = null;
    var toastTimer    = null;

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------
    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function soloFecha(v) { return v ? v.split(' ')[0] : null; }
    function hoyISO() {
        var h = new Date();
        return h.getFullYear() + '-' + String(h.getMonth()+1).padStart(2,'0') + '-' + String(h.getDate()).padStart(2,'0');
    }
    function formatFecha(v) {
        var iso = soloFecha(v);
        if (!iso || iso === '0000-00-00') return '—';
        var p = iso.split('-');
        return p.length === 3 ? p[2]+'/'+p[1]+'/'+p[0] : iso;
    }
    function formatFechaHora(v) {
        if (!v) return '—';
        var pp = v.split(' ');
        return formatFecha(pp[0]) + (pp[1] ? ' ' + pp[1].slice(0,5) : '');
    }

    // ---------------------------------------------------------------
    // Toast
    // ---------------------------------------------------------------
    function mostrarToast(msg, esError) {
        var el = document.getElementById('proformaToast');
        el.textContent = msg;
        el.classList.toggle('is-error', !!esError);
        el.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.classList.remove('is-visible'); }, 2600);
    }

    // ---------------------------------------------------------------
    // Fase lógica
    // Fases 1 y 2 vienen del agendamiento (insert_proyectos_contacto).
    // Fases 3-5 vienen de la proforma (insert_proforma).
    // ---------------------------------------------------------------
    // Fase 3: se cumple AUTOMÁTICAMENTE cuando el promotor sube la foto de proforma.
    //   No requiere acción del analista — es solo un milestone en el timeline.
    // Fase 4 (Negociación): arranca de inmediato después de que llega la foto.
    //   El analista revisa, entra monto/obs y puede:
    //   - "Solicitar nueva evidencia" → nuevo ciclo (promotor manda otra foto).
    //   - "Finalizar Negociación" → aprueba y pasa a Fase 5.
    //   - "Rechazar" → estado terminal.
    // Fase 5: completo — se muestra foto_factura del celular (solo lectura).
    function getFase(p) {
        if (p.foto_factura || p.estado_proforma === 'aprobado') return 5;
        if (p.estado_proforma === 'rechazado') return 4;          // terminal en negociación
        if (p.id) return 4;                                        // proforma recibida → fase 4 automática
        if (p.hora && p.tecnico) return 2;
        return 1;
    }

    function getBadge(p) {
        var f = getFase(p);
        if (f === 5) return { label: 'Fase 5', cls: 'is-aprobado' };
        if (f === 4) {
            if (p.estado_proforma === 'rechazado') return { label: 'Fase 4', cls: 'is-rechazado' };
            return { label: 'Fase 4', cls: 'is-en_proceso' };
        }
        if (f === 2) return { label: 'Fase 2', cls: 'is-pendiente' };
        return           { label: 'Fase 1', cls: 'is-pendiente' };
    }

    // ---------------------------------------------------------------
    // KPIs
    // ---------------------------------------------------------------
    function pintarKpis(rows) {
        var pendientes = 0, negociacion = 0, aprobadasHoy = 0, monto = 0;
        var hoy = hoyISO();
        rows.forEach(function (p) {
            var f = getFase(p);
            if (f === 3 && p.estado_proforma !== 'rechazado') pendientes++;
            if (f === 4) negociacion++;
            if (p.estado_proforma === 'aprobado' && soloFecha(p.fecha_auditoria) === hoy) aprobadasHoy++;
            if (f === 4 && p.monto_validado) monto += parseFloat(p.monto_validado) || 0;
        });
        document.getElementById('proformaKpiPendientes').textContent = pendientes;
        document.getElementById('proformaKpiNegociacion').textContent = negociacion;
        document.getElementById('proformaKpiAprobadasHoy').textContent = aprobadasHoy;
        document.getElementById('proformaKpiMonto').textContent =
            '$' + monto.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ---------------------------------------------------------------
    // Filtros
    // ---------------------------------------------------------------
    function filasFiltradas() {
        var promotorSel  = document.getElementById('proformaFiltroPromotor').value;
        var estadoSel    = document.getElementById('proformaFiltroEstado').value;
        var periodoClave = document.getElementById('proformaFiltroPeriodo').value;
        var busqueda     = document.getElementById('proformaBusqueda').value.toLowerCase().trim();

        return currentRows.filter(function (p) {
            if (promotorSel && p.usuario !== promotorSel) return false;

            if (estadoSel) {
                var f = getFase(p);
                if (estadoSel === 'en_proceso'     && !(f === 3 && p.estado_proforma !== 'rechazado')) return false;
                if (estadoSel === 'en_negociacion' && f !== 4)                                         return false;
                if (estadoSel === 'aprobado'       && p.estado_proforma !== 'aprobado')                return false;
                if (estadoSel === 'rechazado'      && p.estado_proforma !== 'rechazado')               return false;
            }

            if (periodoClave) {
                var hoy = new Date(), desde;
                if (periodoClave === 'mes_actual')   desde = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                if (periodoClave === 'mes_anterior')  desde = new Date(hoy.getFullYear(), hoy.getMonth()-1, 1);
                if (periodoClave === 'ultimos_3')     desde = new Date(hoy.getFullYear(), hoy.getMonth()-3, 1);
                if (desde) {
                    var ref = p.proforma_fecha_registro || p.contacto_fecha_registro;
                    if (!ref || new Date(ref.split(' ')[0]+'T00:00:00') < desde) return false;
                }
            }

            if (busqueda) {
                var haystack = [p.pdv, p.codigo_pdv, p.empresa, p.contacto].join(' ').toLowerCase();
                if (haystack.indexOf(busqueda) === -1) return false;
            }

            return true;
        });
    }

    // ---------------------------------------------------------------
    // Opciones de promotor
    // ---------------------------------------------------------------
    function construirOpcionesPromotor() {
        var select = document.getElementById('proformaFiltroPromotor');
        var prev   = select.value;
        select.innerHTML = '<option value="">Todos los promotores</option>';
        var vistos = {};
        currentRows.forEach(function (p) {
            if (p.usuario && !vistos[p.usuario]) {
                vistos[p.usuario] = true;
                var o = document.createElement('option');
                o.value = o.textContent = p.usuario;
                select.appendChild(o);
            }
        });
        if (vistos[prev]) select.value = prev;
    }

    // ---------------------------------------------------------------
    // Deduplicación: el getter devuelve TODOS los ciclos de insert_proforma.
    // Para la lista principal solo queremos el ciclo más reciente por
    // id_agendamiento (el de mayor id). Los ciclos anteriores se cargan
    // por separado cuando el analista expande una fila (historial gris).
    // ---------------------------------------------------------------
    function ultimosCiclos(rows) {
        // Clave = agendamiento_id (c.id, siempre presente aunque no haya proforma).
        // Si hay múltiples ciclos de negociación para un mismo agendamiento,
        // se queda con el de mayor p.id (el ciclo más reciente).
        // Si no hay proforma aún (p.id = null), parseInt da NaN → 0, y esa
        // única fila es el máximo para ese agendamiento.
        var maximo = {};
        rows.forEach(function (p) {
            var key = p.agendamiento_id;
            var pid = parseInt(p.id, 10) || 0;
            if (maximo[key] === undefined || pid > maximo[key]) {
                maximo[key] = pid;
            }
        });
        return rows.filter(function (p) {
            var pid = parseInt(p.id, 10) || 0;
            return pid === maximo[p.agendamiento_id];
        });
    }

    // ---------------------------------------------------------------
    // Agrupación por promotor
    // ---------------------------------------------------------------
    function agruparPorPromotor(rows) {
        var grupos = {};
        rows.forEach(function (p) {
            var k = p.usuario || '(Sin promotor)';
            if (!grupos[k]) grupos[k] = [];
            grupos[k].push(p);
        });
        return grupos;
    }

    // ---------------------------------------------------------------
    // Render principal
    // ---------------------------------------------------------------
    function renderizar() {
        pintarKpis(currentRows);
        var container = document.getElementById('proformaGrupos');
        container.innerHTML = '';

        var filas = filasFiltradas();
        if (!filas.length) {
            var vacio = document.createElement('div');
            vacio.className = 'proforma-vacio';
            vacio.textContent = 'Sin proformas que coincidan con el filtro.';
            container.appendChild(vacio);
            return;
        }

        var grupos = agruparPorPromotor(ultimosCiclos(filas));
        Object.keys(grupos).sort().forEach(function (promotor) {
            container.appendChild(construirGrupo(promotor, grupos[promotor]));
        });
    }

    // ---------------------------------------------------------------
    // Grupo de promotor
    // ---------------------------------------------------------------
    function construirGrupo(promotor, filas) {
        var wrap = document.createElement('div');
        wrap.className = 'proforma-grupo';

        // Header de grupo
        var hdr = document.createElement('div');
        hdr.className = 'proforma-grupo-header';

        var avatar = document.createElement('div');
        avatar.className = 'proforma-grupo-avatar';
        avatar.innerHTML = '<i class="glyphicon glyphicon-user"></i>';

        var info = document.createElement('div');
        info.className = 'proforma-grupo-info';

        var nombre = document.createElement('span');
        nombre.className = 'proforma-grupo-nombre';
        nombre.textContent = 'Promotor: ' + promotor;

        var count = document.createElement('span');
        count.className = 'proforma-grupo-count';
        count.textContent = filas.length + ' agendamiento' + (filas.length !== 1 ? 's' : '') + ' en ruta';

        info.appendChild(nombre);
        info.appendChild(count);

        var chevron = document.createElement('i');
        chevron.className = 'glyphicon glyphicon-chevron-up proforma-grupo-chevron';

        hdr.appendChild(avatar);
        hdr.appendChild(info);
        hdr.appendChild(chevron);

        hdr.addEventListener('click', function () {
            wrap.classList.toggle('is-cerrado');
        });

        wrap.appendChild(hdr);

        // Sub-header de columnas
        var sub = document.createElement('div');
        sub.className = 'proforma-grupo-subheader';
        sub.innerHTML =
            '<span class="proforma-sh-cliente">Cliente / Punto de venta</span>'
            + '<span class="proforma-sh-promotor">Promotor</span>'
            + '<span class="proforma-sh-fecha">Fecha visita</span>'
            + '<span class="proforma-sh-estado">Estado</span>';
        wrap.appendChild(sub);

        // Filas del grupo
        var body = document.createElement('div');
        body.className = 'proforma-grupo-body';
        filas.forEach(function (p) {
            body.appendChild(construirFilaWrap(p));
        });
        wrap.appendChild(body);

        return wrap;
    }

    // ---------------------------------------------------------------
    // Fila de agendamiento + panel de detalle
    // ---------------------------------------------------------------
    function construirFilaWrap(p) {
        var wrap = document.createElement('div');
        wrap.className = 'proforma-gfila-wrap';

        // Fila principal
        var fila = document.createElement('div');
        fila.className = 'proforma-gfila';
        fila.dataset.id = p.agendamiento_id;  // siempre presente (c.id)
        if (filaAbiertaId !== null && filaAbiertaId === p.agendamiento_id) fila.classList.add('is-abierta');

        var badge = getBadge(p);

        var cCliente = document.createElement('div');
        cCliente.className = 'proforma-gfila-cliente';
        var empresa = document.createElement('div');
        empresa.className = 'proforma-gfila-empresa';
        empresa.textContent = p.empresa || p.contacto || '—';
        var pdvEl = document.createElement('div');
        pdvEl.className = 'proforma-gfila-pdv';
        pdvEl.textContent = (p.pdv || '—') + (p.codigo_pdv ? ' · ' + p.codigo_pdv : '');
        cCliente.appendChild(empresa);
        cCliente.appendChild(pdvEl);

        var cProm = document.createElement('div');
        cProm.className = 'proforma-gfila-promotor';
        cProm.textContent = p.usuario || '—';

        var cFecha = document.createElement('div');
        cFecha.className = 'proforma-gfila-fecha';
        cFecha.textContent = formatFecha(p.fecha_agendamiento);

        var cEstado = document.createElement('div');
        cEstado.className = 'proforma-gfila-estado';
        var badgeEl = document.createElement('span');
        badgeEl.className = 'proforma-badge ' + badge.cls;
        badgeEl.textContent = badge.label;
        cEstado.appendChild(badgeEl);

        fila.appendChild(cCliente);
        fila.appendChild(cProm);
        fila.appendChild(cFecha);
        fila.appendChild(cEstado);

        fila.addEventListener('click', function () { alternarFila(p.agendamiento_id); });

        wrap.appendChild(fila);

        // Panel de detalle si está abierta
        if (filaAbiertaId !== null && filaAbiertaId === p.agendamiento_id) {
            wrap.appendChild(construirDetalle(p));
        }

        return wrap;
    }

    function alternarFila(agendamientoId) {
        filaAbiertaId = (filaAbiertaId === agendamientoId) ? null : agendamientoId;
        renderizar();
        if (filaAbiertaId !== null) {
            setTimeout(function () {
                var el = document.querySelector('.proforma-gfila[data-id="' + agendamientoId + '"]');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 60);
        }
    }

    // ---------------------------------------------------------------
    // Panel de detalle (3 columnas)
    // ---------------------------------------------------------------
    function construirDetalle(p) {
        var panel = document.createElement('div');
        panel.className = 'proforma-gdetalle';

        var grid = document.createElement('div');
        grid.className = 'proforma-gdetalle-grid';

        grid.appendChild(construirTimeline(p));
        grid.appendChild(construirPanelEvidencia(p));
        grid.appendChild(construirPanelAuditoria(p, function () { cargarProformas(); }));

        panel.appendChild(grid);
        return panel;
    }

    // ---------------------------------------------------------------
    // Columna 1 — Timeline de 5 fases
    // ---------------------------------------------------------------
    function construirTimeline(p) {
        var fase = getFase(p);

        var wrap = document.createElement('div');

        var titulo = document.createElement('div');
        titulo.className = 'proforma-seccion-titulo';
        titulo.textContent = 'Progreso del flujo';
        wrap.appendChild(titulo);

        var defs = [
            { num: 1, label: 'Contacto inicial',
              fecha: formatFecha(p.contacto_fecha_registro),
              completa: true, activa: fase === 1 },
            { num: 2, label: 'Visita agendada',
              fecha: formatFecha(p.fecha_agendamiento) + (p.hora ? ' · ' + p.hora.slice(0,5) : ''),
              completa: !!(p.hora && p.tecnico), activa: fase === 2 },
            { num: 3, label: 'Proforma recibida',
              fecha: p.proforma_fecha_registro ? formatFechaHora(p.proforma_fecha_registro) : null,
              completa: !!p.id,   // auto-completa cuando llega la foto del promotor
              activa: false },    // nunca "activa": es solo un milestone
            { num: 4, label: 'Negociación',
              fecha: p.fecha_auditoria ? formatFechaHora(p.fecha_auditoria) : null,
              completa: fase === 5, activa: fase === 4 },
            { num: 5, label: 'Completado',
              fecha: null, completa: fase === 5, activa: false }
        ];

        var list = document.createElement('div');
        list.className = 'proforma-fase-list';

        defs.forEach(function (f) {
            var item = document.createElement('div');
            item.className = 'proforma-fase-item '
                + (f.completa ? 'is-completa' : f.activa ? 'is-activa' : 'is-pendiente');

            var punto = document.createElement('div');
            punto.className = 'proforma-fase-punto';
            if (f.completa) punto.innerHTML = '<i class="glyphicon glyphicon-ok"></i>';
            item.appendChild(punto);

            var texto = document.createElement('div');
            texto.className = 'proforma-fase-texto';

            var lbl = document.createElement('div');
            lbl.className = 'proforma-fase-label';
            lbl.textContent = 'Fase ' + f.num + ': ' + f.label;
            texto.appendChild(lbl);

            if (f.fecha && f.fecha !== '—') {
                var fEl = document.createElement('div');
                fEl.className = 'proforma-fase-fecha';
                fEl.textContent = f.fecha;
                texto.appendChild(fEl);
            }

            item.appendChild(texto);
            list.appendChild(item);
        });

        wrap.appendChild(list);
        return wrap;
    }

    // ---------------------------------------------------------------
    // Columna 2 — Evidencia fotográfica
    // ---------------------------------------------------------------
    function construirPanelEvidencia(p) {
        var wrap = document.createElement('div');

        var titulo = document.createElement('div');
        titulo.className = 'proforma-seccion-titulo';
        titulo.textContent = 'Evidencia fotográfica';
        wrap.appendChild(titulo);

        if (p.evidencia) {
            var img = document.createElement('img');
            img.className = 'proforma-evidencia-foto';
            img.src = FOTO_BASE + p.evidencia;
            img.alt = 'Evidencia de la proforma';
            img.addEventListener('click', function () { window.open(img.src, '_blank'); });
            img.addEventListener('error', function () {
                img.style.display = 'none';
                var av = document.createElement('div');
                av.className = 'proforma-evidencia-vacia';
                av.innerHTML = '<i class="glyphicon glyphicon-picture"></i><br>Foto no disponible.<br><small style="word-break:break-all;opacity:.7">' + esc(img.src) + '</small>';
                img.parentNode.insertBefore(av, img.nextSibling);
            });
            wrap.appendChild(img);
        } else {
            var sin = document.createElement('div');
            sin.className = 'proforma-evidencia-vacia';
            sin.innerHTML = '<i class="glyphicon glyphicon-camera"></i><br>Sin foto de evidencia.';
            wrap.appendChild(sin);
        }

        var lat = parseFloat(p.latitud), lng = parseFloat(p.longitud);
        if (lat && lng) {
            var link = document.createElement('a');
            link.className = 'proforma-evidencia-ubicacion';
            link.href = 'https://maps.google.com/maps?q=' + lat + ',' + lng;
            link.target = '_blank';
            link.rel = 'noopener';
            link.innerHTML = '<i class="glyphicon glyphicon-map-marker"></i> Ver ubicación GPS';
            wrap.appendChild(link);
        }

        if (p.caracteristica_visita || p.acompanamiento_tecnico) {
            var rep = document.createElement('div');
            rep.className = 'proforma-reporte';
            if (p.caracteristica_visita) {
                var c = document.createElement('p');
                c.textContent = p.caracteristica_visita;
                rep.appendChild(c);
            }
            var ac = document.createElement('p');
            ac.className = 'proforma-reporte-meta';
            ac.textContent = 'Acompañamiento técnico: ' + (p.acompanamiento_tecnico === 'SI' ? 'Sí' : 'No');
            rep.appendChild(ac);
            wrap.appendChild(rep);
        }

        return wrap;
    }

    // ---------------------------------------------------------------
    // Columna 3 — Auditoría (con historial de ciclos en fase 4)
    // ---------------------------------------------------------------
    function construirPanelAuditoria(p, onResuelto) {
        var fase = getFase(p);
        var wrap = document.createElement('div');

        var titulo = document.createElement('div');
        titulo.className = 'proforma-seccion-titulo';
        titulo.textContent = 'Acciones de auditoría';
        wrap.appendChild(titulo);

        // ── Fase 5: completado ──────────────────────────────────────────────
        if (fase === 5) {
            if (p.foto_factura) {
                var tituloFact = document.createElement('div');
                tituloFact.className = 'proforma-seccion-titulo';
                tituloFact.textContent = 'Foto de factura';
                wrap.appendChild(tituloFact);

                var imgFact = document.createElement('img');
                imgFact.className = 'proforma-evidencia-foto';
                imgFact.src = FOTO_BASE + p.foto_factura;
                imgFact.alt = 'Foto de factura';
                imgFact.addEventListener('click', function () { window.open(imgFact.src, '_blank'); });
                imgFact.addEventListener('error', function () {
                    imgFact.style.display = 'none';
                    var av = document.createElement('div');
                    av.className = 'proforma-evidencia-vacia';
                    av.innerHTML = '<i class="glyphicon glyphicon-picture"></i><br>Foto de factura no disponible.';
                    imgFact.parentNode.insertBefore(av, imgFact.nextSibling);
                });
                wrap.appendChild(imgFact);
            } else {
                var espFact = document.createElement('div');
                espFact.className = 'proforma-auditoria-cerrada';
                espFact.innerHTML = '<i class="glyphicon glyphicon-ok-circle"></i> Negociación finalizada — esperando foto de factura del promotor.';
                wrap.appendChild(espFact);
            }
            return wrap;
        }

        // ── Fase 1-2: sin proforma aún ─────────────────────────────────────
        if (fase <= 2) {
            var esp = document.createElement('div');
            esp.className = 'proforma-auditoria-cerrada';
            esp.textContent = 'Pendiente de que el promotor suba la proforma.';
            wrap.appendChild(esp);
            return wrap;
        }

        // ── Rechazado (terminal en fase 3) ─────────────────────────────────
        if (p.estado_proforma === 'rechazado') {
            var recMsg = document.createElement('div');
            recMsg.className = 'proforma-auditoria-cerrada is-rechazado';
            recMsg.innerHTML = '<i class="glyphicon glyphicon-remove"></i> Rechazada / Evidencia falsa.';
            wrap.appendChild(recMsg);
            return wrap;
        }

        // ── Fase 4: historial de ciclos anteriores ─────────────────────────
        // proformas_listar.php?id_agendamiento=X devuelve TODOS los ciclos del
        // agendamiento (del más antiguo al más reciente). Los anteriores al
        // ciclo activo (id < p.id) se muestran como tarjetas grises.
        var historialWrap = document.createElement('div');
        historialWrap.className = 'proforma-historial';
        wrap.appendChild(historialWrap);

        if (fase === 4) {
            var CICLO_LABEL = { en_negociacion: 'Aprobado → negociación', aprobado: 'Finalizado', rechazado: 'Rechazado', en_proceso: 'En revisión' };
            fetch(GETTERS_BASE + 'proformas_listar.php?id_agendamiento=' + encodeURIComponent(p.agendamiento_id))
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    var anteriores = (json.data || []).filter(function (h) {
                        return parseInt(h.id, 10) !== parseInt(p.id, 10);
                    });
                    if (!anteriores.length) return;
                    anteriores.forEach(function (h, idx) {
                        var lbl = CICLO_LABEL[h.estado_proforma] || h.estado_proforma;
                        var ciclo = document.createElement('div');
                        ciclo.className = 'proforma-historial-ciclo';
                        ciclo.innerHTML =
                            '<div class="proforma-historial-ciclo-hdr">'
                            + '<span class="proforma-historial-ciclo-num">Ciclo ' + (idx + 1) + '</span>'
                            + '<span class="proforma-historial-ciclo-fecha">' + esc(formatFechaHora(h.fecha_auditoria || h.proforma_fecha_registro)) + '</span>'
                            + '<span class="proforma-badge is-' + esc(h.estado_proforma) + '">' + esc(lbl) + '</span>'
                            + '</div>'
                            + (h.monto_validado
                                ? '<div class="proforma-historial-ciclo-dato">Monto: $' + parseFloat(h.monto_validado).toLocaleString('es-EC', {minimumFractionDigits:2}) + '</div>'
                                : '')
                            + (h.observaciones_auditoria
                                ? '<div class="proforma-historial-ciclo-dato">' + esc(h.observaciones_auditoria) + '</div>'
                                : '');
                        historialWrap.appendChild(ciclo);
                    });
                })
                .catch(function () {});
        }

        // ── Formulario de auditoría (fase 3 y 4 activa) ────────────────────
        var campoMonto = document.createElement('div');
        campoMonto.className = 'proforma-campo';
        var labelMonto = document.createElement('label');
        labelMonto.textContent = 'Monto cotizado ($)';
        var inputMonto = document.createElement('input');
        inputMonto.type = 'number'; inputMonto.step = '0.01'; inputMonto.min = '0';
        inputMonto.placeholder = 'Ej. 1200.00';
        inputMonto.className = 'form-control';
        inputMonto.value = p.monto_validado || '';
        campoMonto.appendChild(labelMonto); campoMonto.appendChild(inputMonto);
        wrap.appendChild(campoMonto);

        var campoObs = document.createElement('div');
        campoObs.className = 'proforma-campo';
        var labelObs = document.createElement('label');
        labelObs.textContent = 'Observaciones internas de validación';
        var inputObs = document.createElement('textarea');
        inputObs.className = 'form-control'; inputObs.placeholder = 'Notas...';
        inputObs.rows = 3; inputObs.value = p.observaciones_auditoria || '';
        campoObs.appendChild(labelObs); campoObs.appendChild(inputObs);
        wrap.appendChild(campoObs);

        var acciones = document.createElement('div');
        acciones.className = 'proforma-auditoria-acciones';

        var btnVerde = document.createElement('button');
        var btnRec   = document.createElement('button');

        function setBusy(b) { btnVerde.disabled = b; btnRec.disabled = b; }

        function accionar(accion) {
            setBusy(true);
            var body = new URLSearchParams();
            body.set('id', p.id);
            body.set('accion', accion);
            body.set('monto', inputMonto.value.trim());
            body.set('observaciones', inputObs.value.trim());
            fetch(GETTERS_BASE + 'update_proforma.php', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (json.success) { mostrarToast('Guardado correctamente.'); onResuelto(); }
                    else { mostrarToast(json.message || 'No se pudo actualizar.', true); setBusy(false); }
                })
                .catch(function () { mostrarToast('Error de conexión.', true); setBusy(false); });
        }

        // Fase 4 — 2 botones:
        //   "Guardar"                  → registra monto/obs sin cambiar estado.
        //   "Rechazar / Subir nuevamente" → rojo: pide nueva evidencia al promotor.
        // La transición a fase 5 la hace el promotor subiendo foto_factura desde el celular.
        btnVerde.type = 'button';
        btnVerde.className = 'proforma-btn-aprobar';
        btnVerde.textContent = 'Guardar';
        btnVerde.addEventListener('click', function () { accionar('guardar'); });

        btnRec.type = 'button';
        btnRec.className = 'proforma-btn-rechazar-rojo';
        btnRec.textContent = 'Rechazar / Subir nuevamente';
        btnRec.addEventListener('click', function () { accionar('negociacion'); });

        acciones.appendChild(btnVerde);
        acciones.appendChild(btnRec);
        wrap.appendChild(acciones);

        return wrap;
    }

    // ---------------------------------------------------------------
    // Sello de fecha
    // ---------------------------------------------------------------
    function actualizarSelloFecha() {
        var h = new Date(), hh = h.getHours(), h12 = hh % 12 || 12;
        document.getElementById('proformaActualizado').textContent =
            'Hoy, ' + String(h12).padStart(2,'0') + ':' + String(h.getMinutes()).padStart(2,'0') + ' ' + (hh >= 12 ? 'PM' : 'AM');
    }

    // ---------------------------------------------------------------
    // Carga
    // ---------------------------------------------------------------
    function cargarProformas() {
        return fetch(GETTERS_BASE + 'proformas_listar.php')
            .then(function (r) { return r.json(); })
            .then(function (json) {
                currentRows = json.data || [];
                construirOpcionesPromotor();
                renderizar();
                actualizarSelloFecha();
            })
            .catch(function (err) {
                document.getElementById('proformaGrupos').innerHTML =
                    '<div class="proforma-vacio">No se pudo cargar. Verifica que proformas_listar.php esté desplegado.</div>';
                mostrarToast('Error: ' + err.message, true);
            });
    }

    // ---------------------------------------------------------------
    // Excel
    // ---------------------------------------------------------------
    function descargarExcel() {
        if (typeof XLSX === 'undefined') { mostrarToast('Falta librería XLSX.', true); return; }
        var datos = filasFiltradas().map(function (p) {
            return {
                'PDV':            p.pdv || '',
                'Código':         p.codigo_pdv || '',
                'Cliente':        p.empresa || p.contacto || '',
                'Promotor':       p.usuario || '',
                'Fecha visita':   formatFecha(p.fecha_agendamiento),
                'Proforma subida':formatFechaHora(p.proforma_fecha_registro),
                'Estado':         getBadge(p).label,
                'Monto validado': p.monto_validado || ''
            };
        });
        var hoja = XLSX.utils.json_to_sheet(datos);
        var libro = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(libro, hoja, 'Proformas');
        XLSX.writeFile(libro, 'proformas_' + hoyISO() + '.xlsx');
    }

    window.ProformaRecargar = cargarProformas;

    document.addEventListener('DOMContentLoaded', function () {
        cargarProformas();
        ['proformaBusqueda'].forEach(function (id) {
            document.getElementById(id).addEventListener('input', renderizar);
        });
        ['proformaFiltroPromotor','proformaFiltroEstado','proformaFiltroPeriodo'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', renderizar);
        });
        document.getElementById('proformaActualizar').addEventListener('click', cargarProformas);
        document.getElementById('proformaExportar').addEventListener('click', descargarExcel);
    });
})();
