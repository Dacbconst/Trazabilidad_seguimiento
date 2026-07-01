(function () {
    var app = document.getElementById('proformaApp');
    var GETTERS_BASE = app.dataset.gettersBase;
    var MODULO_BASE = app.dataset.moduloBase;

    // Ruta base donde la app móvil sube las fotos de evidencia.
    // El campo `evidencia` en DB llega como "Proforma/archivo.png".
    // Si la ruta real cambia (otro folder, otro servidor) solo hay que
    // cambiar esta constante — no tocar construirEvidencia().
    var FOTO_BASE = MODULO_BASE + '/';

    var currentRows = [];
    var filaAbiertaId = null;
    var toastTimer = null;

    // CONFIRMADO con el equipo de la app móvil (Constantes.java /
    // AdapterProforma.java, 2026-06-30): 'pendiente'/'en_proceso'/'realizado'
    // son los 3 únicos valores que la app reconoce hoy — cualquier otro
    // valor cae en su "else" y se muestra ahí como "Pendiente" sin importar
    // qué diga el servidor. 'en_negociacion'/'aprobado'/'rechazado' son
    // NUEVOS, acordados recién con ese equipo para que actualicen su
    // if/else en paralelo — no escribir ningún otro literal sin avisarles.
    // 'pendiente' (default de la columna en MySQL) se trata igual que
    // 'en_proceso' acá — ambos significan "todavía no se audita".
    var ESTADOS_VALIDOS = ['pendiente', 'en_proceso', 'en_negociacion', 'aprobado', 'rechazado'];
    var ESTADO_LABEL = {
        pendiente: 'Pendiente revisión',
        en_proceso: 'Pendiente revisión',
        en_negociacion: 'En negociación',
        aprobado: 'Aprobada',
        rechazado: 'Rechazada'
    };

    function estadoVisual(p) {
        var estado = p.estado_proforma;
        if (estado === 'pendiente') return 'en_proceso'; // mismo bucket visual
        return ESTADOS_VALIDOS.indexOf(estado) !== -1 ? estado : 'en_proceso';
    }

    // ---------------------------------------------------------------
    // Formato de fechas
    // ---------------------------------------------------------------
    function soloFecha(valor) {
        if (!valor) return null;
        return valor.split(' ')[0];
    }

    function formatFecha(valor) {
        var iso = soloFecha(valor);
        if (!iso || iso === '0000-00-00') return '—';
        var partes = iso.split('-');
        if (partes.length !== 3) return iso;
        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    function formatFechaHora(valor) {
        if (!valor) return '—';
        var partes = valor.split(' ');
        var fecha = formatFecha(partes[0]);
        if (partes.length < 2) return fecha;
        return fecha + ' ' + partes[1].slice(0, 5);
    }

    function diasEntre(fechaA, fechaB) {
        var isoA = soloFecha(fechaA), isoB = soloFecha(fechaB);
        if (!isoA || !isoB || isoA === '0000-00-00' || isoB === '0000-00-00') return null;
        var msPorDia = 24 * 60 * 60 * 1000;
        return Math.round((new Date(isoB + 'T00:00:00') - new Date(isoA + 'T00:00:00')) / msPorDia);
    }

    function hoyISO() {
        var hoy = new Date();
        return hoy.getFullYear() + '-' + String(hoy.getMonth() + 1).padStart(2, '0') + '-' + String(hoy.getDate()).padStart(2, '0');
    }

    // ---------------------------------------------------------------
    // Toast (mismo patrón ya usado en Agendamientos/Contactados)
    // ---------------------------------------------------------------
    function mostrarToast(mensaje, esError) {
        var toast = document.getElementById('proformaToast');
        toast.textContent = mensaje;
        toast.classList.toggle('is-error', !!esError);
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.classList.remove('is-visible'); }, 2500);
    }

    // ---------------------------------------------------------------
    // KPIs (calculados de los datos ya cargados, sin pedir nada extra)
    // ---------------------------------------------------------------
    function pintarKpis(rows) {
        var pendientes = 0, negociacion = 0, aprobadasHoy = 0, montoTramite = 0;
        var hoy = hoyISO();
        rows.forEach(function (p) {
            var estado = estadoVisual(p);
            if (estado === 'en_proceso') pendientes++;
            if (estado === 'en_negociacion') negociacion++;
            if (estado === 'aprobado' && soloFecha(p.fecha_auditoria) === hoy) aprobadasHoy++;
            if ((estado === 'en_negociacion' || estado === 'aprobado') && p.monto_validado) {
                montoTramite += parseFloat(p.monto_validado) || 0;
            }
        });
        document.getElementById('proformaKpiPendientes').textContent = pendientes;
        document.getElementById('proformaKpiNegociacion').textContent = negociacion;
        document.getElementById('proformaKpiAprobadasHoy').textContent = aprobadasHoy;
        document.getElementById('proformaKpiMonto').textContent = '$' + montoTramite.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ---------------------------------------------------------------
    // Timeline: Contacto inicial → Visita agendada → Proforma subida.
    // SLA corporativo: 3 días máximo por fase (regla que viene del PDF de
    // especificación) — si se excede, se resalta en rojo.
    // ---------------------------------------------------------------
    function construirTimeline(p) {
        var wrap = document.createElement('div');
        wrap.className = 'proforma-timeline';

        var pasos = [
            { titulo: 'Contacto inicial', fecha: p.contacto_fecha_registro, esFechaHora: true },
            { titulo: 'Agendamiento creado', fecha: p.fecha_agendamiento, esFechaHora: false },
            { titulo: 'Proforma subida', fecha: p.proforma_fecha_registro, esFechaHora: true }
        ];

        for (var i = 0; i < pasos.length; i++) {
            var paso = pasos[i];
            var item = document.createElement('div');
            item.className = 'proforma-timeline-item';

            var punto = document.createElement('span');
            punto.className = 'proforma-timeline-punto';
            item.appendChild(punto);

            var texto = document.createElement('div');
            texto.className = 'proforma-timeline-texto';

            var titulo = document.createElement('div');
            titulo.className = 'proforma-timeline-titulo';
            titulo.textContent = paso.titulo;
            texto.appendChild(titulo);

            var fechaEl = document.createElement('div');
            fechaEl.className = 'proforma-timeline-fecha';
            fechaEl.textContent = paso.fecha ? (paso.esFechaHora ? formatFechaHora(paso.fecha) : formatFecha(paso.fecha)) : 'Sin registrar';
            texto.appendChild(fechaEl);

            if (i > 0) {
                var dias = diasEntre(pasos[i - 1].fecha, paso.fecha);
                if (dias !== null) {
                    var sla = document.createElement('div');
                    var excede = dias > 3;
                    sla.className = 'proforma-timeline-sla' + (excede ? ' is-excedido' : '');
                    sla.textContent = (dias <= 0 ? 'mismo día' : 'tardó ' + dias + ' día' + (dias === 1 ? '' : 's')) + (excede ? ' — excede el SLA (3 días)' : '');
                    texto.appendChild(sla);
                }
            }

            item.appendChild(texto);
            wrap.appendChild(item);
        }

        return wrap;
    }

    // ---------------------------------------------------------------
    // Evidencia: foto subida desde el celular + datos REALES disponibles
    // (no se inventan coordenadas GPS de la foto ni datos de dispositivo,
    // que no existen en la base — eso sería mostrar datos falsos en una
    // pantalla cuyo propósito es justo detectar evidencia falsa).
    // ---------------------------------------------------------------
    function construirEvidencia(p) {
        var wrap = document.createElement('div');
        wrap.className = 'proforma-evidencia';

        var tituloFoto = document.createElement('div');
        tituloFoto.className = 'proforma-seccion-titulo';
        tituloFoto.textContent = 'Evidencia desde campo';
        wrap.appendChild(tituloFoto);

        if (p.evidencia) {
            var img = document.createElement('img');
            img.className = 'proforma-evidencia-foto';
            img.src = FOTO_BASE + p.evidencia;
            img.alt = 'Evidencia de la proforma';
            img.addEventListener('click', function () { window.open(img.src, '_blank'); });
            img.addEventListener('error', function () {
                img.style.display = 'none';
                var aviso = document.createElement('div');
                aviso.className = 'proforma-evidencia-vacia';
                aviso.innerHTML = '<i class="glyphicon glyphicon-picture"></i><br>Foto no disponible en el servidor.<br><small style="word-break:break-all;opacity:.7">' + img.src + '</small>';
                img.parentNode.insertBefore(aviso, img.nextSibling);
            });
            wrap.appendChild(img);
        } else {
            var sinFoto = document.createElement('div');
            sinFoto.className = 'proforma-evidencia-vacia';
            sinFoto.textContent = 'Sin foto de evidencia.';
            wrap.appendChild(sinFoto);
        }

        var lat = parseFloat(p.latitud), lng = parseFloat(p.longitud);
        if (lat && lng) {
            var ubicacion = document.createElement('a');
            ubicacion.className = 'proforma-evidencia-ubicacion';
            ubicacion.href = 'https://maps.google.com/maps?q=' + lat + ',' + lng;
            ubicacion.target = '_blank';
            ubicacion.rel = 'noopener';
            ubicacion.innerHTML = '<i class="glyphicon glyphicon-map-marker"></i> Ver ubicación registrada de la visita';
            wrap.appendChild(ubicacion);
        }

        var reporteTitulo = document.createElement('div');
        reporteTitulo.className = 'proforma-seccion-titulo proforma-seccion-titulo-mt';
        reporteTitulo.textContent = 'Reporte del promotor';
        wrap.appendChild(reporteTitulo);

        var reporte = document.createElement('div');
        reporte.className = 'proforma-reporte';
        var caracteristicas = document.createElement('p');
        caracteristicas.textContent = p.caracteristica_visita || 'Sin observaciones registradas.';
        reporte.appendChild(caracteristicas);
        var acompanamiento = document.createElement('p');
        acompanamiento.className = 'proforma-reporte-meta';
        acompanamiento.textContent = 'Acompañamiento técnico: ' + (p.acompanamiento_tecnico === 'SI' ? 'Sí' : 'No');
        reporte.appendChild(acompanamiento);
        wrap.appendChild(reporte);

        return wrap;
    }

    // ---------------------------------------------------------------
    // Panel de auditoría y resolución
    // ---------------------------------------------------------------
    function construirAuditoria(p, onResuelto) {
        var wrap = document.createElement('div');
        wrap.className = 'proforma-auditoria';

        var titulo = document.createElement('div');
        titulo.className = 'proforma-seccion-titulo';
        titulo.textContent = 'Auditoría y resolución';
        wrap.appendChild(titulo);

        var campoMonto = document.createElement('div');
        campoMonto.className = 'proforma-campo';
        var labelMonto = document.createElement('label');
        labelMonto.textContent = 'Monto cotizado (leer de la foto) $';
        var inputMonto = document.createElement('input');
        inputMonto.type = 'number';
        inputMonto.step = '0.01';
        inputMonto.min = '0';
        inputMonto.placeholder = 'Ej. 1200.00';
        inputMonto.className = 'form-control';
        inputMonto.value = p.monto_validado || '';
        campoMonto.appendChild(labelMonto);
        campoMonto.appendChild(inputMonto);
        wrap.appendChild(campoMonto);

        var campoObs = document.createElement('div');
        campoObs.className = 'proforma-campo';
        var labelObs = document.createElement('label');
        labelObs.textContent = 'Observaciones internas';
        var inputObs = document.createElement('textarea');
        inputObs.className = 'form-control';
        inputObs.placeholder = 'Notas de validación...';
        inputObs.rows = 2;
        inputObs.value = p.observaciones_auditoria || '';
        campoObs.appendChild(labelObs);
        campoObs.appendChild(inputObs);
        wrap.appendChild(campoObs);

        var acciones = document.createElement('div');
        acciones.className = 'proforma-auditoria-acciones';

        var estadoActual = estadoVisual(p);
        var yaDespachado = estadoActual === 'aprobado' || estadoActual === 'rechazado';

        function setBotonesOcupados(ocupado) {
            [btnNegociacion, btnAprobar, btnRechazar].forEach(function (b) {
                b.disabled = ocupado;
            });
        }

        function accionar(accion) {
            setBotonesOcupados(true);
            var body = new URLSearchParams();
            body.set('id', p.id);
            body.set('accion', accion);
            body.set('monto', inputMonto.value.trim());
            body.set('observaciones', inputObs.value.trim());
            fetch(GETTERS_BASE + 'update_proforma.php', { method: 'POST', body: body })
                .then(function (resp) { return resp.json(); })
                .then(function (json) {
                    if (json.success) {
                        mostrarToast('Guardado correctamente.');
                        onResuelto();
                    } else {
                        mostrarToast(json.message || 'No se pudo actualizar.', true);
                        setBotonesOcupados(false);
                    }
                })
                .catch(function () {
                    mostrarToast('No se pudo conectar con el servidor.', true);
                    setBotonesOcupados(false);
                });
        }

        var btnNegociacion = document.createElement('button');
        btnNegociacion.type = 'button';
        btnNegociacion.className = 'proforma-btn-negociacion';
        btnNegociacion.textContent = 'Enviar a Negociación';
        btnNegociacion.addEventListener('click', function () { accionar('negociacion'); });

        var btnAprobar = document.createElement('button');
        btnAprobar.type = 'button';
        btnAprobar.className = 'proforma-btn-aprobar';
        btnAprobar.textContent = 'Aprobar Proforma';
        btnAprobar.addEventListener('click', function () { accionar('aprobar'); });

        var btnRechazar = document.createElement('button');
        btnRechazar.type = 'button';
        btnRechazar.className = 'proforma-btn-rechazar';
        btnRechazar.textContent = 'Rechazar / Evidencia Falsa';
        btnRechazar.addEventListener('click', function () { accionar('rechazar'); });

        // Casos ya cerrados: se muestran en modo solo-lectura, sin botones de acción
        if (yaDespachado) {
            var cerrado = document.createElement('div');
            cerrado.className = 'proforma-auditoria-cerrada';
            cerrado.textContent = estadoActual === 'aprobado' ? '✓ Proforma aprobada.' : '✗ Proforma rechazada.';
            wrap.appendChild(cerrado);
        } else {
            acciones.appendChild(btnNegociacion);
            acciones.appendChild(btnAprobar);
            acciones.appendChild(btnRechazar);
            wrap.appendChild(acciones);
        }

        return wrap;
    }

    // ---------------------------------------------------------------
    // Tabla + acordeón
    // ---------------------------------------------------------------
    function alertaSlaGeneral(p) {
        var dias1 = diasEntre(p.contacto_fecha_registro, p.fecha_agendamiento);
        var dias2 = diasEntre(p.fecha_agendamiento, p.proforma_fecha_registro);
        var max = Math.max(dias1 || 0, dias2 || 0);
        if (max <= 3) return null;
        return 'Retraso (+' + max + ' día' + (max === 1 ? '' : 's') + ')';
    }

    function pintarFilaDetalle(p) {
        var tr = document.createElement('tr');
        tr.className = 'proforma-fila-detalle';

        var td = document.createElement('td');
        td.colSpan = 6;

        var alerta = alertaSlaGeneral(p);
        if (alerta) {
            var aviso = document.createElement('div');
            aviso.className = 'proforma-alerta-sistema';
            aviso.innerHTML = '<i class="glyphicon glyphicon-warning-sign"></i> Alerta del sistema: el tiempo de gestión supera el SLA permitido (3 días por fase).';
            td.appendChild(aviso);
        }

        var grid = document.createElement('div');
        grid.className = 'proforma-detalle-grid';
        grid.appendChild(construirTimeline(p));
        grid.appendChild(construirEvidencia(p));
        grid.appendChild(construirAuditoria(p, function () { cargarProformas(); }));
        td.appendChild(grid);

        tr.appendChild(td);
        return tr;
    }

    function pintarFila(p) {
        var tr = document.createElement('tr');
        tr.className = 'proforma-fila';
        tr.dataset.id = p.id;

        var tdChevron = document.createElement('td');
        tdChevron.className = 'proforma-chevron';
        tdChevron.innerHTML = '<i class="glyphicon glyphicon-chevron-right"></i>';
        tr.appendChild(tdChevron);

        var tdCliente = document.createElement('td');
        var nombreCliente = document.createElement('div');
        nombreCliente.className = 'proforma-cliente-nombre';
        nombreCliente.textContent = p.empresa || p.contacto || 'Sin nombre';
        var pdvEl = document.createElement('div');
        pdvEl.className = 'proforma-cliente-pdv';
        pdvEl.textContent = (p.pdv || '—') + ' · ' + (p.codigo_pdv || '—');
        tdCliente.appendChild(nombreCliente);
        tdCliente.appendChild(pdvEl);
        tr.appendChild(tdCliente);

        var tdPromotor = document.createElement('td');
        tdPromotor.textContent = p.usuario || '—';
        tr.appendChild(tdPromotor);

        var tdFecha = document.createElement('td');
        tdFecha.textContent = formatFecha(p.fecha_agendamiento);
        tr.appendChild(tdFecha);

        var tdKpi = document.createElement('td');
        var alerta = alertaSlaGeneral(p);
        if (alerta) {
            tdKpi.innerHTML = '<span class="proforma-kpi-retraso">' + alerta + '</span>';
        } else {
            tdKpi.innerHTML = '<span class="proforma-kpi-ok">A tiempo</span>';
        }
        tr.appendChild(tdKpi);

        var tdEstado = document.createElement('td');
        var estado = estadoVisual(p);
        var badge = document.createElement('span');
        badge.className = 'proforma-badge is-' + estado;
        badge.textContent = ESTADO_LABEL[estado];
        tdEstado.appendChild(badge);
        tr.appendChild(tdEstado);

        tr.addEventListener('click', function () {
            alternarDetalle(p.id);
        });

        return tr;
    }

    function alternarDetalle(id) {
        filaAbiertaId = (filaAbiertaId === id) ? null : id;
        renderizar();
        if (filaAbiertaId !== null) {
            setTimeout(function () {
                var el = document.querySelector('.proforma-fila[data-id="' + id + '"]');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 50);
        }
    }

    function periodoDesde(clave) {
        var hoy = new Date();
        if (clave === 'mes_actual') {
            return new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        }
        if (clave === 'mes_anterior') {
            return new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
        }
        if (clave === 'ultimos_3') {
            return new Date(hoy.getFullYear(), hoy.getMonth() - 3, 1);
        }
        return null;
    }

    function periodoHasta(clave) {
        var hoy = new Date();
        if (clave === 'mes_anterior') {
            return new Date(hoy.getFullYear(), hoy.getMonth(), 0); // último día mes anterior
        }
        return hoy;
    }

    function filasFiltradas() {
        var promotorSel = document.getElementById('proformaFiltroPromotor');
        var estadoSel   = document.getElementById('proformaFiltroEstado');
        var periodoClave= document.getElementById('proformaFiltroPeriodo').value;
        var busqueda    = document.getElementById('proformaBusqueda').value.toLowerCase().trim();
        var desde = periodoDesde(periodoClave);
        var hasta = periodoHasta(periodoClave);

        return currentRows.filter(function (p) {
            if (promotorSel.value && p.usuario !== promotorSel.value) return false;
            if (estadoSel.value && estadoVisual(p) !== estadoSel.value) return false;
            if (desde) {
                var fechaRef = p.fecha_proforma || p.contacto_fecha_registro;
                if (!fechaRef) return false;
                var fechaD = new Date(fechaRef.split(' ')[0] + 'T00:00:00');
                if (fechaD < desde || fechaD > hasta) return false;
            }
            if (busqueda) {
                var coincide = [p.pdv, p.codigo_pdv, p.empresa, p.contacto].some(function (v) {
                    return (v || '').toLowerCase().indexOf(busqueda) !== -1;
                });
                if (!coincide) return false;
            }
            return true;
        });
    }

    function renderizar() {
        var tbody = document.getElementById('proformaTbody');
        var filas = filasFiltradas();
        tbody.innerHTML = '';

        if (!filas.length) {
            var tr = document.createElement('tr');
            var td = document.createElement('td');
            td.colSpan = 6;
            td.className = 'proforma-vacio';
            td.textContent = 'Sin proformas que coincidan con el filtro.';
            tr.appendChild(td);
            tbody.appendChild(tr);
            return;
        }

        filas.forEach(function (p) {
            var filaP = pintarFila(p);
            if (filaAbiertaId === p.id) filaP.classList.add('is-abierta');
            tbody.appendChild(filaP);
            if (filaAbiertaId === p.id) {
                tbody.appendChild(pintarFilaDetalle(p));
            }
        });

        pintarKpis(currentRows);
    }

    function construirOpcionesPromotor() {
        var select = document.getElementById('proformaFiltroPromotor');
        var valorPrevio = select.value;
        select.innerHTML = '<option value="">Todos los promotores</option>';
        var vistos = {};
        currentRows.forEach(function (p) {
            if (p.usuario && !vistos[p.usuario]) {
                vistos[p.usuario] = true;
                var opt = document.createElement('option');
                opt.value = p.usuario;
                opt.textContent = p.usuario;
                select.appendChild(opt);
            }
        });
        if (vistos[valorPrevio]) select.value = valorPrevio;
    }

    function actualizarSelloFecha() {
        var ahora = new Date();
        var hh = ahora.getHours();
        var ampm = hh >= 12 ? 'PM' : 'AM';
        var hh12 = hh % 12 || 12;
        document.getElementById('proformaActualizado').textContent =
            'Hoy, ' + String(hh12).padStart(2, '0') + ':' + String(ahora.getMinutes()).padStart(2, '0') + ' ' + ampm;
    }

    function cargarProformas() {
        var params = new URLSearchParams();
        return fetch(GETTERS_BASE + 'proformas_listar.php?' + params.toString())
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                currentRows = json.data || [];
                construirOpcionesPromotor();
                renderizar();
                actualizarSelloFecha();
            })
            // Sin esto, cualquier falla (getter no desplegado todavía, error
            // de PHP, etc.) quedaba en una promesa rechazada sin manejar —
            // la tabla se veía pegada en "Cargando..." para siempre sin
            // ninguna pista de qué pasó.
            .catch(function (err) {
                document.getElementById('proformaTbody').innerHTML =
                    '<tr><td colspan="6" class="proforma-vacio">No se pudo cargar la información. Verifica que proformas_listar.php esté desplegado y revisa la consola del navegador.</td></tr>';
                mostrarToast('Error al cargar proformas: ' + err.message, true);
            });
    }

    // ---------------------------------------------------------------
    // Exportar reporte (.xlsx real vía SheetJS, ya cargado en otros
    // módulos — si esta sección abre antes que Contactados, hay que
    // verificar que xlsx.full.min.js esté disponible; se carga aquí
    // también por si acaso).
    // ---------------------------------------------------------------
    function descargarExcel() {
        if (typeof XLSX === 'undefined') {
            mostrarToast('Falta la librería de exportación (XLSX).', true);
            return;
        }
        var filas = filasFiltradas();
        var datos = filas.map(function (p) {
            return {
                'PDV': p.pdv || '',
                'Código PDV': p.codigo_pdv || '',
                'Cliente': p.empresa || p.contacto || '',
                'Promotor': p.usuario || '',
                'Fecha visita': formatFecha(p.fecha_agendamiento),
                'Proforma subida': formatFechaHora(p.proforma_fecha_registro),
                'Estado': ESTADO_LABEL[estadoVisual(p)],
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
        if (typeof XLSX === 'undefined') {
            var script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
            document.head.appendChild(script);
        }

        cargarProformas();

        document.getElementById('proformaBusqueda').addEventListener('input', renderizar);
        document.getElementById('proformaFiltroPromotor').addEventListener('change', renderizar);
        document.getElementById('proformaFiltroEstado').addEventListener('change', renderizar);
        document.getElementById('proformaFiltroPeriodo').addEventListener('change', renderizar);
        document.getElementById('proformaExportar').addEventListener('click', descargarExcel);
    });
})();
