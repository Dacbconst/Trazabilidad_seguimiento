(function () {
    'use strict';

    var app          = document.getElementById('estadoFlujoApp');
    var GETTERS_BASE = app.dataset.gettersBase;

    var allRows  = [];  // todos los ciclos, de todos los agendamientos
    var pipeline = [];  // 1 fila por agendamiento = su último ciclo

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
    // Umbral: verde <=3 días, ámbar 4-7, rojo 8+.
    function clsDias(d) {
        if (d === null) return '';
        if (d <= 3) return 'is-verde';
        if (d <= 7) return 'is-ambar';
        return 'is-rojo';
    }

    // ── Lógica de fases (igual que proforma.js) ───────────────────────
    function getFase(p) {
        if (p.foto_factura || p.estado_proforma === 'aprobado') return 5;
        if (p.estado_proforma === 'rechazado') return 4;
        if (p.id) return 4;
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

    // PDV o empresa: filtro del buscador de arriba.
    function matchPdv(p, q) {
        var hay = [(p.pdv || ''), (p.empresa || ''), (p.codigo_pdv || '')].join(' ').toLowerCase();
        return hay.indexOf(q) !== -1;
    }

    function pipelineFiltrado() {
        var bus  = document.getElementById('efBusqueda').value.toLowerCase().trim();
        var prom = document.getElementById('efFiltroPromotor').value.toLowerCase().trim();
        return pipeline.filter(function (p) {
            if (bus && !matchPdv(p, bus)) return false;
            if (prom && (p.usuario || '').toLowerCase().indexOf(prom) === -1) return false;
            return true;
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
                construirEsqueletoKanban();
                renderKanban(pipelineFiltrado());
            })
            .catch(function () {
                document.getElementById('efKanban').innerHTML = '<div class="ef-vacio">Error al cargar datos.</div>';
            });
    }

    // ── Eventos ────────────────────────────────────────────────────────
    document.getElementById('efBusqueda').addEventListener('input', function () { renderKanban(pipelineFiltrado()); });
    document.getElementById('efFiltroPromotor').addEventListener('input', function () { renderKanban(pipelineFiltrado()); });
    document.getElementById('efActualizar').addEventListener('click', cargar);

    window.EstadoFlujoRecargar = cargar;
    cargar();
})();
