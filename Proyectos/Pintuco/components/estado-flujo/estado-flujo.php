<?php
/**
 * COMPONENTE: estado-flujo — Seguimiento de agendamientos por fase y por
 * promotor. Solo lectura — cero acciones de escritura en este módulo.
 */
$modulo_base = basename((string) $cuenta_dir);
$ef_dir      = __DIR__;
$ef_assets   = $modulo_base . '/components/estado-flujo/assets';
$ef_css_v    = @filemtime($ef_dir . '/assets/estado-flujo.css') ?: time();
$ef_js_v     = @filemtime($ef_dir . '/assets/estado-flujo.js') ?: time();
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars($ef_assets, ENT_QUOTES) ?>/estado-flujo.css?v=<?= $ef_css_v ?>">

<div id="estadoFlujoApp"
     data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/"
     data-modulo-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>">

    <!-- Sub-tabs internos: pill morado propio del módulo (no el stepper azul global) -->
    <div class="ef-tabs" id="efTabs">
        <a href="#" class="ef-tab-btn active" data-ef-tab="fase">Por Fase</a>
        <a href="#" class="ef-tab-btn" data-ef-tab="promotor">Por Promotor</a>
    </div>

    <!-- ══ Tab: Por Fase (kanban de 5 columnas simultáneas) ══ -->
    <div class="ef-pane active" id="efPane-fase">

        <div class="mod-filtros">
            <div class="filter-group is-busqueda">
                <label>PDV o empresa</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="efBusqueda" placeholder="Buscar PDV o empresa...">
                    <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
                </div>
            </div>
            <div class="filter-group">
                <label>Promotor</label>
                <input type="text" class="form-control" id="efFiltroPromotor" placeholder="Buscar promotor...">
            </div>
            <div class="mod-filtros-extra">
                <button type="button" class="btn btn-mod-actualizar" id="efActualizar">
                    <i class="glyphicon glyphicon-refresh"></i> Actualizar
                </button>
            </div>
        </div>

        <div class="ef-kanban" id="efKanban">
            <div class="ef-vacio">Cargando...</div>
        </div>

    </div>

    <!-- ══ Tab: Por Promotor ══ -->
    <div class="ef-pane" id="efPane-promotor">

        <div class="mod-filtros" style="margin-bottom:14px">
            <div class="mod-filtros-extra">
                <button type="button" class="btn btn-mod-actualizar" id="efActualizarProm">
                    <i class="glyphicon glyphicon-refresh"></i> Actualizar
                </button>
            </div>
        </div>

        <div class="ef-por-promotor" id="efPromotorLayout">

            <!-- Columna izquierda: buscador + lista de promotores -->
            <div class="ef-promo-col-izq">
                <div class="ef-promo-buscar-wrap">
                    <input type="text" class="form-control" id="efPromoSearch" placeholder="Buscar promotor...">
                </div>
                <div class="ef-promo-lista" id="efPromoLista">
                    <div class="ef-vacio">Cargando...</div>
                </div>
            </div>

            <!-- Columna derecha: detalle del promotor -->
            <div class="ef-promo-detalle" id="efPromoDetalle">
                <div class="ef-vacio">Selecciona un promotor.</div>
            </div>

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
                </div>
                <button type="button" class="ef-auditoria-close" id="efAudClose" aria-label="Cerrar">&times;</button>
            </div>
            <div class="ef-auditoria-badgerow">
                <span class="ef-fase-badge" id="efAudFaseBadge"></span>
                <span class="ef-auditoria-dias" id="efAudDias"></span>
            </div>
            <div class="ef-auditoria-body">
                <div class="ef-auditoria-seccion-titulo">Línea de tiempo por fase</div>
                <div id="efAudTimeline"></div>

                <div class="ef-auditoria-divider"></div>

                <div class="ef-auditoria-seccion-titulo">Historial de proformas del analista</div>
                <div id="efAudHistorial"></div>
            </div>
        </div>
    </div>

    <!-- Lightbox de foto -->
    <div class="ef-lightbox" id="efLightbox">
        <img src="" alt="Foto" id="efLightboxImg">
    </div>

</div>

<script src="<?= htmlspecialchars($ef_assets, ENT_QUOTES) ?>/estado-flujo.js?v=<?= $ef_js_v ?>"></script>
