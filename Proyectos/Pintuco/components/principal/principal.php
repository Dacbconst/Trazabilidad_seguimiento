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
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"></path><circle cx="12" cy="9.5" r="2.4" stroke="currentColor" stroke-width="2.2"></circle></svg>
                </span>
                <span class="dash-kpi-label">Puntos de venta</span>
            </div>
            <span class="dash-kpi-valor" id="kpiPdvs">—</span>
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

        <!-- Clickeable: abre el detalle de cuáles son (ver principal.js
             abrirModalVencidas). -->
        <button type="button" class="dash-kpi is-loading is-rojo is-clickable" id="kpiVencidasCard">
            <div class="dash-kpi-head">
                <span class="dash-kpi-icono">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2.2"></rect><path d="M3 9h18M8 2v4M16 2v4M9.5 14.5l5 5M14.5 14.5l-5 5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"></path></svg>
                </span>
                <span class="dash-kpi-label">Visitas vencidas</span>
            </div>
            <span class="dash-kpi-valor" id="kpiVencidas">—</span>
            <span class="dash-kpi-ver-detalle">Ver detalle →</span>
        </button>
    </div>

    <!-- Modal: detalle de Visitas vencidas (fecha_agendamiento ya pasó y no
         está cancelada/completada — mismo contrato de estado_agenda que usa
         Agendamientos) — ver abrirModalVencidas en principal.js. -->
    <div class="dash-vencidas-overlay" id="dashVencidasOverlay">
        <div class="dash-vencidas-card">
            <div class="dash-vencidas-header">
                <div>
                    <div class="dash-vencidas-titulo">Visitas vencidas</div>
                    <div class="dash-vencidas-sub" id="dashVencidasCount"></div>
                </div>
                <button type="button" class="dash-vencidas-close" id="dashVencidasClose" aria-label="Cerrar">&times;</button>
            </div>
            <div class="dash-vencidas-nota">
                Fecha agendada ya pasada y sin reagendar, cancelar ni completar — mismos filtros de Promotor/Período que el resto del dashboard.
            </div>
            <div class="dash-vencidas-body" id="dashVencidasBody"></div>
        </div>
    </div>

    <!-- Paneles: Embudo + Top promotores + Top PDV -->
    <div class="dash-paneles">

        <div class="dash-panel dash-panel-funnel">
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

        <div class="dash-panel dash-panel-promo">
            <div class="dash-panel-titulo">Top promotores</div>
            <div id="dashPromotores">
                <div class="dash-cargando">Cargando...</div>
            </div>
        </div>

        <div class="dash-panel dash-panel-pdv">
            <div class="dash-panel-titulo">Top PDV</div>
            <div id="dashTopPdv">
                <div class="dash-cargando">Cargando...</div>
            </div>
        </div>

    </div>

</div>

<script src="<?= htmlspecialchars($principal_assets, ENT_QUOTES) ?>/principal.js?v=<?= $principal_js_v ?>"></script>
