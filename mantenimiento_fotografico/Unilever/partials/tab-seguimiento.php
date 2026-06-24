<?php
/**
 * TAB: Seguimiento
 * Fuente: lvi_rutero (asignado/relevado) JOIN vi_registro (hora entrada/salida, relevo)
 */
?>
<div class="seg-header" style="background:#f7f8fa; border:1px solid #e3e6ea; border-radius:8px; padding:16px 18px; margin-bottom:16px;
                                display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div>
        <h3 style="margin:0 0 4px; font-size:18px;">
            <i class="glyphicon glyphicon-time" style="color:#2a6496; margin-right:6px;"></i>
            Seguimiento
        </h3>
        <p style="margin:0; font-size:12px; color:#888;">
            Por gestor y día: visitas asignadas, relevadas, hora de jornada y relevo fotográfico.
        </p>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
        <button type="button" id="seg-excel" class="btn btn-success btn-sm" disabled>
            <i class="glyphicon glyphicon-download-alt"></i> Excel
        </button>
        <button type="button" id="seg-consultar" class="btn btn-primary btn-sm">
            <i class="glyphicon glyphicon-search"></i> Consultar
        </button>
    </div>
</div>

<style>
.seg-metric-card {
    background:#fff; border:1px solid #e3e6ea; border-radius:8px; padding:14px 16px;
    text-align:center; flex:1; min-width:130px;
}
.seg-metric-card .seg-metric-label {
    font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px;
}
.seg-metric-card .seg-metric-value {
    font-size:24px; font-weight:700; line-height:1;
}
.seg-metric-card .seg-metric-sub {
    font-size:11px; color:#aaa; margin-top:4px;
}
.seg-detail-row td { border-top:none !important; }
.seg-foto-si { color:#27ae60; font-weight:700; }
.seg-foto-no { color:#bbb; }
.seg-foto-x  { color:#c0392b; font-weight:700; }
/* .seg-pdv-row { cursor:pointer; } — desactivado junto con el panel de fotos por PDV */
/* .seg-pdv-row.seg-pdv-row-active td { background:#eef0f2 !important; } */
.seg-pdv-detail-row td { border-top:none !important; }
.seg-pdv-card {
    background:#fff; border:1px solid #e3e6ea; border-radius:8px; overflow:hidden;
    box-shadow:0 1px 4px rgba(0,0,0,.06); flex:1; min-width:200px; max-width:260px;
}
.seg-pdv-card-header {
    background:#f7f8fa; padding:6px 10px; font-size:12px; font-weight:600; color:#555;
    display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e3e6ea;
}
.seg-pdv-card-body { padding:8px; text-align:center; }
.seg-pdv-img { width:100%; height:170px; object-fit:cover; border-radius:4px; display:block; }
.seg-pdv-img-empty {
    width:100%; height:170px; display:flex; align-items:center; justify-content:center;
    background:#f5f6f8; border-radius:4px; color:#aaa; font-size:12px;
}
.seg-evid-card {
    background:#fff; border:1px solid #e3e6ea; border-radius:8px; overflow:hidden;
    box-shadow:0 1px 4px rgba(0,0,0,.06); flex:2; min-width:320px;
}
.seg-evid-card .card-body { display:flex; gap:8px; padding:8px; }
.seg-evid-card .foto_antes, .seg-evid-card .foto_despues { flex:1; text-align:center; }
.seg-evid-card .foto_antes p, .seg-evid-card .foto_despues p { font-size:11px; color:#888; margin:0 0 4px; }
</style>

<div id="seg-content">
    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center;
                height:240px; color:#aaa;">
        <i class="glyphicon glyphicon-stats" style="font-size:48px; margin-bottom:16px;"></i>
        <h3 style="margin:0 0 8px; color:#999;">Aún sin datos</h3>
        <p style="margin:0; font-size:13px;">Selecciona el rango de fechas y presiona "Consultar"</p>
    </div>
</div>

<script>
function segEscapeHtml(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function(c) {
        return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
    });
}

var SEG_IMG_NO_FOTO = 'https://luckyecuadorweb.blob.core.windows.net/app/AppUnilever/Inserts/NO_FOTO.png';

function segRenderEmpty(titulo, mensaje) {
    return '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:240px;color:#aaa;">' +
        '<i class="glyphicon glyphicon-stats" style="font-size:48px;margin-bottom:16px;"></i>' +
        '<h3 style="margin:0 0 8px;color:#999;">' + titulo + '</h3>' +
        '<p style="margin:0;font-size:13px;">' + mensaje + '</p></div>';
}

function segBarraColor(pct) {
    if (pct >= 85) return '#27ae60';
    if (pct >= 70) return '#f0ad4e';
    return '#c0392b';
}

function segRenderResumen(rows) {
    var totalAsignado     = 0;
    var totalRelevado     = 0;
    var gestoresPendientes = 0;
    var localesPendientes  = 0;
    rows.forEach(function(r) {
        totalAsignado += r.asignado;
        totalRelevado += r.relevado;
        var pendientes = r.asignado - r.con_relevo;
        if (pendientes > 0) {
            gestoresPendientes++;
            localesPendientes += pendientes;
        }
    });
    var pctCumplimiento = totalAsignado ? Math.round(totalRelevado  / totalAsignado * 100) : 0;
    var colorCumpl  = segBarraColor(pctCumplimiento);

    function card(label, value, color, sub) {
        return '<div class="seg-metric-card">' +
            '<div class="seg-metric-label">' + label + '</div>' +
            '<div class="seg-metric-value" style="color:' + color + ';">' + value + '</div>' +
            (sub ? '<div class="seg-metric-sub">' + sub + '</div>' : '') +
            '</div>';
    }

    return '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px;">' +
        card('Visitas asignadas',  totalAsignado, '#2a6496') +
        card('Visitas relevadas',  totalRelevado, '#555') +
        card('% Cumplimiento',     pctCumplimiento + '%', colorCumpl,  totalRelevado  + ' de ' + totalAsignado) +
        card('Gestores con pendientes', gestoresPendientes, '#c0392b', localesPendientes + ' local(es) sin relevo') +
        '</div>';
}

function segBadgeEstadoRutero(estado) {
    var color = (estado === 'ATENDIDO')   ? '#27ae60' :
                (estado === 'EN PROCESO') ? '#f0ad4e' :
                (estado === 'JUSTIFICADO' || estado === 'JUSTIFICADO SUPERVISOR') ? '#2a6496' :
                /* NO VISITADO */           '#c0392b';
    return '<span style="background:' + color + '; color:#fff; padding:2px 9px; border-radius:10px; font-size:11px; font-weight:600;">' +
           segEscapeHtml(estado) + '</span>';
}

// Carga (lazy) y pinta el detalle de PDVs asignados a un gestor en un día puntual
function segCargarDetalle($contenedor, gestor, fecha_iso, supervisor) {
    if ($contenedor.data('loaded')) return;
    $contenedor.html('<span style="color:#aaa; font-size:12px;">Cargando detalle...</span>');

    $.ajax({
        type: 'POST',
        url: 'getters/getSeguimientoDetalle.php',
        data: { fecha_inicio: fecha_iso, fecha_fin: fecha_iso, gestor: gestor, supervisor: supervisor },
        dataType: 'json',
        success: function(resp) {
            $contenedor.data('loaded', true);

            if (!resp.count) {
                $contenedor.html('<span style="color:#aaa; font-size:12px;">No hay puntos registrados en el rutero de este gestor para este día</span>');
                return;
            }

            var det = '<table class="table table-condensed" style="background:#fafbfc; margin:0;">' +
                '<thead><tr>' +
                    '<th>Ciudad</th><th>Local asignado</th><th>Estado del rutero</th>' +
                    '<th style="text-align:center;">Relevo</th>' +
                '</tr></thead><tbody>';

            resp.rows.forEach(function(d) {
                var tieneRelevo = d.tiene_antes || d.tiene_despues;
                det += '<tr class="seg-pdv-row">' +
                    '<td>' + segEscapeHtml(d.city) + '</td>' +
                    '<td>' + segEscapeHtml(d.pos_name) + '</td>' +
                    '<td>' + segBadgeEstadoRutero(d.estado) + '</td>' +
                    '<td style="text-align:center;">' + (tieneRelevo ? '<span class="seg-foto-si">&#10003;</span>' : '') + '</td>' +
                '</tr>';

                /* Panel de detalle con las fotos (Antes/Después + Exhibiciones) — desactivado
                   a pedido del cliente, se deja comentado por si se vuelve a pedir a futuro.

                var fotosContent;
                if (!d.tiene_antes && !d.tiene_despues && !d.tiene_exh) {
                    fotosContent = '<span style="color:#aaa;">Sin fotos registradas para este PDV</span>';
                } else {
                    fotosContent = '<div style="display:flex; gap:12px; flex-wrap:wrap;">' +
                        '<div class="card seg-evid-card">' +
                            '<div class="seg-pdv-card-header">' +
                                '<span>Antes / Después</span>' +
                            '</div>' +
                            '<div class="card-body">' +
                                '<div class="foto_antes">' +
                                    '<p><b>Antes</b> ' + (d.tiene_antes ? '<span class="seg-foto-si">&#10003;</span>' : '<span class="seg-foto-x">&#10007;</span>') + '</p>' +
                                    '<img class="seg-pdv-img" src="' + (d.foto_antes ? segEscapeHtml(d.foto_antes) : SEG_IMG_NO_FOTO) + '">' +
                                '</div>' +
                                '<div class="foto_despues">' +
                                    '<p><b>Después</b> ' + (d.tiene_despues ? '<span class="seg-foto-si">&#10003;</span>' : '<span class="seg-foto-x">&#10007;</span>') + '</p>' +
                                    '<img class="seg-pdv-img" src="' + (d.foto_despues ? segEscapeHtml(d.foto_despues) : SEG_IMG_NO_FOTO) + '">' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="seg-pdv-card">' +
                            '<div class="seg-pdv-card-header"><span>Exhibiciones</span>' +
                                (d.tiene_exh ? '<span class="seg-foto-si">&#10003;</span>' : '<span class="seg-foto-x">&#10007;</span>') +
                            '</div>' +
                            '<div class="seg-pdv-card-body">' +
                                (d.exh_foto
                                    ? '<div style="position:relative;">' +
                                          '<img class="seg-pdv-img" src="' + segEscapeHtml(d.exh_foto) + '">' +
                                          (d.exh_total > 1
                                              ? '<span style="position:absolute; top:6px; right:6px; background:#2a6496; color:#fff; font-size:11px; font-weight:600; padding:1px 6px; border-radius:10px;">+' + (d.exh_total - 1) + '</span>'
                                              : '') +
                                      '</div>'
                                    : '<div class="seg-pdv-img-empty">&mdash;</div>') +
                            '</div>' +
                        '</div>' +
                    '</div>';
                }
                det += '<tr class="seg-pdv-detail-row" style="display:none; background:#f8f9fb;">' +
                    '<td colspan="4" style="padding:10px 12px;">' + fotosContent + '</td>' +
                '</tr>';
                */
            });

            det += '</tbody></table>';
            $contenedor.html(det);
        },
        error: function() {
            $contenedor.data('loaded', false);
            $contenedor.html('<span style="color:#c0392b; font-size:12px;">Error al cargar el detalle, intenta nuevamente</span>');
        }
    });
}

/* Toggle del panel de detalle de fotos por PDV — desactivado junto con el panel (ver
   segCargarDetalle). Se deja comentado por si se vuelve a pedir a futuro.

$(document).off('click', '.seg-pdv-row').on('click', '.seg-pdv-row', function() {
    var $fila  = $(this);
    var $det   = $fila.next('.seg-pdv-detail-row');
    var $tabla = $fila.closest('table');
    var vis    = $det.is(':visible');

    $tabla.find('.seg-pdv-detail-row:visible').not($det).each(function() {
        $(this).hide();
        $(this).prev('.seg-pdv-row').removeClass('seg-pdv-row-active');
    });

    $det.toggle(!vis);
    $fila.toggleClass('seg-pdv-row-active', !vis);
});
*/

$(document).ready(function() {

    $('#seg-consultar').click(function() {
        var fecha_inicio = $('#fechaInicio').val();
        var fecha_fin    = $('#fechaFin').val();
        var supervisor   = $('#supervisores').val()   || '.';
        var gestor       = $('#mercaderistas').val()  || '.';

        if (!fecha_inicio) { alert('Ingrese una fecha inicio'); return; }
        if (!fecha_fin)    { alert('Ingrese una fecha fin');    return; }

        // Guardar parámetros para Excel
        window._segParams = { fecha_inicio: fecha_inicio, fecha_fin: fecha_fin,
                               supervisor: supervisor, gestor: gestor };

        $('#seg-excel').prop('disabled', true);
        $('#seg-content').html(segRenderEmpty('Cargando...', 'Consultando seguimiento de visitas'));

        $.ajax({
            type: 'POST',
            url:  'getters/getSeguimiento.php',
            data: { fecha_inicio: fecha_inicio, fecha_fin: fecha_fin,
                    supervisor: supervisor, gestor: gestor },
            dataType: 'json',
            success: function(response) {
                if (!response.count) {
                    $('#seg-content').html(segRenderEmpty('Sin resultados',
                        'No hay visitas registradas para ese rango de fechas y filtros'));
                    return;
                }

                // Calcular porcentaje en JS
                response.rows.forEach(function(r) {
                    r.porcentaje = r.asignado > 0 ? Math.round(r.relevado / r.asignado * 100) : 0;
                });

                var supervisorTxt = (supervisor === '.' || supervisor === '') ? 'Todos' : supervisor;
                var gestorTxt     = (gestor     === '.' || gestor     === '') ? 'Todos' : gestor;

                var html = '<p style="margin:0 0 8px;font-size:12px;color:#888;">' +
                    '<i class="glyphicon glyphicon-filter"></i> ' +
                    fecha_inicio + ' al ' + fecha_fin +
                    ' &nbsp;·&nbsp; Supervisor: <strong>' + segEscapeHtml(supervisorTxt) + '</strong>' +
                    ' &nbsp;·&nbsp; Gestor: <strong>' + segEscapeHtml(gestorTxt) + '</strong>' +
                    ' &nbsp;·&nbsp; ' + response.count + ' fila(s)</p>';

                html += segRenderResumen(response.rows);

                var fechasUnicas = {};
                response.rows.forEach(function(r) { fechasUnicas[r.fecha_iso] = true; });
                var totalFechas = Object.keys(fechasUnicas).length;

                if (totalFechas > 1) {
                    html += '<div style="position:sticky; top:0; z-index:5; background:#fff; padding:6px 0; margin-bottom:8px; border-bottom:1px solid #e3e6ea; display:flex; justify-content:flex-end; gap:8px;">' +
                        '<button type="button" id="seg-expand-all" class="btn btn-default btn-sm">' +
                            '<i class="glyphicon glyphicon-resize-full"></i> Expandir todo' +
                        '</button>' +
                        '<button type="button" id="seg-collapse-all" class="btn btn-default btn-sm">' +
                            '<i class="glyphicon glyphicon-resize-small"></i> Colapsar todo' +
                        '</button>' +
                    '</div>';
                }

                html += '<div style="overflow-x:auto;">' +
                    '<table class="table table-striped table-condensed" style="background:#fff;border-radius:8px;overflow:hidden;font-size:13px;">' +
                    '<thead><tr style="background:#f0f4f8;">' +
                        '<th style="width:32px;"></th>' +
                        '<th>Gestor</th>' +
                        '<th style="text-align:center;">Relevado</th>' +
                        '<th style="text-align:center;">Asignado</th>' +
                        '<th style="min-width:140px;">% Progreso</th>' +
                    '</tr></thead><tbody>';

                var fechaActual  = null;
                var primeraFecha = null;

                response.rows.forEach(function(r, i) {
                    var color = segBarraColor(r.porcentaje);
                    var pdvId  = 'seg-pdv-'  + i;

                    // Separador colapsable por fecha (solo si hay más de un día)
                    if (r.fecha_iso !== fechaActual) {
                        fechaActual = r.fecha_iso;
                        if (primeraFecha === null) primeraFecha = fechaActual;
                        var abierto = (fechaActual === primeraFecha);
                        html += '<tr class="seg-date-row" data-fecha-group="' + segEscapeHtml(fechaActual) + '" style="cursor:pointer;background:#eef2f6;">' +
                            '<td colspan="5" style="font-weight:600;padding:8px 10px;">' +
                                '<i class="glyphicon ' + (abierto ? 'glyphicon-chevron-down' : 'glyphicon-chevron-right') + '" style="margin-right:6px;"></i>' +
                                segEscapeHtml(r.fecha) +
                            '</td></tr>';
                    }
                    var ocultoPorGrupo = (fechaActual !== primeraFecha);

                    // Fila principal
                    html += '<tr class="seg-row" data-target="' + pdvId + '" data-fecha-group="' + segEscapeHtml(r.fecha_iso) + '" style="cursor:pointer;' + (ocultoPorGrupo ? 'display:none;' : '') + '">' +
                        '<td>' +
                            '<button type="button" class="btn btn-default btn-xs seg-toggle-btn">' +
                                '<i class="glyphicon glyphicon-plus"></i>' +
                            '</button>' +
                        '</td>' +
                        '<td>' + segEscapeHtml(r.gestor) + '</td>' +
                        '<td style="text-align:center;">' + r.relevado + '</td>' +
                        '<td style="text-align:center;">' + r.asignado + '</td>' +
                        '<td>' +
                            '<div style="display:flex;align-items:center;gap:8px;">' +
                                '<div style="flex:1;background:#eee;border-radius:6px;height:10px;overflow:hidden;">' +
                                    '<div style="height:100%;width:' + r.porcentaje + '%;background:' + color + ';"></div>' +
                                '</div>' +
                                '<span style="font-size:12px;font-weight:600;color:' + color + ';min-width:36px;text-align:right;">' + r.porcentaje + '%</span>' +
                            '</div>' +
                        '</td>' +
                    '</tr>';

                    // Fila de detalle: PDVs asignados (toggle con el botón "+")
                    html += '<tr id="' + pdvId + '" class="seg-detail-row" data-fecha-group="' + segEscapeHtml(r.fecha_iso) + '" data-fecha-iso="' + segEscapeHtml(r.fecha_iso) + '" data-gestor="' + segEscapeHtml(r.gestor) + '" style="display:none;">' +
                        '<td></td>' +
                        '<td colspan="4" class="seg-detail-content" style="padding:10px 6px;"></td>' +
                    '</tr>';
                });

                html += '</tbody></table></div>';
                $('#seg-content').html(html);
                $('#seg-excel').prop('disabled', false);

                // Expandir / colapsar todos los grupos de fecha
                $('#seg-expand-all').off('click').on('click', function() {
                    $('#seg-content .seg-date-row').each(function() {
                        $(this).find('i.glyphicon').removeClass('glyphicon-chevron-right').addClass('glyphicon-chevron-down');
                    });
                    $('#seg-content .seg-row').show();
                });

                $('#seg-collapse-all').off('click').on('click', function() {
                    $('#seg-content .seg-date-row').each(function() {
                        $(this).find('i.glyphicon').removeClass('glyphicon-chevron-down').addClass('glyphicon-chevron-right');
                    });
                    $('#seg-content .seg-row').hide();
                    $('#seg-content .seg-detail-row:visible').each(function() {
                        $(this).hide();
                        $('#seg-content .seg-row[data-target="' + $(this).attr('id') + '"]')
                            .find('.seg-toggle-btn i')
                            .removeClass('glyphicon-minus').addClass('glyphicon-plus');
                    });
                    $('#seg-content .seg-detail-row').hide();
                });

                // Toggle de grupo por fecha (colapsa/expande las filas de gestores de ese día)
                $('#seg-content').off('click', '.seg-date-row').on('click', '.seg-date-row', function() {
                    var $header = $(this);
                    var grupo   = $header.data('fecha-group');
                    var $icon   = $header.find('i.glyphicon');
                    var abrir   = $icon.hasClass('glyphicon-chevron-right');
                    var $filas  = $('#seg-content tr[data-fecha-group="' + grupo + '"]').not($header);

                    if (abrir) {
                        $filas.filter('.seg-row').show();
                        $icon.removeClass('glyphicon-chevron-right').addClass('glyphicon-chevron-down');
                    } else {
                        $filas.filter('.seg-row').hide();
                        $filas.filter('.seg-detail-row:visible').each(function() {
                            $(this).hide();
                            $('#seg-content .seg-row[data-target="' + $(this).attr('id') + '"]')
                                .find('.seg-toggle-btn i')
                                .removeClass('glyphicon-minus').addClass('glyphicon-plus');
                        });
                        $filas.filter('.seg-detail-row').hide();
                        $icon.removeClass('glyphicon-chevron-down').addClass('glyphicon-chevron-right');
                    }
                });

                // Toggle detalle de PDVs (carga perezosa, una sola vez por fila)
                $('#seg-content').off('click', '.seg-row').on('click', '.seg-row', function() {
                    var $fila = $(this);
                    var $det  = $('#' + $fila.data('target'));
                    var $icon = $fila.find('.seg-toggle-btn i');
                    var vis   = $det.is(':visible');

                    if (!vis) {
                        // Acordeón: cerrar cualquier otra fila de detalle abierta
                        $('#seg-content .seg-detail-row:visible').not($det).each(function() {
                            var $otroDet = $(this);
                            $otroDet.hide();
                            $('#seg-content .seg-row[data-target="' + $otroDet.attr('id') + '"]')
                                .find('.seg-toggle-btn i')
                                .removeClass('glyphicon-minus').addClass('glyphicon-plus');
                        });
                    }

                    $det.toggle(!vis);
                    $icon.toggleClass('glyphicon-plus glyphicon-minus');

                    if (!vis) {
                        segCargarDetalle($det.find('.seg-detail-content'), $det.data('gestor'), $det.data('fecha-iso'), supervisor);
                    }
                });
            },
            error: function() {
                $('#seg-content').html(segRenderEmpty('Error', 'No se pudo consultar el seguimiento. Intenta nuevamente'));
            }
        });
    });

    // Descargar Excel con los mismos parámetros de la última consulta
    $('#seg-excel').click(function() {
        var p = window._segParams || {};
        if (!p.fecha_inicio) { alert('Primero consulta los datos'); return; }
        var url = 'getters/getExcelSeguimiento.php' +
            '?fecha_inicio=' + encodeURIComponent(p.fecha_inicio) +
            '&fecha_fin='    + encodeURIComponent(p.fecha_fin)    +
            '&supervisor='   + encodeURIComponent(p.supervisor)   +
            '&gestor='       + encodeURIComponent(p.gestor);
        window.open(url, '_blank');
    });
});
</script>
