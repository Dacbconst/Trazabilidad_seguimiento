(function () {
    'use strict';

    var app = document.getElementById('avanceApp');
    if (!app) return;

    var GETTERS   = app.dataset.gettersBase || '';
    var todosMerc = [];   // cache del último fetch

    // ─── Carga ────────────────────────────────────────────
    function cargar() {
        document.getElementById('avanceTbody').innerHTML =
            '<tr><td colspan="7" class="avance-vacio">Cargando...</td></tr>';

        fetch(GETTERS + 'get_avance.php')
            .then(function (r) { return r.json(); })
            .then(function (json) {
                todosMerc = json.mercaderistas || [];
                renderKpis(json.global || {});
                renderTabla();
            })
            .catch(function () {
                document.getElementById('avanceTbody').innerHTML =
                    '<tr><td colspan="7" class="avance-vacio">Error al cargar datos.</td></tr>';
            });
    }

    // ─── KPIs globales (siempre del total, no afectados por filtros) ────
    function renderKpis(g) {
        var total = parseInt(g.total_pdvs) || 0;
        var comp  = parseInt(g.completados) || 0;
        var pct   = total > 0 ? Math.round(comp / total * 100) : 0;

        set('avanceKpiMerc',     g.total_mercaderistas || 0);
        set('avanceKpiPdvs',     total);
        set('avanceKpiComp',     comp);
        set('avanceKpiCurso',    g.en_curso || 0);
        set('avanceKpiVencidos', g.vencidos || 0);

        var fill  = document.getElementById('avanceProgresoFill');
        var pctEl = document.getElementById('avanceProgresoPct');
        if (fill)  fill.style.width = pct + '%';
        if (pctEl) pctEl.textContent = pct + '%';
    }

    // ─── Filtros de tabla ────────────────────────────────
    function listaFiltrada() {
        var q      = document.getElementById('avanceBusqueda').value.toLowerCase().trim();
        var estado = document.getElementById('avanceFiltroEstado').value;

        return todosMerc.filter(function (m) {
            if (q && (m.mercaderista || '').toLowerCase().indexOf(q) === -1) return false;
            if (estado === 'con_vencidos' && !(parseInt(m.vencidos) > 0))    return false;
            if (estado === 'al_dia'       && parseInt(m.vencidos) > 0)       return false;
            if (estado === 'sin_iniciar'  && parseInt(m.completados) > 0)    return false;
            return true;
        });
    }

    // ─── Tabla de mercaderistas ───────────────────────────
    function renderTabla() {
        var lista = listaFiltrada();

        if (!lista.length) {
            document.getElementById('avanceTbody').innerHTML =
                '<tr><td colspan="7" class="avance-vacio">'
                + (todosMerc.length ? 'Sin resultados para el filtro aplicado.' : 'Sin agendamientos registrados aún.')
                + '</td></tr>';
            return;
        }

        var html = '';
        lista.forEach(function (m) {
            var total  = parseInt(m.total_pdvs)  || 0;
            var comp   = parseInt(m.completados)  || 0;
            var curso  = parseInt(m.en_curso)     || 0;
            var venc   = parseInt(m.vencidos)     || 0;
            var pforma = parseInt(m.con_proforma) || 0;
            var pct    = parseInt(m.pct_avance)   || 0;

            var fillClass = pct >= 70 ? 'fill-alto' : pct >= 40 ? 'fill-medio' : 'fill-bajo';
            var inicial   = (m.mercaderista || '?').charAt(0).toUpperCase();

            html += '<tr>'
                + '<td><div class="avance-merc-nombre">'
                + '<div class="avance-merc-avatar">' + inicial + '</div>'
                + esc(m.mercaderista || '—') + '</div></td>'
                + '<td class="is-center"><strong>' + total + '</strong></td>'
                + '<td class="is-center"><span class="avance-num-ok">' + comp + '</span></td>'
                + '<td class="is-center"><span class="' + (curso > 0 ? 'avance-num-warn' : 'avance-num-gray') + '">' + curso + '</span></td>'
                + '<td class="is-center"><span class="' + (venc  > 0 ? 'avance-num-bad'  : 'avance-num-gray') + '">' + venc  + '</span></td>'
                + '<td class="is-center"><span class="' + (pforma> 0 ? 'avance-num-ok'   : 'avance-num-gray') + '">' + pforma+ '</span></td>'
                + '<td><div class="avance-bar-wrap">'
                + '<div class="avance-bar-track"><div class="avance-bar-fill ' + fillClass + '" style="width:' + pct + '%"></div></div>'
                + '<span class="avance-bar-pct">' + pct + '%</span></div></td>'
                + '</tr>';
        });

        document.getElementById('avanceTbody').innerHTML = html;
    }

    function set(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ─── Eventos ─────────────────────────────────────────
    document.getElementById('avanceBusqueda').addEventListener('input', renderTabla);
    document.getElementById('avanceFiltroEstado').addEventListener('change', renderTabla);

    // Recargar cuando el tab queda visible
    var tabLink = document.querySelector('a[href="#tab-avance"]');
    if (tabLink) { tabLink.addEventListener('click', function () { setTimeout(cargar, 50); }); }

    window.AvanceRecargar = cargar;
    cargar();
})();
