<?php
/**
 * COMPONENTE: estado-flujo — Kanban de agendamientos por fase (5 columnas
 * simultáneas). Solo lectura — cero acciones de escritura en este módulo.
 *
 * El tab "Por Promotor" (+ panel de auditoría/financiamiento) que vivía
 * acá se separó a su propia sección "Factura" del sidebar (2026-07-13,
 * pedido explícito del usuario) — ver components/factura/factura.php.
 */
$modulo_base = basename((string) $cuenta_dir);
$ef_dir      = __DIR__;
$ef_assets   = $modulo_base . '/components/estado-flujo/assets';
$ef_css_v    = @filemtime($ef_dir . '/assets/estado-flujo.css') ?: time();
$ef_js_v     = @filemtime($ef_dir . '/assets/estado-flujo.js') ?: time();
?>
<link rel="stylesheet" href="<?= htmlspecialchars($ef_assets, ENT_QUOTES) ?>/estado-flujo.css?v=<?= $ef_css_v ?>">

<div id="estadoFlujoApp"
     data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/"
     data-modulo-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>">

    <div class="mod-filtros">
        <div class="filter-group">
            <label>Promotor</label>
            <select class="form-control" id="efFiltroPromotor">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="filter-group">
            <!-- Mismo catálogo de locales que usa "Crear visita" en
                 Agendamientos (get_pdvs.php). -->
            <label>PDV</label>
            <select class="form-control" id="efFiltroPdv">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Empresa</label>
            <select class="form-control" id="efFiltroEmpresa">
                <option value="">Todas</option>
            </select>
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

    <!-- Vista rápida al hacer clic en una tarjeta: resumen de "qué está
         pasando" con ese agendamiento sin salir del kanban — ver
         abrirDetalle() en estado-flujo.js. -->
    <div class="ef-detalle-overlay" id="efDetalleOverlay">
        <div class="ef-detalle-card">
            <div class="ef-detalle-header">
                <div>
                    <div class="ef-detalle-titulo" id="efDetalleTitulo"></div>
                    <div class="ef-detalle-sub" id="efDetalleSub"></div>
                </div>
                <button type="button" class="ef-detalle-close" id="efDetalleClose" aria-label="Cerrar">&times;</button>
            </div>
            <div class="ef-detalle-body" id="efDetalleBody"></div>
            <div class="ef-detalle-footer">
                <a href="#" class="ef-detalle-btn-vermas" id="efDetalleBtnVerMas" style="display:none">Ver más &raquo;</a>
                <button type="button" class="ef-detalle-btn-cerrar" id="efDetalleCerrar">Cerrar</button>
            </div>
        </div>
    </div>

</div>

<script src="<?= htmlspecialchars($ef_assets, ENT_QUOTES) ?>/estado-flujo.js?v=<?= $ef_js_v ?>"></script>
