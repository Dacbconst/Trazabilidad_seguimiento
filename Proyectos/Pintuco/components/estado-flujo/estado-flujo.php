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

<script src="<?= htmlspecialchars($ef_assets, ENT_QUOTES) ?>/estado-flujo.js?v=<?= $ef_js_v ?>"></script>
