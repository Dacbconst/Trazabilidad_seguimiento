<?php
/**
 * COMPONENTE: principal/principal.php — Dashboard ejecutivo (datos reales).
 * Incluido desde partials/tab-avance.php. JS carga datos via get_dashboard.php.
 */
$modulo_base      = basename((string) $cuenta_dir);
$principal_dir    = __DIR__;
$principal_assets = $modulo_base . '/components/principal/assets';
$principal_css_v  = @filemtime($principal_dir . '/assets/principal.css') ?: time();
$principal_js_v   = @filemtime($principal_dir . '/assets/principal.js') ?: time();
?>
<link rel="stylesheet" href="<?= htmlspecialchars($principal_assets, ENT_QUOTES) ?>/principal.css?v=<?= $principal_css_v ?>">

<div id="principalApp"
     data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/"
     data-modulo-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>">

    <div class="dash-header">
        <div>
            <h2>Dashboard</h2>
            <p>Resumen ejecutivo del flujo comercial</p>
        </div>
        <button type="button" class="btn btn-actualizar" id="dashActualizar">
            <i class="glyphicon glyphicon-refresh"></i> Actualizar
        </button>
    </div>

    <!-- KPI cards (4) -->
    <div class="dash-kpis">
        <div class="dash-kpi is-loading">
            <span class="dash-kpi-label">PDVs en seguimiento</span>
            <span class="dash-kpi-valor" id="kpiTotal">—</span>
        </div>
        <div class="dash-kpi is-loading">
            <span class="dash-kpi-label">Monto facturado</span>
            <span class="dash-kpi-valor is-verde" id="kpiFacturado">—</span>
        </div>
        <div class="dash-kpi is-loading">
            <span class="dash-kpi-label">Monto negociado</span>
            <span class="dash-kpi-valor is-azul" id="kpiNegociado">—</span>
        </div>
        <div class="dash-kpi is-loading">
            <span class="dash-kpi-label">Tasa de conversión</span>
            <span class="dash-kpi-valor is-ambar" id="kpiConversion">—</span>
        </div>
    </div>

    <!-- Paneles: Embudo + Top promotores -->
    <div class="dash-paneles">

        <div class="dash-panel">
            <div class="dash-panel-titulo">Embudo por fases</div>
            <div id="dashFunnel" class="dash-funnel">
                <div class="dash-cargando">Cargando...</div>
            </div>
        </div>

        <div class="dash-panel">
            <div class="dash-panel-titulo">Top promotores</div>
            <div id="dashPromotores">
                <div class="dash-cargando">Cargando...</div>
            </div>
        </div>

    </div>

</div>

<script src="<?= htmlspecialchars($principal_assets, ENT_QUOTES) ?>/principal.js?v=<?= $principal_js_v ?>"></script>
