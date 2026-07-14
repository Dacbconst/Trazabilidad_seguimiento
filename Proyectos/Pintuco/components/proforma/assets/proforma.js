(function () {
    'use strict';

    var app          = document.getElementById('proformaApp');
    var GETTERS_BASE = app.dataset.gettersBase;
    var MODULO_BASE  = app.dataset.moduloBase;
    var FOTO_BASE    = 'https://luckyecuadorweb.blob.core.windows.net/app/AppPintuco/Inserts/';

    var currentRows   = [];
    var filaAbiertaId = null;
    var grupoAbierto  = null; // nombre del promotor cuyo grupo está expandido (null = todos cerrados)
    var toastTimer    = null;

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------
    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function soloFecha(v) { return v ? v.split(' ')[0] : null; }
    function hoyISO() {
        var h = new Date();
        return h.getFullYear() + '-' + String(h.getMonth()+1).padStart(2,'0') + '-' + String(h.getDate()).padStart(2,'0');
    }
    function formatFecha(v) {
        var iso = soloFecha(v);
        if (!iso || iso === '0000-00-00') return '—';
        var p = iso.split('-');
        return p.length === 3 ? p[2]+'/'+p[1]+'/'+p[0] : iso;
    }
    function formatFechaHora(v) {
        if (!v) return '—';
        var pp = v.split(' ');
        return formatFecha(pp[0]) + (pp[1] ? ' ' + pp[1].slice(0,5) : '');
    }

    // Selector de Periodo con meses reales (mismo mecanismo que
    // poblarSelectorPeriodoPrincipal en contactados.js / poblarSelectorPeriodo
    // en factura.js, pedido explícito del usuario: mostrar solo los meses
    // que tienen datos, no "mes actual/anterior/últimos 3" fijos). Clave =
    // "YYYY-MM" (cadena vacía '' = sin filtro, "Cualquier fecha").
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

    // Arma las opciones a partir de los meses que realmente tienen datos
    // (misma fecha de referencia que usa filasFiltradas para el periodo:
    // proforma_fecha_registro, con contacto_fecha_registro como respaldo) +
    // el mes actual (siempre presente). Respeta el mes ya elegido al
    // refrescar. Mantiene "Cualquier fecha" (sin filtro) como default,
    // igual que antes.
    function poblarSelectorPeriodo() {
        var select = document.getElementById('proformaFiltroPeriodo');
        var valorPrevio = select.value;
        var actual = claveMesActual();

        var claves = {};
        claves[actual] = true;
        currentRows.forEach(function (p) {
            var clave = claveMes(p.proforma_fecha_registro || p.contacto_fecha_registro);
            if (clave) claves[clave] = true;
        });

        select.innerHTML = '<option value="">Cualquier fecha</option>';
        Object.keys(claves).sort().reverse().forEach(function (clave) {
            var opt = document.createElement('option');
            opt.value = clave;
            opt.textContent = etiquetaMes(clave);
            select.appendChild(opt);
        });

        var opciones = Array.prototype.map.call(select.options, function (o) { return o.value; });
        select.value = opciones.indexOf(valorPrevio) !== -1 ? valorPrevio : '';
    }

    // Columna "Fecha visita" de la lista/Excel: si el flujo ya avanzó a
    // proforma o más allá (fase 4+) y nunca hubo hora/técnico, no fue un
    // paso pendiente — fue un "no requiere agendamiento" a propósito (ver
    // construirTimeline). Mismo criterio que la nota "(no requiere)" del
    // timeline, pero acá el texto es "No requirió" en vez de un guion —
    // pedido explícito del usuario, para no confundirlo con "todavía no
    // se sabe". Un agendamiento genuinamente sin visita agendada (fase 1-2
    // real) sigue mostrando "—", sin cambios.
    function formatFechaVisita(p) {
        if (p.no_requiere_visita === 'SI') return 'No requirió';
        if (!(p.hora && p.tecnico) && getFase(p) >= 4) return 'No requirió'; // datos históricos sin el campo
        return formatFecha(p.fecha_agendamiento);
    }

    // ---------------------------------------------------------------
    // Punto azul "hay un cambio sin atender" — puramente local (localStorage,
    // por navegador/analista). "Cambio" = cualquier dato que mueva la fase o
    // requiera revisión (llegó foto, se movió a negociación/corrección/
    // aprobado/rechazado, llegó factura). Se guarda una "firma" del estado
    // visto la última vez; si no coincide con la actual, se asume sin
    // atender. Al abrir la fila (alternarFila) se marca como vista — eso
    // cuenta como "atenderla". Si vuelve a cambiar después, el punto reaparece.
    // ---------------------------------------------------------------
    var VISTO_KEY_PREFIX = 'pintuco_proforma_visto_';

    function firmaFila(p) {
        return [p.estado_proforma, p.evidencia, p.foto_factura, p.monto_validado, p.fecha_auditoria].join('|');
    }
    function tieneCambioSinAtender(p) {
        var guardada;
        try { guardada = localStorage.getItem(VISTO_KEY_PREFIX + p.agendamiento_id); } catch (e) { return false; }
        return guardada !== firmaFila(p);
    }
    function marcarVisto(p) {
        try { localStorage.setItem(VISTO_KEY_PREFIX + p.agendamiento_id, firmaFila(p)); } catch (e) {}
    }

    // ---------------------------------------------------------------
    // Mini ventana de foto (evidencia / factura)
    // ---------------------------------------------------------------
    function mostrarFoto(src) {
        document.getElementById('proformaFotoGrande').src = src;
        document.getElementById('proformaFotoOverlay').classList.add('is-visible');
    }
    function cerrarFoto() {
        document.getElementById('proformaFotoOverlay').classList.remove('is-visible');
        document.getElementById('proformaFotoGrande').src = '';
    }

    // ---------------------------------------------------------------
    // Toast
    // ---------------------------------------------------------------
    function mostrarToast(msg, esError) {
        var el = document.getElementById('proformaToast');
        el.textContent = msg;
        el.classList.toggle('is-error', !!esError);
        el.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.classList.remove('is-visible'); }, 2600);
    }

    // ---------------------------------------------------------------
    // Fase lógica
    // Fases 1 y 2 vienen del agendamiento (insert_proyectos_contacto).
    // Fases 3-5 vienen de la proforma (insert_proforma).
    // ---------------------------------------------------------------
    // Fase 3: se cumple AUTOMÁTICAMENTE cuando el promotor sube la foto de proforma.
    //   No requiere acción del analista — es solo un milestone en el timeline.
    // Fase 4 (Negociación): arranca de inmediato después de que llega la foto.
    //   El analista revisa, entra monto/obs y puede:
    //   - "Solicitar nueva evidencia" → nuevo ciclo (promotor manda otra foto).
    //   - "Finalizar Negociación" → aprueba y pasa a Fase 5.
    //   - "Rechazar" → estado terminal.
    // Fase 5: completo — se muestra foto_factura del celular (solo lectura).
    function getFase(p) {
        if (p.foto_factura || p.estado_proforma === 'aprobado') return 5;
        if (p.estado_proforma === 'rechazado') return 4;          // terminal en negociación
        if (p.id) return 4;                                        // proforma recibida → fase 4 automática
        // no_requiere_visita: el promotor marcó desde el móvil que este
        // contacto no necesita visita técnica — cuenta igual como fase 2.
        if ((p.hora && p.tecnico) || p.no_requiere_visita === 'SI') return 2;
        return 1;
    }

    // Bandeja de trabajo activo: una vez en Fase 5, la fila se saca del
    // módulo 24h después de que llegó la foto de factura — pedido explícito
    // del usuario para que Proforma solo muestre lo que todavía está en
    // proceso. No se pierde nada: el histórico completo sigue disponible
    // en Estado de Flujo (que nunca oculta nada), esto es solo un filtro de
    // vista acá. Referencia de tiempo: proforma_fecha_registro de la fila
    // que trae foto_factura (la ronda de fase 5 en sí).
    var VENTANA_FASE5_MS = 24 * 60 * 60 * 1000;
    function pasaronMasDe24h(fechaStr) {
        if (!fechaStr) return false;
        var ref = new Date(String(fechaStr).replace(' ', 'T'));
        if (isNaN(ref.getTime())) return false;
        return (Date.now() - ref.getTime()) > VENTANA_FASE5_MS;
    }

    // agendamiento_id → true para los que hay que ocultar del todo (no solo
    // su fila más reciente): si no se excluyeran TODOS sus ciclos, al
    // filtrar la fila vencida de fase 5 quedaría un ciclo anterior (fase 4)
    // como "el de mayor id" restante y reaparecería como si siguiera activo.
    function agendamientosOcultosPorFase5() {
        var ocultos = {};
        ultimosCiclos(currentRows).forEach(function (p) {
            if (getFase(p) === 5 && pasaronMasDe24h(p.proforma_fecha_registro)) {
                ocultos[p.agendamiento_id] = true;
            }
        });
        return ocultos;
    }

    function getBadge(p) {
        var f = getFase(p);
        if (f === 5) return { label: 'Fase 5', cls: 'is-aprobado' };
        if (f === 4) {
            if (p.estado_proforma === 'rechazado')              return { label: 'Rechazada', cls: 'is-rechazado' };
            if (p.estado_proforma === 'correccion_solicitada')  return { label: '⚠ Corrección', cls: 'is-correccion' };
            return { label: 'Fase 4', cls: 'is-en_proceso' };
        }
        if (f === 2) return { label: 'Fase 2', cls: 'is-pendiente' };
        return           { label: 'Fase 1', cls: 'is-pendiente' };
    }

    // ---------------------------------------------------------------
    // Filtros
    // ---------------------------------------------------------------
    function filasFiltradas() {
        var promotorSel  = document.getElementById('proformaFiltroPromotor').value;
        var estadoSel    = document.getElementById('proformaFiltroEstado').value;
        var periodoClave = document.getElementById('proformaFiltroPeriodo').value;
        var busqueda     = document.getElementById('proformaBusqueda').value.toLowerCase().trim();
        var ocultosFase5 = agendamientosOcultosPorFase5();

        return currentRows.filter(function (p) {
            // Pedido explícito del usuario (2026-07-14): Proforma es la
            // bandeja de trabajo de "ya llegó la foto de la cotización" —
            // un agendamiento que todavía no tiene ninguna proforma subida
            // (fase 1 "Contacto" o fase 2 "Agendado") no pertenece acá
            // todavía, aunque exista y esté agendado. Cuando llega su
            // primera foto (fase 4+) aparece con fase 1 y 2 ya marcadas
            // completas en el timeline (construirTimeline), sin más cambios.
            if (getFase(p) < 4) return false;
            if (ocultosFase5[p.agendamiento_id]) return false;
            if (promotorSel && p.usuario !== promotorSel) return false;

            if (estadoSel) {
                var f = getFase(p);
                if (estadoSel === 'en_proceso'            && !(f === 3 && p.estado_proforma !== 'rechazado')) return false;
                if (estadoSel === 'en_negociacion'        && f !== 4)                                         return false;
                if (estadoSel === 'correccion_solicitada' && p.estado_proforma !== 'correccion_solicitada')   return false;
                if (estadoSel === 'aprobado'              && p.estado_proforma !== 'aprobado')                return false;
                if (estadoSel === 'rechazado'             && p.estado_proforma !== 'rechazado')               return false;
            }

            // Clave "YYYY-MM" exacta (ver poblarSelectorPeriodo) — mismo mes
            // y año, no un rango "desde" abierto hacia adelante.
            if (periodoClave) {
                var ref = p.proforma_fecha_registro || p.contacto_fecha_registro;
                if (claveMes(ref) !== periodoClave) return false;
            }

            if (busqueda) {
                var haystack = [p.pdv, p.codigo_pdv, p.empresa, p.contacto].join(' ').toLowerCase();
                if (haystack.indexOf(busqueda) === -1) return false;
            }

            return true;
        });
    }

    // ---------------------------------------------------------------
    // Opciones de promotor
    // ---------------------------------------------------------------
    function construirOpcionesPromotor() {
        var select = document.getElementById('proformaFiltroPromotor');
        var prev   = select.value;
        select.innerHTML = '<option value="">Todos los promotores</option>';
        var vistos = {};
        currentRows.forEach(function (p) {
            if (p.usuario && !vistos[p.usuario]) {
                vistos[p.usuario] = true;
                var o = document.createElement('option');
                o.value = o.textContent = p.usuario;
                select.appendChild(o);
            }
        });
        if (vistos[prev]) select.value = prev;
    }

    // ---------------------------------------------------------------
    // Deduplicación: el getter devuelve TODOS los ciclos de insert_proforma.
    // Para la lista principal solo queremos el ciclo más reciente por
    // id_agendamiento (el de mayor id). Los ciclos anteriores se cargan
    // por separado cuando el analista expande una fila (historial gris).
    // ---------------------------------------------------------------
    function ultimosCiclos(rows) {
        // Clave = agendamiento_id (c.id, siempre presente aunque no haya proforma).
        // Si hay múltiples ciclos de negociación para un mismo agendamiento,
        // se queda con el de mayor p.id (el ciclo más reciente).
        // Si no hay proforma aún (p.id = null), parseInt da NaN → 0, y esa
        // única fila es el máximo para ese agendamiento.
        var maximo = {};
        rows.forEach(function (p) {
            var key = p.agendamiento_id;
            var pid = parseInt(p.id, 10) || 0;
            if (maximo[key] === undefined || pid > maximo[key]) {
                maximo[key] = pid;
            }
        });
        return rows.filter(function (p) {
            var pid = parseInt(p.id, 10) || 0;
            return pid === maximo[p.agendamiento_id];
        });
    }

    // ---------------------------------------------------------------
    // Agrupación por promotor
    // ---------------------------------------------------------------
    function agruparPorPromotor(rows) {
        var grupos = {};
        rows.forEach(function (p) {
            var k = p.usuario || '(Sin promotor)';
            if (!grupos[k]) grupos[k] = [];
            grupos[k].push(p);
        });
        return grupos;
    }

    // ---------------------------------------------------------------
    // Render principal
    // ---------------------------------------------------------------
    function renderizar() {
        var container = document.getElementById('proformaGrupos');
        container.innerHTML = '';

        var filas = filasFiltradas();
        if (!filas.length) {
            var vacio = document.createElement('div');
            vacio.className = 'proforma-vacio';
            vacio.textContent = 'Sin proformas que coincidan con el filtro.';
            container.appendChild(vacio);
            return;
        }

        var grupos = agruparPorPromotor(ultimosCiclos(filas));
        Object.keys(grupos).sort().forEach(function (promotor) {
            container.appendChild(construirGrupo(promotor, grupos[promotor]));
        });
    }

    // ---------------------------------------------------------------
    // Grupo de promotor
    // ---------------------------------------------------------------
    function construirGrupo(promotor, filas) {
        var wrap = document.createElement('div');
        // Acordeón por promotor: arranca cerrado, el usuario lo abre a mano
        // y al abrir uno se cierra el que estaba abierto (mutuamente
        // excluyentes) — mismo criterio que el acordeón de mantenimiento
        // fotográfico.
        wrap.className = 'proforma-grupo' + (promotor === grupoAbierto ? '' : ' is-cerrado');

        // Header de grupo
        var hdr = document.createElement('div');
        hdr.className = 'proforma-grupo-header';

        var avatar = document.createElement('div');
        avatar.className = 'proforma-grupo-avatar';
        avatar.innerHTML = '<i class="glyphicon glyphicon-user"></i>';

        var info = document.createElement('div');
        info.className = 'proforma-grupo-info';

        var nombre = document.createElement('span');
        nombre.className = 'proforma-grupo-nombre';
        nombre.textContent = 'Promotor: ' + promotor;

        var count = document.createElement('span');
        count.className = 'proforma-grupo-count';
        count.textContent = filas.length + ' agendamiento' + (filas.length !== 1 ? 's' : '');

        info.appendChild(nombre);
        info.appendChild(count);

        var chevron = document.createElement('i');
        chevron.className = 'glyphicon glyphicon-chevron-up proforma-grupo-chevron';

        hdr.appendChild(avatar);
        hdr.appendChild(info);
        hdr.appendChild(chevron);

        hdr.addEventListener('click', function () {
            grupoAbierto = (grupoAbierto === promotor) ? null : promotor;
            renderizar();
        });

        wrap.appendChild(hdr);

        // Sub-header de columnas
        var sub = document.createElement('div');
        sub.className = 'proforma-grupo-subheader';
        sub.innerHTML =
            '<span class="proforma-sh-cliente">Empresa / Dirección</span>'
            + '<span class="proforma-sh-fecha">Fecha visita</span>'
            + '<span class="proforma-sh-estado">Estado</span>'
            + '<span class="proforma-sh-acciones">Acciones</span>';
        wrap.appendChild(sub);

        // Filas del grupo
        var body = document.createElement('div');
        body.className = 'proforma-grupo-body';
        filas.forEach(function (p) {
            body.appendChild(construirFilaWrap(p));
        });
        wrap.appendChild(body);

        return wrap;
    }

    // ---------------------------------------------------------------
    // Fila de agendamiento + panel de detalle
    // ---------------------------------------------------------------
    function construirFilaWrap(p) {
        var wrap = document.createElement('div');
        wrap.className = 'proforma-gfila-wrap';

        // Fila principal
        var fila = document.createElement('div');
        fila.className = 'proforma-gfila';
        fila.dataset.id = p.agendamiento_id;  // siempre presente (c.id)
        if (filaAbiertaId !== null && filaAbiertaId === p.agendamiento_id) fila.classList.add('is-abierta');

        var badge = getBadge(p);

        var cCliente = document.createElement('div');
        cCliente.className = 'proforma-gfila-cliente';
        var empresa = document.createElement('div');
        empresa.className = 'proforma-gfila-empresa';
        var empresaTexto = document.createElement('span');
        empresaTexto.textContent = p.empresa || p.contacto || '—';
        empresa.appendChild(empresaTexto);
        // Punto azul: hay un cambio (foto nueva, cambio de fase, etc.) que
        // el analista todavía no revisó en esta sesión — ver
        // tieneCambioSinAtender()/marcarVisto() más arriba.
        if (tieneCambioSinAtender(p)) {
            var dot = document.createElement('span');
            dot.className = 'proforma-gfila-dot';
            dot.title = 'Hay un cambio sin revisar';
            empresa.appendChild(dot);
        }
        // Dirección real guardada en BD (c.direccion), no el nombre del PDV
        // — el promotor ya se ve una sola vez en el header del grupo.
        var direccionEl = document.createElement('div');
        direccionEl.className = 'proforma-gfila-pdv';
        direccionEl.textContent = p.direccion || '—';
        cCliente.appendChild(empresa);
        cCliente.appendChild(direccionEl);

        var cFecha = document.createElement('div');
        cFecha.className = 'proforma-gfila-fecha';
        cFecha.textContent = formatFechaVisita(p);

        var cEstado = document.createElement('div');
        cEstado.className = 'proforma-gfila-estado';
        var badgeEl = document.createElement('span');
        badgeEl.className = 'proforma-badge ' + badge.cls;
        badgeEl.textContent = badge.label;
        cEstado.appendChild(badgeEl);

        // Acciones: el ojo es puramente visual/indicativo — clickear en
        // cualquier parte de la fila (incluido el ojo, por burbujeo normal
        // del evento) abre el mismo detalle.
        var cAcciones = document.createElement('div');
        cAcciones.className = 'proforma-gfila-acciones';
        cAcciones.innerHTML = '<i class="glyphicon glyphicon-eye-open"></i>';

        fila.appendChild(cCliente);
        fila.appendChild(cFecha);
        fila.appendChild(cEstado);
        fila.appendChild(cAcciones);

        fila.addEventListener('click', function () { alternarFila(p); });

        wrap.appendChild(fila);

        // Panel de detalle si está abierta
        if (filaAbiertaId !== null && filaAbiertaId === p.agendamiento_id) {
            wrap.appendChild(construirDetalle(p));
        }

        return wrap;
    }

    function alternarFila(p) {
        var agendamientoId = p.agendamiento_id;
        var abriendo = filaAbiertaId !== agendamientoId;
        filaAbiertaId = abriendo ? agendamientoId : null;
        // Abrir la fila cuenta como "atenderla": el punto azul de esa fila
        // se apaga y no vuelve a aparecer hasta que el estado cambie de
        // nuevo (nueva foto, cambio de fase, etc.).
        if (abriendo) marcarVisto(p);
        renderizar();
        if (filaAbiertaId !== null) {
            setTimeout(function () {
                var el = document.querySelector('.proforma-gfila[data-id="' + agendamientoId + '"]');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 60);
        }
    }

    // ---------------------------------------------------------------
    // Panel de detalle (3 columnas)
    // ---------------------------------------------------------------
    function construirDetalle(p) {
        var panel = document.createElement('div');
        panel.className = 'proforma-gdetalle';

        var grid = document.createElement('div');
        grid.className = 'proforma-gdetalle-grid';
        grid.innerHTML = '<div class="proforma-gdetalle-cargando">Cargando historial…</div>';
        panel.appendChild(grid);

        // Un solo fetch compartido por las 3 columnas: el timeline (fecha+
        // monto del último ciclo con monto) y el historial de auditoría
        // necesitan ver TODOS los ciclos del agendamiento, no solo la fila
        // activa — que ahora casi siempre está vacía esperando la próxima foto.
        function pintar(ciclos) {
            grid.innerHTML = '';
            grid.appendChild(construirTimeline(p, ciclos));

            // Evidencia+Auditoría van dentro de un wrapper propio (columnas
            // 2+3): la foto y el formulario siguen intactos ahí debajo — el
            // bloqueo es un simple blur + pointer-events:none sobre ESTE
            // wrapper (ver mecanica-bloqueo-foto.md), no un overlay que lo
            // reemplace o lo tape con contenido propio.
            var central = document.createElement('div');
            central.className = 'proforma-gdetalle-central';
            central.appendChild(construirPanelEvidencia(p, function () { cargarProformas(); }));
            central.appendChild(construirPanelAuditoria(p, ciclos, function () { cargarProformas(); }));
            grid.appendChild(central);

            // Corrección pendiente: la foto está intacta en la BD (nunca se
            // borró), solo queda borrosa/deshabilitada detrás del modal
            // hasta que llegue la nueva o se cancele el pedido. El modal se
            // agrega como hermano de "central" (no dentro) para que el blur
            // de "central" no lo afecte a él también.
            if (p.estado_proforma === 'correccion_solicitada') {
                central.classList.add('is-bloqueado');
                panel.appendChild(construirModalBloqueo(p, function () { cargarProformas(); }));
            }
        }

        // Un solo fetch compartido por las 3 columnas: el timeline (fecha+
        // monto del último ciclo con monto) y el historial de auditoría
        // necesitan ver TODOS los ciclos del agendamiento, no solo la fila
        // activa — que ahora casi siempre está vacía esperando la próxima foto.
        fetch(GETTERS_BASE + 'proformas_listar.php?id_agendamiento=' + encodeURIComponent(p.agendamiento_id))
            .then(function (r) { return r.json(); })
            .then(function (json) { pintar(json.data || []); })
            .catch(function () { pintar([p]); });

        return panel;
    }

    // ---------------------------------------------------------------
    // Columna 1 — Timeline de 5 fases
    // ---------------------------------------------------------------
    // Ciclo de mayor id, dentro de "ciclos", que ya tiene un monto guardado
    // — es "la última ronda de negociación que se cerró", sin importar si la
    // fila activa (la de mayor id absoluto) está vacía esperando otra foto.
    function ultimoCicloConMonto(ciclos) {
        var candidatos = ciclos
            .filter(function (c) { return !!c.monto_validado; })
            .sort(function (a, b) { return (parseInt(b.id, 10) || 0) - (parseInt(a.id, 10) || 0); });
        return candidatos[0] || null;
    }

    // Igual que arriba pero para evidencia: un agendamiento puede tener
    // varias fotos de proforma a lo largo de sus rondas de negociación —
    // "Proforma recibida" debe reflejar la más reciente que SÍ llegó, no la
    // fila activa (que casi siempre está vacía esperando la próxima).
    function ultimoCicloConEvidencia(ciclos) {
        var candidatos = ciclos
            .filter(function (c) { return !!c.evidencia; })
            .sort(function (a, b) { return (parseInt(b.id, 10) || 0) - (parseInt(a.id, 10) || 0); });
        return candidatos[0] || null;
    }

    // Igual patrón, para saber CUÁNDO llegó la foto de factura (fase 5).
    function ultimoCicloConFactura(ciclos) {
        var candidatos = ciclos
            .filter(function (c) { return !!c.foto_factura; })
            .sort(function (a, b) { return (parseInt(b.id, 10) || 0) - (parseInt(a.id, 10) || 0); });
        return candidatos[0] || null;
    }

    function formatMonto(valor) {
        var n = parseFloat(valor);
        if (isNaN(n)) return null;
        return '$' + n.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function construirTimeline(p, ciclos) {
        var fase = getFase(p);
        var ultimo = ultimoCicloConMonto(ciclos);
        var ultimaEvidencia = ultimoCicloConEvidencia(ciclos);
        var ultimaFactura = ultimoCicloConFactura(ciclos);

        // Todas las RONDAS DE NEGOCIACIÓN (una por cada vez que el analista
        // guardó un monto), ascendente — esto va en "Fase 4: Negociación",
        // no en "Fase 3": la 3 es solo el milestone de "llegó la primera
        // proforma", la 4 es donde se listan los envíos con su monto.
        var rondasNegociacion = ciclos
            .filter(function (c) { return !!c.monto_validado; })
            .sort(function (a, b) { return (parseInt(a.id, 10) || 0) - (parseInt(b.id, 10) || 0); })
            .map(function (c) {
                return {
                    // fecha_auditoria solo se llena cuando el analista corre
                    // 'guardar'/'rechazar' en la web. Si el monto vino puesto
                    // directo por el promotor desde el celular (estado aún
                    // 'en_proceso'), fecha_auditoria queda null — se usa la
                    // fecha de registro de esa fila como respaldo (mismo
                    // patrón ya usado en Fase 5 para ultimaFactura).
                    fecha: formatFechaHora(c.fecha_auditoria || c.proforma_fecha_registro),
                    monto: formatMonto(c.monto_validado)
                };
            });

        var wrap = document.createElement('div');

        var titulo = document.createElement('div');
        titulo.className = 'proforma-seccion-titulo';
        titulo.textContent = 'Progreso del flujo';
        wrap.appendChild(titulo);

        var defs = [
            { num: 1, label: 'Contacto inicial',
              fecha: formatFecha(p.contacto_fecha_registro),
              completa: true, activa: fase === 1 },
            // "No requiere agendamiento": el analista puede saltarse la
            // visita y mandar la proforma directo — si el flujo YA avanzó
            // más allá de agendamiento (fase 4+) pero nunca hubo
            // hora/técnico, no fue un paso "pendiente", fue un paso
            // saltado a propósito. Se pinta completo igual que los demás
            // (con su check) pero con la nota "(no requiere)" en vez de
            // una fecha — pedido explícito del usuario, no dejarlo vacío.
            { num: 2, label: 'Visita agendada',
              fecha: formatFecha(p.fecha_agendamiento) + (p.hora ? ' · ' + p.hora.slice(0,5) : ''),
              completa: !!(p.hora && p.tecnico) || p.no_requiere_visita === 'SI' || fase >= 4,
              omitida: p.no_requiere_visita === 'SI' || (!(p.hora && p.tecnico) && fase >= 4),
              activa: fase === 2 },
            { num: 3, label: 'Proforma recibida',
              // Una sola fecha — la de la evidencia más reciente. El listado
              // de rondas con monto va en Fase 4, no aquí.
              fecha: ultimaEvidencia ? formatFechaHora(ultimaEvidencia.proforma_fecha_registro) : null,
              completa: !!ultimaEvidencia,
              activa: false },    // nunca "activa": es solo un milestone
            // Fase 4: el listado de rondas de negociación (fecha+monto de
            // CADA vez que se guardó un monto), no solo la última.
            { num: 4, label: 'Negociación',
              envios: rondasNegociacion,
              completa: fase === 5, activa: fase === 4 },
            // Fase 5: fecha en que llegó la foto de factura + el monto de la
            // última proforma con monto registrado (mismo valor que "monto
            // vigente" en otras vistas, no un monto propio de la fila de
            // factura — esa fila casi nunca tiene monto_validado propio).
            { num: 5, label: 'Completado',
              // Ya NO cae en ultimaFactura.fecha_auditoria — 'cerrar_plan_pago'
              // (módulo Factura) reusa esa columna para la fecha de cierre del
              // plan de pago, en esta misma fila. Mismo ajuste que en factura.js.
              envios: ultimaFactura ? [{
                  fecha: formatFechaHora(ultimaFactura.proforma_fecha_registro),
                  monto: ultimo ? formatMonto(ultimo.monto_validado) : null
              }] : [],
              completa: fase === 5, activa: false }
        ];

        var list = document.createElement('div');
        list.className = 'proforma-fase-list';

        defs.forEach(function (f) {
            var item = document.createElement('div');
            item.className = 'proforma-fase-item '
                + (f.completa ? 'is-completa' : f.activa ? 'is-activa' : 'is-pendiente');

            var punto = document.createElement('div');
            punto.className = 'proforma-fase-punto';
            if (f.completa) punto.innerHTML = '<i class="glyphicon glyphicon-ok"></i>';
            item.appendChild(punto);

            var texto = document.createElement('div');
            texto.className = 'proforma-fase-texto';

            var lbl = document.createElement('div');
            lbl.className = 'proforma-fase-label';
            lbl.textContent = 'Fase ' + f.num + ': ' + f.label;
            if (f.omitida) {
                var notaOmitida = document.createElement('span');
                notaOmitida.className = 'proforma-fase-omitida';
                notaOmitida.textContent = ' (no requiere)';
                lbl.appendChild(notaOmitida);
            }
            texto.appendChild(lbl);

            if (f.envios && f.envios.length) {
                // Un renglón por cada envío de proforma (ronda), no solo el
                // último — el más reciente se resalta en negrita.
                f.envios.forEach(function (envio, i) {
                    var eEl = document.createElement('div');
                    eEl.className = 'proforma-fase-fecha' + (i === f.envios.length - 1 ? ' is-ultimo-dato' : '');
                    eEl.textContent = envio.fecha + (envio.monto ? ' · ' + envio.monto : '');
                    texto.appendChild(eEl);
                });
            } else if (f.fecha && f.fecha !== '—') {
                var fEl = document.createElement('div');
                fEl.className = 'proforma-fase-fecha' + (f.destacar ? ' is-ultimo-dato' : '');
                fEl.textContent = f.fecha + (f.extra ? ' · ' + f.extra : '');
                texto.appendChild(fEl);
            }

            item.appendChild(texto);
            list.appendChild(item);
        });

        wrap.appendChild(list);
        return wrap;
    }

    // ---------------------------------------------------------------
    // Modal de bloqueo (absolute, centrado dentro del panel de detalle de
    // ESE agendamiento): corrección solicitada. Ver mecanica-bloqueo-foto.md
    // — un solo booleano lógico ("locked" = estado_proforma ===
    // 'correccion_solicitada'), efecto visual es blur + pointer-events:none
    // en el wrapper de Evidencia+Auditoría (aplicado por el llamador vía la
    // clase .is-bloqueado) y este modal centrado sobre el panel (".proforma-
    // gdetalle", que es position:relative), no sobre toda la página.
    // ---------------------------------------------------------------
    function construirModalBloqueo(p, onResuelto) {
        var overlay = document.createElement('div');
        overlay.className = 'proforma-bloqueo-modal-overlay';

        var card = document.createElement('div');
        card.className = 'proforma-bloqueo-modal-card';
        card.innerHTML =
            '<i class="glyphicon glyphicon-warning-sign"></i>'
            + '<div class="proforma-bloqueo-texto">Solicitud de cambio de foto en curso.<br>Esta sección está bloqueada temporalmente.</div>';

        var btnCancelar = document.createElement('button');
        btnCancelar.type = 'button';
        btnCancelar.className = 'proforma-btn-rechazar-rojo';
        btnCancelar.textContent = 'Cancelar solicitud';
        btnCancelar.addEventListener('click', function () {
            btnCancelar.disabled = true;
            var body = new URLSearchParams();
            body.set('id', p.id);
            body.set('accion', 'cancelar_correccion');
            fetch(GETTERS_BASE + 'update_proforma.php', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (json.success) {
                        mostrarToast('Solicitud cancelada.');
                        onResuelto();
                    } else {
                        mostrarToast(json.message || 'No se pudo cancelar.', true);
                        btnCancelar.disabled = false;
                    }
                })
                .catch(function () { mostrarToast('Error de conexión.', true); btnCancelar.disabled = false; });
        });
        card.appendChild(btnCancelar);
        overlay.appendChild(card);

        return overlay;
    }

    // ---------------------------------------------------------------
    // Cierre de proceso: para cuando la negociación se queda en pura
    // cotización y nunca llega a factura (cliente ya no quiso, se fue con
    // la competencia, etc.) — pedido explícito del usuario. Reusa la
    // acción 'rechazar' que ya existía en update_proforma.php (terminal,
    // estado_proforma='rechazado', ya soportada por getFase/getBadge/el
    // filtro "Estado auditoría" → "Rechazada") pero que nunca tuvo un
    // botón conectado en esta pantalla. El motivo queda guardado en su
    // propia columna motivo_cierre, no en observaciones_auditoria (esa es
    // de la acción 'guardar' — ver update_proforma.php).
    // ---------------------------------------------------------------
    // obtenerMontoActual (opcional): cuando existe el input de "Monto
    // cotizado" en pantalla, lee su valor EN VIVO en vez del p.monto_validado
    // ya obsoleto que llegó del último fetch — sin esto, un monto recién
    // tecleado pero no guardado con "Guardar cambios" se perdía en silencio
    // al cerrar el proceso directamente.
    function agregarBotonCerrarProceso(wrap, p, onResuelto, obtenerMontoActual) {
        var cont = document.createElement('div');
        cont.className = 'proforma-cerrar-proceso-wrap';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'proforma-btn-cerrar-proceso';
        btn.innerHTML = '<i class="glyphicon glyphicon-ban-circle"></i> Cerrar proceso';
        btn.addEventListener('click', function () { mostrarConfirmacionCierre(p, wrap, onResuelto, obtenerMontoActual); });

        cont.appendChild(btn);
        wrap.appendChild(cont);
    }

    function mostrarConfirmacionCierre(p, wrap, onResuelto, obtenerMontoActual) {
        var overlay = document.createElement('div');
        overlay.className = 'proforma-bloqueo-modal-overlay';

        var card = document.createElement('div');
        card.className = 'proforma-cierre-modal-card';
        card.innerHTML =
            '<i class="glyphicon glyphicon-question-sign"></i>'
            + '<div class="proforma-cierre-modal-titulo">¿Cerrar este proceso?</div>'
            + '<div class="proforma-cierre-modal-texto">'
            +   'Se va a marcar como <strong>Rechazada</strong> (cerrada sin llegar a factura) y ya no se podrá seguir editando. Esta acción no se puede deshacer.'
            + '</div>';

        var campoObs = document.createElement('div');
        campoObs.className = 'proforma-campo';
        var labelObs = document.createElement('label');
        labelObs.textContent = 'Motivo del cierre *';
        var inputObs = document.createElement('textarea');
        inputObs.className = 'form-control';
        inputObs.rows = 3;
        inputObs.placeholder = 'Explica por qué se cierra este proceso...';
        campoObs.appendChild(labelObs);
        campoObs.appendChild(inputObs);
        card.appendChild(campoObs);

        var errorObs = document.createElement('div');
        errorObs.className = 'proforma-cierre-modal-error';
        card.appendChild(errorObs);

        var acciones = document.createElement('div');
        acciones.className = 'proforma-cierre-modal-acciones';

        var btnCancelar = document.createElement('button');
        btnCancelar.type = 'button';
        btnCancelar.className = 'btn';
        btnCancelar.textContent = 'Cancelar';
        btnCancelar.addEventListener('click', function () { overlay.remove(); });

        var btnConfirmar = document.createElement('button');
        btnConfirmar.type = 'button';
        btnConfirmar.className = 'proforma-btn-rechazar-rojo';
        btnConfirmar.textContent = 'Cerrar proceso';
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
            var montoActual = (typeof obtenerMontoActual === 'function' ? obtenerMontoActual() : null) || p.monto_validado || '';
            body.set('id', p.id);
            body.set('accion', 'rechazar');
            body.set('monto', montoActual);
            body.set('motivo_cierre', motivo);
            fetch(GETTERS_BASE + 'update_proforma.php', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (json.success) {
                        mostrarToast('Proceso cerrado.');
                        onResuelto();
                    } else {
                        mostrarToast(json.message || 'No se pudo cerrar.', true);
                        btnConfirmar.disabled = false;
                        btnCancelar.disabled = false;
                    }
                })
                .catch(function () {
                    mostrarToast('Error de conexión.', true);
                    btnConfirmar.disabled = false;
                    btnCancelar.disabled = false;
                });
        });

        acciones.appendChild(btnCancelar);
        acciones.appendChild(btnConfirmar);
        card.appendChild(acciones);

        overlay.appendChild(card);
        wrap.appendChild(overlay);
        inputObs.focus();
    }

    // ---------------------------------------------------------------
    // Columna 2 — Evidencia fotográfica
    // ---------------------------------------------------------------
    function construirPanelEvidencia(p, onResuelto) {
        var wrap = document.createElement('div');

        var titulo = document.createElement('div');
        titulo.className = 'proforma-seccion-titulo';
        titulo.textContent = 'Evidencia fotográfica';
        wrap.appendChild(titulo);

        if (p.evidencia) {
            var img = document.createElement('img');
            img.className = 'proforma-evidencia-foto';
            img.src = FOTO_BASE + p.evidencia;
            img.alt = 'Evidencia de la proforma';
            img.addEventListener('click', function () { mostrarFoto(img.src); });
            img.addEventListener('error', function () {
                img.style.display = 'none';
                var av = document.createElement('div');
                av.className = 'proforma-evidencia-vacia';
                av.innerHTML = '<i class="glyphicon glyphicon-picture"></i><br>Foto no disponible.<br><small style="word-break:break-all;opacity:.7">' + esc(img.src) + '</small>';
                img.parentNode.insertBefore(av, img.nextSibling);
            });
            wrap.appendChild(img);

            // Disponible en TODO momento mientras haya foto y el ciclo no
            // haya terminado (fase 5) — antes solo existía en el paso
            // previo a "Aceptar"; si el analista notaba recién en el
            // formulario de monto que la foto está mal, quedaba sin forma
            // de pedir un reenvío. Mismo endpoint que ya existía
            // (rechazar_calidad): no genera histórico, es corrección de foto.
            if (getFase(p) !== 5) {
                var btnRechazarFoto = document.createElement('button');
                btnRechazarFoto.type = 'button';
                btnRechazarFoto.className = 'proforma-btn-rechazar-foto';
                btnRechazarFoto.innerHTML = '<i class="glyphicon glyphicon-remove"></i> Rechazar / Pedir nueva foto';
                btnRechazarFoto.addEventListener('click', function () {
                    btnRechazarFoto.disabled = true;
                    var body = new URLSearchParams();
                    body.set('id', p.id);
                    body.set('accion', 'rechazar_calidad');
                    fetch(GETTERS_BASE + 'update_proforma.php', { method: 'POST', body: body })
                        .then(function (r) { return r.json(); })
                        .then(function (json) {
                            if (json.success) {
                                mostrarToast('Se pidió una nueva foto.');
                                onResuelto();
                            } else {
                                mostrarToast(json.message || 'No se pudo actualizar.', true);
                                btnRechazarFoto.disabled = false;
                            }
                        })
                        .catch(function () { mostrarToast('Error de conexión.', true); btnRechazarFoto.disabled = false; });
                });
                wrap.appendChild(btnRechazarFoto);
            }
        } else {
            var sin = document.createElement('div');
            sin.className = 'proforma-evidencia-vacia';
            sin.innerHTML = '<i class="glyphicon glyphicon-camera"></i><br>Sin foto de evidencia.';
            wrap.appendChild(sin);
        }

        var lat = parseFloat(p.latitud), lng = parseFloat(p.longitud);
        if (lat && lng) {
            var link = document.createElement('a');
            link.className = 'proforma-evidencia-ubicacion';
            link.href = 'https://maps.google.com/maps?q=' + lat + ',' + lng;
            link.target = '_blank';
            link.rel = 'noopener';
            link.innerHTML = '<i class="glyphicon glyphicon-map-marker"></i> Ver ubicación GPS';
            wrap.appendChild(link);
        }

        if (p.caracteristica_visita || p.acompanamiento_tecnico) {
            var rep = document.createElement('div');
            rep.className = 'proforma-reporte';
            if (p.caracteristica_visita) {
                var c = document.createElement('p');
                c.textContent = p.caracteristica_visita;
                rep.appendChild(c);
            }
            var ac = document.createElement('p');
            ac.className = 'proforma-reporte-meta';
            ac.textContent = 'Acompañamiento técnico: ' + (p.acompanamiento_tecnico === 'SI' ? 'Sí' : 'No');
            rep.appendChild(ac);
            wrap.appendChild(rep);
        }

        return wrap;
    }

    // ---------------------------------------------------------------
    // Formato de moneda para el input de monto: acepta comas de miles y
    // punto decimal mientras se escribe; al perder foco se reformatea bonito.
    // ---------------------------------------------------------------
    function limpiarMonto(texto) {
        var limpio = String(texto || '').replace(/[^0-9.]/g, '');
        var partes = limpio.split('.');
        if (partes.length > 2) limpio = partes[0] + '.' + partes.slice(1).join('');
        return limpio;
    }

    // ---------------------------------------------------------------
    // Columna 3 — Auditoría (con historial de ciclos en fase 4)
    // ---------------------------------------------------------------
    function construirPanelAuditoria(p, ciclos, onResuelto) {
        var fase = getFase(p);
        var wrap = document.createElement('div');

        var titulo = document.createElement('div');
        titulo.className = 'proforma-seccion-titulo';
        titulo.textContent = 'Acciones de auditoría';
        wrap.appendChild(titulo);

        // ── Fase 5: completado ──────────────────────────────────────────────
        if (fase === 5) {
            if (p.foto_factura) {
                var tituloFact = document.createElement('div');
                tituloFact.className = 'proforma-seccion-titulo';
                tituloFact.textContent = 'Foto de factura';
                wrap.appendChild(tituloFact);

                var imgFact = document.createElement('img');
                imgFact.className = 'proforma-evidencia-foto';
                // p.foto_factura ya incluye el prefijo "Factura/" en el valor
                // guardado por el móvil (confirmado 2026-07-03 con URL real:
                // agregar 'Factura/' de nuevo aquí duplica la carpeta y da 404).
                imgFact.src = FOTO_BASE + p.foto_factura;
                imgFact.alt = 'Foto de factura';
                imgFact.addEventListener('click', function () { mostrarFoto(imgFact.src); });
                imgFact.addEventListener('error', function () {
                    imgFact.style.display = 'none';
                    var av = document.createElement('div');
                    av.className = 'proforma-evidencia-vacia';
                    av.innerHTML = '<i class="glyphicon glyphicon-picture"></i><br>Foto de factura no disponible.';
                    imgFact.parentNode.insertBefore(av, imgFact.nextSibling);
                });
                wrap.appendChild(imgFact);

                var terminado = document.createElement('div');
                terminado.className = 'proforma-fases-terminadas';
                terminado.innerHTML = '<i class="glyphicon glyphicon-ok-sign"></i> Fases terminadas';
                wrap.appendChild(terminado);
            } else {
                var espFact = document.createElement('div');
                espFact.className = 'proforma-auditoria-cerrada';
                espFact.innerHTML = '<i class="glyphicon glyphicon-ok-circle"></i> Negociación finalizada — esperando foto de factura del promotor.';
                wrap.appendChild(espFact);
            }
            return wrap;
        }

        // ── Fase 1-2: sin proforma aún ─────────────────────────────────────
        if (fase <= 2) {
            var esp = document.createElement('div');
            esp.className = 'proforma-auditoria-cerrada';
            esp.textContent = 'Pendiente de que el promotor suba la proforma.';
            wrap.appendChild(esp);
            return wrap;
        }

        // ── Rechazado (terminal en fase 3) ─────────────────────────────────
        if (p.estado_proforma === 'rechazado') {
            var recMsg = document.createElement('div');
            recMsg.className = 'proforma-auditoria-cerrada is-rechazado';
            recMsg.innerHTML = '<i class="glyphicon glyphicon-remove"></i> Rechazada / Evidencia falsa.';
            wrap.appendChild(recMsg);
            return wrap;
        }

        // ── Fase 4: historial de ciclos anteriores ─────────────────────────
        // "ciclos" trae TODOS los ciclos del agendamiento. Los anteriores al
        // activo (id != p.id) se muestran como tarjetas; el más reciente que
        // ya tiene monto se resalta (mismo dato que ve el timeline).
        var CICLO_LABEL = { en_negociacion: 'Negociado', aprobado: 'Finalizado', rechazado: 'Rechazado', en_proceso: 'En revisión' };
        var ultimo = ultimoCicloConMonto(ciclos);
        var anteriores = ciclos.filter(function (h) { return parseInt(h.id, 10) !== parseInt(p.id, 10) && h.monto_validado; });
        if (anteriores.length) {
            var historialWrap = document.createElement('div');
            historialWrap.className = 'proforma-historial';
            anteriores.forEach(function (h, idx) {
                var esUltimo = ultimo && parseInt(h.id, 10) === parseInt(ultimo.id, 10);
                var lbl = CICLO_LABEL[h.estado_proforma] || h.estado_proforma;
                var ciclo = document.createElement('div');
                ciclo.className = 'proforma-historial-ciclo' + (esUltimo ? ' is-ultimo' : '');

                // La foto de esa ronda nunca se borra (solo se limpia la
                // fila NUEVA que se crea al guardar) — se conserva en la BD
                // y se muestra aquí como miniatura clickeable.
                if (h.evidencia) {
                    var mini = document.createElement('img');
                    mini.className = 'proforma-historial-ciclo-mini';
                    mini.src = FOTO_BASE + h.evidencia;
                    mini.alt = 'Foto de la ronda ' + (idx + 1);
                    mini.addEventListener('click', function () { mostrarFoto(mini.src); });
                    mini.addEventListener('error', function () { mini.style.display = 'none'; });
                    ciclo.appendChild(mini);
                }

                var cicloTexto = document.createElement('div');
                cicloTexto.className = 'proforma-historial-ciclo-texto';
                cicloTexto.innerHTML =
                    '<div class="proforma-historial-ciclo-hdr">'
                    + '<span class="proforma-historial-ciclo-num">Ronda ' + (idx + 1) + (esUltimo ? ' · última' : '') + '</span>'
                    + '<span class="proforma-historial-ciclo-fecha">' + esc(formatFechaHora(h.fecha_auditoria || h.proforma_fecha_registro)) + '</span>'
                    + '<span class="proforma-badge is-' + esc(h.estado_proforma) + '">' + esc(lbl) + '</span>'
                    + '</div>'
                    + '<div class="proforma-historial-ciclo-dato">Monto: ' + esc(formatMonto(h.monto_validado) || '—') + '</div>'
                    + (h.observaciones_auditoria
                        ? '<div class="proforma-historial-ciclo-dato">' + esc(h.observaciones_auditoria) + '</div>'
                        : '')
                    + (h.motivo_cierre
                        ? '<div class="proforma-historial-ciclo-dato proforma-historial-ciclo-cierre"><strong>Motivo de cierre:</strong> ' + esc(h.motivo_cierre) + '</div>'
                        : '');
                ciclo.appendChild(cicloTexto);

                historialWrap.appendChild(ciclo);
            });
            wrap.appendChild(historialWrap);
        }

        // El ciclo activo puede estar vacío (recién guardado el anterior,
        // esperando que el promotor mande la siguiente foto) — sin foto no
        // hay nada que cotizar todavía, el formulario no debe mostrarse.
        if (!p.evidencia) {
            var espFoto = document.createElement('div');
            espFoto.className = 'proforma-auditoria-cerrada';
            espFoto.innerHTML = '<i class="glyphicon glyphicon-hourglass"></i> Esperando la próxima foto de proforma del promotor.';
            wrap.appendChild(espFoto);
            agregarBotonCerrarProceso(wrap, p, onResuelto);
            return wrap;
        }

        // ── Formulario de monto (obligatorio) + observaciones (opcional) ──
        var campoMonto = document.createElement('div');
        campoMonto.className = 'proforma-campo';
        var labelMonto = document.createElement('label');
        labelMonto.textContent = 'Monto cotizado ($) *';
        var inputMonto = document.createElement('input');
        inputMonto.type = 'text'; inputMonto.inputMode = 'decimal';
        inputMonto.placeholder = 'Ej. 1200.00';
        inputMonto.className = 'form-control';
        // OJO: nunca usar toLocaleString('es-EC', ...) aquí — esa localización
        // usa "." como separador de miles y "," como decimal (2000 -> "2.000,00"),
        // y al reenviarlo por limpiarMonto() (que solo entiende "." como
        // decimal) el valor se corrompe (2000 terminaba guardándose como 2).
        // toFixed(2) usa siempre punto decimal y no agrega miles, así que es
        // seguro re-parsearlo tal cual.
        inputMonto.value = p.monto_validado ? parseFloat(p.monto_validado).toFixed(2) : '';
        // Monto tal como llegó (del promotor o de un guardado previo). El
        // botón "Guardar cambios" arranca deshabilitado y solo se habilita
        // si el analista lo edita — mientras el valor sea igual al que
        // llegó, no hay nada que actualizar.
        var montoOriginal = inputMonto.value;
        inputMonto.addEventListener('input', function () {
            inputMonto.value = limpiarMonto(inputMonto.value);
            btnGuardar.disabled = (inputMonto.value === montoOriginal);
        });
        inputMonto.addEventListener('blur', function () {
            var n = parseFloat(inputMonto.value);
            if (!isNaN(n)) inputMonto.value = n.toFixed(2);
            btnGuardar.disabled = (inputMonto.value === montoOriginal);
        });
        campoMonto.appendChild(labelMonto); campoMonto.appendChild(inputMonto);
        wrap.appendChild(campoMonto);

        var campoObs = document.createElement('div');
        campoObs.className = 'proforma-campo';
        var labelObs = document.createElement('label');
        labelObs.textContent = 'Observaciones internas de validación';
        var inputObs = document.createElement('textarea');
        inputObs.className = 'form-control'; inputObs.placeholder = 'Notas... (opcional)';
        inputObs.rows = 3; inputObs.value = p.observaciones_auditoria || '';
        campoObs.appendChild(labelObs); campoObs.appendChild(inputObs);
        wrap.appendChild(campoObs);

        var acciones = document.createElement('div');
        acciones.className = 'proforma-auditoria-acciones';

        var btnGuardar = document.createElement('button');
        btnGuardar.type = 'button';
        btnGuardar.className = 'proforma-btn-aprobar';
        btnGuardar.textContent = 'Guardar cambios';
        // Deshabilitado hasta que el monto se edite (ver montoOriginal arriba).
        btnGuardar.disabled = (inputMonto.value === montoOriginal);
        btnGuardar.addEventListener('click', function () {
            var montoLimpio = limpiarMonto(inputMonto.value);
            if (!montoLimpio) {
                mostrarToast('El monto cotizado es obligatorio.', true);
                inputMonto.focus();
                return;
            }
            btnGuardar.disabled = true;
            var body = new URLSearchParams();
            body.set('id', p.id);
            body.set('accion', 'guardar');
            body.set('monto', montoLimpio);
            body.set('observaciones', inputObs.value.trim());
            fetch(GETTERS_BASE + 'update_proforma.php', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (json.success) { mostrarToast('Guardado correctamente.'); onResuelto(); }
                    else { mostrarToast(json.message || 'No se pudo actualizar.', true); btnGuardar.disabled = false; }
                })
                .catch(function () { mostrarToast('Error de conexión.', true); btnGuardar.disabled = false; });
        });

        acciones.appendChild(btnGuardar);
        wrap.appendChild(acciones);

        agregarBotonCerrarProceso(wrap, p, onResuelto, function () { return limpiarMonto(inputMonto.value); });

        return wrap;
    }

    // ---------------------------------------------------------------
    // Sello de fecha
    // ---------------------------------------------------------------
    function actualizarSelloFecha() {
        var h = new Date(), hh = h.getHours(), h12 = hh % 12 || 12;
        document.getElementById('proformaActualizado').textContent =
            'Hoy, ' + String(h12).padStart(2,'0') + ':' + String(h.getMinutes()).padStart(2,'0') + ' ' + (hh >= 12 ? 'PM' : 'AM');
    }

    // ---------------------------------------------------------------
    // Carga
    // ---------------------------------------------------------------
    function cargarProformas() {
        return fetch(GETTERS_BASE + 'proformas_listar.php')
            .then(function (r) { return r.json(); })
            .then(function (json) {
                currentRows = json.data || [];
                construirOpcionesPromotor();
                poblarSelectorPeriodo();
                renderizar();
                actualizarSelloFecha();
            })
            .catch(function (err) {
                document.getElementById('proformaGrupos').innerHTML =
                    '<div class="proforma-vacio">No se pudo cargar. Verifica que proformas_listar.php esté desplegado.</div>';
                mostrarToast('Error: ' + err.message, true);
            });
    }

    // ---------------------------------------------------------------
    // Excel
    // ---------------------------------------------------------------
    function descargarExcel() {
        if (typeof XLSX === 'undefined') { mostrarToast('Falta librería XLSX.', true); return; }
        var datos = filasFiltradas().map(function (p) {
            return {
                'PDV':            p.pdv || '',
                'Código':         p.codigo_pdv || '',
                'Cliente':        p.empresa || p.contacto || '',
                'Promotor':       p.usuario || '',
                'Fecha visita':   formatFechaVisita(p),
                'Proforma subida':formatFechaHora(p.proforma_fecha_registro),
                'Estado':         getBadge(p).label,
                'Monto validado': p.monto_validado || ''
            };
        });
        var hoja = XLSX.utils.json_to_sheet(datos);
        var libro = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(libro, hoja, 'Proformas');
        XLSX.writeFile(libro, 'proformas_' + hoyISO() + '.xlsx');
    }

    window.ProformaRecargar = cargarProformas;

    document.addEventListener('DOMContentLoaded', function () {
        cargarProformas();
        ['proformaBusqueda'].forEach(function (id) {
            document.getElementById(id).addEventListener('input', renderizar);
        });
        ['proformaFiltroPromotor','proformaFiltroEstado','proformaFiltroPeriodo'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', renderizar);
        });
        document.getElementById('proformaActualizar').addEventListener('click', cargarProformas);
        document.getElementById('proformaExportar').addEventListener('click', descargarExcel);

        document.getElementById('proformaFotoCerrar').addEventListener('click', cerrarFoto);
        document.getElementById('proformaFotoOverlay').addEventListener('click', function (ev) {
            if (ev.target.id === 'proformaFotoOverlay') cerrarFoto();
        });
    });
})();
