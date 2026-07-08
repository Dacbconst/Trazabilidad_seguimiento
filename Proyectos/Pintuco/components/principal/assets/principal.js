(function () {
    'use strict';

    var app          = document.getElementById('principalApp');
    var GETTERS_BASE = app.dataset.gettersBase;

    var registrosCrudos = [];
    var pagosCrudos      = [];

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
    // refFechaFase(): fecha de referencia para "días sin avance" en la fase actual.
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

            var ultimoConMonto = null;
            ciclos.forEach(function (c) { if (c.monto_validado) ultimoConMonto = c; });

            var fechaRef = refFechaFase(ultimo, fase);
            var dias     = diasDesde(fechaRef);

            return {
                agendamiento_id: id,
                usuario: ciclos[0].usuario,
                fase: fase,
                montoNegociado: ultimoConMonto ? (parseFloat(ultimoConMonto.monto_validado) || 0) : 0,
                fechaRef: fechaRef,
                estancado: fase < 5 && dias !== null && dias > 7,
            };
        });
    }

    // Mismo criterio de "Período" que proforma.js (Cualquier fecha/Este mes/
    // Mes anterior/Últimos 3 meses) para no introducir un comportamiento
    // distinto al resto de la app.
    function pasaPeriodo(fechaRef, periodoClave) {
        if (!periodoClave) return true;
        if (!fechaRef) return false;
        var hoy = new Date(), desde;
        if (periodoClave === 'mes_actual')   desde = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        if (periodoClave === 'mes_anterior') desde = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
        if (periodoClave === 'ultimos_3')    desde = new Date(hoy.getFullYear(), hoy.getMonth() - 3, 1);
        if (!desde) return true;
        var ref = new Date(String(fechaRef).split(' ')[0] + 'T00:00:00');
        return ref >= desde;
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
        document.getElementById('kpiTotal').textContent      = kpis.total_pdvs;
        document.getElementById('kpiFacturado').textContent  = fmtMonto(kpis.monto_facturado);
        document.getElementById('kpiNegociado').textContent  = fmtMonto(kpis.monto_negociado);
        document.getElementById('kpiConversion').textContent = kpis.conversion_pct + '%';
        document.getElementById('kpiEstancados').textContent = kpis.estancados_count;
        document.getElementById('dashFunnelTotal').textContent = kpis.total_pdvs + ' PDV' + (kpis.total_pdvs !== 1 ? 's' : '') + ' totales';
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
            var pdvs    = p.total + ' PDV' + (p.total !== 1 ? 's' : '');
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

    // Recalcula KPIs/embudo/promotores a partir de los datos ya cargados,
    // aplicando los filtros de Promotor y Período — sin ida y vuelta al
    // servidor (mismo criterio que proforma.js / contactados.js).
    function renderizar() {
        var promotorSel  = document.getElementById('dashFiltroPromotor').value;
        var periodoClave = document.getElementById('dashFiltroPeriodo').value;

        var agendamientos = construirAgendamientos(registrosCrudos).filter(function (a) {
            if (promotorSel && a.usuario !== promotorSel) return false;
            return pasaPeriodo(a.fechaRef, periodoClave);
        });

        var idsPermitidos = {};
        agendamientos.forEach(function (a) { idsPermitidos[a.agendamiento_id] = true; });

        var totalPdvs = agendamientos.length;
        var conteoFase = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
        var montoNeg = 0;
        var estancados = 0;
        agendamientos.forEach(function (a) {
            conteoFase[a.fase]++;
            montoNeg += a.montoNegociado;
            if (a.estancado) estancados++;
        });
        var fase5 = conteoFase[5];
        var convPct = totalPdvs > 0 ? Math.round((fase5 / totalPdvs) * 1000) / 10 : 0;

        // Pagos reales (insert_pago_factura) de los agendamientos que pasan
        // el filtro actual — el monto facturado no vive en insert_proforma
        // (ver comentario en construirAgendamientos / estado-flujo.js).
        var pagosFiltrados = pagosCrudos.filter(function (pg) { return idsPermitidos[pg.id_agendamiento]; });
        var montoFact = 0;
        var facturadoPorUsuario = {};
        pagosFiltrados.forEach(function (pg) {
            var monto = parseFloat(pg.monto_pago) || 0;
            montoFact += monto;
            var u = pg.usuario || '(sin asignar)';
            facturadoPorUsuario[u] = (facturadoPorUsuario[u] || 0) + monto;
        });

        renderKpis({
            total_pdvs: totalPdvs,
            monto_negociado: montoNeg,
            monto_facturado: montoFact,
            conversion_pct: convPct,
            estancados_count: estancados,
        });

        renderFunnel(FASES_META.map(function (m) { return { fase: m.fase, label: m.label, count: conteoFase[m.fase] }; }), totalPdvs);

        var promMap = {};
        agendamientos.forEach(function (a) {
            var u = a.usuario || '(sin asignar)';
            if (!promMap[u]) promMap[u] = { usuario: u, total: 0, monto_facturado: 0 };
            promMap[u].total++;
        });
        Object.keys(facturadoPorUsuario).forEach(function (u) {
            if (promMap[u]) promMap[u].monto_facturado = facturadoPorUsuario[u];
        });
        var promotores = Object.values(promMap)
            .sort(function (a, b) { return b.monto_facturado - a.monto_facturado; })
            .slice(0, 8);
        renderPromotores(promotores);
    }

    function cargar() {
        document.getElementById('dashFunnel').innerHTML     = '<div class="dash-cargando">Cargando...</div>';
        document.getElementById('dashPromotores').innerHTML = '<div class="dash-cargando">Cargando...</div>';
        document.querySelectorAll('.dash-kpi').forEach(function (el) { el.classList.add('is-loading'); });
        ['kpiTotal', 'kpiFacturado', 'kpiNegociado', 'kpiConversion', 'kpiEstancados', 'dashFunnelTotal'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = '—';
        });

        fetch(GETTERS_BASE + 'get_dashboard.php')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                registrosCrudos = d.registros || [];
                pagosCrudos      = d.pagos || [];
                construirOpcionesPromotor();
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
    cargar();
})();
