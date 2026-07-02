(function () {
    'use strict';

    var app          = document.getElementById('principalApp');
    var GETTERS_BASE = app.dataset.gettersBase;

    function fmtMonto(v) {
        var n = parseFloat(v) || 0;
        return '$' + n.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderKpis(kpis) {
        document.getElementById('kpiTotal').textContent      = kpis.total_pdvs;
        document.getElementById('kpiFacturado').textContent  = fmtMonto(kpis.monto_facturado);
        document.getElementById('kpiNegociado').textContent  = fmtMonto(kpis.monto_negociado);
        document.getElementById('kpiConversion').textContent = kpis.conversion_pct + '%';
        document.querySelectorAll('.dash-kpi').forEach(function (el) {
            el.classList.remove('is-loading');
        });
    }

    function renderFunnel(fases) {
        var container = document.getElementById('dashFunnel');
        if (!fases || !fases.length) {
            container.innerHTML = '<div class="dash-cargando">Sin datos.</div>';
            return;
        }
        var maxCount = Math.max.apply(null, fases.map(function (f) { return f.count; })) || 1;
        container.innerHTML = fases.map(function (f) {
            var pct = Math.max(4, Math.round(f.count / maxCount * 100));
            var cls = f.fase === 5 ? 'is-f5' : '';
            return '<div class="dash-funnel-fila">'
                + '<span class="dash-funnel-etiqueta">Fase ' + f.fase + ' — ' + f.label + '</span>'
                + '<div class="dash-funnel-barra-wrap">'
                +   '<div class="dash-funnel-barra ' + cls + '" style="width:' + pct + '%"></div>'
                + '</div>'
                + '<span class="dash-funnel-count">' + f.count + '</span>'
                + '</div>';
        }).join('');
    }

    function renderPromotores(promotores) {
        var container = document.getElementById('dashPromotores');
        if (!promotores || !promotores.length) {
            container.innerHTML = '<div class="dash-promo-vacio">Sin datos.</div>';
            return;
        }
        container.innerHTML = promotores.map(function (p) {
            var inicial = (p.usuario || '?').charAt(0).toUpperCase();
            var pdvs    = p.total + ' PDV' + (p.total !== 1 ? 's' : '');
            return '<div class="dash-promo-fila">'
                + '<div class="dash-promo-avatar">' + inicial + '</div>'
                + '<span class="dash-promo-nombre">' + (p.usuario || '—') + '</span>'
                + '<span class="dash-promo-meta">' + pdvs + '</span>'
                + '<span class="dash-promo-monto">' + fmtMonto(p.monto_facturado) + '</span>'
                + '</div>';
        }).join('');
    }

    function cargar() {
        document.getElementById('dashFunnel').innerHTML    = '<div class="dash-cargando">Cargando...</div>';
        document.getElementById('dashPromotores').innerHTML = '<div class="dash-cargando">Cargando...</div>';
        document.querySelectorAll('.dash-kpi').forEach(function (el) { el.classList.add('is-loading'); });
        ['kpiTotal','kpiFacturado','kpiNegociado','kpiConversion'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = '—';
        });

        fetch(GETTERS_BASE + 'get_dashboard.php')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                renderKpis(d.kpis);
                renderFunnel(d.fases);
                renderPromotores(d.promotores);
            })
            .catch(function () {
                document.getElementById('dashFunnel').innerHTML = '<div class="dash-cargando">Error al cargar datos.</div>';
            });
    }

    window.DashboardRecargar = cargar;
    document.getElementById('dashActualizar').addEventListener('click', cargar);
    cargar();
})();
