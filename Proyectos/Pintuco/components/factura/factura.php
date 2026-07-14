<?php
/**
 * COMPONENTE: factura — Seguimiento de facturación por promotor + panel de
 * auditoría de agendamiento (línea de tiempo, historial de proformas y
 * financiamiento a plazos). Solo lectura — cero acciones de escritura.
 *
 * Antes vivía como el tab "Por Promotor" dentro de Estado de Flujo; se
 * separó a su propia sección del sidebar (2026-07-13, pedido explícito del
 * usuario) — Estado de Flujo se quedó solo con el kanban "Por Fase".
 */
$modulo_base    = basename((string) $cuenta_dir);
$factura_dir    = __DIR__;
$factura_assets = $modulo_base . '/components/factura/assets';
$factura_css_v  = @filemtime($factura_dir . '/assets/factura.css') ?: time();
$factura_js_v   = @filemtime($factura_dir . '/assets/factura.js') ?: time();
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars($factura_assets, ENT_QUOTES) ?>/factura.css?v=<?= $factura_css_v ?>">
<!-- Fork de SheetJS con soporte de estilos de celda (fill/font) en la
     escritura del .xlsx — la edición community de xlsx.full.min.js ignora
     la propiedad "s" al exportar, por eso no se ve el encabezado azul. -->
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>

<div id="facturaApp"
     data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/"
     data-modulo-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>">

    <div class="mod-filtros">
        <div class="filter-group is-busqueda">
            <label>PDV o empresa</label>
            <div class="input-group">
                <input type="text" class="form-control" id="efPromoBusquedaPdv" placeholder="Buscar PDV o empresa...">
                <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
            </div>
        </div>
        <div class="filter-group">
            <label>Promotor</label>
            <input type="text" class="form-control" id="efPromoSearch" placeholder="Buscar promotor...">
        </div>
        <div class="filter-group">
            <label>Periodo</label>
            <!-- Se llena en JS (poblarSelectorPeriodo) con los meses/año que
                 realmente tienen datos (visita, contacto o algún pago/factura),
                 igual que el selector de Contactados — para que el analista
                 pueda elegir cualquier mes con actividad, no solo "actual"/
                 "anterior". -->
            <select class="form-control" id="efPromoFiltroPeriodo"></select>
        </div>
        <div class="mod-filtros-extra">
            <button type="button" class="btn btn-mod-actualizar" id="efActualizarProm">
                <i class="glyphicon glyphicon-refresh"></i> Actualizar
            </button>
            <button type="button" class="btn ef-btn-select-all" id="efSeleccionarTodo">
                <i class="glyphicon glyphicon-ok"></i> Seleccionar todo
            </button>
            <button type="button" class="btn ef-btn-clear-sel" id="efDesmarcarTodo" style="display:none">
                <i class="glyphicon glyphicon-remove"></i> Desmarcar todo (<span id="efSelCount">0</span>)
            </button>
            <button type="button" class="btn ef-btn-excel" id="efDescargarExcel">
                <i class="glyphicon glyphicon-save"></i> Descargar Excel
            </button>
        </div>
    </div>

    <div class="ef-por-promotor" id="efPromotorLayout">

        <!-- Columna izquierda: lista de promotores (buscador subió a la barra de filtros de arriba) -->
        <div class="ef-promo-col-izq">
            <div class="ef-promo-lista" id="efPromoLista">
                <div class="ef-vacio">Cargando...</div>
            </div>
        </div>

        <!-- Columna derecha: detalle del promotor -->
        <div class="ef-promo-detalle" id="efPromoDetalle">
            <div class="ef-vacio">Selecciona un promotor.</div>
        </div>

    </div>

    <!-- ══ Panel de auditoría (slide-over) ══ -->
    <div class="ef-auditoria-overlay" id="efAuditoriaOverlay">
        <div class="ef-auditoria-panel" id="efAuditoriaPanel">
            <div class="ef-auditoria-header">
                <div>
                    <div class="ef-auditoria-kicker">Auditoría de agendamiento</div>
                    <div class="ef-auditoria-nombre" id="efAudNombre"></div>
                    <div class="ef-auditoria-sub" id="efAudSub"></div>
                    <div class="ef-auditoria-pdv" id="efAudPdv"></div>
                </div>
                <button type="button" class="ef-auditoria-close" id="efAudClose" aria-label="Cerrar">&times;</button>
            </div>
            <div class="ef-auditoria-badgerow">
                <span class="ef-fase-badge" id="efAudFaseBadge"></span>
                <span class="ef-auditoria-dias" id="efAudDias"></span>
            </div>
            <div class="ef-auditoria-columns" id="efAuditoriaColumnas">
                <div class="ef-auditoria-body">
                    <div class="ef-auditoria-seccion-titulo">Línea de tiempo por fase</div>
                    <div id="efAudTimeline"></div>

                    <div class="ef-auditoria-divider"></div>

                    <div class="ef-auditoria-seccion-titulo">Historial de proformas del analista</div>
                    <div id="efAudHistorial"></div>
                </div>

                <!-- Financiamiento: solo se llena/muestra cuando el agendamiento
                     ya tiene una fila de factura (foto_factura) — ver
                     renderFinanciamiento() en factura.js. -->
                <div class="ef-financiamiento" id="efFinanciamiento"></div>
            </div>
        </div>
    </div>

    <!-- Lightbox de foto (timeline, sin info adicional) -->
    <div class="ef-lightbox" id="efLightbox">
        <img src="" alt="Foto" id="efLightboxImg">
    </div>

    <!-- Modal de detalle de pago (foto + mes + monto + fecha + observación) -->
    <div class="ef-pago-modal-overlay" id="efPagoModalOverlay">
        <div class="ef-pago-modal-card">
            <button type="button" class="ef-pago-modal-close" id="efPagoModalClose" aria-label="Cerrar">&times;</button>
            <div class="ef-pago-modal-foto">
                <img src="" alt="Foto de pago" id="efPagoModalImg">
            </div>
            <div class="ef-pago-modal-info">
                <div class="ef-pago-modal-top">
                    <span class="ef-pago-modal-mes" id="efPagoModalMes"></span>
                    <span class="ef-pago-modal-monto" id="efPagoModalMonto"></span>
                </div>
                <div class="ef-pago-modal-fecha" id="efPagoModalFecha"></div>
                <div class="ef-pago-modal-obs-titulo">Observación</div>
                <div class="ef-pago-modal-obs" id="efPagoModalObs"></div>
            </div>
        </div>
    </div>

</div>

<script src="<?= htmlspecialchars($factura_assets, ENT_QUOTES) ?>/factura.js?v=<?= $factura_js_v ?>"></script>
