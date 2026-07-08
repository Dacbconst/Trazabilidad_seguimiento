<?php
/**
 * COMPONENTE: principal/principal.php — Dashboard ejecutivo (datos reales).
 * Incluido desde partials/tab-avance.php. JS carga datos crudos vía
 * get_dashboard.php y calcula KPIs/embudo/promotores en el cliente,
 * aplicando los filtros de Promotor y Período — mismo criterio que
 * proforma.js / contactados.js (fetch una vez, filtrar y agregar en JS).
 */
$modulo_base      = basename((string) $cuenta_dir);
$principal_dir    = __DIR__;
$principal_assets = $modulo_base . '/components/principal/assets';
$principal_css_v  = @filemtime($principal_dir . '/assets/principal.css') ?: time();
$principal_js_v   = @filemtime($principal_dir . '/assets/principal.js') ?: time();
?>
<!-- Inter ya se carga global en style.css; solo falta IBM Plex Mono para
     los montos y porcentajes del rediseño de las tarjetas KPI. -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&display=swap">
<link rel="stylesheet" href="<?= htmlspecialchars($principal_assets, ENT_QUOTES) ?>/principal.css?v=<?= $principal_css_v ?>">

<div id="principalApp"
     data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/"
     data-modulo-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>">

    <!-- Filtros unificados (.mod-filtros definido en style.css global) -->
    <div class="mod-filtros">
        <div class="filter-group">
            <label>Promotor</label>
            <select class="form-control" id="dashFiltroPromotor">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Período</label>
            <select class="form-control" id="dashFiltroPeriodo">
                <option value="">Cualquier fecha</option>
                <option value="mes_actual">Este mes</option>
                <option value="mes_anterior">Mes anterior</option>
                <option value="ultimos_3">Últimos 3 meses</option>
            </select>
        </div>
        <div class="mod-filtros-extra">
            <button type="button" class="btn btn-mod-actualizar" id="dashActualizar">
                <i class="glyphicon glyphicon-refresh"></i> Actualizar
            </button>
        </div>
    </div>

    <!-- KPI cards (4) -->
    <div class="dash-kpis">
        <div class="dash-kpi is-loading is-azul">
            <div class="dash-kpi-head">
                <span class="dash-kpi-icono">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M3 7h18M3 12h18M3 17h18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"></path></svg>
                </span>
                <span class="dash-kpi-label">PDVs en seguimiento</span>
            </div>
            <span class="dash-kpi-valor" id="kpiTotal">—</span>
        </div>

        <div class="dash-kpi is-loading is-verde">
            <div class="dash-kpi-head">
                <span class="dash-kpi-icono">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </span>
                <span class="dash-kpi-label">Monto facturado</span>
            </div>
            <span class="dash-kpi-valor" id="kpiFacturado">—</span>
        </div>

        <div class="dash-kpi is-loading is-violeta">
            <div class="dash-kpi-head">
                <span class="dash-kpi-icono">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M22 7 13.5 15.5 8.5 10.5 2 17" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path><path d="M16 7h6v6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </span>
                <span class="dash-kpi-label">Monto negociado</span>
            </div>
            <span class="dash-kpi-valor" id="kpiNegociado">—</span>
        </div>

        <div class="dash-kpi is-loading is-rojo">
            <div class="dash-kpi-head">
                <span class="dash-kpi-icono">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L14.71 3.86a2 2 0 0 0-3.42 0Z" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </span>
                <span class="dash-kpi-label">PDVs estancados</span>
            </div>
            <span class="dash-kpi-valor" id="kpiEstancados">—</span>
        </div>
    </div>

    <!-- Paneles: Embudo + Top promotores -->
    <div class="dash-paneles">

        <div class="dash-panel">
            <div class="dash-panel-header">
                <span class="dash-panel-titulo">Embudo por fases</span>
                <span class="dash-panel-total" id="dashFunnelTotal">—</span>
            </div>
            <div id="dashFunnel" class="dash-funnel">
                <div class="dash-cargando">Cargando...</div>
            </div>
            <div class="dash-funnel-footer">
                <span>Tasa de conversión global (Fase 1 → Fase 5)</span>
                <span class="dash-funnel-conversion" id="kpiConversion">—</span>
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
