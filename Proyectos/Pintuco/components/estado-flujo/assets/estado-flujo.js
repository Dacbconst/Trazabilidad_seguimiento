(function () {
    'use strict';

    var app          = document.getElementById('estadoFlujoApp');
    var GETTERS_BASE = app.dataset.gettersBase;
    var FOTO_BASE    = 'https://luckyecuadorweb.blob.core.windows.net/app/AppPintuco/Inserts/';

    var allRows           = [];   // todos los ciclos, de todos los agendamientos
    var pipeline           = [];  // 1 fila por agendamiento = su último ciclo
    var porAgendamiento    = {};  // { agendamiento_id: [ciclos ASC por id] }
    var tabActiva          = 'fase';
    var promActivo         = null;
    var highlightedAgId    = null;
    var auditoriaAbierta   = null;

    // ── Helpers ──────────────────────────────────────────────────────
    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    function soloFecha(v) { return v ? String(v).split(' ')[0] : null; }
    function formatFecha(v) {
        var iso = soloFecha(v);
        if (!iso || iso === '0000-00-00') return '—';
        var p = iso.split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso;
    }
    function formatFechaHora(v) {
        if (!v) return '—';
        var pp = String(v).split(' ');
        return formatFecha(pp[0]) + (pp[1] ? ' ' + pp[1].slice(0, 5) : '');
    }
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

    function agruparPorAgendamiento(rows) {
        var mapa = {};
        rows.forEach(function (p) {
            var key = p.agendamiento_id;
            if (!mapa[key]) mapa[key] = [];
            mapa[key].push(p);
        });
        Object.keys(mapa).forEach(function (k) {
            mapa[k].sort(function (a, b) { return (parseInt(a.id, 10) || 0) - (parseInt(b.id, 10) || 0); });
        });
        return mapa;
    }

    // Fecha de referencia para calcular días en la fase actual.
    function refFechaFase(p) {
        var f = getFase(p);
        if (f >= 4) return p.proforma_fecha_registro || p.contacto_fecha_registro;
        return p.contacto_fecha_registro;
    }

    // Ciclos que corresponden a una proforma real (p.id existe) — descarta
    // la fila "vacía" que trae LEFT JOIN cuando el agendamiento aún no tiene
    // ninguna proforma subida.
    function ciclosRealesDe(agendamientoId) {
        var ciclos = porAgendamiento[agendamientoId] || [];
        return ciclos.filter(function (c) { return !!c.id; });
    }

    // Regla de negocio: la ÚLTIMA proforma CON MONTO REGISTRADO (mayor id
    // entre las que ya tienen monto_validado) es la que cuenta para
    // cualquier suma/total, sin importar su estado — cuenta aunque esté
    // rechazada. OJO: no basta con "el ciclo de mayor id" a secas — cada
    // "Guardar" cierra la ronda actual con su monto y abre una ronda nueva
    // VACÍA esperando la próxima foto (ver update_proforma.php), así que la
    // fila de mayor id casi siempre no tiene monto todavía. Por eso se
    // filtra por monto_validado antes de tomar la última.
    function ultimaProformaDe(agendamientoId) {
        var conMonto = ciclosRealesDe(agendamientoId).filter(function (c) { return !!c.monto_validado; });
        return conMonto.length ? conMonto[conMonto.length - 1] : null;
    }

    function totalAcumuladoPromotor(usuario) {
        var total = 0;
        Object.keys(porAgendamiento).forEach(function (agId) {
            var ciclos = porAgendamiento[agId];
            if (!ciclos.length) return;
            var u = (ciclos[0].usuario || '(sin asignar)');
            if (u !== usuario) return;
            var ultima = ultimaProformaDe(agId);
            total += ultima ? (parseFloat(ultima.monto_validado) || 0) : 0;
        });
        return total;
    }

    function ultimoCicloConEvidencia(ciclos) {
        var candidatos = ciclos.filter(function (c) { return !!c.evidencia; })
            .sort(function (a, b) { return (parseInt(b.id, 10) || 0) - (parseInt(a.id, 10) || 0); });
        return candidatos[0] || null;
    }
    function ultimoCicloConFactura(ciclos) {
        var candidatos = ciclos.filter(function (c) { return !!c.foto_factura; })
            .sort(function (a, b) { return (parseInt(b.id, 10) || 0) - (parseInt(a.id, 10) || 0); });
        return candidatos[0] || null;
    }

    // Mapea el valor real de estado_proforma a la etiqueta que ve el analista.
    function estadoVisual(estado) {
        if (estado === 'rechazado') return { label: 'Rechazada', cls: 'is-rechazada' };
        if (estado === 'aprobado')  return { label: 'Aprobada',  cls: 'is-aprobada'  };
        return { label: 'Enviada', cls: 'is-enviada' }; // pendiente | en_proceso | en_negociacion | realizado
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

    function pipelineFiltrado() {
        var bus  = document.getElementById('efBusqueda').value.toLowerCase().trim();
        var prom = document.getElementById('efFiltroPromotor').value.toLowerCase().trim();
        return pipeline.filter(function (p) {
            if (bus) {
                var hay = [(p.pdv || ''), (p.empresa || ''), (p.codigo_pdv || '')].join(' ').toLowerCase();
                if (hay.indexOf(bus) === -1) return false;
            }
            if (prom && (p.usuario || '').toLowerCase().indexOf(prom) === -1) return false;
            return true;
        });
    }

    // ── Navegación cruzada: click en tarjeta → tab Por Promotor + scroll + highlight ──
    function irAPromotorDesdeTarjeta(agendamientoId) {
        var fila = pipeline.filter(function (p) { return String(p.agendamiento_id) === String(agendamientoId); })[0];
        if (!fila) return;

        cambiarTab('promotor');
        promActivo = fila.usuario || '(sin asignar)';
        renderPromoLista();
        renderPromoDetalle(promActivo);
        highlightedAgId = agendamientoId;

        // requestAnimationFrame: da tiempo a que el layout del pane recién
        // vuelto visible se asiente antes de medir con scrollIntoView.
        requestAnimationFrame(function () {
            var row = document.querySelector('.ef-tabla-prom tr[data-agendamiento-id="' + agendamientoId + '"]');
            if (!row) return;
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.classList.add('is-highlight');
            setTimeout(function () {
                row.classList.remove('is-highlight');
                if (String(highlightedAgId) === String(agendamientoId)) highlightedAgId = null;
            }, 1800);
        });
    }

    // ── Tabs internos ─────────────────────────────────────────────────
    function cambiarTab(tab) {
        tabActiva = tab;
        document.querySelectorAll('#efTabs .ef-tab-btn').forEach(function (a) { a.classList.remove('active'); });
        var link = document.querySelector('#efTabs a[data-ef-tab="' + tab + '"]');
        if (link) link.classList.add('active');
        document.querySelectorAll('.ef-pane').forEach(function (p) { p.classList.remove('active'); });
        var pane = document.getElementById('efPane-' + tab);
        if (pane) pane.classList.add('active');
    }

    // ── Por Promotor — lista izquierda ────────────────────────────────
    function construirMapaPromotores() {
        var mapa = {};
        pipeline.forEach(function (p) {
            var u = p.usuario || '(sin asignar)';
            if (!mapa[u]) mapa[u] = { usuario: u, total: 0 };
            mapa[u].total++;
        });
        Object.keys(mapa).forEach(function (u) {
            mapa[u].montoAcumulado = totalAcumuladoPromotor(u);
        });
        return mapa;
    }

    function renderPromoLista() {
        var container = document.getElementById('efPromoLista');
        var mapa = construirMapaPromotores();
        var lista = Object.keys(mapa).map(function (k) { return mapa[k]; })
            .sort(function (a, b) { return b.total - a.total; });
        var q = (document.getElementById('efPromoSearch').value || '').toLowerCase().trim();
        var visible = q ? lista.filter(function (p) { return p.usuario.toLowerCase().indexOf(q) !== -1; }) : lista;
        if (!visible.length) {
            container.innerHTML = '<div class="ef-vacio">' + (q ? 'Sin resultados para "' + esc(q) + '".' : 'Sin datos.') + '</div>';
            return;
        }
        container.innerHTML = visible.map(function (p) {
            var activo = p.usuario === promActivo ? 'is-activo' : '';
            return '<div class="ef-promo-item ' + activo + '" data-usuario="' + esc(p.usuario) + '">'
                + '<div class="ef-promo-item-nombre">' + esc(p.usuario) + '</div>'
                + '<div class="ef-promo-item-meta">'
                +   '<span>' + p.total + ' agendado' + (p.total !== 1 ? 's' : '') + '</span>'
                +   '<span class="ef-promo-item-monto">' + fmtMonto(p.montoAcumulado) + '</span>'
                + '</div>'
                + '</div>';
        }).join('');
    }

    // ── Por Promotor — panel derecho ──────────────────────────────────
    function renderPromoDetalle(usuario) {
        var container = document.getElementById('efPromoDetalle');
        if (!usuario) {
            container.innerHTML = '<div class="ef-vacio">Selecciona un promotor.</div>';
            return;
        }

        var pdvs = pipeline.filter(function (p) { return (p.usuario || '(sin asignar)') === usuario; });
        var conteosFase = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
        pdvs.forEach(function (p) { conteosFase[getFase(p)]++; });
        var total = totalAcumuladoPromotor(usuario);

        var badges = FASES_META.map(function (m) {
            return '<div class="ef-fase-badge-mini"><span class="ef-fase-badge-dot"></span>F' + m.fase + ': ' + conteosFase[m.fase] + '</div>';
        }).join('');

        var filas = pdvs.map(function (p) {
            var f = getFase(p);
            var dias = diasDesde(refFechaFase(p));
            var vigente = ultimaProformaDe(p.agendamiento_id);
            var monto = vigente ? fmtMonto(vigente.monto_validado) : '—';
            var hl = String(p.agendamiento_id) === String(highlightedAgId) ? 'is-highlight' : '';
            return '<tr data-agendamiento-id="' + esc(p.agendamiento_id) + '" class="' + hl + '">'
                + '<td><div class="ef-tabla-empresa">' + esc(p.empresa || '—') + '</div>'
                +     '<div class="ef-tabla-pdv">' + esc(p.pdv || p.codigo_pdv || '') + '</div></td>'
                + '<td><span class="ef-fase-badge is-f' + f + '">Fase ' + f + '</span></td>'
                + '<td class="' + clsDias(dias) + '">' + fmtDias(dias) + '</td>'
                + '<td class="ef-monto">' + monto + '</td>'
                + '<td><button type="button" class="ef-btn-auditar" data-agendamiento-id="' + esc(p.agendamiento_id) + '">Auditar</button></td>'
                + '</tr>';
        }).join('');

        container.innerHTML =
            '<div class="ef-promo-header-d">'
            +   '<div class="ef-promo-header-left">'
            +     '<div class="ef-promo-avatar">' + esc((usuario || '?').charAt(0).toUpperCase()) + '</div>'
            +     '<div>'
            +       '<div class="ef-promo-nombre-d">' + esc(usuario) + '</div>'
            +       '<div class="ef-promo-stats-d">' + pdvs.length + ' agendado' + (pdvs.length !== 1 ? 's' : '') + '</div>'
            +     '</div>'
            +   '</div>'
            +   '<div class="ef-promo-header-right">'
            +     '<div class="ef-promo-total-label">Total negociado acumulado</div>'
            +     '<div class="ef-promo-total-valor">' + fmtMonto(total) + '</div>'
            +     '<div class="ef-promo-total-caption">Suma de la última proforma de cada agendamiento</div>'
            +   '</div>'
            + '</div>'
            + '<div class="ef-promo-badges">' + badges + '</div>'
            + '<div class="ef-promo-tabla-wrap"><table class="ef-tabla-prom">'
            +   '<thead><tr><th>Empresa / PDV</th><th>Fase</th><th>Días</th><th>Monto vigente</th><th></th></tr></thead>'
            +   '<tbody>' + (filas || '<tr><td colspan="5" class="ef-vacio">Sin agendamientos.</td></tr>') + '</tbody>'
            + '</table></div>';
    }

    // ── Panel de auditoría (slide-over, 100% lectura) ─────────────────
    function abrirAuditoria(agendamientoId) {
        var p = pipeline.filter(function (x) { return String(x.agendamiento_id) === String(agendamientoId); })[0];
        if (!p) return;
        var ciclos = porAgendamiento[agendamientoId] || [];
        auditoriaAbierta = agendamientoId;

        var f = getFase(p);
        var dias = diasDesde(refFechaFase(p));
        var meta = FASES_META[f - 1];

        document.getElementById('efAudNombre').textContent = p.pdv || p.codigo_pdv || '—';
        document.getElementById('efAudSub').textContent = (p.empresa || '—') + ' · Promotor: ' + (p.usuario || '(sin asignar)');
        var faseBadge = document.getElementById('efAudFaseBadge');
        faseBadge.className = 'ef-fase-badge is-f' + f;
        faseBadge.textContent = 'Fase ' + f + ' · ' + meta.label;
        document.getElementById('efAudDias').textContent = fmtDias(dias) + ' en esta fase';

        renderAuditoriaTimeline(p, ciclos);
        renderAuditoriaHistorial(ciclos);

        document.getElementById('efAuditoriaOverlay').classList.add('is-abierto');
    }

    function cerrarAuditoria() {
        document.getElementById('efAuditoriaOverlay').classList.remove('is-abierto');
        auditoriaAbierta = null;
    }

    function renderAuditoriaTimeline(p, ciclos) {
        var fase = getFase(p);
        var ultimaEvidencia = ultimoCicloConEvidencia(ciclos);
        var ultimaFactura   = ultimoCicloConFactura(ciclos);

        // Rondas de negociación (Fase 4): una fila por cada ciclo que YA
        // tiene monto_validado, con su propia foto de evidencia (la proforma
        // que se cotizó en esa ronda) — mismo criterio que se corrigió en
        // proforma.js: antes solo se mostraba la última ronda.
        // Orden DESCENDENTE (más reciente primero): la primera fila es la
        // misma que "monto vigente"/"cuenta para el total" en el resto del
        // panel, y las demás rondas quedan después, como contexto histórico.
        var ciclosConMonto = ciclos
            .filter(function (c) { return !!c.monto_validado; })
            .sort(function (a, b) { return (parseInt(b.id, 10) || 0) - (parseInt(a.id, 10) || 0); });
        var ultimoConMonto = ciclosConMonto[0] || null; // la primera ya es la más reciente
        var rondasNegociacion = ciclosConMonto.map(function (c) {
            return {
                fecha: formatFechaHora(c.fecha_auditoria),
                detalle: 'Monto cotizado: ' + fmtMonto(c.monto_validado),
                foto: c.evidencia ? (FOTO_BASE + c.evidencia) : null
            };
        });

        var defs = [
            { num: 1, label: 'Contacto',
              filas: [{ fecha: formatFechaHora(p.contacto_fecha_registro),
                        detalle: 'Primer contacto registrado con el punto de venta.' }] },
            { num: 2, label: 'Agendado',
              filas: [{ fecha: formatFecha(p.fecha_agendamiento) + (p.hora ? ' · ' + String(p.hora).slice(0, 5) : ''),
                        detalle: 'Visita técnica agendada' + (p.tecnico ? ' con ' + p.tecnico : '') + '.' }] },
            { num: 3, label: 'Proforma',
              filas: [{ fecha: ultimaEvidencia ? formatFechaHora(ultimaEvidencia.proforma_fecha_registro) : '—',
                        detalle: 'Proforma recibida del promotor.',
                        foto: ultimaEvidencia && ultimaEvidencia.evidencia ? (FOTO_BASE + ultimaEvidencia.evidencia) : null }] },
            { num: 4, label: 'Negociación',
              filas: rondasNegociacion.length ? rondasNegociacion
                  : [{ fecha: '—', detalle: 'En negociación de monto y condiciones finales.' }] },
            { num: 5, label: 'Facturado',
              // El monto es el mismo que "monto vigente"/"cuenta para el
              // total" en el resto del panel (última proforma con monto
              // validado) — no un monto propio de la fila de factura, que
              // casi nunca tiene uno. Título distinto a Fase 4 ("Monto
              // Aprobado" en vez de "Monto cotizado") porque acá ya es el
              // monto final, no una ronda más de negociación.
              filas: [{ fecha: ultimaFactura ? formatFechaHora(ultimaFactura.fecha_auditoria || ultimaFactura.proforma_fecha_registro) : '—',
                        detalle: ultimoConMonto
                            ? 'Monto Aprobado: ' + fmtMonto(ultimoConMonto.monto_validado)
                            : 'Proforma final aprobada y factura emitida.',
                        // foto_factura ya incluye el prefijo "Factura/" en el
                        // valor guardado por el móvil — agregarlo de nuevo
                        // duplica la carpeta y da 404 (confirmado 2026-07-03
                        // contra el blob real).
                        foto: ultimaFactura && ultimaFactura.foto_factura ? (FOTO_BASE + ultimaFactura.foto_factura) : null }] },
        ];

        var visibles = defs.slice(0, fase); // solo fases ya recorridas (1..fase actual)

        var html = visibles.map(function (d, i) {
            var esUltimo = i === visibles.length - 1;
            // Cada fila: texto (fecha+detalle) a la izquierda, foto a la
            // derecha A LA MISMA ALTURA de esa fila — no debajo del bloque
            // completo, para que quede claro a cuál envío corresponde.
            var filasHtml = d.filas.map(function (f) {
                return '<div class="ef-tl-row">'
                    + '<div class="ef-tl-row-texto">'
                    +   '<div class="ef-tl-fecha">' + esc(f.fecha) + '</div>'
                    +   (f.detalle ? '<div class="ef-tl-detalle">' + esc(f.detalle) + '</div>' : '')
                    + '</div>'
                    + (f.foto ? '<img class="ef-tl-thumb" src="' + esc(f.foto) + '" alt="Foto evidencia">' : '')
                    + '</div>';
            }).join('');
            return '<div class="ef-tl-item">'
                + '<div class="ef-tl-marker">'
                +   '<span class="ef-tl-dot"></span>'
                +   (esUltimo ? '' : '<span class="ef-tl-line"></span>')
                + '</div>'
                + '<div class="ef-tl-content">'
                +   '<div class="ef-tl-titulo">Fase ' + d.num + ' — ' + esc(d.label) + '</div>'
                +   filasHtml
                + '</div>'
                + '</div>';
        }).join('');

        document.getElementById('efAudTimeline').innerHTML = html;
    }

    function renderAuditoriaHistorial(ciclos) {
        var reales = ciclos.filter(function (c) { return !!c.id; });
        if (!reales.length) {
            document.getElementById('efAudHistorial').innerHTML = '<div class="ef-vacio">Sin proformas registradas.</div>';
            return;
        }
        // El badge "cuenta para el total" debe marcar la MISMA fila que
        // ultimaProformaDe() (última con monto_validado, no simplemente la
        // de mayor id — esa suele ser la ronda vacía recién abierta).
        var conMonto = reales.filter(function (c) { return !!c.monto_validado; });
        var ultimaId = conMonto.length ? (parseInt(conMonto[conMonto.length - 1].id, 10) || 0) : 0;
        var html = reales.map(function (c, i) {
            var est = estadoVisual(c.estado_proforma);
            var esVigente = (parseInt(c.id, 10) || 0) === ultimaId;
            return '<div class="ef-hist-card ' + (esVigente ? 'is-vigente' : '') + '">'
                + '<div class="ef-hist-top">'
                +   '<span class="ef-hist-ciclo">Ciclo ' + (i + 1) + '</span>'
                +   '<span class="ef-estado-badge ' + est.cls + '">' + est.label + '</span>'
                + '</div>'
                + '<div class="ef-hist-fecha">' + esc(formatFecha(c.fecha_proforma)) + '</div>'
                + '<div class="ef-hist-montorow">'
                +   '<span class="ef-hist-monto">' + fmtMonto(c.monto_validado) + '</span>'
                +   (esVigente ? '<span class="ef-badge-vigente">✓ Cuenta para el total</span>' : '')
                + '</div>'
                + (c.caracteristica_visita
                    ? '<div class="ef-hist-caract"><strong>Características:</strong> ' + esc(c.caracteristica_visita) + '</div>'
                    : '')
                + '</div>';
        }).join('');
        document.getElementById('efAudHistorial').innerHTML = html;
    }

    // ── Lightbox de foto ────────────────────────────────────────────
    function abrirLightbox(src) {
        document.getElementById('efLightboxImg').src = src;
        document.getElementById('efLightbox').classList.add('is-visible');
    }
    function cerrarLightbox() {
        document.getElementById('efLightbox').classList.remove('is-visible');
        document.getElementById('efLightboxImg').src = '';
    }

    // ── Carga de datos ────────────────────────────────────────────────
    function cargar() {
        document.getElementById('efKanban').innerHTML = '<div class="ef-vacio">Cargando...</div>';
        document.getElementById('efPromoLista').innerHTML = '<div class="ef-vacio">Cargando...</div>';
        document.getElementById('efPromoDetalle').innerHTML = '<div class="ef-vacio">Selecciona un promotor.</div>';

        return fetch(GETTERS_BASE + 'proformas_listar.php')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                allRows = d.data || [];
                pipeline = ultimosCiclos(allRows);
                porAgendamiento = agruparPorAgendamiento(allRows);
                construirEsqueletoKanban();
                renderKanban(pipelineFiltrado());
                renderPromoLista();
                if (promActivo) renderPromoDetalle(promActivo);
            })
            .catch(function () {
                document.getElementById('efKanban').innerHTML = '<div class="ef-vacio">Error al cargar datos.</div>';
            });
    }

    // ── Eventos (delegación, un solo listener por contenedor) ─────────
    document.querySelectorAll('#efTabs a[data-ef-tab]').forEach(function (a) {
        a.addEventListener('click', function (e) { e.preventDefault(); cambiarTab(a.dataset.efTab); });
    });

    document.getElementById('efBusqueda').addEventListener('input', function () { renderKanban(pipelineFiltrado()); });
    document.getElementById('efFiltroPromotor').addEventListener('input', function () { renderKanban(pipelineFiltrado()); });
    document.getElementById('efPromoSearch').addEventListener('input', renderPromoLista);

    document.getElementById('efKanban').addEventListener('click', function (e) {
        var card = e.target.closest('.ef-card');
        if (!card) return;
        irAPromotorDesdeTarjeta(card.dataset.agendamientoId);
    });

    document.getElementById('efPromoLista').addEventListener('click', function (e) {
        var item = e.target.closest('.ef-promo-item');
        if (!item) return;
        promActivo = item.dataset.usuario;
        renderPromoLista();
        renderPromoDetalle(promActivo);
    });

    document.getElementById('efPromoDetalle').addEventListener('click', function (e) {
        var btn = e.target.closest('.ef-btn-auditar');
        if (!btn) return;
        abrirAuditoria(btn.dataset.agendamientoId);
    });

    document.getElementById('efAudClose').addEventListener('click', cerrarAuditoria);
    document.getElementById('efAuditoriaOverlay').addEventListener('click', function (e) {
        if (e.target.id === 'efAuditoriaOverlay') cerrarAuditoria();
    });
    document.getElementById('efAudTimeline').addEventListener('click', function (e) {
        var img = e.target.closest('.ef-tl-thumb');
        if (!img) return;
        abrirLightbox(img.src);
    });
    document.getElementById('efLightbox').addEventListener('click', cerrarLightbox);
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('efLightbox').classList.contains('is-visible')) { cerrarLightbox(); return; }
        if (auditoriaAbierta) cerrarAuditoria();
    });

    document.getElementById('efActualizar').addEventListener('click', cargar);
    document.getElementById('efActualizarProm').addEventListener('click', cargar);

    window.EstadoFlujoRecargar = cargar;
    cargar();
})();
