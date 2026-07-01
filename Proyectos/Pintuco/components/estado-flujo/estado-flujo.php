<?php
/**
 * COMPONENTE: estado-flujo/estado-flujo.php
 * Vista del pipeline comercial: muestra en qué etapa está cada PDV
 * a lo largo del flujo Contactado → Agendado → Visita → Proforma → Negociación → Aprobado.
 */
$modulo_base    = basename((string) $cuenta_dir);
$flujo_dir      = __DIR__;
$flujo_assets   = $modulo_base . '/components/estado-flujo/assets';

$flujo_css_v = @filemtime($flujo_dir . '/assets/flujo.css') ?: time();
$flujo_js_v  = @filemtime($flujo_dir . '/assets/flujo.js')  ?: time();
?>
<link rel="stylesheet" href="<?= htmlspecialchars($flujo_assets, ENT_QUOTES) ?>/flujo.css?v=<?= $flujo_css_v ?>">

<div id="flujoApp"
     data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/"
     data-modulo-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>">

    <div class="flujo-header">
        <div>
            <h2>Estado de Flujo Comercial</h2>
            <p>Seguimiento del pipeline: desde el contacto hasta la aprobación de la proforma.</p>
        </div>
        <span class="flujo-actualizado">Actualizado: <span id="flujoActualizado">—</span></span>
    </div>

    <!-- Pipeline visual -->
    <div class="flujo-pipeline" id="flujoPipeline">
        <div class="flujo-etapa-btn is-activa" data-etapa="">
            <span class="flujo-etapa-nombre">Todos</span>
            <span class="flujo-etapa-count" id="flujoCntTodos">0</span>
        </div>
        <div class="flujo-pipeline-sep"></div>
        <div class="flujo-etapa-btn" data-etapa="agendado">
            <span class="flujo-etapa-nombre">Agendados</span>
            <span class="flujo-etapa-count" id="flujoCntAgendado">0</span>
        </div>
        <div class="flujo-pipeline-sep"></div>
        <div class="flujo-etapa-btn" data-etapa="visita_ok">
            <span class="flujo-etapa-nombre">Visita OK</span>
            <span class="flujo-etapa-count" id="flujoCntVisita">0</span>
        </div>
        <div class="flujo-pipeline-sep"></div>
        <div class="flujo-etapa-btn" data-etapa="proforma">
            <span class="flujo-etapa-nombre">Proforma</span>
            <span class="flujo-etapa-count" id="flujoCntProforma">0</span>
        </div>
        <div class="flujo-pipeline-sep"></div>
        <div class="flujo-etapa-btn" data-etapa="en_negociacion">
            <span class="flujo-etapa-nombre">Negociación</span>
            <span class="flujo-etapa-count" id="flujoCntNegociacion">0</span>
        </div>
        <div class="flujo-pipeline-sep"></div>
        <div class="flujo-etapa-btn is-positivo" data-etapa="aprobado">
            <span class="flujo-etapa-nombre">Aprobados</span>
            <span class="flujo-etapa-count" id="flujoCntAprobado">0</span>
        </div>
        <div class="flujo-pipeline-sep"></div>
        <div class="flujo-etapa-btn is-finalizada" data-etapa="venta_finalizada">
            <span class="flujo-etapa-nombre">Venta finalizada</span>
            <span class="flujo-etapa-count" id="flujoCntFinalizada">0</span>
        </div>
        <div class="flujo-pipeline-negativos">
            <span class="flujo-etapa-btn is-negativo" data-etapa="vencida">
                <span class="flujo-etapa-nombre">Vencidos</span>
                <span class="flujo-etapa-count" id="flujoCntVencida">0</span>
            </span>
            <span class="flujo-etapa-btn is-negativo" data-etapa="cancelada">
                <span class="flujo-etapa-nombre">Cancelados</span>
                <span class="flujo-etapa-count" id="flujoCntCancelada">0</span>
            </span>
            <span class="flujo-etapa-btn is-negativo" data-etapa="rechazado">
                <span class="flujo-etapa-nombre">Rechazados</span>
                <span class="flujo-etapa-count" id="flujoCntRechazado">0</span>
            </span>
        </div>
    </div>

    <!-- KPI conversión -->
    <div class="flujo-kpis" id="flujoKpis">
        <div class="flujo-kpi">
            <span class="flujo-kpi-label">En pipeline activo</span>
            <span class="flujo-kpi-valor" id="flujoKpiActivo">0</span>
        </div>
        <div class="flujo-kpi">
            <span class="flujo-kpi-label">Tasa de conversión</span>
            <span class="flujo-kpi-valor is-verde" id="flujoKpiConversion">0%</span>
        </div>
        <div class="flujo-kpi">
            <span class="flujo-kpi-label">Promedio días en flujo</span>
            <span class="flujo-kpi-valor" id="flujoKpiDias">—</span>
        </div>
        <div class="flujo-kpi">
            <span class="flujo-kpi-label">Caídas (venc. + canc. + rech.)</span>
            <span class="flujo-kpi-valor is-rojo" id="flujoKpiCaidas">0</span>
        </div>
    </div>

    <!-- Filtros unificados (.mod-filtros definido en style.css global) -->
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
            <select class="form-control" id="flujoFiltroPromotor">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Técnico asignado</label>
            <select class="form-control" id="flujoFiltroTecnico">
                <option value="">Todos</option>
            </select>
        </div>
    </div>

    <!-- Tabla -->
    <div class="flujo-scroll">
        <table class="flujo-table">
            <thead>
                <tr>
                    <th>PDV / Empresa</th>
                    <th>Promotor</th>
                    <th>Técnico</th>
                    <th>Fecha visita</th>
                    <th>Etapa actual</th>
                    <th>Días en flujo</th>
                    <th>Factura</th>
                </tr>
            </thead>
            <tbody id="flujoTbody">
                <tr><td colspan="6" class="flujo-vacio">Cargando...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="flujo-paginacion">
        <span class="flujo-paginacion-info" id="flujoPageInfo"></span>
        <div class="flujo-paginacion-controles">
            <button type="button" class="flujo-page-btn" id="flujoPagAnterior">&laquo; Anterior</button>
            <span class="flujo-paginacion-pagina" id="flujoPaginaActual"></span>
            <button type="button" class="flujo-page-btn" id="flujoPagSiguiente">Siguiente &raquo;</button>
        </div>
    </div>

    <div class="flujo-toast" id="flujoToast"></div>
</div>

<script src="<?= htmlspecialchars($flujo_assets, ENT_QUOTES) ?>/flujo.js?v=<?= $flujo_js_v ?>"></script>
