(function () {
    'use strict';

    var app          = document.getElementById('estadoFlujoApp');
    var GETTERS_BASE = app.dataset.gettersBase;

    var allRows  = [];  // todos los ciclos, de todos los agendamientos
    var pipeline = [];  // 1 fila por agendamiento = su último ciclo
    var detalleAbierto = null;  // fila de la mini card actualmente abierta (para "Ver más")
    var pagosPorProforma = {}; // { id_proforma: suma de monto_pago } — para "Facturado" a plazos

    // Facturado real: Pago Directo lee monto_total_factura, a plazos suma insert_pago_factura (mismo contrato que factura.js).
    function montoFacturadoDe(p) {
        if ((parseInt(p.plazo_meses, 10) || 0) <= 0) return parseFloat(p.monto_total_factura) || 0;
        return pagosPorProforma[p.id] || 0;
    }

    // ── Helpers ──────────────────────────────────────────────────────
    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    function soloFecha(v) { return v ? String(v).split(' ')[0] : null; }
    function diasDesde(fechaStr) {
        var f = soloFecha(fechaStr);
        if (!f || f === '0000-00-00') return null;
        return Math.floor((new Date() - new Date(f + 'T00:00:00')) / 86400000);
    }
    function fmtDias(d) { return d === null ? '—' : (d < 0 ? '0d' : d + 'd'); }
    // Umbral: neutro <=3 días, ámbar 4-7, rojo 8+. Nunca verde: recién llegado no es "bien".
    function clsDias(d) {
        if (d === null) return '';
        if (d <= 3) return 'is-neutro';
        if (d <= 7) return 'is-ambar';
        return 'is-rojo';
    }

    // ── Lógica de fases (igual que proforma.js) ───────────────────────
    function getFase(p) {
        if (p.foto_factura || p.estado_proforma === 'aprobado') return 5;
        if (p.estado_proforma === 'rechazado') return 4;
        if (p.id) return 4;
        // Sin visita requerida, el siguiente paso es subir la foto directo: cae en fase 3.
        if (p.no_requiere_visita === 'SI') return 3;
        if (p.hora && p.tecnico) return 2;
        return 1;
    }

    function ultimosCiclos(rows) {
        var maximo = {};
        rows.forEach(function (p) {
            var key = p.agendamiento_id;
            var pid = parseInt(p.id, 10) || 0;
            if (maximo[key] === undefined || pid > maximo[key]) maximo[key] = pid;
        });
        return rows.filter(function (p) {
            return (parseInt(p.id, 10) || 0) === maximo[p.agendamiento_id];
        });
    }

    // Fecha de referencia para calcular días en la fase actual.
    function refFechaFase(p) {
        var f = getFase(p);
        if (f >= 4) return p.proforma_fecha_registro || p.contacto_fecha_registro;
        return p.contacto_fecha_registro;
    }

    // ── Metadatos de fase ──────────────────────────────────────────────
    var FASES_META = [
        { fase: 1, label: 'Contacto' },
        { fase: 2, label: 'Agendado' },
        { fase: 3, label: 'Proforma' },
        { fase: 4, label: 'Negociación' },
        { fase: 5, label: 'Facturado' },
    ];

    // ── Kanban "Por fase" — 5 columnas simultáneas ────────────────────
    function construirEsqueletoKanban() {
        var cont = document.getElementById('efKanban');
        cont.innerHTML = FASES_META.map(function (m) {
            return '<div class="ef-col">'
                + '<div class="ef-col-header">'
                +   '<span class="ef-col-dot"></span>'
                +   '<span class="ef-col-fase">Fase ' + m.fase + '</span>'
                +   '<span class="ef-col-count" id="efColCount-' + m.fase + '">0</span>'
                + '</div>'
                + '<div class="ef-col-label">' + esc(m.label) + '</div>'
                + '<div class="ef-col-body" id="efColBody-' + m.fase + '"></div>'
                + '</div>';
        }).join('');
    }

    function renderTarjetaKanban(p) {
        var dias = diasDesde(refFechaFase(p));
        // La tarjeta chica del kanban también marca un plan de pago cerrado.
        var planCerrado = p.estado_pago === 'cerrado';
        return '<div class="ef-card' + (planCerrado ? ' is-plan-cerrado' : '') + '" data-agendamiento-id="' + esc(p.agendamiento_id) + '">'
            + '<div class="ef-card-empresa">' + esc(p.empresa || '—') + '</div>'
            + '<div class="ef-card-pdv">' + esc(p.pdv || p.codigo_pdv || '') + '</div>'
            + (p.no_requiere_visita === 'SI' && !(p.hora && p.tecnico)
                ? '<div class="ef-card-nota">No requirió visita</div>' : '')
            + (planCerrado ? '<div class="ef-card-nota is-alerta">Plan de pago cerrado</div>' : '')
            + '<div class="ef-card-footer">'
            +   '<span class="ef-card-promotor"><i class="glyphicon glyphicon-user"></i> ' + esc(p.usuario || '(sin asignar)') + '</span>'
            +   '<span class="ef-dias-pill ' + clsDias(dias) + '">' + fmtDias(dias) + '</span>'
            + '</div>'
            + '</div>';
    }

    function renderKanban(rows) {
        var porFase = { 1: [], 2: [], 3: [], 4: [], 5: [] };
        rows.forEach(function (p) { porFase[getFase(p)].push(p); });
        FASES_META.forEach(function (m) {
            var countEl = document.getElementById('efColCount-' + m.fase);
            var bodyEl  = document.getElementById('efColBody-' + m.fase);
            if (!countEl || !bodyEl) return;
            countEl.textContent = porFase[m.fase].length;
            bodyEl.innerHTML = porFase[m.fase].length
                ? porFase[m.fase].map(renderTarjetaKanban).join('')
                : '<div class="ef-vacio">Sin registros.</div>';
        });
    }

    // ── Vista rápida (clic en una tarjeta) ─────────────────────────────
    // Solo datos que ya vienen en el fetch de proformas_listar.php; "Ver más" va al módulo dueño.
    var ESTADOS_AGENDA_LABEL = {
        pendiente: 'Pendiente técnico',
        confirmado: 'Agendado',
        reagendada: 'Reagendada',
        vencida: 'Vencida',
        cancelada: 'Cancelada',
        completada: 'Completada'
    };

    function fmtMonto(v) {
        var n = parseFloat(v) || 0;
        return '$' + n.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function formatHora(h) { return h ? String(h).slice(0, 5) : ''; }
    function formatFechaCorta(v) {
        var f = soloFecha(v);
        if (!f || f === '0000-00-00') return '—';
        var partes = f.split('-');
        return partes.length === 3 ? partes[2] + '/' + partes[1] + '/' + partes[0] : f;
    }

    function ciclosDe(agendamientoId) {
        return allRows.filter(function (r) { return String(r.agendamiento_id) === String(agendamientoId) && !!r.id; });
    }

    // Rondas de cotización: ciclos con monto_validado, orden ascendente (ver contactados.js).
    function infoRondas(agendamientoId) {
        var conMonto = ciclosDe(agendamientoId)
            .filter(function (c) { return c.monto_validado !== null && c.monto_validado !== '' && c.monto_validado !== undefined; })
            .sort(function (a, b) { return (parseInt(a.id, 10) || 0) - (parseInt(b.id, 10) || 0); });
        if (!conMonto.length) return null;
        return {
            numero: conMonto.length,
            actual: conMonto[conMonto.length - 1],
            anterior: conMonto.length > 1 ? conMonto[conMonto.length - 2] : null
        };
    }

    function badgeEstadoAgenda(p) {
        var estado = p.estado_agenda || 'pendiente';
        var label = ESTADOS_AGENDA_LABEL[estado] || estado;
        return '<span class="ef-detalle-badge is-' + esc(estado) + '">' + esc(label) + '</span>';
    }

    function badgeAuditoria(p) {
        if (p.estado_proforma === 'rechazado') return '<span class="ef-detalle-badge is-rechazado">Cerrada</span>';
        if (p.estado_proforma === 'correccion_solicitada') return '<span class="ef-detalle-badge is-correccion">⚠ Corrección solicitada</span>';
        return '<span class="ef-detalle-badge is-negociacion">En negociación</span>';
    }

    function abrirDetalle(p) {
        detalleAbierto = p;
        var f = getFase(p);
        var meta = FASES_META[f - 1];

        document.getElementById('efDetalleTitulo').textContent = p.empresa || '—';
        document.getElementById('efDetalleSub').textContent =
            (p.pdv || p.codigo_pdv || '—') + ' · Fase ' + f + ' · ' + meta.label;

        var cardEl = document.getElementById('efDetalleCard');
        cardEl.classList.remove('is-exito', 'is-cerrada');
        if (f === 5) cardEl.classList.add('is-exito');
        else if (p.estado_proforma === 'rechazado') cardEl.classList.add('is-cerrada');

        // El badge de estado_agenda es el dato principal en fase 1-2; en fases posteriores
        // solo se muestra si quedó una anomalía relevante (cancelada/vencida).
        var estadoAgendaAnomalo = p.estado_agenda === 'cancelada' || p.estado_agenda === 'vencida';
        var filas = (f <= 2 || estadoAgendaAnomalo) ? [badgeEstadoAgenda(p)] : [];

        if (f === 2) {
            filas.push('<div class="ef-detalle-linea"><span class="ef-detalle-label">Visita</span>'
                + esc(formatFechaCorta(p.fecha_agendamiento)) + (p.hora ? ' · ' + esc(formatHora(p.hora)) : '')
                + (p.tecnico ? ' · ' + esc(p.tecnico) : '') + '</div>');
        }

        if (f === 3) {
            filas.push('<div class="ef-detalle-linea">No requirió visita técnica — esperando que suba la foto de la proforma.</div>');
        }

        if (f === 4) {
            var rondas4 = infoRondas(p.agendamiento_id);
            if (rondas4) {
                var texto = (rondas4.numero > 1 ? rondas4.numero + 'ª ronda de cotización: ' : 'Cotización: ')
                    + fmtMonto(rondas4.actual.monto_validado)
                    + (rondas4.anterior ? ' (antes ' + fmtMonto(rondas4.anterior.monto_validado) + ')' : '');
                filas.push('<div class="ef-detalle-linea"><span class="ef-detalle-label">Monto</span>' + esc(texto) + '</div>');
            }
            filas.push(badgeAuditoria(p));
            if (p.estado_proforma === 'rechazado' && p.motivo_cierre) {
                filas.push('<div class="ef-detalle-motivo"><strong>Motivo:</strong> ' + esc(p.motivo_cierre) + '</div>');
            }
        }

        if (f === 5) {
            var plazoMeses = parseInt(p.plazo_meses, 10) || 0;
            var rondas5 = infoRondas(p.agendamiento_id);
            // Mismo formato "2 columnas" que el panel de Facturas (Cotización/Facturado).
            filas.push(
                '<div class="ef-detalle-monto-titulo">Monto</div>'
                + '<div class="ef-detalle-monto-box">'
                +   '<div class="ef-detalle-monto-item"><span class="ef-detalle-monto-label">Cotización</span><div class="ef-detalle-monto-valor">' + fmtMonto(rondas5 ? rondas5.actual.monto_validado : 0) + '</div></div>'
                +   '<div class="ef-detalle-monto-sep"></div>'
                +   '<div class="ef-detalle-monto-item"><span class="ef-detalle-monto-label">Facturado</span><div class="ef-detalle-monto-valor is-verde">' + fmtMonto(montoFacturadoDe(p)) + '</div></div>'
                + '</div>'
            );
            if (plazoMeses > 0) {
                var estadoPagoLabel = { pendiente: 'Pendiente', en_proceso: 'En proceso', completado: 'Completado', cerrado: 'Cerrado' }[p.estado_pago] || 'Pendiente';
                // Cierre desde la web trae motivo_cierre_pago; desde el móvil solo estado_pago='cerrado'.
                var planCerrado = !!p.motivo_cierre_pago || p.estado_pago === 'cerrado';
                filas.push('<div class="ef-detalle-linea"><span class="ef-detalle-label">Plan de pago</span>'
                    + plazoMeses + ' meses · <span class="ef-detalle-estado-pago' + (p.estado_pago === 'completado' ? ' is-completo' : '') + '">' + esc(estadoPagoLabel) + '</span></div>');
                if (planCerrado) {
                    filas.push('<div class="ef-detalle-motivo is-cierre"><strong>Plan cerrado' + (p.motivo_cierre_pago ? ':' : '') + '</strong>' + (p.motivo_cierre_pago ? ' ' + esc(p.motivo_cierre_pago) : ' (desde la app)') + '</div>');
                }
            }
        }

        document.getElementById('efDetalleBody').innerHTML = filas.join('');

        var VER_MAS_LABEL = { 1: 'Ver agendamiento', 2: 'Ver agendamiento', 3: 'Ver proforma', 4: 'Ver proforma', 5: 'Ver factura' };
        var btnVerMas = document.getElementById('efDetalleBtnVerMas');
        btnVerMas.textContent = (VER_MAS_LABEL[f] || 'Ver más') + ' »';
        btnVerMas.dataset.target = (f <= 2) ? '#sec-agendamientos' : (f <= 4 ? '#sec-proforma' : '#sec-factura');
        btnVerMas.style.display = '';

        document.getElementById('efDetalleOverlay').classList.add('is-abierto');
    }

    function cerrarDetalle() {
        document.getElementById('efDetalleOverlay').classList.remove('is-abierto');
    }

    // PDV y Empresa: desplegables independientes que comparan contra el valor exacto.
    function matchPdv(p, valor) {
        return !valor || (p.pdv || '') === valor;
    }
    function matchEmpresa(p, valor) {
        return !valor || (p.empresa || '') === valor;
    }

    // Selector de Periodo con meses reales (mismo mecanismo que factura.js). Clave = "YYYY-MM".
    var NOMBRES_MES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    function claveMes(fechaStr) {
        var f = soloFecha(fechaStr);
        if (!f || f === '0000-00-00') return null;
        var partes = f.split('-'); // "YYYY-MM-DD"
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

    // Opciones: meses con datos (mismo criterio que refFechaFase) + el mes actual.
    function poblarSelectorPeriodo() {
        var select = document.getElementById('efFiltroPeriodo');
        var valorPrevio = select.value;
        var actual = claveMesActual();

        var claves = {};
        claves[actual] = true;
        pipeline.forEach(function (p) {
            var fechaAgendaValida = soloFecha(p.fecha_agendamiento);
            var refCruda = (fechaAgendaValida && fechaAgendaValida !== '0000-00-00')
                ? p.fecha_agendamiento
                : p.contacto_fecha_registro;
            var clave = claveMes(refCruda);
            if (clave) claves[clave] = true;
        });

        select.innerHTML = '<option value="todos">Todos</option>';
        Object.keys(claves).sort().reverse().forEach(function (clave) {
            var opt = document.createElement('option');
            opt.value = clave;
            opt.textContent = etiquetaMes(clave);
            select.appendChild(opt);
        });

        var opciones = Array.prototype.map.call(select.options, function (o) { return o.value; });
        select.value = opciones.indexOf(valorPrevio) !== -1 ? valorPrevio : actual;
    }

    // Mismo criterio de normalización que refFechaFase/poblarSelectorPeriodo:
    // fecha_agendamiento en blanco llega como '0000-00-00...' (truthy en JS),
    // así que se valida antes de usarla como referencia.
    function coincidePeriodo(p) {
        var sel = document.getElementById('efFiltroPeriodo');
        var clave = sel ? sel.value : 'todos';
        if (!clave || clave === 'todos') return true;
        var fechaAgendaValida = soloFecha(p.fecha_agendamiento);
        var refCruda = (fechaAgendaValida && fechaAgendaValida !== '0000-00-00')
            ? p.fecha_agendamiento
            : p.contacto_fecha_registro;
        return claveMes(refCruda) === clave;
    }

    function pipelineFiltrado() {
        var prom = document.getElementById('efFiltroPromotor').value;
        var pdv  = document.getElementById('efFiltroPdv').value;
        var emp  = document.getElementById('efFiltroEmpresa').value;
        return pipeline.filter(function (p) {
            if (prom && (p.usuario || '') !== prom) return false;
            if (!matchPdv(p, pdv) || !matchEmpresa(p, emp)) return false;
            if (!coincidePeriodo(p)) return false;
            return true;
        });
    }

    // ── Filtros: Promotor / PDV / Empresa ──────────────────────────────
    // Salen de allRows (solo lo ya registrado en este módulo, no el catálogo completo).
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
        if (valorPrevio && valores.indexOf(valorPrevio) !== -1) select.value = valorPrevio;
    }

    // ── Carga de datos ────────────────────────────────────────────────
    function cargar() {
        document.getElementById('efKanban').innerHTML = '<div class="ef-vacio">Cargando...</div>';

        return Promise.all([
            fetch(GETTERS_BASE + 'proformas_listar.php').then(function (r) { return r.json(); }),
            fetch(GETTERS_BASE + 'get_pagos_factura.php').then(function (r) { return r.json(); })
        ])
            .then(function (resultados) {
                allRows = resultados[0].data || [];
                pagosPorProforma = {};
                (resultados[1].data || []).forEach(function (pg) {
                    var key = pg.id_proforma;
                    pagosPorProforma[key] = (pagosPorProforma[key] || 0) + (parseFloat(pg.monto_pago) || 0);
                });
                pipeline = ultimosCiclos(allRows);
                poblarSelectorPeriodo();
                poblarSelectDistinct('efFiltroPromotor', allRows, 'usuario', 'Todos');
                poblarSelectDistinct('efFiltroPdv', allRows, 'pdv', 'Todos');
                poblarSelectDistinct('efFiltroEmpresa', allRows, 'empresa', 'Todas');
                construirEsqueletoKanban();
                renderKanban(pipelineFiltrado());
            })
            .catch(function () {
                document.getElementById('efKanban').innerHTML = '<div class="ef-vacio">Error al cargar datos.</div>';
            });
    }

    // ── Eventos ────────────────────────────────────────────────────────
    document.getElementById('efFiltroPromotor').addEventListener('change', function () { renderKanban(pipelineFiltrado()); });
    document.getElementById('efFiltroPdv').addEventListener('change', function () { renderKanban(pipelineFiltrado()); });
    document.getElementById('efFiltroEmpresa').addEventListener('change', function () { renderKanban(pipelineFiltrado()); });
    document.getElementById('efFiltroPeriodo').addEventListener('change', function () { renderKanban(pipelineFiltrado()); });
    document.getElementById('efActualizar').addEventListener('click', cargar);
    ['efFiltroPromotor', 'efFiltroPdv', 'efFiltroEmpresa'].forEach(function (id) {
        window.AgendaHabilitarComboBuscador(id);
    });

    // Delegado sobre el contenedor (las tarjetas se repintan enteras en
    // cada renderKanban, un listener por tarjeta se perdería en cada refresh).
    document.getElementById('efKanban').addEventListener('click', function (ev) {
        var card = ev.target.closest('.ef-card');
        if (!card) return;
        var p = pipeline.filter(function (x) { return String(x.agendamiento_id) === String(card.dataset.agendamientoId); })[0];
        if (p) abrirDetalle(p);
    });

    document.getElementById('efDetalleClose').addEventListener('click', cerrarDetalle);
    document.getElementById('efDetalleCerrar').addEventListener('click', cerrarDetalle);
    document.getElementById('efDetalleOverlay').addEventListener('click', function (ev) {
        if (ev.target === this) cerrarDetalle();
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && document.getElementById('efDetalleOverlay').classList.contains('is-abierto')) cerrarDetalle();
    });
    // "Ver más": simula el clic en el link real del sidebar en vez de reimplementar el toggle.
    document.getElementById('efDetalleBtnVerMas').addEventListener('click', function (ev) {
        ev.preventDefault();
        var destino = this.dataset.target;
        var p = detalleAbierto;
        cerrarDetalle();
        var link = document.querySelector('.sidebar-nav a[href="' + destino + '"]');
        if (link) link.click();

        // Cada módulo destino expone su propio Recargar().then(Abrir) para caer directo
        // en el registro correcto, no en datos viejos de antes de refrescar.
        if (!p) return;
        if (destino === '#sec-agendamientos' && window.AgendaRecargar && window.AgendaResaltar) {
            window.AgendaRecargar().then(function () {
                window.AgendaResaltar(p.agendamiento_id, p.fecha_agendamiento, p.hora);
            });
        } else if (destino === '#sec-proforma' && window.ProformaRecargar && window.ProformaAbrir) {
            window.ProformaRecargar().then(function () {
                window.ProformaAbrir(p.agendamiento_id);
            });
        } else if (destino === '#sec-factura' && window.FacturaRecargar && window.FacturaAbrirAuditoria) {
            window.FacturaRecargar().then(function () {
                window.FacturaAbrirAuditoria(p.agendamiento_id);
            });
        }
    });

    window.EstadoFlujoRecargar = cargar;
    cargar();
})();
