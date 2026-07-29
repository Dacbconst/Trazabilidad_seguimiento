(function () {
    'use strict';

    var app          = document.getElementById('principalApp');
    var GETTERS_BASE = app.dataset.gettersBase;

    var registrosCrudos = [];
    var pagosCrudos      = [];
    var agendamientosVista = []; // último cálculo de renderizar(), con los filtros ya aplicados

    var FASES_META = [
        { fase: 1, label: 'Contacto inicial' },
        { fase: 2, label: 'Agendamiento' },
        { fase: 3, label: 'Proforma recibida' },
        { fase: 4, label: 'Negociación' },
        { fase: 5, label: 'Facturado' },
    ];

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function fmtMonto(v) {
        var n = parseFloat(v) || 0;
        return '$' + n.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ── Reglas de negocio portadas de estado-flujo.js (misma fuente de verdad) ──
    // getFase(): fase vigente del agendamiento según su último ciclo de proforma.
    function getFase(ultimo) {
        if (!ultimo) return 1;
        if (ultimo.foto_factura || ultimo.estado_proforma === 'aprobado') return 5;
        if (ultimo.estado_proforma === 'rechazado') return 4;
        if (ultimo.proforma_id) return 4;
        if (ultimo.hora && ultimo.tecnico) return 2;
        return 1;
    }
    // refFechaFase(): fecha de referencia para el filtro de Período (no para
    // "vencida", que usa fecha_agendamiento — ver esVencida()).
    function refFechaFase(ultimo, fase) {
        if (fase >= 4) return ultimo.proforma_fecha_registro || ultimo.contacto_fecha_registro;
        return ultimo.contacto_fecha_registro;
    }
    function diasDesde(fechaStr) {
        if (!fechaStr) return null;
        var f = String(fechaStr).split(' ')[0];
        if (!f || f === '0000-00-00') return null;
        return Math.floor((new Date() - new Date(f + 'T00:00:00')) / 86400000);
    }

    // Mismo helper que agenda.js — fecha local "YYYY-MM-DD" (no toISOString(),
    // que corre por UTC y puede desfasar un día).
    function hoyISO() {
        var h = new Date();
        return h.getFullYear() + '-' + String(h.getMonth() + 1).padStart(2, '0') + '-' + String(h.getDate()).padStart(2, '0');
    }

    // Misma regla que el UPDATE perezoso de get_agenda.php (contrato de 6
    // estados con la app móvil): una visita está vencida si su fecha ya pasó
    // y no fue cancelada ni completada — sin importar si el literal
    // estado_agenda en BD ya se corrigió (esa corrección solo corre cuando
    // se abre la Agenda; el Dashboard es de solo lectura, así que recalcula
    // la misma condición en vez de confiar ciegamente en el campo).
    function esVencida(fechaAgendamiento, estadoAgenda) {
        if (!fechaAgendamiento) return false;
        if (estadoAgenda === 'cancelada' || estadoAgenda === 'completada') return false;
        var f = String(fechaAgendamiento).split(' ')[0];
        if (!f || f === '0000-00-00') return false;
        return f < hoyISO();
    }

    function fmtFechaCorta(fechaStr) {
        if (!fechaStr) return '—';
        var partes = String(fechaStr).split(' ')[0].split('-');
        if (partes.length !== 3) return '—';
        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    // Agrupa las filas crudas (una por agendamiento×ciclo) en un registro por
    // agendamiento con su fase vigente, monto negociado y fecha de referencia
    // ya resueltos — mismo criterio que agruparPorAgendamiento()/getFase()/
    // ultimaProformaDe() de estado-flujo.js.
    function construirAgendamientos(registros) {
        var porAgendamiento = {};
        registros.forEach(function (r) {
            var key = r.agendamiento_id;
            if (!porAgendamiento[key]) porAgendamiento[key] = [];
            porAgendamiento[key].push(r);
        });

        return Object.keys(porAgendamiento).map(function (id) {
            var ciclos = porAgendamiento[id].slice().sort(function (a, b) {
                return (parseInt(a.proforma_id, 10) || 0) - (parseInt(b.proforma_id, 10) || 0);
            });
            var ultimo = ciclos[ciclos.length - 1];
            var fase   = getFase(ultimo);

            // Excluye agendamientos cuyo ciclo vigente quedó 'rechazado'
            // (proceso cerrado sin llegar a factura, ver update_proforma.php)
            // — "Monto negociado" vive al lado de "Monto facturado" como par
            // pipeline-vs-cerrado, así que un proceso muerto debe contribuir
            // $0, no el monto de una ronda anterior ya superada (decisión
            // 2026-07-16, distinta a propósito del "Total acumulado" de
            // Estado de Flujo, que sí las cuenta porque responde otra
            // pregunta: actividad total, no pipeline vivo). 'rechazado' es
            // siempre terminal — si aparece, solo puede ser en ciclos[último],
            // nunca en una ronda intermedia (no se abre ciclo nuevo después).
            var ultimoConMonto = null;
            if (ultimo.estado_proforma !== 'rechazado') {
                ciclos.forEach(function (c) { if (c.monto_validado) ultimoConMonto = c; });
            }

            var fechaAgendamiento = ciclos[0].fecha_agendamiento;
            var estadoAgenda      = ciclos[0].estado_agenda;

            return {
                agendamiento_id: id,
                usuario: ciclos[0].usuario,
                empresa: ciclos[0].empresa,
                contacto: ciclos[0].contacto,
                pdv: ciclos[0].pdv,
                fase: fase,
                montoNegociado: ultimoConMonto ? (parseFloat(ultimoConMonto.monto_validado) || 0) : 0,
                fechaRef: refFechaFase(ultimo, fase),
                fechaAgendamiento: fechaAgendamiento,
                estadoAgenda: estadoAgenda,
                vencida: esVencida(fechaAgendamiento, estadoAgenda),
            };
        });
    }

    // Ciclos reales por agendamiento, ascendente por proforma_id (mismo patrón que factura.js).
    function agruparCiclosPorAgendamiento(registros) {
        var mapa = {};
        registros.forEach(function (r) {
            if (!r.proforma_id) return;
            var key = r.agendamiento_id;
            if (!mapa[key]) mapa[key] = [];
            mapa[key].push(r);
        });
        Object.keys(mapa).forEach(function (k) {
            mapa[k].sort(function (a, b) { return (parseInt(a.proforma_id, 10) || 0) - (parseInt(b.proforma_id, 10) || 0); });
        });
        return mapa;
    }

    // Respaldo si la fila con foto_factura no trae plazo_meses (mismo criterio que factura.js).
    function plazoMesesDe(factura, ciclos) {
        var propio = parseInt(factura.plazo_meses, 10);
        if (!isNaN(propio) && propio > 0) return propio;
        return ciclos.reduce(function (max, c) {
            var v = parseInt(c.plazo_meses, 10);
            return (!isNaN(v) && v > max) ? v : max;
        }, 0);
    }

    function agruparPagosPorProforma(pagos) {
        var mapa = {};
        pagos.forEach(function (pg) {
            var key = pg.id_proforma;
            if (!mapa[key]) mapa[key] = [];
            mapa[key].push(pg);
        });
        return mapa;
    }

    // Centavos enteros para sumar sin arrastrar error de coma flotante.
    function centavosDe(v) {
        var n = parseFloat(v);
        return isNaN(n) ? 0 : Math.round(n * 100);
    }

    var MESES_ES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    // "YYYY-MM" de una fecha — clave de agrupación para el filtro de Período.
    function claveMes(fechaStr) {
        if (!fechaStr) return null;
        var f = String(fechaStr).split(' ')[0];
        if (!f || f === '0000-00-00') return null;
        var partes = f.split('-');
        if (partes.length < 2) return null;
        return partes[0] + '-' + partes[1];
    }

    // Período = mes calendario exacto del registro, no relativo a hoy.
    function pasaPeriodo(fechaRef, periodoClave) {
        if (!periodoClave) return true;
        if (!fechaRef) return false;
        return claveMes(fechaRef) === periodoClave;
    }

    // Solo lista meses que realmente tienen algún registro.
    function construirOpcionesPeriodo() {
        var select = document.getElementById('dashFiltroPeriodo');
        var valorPrevio = select.value;
        var claves = {};
        construirAgendamientos(registrosCrudos).forEach(function (a) {
            var k = claveMes(a.fechaRef);
            if (k) claves[k] = true;
        });
        pagosCrudos.forEach(function (pg) {
            var k = claveMes(pg.fecha_pago || pg.fecha_registro);
            if (k) claves[k] = true;
        });
        registrosCrudos.forEach(function (r) {
            if (!r.foto_factura) return;
            var k = claveMes(r.proforma_fecha_registro);
            if (k) claves[k] = true;
        });
        var ordenadas = Object.keys(claves).sort().reverse(); // más reciente primero

        select.innerHTML = '<option value="">Cualquier fecha</option>';
        ordenadas.forEach(function (k) {
            var partes = k.split('-');
            var mes = MESES_ES[parseInt(partes[1], 10) - 1];
            var opt = document.createElement('option');
            opt.value = k;
            opt.textContent = mes + ' ' + partes[0];
            select.appendChild(opt);
        });
        if (valorPrevio === '' || claves[valorPrevio]) select.value = valorPrevio;
    }

    function construirOpcionesPromotor() {
        var select = document.getElementById('dashFiltroPromotor');
        var valorPrevio = select.value;
        select.innerHTML = '<option value="">Todos</option>';
        var vistos = {};
        registrosCrudos.forEach(function (r) {
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

    function renderKpis(kpis) {
        document.getElementById('kpiPdvs').textContent       = kpis.pdvs_distintos;
        document.getElementById('kpiFacturado').textContent  = fmtMonto(kpis.monto_facturado);
        document.getElementById('kpiNegociado').textContent  = fmtMonto(kpis.monto_negociado);
        document.getElementById('kpiConversion').textContent = kpis.conversion_pct + '%';
        document.getElementById('kpiVencidas').textContent = kpis.vencidas_count;
        document.getElementById('dashFunnelTotal').textContent = kpis.total_agendamientos + ' agendamiento' + (kpis.total_agendamientos !== 1 ? 's' : '') + ' totales';
        document.querySelectorAll('.dash-kpi').forEach(function (el) {
            el.classList.remove('is-loading');
        });
    }

    function renderFunnel(fases, totalPdvs) {
        var container = document.getElementById('dashFunnel');
        if (!fases || !fases.length) {
            container.innerHTML = '<div class="dash-cargando">Sin datos.</div>';
            return;
        }
        var maxCount = Math.max.apply(null, fases.map(function (f) { return f.count; })) || 1;
        container.innerHTML = fases.map(function (f) {
            var pct     = Math.max(3, Math.round(f.count / maxCount * 100));
            var pctReal = totalPdvs > 0 ? Math.round(f.count / totalPdvs * 100) : 0;
            var cls     = f.fase === 5 ? 'is-f5' : '';
            return '<div class="dash-funnel-fila ' + cls + '">'
                + '<span class="dash-funnel-etiqueta"><span class="dash-funnel-dot"></span>Fase ' + f.fase + ' — ' + esc(f.label) + '</span>'
                + '<div class="dash-funnel-barra-wrap">'
                +   '<div class="dash-funnel-barra" style="width:' + pct + '%"></div>'
                + '</div>'
                + '<span class="dash-funnel-count">' + f.count + '</span>'
                + '<span class="dash-funnel-pct">' + pctReal + '%</span>'
                + '</div>';
        }).join('');
    }

    function renderPromotores(promotores) {
        var container = document.getElementById('dashPromotores');
        if (!promotores || !promotores.length) {
            container.innerHTML = '<div class="dash-promo-vacio">Sin datos.</div>';
            return;
        }
        var maxMonto = Math.max.apply(null, promotores.map(function (p) { return p.monto_facturado; }));
        var maxTotal = Math.max.apply(null, promotores.map(function (p) { return p.total; })) || 1;

        var filas = promotores.map(function (p, i) {
            var inicial = (p.usuario || '?').charAt(0).toUpperCase();
            var pdvs    = p.total + ' agendamiento' + (p.total !== 1 ? 's' : '');
            var pct     = maxMonto > 0 ? Math.round(p.monto_facturado / maxMonto * 100) : Math.round(p.total / maxTotal * 100);
            return '<div class="dash-promo-fila">'
                + '<span class="dash-promo-rank">' + (i + 1) + '</span>'
                + '<div class="dash-promo-avatar">' + esc(inicial) + '</div>'
                + '<div class="dash-promo-info">'
                +   '<div class="dash-promo-nombre">' + esc(p.usuario || '—') + '</div>'
                +   '<div class="dash-promo-barra-wrap"><div class="dash-promo-barra" style="width:' + Math.max(3, pct) + '%"></div></div>'
                + '</div>'
                + '<div class="dash-promo-meta">'
                +   '<div class="dash-promo-pdvs">' + pdvs + '</div>'
                +   '<div class="dash-promo-monto">' + fmtMonto(p.monto_facturado) + '</div>'
                + '</div>'
                + '</div>';
        }).join('');

        var nota = promotores.length <= 1
            ? '<div class="dash-promo-nota">Solo ' + promotores.length + ' promotor activo con estos filtros — datos limitados aún</div>'
            : '';

        container.innerHTML = filas + nota;
    }

    function renderTopPdv(pdvs) {
        var container = document.getElementById('dashTopPdv');
        if (!pdvs || !pdvs.length) {
            container.innerHTML = '<div class="dash-promo-vacio">Sin datos.</div>';
            return;
        }
        var maxMonto = Math.max.apply(null, pdvs.map(function (p) { return p.monto_facturado; }));
        var maxTotal = Math.max.apply(null, pdvs.map(function (p) { return p.total; })) || 1;

        var filas = pdvs.map(function (p, i) {
            var inicial = (p.pdv || '?').charAt(0).toUpperCase();
            var count   = p.total + ' agendamiento' + (p.total !== 1 ? 's' : '');
            var pct     = maxMonto > 0 ? Math.round(p.monto_facturado / maxMonto * 100) : Math.round(p.total / maxTotal * 100);
            return '<div class="dash-promo-fila">'
                + '<span class="dash-promo-rank">' + (i + 1) + '</span>'
                + '<div class="dash-promo-avatar">' + esc(inicial) + '</div>'
                + '<div class="dash-promo-info">'
                +   '<div class="dash-promo-nombre">' + esc(p.pdv || '—') + '</div>'
                +   '<div class="dash-promo-barra-wrap"><div class="dash-promo-barra" style="width:' + Math.max(3, pct) + '%"></div></div>'
                + '</div>'
                + '<div class="dash-promo-meta">'
                +   '<div class="dash-promo-pdvs">' + count + '</div>'
                +   '<div class="dash-promo-monto">' + fmtMonto(p.monto_facturado) + '</div>'
                + '</div>'
                + '</div>';
        }).join('');

        container.innerHTML = filas;
    }

    // Recalcula KPIs/embudo/promotores a partir de los datos ya cargados,
    // aplicando los filtros de Promotor y Período — sin ida y vuelta al
    // servidor (mismo criterio que proforma.js / contactados.js).
    function renderizar() {
        var promotorSel  = document.getElementById('dashFiltroPromotor').value;
        var periodoClave = document.getElementById('dashFiltroPeriodo').value;

        var todosAgendamientos = construirAgendamientos(registrosCrudos);

        var agendamientos = todosAgendamientos.filter(function (a) {
            if (promotorSel && a.usuario !== promotorSel) return false;
            return pasaPeriodo(a.fechaRef, periodoClave);
        });
        agendamientosVista = agendamientos; // para el modal de "Visitas vencidas"

        var totalAgendamientos = agendamientos.length;
        var pdvsDistintos = new Set(agendamientos.map(function (a) { return a.pdv; }).filter(Boolean)).size;
        var conteoFase = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
        var montoNegCentavos = 0;
        var vencidas = 0;
        agendamientos.forEach(function (a) {
            conteoFase[a.fase]++;
            montoNegCentavos += centavosDe(a.montoNegociado);
            if (a.vencida) vencidas++;
        });
        var montoNeg = montoNegCentavos / 100;
        var fase5 = conteoFase[5];
        var convPct = totalAgendamientos > 0 ? Math.round((fase5 / totalAgendamientos) * 1000) / 10 : 0;

        // "Monto Facturado" híbrido: Pago Directo lee monto_total_factura, a plazos suma insert_pago_factura (mismo contrato que factura.js).
        var ciclosPorAgendamiento = agruparCiclosPorAgendamiento(registrosCrudos);
        var pagosPorProforma      = agruparPagosPorProforma(pagosCrudos);

        var montoFactCentavos = 0;
        var facturadoPorUsuarioCentavos = {};
        var facturadoPorPdvCentavos = {};
        function sumarFacturado(usuario, pdv, monto) {
            var c = centavosDe(monto);
            montoFactCentavos += c;
            var u = usuario || '(sin asignar)';
            facturadoPorUsuarioCentavos[u] = (facturadoPorUsuarioCentavos[u] || 0) + c;
            var pdvKey = pdv || '(sin PDV)';
            facturadoPorPdvCentavos[pdvKey] = (facturadoPorPdvCentavos[pdvKey] || 0) + c;
        }

        Object.keys(ciclosPorAgendamiento).forEach(function (agId) {
            var ciclos = ciclosPorAgendamiento[agId];
            ciclos.filter(function (c) { return !!c.foto_factura; }).forEach(function (factura) {
                if (promotorSel && factura.usuario !== promotorSel) return;
                if (plazoMesesDe(factura, ciclos) <= 0) {
                    if (periodoClave && claveMes(factura.proforma_fecha_registro) !== periodoClave) return;
                    sumarFacturado(factura.usuario, factura.pdv, factura.monto_total_factura);
                } else {
                    var pagos = pagosPorProforma[factura.proforma_id] || [];
                    pagos.forEach(function (pg) {
                        if (periodoClave && claveMes(pg.fecha_pago || pg.fecha_registro) !== periodoClave) return;
                        sumarFacturado(factura.usuario, factura.pdv, pg.monto_pago);
                    });
                }
            });
        });

        var montoFact = montoFactCentavos / 100;
        var facturadoPorUsuario = {};
        Object.keys(facturadoPorUsuarioCentavos).forEach(function (u) {
            facturadoPorUsuario[u] = facturadoPorUsuarioCentavos[u] / 100;
        });
        var facturadoPorPdv = {};
        Object.keys(facturadoPorPdvCentavos).forEach(function (k) {
            facturadoPorPdv[k] = facturadoPorPdvCentavos[k] / 100;
        });

        renderKpis({
            pdvs_distintos: pdvsDistintos,
            total_agendamientos: totalAgendamientos,
            monto_negociado: montoNeg,
            monto_facturado: montoFact,
            conversion_pct: convPct,
            vencidas_count: vencidas,
        });

        renderFunnel(FASES_META.map(function (m) { return { fase: m.fase, label: m.label, count: conteoFase[m.fase] }; }), totalAgendamientos);

        var promMap = {};
        agendamientos.forEach(function (a) {
            var u = a.usuario || '(sin asignar)';
            if (!promMap[u]) promMap[u] = { usuario: u, total: 0, monto_facturado: 0 };
            promMap[u].total++;
        });
        // Crea la entrada si hace falta: puede facturar sin tener agendamientos en el filtro.
        Object.keys(facturadoPorUsuario).forEach(function (u) {
            if (!promMap[u]) promMap[u] = { usuario: u, total: 0, monto_facturado: 0 };
            promMap[u].monto_facturado = facturadoPorUsuario[u];
        });
        var promotores = Object.values(promMap)
            .sort(function (a, b) { return b.monto_facturado - a.monto_facturado; })
            .slice(0, 8);
        renderPromotores(promotores);

        var pdvMap = {};
        agendamientos.forEach(function (a) {
            var key = a.pdv || '(sin PDV)';
            if (!pdvMap[key]) pdvMap[key] = { pdv: key, total: 0, monto_facturado: 0 };
            pdvMap[key].total++;
        });
        Object.keys(facturadoPorPdv).forEach(function (key) {
            if (!pdvMap[key]) pdvMap[key] = { pdv: key, total: 0, monto_facturado: 0 };
            pdvMap[key].monto_facturado = facturadoPorPdv[key];
        });
        var topPdvs = Object.values(pdvMap)
            .sort(function (a, b) { return b.monto_facturado - a.monto_facturado; })
            .slice(0, 8);
        renderTopPdv(topPdvs);
    }

    // ---------------------------------------------------------------
    // Modal "Visitas vencidas" — al hacer clic en la tarjeta muestra CUÁLES
    // son (empresa, contacto, punto de venta, promotor, fecha que se pasó y
    // hace cuántos días), más vencida primero. Usa agendamientosVista, el
    // mismo cálculo ya filtrado por Promotor/Período de renderizar() — sin
    // pedir nada nuevo al servidor. "Vencida" = misma regla que el contrato
    // de estado_agenda con la app móvil (ver esVencida()), no el concepto
    // viejo de "fase estancada".
    // ---------------------------------------------------------------
    function abrirModalVencidas() {
        var lista = agendamientosVista
            .filter(function (a) { return a.vencida; })
            .slice()
            .sort(function (a, b) { return (diasDesde(b.fechaAgendamiento) || 0) - (diasDesde(a.fechaAgendamiento) || 0); });

        var cuerpo = document.getElementById('dashVencidasBody');
        if (!lista.length) {
            cuerpo.innerHTML = '<div class="dash-cargando">Ninguna visita vencida con los filtros actuales.</div>';
        } else {
            cuerpo.innerHTML = '<table class="dash-vencidas-tabla"><thead><tr>'
                + '<th>Empresa / Contacto</th><th>Punto de venta</th><th>Promotor</th><th>Fecha agendada</th><th>Días vencida</th>'
                + '</tr></thead><tbody>'
                + lista.map(function (a) {
                    var dias = diasDesde(a.fechaAgendamiento);
                    return '<tr>'
                        + '<td><div class="dash-venc-empresa">' + esc(a.empresa || '—') + '</div>'
                        +     '<div class="dash-venc-contacto">' + esc(a.contacto || '—') + '</div></td>'
                        + '<td>' + esc(a.pdv || '—') + '</td>'
                        + '<td>' + esc(a.usuario || 'Sin asignar') + '</td>'
                        + '<td>' + fmtFechaCorta(a.fechaAgendamiento) + '</td>'
                        + '<td><span class="dash-venc-dias">' + dias + (dias === 1 ? ' día' : ' días') + '</span></td>'
                        + '</tr>';
                }).join('')
                + '</tbody></table>';
        }
        document.getElementById('dashVencidasCount').textContent =
            lista.length + (lista.length === 1 ? ' visita vencida' : ' visitas vencidas');
        document.getElementById('dashVencidasOverlay').classList.add('is-abierto');
    }

    function cerrarModalVencidas() {
        document.getElementById('dashVencidasOverlay').classList.remove('is-abierto');
    }

    function cargar() {
        document.getElementById('dashFunnel').innerHTML     = '<div class="dash-cargando">Cargando...</div>';
        document.getElementById('dashPromotores').innerHTML = '<div class="dash-cargando">Cargando...</div>';
        document.getElementById('dashTopPdv').innerHTML     = '<div class="dash-cargando">Cargando...</div>';
        document.querySelectorAll('.dash-kpi').forEach(function (el) { el.classList.add('is-loading'); });
        ['kpiPdvs', 'kpiFacturado', 'kpiNegociado', 'kpiConversion', 'kpiVencidas', 'dashFunnelTotal'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = '—';
        });

        fetch(GETTERS_BASE + 'get_dashboard.php')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                registrosCrudos = d.registros || [];
                pagosCrudos      = d.pagos || [];
                construirOpcionesPromotor();
                construirOpcionesPeriodo();
                renderizar();
            })
            .catch(function () {
                document.getElementById('dashFunnel').innerHTML = '<div class="dash-cargando">Error al cargar datos.</div>';
            });
    }

    window.DashboardRecargar = cargar;
    document.getElementById('dashActualizar').addEventListener('click', cargar);
    ['dashFiltroPromotor', 'dashFiltroPeriodo'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', renderizar);
    });

    document.getElementById('kpiVencidasCard').addEventListener('click', abrirModalVencidas);
    document.getElementById('dashVencidasClose').addEventListener('click', cerrarModalVencidas);
    var vencidasOverlay = document.getElementById('dashVencidasOverlay');
    vencidasOverlay.addEventListener('click', function (ev) {
        if (ev.target === vencidasOverlay) cerrarModalVencidas();
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && vencidasOverlay.classList.contains('is-abierto')) cerrarModalVencidas();
    });

    cargar();
})();
