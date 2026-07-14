(function () {
    'use strict';

    var app          = document.getElementById('facturaApp');
    var GETTERS_BASE = app.dataset.gettersBase;
    var FOTO_BASE    = 'https://luckyecuadorweb.blob.core.windows.net/app/AppPintuco/Inserts/';

    var allRows           = [];   // todos los ciclos, de todos los agendamientos
    var pipeline           = [];  // 1 fila por agendamiento = su último ciclo
    var porAgendamiento    = {};  // { agendamiento_id: [ciclos ASC por id] }
    var pagosPorProforma  = {};   // { id_proforma: [pagos ASC por numero_cuota] }
    var promActivo         = null;
    var auditoriaAbierta   = null;
    var pagoModalAbierto   = false;
    // Selección para exportar — Set de agendamiento_id (string) marcados con
    // checkbox, a nivel de promotor completo o de fila suelta del detalle.
    var agendamientosSeleccionados = new Set();

    // ── Punto azul "hay un cambio sin atender" — misma mecánica que
    // proforma.js (localStorage por navegador/analista, namespace propio
    // para no mezclarse con la de Proforma). "Cambio" acá también incluye
    // el monto facturado (llegó un pago/cuota nueva), a diferencia de
    // Proforma — es justo lo que este módulo vigila. Se marca visto al
    // abrir el panel de Auditoría de ese agendamiento (ver abrirAuditoria).
    // ---------------------------------------------------------------
    var VISTO_KEY_PREFIX = 'pintuco_factura_visto_';

    function firmaFila(p) {
        return [
            p.estado_proforma, p.evidencia, p.foto_factura, p.monto_validado, p.fecha_auditoria,
            totalFacturadoDe(p.agendamiento_id)
        ].join('|');
    }
    function tieneCambioSinAtender(p) {
        var guardada;
        try { guardada = localStorage.getItem(VISTO_KEY_PREFIX + p.agendamiento_id); } catch (e) { return false; }
        return guardada !== firmaFila(p);
    }
    function marcarVisto(p) {
        try { localStorage.setItem(VISTO_KEY_PREFIX + p.agendamiento_id, firmaFila(p)); } catch (e) {}
    }

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
        // no_requiere_visita: el promotor marcó desde el móvil que este
        // contacto no necesita visita técnica — cuenta igual como fase 2.
        if ((p.hora && p.tecnico) || p.no_requiere_visita === 'SI') return 2;
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

    // plazo_meses no siempre vive en la misma fila que foto_factura — el
    // celular abre una fila vacía nueva por cada cuota y a veces el plazo
    // queda ahí, no en la fila final que trae la foto de factura (confirmado
    // contra datos reales 2026-07-14: agendamiento con plan a 3 meses cuya
    // fila con foto_factura tenía plazo_meses NULL). Se prioriza el
    // plazo_meses de la PROPIA fila de factura (cada ciclo de factura puede
    // tener su propio plan); si viene vacío, se usa el mayor plazo_meses
    // entre los demás ciclos del agendamiento como respaldo, para no
    // clasificar ese ciclo como "Pago Directo" solo porque esa fila puntual
    // quedó vacía.
    function plazoMesesDe(factura, ciclos) {
        var propio = parseInt(factura.plazo_meses, 10);
        if (!isNaN(propio) && propio > 0) return propio;
        return ciclos.reduce(function (max, c) {
            var v = parseInt(c.plazo_meses, 10);
            return (!isNaN(v) && v > max) ? v : max;
        }, 0);
    }

    // "Monto Facturado" (corregido 2026-07-14 tras confirmar el contrato real
    // con el usuario y con el equipo Android): monto_total_factura es la
    // META cotizada fija (se copia una sola vez de monto_validado al pasar a
    // "a plazos" o al facturar directo), NO un acumulador.
    //   - Pago Directo (una sola factura): "Monto Facturado" = esa misma
    //     meta fija (monto_total_factura) — coincide con la Cotización
    //     Inicial, es la misma factura única.
    //   - A plazos: se acumula cada factura PARCIAL que el promotor teclea a
    //     mano (primera cuota al activar "a plazos" + cada cuota siguiente
    //     desde el módulo Facturas de la app), una fila propia en
    //     insert_pago_factura por cada una, agrupadas por id_proforma = la
    //     fila "factura" a la que corresponden. Ese agrupamiento ya existe
    //     en pagosPorProforma (ver agruparPagosPorProforma), la misma fuente
    //     que pinta las tarjetas del panel de Financiamiento.
    //
    // Se suma sobre CADA ciclo con foto_factura del agendamiento, no solo el
    // más reciente — una re-negociación puede abrir un segundo ciclo de
    // factura (id_proforma distinto) sin que los pagos ya cobrados bajo el
    // ciclo anterior dejen de contar para el total.
    function sumarFacturadoDe(agendamientoId, periodoClave) {
        var ciclos = ciclosRealesDe(agendamientoId);
        var facturas = ciclos.filter(function (c) { return !!c.foto_factura; });
        return facturas.reduce(function (total, factura) {
            if (plazoMesesDe(factura, ciclos) <= 0) {
                // Pago Directo: todavía no existe fecha_factura en
                // insert_proforma (pendiente ALTER TABLE, ver
                // proformas_listar.php) — se usa proforma_fecha_registro del
                // ciclo con foto_factura como proxy de "fecha de
                // facturación". Migrar a fecha_factura en cuanto exista.
                if (periodoClave && !fechaEnPeriodo(factura.proforma_fecha_registro, periodoClave)) return total;
                return total + (parseFloat(factura.monto_total_factura) || 0);
            }
            var pagos = pagosPorProforma[factura.id] || [];
            return total + pagos.reduce(function (sum, pg) {
                if (periodoClave && !fechaEnPeriodo(pg.fecha_pago || pg.fecha_registro, periodoClave)) return sum;
                return sum + (parseFloat(pg.monto_pago) || 0);
            }, 0);
        }, 0);
    }

    function totalFacturadoDe(agendamientoId) {
        return sumarFacturadoDe(agendamientoId, null);
    }

    // Igual criterio que totalFacturadoDe, pero restringido a lo que cayó
    // DENTRO del periodo seleccionado — usar SOLO en las vistas de reporte
    // mensual (lista/detalle de promotor, Excel). periodoClave === '' o
    // 'todos' delega en totalFacturadoDe.
    function montoFacturadoEnPeriodo(agendamientoId, periodoClave) {
        if (!periodoClave || periodoClave === 'todos') return totalFacturadoDe(agendamientoId);
        return sumarFacturadoDe(agendamientoId, periodoClave);
    }

    // Mismos pagos, agrupados por id_proforma (la fila de factura puntual,
    // no el agendamiento) y ordenados por numero_cuota — para dibujar la
    // cuadrícula de cuotas del panel de Financiamiento.
    function agruparPagosPorProforma(pagos) {
        var mapa = {};
        pagos.forEach(function (pg) {
            var key = pg.id_proforma;
            if (!mapa[key]) mapa[key] = [];
            mapa[key].push(pg);
        });
        Object.keys(mapa).forEach(function (k) {
            mapa[k].sort(function (a, b) { return (parseInt(a.numero_cuota, 10) || 0) - (parseInt(b.numero_cuota, 10) || 0); });
        });
        return mapa;
    }

    // idsPermitidos (opcional): Set de agendamiento_id (string) al que
    // restringir la suma — se usa cuando hay un filtro de PDV/empresa o una
    // selección activa. Sin idsPermitidos, suma TODO el histórico del promotor.
    function totalAcumuladoPromotor(usuario, idsPermitidos) {
        var total = 0;
        Object.keys(porAgendamiento).forEach(function (agId) {
            if (idsPermitidos && !idsPermitidos.has(String(agId))) return;
            var ciclos = porAgendamiento[agId];
            if (!ciclos.length) return;
            var u = (ciclos[0].usuario || '(sin asignar)');
            if (u !== usuario) return;
            var ultima = ultimaProformaDe(agId);
            total += ultima ? (parseFloat(ultima.monto_validado) || 0) : 0;
        });
        return total;
    }

    // Suma de "Monto Facturado" (pagos reales de insert_pago_factura, ver
    // totalFacturadoDe/montoFacturadoEnPeriodo) de TODOS los agendamientos de
    // un promotor — mismo patrón que totalAcumuladoPromotor pero con los
    // pagos, no la cotización. periodoClave === '' ("Todos") suma el
    // histórico completo, vía montoFacturadoEnPeriodo delegando en
    // totalFacturadoDe.
    function totalFacturadoPromotorEnPeriodo(usuario, idsPermitidos, periodoClave) {
        var total = 0;
        Object.keys(porAgendamiento).forEach(function (agId) {
            if (idsPermitidos && !idsPermitidos.has(String(agId))) return;
            var ciclos = porAgendamiento[agId];
            if (!ciclos.length) return;
            var u = (ciclos[0].usuario || '(sin asignar)');
            if (u !== usuario) return;
            total += montoFacturadoEnPeriodo(agId, periodoClave);
        });
        return total;
    }

    // Conteo por fase de un promotor, opcionalmente restringido a
    // idsPermitidos (mismo criterio que las funciones de arriba).
    function conteoFasePorPromotor(usuario, idsPermitidos) {
        var conteos = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
        pipeline.forEach(function (p) {
            if ((p.usuario || '(sin asignar)') !== usuario) return;
            if (idsPermitidos && !idsPermitidos.has(String(p.agendamiento_id))) return;
            conteos[getFase(p)]++;
        });
        return conteos;
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

    // PDV o empresa: mismo criterio de búsqueda que usaba Por Fase.
    function matchPdv(p, q) {
        var hay = [(p.pdv || ''), (p.empresa || ''), (p.codigo_pdv || '')].join(' ').toLowerCase();
        return hay.indexOf(q) !== -1;
    }

    // ── Metadatos de fase ──────────────────────────────────────────────
    var FASES_META = [
        { fase: 1, label: 'Contacto' },
        { fase: 2, label: 'Agendado' },
        { fase: 3, label: 'Proforma' },
        { fase: 4, label: 'Negociación' },
        { fase: 5, label: 'Facturado' },
    ];

    // Selector de Periodo con meses reales (igual mecanismo que
    // poblarSelectorPeriodoPrincipal en contactados.js, pedido explícito del
    // usuario: mostrar solo los meses que tienen datos, como en la app
    // móvil, en vez de solo "mes actual/anterior"). Clave = "YYYY-MM"
    // (o "todos" para sin filtro).
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

    // Mismo criterio que coincidePeriodo, pero como helper standalone
    // reusable por montoFacturadoEnPeriodo — para poder comparar la fecha
    // de un PAGO puntual (no solo la fecha de visita/contacto) contra la
    // clave de Periodo seleccionada. clave === '' o 'todos' no filtra nada.
    function fechaEnPeriodo(fechaStr, clave) {
        if (!clave || clave === 'todos') return true;
        var propia = claveMes(fechaStr);
        return propia !== null && propia === clave;
    }

    // Etiqueta visual del periodo activo, para dejar claro en los títulos
    // que el monto mostrado es SOLO de ese periodo. '' / 'todos' no agrega
    // sufijo.
    function etiquetaPeriodo(clave) {
        return (clave && clave !== 'todos') ? (' (' + etiquetaMes(clave) + ')') : '';
    }

    // Arma las opciones del selector de Periodo a partir de los meses que
    // realmente tienen datos (visita/contacto, o algún pago/factura — mismos
    // criterios que coincidePeriodo/montoFacturadoEnPeriodo) + el mes actual
    // (siempre presente, para que el default tenga dónde caer), más
    // reciente primero. Respeta el mes ya elegido al refrescar.
    function poblarSelectorPeriodo() {
        var select = document.getElementById('efPromoFiltroPeriodo');
        var valorPrevio = select.value;
        var actual = claveMesActual();

        var claves = {};
        claves[actual] = true;
        allRows.forEach(function (c) {
            var fechaAgendaValida = soloFecha(c.fecha_agendamiento);
            var refCruda = (fechaAgendaValida && fechaAgendaValida !== '0000-00-00')
                ? c.fecha_agendamiento
                : c.contacto_fecha_registro;
            var clave = claveMes(refCruda);
            if (clave) claves[clave] = true;
            if (c.foto_factura) {
                var claveFactura = claveMes(c.proforma_fecha_registro);
                if (claveFactura) claves[claveFactura] = true;
            }
        });
        Object.keys(pagosPorProforma).forEach(function (idProforma) {
            pagosPorProforma[idProforma].forEach(function (pg) {
                var clavePago = claveMes(pg.fecha_pago || pg.fecha_registro);
                if (clavePago) claves[clavePago] = true;
            });
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

    // Filtro "Periodo" — arranca en el mes actual por defecto (ver
    // poblarSelectorPeriodo): al entrar a la página, la lista/detalle/total
    // solo muestran lo del mes en curso, sin que el analista tenga que
    // tocar nada. Fecha de referencia: fecha_agendamiento (visita), con
    // contacto_fecha_registro como respaldo para agendamientos que aún no
    // tienen visita agendada.
    function coincidePeriodo(p) {
        var sel = document.getElementById('efPromoFiltroPeriodo');
        var clave = sel ? sel.value : 'todos';
        if (!clave || clave === 'todos') return true; // "Todos"
        // fecha_agendamiento en blanco llega de MySQL como '0000-00-00...'
        // (string, no null) cuando el agendamiento todavía no tiene visita
        // agendada — ese string es "truthy" en JS, así que "p.fecha_agendamiento
        // || p.contacto_fecha_registro" NUNCA caía al respaldo como decía el
        // comentario de arriba, y ese agendamiento desaparecía sin explicación
        // de "Mes actual"/"Mes anterior" aunque se hubiera registrado hoy
        // (confirmado 2026-07-10). Se normaliza ANTES del fallback.
        var fechaAgendaValida = soloFecha(p.fecha_agendamiento);
        var refCruda = (fechaAgendaValida && fechaAgendaValida !== '0000-00-00')
            ? p.fecha_agendamiento
            : p.contacto_fecha_registro;
        if (fechaEnPeriodo(refCruda, clave)) return true;
        // También cuenta como "del periodo" si recibió un pago/factura EN ese
        // periodo, aunque la visita haya sido meses atrás — típico de
        // financiamiento a plazos, donde la cuota de julio no debe
        // desaparecer del reporte de julio solo porque la visita fue en mayo.
        return montoFacturadoEnPeriodo(p.agendamiento_id, clave) > 0;
    }

    // ── Lista izquierda ────────────────────────────────────────────────
    // Respeta el filtro "PDV o empresa" de la barra de arriba: sin filtro,
    // cada promotor muestra su histórico completo (igual que siempre); con
    // filtro, el conteo/montos se recalculan solo con los agendamientos que
    // matchean, para que coincida con lo que se ve al abrir su detalle.
    // p.ids: Set de agendamiento_id (string) que matchean el filtro actual —
    // se usa para saber qué marcar al tildar el checkbox de ese promotor.
    function construirMapaPromotores() {
        var filtroPdv = (document.getElementById('efPromoBusquedaPdv').value || '').toLowerCase().trim();
        var sel = document.getElementById('efPromoFiltroPeriodo');
        var clave = sel ? sel.value : 'todos';
        var conPeriodo = clave !== 'todos';
        var mapa = {};
        pipeline.forEach(function (p) {
            if (!coincidePeriodo(p)) return;
            if (filtroPdv && !matchPdv(p, filtroPdv)) return;
            var u = p.usuario || '(sin asignar)';
            if (!mapa[u]) mapa[u] = { usuario: u, total: 0, ids: new Set() };
            mapa[u].total++;
            mapa[u].ids.add(String(p.agendamiento_id));
        });
        Object.keys(mapa).forEach(function (u) {
            var idsPermitidos = (filtroPdv || conPeriodo) ? mapa[u].ids : null;
            mapa[u].montoAcumulado = totalAcumuladoPromotor(u, idsPermitidos);
            mapa[u].montoFacturado = totalFacturadoPromotorEnPeriodo(u, idsPermitidos, clave);
        });
        return mapa;
    }

    // "$facturado / $cotizado" — como una nota de examen (logrado / total).
    // El facturado va resaltado (ya "ganado"); el cotizado en color neutro
    // (es la referencia, no el logro).
    function fmtMontoSlash(facturado, cotizado) {
        return '<span class="ef-slash-facturado">' + fmtMonto(facturado) + '</span>'
            + ' <span class="ef-slash-sep">/</span> '
            + '<span class="ef-slash-cotizado">' + fmtMonto(cotizado) + '</span>';
    }

    // Actualiza el botón "Desmarcar todo" (solo visible con selección activa)
    // y su contador — se llama cada vez que cambia agendamientosSeleccionados.
    function actualizarBotonSeleccion() {
        var totalSel = agendamientosSeleccionados.size;
        var btn = document.getElementById('efDesmarcarTodo');
        var count = document.getElementById('efSelCount');
        if (count) count.textContent = totalSel;
        if (btn) btn.style.display = totalSel > 0 ? '' : 'none';
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
            actualizarBotonSeleccion();
            return;
        }
        container.innerHTML = visible.map(function (p) {
            var activo = p.usuario === promActivo ? 'is-activo' : '';
            var seleccionados = 0;
            p.ids.forEach(function (id) { if (agendamientosSeleccionados.has(id)) seleccionados++; });
            var marcado = seleccionados > 0 && seleccionados === p.total;
            // Punto azul agregado: alguno de los agendamientos de este
            // promotor tiene un cambio sin revisar — así el analista sabe a
            // cuál promotor entrar sin tener que abrirlos todos uno a uno.
            var tieneCambios = false;
            p.ids.forEach(function (id) {
                if (tieneCambios) return;
                var fila = pipeline.filter(function (x) { return String(x.agendamiento_id) === id; })[0];
                if (fila && tieneCambioSinAtender(fila)) tieneCambios = true;
            });
            var dot = tieneCambios ? '<span class="ef-promo-item-dot" title="Hay un cambio sin revisar"></span>' : '';
            return '<div class="ef-promo-item ' + activo + '" data-usuario="' + esc(p.usuario) + '">'
                + '<input type="checkbox" class="ef-promo-check" data-usuario="' + esc(p.usuario) + '"' + (marcado ? ' checked' : '') + '>'
                + '<div class="ef-promo-item-body">'
                +   '<div class="ef-promo-item-nombre">' + esc(p.usuario) + dot + '</div>'
                +   '<div class="ef-promo-item-meta">'
                +     '<span>' + p.total + ' agendado' + (p.total !== 1 ? 's' : '') + '</span>'
                +   '</div>'
                +   '<div class="ef-promo-item-monto">' + fmtMontoSlash(p.montoFacturado, p.montoAcumulado) + '</div>'
                + '</div>'
                + '</div>';
        }).join('');

        // El estado "parcialmente seleccionado" (indeterminate) no se puede
        // fijar por HTML — se aplica después de insertar el DOM.
        var porUsuario = {};
        visible.forEach(function (p) { porUsuario[p.usuario] = p; });
        container.querySelectorAll('.ef-promo-check').forEach(function (cb) {
            var p = porUsuario[cb.dataset.usuario];
            if (!p) return;
            var seleccionados = 0;
            p.ids.forEach(function (id) { if (agendamientosSeleccionados.has(id)) seleccionados++; });
            cb.indeterminate = seleccionados > 0 && seleccionados < p.total;
        });

        actualizarBotonSeleccion();
    }

    // Marca/desmarca TODOS los agendamientos de un promotor que matchean el
    // filtro "PDV o empresa" actual (mapa[usuario].ids ya viene filtrado).
    function toggleSeleccionPromotor(usuario, marcar) {
        var mapa = construirMapaPromotores();
        var p = mapa[usuario];
        if (!p) return;
        p.ids.forEach(function (id) {
            if (marcar) agendamientosSeleccionados.add(id); else agendamientosSeleccionados.delete(id);
        });
        renderPromoLista();
        if (promActivo === usuario) renderPromoDetalle(promActivo);
    }

    // ── Panel derecho ──────────────────────────────────────────────────
    function renderPromoDetalle(usuario) {
        var container = document.getElementById('efPromoDetalle');
        if (!usuario) {
            container.innerHTML = '<div class="ef-vacio">Selecciona un promotor.</div>';
            return;
        }

        var filtroPdv = (document.getElementById('efPromoBusquedaPdv').value || '').toLowerCase().trim();
        var sel = document.getElementById('efPromoFiltroPeriodo');
        var clave = sel ? sel.value : 'todos';
        var conPeriodo = clave !== 'todos';
        var todos = pipeline.filter(function (p) {
            return (p.usuario || '(sin asignar)') === usuario && coincidePeriodo(p);
        });
        var pdvs = filtroPdv ? todos.filter(function (p) { return matchPdv(p, filtroPdv); }) : todos;

        // Con filtro de PDV/empresa o de Periodo activo, badges y totales se
        // recalculan solo con lo que queda visible en la tabla de abajo (para
        // que cuadren entre sí); sin ningún filtro, muestran el histórico
        // completo del promotor, igual que siempre.
        var idsPermitidos = (filtroPdv || conPeriodo) ? new Set(pdvs.map(function (p) { return String(p.agendamiento_id); })) : null;
        var conteosFase = conteoFasePorPromotor(usuario, idsPermitidos);
        var total = totalAcumuladoPromotor(usuario, idsPermitidos);
        var totalFacturado = totalFacturadoPromotorEnPeriodo(usuario, idsPermitidos, clave);

        var badges = FASES_META.map(function (m) {
            return '<div class="ef-fase-badge-mini"><span class="ef-fase-badge-dot"></span>F' + m.fase + ': ' + conteosFase[m.fase] + '</div>';
        }).join('');

        var filas = pdvs.map(function (p) {
            var f = getFase(p);
            var dias = diasDesde(refFechaFase(p));
            var vigente = ultimaProformaDe(p.agendamiento_id);
            var cotizacionNum = vigente ? (parseFloat(vigente.monto_validado) || 0) : 0;
            // facturadoNum se mantiene HISTÓRICO (totalFacturadoDe) — decide
            // el color rojo/negro (facturaCompleta), un concepto "de por
            // vida" (¿ya se pagó completo?), no mensual. facturadoPeriodo es
            // lo que se MUESTRA en la celda cuando hay un periodo activo.
            var facturadoNum = totalFacturadoDe(p.agendamiento_id);
            var facturadoPeriodo = montoFacturadoEnPeriodo(p.agendamiento_id, clave);
            // Rojo mientras falte por facturar; negro en cuanto lo facturado
            // alcanza o supera lo cotizado — pedido explícito del usuario
            // (2026-07-14), antes quedaba siempre en rojo sin importar el
            // avance real. cotizacionNum > 0 evita marcar "completo" un
            // ciclo con monto_validado="0" (string no vacío, cuenta como
            // cotización real para ultimaProformaDe) donde 0 >= 0 daría un
            // falso "completo" sin haberse cotizado ni facturado nada.
            var facturaCompleta = vigente && cotizacionNum > 0 && facturadoNum >= cotizacionNum;
            var cotizacionInicial = vigente ? fmtMonto(vigente.monto_validado) : '—';
            var montoFacturado = fmtMonto(facturadoPeriodo);
            var marcado = agendamientosSeleccionados.has(String(p.agendamiento_id));
            // Punto azul: hay un cambio (foto nueva, cambio de fase, pago
            // nuevo, etc.) que el analista todavía no revisó en esta sesión
            // — misma mecánica que Proforma (ver tieneCambioSinAtender
            // arriba), se apaga al abrir "Auditar" de esa fila.
            var dot = tieneCambioSinAtender(p) ? '<span class="ef-tabla-dot" title="Hay un cambio sin revisar"></span>' : '';
            return '<tr data-agendamiento-id="' + esc(p.agendamiento_id) + '">'
                + '<td class="ef-td-check"><input type="checkbox" class="ef-row-check" data-agendamiento-id="' + esc(p.agendamiento_id) + '"' + (marcado ? ' checked' : '') + '></td>'
                + '<td><div class="ef-tabla-empresa">' + esc(p.empresa || '—') + dot + '</div>'
                +     '<div class="ef-tabla-pdv">' + esc(p.pdv || p.codigo_pdv || '') + '</div></td>'
                + '<td><span class="ef-fase-badge is-f' + f + '">Fase ' + f + '</span></td>'
                + '<td class="ef-dias-plano">' + fmtDias(dias) + '</td>'
                + '<td class="ef-monto-facturado' + (facturaCompleta ? ' is-completo' : '') + '">' + montoFacturado + '</td>'
                + '<td class="ef-monto-cotizacion">' + cotizacionInicial + '</td>'
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
            +     '<div class="ef-promo-total-label">Total Monto Facturado' + esc(etiquetaPeriodo(clave)) + '</div>'
            +     '<div class="ef-promo-total-valor">' + fmtMontoSlash(totalFacturado, total) + '</div>'
            +     '<div class="ef-promo-total-caption">Monto facturado / Cotización</div>'
            +   '</div>'
            + '</div>'
            + '<div class="ef-promo-badges">' + badges + '</div>'
            + '<div class="ef-promo-tabla-wrap"><table class="ef-tabla-prom">'
            +   '<thead><tr><th class="ef-th-check"></th><th>Empresa / PDV</th><th>Fase</th><th>Días</th><th>Monto Facturado' + esc(etiquetaPeriodo(clave)) + '</th><th>Cotización Inicial</th><th></th></tr></thead>'
            +   '<tbody>' + (filas || '<tr><td colspan="7" class="ef-vacio">Sin agendamientos.</td></tr>') + '</tbody>'
            + '</table></div>';
    }

    // ── Panel de auditoría (slide-over, 100% lectura) ─────────────────
    function abrirAuditoria(agendamientoId) {
        var p = pipeline.filter(function (x) { return String(x.agendamiento_id) === String(agendamientoId); })[0];
        if (!p) return;
        var ciclos = porAgendamiento[agendamientoId] || [];
        auditoriaAbierta = agendamientoId;

        // Abrir la auditoría cuenta como "atenderla": el punto azul de esa
        // fila (y del promotor, si era el único cambio pendiente) se apaga
        // hasta que algo vuelva a cambiar.
        marcarVisto(p);
        renderPromoLista();
        if (promActivo) renderPromoDetalle(promActivo);

        var f = getFase(p);
        var dias = diasDesde(refFechaFase(p));
        var meta = FASES_META[f - 1];

        document.getElementById('efAudNombre').textContent = p.empresa || '—';
        document.getElementById('efAudSub').textContent = (p.contacto || '—') + ' · Promotor: ' + (p.usuario || '(sin asignar)');
        document.getElementById('efAudPdv').textContent = 'PDV: ' + (p.pdv || p.codigo_pdv || '—');
        var faseBadge = document.getElementById('efAudFaseBadge');
        faseBadge.className = 'ef-fase-badge is-f' + f;
        faseBadge.textContent = 'Fase ' + f + ' · ' + meta.label;
        document.getElementById('efAudDias').textContent = fmtDias(dias) + ' en esta fase';

        renderAuditoriaTimeline(p, ciclos);
        renderAuditoriaHistorial(ciclos);
        renderFinanciamiento(agendamientoId, ciclos);

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
                // fecha_auditoria solo se llena cuando el analista corre
                // 'guardar'/'rechazar' en la web. Si el monto vino puesto
                // directo por el promotor desde el celular (estado aún
                // 'en_proceso'), fecha_auditoria queda null — se usa la
                // fecha de registro de esa fila como respaldo (mismo patrón
                // ya usado abajo para ultimaFactura).
                fecha: formatFechaHora(c.fecha_auditoria || c.proforma_fecha_registro),
                detalle: 'Monto cotizado: ' + fmtMonto(c.monto_validado),
                foto: c.evidencia ? (FOTO_BASE + c.evidencia) : null
            };
        });

        var defs = [
            { num: 1, label: 'Contacto',
              filas: [{ fecha: formatFechaHora(p.contacto_fecha_registro),
                        detalle: 'Primer contacto registrado con el Cliente.' }] },
            { num: 2, label: 'Agendado',
              filas: [p.no_requiere_visita === 'SI'
                  ? { fecha: '—', detalle: 'No requirió visita técnica.' }
                  : { fecha: formatFecha(p.fecha_agendamiento) + (p.hora ? ' · ' + String(p.hora).slice(0, 5) : ''),
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
              // Ya NO cae en ultimaFactura.fecha_auditoria — esa columna la
              // pisa 'cerrar_plan_pago' con la fecha de cierre del plan (ver
              // el mismo ajuste ya hecho arriba en fechaActivacion), y esta
              // fila de "Facturado" es la misma que esa acción actualiza.
              filas: [{ fecha: ultimaFactura ? formatFechaHora(ultimaFactura.proforma_fecha_registro) : '—',
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
        // Cada vez que el móvil sube una foto de factura o una cuota de
        // pago crea una fila NUEVA en insert_proforma (estado_proforma
        // 'realizado', sin evidencia ni monto_validado) en vez de escribir
        // sobre la existente — confirmado contra BD 2026-07-10. Esa fila
        // no es una proforma real (no tiene ni foto de cotización ni
        // monto), así que salía acá como un "Ciclo N" vacío con guion. Ese
        // evento de factura ya se ve en el panel de Financiamiento (más
        // abajo, vía foto_factura/pagos), así que se descarta acá.
        var reales = ciclos.filter(function (c) { return !!c.id && (!!c.monto_validado || !!c.evidencia); });
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
            return '<div class="ef-hist-row' + (esVigente ? ' is-vigente' : '') + '">'
                + '<div class="ef-hist-row-left">'
                +   '<div class="ef-hist-row-titulo">Ciclo ' + (i + 1) + '</div>'
                +   '<div class="ef-hist-row-sub">' + esc(formatFecha(c.fecha_proforma)) + ' · Cotizado</div>'
                + '</div>'
                + '<div class="ef-hist-row-right">'
                +   '<div class="ef-hist-row-monto">' + fmtMonto(c.monto_validado) + '</div>'
                +   '<span class="ef-estado-badge ' + est.cls + '">' + est.label + '</span>'
                +   (esVigente ? '<span class="ef-badge-vigente">✓ Cuenta para el total</span>' : '')
                + '</div>'
                + '</div>';
        }).join('');
        document.getElementById('efAudHistorial').innerHTML = html;
    }

    // ── Panel de Financiamiento (factura pagada a plazos, 2026-07-07) ──
    // La "factura" no es una tabla aparte: es la fila de "ciclos" que tiene
    // foto_factura lleno (misma insert_proforma de siempre). Cada pago/cuota
    // vive en insert_pago_factura, agrupado por esa fila (id_proforma).
    //
    // 2026-07-10 — pedido explícito del usuario: ya no se dibujan tarjetas
    // "Pendiente" para las cuotas que todavía no llegaron (antes se
    // rellenaba hasta totalCuotas con placeholders vacíos, con un monto
    // "teórico" inventado). Ahora solo se pinta una tarjeta por cada pago
    // REAL que ya subió el promotor — se va llenando de una en una según
    // llegan, sin espacios vacíos. También se quitó la barra "Progreso de
    // Cobro" y el badge "N Cuotas"; el título "Financiamiento (N meses)" y
    // "Estado de pago" se mantienen igual que antes.
    function renderFinanciamiento(agendamientoId, ciclos) {
        var cont = document.getElementById('efFinanciamiento');
        var panel = document.getElementById('efAuditoriaPanel');
        var factura = ultimoCicloConFactura(ciclos);

        if (!factura) {
            // Sin factura todavía (fase 1-4): panel de una sola columna,
            // igual que antes de esta mecánica.
            panel.classList.remove('has-financiamiento');
            cont.innerHTML = '';
            return;
        }
        panel.classList.add('has-financiamiento');

        var cotizacionInicial = ultimaProformaDe(agendamientoId);
        var montoFacturado = totalFacturadoDe(agendamientoId);
        var pagos = pagosPorProforma[factura.id] || [];

        var plazoMeses = plazoMesesDe(factura, ciclos);
        var esDirecto = plazoMeses <= 0;
        var totalCuotas = esDirecto ? 1 : plazoMeses;

        var pagosOrdenados = pagos.slice().sort(function (a, b) {
            return (parseInt(a.numero_cuota, 10) || 0) - (parseInt(b.numero_cuota, 10) || 0);
        });
        var tarjetas = pagosOrdenados.map(function (pago) {
            var etiqueta = esDirecto ? 'Pago Único' : ('M' + (parseInt(pago.numero_cuota, 10) || ''));
            var fotoUrl = pago.foto_pago ? (FOTO_BASE + pago.foto_pago) : null;
            return '<div class="ef-fin-card is-pagada" data-pago-id="' + esc(pago.id) + '">'
                + '<div class="ef-fin-card-top">'
                +   '<div class="ef-fin-card-info">'
                +     '<span class="ef-fin-card-mes">' + esc(etiqueta) + '</span>'
                +     '<div class="ef-fin-card-monto">' + fmtMonto(pago.monto_pago) + '</div>'
                +     '<div class="ef-fin-card-fecha">' + esc(formatFechaHora(pago.fecha_pago || pago.fecha_registro)) + '</div>'
                +     (pago.observacion ? '<div class="ef-fin-card-obs">Obs: ' + esc(pago.observacion) + '</div>' : '')
                +   '</div>'
                +   (fotoUrl
                        ? '<div class="ef-fin-card-foto-wrap"><img class="ef-fin-card-foto" src="' + esc(fotoUrl) + '" alt="Foto de pago"><span class="ef-fin-card-check"><i class="glyphicon glyphicon-ok"></i></span></div>'
                        : '<div class="ef-fin-card-foto-wrap"><span class="ef-fin-card-check"><i class="glyphicon glyphicon-ok"></i></span></div>')
                + '</div>'
                + '</div>';
        }).join('') || '<div class="ef-fin-sin-pagos">Todavía no se registran pagos.</div>';

        // Ya NO incluye factura.fecha_auditoria en el respaldo — desde que
        // 'cerrar_plan_pago' reusa esa misma columna para la fecha de
        // cierre del plan (ver más abajo), dejarla acá pisaría "fecha de
        // aprobación de Fase 5" con la fecha de cierre después de cerrar
        // un plan. fecha_factura todavía no se selecciona en
        // proformas_listar.php (pendiente ALTER TABLE), así que hoy este
        // respaldo cae siempre en proforma_fecha_registro — mismo
        // resultado que antes, sin el riesgo.
        var fechaActivacion = factura.fecha_factura || factura.proforma_fecha_registro;
        var estadoPagoLabel = { pendiente: 'Pendiente', en_proceso: 'En proceso', completado: 'Completado' }[factura.estado_pago] || null;

        // Cierre de plan de pago a plazos que se quedó a medias (dejó de
        // pagar cuotas y nunca va a terminar) — pedido explícito del
        // usuario, 2026-07-14. NUNCA toca estado_pago (de un solo dueño,
        // la app) — es solo una anotación propia de la web en una columna
        // separada (motivo_cierre_pago; la fecha reusa fecha_auditoria,
        // ver nota arriba). No aplica a
        // "Pago Directo" (ya está completo por definición) ni si ya está
        // 'completado' según la app, ni si ya se cerró antes.
        var planCerrado = !!factura.motivo_cierre_pago;
        var puedeCerrarPlan = !esDirecto && !planCerrado && factura.estado_pago !== 'completado';

        cont.innerHTML =
            '<div class="ef-fin-resumen">'
            +   '<div class="ef-fin-resumen-item">'
            +     '<span class="ef-fin-resumen-label">Cotización Inicial</span>'
            +     '<div class="ef-fin-resumen-valor">' + (cotizacionInicial ? fmtMonto(cotizacionInicial.monto_validado) : '—') + '</div>'
            +   '</div>'
            +   '<div class="ef-fin-resumen-sep"></div>'
            +   '<div class="ef-fin-resumen-item is-derecha">'
            +     '<span class="ef-fin-resumen-label">Monto Facturado</span>'
            +     '<div class="ef-fin-resumen-valor is-verde">' + fmtMonto(montoFacturado) + '</div>'
            +   '</div>'
            + '</div>'
            + '<div class="ef-fin-header">'
            +   '<h3 class="ef-auditoria-seccion-titulo">Financiamiento' + (esDirecto ? '' : ' (' + totalCuotas + ' meses)') + '</h3>'
            +   '<span class="ef-fin-tipo-badge">' + (esDirecto ? 'Pago Directo' : 'Pago a Meses') + '</span>'
            + '</div>'
            + (estadoPagoLabel ? '<div class="ef-fin-estado-pago">Estado de pago: <strong>' + esc(estadoPagoLabel) + '</strong></div>' : '')
            + (planCerrado
                ? '<div class="ef-fin-cierre-banner"><i class="glyphicon glyphicon-ban-circle"></i> '
                  + '<div><strong>Plan de pago cerrado</strong>' + (factura.fecha_auditoria ? ' el ' + esc(formatFecha(factura.fecha_auditoria)) : '') + '.'
                  + '<div class="ef-fin-cierre-banner-motivo">' + esc(factura.motivo_cierre_pago) + '</div></div></div>'
                : '')
            + '<div class="ef-fin-grid">' + tarjetas + '</div>'
            + '<div class="ef-fin-nota">'
            +   '<p class="ef-fin-nota-titulo">Nota de Facturación</p>'
            +   '<p class="ef-fin-nota-texto">El financiamiento se activó automáticamente tras la aprobación de la <strong>Fase 5</strong>'
            +     (fechaActivacion ? ' el ' + esc(formatFecha(fechaActivacion)) : '') + '.</p>'
            + '</div>'
            + (puedeCerrarPlan
                ? '<div class="ef-fin-cerrar-plan-wrap"><button type="button" class="ef-fin-btn-cerrar-plan" id="efBtnCerrarPlanPago">'
                  + '<i class="glyphicon glyphicon-ban-circle"></i> Cerrar plan de pago</button></div>'
                : '');

        cont.querySelectorAll('.ef-fin-card.is-pagada').forEach(function (card) {
            card.addEventListener('click', function () {
                var pagoId = card.dataset.pagoId;
                var pago = pagos.filter(function (pg) { return String(pg.id) === String(pagoId); })[0];
                if (pago) abrirPagoModal(pago, card.querySelector('.ef-fin-card-mes').textContent);
            });
        });

        var btnCerrarPlan = document.getElementById('efBtnCerrarPlanPago');
        if (btnCerrarPlan) {
            btnCerrarPlan.addEventListener('click', function () {
                mostrarConfirmacionCierrePlanPago(factura, agendamientoId, ciclos);
            });
        }
    }

    function mostrarConfirmacionCierrePlanPago(factura, agendamientoId, ciclos) {
        var overlay = document.createElement('div');
        overlay.className = 'ef-fin-cierre-modal-overlay';

        var card = document.createElement('div');
        card.className = 'ef-fin-cierre-modal-card';
        card.innerHTML =
            '<i class="glyphicon glyphicon-question-sign"></i>'
            + '<div class="ef-fin-cierre-modal-titulo">¿Cerrar este plan de pago?</div>'
            + '<div class="ef-fin-cierre-modal-texto">'
            +   'Se va a marcar como cerrado desde la web. El estado de pago que reporta la app no se modifica — esto queda como una anotación aparte. Esta acción no se puede deshacer.'
            + '</div>';

        var campoObs = document.createElement('div');
        campoObs.className = 'ef-fin-cierre-campo';
        var labelObs = document.createElement('label');
        labelObs.textContent = 'Motivo del cierre *';
        var inputObs = document.createElement('textarea');
        inputObs.className = 'form-control';
        inputObs.rows = 3;
        inputObs.placeholder = 'Explica por qué se cierra este plan de pago...';
        campoObs.appendChild(labelObs);
        campoObs.appendChild(inputObs);
        card.appendChild(campoObs);

        var errorObs = document.createElement('div');
        errorObs.className = 'ef-fin-cierre-modal-error';
        card.appendChild(errorObs);

        var acciones = document.createElement('div');
        acciones.className = 'ef-fin-cierre-modal-acciones';

        var btnCancelar = document.createElement('button');
        btnCancelar.type = 'button';
        btnCancelar.className = 'btn';
        btnCancelar.textContent = 'Cancelar';
        btnCancelar.addEventListener('click', function () { overlay.remove(); });

        var btnConfirmar = document.createElement('button');
        btnConfirmar.type = 'button';
        btnConfirmar.className = 'ef-fin-btn-confirmar-cierre';
        btnConfirmar.textContent = 'Cerrar plan de pago';
        btnConfirmar.addEventListener('click', function () {
            var motivo = inputObs.value.trim();
            if (!motivo) {
                errorObs.textContent = 'El motivo del cierre es obligatorio.';
                inputObs.focus();
                return;
            }
            errorObs.textContent = '';
            btnConfirmar.disabled = true;
            btnCancelar.disabled = true;
            var body = new URLSearchParams();
            body.set('id', factura.id);
            body.set('accion', 'cerrar_plan_pago');
            body.set('motivo_cierre_pago', motivo);
            fetch(GETTERS_BASE + 'update_proforma.php', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (json.success) {
                        // Refleja el cambio de inmediato en el mismo objeto
                        // (ciclos/factura son la misma referencia usada por
                        // renderFinanciamiento) sin esperar un round-trip;
                        // cargar() en segundo plano mantiene todo lo demás
                        // sincronizado para la próxima vez que se abra.
                        factura.motivo_cierre_pago = motivo;
                        factura.fecha_auditoria = new Date().toISOString().slice(0, 19).replace('T', ' ');
                        // Sin esto, firmaFila(p) (que incluye fecha_auditoria)
                        // deja de coincidir con lo que marcarVisto guardó al
                        // abrir esta Auditoría, y el punto azul "cambio sin
                        // revisar" reaparece sobre la misma fila que el
                        // analista acaba de cerrar.
                        var pFila = pipeline.filter(function (x) { return String(x.agendamiento_id) === String(agendamientoId); })[0];
                        if (pFila) marcarVisto(pFila);
                        overlay.remove();
                        renderFinanciamiento(agendamientoId, ciclos);
                        renderPromoLista();
                        if (promActivo) renderPromoDetalle(promActivo);
                        cargar();
                    } else {
                        errorObs.textContent = json.message || 'No se pudo cerrar.';
                        btnConfirmar.disabled = false;
                        btnCancelar.disabled = false;
                    }
                })
                .catch(function () {
                    errorObs.textContent = 'Error de conexión.';
                    btnConfirmar.disabled = false;
                    btnCancelar.disabled = false;
                });
        });

        acciones.appendChild(btnCancelar);
        acciones.appendChild(btnConfirmar);
        card.appendChild(acciones);

        overlay.appendChild(card);
        document.body.appendChild(overlay);
        inputObs.focus();
    }

    // ── Modal de detalle de pago ───────────────────────────────────────
    function abrirPagoModal(pago, etiquetaMes) {
        document.getElementById('efPagoModalMes').textContent = etiquetaMes || '';
        document.getElementById('efPagoModalMonto').textContent = fmtMonto(pago.monto_pago);
        document.getElementById('efPagoModalFecha').textContent = formatFechaHora(pago.fecha_pago || pago.fecha_registro);
        document.getElementById('efPagoModalObs').textContent = pago.observacion || 'Sin observación.';
        document.getElementById('efPagoModalImg').src = pago.foto_pago ? (FOTO_BASE + pago.foto_pago) : '';
        pagoModalAbierto = true;
        document.getElementById('efPagoModalOverlay').classList.add('is-abierto');
    }
    function cerrarPagoModal() {
        pagoModalAbierto = false;
        document.getElementById('efPagoModalOverlay').classList.remove('is-abierto');
        document.getElementById('efPagoModalImg').src = '';
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

    // ── Exportar a Excel ─────────────────────────────────────────────
    // El libro tiene una hoja de detalle por agendamiento — restringida a
    // lo que el usuario marcó con checkbox (ver agendamientosSeleccionados,
    // "Seleccionar todo" y "Desmarcar todo").
    var ESTILO_ENCABEZADO = {
        fill: { patternType: 'solid', fgColor: { rgb: 'FF1D5FA8' } },
        font: { bold: true, color: { rgb: 'FFFFFFFF' } },
        alignment: { vertical: 'center', horizontal: 'left' }
    };

    function estilizarEncabezado(hoja) {
        var rango = XLSX.utils.decode_range(hoja['!ref'] || 'A1');
        for (var C = rango.s.c; C <= rango.e.c; C++) {
            var addr = XLSX.utils.encode_cell({ r: rango.s.r, c: C });
            if (hoja[addr]) hoja[addr].s = ESTILO_ENCABEZADO;
        }
    }

    // Columnas pedidas por el usuario, en este orden exacto: Promotor, PDV,
    // Contacto, Empresa, Monto Facturado, Cotización inicial. periodoClave
    // periodiza "Monto Facturado" igual que en pantalla (ver
    // montoFacturadoEnPeriodo) y etiqueta la columna con el periodo activo.
    function construirHojaDetalle(filas, periodoClave) {
        var colMonto = 'Monto Facturado' + etiquetaPeriodo(periodoClave);
        var datos = filas.map(function (p) {
            var ultimaP = ultimaProformaDe(p.agendamiento_id);
            var cobrado = montoFacturadoEnPeriodo(p.agendamiento_id, periodoClave);
            var cotiz   = ultimaP ? (parseFloat(ultimaP.monto_validado) || 0) : 0;
            var fila = {
                'Promotor': p.usuario  || '(sin asignar)',
                'PDV':      p.pdv      || p.codigo_pdv || '',
                'Contacto': p.contacto || '',
                'Empresa':  p.empresa  || ''
            };
            fila[colMonto] = cobrado || '';
            fila['Cotización inicial'] = cotiz || '';
            return fila;
        });
        var hoja = XLSX.utils.json_to_sheet(datos);
        hoja['!cols'] = [{ wch: 18 }, { wch: 18 }, { wch: 18 }, { wch: 22 }, { wch: 16 }, { wch: 16 }];
        estilizarEncabezado(hoja);
        return hoja;
    }

    // Exporta EXACTAMENTE lo marcado con checkbox (a nivel de promotor
    // completo o de fila suelta) — sin selección no se exporta nada, se le
    // pide al usuario que marque algo primero.
    function exportarExcel() {
        if (!agendamientosSeleccionados.size) {
            alert('Marca al menos un promotor o un agendamiento (checkbox) antes de descargar el Excel.');
            return;
        }

        var sel = document.getElementById('efPromoFiltroPeriodo');
        var periodoClave = sel ? sel.value : 'todos';

        var filasDetalle = pipeline
            .filter(function (p) { return agendamientosSeleccionados.has(String(p.agendamiento_id)); })
            .sort(function (a, b) {
                var ua = (a.usuario || '(sin asignar)'), ub = (b.usuario || '(sin asignar)');
                if (ua !== ub) return ua < ub ? -1 : 1;
                return (a.empresa || '').localeCompare(b.empresa || '');
            });

        var libro = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(libro, construirHojaDetalle(filasDetalle, periodoClave), 'Detalle por Agendamiento');

        var hoy = new Date();
        var dd  = String(hoy.getDate()).padStart(2, '0');
        var mm  = String(hoy.getMonth() + 1).padStart(2, '0');
        XLSX.writeFile(libro, 'factura_por_promotor_' + dd + '-' + mm + '-' + hoy.getFullYear() + '.xlsx');
    }

    // ── Carga de datos ────────────────────────────────────────────────
    function cargar() {
        document.getElementById('efPromoLista').innerHTML = '<div class="ef-vacio">Cargando...</div>';
        document.getElementById('efPromoDetalle').innerHTML = '<div class="ef-vacio">Selecciona un promotor.</div>';

        return Promise.all([
            fetch(GETTERS_BASE + 'proformas_listar.php').then(function (r) { return r.json(); }),
            // get_pagos_factura.php degrada a {"data":[]} si insert_pago_factura
            // todavía no existe en esta BD — no hace falta chequeo defensivo.
            fetch(GETTERS_BASE + 'get_pagos_factura.php').then(function (r) { return r.json(); })
        ])
            .then(function (resultados) {
                allRows = resultados[0].data || [];
                pipeline = ultimosCiclos(allRows);
                porAgendamiento = agruparPorAgendamiento(allRows);
                pagosPorProforma = agruparPagosPorProforma(resultados[1].data || []);
                poblarSelectorPeriodo();
                renderPromoLista();
                if (promActivo) renderPromoDetalle(promActivo);
            })
            .catch(function () {
                document.getElementById('efPromoLista').innerHTML = '<div class="ef-vacio">Error al cargar datos.</div>';
            });
    }

    // ── Eventos (delegación, un solo listener por contenedor) ─────────
    document.getElementById('efPromoSearch').addEventListener('input', renderPromoLista);
    // "PDV o empresa": recalcula lista, badges/totales y filas de la tabla
    // del promotor abierto (ver renderPromoDetalle).
    document.getElementById('efPromoBusquedaPdv').addEventListener('input', function () {
        renderPromoLista();
        if (promActivo) renderPromoDetalle(promActivo);
    });
    document.getElementById('efPromoFiltroPeriodo').addEventListener('change', function () {
        renderPromoLista();
        if (promActivo) renderPromoDetalle(promActivo);
    });

    document.getElementById('efPromoLista').addEventListener('click', function (e) {
        // Checkbox del promotor: selecciona/deselecciona todos sus
        // agendamientos (filtrados por PDV/empresa si hay filtro activo) —
        // no abre el detalle, solo marca.
        var check = e.target.closest('.ef-promo-check');
        if (check) {
            toggleSeleccionPromotor(check.dataset.usuario, check.checked);
            return;
        }
        var item = e.target.closest('.ef-promo-item');
        if (!item) return;
        promActivo = item.dataset.usuario;
        renderPromoLista();
        renderPromoDetalle(promActivo);
    });

    document.getElementById('efPromoDetalle').addEventListener('click', function (e) {
        var check = e.target.closest('.ef-row-check');
        if (check) {
            var id = String(check.dataset.agendamientoId);
            if (check.checked) agendamientosSeleccionados.add(id); else agendamientosSeleccionados.delete(id);
            renderPromoLista(); // sincroniza el checkbox/indeterminate del promotor en la lista izquierda
            return;
        }
        var btn = e.target.closest('.ef-btn-auditar');
        if (!btn) return;
        abrirAuditoria(btn.dataset.agendamientoId);
    });

    // "Seleccionar todo": solo lo visible/filtrado (respeta PDV/empresa y
    // el buscador de promotor de la lista izquierda).
    document.getElementById('efSeleccionarTodo').addEventListener('click', function () {
        var mapa = construirMapaPromotores();
        var q = (document.getElementById('efPromoSearch').value || '').toLowerCase().trim();
        Object.keys(mapa).forEach(function (u) {
            if (q && u.toLowerCase().indexOf(q) === -1) return;
            mapa[u].ids.forEach(function (id) { agendamientosSeleccionados.add(id); });
        });
        renderPromoLista();
        if (promActivo) renderPromoDetalle(promActivo);
    });

    document.getElementById('efDesmarcarTodo').addEventListener('click', function () {
        agendamientosSeleccionados.clear();
        renderPromoLista();
        if (promActivo) renderPromoDetalle(promActivo);
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

    document.getElementById('efPagoModalClose').addEventListener('click', cerrarPagoModal);
    document.getElementById('efPagoModalOverlay').addEventListener('click', function (e) {
        if (e.target.id === 'efPagoModalOverlay') cerrarPagoModal();
    });
    // Click en la foto del modal de pago → la misma foto, a pantalla
    // completa, en el lightbox ya existente (reusado tal cual).
    document.getElementById('efPagoModalImg').addEventListener('click', function (e) {
        if (!e.target.src) return;
        abrirLightbox(e.target.src);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (pagoModalAbierto) { cerrarPagoModal(); return; }
        if (document.getElementById('efLightbox').classList.contains('is-visible')) { cerrarLightbox(); return; }
        if (auditoriaAbierta) cerrarAuditoria();
    });

    document.getElementById('efActualizarProm').addEventListener('click', cargar);
    document.getElementById('efDescargarExcel').addEventListener('click', exportarExcel);

    window.FacturaRecargar = cargar;
    cargar();
})();
