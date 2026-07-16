(function () {
    'use strict';

    var app          = document.getElementById('estadoFlujoApp');
    var GETTERS_BASE = app.dataset.gettersBase;

    var allRows  = [];  // todos los ciclos, de todos los agendamientos
    var pipeline = [];  // 1 fila por agendamiento = su último ciclo
    var detalleAbierto = null;  // fila de la mini card actualmente abierta (para "Ver más")

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
    // Umbral: neutro <=3 días, ámbar 4-7, rojo 8+. Antes <=3 era verde, pero
    // "verde" comunica "está bien" — y un agendamiento recién llegado a su
    // fase no está ni bien ni mal, solo no lleva tiempo todavía. Sin señal
    // positiva: el pill queda neutro hasta que empieza a demorarse de verdad.
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
        // no_requiere_visita: el promotor marcó desde el móvil que este
        // contacto no necesita visita técnica — no hay agendamiento que
        // esperar, el siguiente paso real es que suba la foto directo, así
        // que cae en fase 3 (mismo criterio que proforma.js/factura.js).
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
        return '<div class="ef-card" data-agendamiento-id="' + esc(p.agendamiento_id) + '">'
            + '<div class="ef-card-empresa">' + esc(p.empresa || '—') + '</div>'
            + '<div class="ef-card-pdv">' + esc(p.pdv || p.codigo_pdv || '') + '</div>'
            + (p.no_requiere_visita === 'SI' && !(p.hora && p.tecnico)
                ? '<div class="ef-card-nota">No requirió visita</div>' : '')
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
    // Contenido validado con el usuario: solo datos que YA vienen en el
    // fetch de proformas_listar.php (cero llamadas nuevas al abrir), y
    // recortado a "qué está pasando" — el detalle de auditoría/cuotas
    // completo se queda en el botón "Ver más" hacia el módulo dueño.
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

    // Rondas de cotización = ciclos con monto_validado, orden ascendente
    // (mismo criterio que agruparCiclosCotizacion en contactados.js).
    // Mostrar solo "monto cotizado" sin esto escondía que ya se había
    // vuelto a cotizar más de una vez — hallazgo del consejo 2026-07-15.
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
        if (p.estado_proforma === 'rechazado') return '<span class="ef-detalle-badge is-rechazado">Rechazada</span>';
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

        var filas = [badgeEstadoAgenda(p)];

        if (f === 2) {
            filas.push('<div class="ef-detalle-linea"><span class="ef-detalle-label">Visita</span>'
                + esc(formatFechaCorta(p.fecha_agendamiento)) + (p.hora ? ' · ' + esc(formatHora(p.hora)) : '')
                + (p.tecnico ? ' · ' + esc(p.tecnico) : '') + '</div>');
        }

        if (f === 3) {
            filas.push('<div class="ef-detalle-linea">No requirió visita técnica — esperando que suba la foto de la proforma.</div>');
        }

        if (f === 4 || f === 5) {
            var rondas = infoRondas(p.agendamiento_id);
            if (rondas) {
                var texto = (rondas.numero > 1 ? rondas.numero + 'ª ronda de cotización: ' : 'Cotización: ')
                    + fmtMonto(rondas.actual.monto_validado)
                    + (rondas.anterior ? ' (antes ' + fmtMonto(rondas.anterior.monto_validado) + ')' : '');
                filas.push('<div class="ef-detalle-linea"><span class="ef-detalle-label">Monto</span>' + esc(texto) + '</div>');
            }
        }

        if (f === 4) {
            filas.push(badgeAuditoria(p));
            if (p.estado_proforma === 'rechazado' && p.motivo_cierre) {
                filas.push('<div class="ef-detalle-motivo"><strong>Motivo:</strong> ' + esc(p.motivo_cierre) + '</div>');
            }
        }

        if (f === 5) {
            var plazoMeses = parseInt(p.plazo_meses, 10) || 0;
            if (plazoMeses > 0) {
                var estadoPagoLabel = { pendiente: 'Pendiente', en_proceso: 'En proceso', completado: 'Completado' }[p.estado_pago] || 'Pendiente';
                filas.push('<div class="ef-detalle-linea"><span class="ef-detalle-label">Plan de pago</span>'
                    + plazoMeses + ' meses · ' + esc(estadoPagoLabel) + '</div>');
                if (p.motivo_cierre_pago) {
                    filas.push('<div class="ef-detalle-motivo is-cierre"><strong>Plan cerrado:</strong> ' + esc(p.motivo_cierre_pago) + '</div>');
                }
            } else {
                filas.push('<div class="ef-detalle-linea"><span class="ef-detalle-label">Facturado</span>' + fmtMonto(p.monto_total_factura) + '</div>');
            }
        }

        document.getElementById('efDetalleBody').innerHTML = filas.join('');

        var btnVerMas = document.getElementById('efDetalleBtnVerMas');
        btnVerMas.dataset.target = (f <= 2) ? '#sec-agendamientos' : (f <= 4 ? '#sec-proforma' : '#sec-factura');
        btnVerMas.style.display = '';

        document.getElementById('efDetalleOverlay').classList.add('is-abierto');
    }

    function cerrarDetalle() {
        document.getElementById('efDetalleOverlay').classList.remove('is-abierto');
    }

    // PDV y Empresa: antes un solo cuadro de texto con match por substring
    // contra pdv/empresa/codigo_pdv junto; ahora dos desplegables
    // independientes (con buscador propio) que comparan contra el valor
    // exacto elegido — pedido explícito del usuario (2026-07-16: "separa
    // pdv empresa ponle como tenemos" [en Agendamientos]).
    function matchPdv(p, valor) {
        return !valor || (p.pdv || '') === valor;
    }
    function matchEmpresa(p, valor) {
        return !valor || (p.empresa || '') === valor;
    }

    function pipelineFiltrado() {
        var prom = document.getElementById('efFiltroPromotor').value;
        var pdv  = document.getElementById('efFiltroPdv').value;
        var emp  = document.getElementById('efFiltroEmpresa').value;
        return pipeline.filter(function (p) {
            if (prom && (p.usuario || '') !== prom) return false;
            if (!matchPdv(p, pdv) || !matchEmpresa(p, emp)) return false;
            return true;
        });
    }

    // ── Filtros: Promotor / PDV / Empresa ──────────────────────────────
    // Promotor y Empresa salen de allRows (todo lo ya cargado); PDV usa el
    // mismo catálogo de locales que Agendamientos (get_pdvs.php). Los 3
    // usan el combobox con buscador compartido con Agendamientos
    // (habilitarComboBuscador en agenda-crear.js, ver
    // window.AgendaHabilitarComboBuscador) — pedido explícito del usuario
    // (2026-07-16).
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

    function cargarOpcionesPdv() {
        fetch(GETTERS_BASE + 'get_pdvs.php')
            .then(function (r) { return r.json(); })
            .then(function (json) {
                poblarSelectDistinct('efFiltroPdv', json.data || [], 'pos_name', 'Todos');
            });
    }

    // ── Carga de datos ────────────────────────────────────────────────
    function cargar() {
        document.getElementById('efKanban').innerHTML = '<div class="ef-vacio">Cargando...</div>';

        return fetch(GETTERS_BASE + 'proformas_listar.php')
            .then(function (r) { return r.json(); })
            .then(function (json) {
                allRows = json.data || [];
                pipeline = ultimosCiclos(allRows);
                poblarSelectDistinct('efFiltroPromotor', allRows, 'usuario', 'Todos');
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
    document.getElementById('efActualizar').addEventListener('click', cargar);
    ['efFiltroPromotor', 'efFiltroPdv', 'efFiltroEmpresa'].forEach(function (id) {
        window.AgendaHabilitarComboBuscador(id);
    });
    cargarOpcionesPdv();

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
    // "Ver más": reusa el mismo mecanismo de navegación del sidebar (ver
    // index.php) simulando el clic en el link real, en vez de reimplementar
    // el toggle de secciones acá — así hereda gratis el auto-refresco de la
    // sección destino y el manejo de "activo" del sidebar.
    document.getElementById('efDetalleBtnVerMas').addEventListener('click', function (ev) {
        ev.preventDefault();
        var destino = this.dataset.target;
        var p = detalleAbierto;
        cerrarDetalle();
        var link = document.querySelector('.sidebar-nav a[href="' + destino + '"]');
        if (link) link.click();

        // No alcanza con solo cambiar de sección: cada módulo destino expone
        // su propio "abrir este agendamiento puntual" (mismo patrón que ya
        // usa agenda-crear.js al guardar una visita nueva —
        // Recargar().then(Abrir) — para no apuntar a datos viejos de antes
        // de refrescar) y así el analista cae directo en el registro
        // correcto en vez de tener que buscarlo a mano entre todos los demás.
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
