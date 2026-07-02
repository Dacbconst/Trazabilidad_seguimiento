<?php
/**
 * COMPONENTE: flujo-comercial — Pipeline comercial por fases y por promotor.
 * Solo lectura — cero acciones de escritura en este módulo.
 */
$modulo_base  = basename((string) $cuenta_dir);
$flujo_dir    = __DIR__;
$flujo_assets = $modulo_base . '/components/flujo-comercial/assets';
$flujo_css_v  = @filemtime($flujo_dir . '/assets/flujo.css') ?: time();
$flujo_js_v   = @filemtime($flujo_dir . '/assets/flujo.js') ?: time();
?>
<link rel="stylesheet" href="<?= htmlspecialchars($flujo_assets, ENT_QUOTES) ?>/flujo.css?v=<?= $flujo_css_v ?>">

<div id="flujoComercialApp"
     data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/"
     data-modulo-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>">

    <!-- Sub-tabs internos usando el stepper del layout global -->
    <ul class="stepper" id="flujoTabs">
        <li class="active"><a href="#" data-flujo-tab="pipeline">Por Fase</a></li>
        <li><a href="#" data-flujo-tab="promotor">Por Promotor</a></li>
    </ul>

    <!-- ══ Tab: Pipeline ══ -->
    <div class="flujo-pane active" id="flujoPane-pipeline">

        <div class="mod-filtros">
            <div class="filter-group is-busqueda">
                <label>PDV o empresa</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="flujoBusqueda" placeholder="Buscar PDV o empresa...">
                    <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
                </div>
            </div>
            <div class="filter-group">
                <label>Mercaderista</label>
                <input type="text" class="form-control" id="flujoFiltroPromotor" placeholder="Buscar mercaderista...">
            </div>
            <div class="mod-filtros-extra">
                <button type="button" class="btn btn-actualizar" id="flujoActualizar">
                    <i class="glyphicon glyphicon-refresh"></i> Actualizar
                </button>
            </div>
        </div>

        <!-- Tarjetas de fase (selectores) -->
        <div class="flujo-fase-cards" id="flujoFaseCards">
            <div class="flujo-vacio">Cargando...</div>
        </div>

        <!-- Detalle de la fase seleccionada -->
        <div class="flujo-fase-detalle" id="flujoFaseDetalle">
            <div class="flujo-vacio">Selecciona una fase para ver el detalle.</div>
        </div>

    </div>

    <!-- ══ Tab: Por Promotor ══ -->
    <div class="flujo-pane" id="flujoPane-promotor">

        <div class="mod-filtros" style="margin-bottom:14px">
            <div class="mod-filtros-extra">
                <button type="button" class="btn btn-actualizar" id="flujoActualizarProm">
                    <i class="glyphicon glyphicon-refresh"></i> Actualizar
                </button>
            </div>
        </div>

        <div class="flujo-por-promotor" id="flujoPromotorLayout">

            <!-- Columna izquierda: buscador + lista de promotores -->
            <div class="flujo-promo-col-izq">
                <div class="flujo-promo-buscar-wrap">
                    <input type="text" class="form-control" id="flujoPromoSearch" placeholder="Buscar mercaderista...">
                </div>
                <div class="flujo-promo-lista" id="flujoPromoLista">
                    <div class="flujo-vacio">Cargando...</div>
                </div>
            </div>

            <!-- Columna derecha: detalle del promotor -->
            <div class="flujo-promo-detalle" id="flujoPromoDetalle">
                <div class="flujo-vacio">Selecciona un promotor.</div>
            </div>

        </div>

    </div>

</div>

<script src="<?= htmlspecialchars($flujo_assets, ENT_QUOTES) ?>/flujo.js?v=<?= $flujo_js_v ?>"></script>
