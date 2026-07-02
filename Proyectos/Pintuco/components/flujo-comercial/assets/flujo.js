(function () {
    'use strict';

    var app          = document.getElementById('flujoComercialApp');
    var GETTERS_BASE = app.dataset.gettersBase;

    var allRows    = [];   // todos los ciclos de proforma (todos los agendamientos)
    var pipeline   = [];   // un registro por agendamiento (último ciclo)
    var faseActiva = 1;
    var promActivo = null;

    // ── Helpers ──────────────────────────────────────────────────────
    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    function soloFecha(v) { return v ? String(v).split(' ')[0] : null; }
    function diasDesde(fechaStr) {
        var f = soloFecha(fechaStr);
        if (!f || f === '0000-00-00') return null;
        return Math.floor((new Date() - new Date(f + 'T00:00:00')) / 86400000);
    }
    function fmtDias(d) { return d === null ? '—' : (d < 0 ? '0d' : d + 'd'); }
    function clsDias(d)  {
        if (d === null) return '';
        if (d <= 7) return 'is-verde';
        if (d <= 20) return 'is-ambar';
        return 'is-rojo';
    }
    function fmtMonto(v) {
        var n = parseFloat(v) || 0;
        if (n === 0) return '—';
        return '$' + n.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

    // Fecha de referencia para calcular días en la fase actual
    function refFechaFase(p) {
        var f = getFase(p);
        if (f >= 4) return p.proforma_fecha_registro || p.contacto_fecha_registro;
        return p.contacto_fecha_registro;
    }

    // ── Filtros ───────────────────────────────────────────────────────
    function rowsFiltradas() {
        var prom = document.getElementById('flujoFiltroPromotor').value.toLowerCase().trim();
        var bus  = document.getElementById('flujoBusqueda').value.toLowerCase().trim();
        return pipeline.filter(function (p) {
            if (prom && (p.usuario || '').toLowerCase().indexOf(prom) === -1) return false;
            if (bus) {
                var hay = [(p.pdv||''), (p.empresa||''), (p.codigo_pdv||'')].join(' ').toLowerCase();
                if (hay.indexOf(bus) === -1) return false;
            }
            return true;
        });
    }

    // ── Pipeline — tarjetas de fase ──────────────────────────────────
    var FASES_META = [
        { fase: 1, label: 'Contacto' },
        { fase: 2, label: 'Agendado' },
        { fase: 3, label: 'Proforma' },
        { fase: 4, label: 'Negociación' },
        { fase: 5, label: 'Facturado' },
    ];

    function renderFaseCards(rows) {
        var container = document.getElementById('flujoFaseCards');
        var conteos = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
        rows.forEach(function (p) {
            var f = getFase(p);
            conteos[f] = (conteos[f] || 0) + 1;
        });
        container.innerHTML = FASES_META.map(function (m) {
            var activo = m.fase === faseActiva ? 'is-activa' : '';
            return '<div class="flujo-fase-card ' + activo + '" data-fase="' + m.fase + '">'
                + '<div class="flujo-fase-card-num">Fase ' + m.fase + '</div>'
                + '<div class="flujo-fase-card-label">' + m.label + '</div>'
                + '<div class="flujo-fase-card-count">' + (conteos[m.fase] || 0) + '</div>'
                + '</div>';
        }).join('');

        container.querySelectorAll('.flujo-fase-card').forEach(function (el) {
            el.addEventListener('click', function () {
                faseActiva = parseInt(el.dataset.fase, 10);
                renderFaseCards(rows);
                renderDetalleFase(rows);
            });
        });
    }

    // ── Acordeón por promotor ─────────────────────────────────────────
    function agruparPorPromotor(rows) {
        var grupos = {}, orden = [];
        rows.forEach(function (p) {
            var u = p.usuario || '(sin asignar)';
            if (!grupos[u]) { grupos[u] = []; orden.push(u); }
            grupos[u].push(p);
        });
        return { grupos: grupos, orden: orden };
    }

    function htmlGrupoActivos(agrup) {
        if (!agrup.orden.length) {
            return '<div class="flujo-grupo-vacio">No hay registros en esta fase.</div>';
        }
        return agrup.orden.map(function (u) {
            var items = agrup.grupos[u];
            var filas = items.map(function (p) {
                var dias = diasDesde(refFechaFase(p));
                return '<div class="flujo-item-fila">'
                    + '<span class="flujo-item-pdv">' + esc(p.pdv || p.codigo_pdv || '—') + '</span>'
                    + '<span class="flujo-item-empresa">' + esc(p.empresa || '') + '</span>'
                    + '<span class="flujo-item-dias ' + clsDias(dias) + '">' + fmtDias(dias) + '</span>'
                    + '</div>';
            }).join('');
            return '<div class="flujo-grupo">'
                + '<div class="flujo-grupo-header" data-toggle-grupo>'
                +   '<i class="glyphicon glyphicon-chevron-down flujo-grupo-chevron is-girado"></i>'
                +   '<span class="flujo-grupo-nombre">' + esc(u) + '</span>'
                +   '<span class="flujo-grupo-badge">' + items.length + '</span>'
                + '</div>'
                + '<div class="flujo-grupo-body is-cerrado">' + filas + '</div>'
                + '</div>';
        }).join('');
    }

    function htmlAusentes(ausentes) {
        if (!ausentes.length) return '';
        var ag = agruparPorPromotor(ausentes);
        var filas = ag.orden.map(function (u) {
            var items = ag.grupos[u];
            var pdvsStr = items.slice(0, 4).map(function (p) {
                return esc(p.pdv || p.codigo_pdv || '—');
            }).join(' · ');
            if (items.length > 4) pdvsStr += ' · +' + (items.length - 4) + ' más';
            return '<div class="flujo-ausente-fila">'
                + '<span class="flujo-ausente-prom">' + esc(u) + ' (' + items.length + ')</span>'
                + '<span class="flujo-ausente-pdvs">' + pdvsStr + '</span>'
                + '</div>';
        }).join('');
        return '<div class="flujo-ausentes-bloque">'
            + '<div class="flujo-ausentes-titulo">Aún no han llegado (' + ausentes.length + ')</div>'
            + filas
            + '</div>';
    }

    function activarToggleGrupos(container) {
        container.querySelectorAll('[data-toggle-grupo]').forEach(function (hdr) {
            hdr.addEventListener('click', function () {
                var body    = hdr.parentElement.querySelector('.flujo-grupo-body');
                var chevron = hdr.querySelector('.flujo-grupo-chevron');
                var cerrado = body.classList.toggle('is-cerrado');
                chevron.classList.toggle('is-girado', cerrado);
            });
        });
    }

    // ── Detalle de la fase seleccionada ──────────────────────────────
    function renderDetalleFase(rows) {
        var container = document.getElementById('flujoFaseDetalle');
        var enFase   = rows.filter(function (p) { return getFase(p) === faseActiva; });
        var ausentes = faseActiva === 1 ? [] : rows.filter(function (p) { return getFase(p) < faseActiva; });

        var meta = { fase: faseActiva, label: '' };
        FASES_META.forEach(function (m) { if (m.fase === faseActiva) meta = m; });

        var agEnFase = agruparPorPromotor(enFase);

        var extraHtml = faseActiva === 5
            ? htmlConversionExtra(rows)
            : htmlAusentes(ausentes);

        container.innerHTML =
            '<div class="flujo-detalle-header">'
            +   '<span>Fase ' + faseActiva + ' — ' + meta.label + '</span>'
            +   '<span class="flujo-detalle-count">' + enFase.length + ' en esta fase</span>'
            + '</div>'
            + '<div class="flujo-activos-bloque">'
            + htmlGrupoActivos(agEnFase)
            + '</div>'
            + extraHtml;

        activarToggleGrupos(container);
    }

    function htmlConversionExtra(rows) {
        var total = rows.length;
        var fase5 = rows.filter(function (p) { return getFase(p) === 5; }).length;
        var pct   = total > 0 ? Math.round(fase5 / total * 100) : 0;
        return '<div class="flujo-conversion-badge">'
            + '<span class="flujo-conversion-label">Tasa de conversión</span>'
            + '<span class="flujo-conversion-pct">' + pct + '%</span>'
            + '<span class="flujo-conversion-desc">' + fase5 + ' de ' + total + ' PDVs facturados</span>'
            + '</div>';
    }

    function renderPipeline() {
        var rows = rowsFiltradas();
        renderFaseCards(rows);
        renderDetalleFase(rows);
    }

    // ── Por Promotor — lista izquierda ────────────────────────────────
    function renderPromoLista() {
        var container = document.getElementById('flujoPromoLista');

        // Contar PDVs por promotor desde pipeline (dedup)
        var mapaProms = {};
        pipeline.forEach(function (p) {
            var u = p.usuario || '(sin asignar)';
            if (!mapaProms[u]) mapaProms[u] = { usuario: u, total: 0, monto_facturado: 0 };
            mapaProms[u].total++;
        });

        // Calcular monto facturado desde allRows (todos los ciclos)
        allRows.forEach(function (p) {
            var u = p.usuario || '(sin asignar)';
            if (!mapaProms[u]) return;
            var m = parseFloat(p.monto_validado) || 0;
            if (m > 0 && (p.foto_factura || p.estado_proforma === 'aprobado')) {
                mapaProms[u].monto_facturado += m;
            }
        });

        var lista = Object.values(mapaProms).sort(function (a, b) { return b.total - a.total; });
        var q = (document.getElementById('flujoPromoSearch').value || '').toLowerCase().trim();
        var visible = q ? lista.filter(function (p) { return (p.usuario || '').toLowerCase().indexOf(q) !== -1; }) : lista;
        if (!visible.length) {
            container.innerHTML = '<div class="flujo-vacio">' + (q ? 'Sin resultados para "' + esc(q) + '".' : 'Sin datos.') + '</div>';
            return;
        }
        container.innerHTML = visible.map(function (p) {
            var activo = p.usuario === promActivo ? 'is-activo' : '';
            return '<div class="flujo-promo-item ' + activo + '" data-usuario="' + esc(p.usuario) + '">'
                + '<div class="flujo-promo-item-nombre">' + esc(p.usuario) + '</div>'
                + '<div class="flujo-promo-item-meta">' + p.total + ' PDV' + (p.total !== 1 ? 's' : '') + '</div>'
                + '<div class="flujo-promo-item-monto">' + fmtMonto(p.monto_facturado) + '</div>'
                + '</div>';
        }).join('');

        container.querySelectorAll('.flujo-promo-item').forEach(function (el) {
            el.addEventListener('click', function () {
                promActivo = el.dataset.usuario;
                renderPromoLista();
                renderPromoDetalle(promActivo);
            });
        });
    }

    // ── Por Promotor — panel derecho ──────────────────────────────────
    function renderPromoDetalle(usuario) {
        var container = document.getElementById('flujoPromoDetalle');
        if (!usuario) {
            container.innerHTML = '<div class="flujo-vacio">Selecciona un promotor.</div>';
            return;
        }

        // PDVs del promotor (pipeline dedup)
        var pdvs = pipeline.filter(function (p) { return (p.usuario || '') === usuario; });

        // Historial de montos: todos los ciclos del promotor con proforma (p.id != null)
        // Agrupados por agendamiento, ordenados por p.id ASC
        var ciclosPorAg = {};
        allRows.forEach(function (p) {
            if ((p.usuario || '') !== usuario || !p.id) return;
            var key = p.agendamiento_id;
            if (!ciclosPorAg[key]) ciclosPorAg[key] = [];
            ciclosPorAg[key].push(p);
        });
        Object.keys(ciclosPorAg).forEach(function (k) {
            ciclosPorAg[k].sort(function (a, b) { return (parseInt(a.id)||0) - (parseInt(b.id)||0); });
        });

        // Calcular totales de monto
        var totalNeg = 0, totalFact = 0;
        Object.values(ciclosPorAg).forEach(function (ciclos) {
            ciclos.forEach(function (c) {
                var m = parseFloat(c.monto_validado) || 0;
                if (m > 0) {
                    totalNeg += m;
                    if (c.foto_factura || c.estado_proforma === 'aprobado') totalFact += m;
                }
            });
        });

        // Tabla de PDVs del promotor
        var tablaPdvs = '<div class="flujo-promo-tabla-wrap"><table class="flujo-tabla">'
            + '<thead><tr><th>PDV / Empresa</th><th>Fase</th><th>Días en fase</th></tr></thead>'
            + '<tbody>'
            + (pdvs.length ? pdvs.map(function (p) {
                var f    = getFase(p);
                var dias = diasDesde(refFechaFase(p));
                return '<tr>'
                    + '<td><div class="flujo-tabla-pdv">' + esc(p.pdv || p.codigo_pdv || '—') + '</div>'
                    +     '<div class="flujo-tabla-empresa">' + esc(p.empresa || '') + '</div></td>'
                    + '<td><span class="flujo-fase-badge is-f' + f + '">Fase ' + f + (f === 5 ? ' ✓' : '') + '</span></td>'
                    + '<td class="' + clsDias(dias) + '">' + fmtDias(dias) + '</td>'
                    + '</tr>';
            }).join('') : '<tr><td colspan="3" class="flujo-vacio">Sin PDVs.</td></tr>')
            + '</tbody></table></div>';

        // Tabla de historial de montos
        var hayMontos = Object.keys(ciclosPorAg).length > 0;
        var tablaMontos = hayMontos ? (function () {
            var filas = '';
            Object.keys(ciclosPorAg).forEach(function (agId) {
                var ciclos = ciclosPorAg[agId];
                ciclos.forEach(function (c, i) {
                    var m       = parseFloat(c.monto_validado) || 0;
                    var esFinal = !!(c.foto_factura || c.estado_proforma === 'aprobado');
                    var estadoLabel = esFinal ? '✓ Facturado'
                        : (c.estado_proforma === 'rechazado' ? 'Rechazado'
                        : (c.estado_proforma === 'en_negociacion' ? 'Negociando'
                        : 'En revisión'));
                    filas += '<tr class="' + (esFinal ? 'is-facturado' : '') + '">'
                        + '<td>' + esc(c.pdv || c.codigo_pdv || '—') + '</td>'
                        + '<td style="text-align:center">' + (i + 1) + '</td>'
                        + '<td>' + (m > 0 ? fmtMonto(m) : '—') + '</td>'
                        + '<td>' + estadoLabel + '</td>'
                        + '</tr>';
                });
            });
            return '<div class="flujo-promo-tabla-wrap"><table class="flujo-tabla">'
                + '<thead><tr><th>PDV</th><th>Ciclo</th><th>Monto</th><th>Estado</th></tr></thead>'
                + '<tbody>' + filas
                + '<tr class="flujo-totales">'
                +   '<td colspan="2">Totales</td>'
                +   '<td colspan="2">'
                +     'Negociado: ' + fmtMonto(totalNeg) + ' &nbsp;·&nbsp; '
                +     '<strong>Facturado: ' + fmtMonto(totalFact) + '</strong>'
                +   '</td>'
                + '</tr>'
                + '</tbody></table></div>';
        }()) : '<div class="flujo-vacio" style="margin-top:8px">Sin historial de montos.</div>';

        container.innerHTML =
            '<div class="flujo-promo-header-d">'
            +   '<span class="flujo-promo-nombre-d">' + esc(usuario) + '</span>'
            +   '<span class="flujo-promo-stats-d">'
            +       pdvs.length + ' PDVs · ' + fmtMonto(totalFact) + ' facturado'
            +   '</span>'
            + '</div>'
            + '<div class="flujo-promo-seccion-titulo">PDVs en gestión</div>'
            + tablaPdvs
            + '<div class="flujo-promo-seccion-titulo" style="margin-top:16px">Historial de montos</div>'
            + tablaMontos;
    }

    // ── Tabs internos ─────────────────────────────────────────────────
    document.querySelectorAll('#flujoTabs a[data-flujo-tab]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var tab = a.dataset.flujoTab;
            document.querySelectorAll('#flujoTabs li').forEach(function (li) { li.classList.remove('active'); });
            a.parentElement.classList.add('active');
            document.querySelectorAll('.flujo-pane').forEach(function (p) { p.classList.remove('active'); });
            var pane = document.getElementById('flujoPane-' + tab);
            if (pane) pane.classList.add('active');
        });
    });

    // ── Filtros live ──────────────────────────────────────────────────
    document.getElementById('flujoFiltroPromotor').addEventListener('input', renderPipeline);
    document.getElementById('flujoBusqueda').addEventListener('input', renderPipeline);
    document.getElementById('flujoPromoSearch').addEventListener('input', renderPromoLista);
    document.getElementById('flujoActualizarProm').addEventListener('click', cargar);

    // ── Carga de datos ────────────────────────────────────────────────
    function cargar() {
        document.getElementById('flujoFaseCards').innerHTML   = '<div class="flujo-vacio">Cargando...</div>';
        document.getElementById('flujoFaseDetalle').innerHTML = '<div class="flujo-vacio">Cargando...</div>';
        document.getElementById('flujoPromoLista').innerHTML  = '<div class="flujo-vacio">Cargando...</div>';
        document.getElementById('flujoPromoDetalle').innerHTML = '<div class="flujo-vacio">Selecciona un promotor.</div>';

        fetch(GETTERS_BASE + 'proformas_listar.php')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                allRows  = d.data || [];
                pipeline = ultimosCiclos(allRows);
                renderPipeline();
                renderPromoLista();
                if (promActivo) renderPromoDetalle(promActivo);
            })
            .catch(function () {
                document.getElementById('flujoFaseDetalle').innerHTML =
                    '<div class="flujo-vacio">Error al cargar datos.</div>';
            });
    }

    document.getElementById('flujoActualizar').addEventListener('click', cargar);
    cargar();
})();
