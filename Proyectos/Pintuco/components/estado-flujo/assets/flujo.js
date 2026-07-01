(function () {
    'use strict';

    var app         = document.getElementById('flujoApp');
    if (!app) return;

    var GETTERS_BASE  = app.dataset.gettersBase  || '';
    var MODULO_BASE   = app.dataset.moduloBase   || '';
    var FILAS_POR_PAGINA = 20;

    var datos       = [];   // todos los registros cargados
    var filtrados   = [];   // después de filtros activos
    var etapaActiva = '';   // filtro de pipeline clickeado
    var paginaActual = 1;

    var tbodyEl    = document.getElementById('flujoTbody');
    var busquedaEl = document.getElementById('flujoBusqueda');
    var promotorEl = document.getElementById('flujoFiltroPromotor');
    var tecnicoEl  = document.getElementById('flujoFiltroTecnico');
    var pageInfoEl = document.getElementById('flujoPageInfo');
    var paginaEl   = document.getElementById('flujoPaginaActual');
    var btnAnterior= document.getElementById('flujoPagAnterior');
    var btnSig     = document.getElementById('flujoPagSiguiente');
    var toastEl    = document.getElementById('flujoToast');

    var ETIQUETAS = {
        contactado:       'Contactado',
        agendado:         'Agendado',
        visita_ok:        'Visita OK',
        proforma:         'Proforma',
        en_negociacion:   'En Negociación',
        aprobado:         'Aprobado',
        venta_finalizada: 'Venta finalizada',
        rechazado:        'Rechazado',
        vencida:          'Vencido',
        cancelada:        'Cancelado',
    };

    // ─── Toast ────────────────────────────────────────────
    var _toastTimer;
    function toast(msg, esError) {
        toastEl.textContent = msg;
        toastEl.className = 'flujo-toast is-visible' + (esError ? ' is-error' : '');
        clearTimeout(_toastTimer);
        _toastTimer = setTimeout(function () {
            toastEl.classList.remove('is-visible');
        }, 3000);
    }

    // ─── Carga de datos ───────────────────────────────────
    function cargar() {
        tbodyEl.innerHTML = '<tr><td colspan="6" class="flujo-vacio">Cargando...</td></tr>';
        fetch(GETTERS_BASE + 'get_flujo.php')
            .then(function (r) { return r.json(); })
            .then(function (json) {
                datos = json.data || [];
                llenarPromotores();
                llenarTecnicos();
                paginaActual = 1;
                aplicarFiltros();
                actualizarTimestamp();
            })
            .catch(function () {
                tbodyEl.innerHTML = '<tr><td colspan="6" class="flujo-vacio">Error al cargar datos.</td></tr>';
                toast('No se pudieron cargar los datos.', true);
            });
    }

    function actualizarTimestamp() {
        var el = document.getElementById('flujoActualizado');
        if (!el) return;
        var now = new Date();
        el.textContent = now.toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit' });
    }

    // ─── Filtros ──────────────────────────────────────────
    function aplicarFiltros() {
        var busq    = busquedaEl.value.toLowerCase().trim();
        var prom    = promotorEl.value;
        var tecnico = tecnicoEl.value;

        filtrados = datos.filter(function (r) {
            if (etapaActiva && r.etapa !== etapaActiva) return false;
            if (prom    && r.usuario !== prom)    return false;
            if (tecnico && r.tecnico !== tecnico) return false;
            if (busq) {
                var haystack = [r.pdv, r.empresa, r.codigo_pdv].join(' ').toLowerCase();
                if (haystack.indexOf(busq) === -1) return false;
            }
            return true;
        });

        paginaActual = 1;
        actualizarPipeline();
        actualizarKpis();
        renderizarTabla();
    }

    function resetPagina() {
        paginaActual = 1;
        renderizarTabla();
    }

    // ─── Pipeline KPIs ───────────────────────────────────
    var CONTADORES = {
        '':               'flujoCntTodos',
        agendado:         'flujoCntAgendado',
        visita_ok:        'flujoCntVisita',
        proforma:         'flujoCntProforma',
        en_negociacion:   'flujoCntNegociacion',
        aprobado:         'flujoCntAprobado',
        venta_finalizada: 'flujoCntFinalizada',
        vencida:          'flujoCntVencida',
        cancelada:        'flujoCntCancelada',
        rechazado:        'flujoCntRechazado',
    };

    function actualizarPipeline() {
        var counts = {};
        Object.keys(CONTADORES).forEach(function (k) { counts[k] = 0; });
        datos.forEach(function (r) {
            counts['']++;
            if (counts[r.etapa] !== undefined) counts[r.etapa]++;
        });
        Object.keys(CONTADORES).forEach(function (k) {
            var el = document.getElementById(CONTADORES[k]);
            if (el) el.textContent = counts[k] || 0;
        });
    }

    function actualizarKpis() {
        var activos = 0, caidas = 0, cerrados = 0, sumaDias = 0, conDias = 0;
        var etapasActivas = ['agendado','visita_ok','proforma','en_negociacion','aprobado'];
        var etapasCaida   = ['vencida','cancelada','rechazado'];

        datos.forEach(function (r) {
            if (etapasActivas.indexOf(r.etapa) !== -1) activos++;
            // venta_finalizada es la conversión real (venta ejecutada con factura)
            if (r.etapa === 'venta_finalizada') cerrados++;
            if (etapasCaida.indexOf(r.etapa) !== -1) caidas++;
            if (r.dias_flujo !== null) { sumaDias += r.dias_flujo; conDias++; }
        });

        var total = datos.length;
        var conversion = total > 0 ? Math.round(cerrados / total * 100) : 0;
        var promDias = conDias > 0 ? Math.round(sumaDias / conDias) : 0;

        setText('flujoKpiActivo',     activos);
        setText('flujoKpiConversion', conversion + '%');
        setText('flujoKpiDias',       conDias > 0 ? promDias + ' días' : '—');
        setText('flujoKpiCaidas',     caidas);
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    // ─── Tabla ───────────────────────────────────────────
    function renderizarTabla() {
        var total  = filtrados.length;
        var inicio = (paginaActual - 1) * FILAS_POR_PAGINA;
        var pagina = filtrados.slice(inicio, inicio + FILAS_POR_PAGINA);
        var totalPags = Math.max(1, Math.ceil(total / FILAS_POR_PAGINA));

        if (total === 0) {
            tbodyEl.innerHTML = '<tr><td colspan="7" class="flujo-vacio">Sin resultados para los filtros aplicados.</td></tr>';
            pageInfoEl.textContent = '';
            paginaEl.textContent = '';
            btnAnterior.disabled = true;
            btnSig.disabled = true;
            return;
        }

        var html = '';
        pagina.forEach(function (r) {
            html += '<tr>';
            html += '<td><div class="flujo-pdv-nombre">' + esc(r.pdv || r.codigo_pdv) + '</div>';
            if (r.empresa) html += '<div class="flujo-pdv-empresa">' + esc(r.empresa) + '</div>';
            html += '</td>';
            html += '<td>' + esc(r.usuario || '—') + '</td>';
            html += '<td>' + esc(r.tecnico || '—') + '</td>';
            html += '<td>' + formatFecha(r.fecha_agendamiento) + '</td>';
            html += '<td><span class="flujo-badge is-' + esc(r.etapa) + '">' + (ETIQUETAS[r.etapa] || esc(r.etapa)) + '</span></td>';
            html += '<td>' + diasSpan(r.dias_flujo, r.etapa) + '</td>';
            html += '<td>' + facturaCell(r) + '</td>';
            html += '</tr>';
        });
        tbodyEl.innerHTML = html;

        pageInfoEl.textContent = 'Mostrando ' + (inicio + 1) + '–' + Math.min(inicio + FILAS_POR_PAGINA, total) + ' de ' + total;
        paginaEl.textContent   = 'Página ' + paginaActual + ' / ' + totalPags;
        btnAnterior.disabled   = paginaActual <= 1;
        btnSig.disabled        = paginaActual >= totalPags;
    }

    function esc(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function formatFecha(f) {
        if (!f || f === '0000-00-00') return '—';
        var p = f.split('-');
        if (p.length !== 3) return f;
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function diasSpan(d, etapa) {
        if (d === null || d === undefined) return '<span style="color:#aab3c2">—</span>';
        var etapasTerminadas = ['aprobado','venta_finalizada','rechazado','cancelada'];
        if (etapasTerminadas.indexOf(etapa) !== -1) {
            return '<span class="flujo-dias-ok">' + d + ' d</span>';
        }
        var cls = d <= 7 ? 'flujo-dias-ok' : d <= 20 ? 'flujo-dias-warn' : 'flujo-dias-bad';
        return '<span class="' + cls + '">' + d + ' d</span>';
    }

    function facturaCell(r) {
        if (r.etapa !== 'venta_finalizada' || !r.foto_factura) {
            return '<span style="color:#aab3c2">—</span>';
        }
        var url = MODULO_BASE + '/' + r.foto_factura;
        return '<a href="' + esc(url) + '" target="_blank" rel="noopener" class="flujo-factura-link" title="Ver factura">'
            + '<i class="glyphicon glyphicon-file"></i> Ver factura</a>';
    }

    // ─── Dropdowns dinámicos ─────────────────────────────
    function llenarPromotores() {
        var vistos = {};
        datos.forEach(function (r) { if (r.usuario) vistos[r.usuario] = true; });
        var lista = Object.keys(vistos).sort();
        var html = '<option value="">Todos los mercaderistas</option>';
        lista.forEach(function (n) { html += '<option value="' + esc(n) + '">' + esc(n) + '</option>'; });
        promotorEl.innerHTML = html;
    }

    function llenarTecnicos() {
        var vistos = {};
        datos.forEach(function (r) { if (r.tecnico) vistos[r.tecnico] = true; });
        var lista = Object.keys(vistos).sort();
        var html = '<option value="">Todos los técnicos</option>';
        lista.forEach(function (n) { html += '<option value="' + esc(n) + '">' + esc(n) + '</option>'; });
        tecnicoEl.innerHTML = html;
    }

    // ─── Event listeners ─────────────────────────────────
    busquedaEl.addEventListener('input', aplicarFiltros);
    promotorEl.addEventListener('change', aplicarFiltros);
    tecnicoEl.addEventListener('change', aplicarFiltros);
    document.getElementById('flujoActualizar').addEventListener('click', function () { _iniciado = true; cargar(); });

    document.getElementById('flujoPipeline').addEventListener('click', function (e) {
        var btn = e.target.closest('.flujo-etapa-btn');
        if (!btn) return;
        etapaActiva = btn.dataset.etapa || '';
        document.querySelectorAll('.flujo-etapa-btn').forEach(function (b) {
            b.classList.remove('is-activa');
        });
        btn.classList.add('is-activa');
        aplicarFiltros();
    });

    btnAnterior.addEventListener('click', function () {
        if (paginaActual > 1) { paginaActual--; renderizarTabla(); }
    });
    btnSig.addEventListener('click', function () {
        var total = Math.ceil(filtrados.length / FILAS_POR_PAGINA);
        if (paginaActual < total) { paginaActual++; renderizarTabla(); }
    });

    // ─── Lazy loading ────────────────────────────────────
    // Estado de Flujo es su propia sección del sidebar — arranca oculta.
    // Carga al primer clic en el ítem del sidebar que la activa.
    var _iniciado = false;

    var secLink = document.querySelector('.sidebar-nav a[href="#sec-estado-flujo"]');
    if (secLink) {
        secLink.addEventListener('click', function () {
            if (!_iniciado) {
                _iniciado = true;
                setTimeout(cargar, 60);
            }
        });
    }

    // ─── API pública ──────────────────────────────────────
    // FlujoRecargar siempre fuerza recarga (ignora _iniciado).
    window.FlujoRecargar = function () { _iniciado = true; cargar(); };
})();
